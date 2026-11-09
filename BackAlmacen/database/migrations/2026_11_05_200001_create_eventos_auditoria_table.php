<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro de actividad que no son movimientos: reservas, accesos
        // fallidos y cambios de configuración. Alimenta el feed del admin.
        Schema::create('eventos_auditoria', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3)->nullable();
            $table->string('detalle')->nullable();
            // Registro inmutable: solo fecha de creación.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_auditoria');
    }
};
