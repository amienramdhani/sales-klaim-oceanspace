<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_pin')->default(false)->after('password');
            $table->string('initial_pin', 32)->nullable()->after('must_change_pin');
            $table->timestamp('pin_changed_at')->nullable()->after('initial_pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_pin', 'initial_pin', 'pin_changed_at']);
        });
    }
};
