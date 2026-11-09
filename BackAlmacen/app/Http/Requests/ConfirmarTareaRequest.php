<?php

namespace App\Http\Requests;

use App\Models\TareaPicking;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Antes de confirmar, el operador teclea el código de la ubicación, el del lote
 * y la cantidad; si algo no coincide con la tarea, se avisa y no se consume.
 */
class ConfirmarTareaRequest extends FormRequest
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
            'codigo_ubicacion' => ['required', 'string'],
            'codigo_lote' => ['required', 'string'],
            'cantidad' => ['required', 'numeric'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tarea = $this->route('tarea');

            if (! $tarea instanceof TareaPicking) {
                return;
            }

            $tarea->loadMissing('ubicacion', 'lote');

            if ($this->input('codigo_ubicacion') !== $tarea->ubicacion->codigo) {
                $validator->errors()->add('codigo_ubicacion', 'La ubicación no coincide con la tarea.');
            }

            if ($this->input('codigo_lote') !== $tarea->lote->codigo_lote) {
                $validator->errors()->add('codigo_lote', 'El lote no coincide con la tarea.');
            }

            if ((float) $this->input('cantidad') !== (float) $tarea->cantidad) {
                $validator->errors()->add('cantidad', 'La cantidad no coincide con la tarea.');
            }
        });
    }
}
