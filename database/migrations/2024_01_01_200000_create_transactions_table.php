<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('revitalization_projects')->cascadeOnDelete();
            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();
            $table->string('transaction_number')->unique();
            $table->date('transaction_date');
            $table->enum('type', [
                'penerimaan_dana',
                'tarik_tunai',
                'setor_tunai',
                'belanja_tunai',
                'belanja_transfer',
                'pungut_pajak',
                'setor_pajak',
                'bunga_bank',
                'biaya_bank'
            ]);
            $table->enum('payment_method', ['kas_tunai', 'bank_transfer'])->default('kas_tunai');
            $table->text('description');
            $table->string('recipient_name')->nullable();
            $table->text('recipient_address')->nullable();
            $table->decimal('amount', 15, 2); // Nilai Transaksi Bruto
            $table->boolean('has_tax')->default(false);
            $table->string('tax_type')->nullable(); // PPN, PPh 21, PPh 22, PPh 23, PPh 4(2)
            $table->decimal('tax_ppn', 15, 2)->default(0);
            $table->decimal('tax_pph21', 15, 2)->default(0);
            $table->decimal('tax_pph22', 15, 2)->default(0);
            $table->decimal('tax_pph23', 15, 2)->default(0);
            $table->decimal('tax_pph4_2', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0); // Yang dibayarkan bersih
            $table->string('tax_ntpn')->nullable(); // NTPN / Bukti Setor Pajak
            $table->date('tax_payment_date')->nullable();
            $table->enum('tax_status', ['none', 'dipungut', 'disetor'])->default('none');
            $table->string('receipt_file')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

