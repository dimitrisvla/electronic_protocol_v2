@extends('layouts.app')

@section('title', __('search.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('search.title') }}</h1>
            <p>{{ __('search.description') }}</p>
        </div>

        <a href="{{ route('protocols.index') }}" class="button button-secondary">
            {{ __('common.actions.back') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="validation-errors" role="alert">
            <strong>{{ __('search.validation_summary') }}</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($exactNotFound)
        <div class="validation-errors" role="status">
            {{ __('search.exact.not_found') }}
        </div>
    @endif

    <section class="content-section">
        <h2>{{ __('search.exact.title') }}</h2>

        <form method="GET" action="{{ route('protocols.search') }}">
            <div class="form-grid">
                <div class="form-group">
                    <label for="exact_number">{{ __('search.exact.number') }}</label>
                    <input id="exact_number" name="exact_number" type="number" min="1"
                        value="{{ request('exact_number') }}">
                </div>
                <div class="form-group">
                    <label for="exact_year">{{ __('search.exact.year') }}</label>
                    <input id="exact_year" name="exact_year" type="number" min="1000" max="9999"
                        value="{{ request('exact_year') }}">
                </div>
            </div>
            <button type="submit" class="button button-primary">
                {{ __('search.exact.submit') }}
            </button>
        </form>
    </section>

    <section class="content-section">
        <form method="GET" action="{{ route('protocols.search') }}">
            <h2>{{ __('search.ranges.title') }}</h2>

            <div class="form-grid">
                @foreach (['number_from', 'number_to', 'protocol_year'] as $field)
                    <div class="form-group">
                        <label for="{{ $field }}">{{ __('search.ranges.' . ($field === 'protocol_year' ? 'year' : $field)) }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number"
                            min="{{ $field === 'protocol_year' ? 1000 : 1 }}"
                            @if ($field === 'protocol_year') max="9999" @endif
                            value="{{ request($field) }}">
                    </div>
                @endforeach

                <div class="form-group">
                    <label for="date_from">{{ __('search.ranges.date_from') }}</label>
                    <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}">
                </div>
                <div class="form-group">
                    <label for="date_to">{{ __('search.ranges.date_to') }}</label>
                    <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}">
                </div>
                <div class="form-group">
                    <label for="direction">{{ __('search.ranges.direction') }}</label>
                    <select id="direction" name="direction">
                        <option value="">{{ __('search.ranges.all_directions') }}</option>
                        @foreach (['incoming', 'outgoing'] as $direction)
                            <option value="{{ $direction }}" @selected(request('direction') === $direction)>
                                {{ __('protocols.directions.' . $direction) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <h2>{{ __('search.criteria.title') }}</h2>

            @foreach (range(1, 3) as $position)
                <div class="form-grid">
                    <div class="form-group">
                        <label for="field_{{ $position }}">
                            {{ __('search.criteria.field') }} {{ $position }}
                        </label>
                        <select id="field_{{ $position }}" name="field_{{ $position }}"
                            data-search-field="{{ $position }}">
                            <option value="">{{ __('search.criteria.choose_field') }}</option>
                            @foreach ($searchFields as $searchField)
                                <option value="{{ $searchField->value }}"
                                    @selected(request("field_{$position}") === $searchField->value)>
                                    {{ $searchField->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="term_{{ $position }}">{{ __('search.criteria.term') }}</label>
                        <input id="term_{{ $position }}" name="term_{{ $position }}" type="text"
                            maxlength="255" value="{{ request("term_{$position}") }}"
                            data-search-term="{{ $position }}"
                            @disabled(request()->filled("field_{$position}") && request()->boolean("empty_{$position}"))>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-item" for="empty_{{ $position }}">
                            <input id="empty_{{ $position }}" name="empty_{{ $position }}" type="checkbox"
                                value="1" data-search-empty="{{ $position }}"
                                @checked(request()->filled("field_{$position}") && request()->boolean("empty_{$position}"))
                                @disabled(! request()->filled("field_{$position}"))>
                            <span>{{ __('search.criteria.empty') }}</span>
                        </label>
                        <small>{{ __('search.criteria.empty_hint') }}</small>
                    </div>
                </div>
            @endforeach

            <div class="form-actions">
                <button type="submit" class="button button-primary">{{ __('search.actions.search') }}</button>
                <a href="{{ route('protocols.search') }}" class="button button-secondary">
                    {{ __('search.actions.clear') }}
                </a>
            </div>
        </form>
    </section>

    <section class="content-section">
        <h2>{{ __('search.results.title') }}</h2>

        @if (! $hasCriteria)
            <p>{{ __('search.results.initial') }}</p>
        @else
            <p>{{ trans_choice('search.results.count', $protocols->total(), ['count' => $protocols->total()]) }}</p>

            @if ($protocols->isNotEmpty())
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('search.results.protocol') }}</th>
                                <th>{{ __('search.results.date') }}</th>
                                <th>{{ __('search.results.direction') }}</th>
                                <th>{{ __('search.results.subject') }}</th>
                                <th>{{ __('search.results.correspondent') }}</th>
                                <th>{{ __('search.results.archive_folder') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($protocols as $protocol)
                                <tr>
                                    <td>
                                        <a href="{{ route('protocols.show', $protocol) }}">
                                            {{ $protocol->protocol_number }}/{{ $protocol->protocol_year }}
                                        </a>
                                    </td>
                                    <td>{{ $protocol->protocol_date->format('d/m/Y') }}</td>
                                    <td>{{ __('protocols.directions.' . $protocol->direction) }}</td>
                                    <td>{{ $protocol->subject }}</td>
                                    <td>{{ $protocol->sender ?: $protocol->recipient ?: '—' }}</td>
                                    <td>
                                        {{ $protocol->archiveFolder
                                            ? $protocol->archiveFolder->code . ' — ' . $protocol->archiveFolder->description
                                            : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">{{ $protocols->links() }}</div>
            @endif
        @endif
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-search-field]').forEach((fieldInput) => {
                const position = fieldInput.dataset.searchField;
                const termInput = document.querySelector(`[data-search-term="${position}"]`);
                const emptyInput = document.querySelector(`[data-search-empty="${position}"]`);

                const synchronizeCriterion = () => {
                    const hasField = fieldInput.value !== '';

                    emptyInput.disabled = ! hasField;

                    if (! hasField) {
                        emptyInput.checked = false;
                    }

                    if (hasField && emptyInput.checked) {
                        termInput.value = '';
                        termInput.disabled = true;
                    } else {
                        termInput.disabled = false;
                    }
                };

                fieldInput.addEventListener('change', synchronizeCriterion);
                emptyInput.addEventListener('change', synchronizeCriterion);
                synchronizeCriterion();
            });
        });
    </script>
@endsection
