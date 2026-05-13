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
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('paket_langganan_id')->constrained('paket_langganans')->onDelete('cascade');
            $table->date('tgl_bayar');
            $table->enum('status_bayar', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('status_langganan', ['active', 'expired'])->default('active');
            $table->date('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
