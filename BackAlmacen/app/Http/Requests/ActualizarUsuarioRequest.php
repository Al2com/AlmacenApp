<?php

namespace App\Http\Requests;

use App\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarUsuarioRequest extends FormRequest
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
        $usuario = $this->route('usuario');
        $id = is_object($usuario) && method_exists($usuario, 'getKey') ? $usuario->getKey() : null;

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'rol' => ['sometimes', 'required', Rule::enum(Rol::class)],
            'activo' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
