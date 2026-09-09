<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create fuel_prices table
        if (!Schema::hasTable('fuel_prices')) {
            Schema::create('fuel_prices', function (Blueprint $table) {
                $table->id();
                $table->string('fuel_type')->unique();
                $table->decimal('price_per_liter', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }

        // 2. Add return transfer fields to claims table
        Schema::table('claims', function (Blueprint $table) {
            if (!Schema::hasColumn('claims', 'return_transfer_proof')) {
                $table->string('return_transfer_proof')->nullable();
            }
            if (!Schema::hasColumn('claims', 'return_transfer_amount')) {
                $table->decimal('return_transfer_amount', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('claims', 'return_transferred_at')) {
                $table->timestamp('return_transferred_at')->nullable();
            }
            if (!Schema::hasColumn('claims', 'return_transfer_by_id')) {
                $table->foreignId('return_transfer_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('claims', 'return_transfer_notes')) {
                $table->text('return_transfer_notes')->nullable();
            }
        });

        // 3. Ensure SUPERADMIN role exists
        $superAdminRole = Role::firstOrCreate(
            ['code' => 'SUPERADMIN'],
            [
                'name' => 'Super Administrator',
                'description' => 'Akses penuh ke semua modul sistem termasuk Master Data',
                'entertain_budget' => 0,
                'operational_budget' => 0,
                'status' => 'active',
            ]
        );

        // 4. Assign admin@salesklaim.com to SUPERADMIN
        $adminUser = User::where('email', 'admin@salesklaim.com')->first();
        if ($adminUser) {
            $adminUser->role_id = $superAdminRole->id;
            $adminUser->save();
        }

        // 5. Seed default fuel prices
        if (Schema::hasTable('fuel_prices')) {
            \Illuminate\Support\Facades\DB::table('fuel_prices')->updateOrInsert(
                ['fuel_type' => 'Pertalite'],
                ['price_per_liter' => 10000, 'is_active' => true, 'notes' => 'Harga Resmi Pertalite (Subsidi)', 'updated_at' => now(), 'created_at' => now()]
            );
            \Illuminate\Support\Facades\DB::table('fuel_prices')->updateOrInsert(
                ['fuel_type' => 'Pertamax'],
                ['price_per_liter' => 12300, 'is_active' => true, 'notes' => 'Harga Resmi Pertamax Non-Subsidi', 'updated_at' => now(), 'created_at' => now()]
            );
            \Illuminate\Support\Facades\DB::table('fuel_prices')->updateOrInsert(
                ['fuel_type' => 'Solar'],
                ['price_per_liter' => 6800, 'is_active' => true, 'notes' => 'Harga Resmi Biosolar', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_prices');

        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign(['return_transfer_by_id']);
            $table->dropColumn([
                'return_transfer_proof',
                'return_transfer_amount',
                'return_transferred_at',
                'return_transfer_by_id',
                'return_transfer_notes',
            ]);
        });
    }
};
