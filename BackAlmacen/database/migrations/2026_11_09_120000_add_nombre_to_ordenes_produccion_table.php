<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_produccion', function (Blueprint $table) {
            // Nombre que pone el usuario (p. ej. «Línea 2»); el código se genera
            // solo. Nullable para no romper órdenes antiguas sin nombre.
            $table->string('nombre')->nullable()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_produccion', function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
    }
};
