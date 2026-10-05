<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revitalization_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->year('fiscal_year')->default(2026);
            $table->string('title'); // e.g. "Revitalisasi Ruang Kelas, Perpustakaan & Laboratorium"
            $table->string('funding_source'); // e.g. "DAK Fisik Bidang Pendidikan TA 2026", "Bantuan Pemerintah Revitalisasi SMK"
            $table->string('spk_number'); // No. SPK / SK Penetapan
            $table->date('spk_date');
            $table->decimal('contract_amount', 15, 2); // Nilai Total Pagu Dana
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['perencanaan', 'pelaksanaan', 'selesai', 'evaluasi'])->default('pelaksanaan');
            $table->decimal('physical_progress', 5, 2)->default(0.00); // % Progres fisik lapangan
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revitalization_projects');
    }
};

