<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('revitalization_projects')->cascadeOnDelete();
            $table->string('code')->nullable(); // e.g. "5.2.2.01.01"
            $table->string('category'); // "Bahan/Material", "Upah Tenaga Kerja", "Peralatan/Sewa", "Manajemen/Operasional", "Perencanaan/Pengawasan"
            $table->string('name'); // Uraian pekerjaan / item
            $table->decimal('volume', 10, 2)->default(1);
            $table->string('unit')->default('paket'); // m3, sak, oh, unit, dll
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};

