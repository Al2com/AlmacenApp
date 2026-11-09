<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CambiarPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El acceso lo garantiza el middleware auth:sanctum de la ruta.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->uncompromised()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user instanceof User && ! Hash::check((string) $this->string('password_actual'), $user->password)) {
                $validator->errors()->add('password_actual', 'La contraseña actual no es correcta.');
            }
        });
    }
}
