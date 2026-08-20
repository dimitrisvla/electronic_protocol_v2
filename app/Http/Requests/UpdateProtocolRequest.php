<?php

/**
 * Validates the HTTP request used to update an existing protocol.
 *
 * Location:
 * app/Http/Requests/UpdateProtocolRequest.php
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesRelatedProtocols;
use App\Models\Protocol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateProtocolRequest extends FormRequest
{
    use ValidatesRelatedProtocols;

    /**
     * Determine whether the user may make this request.
     *
     * Authorization for updating the specific protocol
     * is handled by the ProtocolPolicy and controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return the validation rules for updating a protocol.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $protocol = $this->route('protocol');
        $currentArchiveFolderId = $protocol instanceof Protocol
            ? $protocol->archive_folder_id
            : null;

        return [
            'protocol_number' => [
                'required',
                'integer',
                'min:1',

                /**
                 * The protocol number must remain unique within
                 * the selected protocol year.
                 *
                 * ignore() excludes the protocol currently being
                 * updated from the uniqueness check.
                 */
                Rule::unique('protocols', 'protocol_number')
                    ->where(
                        fn ($query) => $query->where(
                            'protocol_year',
                            $this->input('protocol_year')
                        )
                    )
                    ->ignore($this->route('protocol')),
            ],

            'protocol_year' => ['required', 'integer', 'digits:4',],

            'protocol_date' => ['required', 'date',],

            'direction' => ['required', Rule::in(['incoming', 'outgoing']),],

            'subject' => ['required', 'string', 'max:255',],

            'sender' => ['nullable', 'string', 'max:255',],

            'recipient' => ['nullable', 'string', 'max:255',],

            'notes' => ['nullable', 'string',],

            /**
             * New selections must be active and selectable. The protocol's
             * existing folder remains valid even if it was later retired, so
             * unrelated edits never destroy historical classification.
             */
            'archive_folder_id' => [
                'nullable',
                'integer',
                Rule::exists('archive_folders', 'id')
                    ->where(function ($query) use ($currentArchiveFolderId) {
                        $query->where(function ($query) {
                            $query
                                ->where('is_active', true)
                                ->where('is_selectable', true);
                        });

                        if ($currentArchiveFolderId !== null) {
                            $query->orWhere('id', $currentArchiveFolderId);
                        }
                    }),
            ],

            ...$this->relatedProtocolRules(),

            /**
             * Newly uploaded attachments are optional when
             * updating a protocol.
             *
             * Existing attachments are not affected by these
             * rules. This array contains only newly selected files.
             */
            'attachments' => ['nullable','array', 'max:10',],

            /**
             * Every newly selected attachment must:
             *
             * - Be a successfully uploaded file.
             * - Have PDF content.
             * - Use the .pdf filename extension.
             * - Be at most 10 MB.
             */
            'attachments.*' => [
                'file',
                File::types(['pdf'])->max(10 * 1024),
                'extensions:pdf',
            ],
        ];
    }
}
