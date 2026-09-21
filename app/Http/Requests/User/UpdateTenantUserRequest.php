<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && $user->canManageUsers();
    }

    public function rules(): array
    {
        $targetUserId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetUserId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Prevent privilege escalation during user modification.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $currentUser = $this->user();

            if ($this->has('role')) {
                $targetRole = UserRole::tryFrom($this->input('role'));

                if ($currentUser && $targetRole) {
                    if (! $currentUser->role->canAssignRole($targetRole)) {
                        $v->errors()->add('role', "You are not authorized to assign the [{$targetRole->label()}] role.");
                    }
                }
            }
        });
    }
}
