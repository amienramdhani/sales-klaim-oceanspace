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
        Schema::table('budget_types', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->default(0)->after('name');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('signature_image')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_types', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('signature_image');
        });
    }
};
