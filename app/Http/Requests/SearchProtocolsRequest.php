<?php

namespace App\Http\Requests;

use App\Enums\ProtocolSearchField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validate every query-string value accepted by advanced search. */
class SearchProtocolsRequest extends FormRequest
{
    /**
     * Treat a criterion row without a selected field as completely unused.
     *
     * This also protects users returning from an older cached version of the
     * form, where the "empty" checkbox could be selected before a field.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (range(1, 3) as $position) {
            if (! $this->filled("field_{$position}")) {
                $normalized["term_{$position}"] = null;
                $normalized["empty_{$position}"] = null;
            }
        }

        $this->merge($normalized);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $numberToRules = ['nullable', 'integer', 'min:1'];

        if ($this->filled('number_from')) {
            $numberToRules[] = 'gte:number_from';
        }

        $dateToRules = ['nullable', 'date'];

        if ($this->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        $rules = [
            'exact_number' => ['nullable', 'integer', 'min:1', 'required_with:exact_year'],
            'exact_year' => ['nullable', 'integer', 'digits:4', 'required_with:exact_number'],
            'number_from' => ['nullable', 'integer', 'min:1'],
            'number_to' => $numberToRules,
            'protocol_year' => ['nullable', 'integer', 'digits:4'],
            'date_from' => ['nullable', 'date'],
            'date_to' => $dateToRules,
            'direction' => ['nullable', Rule::in(['incoming', 'outgoing'])],
        ];

        foreach (range(1, 3) as $position) {
            $rules["field_{$position}"] = [
                'nullable',
                Rule::in(ProtocolSearchField::values()),
                Rule::requiredIf(fn (): bool =>
                    $this->filled("term_{$position}")
                    || $this->boolean("empty_{$position}")),
            ];
            $rules["term_{$position}"] = [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool =>
                    $this->filled("field_{$position}")
                    && ! $this->boolean("empty_{$position}")),
            ];
            $rules["empty_{$position}"] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = [
            'exact_number' => 'αριθμός πρωτοκόλλου',
            'exact_year' => 'έτος πρωτοκόλλου',
            'number_from' => 'αριθμός από',
            'number_to' => 'αριθμός έως',
            'protocol_year' => 'έτος πρωτοκόλλου',
            'date_from' => 'ημερομηνία από',
            'date_to' => 'ημερομηνία έως',
            'direction' => 'κατεύθυνση',
        ];

        foreach (range(1, 3) as $position) {
            $attributes["field_{$position}"] = "πεδίο {$position}";
            $attributes["term_{$position}"] = "κείμενο κριτηρίου {$position}";
            $attributes["empty_{$position}"] = "κενή τιμή κριτηρίου {$position}";
        }

        return $attributes;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $messages = [];

        foreach (range(1, 3) as $position) {
            $messages["field_{$position}.required"] = (string) __(
                'search.validation.field_required',
                ['position' => $position]
            );
            $messages["field_{$position}.in"] = (string) __(
                'search.validation.field_invalid',
                ['position' => $position]
            );
            $messages["term_{$position}.required"] = (string) __(
                'search.validation.term_required',
                ['position' => $position]
            );
        }

        return $messages;
    }
}
