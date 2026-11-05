<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('tipo');
            $table->unsignedInteger('pasillo');
            $table->unsignedInteger('estanteria');
            $table->unsignedInteger('nivel');
            $table->unsignedInteger('capacidad');
            $table->unsignedInteger('max_lotes')->default(1);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // Una ubicación admite al menos un lote.
        DB::statement('ALTER TABLE ubicaciones ADD CONSTRAINT chk_ubicaciones_max_lotes CHECK (max_lotes >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicaciones');
    }
};
