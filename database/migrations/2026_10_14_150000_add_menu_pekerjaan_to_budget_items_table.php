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
        Schema::table('budget_items', function (Blueprint $table) {
            $table->string('menu_pekerjaan')->default('Rehabilitasi Ruang Kelas')->index()->after('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropColumn('menu_pekerjaan');
        });
    }
};

