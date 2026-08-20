<?php

namespace App\Http\Requests;

use App\Models\ApplicationSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorize and validate changes to application-wide settings.
 */
class UpdateApplicationSettingsRequest extends FormRequest
{
    /**
     * Only Administrators may update application settings.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(
            'updateAny',
            ApplicationSetting::class
        ) ?? false;
    }

    /**
     * Return validation rules for the first Step 13.9 settings.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_name' => [
                'required',
                'string',
                'max:255',
            ],
            'active_protocol_year' => [
                'nullable',
                'integer',
                'digits:4',
                'min:1000',
                'max:9999',
            ],
            'starting_protocol_number' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
            ],
            'automatic_protocol_numbering' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Use Greek field names inside framework validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_name' => __('settings.fields.organization_name'),
            'active_protocol_year' => __(
                'settings.fields.active_protocol_year'
            ),
            'starting_protocol_number' => __(
                'settings.fields.starting_protocol_number'
            ),
            'automatic_protocol_numbering' => __(
                'settings.fields.automatic_protocol_numbering'
            ),
        ];
    }
}
