<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lote_id' => ['required', 'integer', 'exists:lotes,id'],
            'ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
