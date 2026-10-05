<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('npsn', 20)->unique();
            $table->string('name');
            $table->string('jenjang', 10)->default('SMP'); // SD, SMP, SMA, SMK
            $table->string('status', 15)->default('Negeri'); // Negeri, Swasta
            $table->string('address');
            $table->string('kecamatan')->default('Situbondo');
            $table->string('kabupaten')->default('Situbondo');
            $table->string('provinsi')->default('Jawa Timur');
            $table->string('postal_code', 10)->nullable();
            $table->string('principal_name');
            $table->string('principal_nip')->nullable();
            $table->string('treasurer_name');
            $table->string('treasurer_nip')->nullable();
            $table->string('bank_name')->default('Bank Jatim Cabang Situbondo');
            $table->string('bank_account_number');
            $table->string('bank_account_holder');
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};

