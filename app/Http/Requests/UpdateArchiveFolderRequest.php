<?php

namespace App\Http\Requests;

use App\Models\ArchiveFolder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates changes to an archive-folder definition.
 */
class UpdateArchiveFolderRequest extends FormRequest
{
    /**
     * Only an Administrator may update the bound archive folder.
     */
    public function authorize(): bool
    {
        $archiveFolder = $this->route('archiveFolder');

        return $archiveFolder instanceof ArchiveFolder
            && ($this->user()?->can('update', $archiveFolder) ?? false);
    }

    /**
     * Normalize empty optional controls to database null values.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedInput());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ArchiveFolder $archiveFolder */
        $archiveFolder = $this->route('archiveFolder');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^Φ\.\w[\w ._-]*$/u',
                Rule::unique('archive_folders', 'code')
                    ->ignore($archiveFolder),
            ],
            'retention_years' => [
                'nullable',
                'integer',
                'min:1',
                'max:999',
                'prohibits:retention_rule',
            ],
            'retention_rule' => [
                'nullable',
                'string',
                'max:100',
                'prohibits:retention_years',
            ],
            'description' => ['required', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Greek field names used by Laravel's shared validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('archive_folders.fields.code'),
            'retention_years' => __('archive_folders.fields.retention_years'),
            'retention_rule' => __('archive_folders.fields.retention_rule'),
            'description' => __('archive_folders.fields.description'),
            'remarks' => __('archive_folders.fields.remarks'),
        ];
    }

    /**
     * Feature-specific Greek validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('archive_folders.validation.code_format'),
            'retention_years.prohibits' => __(
                'archive_folders.validation.retention_exclusive'
            ),
            'retention_rule.prohibits' => __(
                'archive_folders.validation.retention_exclusive'
            ),
        ];
    }

    /**
     * Prepare trimmed values shared by validation and persistence.
     *
     * @return array<string, mixed>
     */
    private function normalizedInput(): array
    {
        $normalized = [];

        foreach (['code', 'retention_rule', 'description', 'remarks'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
            }

            $normalized[$field] = $value === '' ? null : $value;
        }

        $normalized['retention_years'] = $this->input('retention_years') === ''
            ? null
            : $this->input('retention_years');

        return $normalized;
    }
}
