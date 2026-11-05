<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes_produccion')->restrictOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->decimal('cantidad_solicitada', 12, 3);
            $table->decimal('cantidad_preparada', 12, 3)->default(0);
        });

        // Se pide una cantidad positiva y lo preparado nunca es negativo ni supera lo pedido.
        DB::statement('ALTER TABLE orden_lineas ADD CONSTRAINT chk_orden_lineas_solicitada_positiva CHECK (cantidad_solicitada > 0)');
        DB::statement('ALTER TABLE orden_lineas ADD CONSTRAINT chk_orden_lineas_preparada_valida CHECK (cantidad_preparada >= 0 AND cantidad_preparada <= cantidad_solicitada)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_lineas');
    }
};
