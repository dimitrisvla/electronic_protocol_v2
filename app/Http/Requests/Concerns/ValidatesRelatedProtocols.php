<?php

namespace App\Http\Requests\Concerns;

use App\Models\Protocol;
use Illuminate\Validation\Validator;

/**
 * Shared compound validation for protocol number/year references.
 */
trait ValidatesRelatedProtocols
{
    /**
     * Remove completely blank browser rows before validation.
     */
    protected function prepareForValidation(): void
    {
        $rows = $this->input('related_protocols');

        if (! is_array($rows)) {
            return;
        }

        $normalizedRows = collect($rows)
            ->map(function ($row) {
                if (! is_array($row)) {
                    return $row;
                }

                return [
                    'protocol_number' => trim((string) ($row['protocol_number'] ?? '')),
                    'protocol_year' => trim((string) ($row['protocol_year'] ?? '')),
                ];
            })
            ->reject(fn ($row): bool => is_array($row)
                && $row['protocol_number'] === ''
                && $row['protocol_year'] === '')
            ->values()
            ->all();

        $this->merge(['related_protocols' => $normalizedRows]);
    }

    /**
     * Rules appended to both protocol create and update requests.
     *
     * @return array<string, array<int, string>>
     */
    protected function relatedProtocolRules(): array
    {
        return [
            'related_protocols' => ['nullable', 'array', 'max:20'],
            'related_protocols.*' => ['array'],
            'related_protocols.*.protocol_number' => [
                'nullable',
                'required_with:related_protocols.*.protocol_year',
                'integer',
                'min:1',
                'max:4294967295',
            ],
            'related_protocols.*.protocol_year' => [
                'nullable',
                'required_with:related_protocols.*.protocol_number',
                'integer',
                'digits:4',
            ],
        ];
    }

    /**
     * Greek messages for the compound reference controls.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'related_protocols.array' =>
                __('protocols.related.validation.invalid_list'),
            'related_protocols.max' =>
                __('protocols.related.validation.too_many'),
            'related_protocols.*.array' =>
                __('protocols.related.validation.invalid_row'),
            'related_protocols.*.protocol_number.required_with' =>
                __('protocols.related.validation.number_required'),
            'related_protocols.*.protocol_number.integer' =>
                __('protocols.related.validation.number_integer'),
            'related_protocols.*.protocol_number.min' =>
                __('protocols.related.validation.number_min'),
            'related_protocols.*.protocol_number.max' =>
                __('protocols.related.validation.number_max'),
            'related_protocols.*.protocol_year.required_with' =>
                __('protocols.related.validation.year_required'),
            'related_protocols.*.protocol_year.integer' =>
                __('protocols.related.validation.year_format'),
            'related_protocols.*.protocol_year.digits' =>
                __('protocols.related.validation.year_format'),
        ];
    }

    /**
     * Validate existence, uniqueness and the self-relation prohibition after
     * Laravel has checked the primitive values of every row.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rows = $this->input('related_protocols', []);

            if (! is_array($rows)) {
                return;
            }

            $currentProtocol = $this->route('protocol');
            $seenReferences = [];

            foreach ($rows as $index => $row) {
                if (! is_array($row)
                    || $validator->errors()->has(
                        "related_protocols.{$index}.protocol_number"
                    )
                    || $validator->errors()->has(
                        "related_protocols.{$index}.protocol_year"
                    )) {
                    continue;
                }

                $number = (int) ($row['protocol_number'] ?? 0);
                $year = (int) ($row['protocol_year'] ?? 0);

                if ($number < 1 || $year < 1) {
                    continue;
                }

                $referenceKey = "{$year}/{$number}";

                if (isset($seenReferences[$referenceKey])) {
                    $validator->errors()->add(
                        "related_protocols.{$index}.protocol_number",
                        __('protocols.related.validation.duplicate')
                    );

                    continue;
                }

                $seenReferences[$referenceKey] = true;

                $relatedProtocol = Protocol::query()
                    ->where('protocol_number', $number)
                    ->where('protocol_year', $year)
                    ->first();

                if ($relatedProtocol === null) {
                    $validator->errors()->add(
                        "related_protocols.{$index}.protocol_number",
                        __('protocols.related.validation.not_found')
                    );

                    continue;
                }

                if ($currentProtocol instanceof Protocol
                    && $relatedProtocol->is($currentProtocol)) {
                    $validator->errors()->add(
                        "related_protocols.{$index}.protocol_number",
                        __('protocols.related.validation.self')
                    );
                }
            }
        });
    }
}
