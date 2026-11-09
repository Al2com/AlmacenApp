<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_produccion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('estado');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // La orden cambia de estado a lo largo de su vida, así que lleva
            // created_at y updated_at (a diferencia del registro inmutable de movimientos).
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_produccion');
    }
};
