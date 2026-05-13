<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('jenis_program', ['Bulking', 'Cutting', 'Maintenance']);
            $table->integer('tdee');
            $table->integer('target_kalori');
            $table->integer('protein_g');
            $table->integer('karbo_g');
            $table->integer('lemak_g');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
