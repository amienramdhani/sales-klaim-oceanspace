<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyncEmployeeUsersCommand extends Command
{
    protected $signature = 'app:sync-employee-users {--reset-pin : Reset PIN for all users linked to employees to initial PIN}';
    protected $description = 'Sync all employees to user accounts with NIK (custom_id) and auto-generated 6-digit initial PIN';

    public function handle(): int
    {
        $this->info('Starting Employee to User synchronization...');

        $employees = Employee::with('role')->get();
        $created = 0;
        $updated = 0;

        foreach ($employees as $employee) {
            $nik = trim($employee->custom_id ?? '');
            if (empty($nik)) {
                $this->warn("Employee #{$employee->id} ({$employee->name}) has no NIK (custom_id), skipping.");
                continue;
            }

            // Find existing user by employee_id or custom_id or email
            $user = User::where('employee_id', $employee->id)
                ->orWhere('custom_id', $nik)
                ->first();

            $pin = str_pad((string)mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);

            // Determine role_id from employee
            $roleId = $employee->role_id;
            if (!$roleId && !empty($employee->position)) {
                $posUpper = strtoupper($employee->position);
                $matchedRole = Role::where('name', 'like', "%{$posUpper}%")
                    ->orWhere('code', 'like', "%{$posUpper}%")
                    ->first();
                if ($matchedRole) {
                    $roleId = $matchedRole->id;
                }
            }

            $email = $employee->email;
            if (empty($email)) {
                $slug = Str::slug($employee->name, '.');
                $email = "{$slug}@msi.co.id";
            }

            if (!$user) {
                // Ensure unique email
                $baseEmail = $email;
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $parts = explode('@', $baseEmail);
                    $email = $parts[0] . $counter . '@' . ($parts[1] ?? 'msi.co.id');
                    $counter++;
                }

                $user = User::create([
                    'name' => $employee->name,
                    'custom_id' => $nik,
                    'email' => $email,
                    'password' => Hash::make($pin),
                    'must_change_pin' => true,
                    'initial_pin' => $pin,
                    'employee_id' => $employee->id,
                    'role_id' => $roleId,
                    'homebase' => $employee->homebase ?? 'Purwokerto',
                    'region' => $employee->region ?? 'PURWOKERTO',
                    'managed_regions' => !empty($employee->region) ? [strtoupper(trim($employee->region))] : ['PURWOKERTO'],
                ]);

                $this->line("<info>[CREATED]</info> User for {$employee->name} | NIK: <comment>{$nik}</comment> | PIN: <comment>{$pin}</comment> | Email: {$email}");
                $created++;
            } else {
                $updates = [];
                if ($user->custom_id !== $nik) {
                    $updates['custom_id'] = $nik;
                }
                if ($user->employee_id !== $employee->id) {
                    $updates['employee_id'] = $employee->id;
                }
                if ($roleId && $user->role_id !== $roleId) {
                    $updates['role_id'] = $roleId;
                }
                if (empty($user->region) && !empty($employee->region)) {
                    $updates['region'] = $employee->region;
                    $updates['managed_regions'] = [strtoupper(trim($employee->region))];
                }

                if ($this->option('reset-pin')) {
                    $updates['password'] = Hash::make($pin);
                    $updates['initial_pin'] = $pin;
                    $updates['must_change_pin'] = true;
                    $this->line("<comment>[RESET PIN]</comment> User {$user->name} | NIK: {$nik} | New PIN: {$pin}");
                }

                if (!empty($updates)) {
                    $user->update($updates);
                    $updated++;
                }
            }
        }

        $this->info("Sync completed! Created: {$created}, Updated: {$updated}");
        return Command::SUCCESS;
    }
}
