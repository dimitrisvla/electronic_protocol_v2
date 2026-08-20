<?php

/**
 * Validates the HTTP request used to create a protocol.
 *
 * Location:
 * app/Http/Requests/StoreProtocolRequest.php
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesRelatedProtocols;
use App\Services\ApplicationSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreProtocolRequest extends FormRequest
{
    use ValidatesRelatedProtocols;

    /**
     * Determine whether the user may make this request.
     *
     * The ProtocolPolicy and controller handle the protocol's
     * authorization separately.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return the validation rules for creating a protocol.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $automaticNumbering = app(ApplicationSettings::class)
            ->usesAutomaticProtocolNumbering();

        return [
            /*
             * In automatic mode the submitted number is deliberately
             * excluded. ProtocolController calculates the authoritative
             * value inside the database transaction, so a crafted request
             * cannot force or reserve a number.
             *
             * Manual mode retains the existing required and per-year unique
             * validation behavior.
             */
            'protocol_number' => $automaticNumbering
                ? ['exclude']
                : [
                    'required',
                    'integer',
                    'min:1',
                    'max:4294967295',
                    Rule::unique('protocols', 'protocol_number')
                        ->where(
                            fn ($query) => $query->where(
                                'protocol_year',
                                $this->input('protocol_year')
                            )
                        ),
                ],

            'protocol_year' => ['required', 'integer', 'digits:4',],

            'protocol_date' => ['required', 'date',],

            'direction' => [
                'required',
                Rule::in(['incoming', 'outgoing']),
            ],

            'subject' => ['required', 'string', 'max:255',],

            'sender' => ['nullable', 'string', 'max:255',],

            'recipient' => ['nullable', 'string', 'max:255',],

            'notes' => ['nullable', 'string',],

            /**
             * Archive classification is optional while historical protocols
             * are migrated gradually. A newly selected entry must still be
             * active and available for operational use.
             */
            'archive_folder_id' => [
                'nullable',
                'integer',
                Rule::exists('archive_folders', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('is_active', true)
                            ->where('is_selectable', true)
                    ),
            ],

            ...$this->relatedProtocolRules(),

            /**
             * The attachments field is optional.
             *
             * When supplied, it must be an array containing
             * at most 10 uploaded files.
             */
            'attachments' => ['nullable', 'array', 'max:10',],

            /**
             * Validate every individual element of the array.
             *
             * File::types(['pdf']) examines the file's detected
             * MIME type instead of trusting only its filename.
             *
             * extensions:pdf also requires the user-provided
             * filename to end with the .pdf extension.
             *
             * Each PDF must be at most 10 MB.
             */
            'attachments.*' => [
                'file',
                File::types(['pdf'])->max(10 * 1024),
                'extensions:pdf',
            ],
        ];
    }
}
