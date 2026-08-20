@extends('layouts.app')

@section('title', __('protocols.show.title'))

@section('content')
    <div class="page-header">
        <h1>{{ __('protocols.show.title') }}</h1>

        <div class="page-actions">
            <a
                href="{{ route('protocols.index') }}"
                class="button button-secondary"
            >
                {{ __('protocols.actions.back_to_protocols') }}
            </a>

            @can('update', $protocol)
                <a
                    href="{{ route('protocols.edit', $protocol) }}"
                    class="button button-primary"
                >
                    {{ __('protocols.actions.edit_protocol') }}
                </a>
            @endcan

            @can('delete', $protocol)
                <form
                    action="{{ route('protocols.destroy', $protocol) }}"
                    method="POST"
                    onsubmit="return confirm('{{ __('protocols.confirmations.delete') }}')"
                >
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="button button-danger">
                        {{ __('protocols.actions.delete_protocol') }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <section class="content-section" aria-labelledby="protocol-information-heading">
        <div class="section-header">
            <div>
                <h2 id="protocol-information-heading">
                    {{ __('protocols.show.information_heading') }}
                </h2>
                <p class="muted-text">
                    {{ __('protocols.show.information_description') }}
                </p>
            </div>
        </div>

        <dl class="detail-grid">
            <div class="detail-item">
                <dt>{{ __('protocols.fields.protocol_number') }}</dt>
                <dd>{{ $protocol->protocol_number }}/{{ $protocol->protocol_year }}</dd>
            </div>

            <div class="detail-item">
                <dt>{{ __('protocols.fields.protocol_date') }}</dt>
                <dd>{{ $protocol->protocol_date->format('d/m/Y') }}</dd>
            </div>

            <div class="detail-item">
                <dt>{{ __('protocols.fields.direction') }}</dt>
                <dd>{{ __('protocols.directions.' . $protocol->direction) }}</dd>
            </div>

            <div class="detail-item detail-item-wide">
                <dt>{{ __('protocols.fields.archive_folder') }}</dt>
                <dd>
                    @if ($protocol->archiveFolder)
                        {{ $protocol->archiveFolder->code }} —
                        {{ $protocol->archiveFolder->description }}
                    @else
                        —
                    @endif
                </dd>
            </div>

            <div class="detail-item">
                <dt>{{ __('protocols.fields.retention') }}</dt>
                <dd>
                    @if ($protocol->archiveFolder?->retention_years !== null)
                        {{ trans_choice(
                            'protocols.archive.retention_years',
                            $protocol->archiveFolder->retention_years,
                            ['count' => $protocol->archiveFolder->retention_years]
                        ) }}
                    @elseif ($protocol->archiveFolder?->retention_rule)
                        {{ $protocol->archiveFolder->retention_rule }}
                    @else
                        —
                    @endif
                </dd>
            </div>

            <div class="detail-item detail-item-wide">
                <dt>{{ __('protocols.fields.subject') }}</dt>
                <dd>{{ $protocol->subject }}</dd>
            </div>

            <div class="detail-item">
                <dt>{{ __('protocols.fields.sender') }}</dt>
                <dd>{{ $protocol->sender ?: '—' }}</dd>
            </div>

            <div class="detail-item">
                <dt>{{ __('protocols.fields.recipient') }}</dt>
                <dd>{{ $protocol->recipient ?: '—' }}</dd>
            </div>

            <div class="detail-item detail-item-wide">
                <dt>{{ __('protocols.fields.notes') }}</dt>
                <dd>
                    {!! $protocol->notes
                        ? nl2br(e($protocol->notes))
                        : '—'
                    !!}
                </dd>
            </div>
        </dl>
    </section>

    <section
        class="content-section"
        aria-labelledby="related-protocols-heading"
    >
        <div class="section-header">
            <div>
                <h2 id="related-protocols-heading">
                    {{ __('protocols.related.title') }}
                </h2>
                <p class="muted-text">
                    {{ __('protocols.related.description') }}
                </p>
            </div>
        </div>

        @if ($relatedProtocols->isEmpty())
            <p class="muted-text">
                {{ __('protocols.related.empty') }}
            </p>
        @else
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">
                                {{ __('protocols.fields.protocol_number') }}
                            </th>
                            <th scope="col">
                                {{ __('protocols.fields.protocol_date') }}
                            </th>
                            <th scope="col">
                                {{ __('protocols.fields.direction') }}
                            </th>
                            <th scope="col">
                                {{ __('protocols.fields.subject') }}
                            </th>
                            <th scope="col">
                                {{ __('protocols.columns.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($relatedProtocols as $relatedProtocol)
                            @php
                                $reference = $relatedProtocol->protocol_number
                                    . '/'
                                    . $relatedProtocol->protocol_year;
                            @endphp

                            <tr>
                                <td>
                                    <a
                                        href="{{ route(
                                            'protocols.show',
                                            $relatedProtocol
                                        ) }}"
                                        aria-label="{{ __(
                                            'protocols.related.open_aria',
                                            ['reference' => $reference]
                                        ) }}"
                                    >
                                        <strong>{{ $reference }}</strong>
                                    </a>
                                </td>
                                <td>
                                    {{ $relatedProtocol->protocol_date
                                        ->format('d/m/Y') }}
                                </td>
                                <td>
                                    {{ __(
                                        'protocols.directions.'
                                            . $relatedProtocol->direction
                                    ) }}
                                </td>
                                <td>{{ $relatedProtocol->subject }}</td>
                                <td>
                                    <a
                                        href="{{ route(
                                            'protocols.show',
                                            $relatedProtocol
                                        ) }}"
                                        class="button button-primary"
                                    >
                                        {{ __('protocols.related.open') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="content-section" aria-labelledby="assignments-heading">
        <div class="section-header">
            <div>
                <h2 id="assignments-heading">{{ __('protocols.assignments.title') }}</h2>
                <p class="muted-text">
                    {{ __('protocols.assignments.description') }}
                </p>
            </div>
        </div>

        @if ($canManageAssignments)
            @php
                $selectedProcessingOfficer = (string) old(
                    'processing_assignee_id',
                    $currentProcessingAssignment?->assigned_to
                );

                $selectedInformationRecipients = collect(
                    old(
                        'information_recipient_ids',
                        $informationAssignments
                            ->pluck('assigned_to')
                            ->filter()
                            ->all()
                    )
                )->map(fn ($id) => (string) $id);

                $selectedDueAt = old(
                    'due_at',
                    $currentProcessingAssignment?->due_at?->format(
                        'Y-m-d\\TH:i'
                    )
                );
            @endphp

            <div class="assignment-management">
                <h3>{{ __('protocols.assignments.manage') }}</h3>

                <form
                    action="{{ route('protocols.assignments.update', $protocol) }}"
                    method="POST"
                >
                    @csrf
                    @method('PUT')

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="processing_assignee_id">
                                {{ __('protocols.assignments.processing_officer') }}
                            </label>

                            <select
                                id="processing_assignee_id"
                                name="processing_assignee_id"
                                class="@error('processing_assignee_id') invalid-field @enderror"
                                required
                            >
                                <option value="">{{ __('protocols.assignments.select_officer') }}</option>

                                @foreach ($processingOfficers as $officer)
                                    <option
                                        value="{{ $officer->id }}"
                                        @selected(
                                            $selectedProcessingOfficer
                                                === (string) $officer->id
                                        )
                                    >
                                        {{ $officer->name }} ({{ $officer->email }})
                                    </option>
                                @endforeach
                            </select>

                            @error('processing_assignee_id')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="due_at">
                                {{ __('protocols.assignments.processing_deadline') }}
                            </label>

                            <input
                                id="due_at"
                                name="due_at"
                                type="datetime-local"
                                value="{{ $selectedDueAt }}"
                                class="@error('due_at') invalid-field @enderror"
                            >

                            @error('due_at')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <fieldset class="recipient-fieldset">
                        <legend>{{ __('protocols.assignments.information_recipients') }}</legend>

                        <p class="field-help">
                            {{ __('protocols.assignments.information_help') }}
                        </p>

                        @if ($informationRecipients->isEmpty())
                            <p class="muted-text">{{ __('protocols.assignments.no_users') }}</p>
                        @else
                            <div class="checkbox-grid">
                                @foreach ($informationRecipients as $recipient)
                                    <label class="checkbox-item">
                                        <input
                                            type="checkbox"
                                            name="information_recipient_ids[]"
                                            value="{{ $recipient->id }}"
                                            @checked(
                                                $selectedInformationRecipients
                                                    ->contains((string) $recipient->id)
                                            )
                                        >

                                        <span>
                                            <strong>{{ $recipient->name }}</strong>
                                            <small>
                                                {{ $recipient->email }} ·
                                                {{ $recipient->role->label() }}
                                            </small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        @error('information_recipient_ids')
                            <span class="error-message">{{ $message }}</span>
                        @enderror

                        @if ($errors->has('information_recipient_ids.*'))
                            <span class="error-message">
                                {{ $errors->first('information_recipient_ids.*') }}
                            </span>
                        @endif
                    </fieldset>

                    <div class="actions">
                        <button type="submit" class="button button-primary">
                            {{ __('protocols.assignments.save') }}
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="assignment-grid">
            <article class="assignment-card">
                <div class="assignment-card-header">
                    <h3>{{ __('protocols.assignments.current_processing') }}</h3>

                    @if ($currentProcessingAssignment)
                        <span class="status-badge status-pending">
                            {{ __('protocols.statuses.pending') }}
                        </span>
                    @endif
                </div>

                @if ($currentProcessingAssignment)
                    <dl class="assignment-details">
                        <div>
                            <dt>{{ __('protocols.assignments.assigned_to') }}</dt>
                            <dd>
                                {{ $currentProcessingAssignment->assignee?->name
                                    ?? __('protocols.assignments.former_user') }}
                            </dd>
                        </div>

                        <div>
                            <dt>{{ __('protocols.assignments.assigned_by') }}</dt>
                            <dd>
                                {{ $currentProcessingAssignment->assigner?->name
                                    ?? __('protocols.assignments.former_user') }}
                            </dd>
                        </div>

                        <div>
                            <dt>{{ __('protocols.assignments.assigned_at') }}</dt>
                            <dd>
                                {{ $currentProcessingAssignment->created_at
                                    ->format('d/m/Y H:i') }}
                            </dd>
                        </div>

                        <div>
                            <dt>{{ __('protocols.assignments.due_at') }}</dt>
                            <dd>
                                {{ $currentProcessingAssignment->due_at
                                    ?->format('d/m/Y H:i')
                                    ?? __('protocols.assignments.no_deadline') }}
                            </dd>
                        </div>
                    </dl>

                    @can('complete', $currentProcessingAssignment)
                        <form
                            class="assignment-complete-form"
                            action="{{ route('protocols.assignments.complete', [
                                'protocol' => $protocol,
                                'protocolAssignment' => $currentProcessingAssignment,
                            ]) }}"
                            method="POST"
                            onsubmit="return confirm('{{ __('protocols.confirmations.complete_assignment') }}')"
                        >
                            @csrf
                            @method('PATCH')

                            <button type="submit" class="button button-success">
                                {{ __('protocols.assignments.complete') }}
                            </button>
                        </form>
                    @endcan
                @else
                    <p class="muted-text">
                        {{ __('protocols.assignments.no_active_processing') }}
                    </p>
                @endif
            </article>

            <article class="assignment-card">
                <div class="assignment-card-header">
                    <h3>{{ __('protocols.assignments.information_recipients') }}</h3>

                    @if ($informationAssignments->isNotEmpty())
                        <span class="status-badge status-information">
                            {{ $informationAssignments->count() }}
                        </span>
                    @endif
                </div>

                @forelse ($informationAssignments as $assignment)
                    <div class="recipient-summary">
                        <strong>
                            {{ $assignment->assignee?->name
                                ?? __('protocols.assignments.former_user') }}
                        </strong>

                        <span class="muted-text">
                            {{ __('protocols.assignments.added_at', [
                                'date' => $assignment->created_at->format('d/m/Y H:i'),
                            ]) }}
                            @if ($assignment->assigner)
                                {{ __('protocols.assignments.by', [
                                    'name' => $assignment->assigner->name,
                                ]) }}
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="muted-text">
                        {{ __('protocols.assignments.no_information') }}
                    </p>
                @endforelse
            </article>
        </div>

        @if ($processingHistory->isNotEmpty())
            <div class="assignment-history">
                <h3>{{ __('protocols.assignments.history') }}</h3>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('protocols.assignments.officer') }}</th>
                                <th scope="col">{{ __('protocols.assignments.assigned_by') }}</th>
                                <th scope="col">{{ __('protocols.assignments.assigned_at') }}</th>
                                <th scope="col">{{ __('protocols.assignments.due_at') }}</th>
                                <th scope="col">{{ __('protocols.assignments.status') }}</th>
                                <th scope="col">{{ __('protocols.assignments.status_date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($processingHistory as $assignment)
                                @php
                                    $wasCompleted = $assignment->completed_at !== null;
                                    $statusDate = $assignment->completed_at
                                        ?? $assignment->superseded_at;
                                @endphp

                                <tr>
                                    <td>
                                        {{ $assignment->assignee?->name
                                            ?? __('protocols.assignments.former_user') }}
                                    </td>
                                    <td>
                                        {{ $assignment->assigner?->name
                                            ?? __('protocols.assignments.former_user') }}
                                    </td>
                                    <td>{{ $assignment->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        {{ $assignment->due_at?->format('d/m/Y H:i')
                                            ?? '—' }}
                                    </td>
                                    <td>
                                        <span class="status-badge {{ $wasCompleted
                                            ? 'status-completed'
                                            : 'status-superseded' }}">
                                            {{ $wasCompleted
                                                ? __('protocols.statuses.completed')
                                                : __('protocols.statuses.superseded') }}
                                        </span>
                                    </td>
                                    <td>{{ $statusDate?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    <section class="content-section" aria-labelledby="attachments-heading">
        <div class="section-header">
            <div>
                <h2 id="attachments-heading">{{ __('protocols.attachments.title') }}</h2>
                <p class="muted-text">{{ __('protocols.attachments.description') }}</p>
            </div>
        </div>

        @forelse ($protocol->attachments as $attachment)
            <div class="attachment-item">
                <div class="attachment-details">
                    <p>
                        <strong>{{ __('protocols.attachments.filename') }}:</strong>
                        {{ $attachment->original_name }}
                    </p>

                    <p>
                        <strong>{{ __('protocols.attachments.file_type') }}:</strong>
                        {{ $attachment->mime_type }}
                    </p>

                    <p>
                        <strong>{{ __('protocols.attachments.file_size') }}:</strong>
                        {{ number_format($attachment->file_size / 1024, 2) }} KB
                    </p>

                    <p>
                        <strong>{{ __('protocols.attachments.uploaded_at') }}:</strong>
                        {{ $attachment->created_at->format('d/m/Y H:i') }}
                    </p>
                </div>

                <div class="page-actions">
                    <a
                        href="{{ route('protocols.attachments.download', [
                            'protocol' => $protocol,
                            'attachment' => $attachment,
                        ]) }}"
                        class="button button-primary"
                    >
                        {{ __('protocols.attachments.download') }}
                    </a>

                    @can('update', $protocol)
                        <form
                            action="{{ route('protocols.attachments.destroy', [
                                'protocol' => $protocol,
                                'attachment' => $attachment,
                            ]) }}"
                            method="POST"
                            onsubmit="return confirm('{{ __('protocols.confirmations.delete_attachment') }}')"
                        >
                            @csrf
                            @method('DELETE')

                            <button type="submit" class="button button-danger">
                                {{ __('protocols.attachments.delete') }}
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <p class="muted-text">
                {{ __('protocols.attachments.empty') }}
            </p>
        @endforelse
    </section>

    <section
        class="record-metadata"
        aria-label="{{ __('protocols.metadata.aria_label') }}"
    >
        <p>
            <strong>{{ __('protocols.metadata.created_at') }}:</strong>
            {{ $protocol->created_at->format('d/m/Y H:i') }}
        </p>

        <p>
            <strong>{{ __('protocols.metadata.updated_at') }}:</strong>
            {{ $protocol->updated_at->format('d/m/Y H:i') }}
        </p>
    </section>
@endsection
