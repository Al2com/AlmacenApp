<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrasladarRequest extends FormRequest
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
            'origen_id' => ['required', 'integer', 'exists:ubicaciones,id'],
            'destino_id' => ['required', 'integer', 'different:origen_id', 'exists:ubicaciones,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
