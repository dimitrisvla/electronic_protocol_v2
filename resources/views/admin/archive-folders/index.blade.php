@extends('layouts.app')

@section('title', __('archive_folders.index.title'))

@section('content')
    @php
        $isEditing = $editingArchiveFolder->exists;
    @endphp

    <div class="page-header">
        <div>
            <h1>{{ __('archive_folders.index.title') }}</h1>
            <p>{{ __('archive_folders.index.description') }}</p>
        </div>

        <div class="page-actions">
            <a
                href="{{ route('protocols.index') }}"
                class="button button-secondary"
            >
                {{ __('archive_folders.actions.back_to_protocols') }}
            </a>
        </div>
    </div>

    {{--
        The original application displayed insertion and editing above the
        catalogue. Only Administrators receive this form; the controller,
        policy and Form Requests repeat authorization on the server.
    --}}
    @if (
        ($isEditing && auth()->user()->can('update', $editingArchiveFolder))
        || (! $isEditing && auth()->user()->can(
            'create',
            \App\Models\ArchiveFolder::class
        ))
    )
        <section class="content-section">
            <div class="section-header">
                <div>
                    <h2>
                        @if ($isEditing)
                            {{ __('archive_folders.form.edit_title', [
                                'code' => $editingArchiveFolder->code,
                            ]) }}
                        @else
                            {{ __('archive_folders.form.create_title') }}
                        @endif
                    </h2>
                </div>

                @if ($isEditing)
                    <a
                        href="{{ route('admin.archive-folders.index') }}"
                        class="button button-secondary"
                    >
                        {{ __('archive_folders.actions.clear') }}
                    </a>
                @endif
            </div>

            <form
                method="POST"
                action="{{ $isEditing
                    ? route('admin.archive-folders.update', $editingArchiveFolder)
                    : route('admin.archive-folders.store') }}"
            >
                @csrf

                @if ($isEditing)
                    @method('PUT')
                @endif

                <div class="form-grid">
                    <div class="form-group">
                        <label for="code">
                            {{ __('archive_folders.fields.code') }}
                        </label>

                        <input
                            id="code"
                            name="code"
                            type="text"
                            maxlength="50"
                            value="{{ old('code', $editingArchiveFolder->code) }}"
                            class="@error('code') invalid-field @enderror"
                            required
                            autofocus
                        >

                        @error('code')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="retention_years">
                            {{ __('archive_folders.fields.retention_years') }}
                        </label>

                        <input
                            id="retention_years"
                            name="retention_years"
                            type="number"
                            min="1"
                            max="999"
                            value="{{ old(
                                'retention_years',
                                $editingArchiveFolder->retention_years
                            ) }}"
                            class="@error('retention_years') invalid-field @enderror"
                            aria-describedby="retention_help"
                            data-retention-years
                        >

                        @error('retention_years')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="retention_rule">
                            {{ __('archive_folders.fields.retention_rule') }}
                        </label>

                        <input
                            id="retention_rule"
                            name="retention_rule"
                            type="text"
                            maxlength="100"
                            value="{{ old(
                                'retention_rule',
                                $editingArchiveFolder->retention_rule
                            ) }}"
                            class="@error('retention_rule') invalid-field @enderror"
                            aria-describedby="retention_help"
                            data-retention-rule
                        >

                        @error('retention_rule')
                            <span class="error-message">{{ $message }}</span>
                        @enderror

                        <p id="retention_help" class="field-help">
                            {{ __('archive_folders.form.retention_help') }}
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="remarks">
                            {{ __('archive_folders.fields.remarks') }}
                        </label>

                        <textarea
                            id="remarks"
                            name="remarks"
                            maxlength="2000"
                            class="@error('remarks') invalid-field @enderror"
                        >{{ old('remarks', $editingArchiveFolder->remarks) }}</textarea>

                        @error('remarks')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">
                        {{ __('archive_folders.fields.description') }}
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        maxlength="2000"
                        class="@error('description') invalid-field @enderror"
                        required
                    >{{ old('description', $editingArchiveFolder->description) }}</textarea>

                    @error('description')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="actions">
                    <button type="submit" class="button button-primary">
                        {{ $isEditing
                            ? __('archive_folders.actions.update')
                            : __('archive_folders.actions.create') }}
                    </button>

                    <a
                        href="{{ route('admin.archive-folders.index') }}"
                        class="button button-secondary"
                    >
                        {{ __('archive_folders.actions.clear') }}
                    </a>
                </div>
            </form>
        </section>
    @endif

    <section class="content-section">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('archive_folders.columns.position') }}</th>
                        <th>{{ __('archive_folders.columns.code') }}</th>
                        <th>{{ __('archive_folders.columns.retention_years') }}</th>
                        <th>{{ __('archive_folders.columns.retention_rule') }}</th>
                        <th>{{ __('archive_folders.columns.description') }}</th>
                        <th>{{ __('archive_folders.columns.remarks') }}</th>

                        @can('create', \App\Models\ArchiveFolder::class)
                            <th>{{ __('archive_folders.columns.actions') }}</th>
                        @endcan
                    </tr>
                </thead>

                <tbody>
                    @forelse ($archiveFolders as $archiveFolder)
                        <tr>
                            <td>
                                {{ $archiveFolders->firstItem() + $loop->index }}
                            </td>
                            <td>{{ $archiveFolder->code }}</td>
                            <td>{{ $archiveFolder->retention_years ?? '—' }}</td>
                            <td>{{ $archiveFolder->retention_rule ?? '—' }}</td>
                            <td>{!! nl2br(e($archiveFolder->description)) !!}</td>
                            <td>
                                @if ($archiveFolder->remarks)
                                    {!! nl2br(e($archiveFolder->remarks)) !!}
                                @else
                                    —
                                @endif
                            </td>

                            @can('update', $archiveFolder)
                                <td>
                                    <div class="table-actions">
                                        <a
                                            href="{{ route(
                                                'admin.archive-folders.edit',
                                                $archiveFolder
                                            ) }}"
                                            class="button button-primary"
                                        >
                                            {{ __('archive_folders.actions.edit') }}
                                        </a>

                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'admin.archive-folders.destroy',
                                                $archiveFolder
                                            ) }}"
                                            onsubmit="return confirm(
                                                '{{ __('archive_folders.confirmations.delete', [
                                                    'code' => $archiveFolder->code,
                                                ]) }}'
                                            );"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="button button-danger"
                                            >
                                                {{ __('archive_folders.actions.delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="{{ auth()->user()->can(
                                    'create',
                                    \App\Models\ArchiveFolder::class
                                ) ? 7 : 6 }}"
                            >
                                {{ __('archive_folders.index.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-container">
            {{ $archiveFolders->links() }}
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const yearsInput = document.querySelector('[data-retention-years]');
            const ruleInput = document.querySelector('[data-retention-rule]');

            if (! yearsInput || ! ruleInput) {
                return;
            }

            const hasValue = (input) => input.value.trim() !== '';

            const synchronizeRetentionInputs = function () {
                const hasYears = hasValue(yearsInput);
                const hasRule = hasValue(ruleInput);

                /*
                 * Validation may return legacy input containing both values.
                 * Keep both enabled in that case so the user can clear one.
                 */
                if (hasYears && hasRule) {
                    yearsInput.disabled = false;
                    ruleInput.disabled = false;

                    return;
                }

                yearsInput.disabled = hasRule;
                ruleInput.disabled = hasYears;
            };

            yearsInput.addEventListener('input', function () {
                if (hasValue(yearsInput)) {
                    ruleInput.value = '';
                }

                synchronizeRetentionInputs();
            });

            ruleInput.addEventListener('input', function () {
                if (hasValue(ruleInput)) {
                    yearsInput.value = '';
                }

                synchronizeRetentionInputs();
            });

            synchronizeRetentionInputs();
        });
    </script>
@endsection
