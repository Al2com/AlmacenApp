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
            // El código lo genera el servidor; el usuario solo pone el nombre.
            'nombre' => ['required', 'string', 'max:255'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.articulo_id' => ['required', 'integer', 'exists:articulos,id'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
