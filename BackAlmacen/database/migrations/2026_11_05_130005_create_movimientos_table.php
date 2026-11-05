<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            // Origen y destino son opcionales: una recepción no tiene origen y una baja no tiene destino.
            $table->foreignId('ubicacion_origen_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->foreignId('ubicacion_destino_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // La clave ajena a tareas_picking se añade en la fase 2, cuando exista esa tabla.
            $table->unsignedBigInteger('tarea_picking_id')->nullable();
            $table->string('motivo')->nullable();
            // Registro inmutable: solo fecha de creación, nunca se actualiza.
            $table->timestamp('created_at')->useCurrent();
        });

        // La cantidad de un movimiento siempre es positiva.
        DB::statement('ALTER TABLE movimientos ADD CONSTRAINT chk_movimientos_cantidad_positiva CHECK (cantidad > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
