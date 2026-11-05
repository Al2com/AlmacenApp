<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock', function (Blueprint $table) {
            // Sin id propio: el saldo se identifica por la pareja lote + ubicación.
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('cantidad_reservada', 12, 3)->default(0);
            $table->timestamps();

            $table->primary(['lote_id', 'ubicacion_id']);
        });

        // El stock nunca es negativo y lo reservado nunca supera lo disponible.
        DB::statement('ALTER TABLE stock ADD CONSTRAINT chk_stock_cantidad_no_negativa CHECK (cantidad >= 0)');
        DB::statement('ALTER TABLE stock ADD CONSTRAINT chk_stock_reservada_no_negativa CHECK (cantidad_reservada >= 0)');
        DB::statement('ALTER TABLE stock ADD CONSTRAINT chk_stock_reservada_no_supera_cantidad CHECK (cantidad_reservada <= cantidad)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock');
    }
};
