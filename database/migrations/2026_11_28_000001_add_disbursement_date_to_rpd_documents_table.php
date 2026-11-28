<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rpd_documents', function (Blueprint $table) {
            $table->date('disbursement_date')->nullable()->after('term_stage');
        });
    }

    public function down(): void
    {
        Schema::table('rpd_documents', function (Blueprint $table) {
            $table->dropColumn('disbursement_date');
        });
    }
};
