<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && $user->canManageUsers();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Prevent privilege escalation during user creation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $currentUser = $this->user();
            $targetRole = UserRole::tryFrom($this->input('role'));

            if ($currentUser && $targetRole) {
                if (! $currentUser->role->canAssignRole($targetRole)) {
                    $v->errors()->add('role', "You are not authorized to assign the [{$targetRole->label()}] role.");
                }
            }
        });
    }
}
