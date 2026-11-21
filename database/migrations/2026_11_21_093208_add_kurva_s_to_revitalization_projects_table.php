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
        Schema::table('revitalization_projects', function (Blueprint $table) {
            $table->string('kurva_s_path')->nullable()->after('description');
            $table->string('kurva_s_filename')->nullable()->after('kurva_s_path');
            $table->json('kurva_s_data')->nullable()->after('kurva_s_filename');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revitalization_projects', function (Blueprint $table) {
            $table->dropColumn(['kurva_s_path', 'kurva_s_filename', 'kurva_s_data']);
        });
    }
};
