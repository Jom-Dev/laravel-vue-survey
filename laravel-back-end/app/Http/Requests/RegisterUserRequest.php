<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email|string|unique:users,email',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()
            ]
        ];
    }

    public function validated()
    {
        $validated = $this->validator->validated();

        if ($this->filled('password')) {
            $validated['password'] = bcrypt($this->password);
        } else {
            unset($validated['password']);
        }

        return $validated;
    }

}
