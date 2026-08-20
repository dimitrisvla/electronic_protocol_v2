<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may create accounts.
     */
    public function authorize(): bool
    {
        /*
         * The null-safe operator also rejects unauthenticated requests.
         * The surrounding auth middleware normally redirects guests before
         * this method is reached, while authenticated non-administrators
         * receive HTTP 403 from FormRequest authorization.
         */
        return $this->user()?->isAdministrator() ?? false;
    }

    /**
     * Get the validation rules for the new account.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:users,email',
            ],
            
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],

            /*
             * Rule::enum() accepts only one of the string-backed values
             * declared by UserRole. Arbitrary role names are rejected.
             */
            'role' => [
                'required',
                Rule::enum(UserRole::class),
            ],
        ];
    }
}