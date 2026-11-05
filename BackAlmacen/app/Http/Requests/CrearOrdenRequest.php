<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El permiso lo comprueba la policy en el controlador.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:255', 'unique:ordenes_produccion,codigo'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.articulo_id' => ['required', 'integer', 'exists:articulos,id'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
