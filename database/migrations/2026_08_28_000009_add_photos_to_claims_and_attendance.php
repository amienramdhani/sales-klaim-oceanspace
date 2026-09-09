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
        Schema::table('claims', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('city');
        });

        Schema::table('attendance_docs', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn('photos');
        });

        Schema::table('attendance_docs', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
