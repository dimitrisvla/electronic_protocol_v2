<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Protocol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProtocolAssignmentsRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may assign this protocol.
     */
    public function authorize(): bool
    {
        $protocol = $this->route('protocol');

        return $protocol instanceof Protocol
            && ($this->user()?->can('assign', $protocol) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'processing_assignee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'role',
                        UserRole::ProtocolOfficer->value
                    )
                ),
            ],
            'due_at' => ['nullable', 'date'],
            'information_recipient_ids' => ['sometimes', 'array'],
            'information_recipient_ids.*' => [
                'integer',
                'distinct',
                'different:processing_assignee_id',
                Rule::exists('users', 'id'),
            ],
        ];
    }

    /**
     * Return clearer messages for assignment-specific validation failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'processing_assignee_id.exists' =>
                (string) __(
                    'assignments.validation.processing_officer_exists'
                ),
            'information_recipient_ids.*.distinct' =>
                (string) __(
                    'assignments.validation.information_recipient_distinct'
                ),
            'information_recipient_ids.*.different' =>
                (string) __(
                    'assignments.validation.processing_officer_not_recipient'
                ),
            'information_recipient_ids.*.exists' =>
                (string) __(
                    'assignments.validation.information_recipient_exists'
                ),
        ];
    }
}
