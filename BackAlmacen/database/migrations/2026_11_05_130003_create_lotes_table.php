<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->string('codigo_lote');
            $table->date('fecha_recepcion');
            // Nullable: los artículos sin caducidad tienen lotes sin fecha de caducidad.
            $table->date('caducidad')->nullable();
            $table->timestamps();

            // El código de lote es único dentro de cada artículo.
            $table->unique(['articulo_id', 'codigo_lote']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
