<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $requiresCurrentPassword =
            $this->filled('password') ||
            ($this->filled('email') && $this->input('email') !== $this->user()->email);

        return [
            'name' => ['string', 'min:2', 'max:255'],
            'email' => ['email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            // Password update (optional), but if present require all three fields
            'password' => ['confirmed', 'different:current_password', Password::defaults()],
            'current_password' => [$requiresCurrentPassword ? 'required' : 'nullable', 'string', 'current_password'],
        ];
    }
}
