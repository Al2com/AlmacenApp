<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La columna existe desde la fase 1; ahora que hay tabla de tareas,
        // se cierra la clave ajena para que un movimiento solo pueda apuntar a
        // una tarea real.
        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreign('tarea_picking_id')->references('id')->on('tareas_picking')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropForeign(['tarea_picking_id']);
        });
    }
};
