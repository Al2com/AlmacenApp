<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas_picking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_linea_id')->constrained('orden_lineas')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            // Orden de recogida por ruta (pasillo, estantería, nivel).
            $table->unsignedInteger('secuencia');
            $table->string('estado')->default('pendiente');
            $table->foreignId('asignada_a')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmada_at')->nullable();
        });

        DB::statement('ALTER TABLE tareas_picking ADD CONSTRAINT chk_tareas_picking_cantidad_positiva CHECK (cantidad > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_picking');
    }
};
