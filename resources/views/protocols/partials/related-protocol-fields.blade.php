@php
    $submittedRows = old('related_protocols');

    if (is_array($submittedRows)) {
        $relatedProtocolRows = $submittedRows;
    } elseif (isset($relatedProtocols)) {
        $relatedProtocolRows = $relatedProtocols
            ->map(fn ($relatedProtocol) => [
                'protocol_number' => $relatedProtocol->protocol_number,
                'protocol_year' => $relatedProtocol->protocol_year,
            ])
            ->values()
            ->all();
    } else {
        $relatedProtocolRows = [];
    }

    if ($relatedProtocolRows === []) {
        $relatedProtocolRows[] = [
            'protocol_number' => '',
            'protocol_year' => '',
        ];
    }
@endphp

<style>
    .related-protocol-list {
        display: grid;
        gap: 10px;
        margin-bottom: 10px;
    }

    .related-protocol-row {
        display: grid;
        grid-template-columns: minmax(150px, 1fr) minmax(130px, 1fr) auto;
        gap: 10px;
        align-items: end;
    }

    .related-protocol-field label {
        font-size: 14px;
        font-weight: 600;
    }

    .related-protocol-remove {
        white-space: nowrap;
    }

    @media (max-width: 680px) {
        .related-protocol-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<fieldset class="form-group related-protocols">
    <legend>{{ __('protocols.related.title') }}</legend>

    <p>{{ __('protocols.related.help') }}</p>

    @error('related_protocols')
        <span class="error-message">{{ $message }}</span>
    @enderror

    <div
        id="related-protocol-list"
        class="related-protocol-list"
        data-maximum="20"
    >
        @foreach ($relatedProtocolRows as $index => $row)
            <div class="related-protocol-row" data-related-protocol-row>
                <div class="related-protocol-field">
                    <label for="related_protocol_number_{{ $index }}">
                        {{ __('protocols.related.number') }}
                    </label>
                    <input
                        type="number"
                        id="related_protocol_number_{{ $index }}"
                        name="related_protocols[{{ $index }}][protocol_number]"
                        value="{{ $row['protocol_number'] ?? '' }}"
                        min="1"
                        max="4294967295"
                        class="@error('related_protocols.'.$index.'.protocol_number') invalid-field @enderror"
                    >
                    @error('related_protocols.'.$index.'.protocol_number')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="related-protocol-field">
                    <label for="related_protocol_year_{{ $index }}">
                        {{ __('protocols.related.year') }}
                    </label>
                    <input
                        type="number"
                        id="related_protocol_year_{{ $index }}"
                        name="related_protocols[{{ $index }}][protocol_year]"
                        value="{{ $row['protocol_year'] ?? '' }}"
                        min="1000"
                        max="9999"
                        class="@error('related_protocols.'.$index.'.protocol_year') invalid-field @enderror"
                    >
                    @error('related_protocols.'.$index.'.protocol_year')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <button
                    type="button"
                    class="button button-secondary related-protocol-remove"
                    data-remove-related-protocol
                >
                    {{ __('protocols.related.remove') }}
                </button>
            </div>
        @endforeach
    </div>

    <button
        type="button"
        id="add-related-protocol"
        class="button button-secondary"
    >
        {{ __('protocols.related.add') }}
    </button>
</fieldset>

<template id="related-protocol-template">
    <div class="related-protocol-row" data-related-protocol-row>
        <div class="related-protocol-field">
            <label for="related_protocol_number___INDEX__">
                {{ __('protocols.related.number') }}
            </label>
            <input
                type="number"
                id="related_protocol_number___INDEX__"
                name="related_protocols[__INDEX__][protocol_number]"
                min="1"
                max="4294967295"
            >
        </div>

        <div class="related-protocol-field">
            <label for="related_protocol_year___INDEX__">
                {{ __('protocols.related.year') }}
            </label>
            <input
                type="number"
                id="related_protocol_year___INDEX__"
                name="related_protocols[__INDEX__][protocol_year]"
                min="1000"
                max="9999"
            >
        </div>

        <button
            type="button"
            class="button button-secondary related-protocol-remove"
            data-remove-related-protocol
        >
            {{ __('protocols.related.remove') }}
        </button>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const list = document.getElementById('related-protocol-list');
        const addButton = document.getElementById('add-related-protocol');
        const template = document.getElementById('related-protocol-template');

        if (! list || ! addButton || ! template) {
            return;
        }

        const maximum = Number(list.dataset.maximum);
        let nextIndex = Array.from(
            list.querySelectorAll('[data-related-protocol-row]')
        ).reduce((largest, row) => {
            const input = row.querySelector('input[name*="[protocol_number]"]');
            const match = input?.name.match(/related_protocols\[(\d+)\]/);

            return match ? Math.max(largest, Number(match[1]) + 1) : largest;
        }, 0);

        const updateAddButton = () => {
            addButton.disabled =
                list.querySelectorAll('[data-related-protocol-row]').length
                >= maximum;
        };

        addButton.addEventListener('click', () => {
            if (addButton.disabled) {
                return;
            }

            list.insertAdjacentHTML(
                'beforeend',
                template.innerHTML.replaceAll('__INDEX__', String(nextIndex))
            );

            nextIndex += 1;
            updateAddButton();
        });

        list.addEventListener('click', (event) => {
            const removeButton = event.target.closest(
                '[data-remove-related-protocol]'
            );

            if (! removeButton) {
                return;
            }

            const rows = list.querySelectorAll('[data-related-protocol-row]');
            const row = removeButton.closest('[data-related-protocol-row]');

            if (rows.length === 1) {
                row.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });
            } else {
                row.remove();
            }

            updateAddButton();
        });

        updateAddButton();
    });
</script>
