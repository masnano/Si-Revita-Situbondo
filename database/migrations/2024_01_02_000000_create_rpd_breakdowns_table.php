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
        Schema::create('rpd_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('project_id')->constrained('revitalization_projects')->onDelete('cascade');
            $table->string('title');
            $table->string('term_stage')->default('Tahap 1');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable(); // xlsx, xls, csv
            $table->decimal('total_budget', 15, 2)->default(0);
            $table->decimal('total_materials', 15, 2)->default(0);
            $table->decimal('total_wages', 15, 2)->default(0);
            $table->decimal('total_equipment', 15, 2)->default(0);
            $table->decimal('total_operational', 15, 2)->default(0);
            $table->decimal('total_tax_estimated', 15, 2)->default(0);
            $table->decimal('total_net_estimated', 15, 2)->default(0);
            $table->integer('total_items_count')->default(0);
            $table->enum('status', ['draft', 'analyzed', 'posted_to_bku'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rpd_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rpd_document_id')->constrained('rpd_documents')->onDelete('cascade');
            $table->foreignId('project_id')->constrained('revitalization_projects')->onDelete('cascade');
            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->integer('row_number')->default(1);
            $table->string('item_code')->nullable();
            $table->enum('category', ['bahan', 'upah', 'alat', 'operasional'])->default('bahan');
            $table->string('item_name');
            $table->string('specification')->nullable();
            $table->decimal('volume', 12, 2)->default(1);
            $table->string('unit')->default('Unit');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->date('planned_date');
            $table->string('supplier_name')->nullable();
            $table->string('supplier_npwp')->nullable();
            $table->enum('payment_method', ['belanja_tunai', 'belanja_transfer'])->default('belanja_tunai');
            $table->string('bku_number')->nullable();
            $table->text('bku_description')->nullable();
            $table->boolean('has_tax')->default(false);
            $table->decimal('tax_ppn', 15, 2)->default(0);
            $table->decimal('tax_pph22', 15, 2)->default(0);
            $table->decimal('tax_pph21', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->boolean('is_posted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rpd_items');
        Schema::dropIfExists('rpd_documents');
    }
};

