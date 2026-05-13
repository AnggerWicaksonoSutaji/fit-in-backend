<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paket_langganans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_paket');
            $table->decimal('harga', 10, 2);
            $table->integer('durasi_hari'); // durasi langganan dalam hari
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paket_langganans');
    }
};
