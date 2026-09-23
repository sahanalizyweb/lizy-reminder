<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/editing a user account (Manage Users, Admin only —
 * the route is already gated by the `admin` middleware, this is defence in
 * depth). Fields: Name, Email, Role, New Password, Confirm Password — no
 * Assigned Person and no admin re-authentication here; both Admin and User
 * accounts are created/edited the same way.
 *
 * Password fields:
 *  - New user: `password` is required, `password_confirmation` must match it.
 *  - Existing user: `password` may be left blank to keep the current one; if
 *    set, `password_confirmation` must match it.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isCreate = ! $userId;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in(User::ROLES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.confirmed' => 'Confirm Password does not match New Password.',
        ];
    }
}
