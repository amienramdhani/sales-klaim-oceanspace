<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "Checking columns...\n";
if (!Schema::hasColumn('claims', '_uid')) {
    Schema::table('claims', function (Blueprint $table) {
        $table->string('_uid')->nullable()->index()->after('id');
        $table->string('claim_category')->nullable()->default('transport_entertain')->after('_uid');
        $table->decimal('fuel_pertalite_price', 10, 2)->nullable()->default(10000)->after('fuel_type');
        $table->decimal('fuel_pertamax_price', 10, 2)->nullable()->default(12300)->after('fuel_pertalite_price');
        $table->decimal('fuel_liters', 10, 3)->nullable()->default(0)->after('fuel_pertamax_price');
        $table->string('transfer_proof_photo')->nullable()->after('disbursed_at');
        $table->foreignId('finance_approved_by_id')->nullable()->constrained('users')->nullOnDelete()->after('transfer_proof_photo');
        $table->timestamp('finance_approved_at')->nullable()->after('finance_approved_by_id');
        $table->string('ba_phone')->nullable()->after('rejection_reason');
        $table->string('ba_division')->nullable()->after('ba_phone');
        $table->string('ba_approver_1')->nullable()->default('Agus Supangat')->after('ba_division');
        $table->string('ba_approver_2')->nullable()->default('Alb. Maria Adi Nugroho')->after('ba_approver_1');
        $table->string('ba_approver_3')->nullable()->default('Yoga Prima Hadi')->after('ba_approver_2');
        $table->string('ba_wa_proof_photo')->nullable()->after('ba_approver_3');
    });
    echo "Columns added to claims table.\n";
} else {
    echo "Columns already exist.\n";
}

// Ensure FINANCE role exists
$financeRole = \App\Models\Role::firstOrCreate(
    ['code' => 'FINANCE'],
    [
        'name' => 'Finance & Accounting',
        'entertain_budget' => 0,
        'meal_allowance_per_day' => 0,
        'lodging_allowance_per_night' => 0,
        'toll_allowance' => 0,
        'fuel_budget_default' => 0,
        'car_rental_budget_default' => 0,
    ]
);

// Create Finance User
$financeUser = \App\Models\User::firstOrCreate(
    ['email' => 'finance@salesklaim.com'],
    [
        'name' => 'Staff Finance & Pencairan',
        'custom_id' => 'FIN-001',
        'role_id' => $financeRole->id,
        'homebase' => 'Cirebon',
        'password' => bcrypt('password'),
    ]
);
echo "Finance Role & User verified: [{$financeUser->custom_id}] {$financeUser->name}\n";
