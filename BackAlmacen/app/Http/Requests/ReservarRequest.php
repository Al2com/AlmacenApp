<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservarRequest extends FormRequest
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
            'articulo_id' => ['required', 'integer', 'exists:articulos,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
