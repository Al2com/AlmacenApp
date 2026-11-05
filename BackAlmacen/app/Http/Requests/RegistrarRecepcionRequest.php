<?php

namespace App\Http\Requests;

use App\Models\Articulo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RegistrarRecepcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El permiso lo comprueba el gate en el controlador.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'articulo_id' => ['required', 'integer', 'exists:articulos,id'],
            'codigo_lote' => ['required', 'string', 'max:255'],
            'fecha_recepcion' => ['required', 'date'],
            'caducidad' => ['nullable', 'date', 'after_or_equal:fecha_recepcion'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'ubicacion_id' => ['nullable', 'integer', 'exists:ubicaciones,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $articulo = Articulo::find($this->input('articulo_id'));

            if (! $articulo instanceof Articulo) {
                return;
            }

            // La caducidad va atada a la condición del artículo: ni falta ni sobra.
            if ($articulo->tiene_caducidad && $this->input('caducidad') === null) {
                $validator->errors()->add('caducidad', 'Este artículo tiene caducidad: es obligatoria.');
            }

            if (! $articulo->tiene_caducidad && $this->input('caducidad') !== null) {
                $validator->errors()->add('caducidad', 'Este artículo no tiene caducidad: no debe indicarse.');
            }
        });
    }
}
