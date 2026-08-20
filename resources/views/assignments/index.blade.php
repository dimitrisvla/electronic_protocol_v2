@extends('layouts.app')

@php
    $displayTitle = match ($queue) {
        'processing' => $canViewAllAssignments
            ? __('assignments.titles.processing.oversight')
            : __('assignments.titles.processing.personal'),
        'information' => $canViewAllAssignments
            ? __('assignments.titles.information.oversight')
            : __('assignments.titles.information.personal'),
        'completed' => $canViewAllAssignments
            ? __('assignments.titles.completed.oversight')
            : __('assignments.titles.completed.personal'),
    };

    $scopeDescription = $canViewAllAssignments
        ? __('assignments.scope.organization')
        : __('assignments.scope.personal');

    $emptyMessage = match ($queue) {
        'processing' => __('assignments.empty.processing'),
        'information' => __('assignments.empty.information'),
        'completed' => __('assignments.empty.completed'),
    };

    $columnCount = match (true) {
        $canViewAllAssignments && $queue !== 'information' => 8,
        $canViewAllAssignments => 7,
        $queue !== 'information' => 7,
        default => 6,
    };
@endphp

@section('title', $displayTitle)

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $displayTitle }}</h1>
            <p>{{ $scopeDescription }}</p>
        </div>

        <a href="{{ route('protocols.index') }}" class="button button-secondary">
            {{ __('protocols.actions.back_to_protocols') }}
        </a>
    </div>

    <nav
        class="queue-tabs"
        aria-label="{{ __('assignments.aria.queue_tabs') }}"
    >
        <a
            href="{{ route('assignments.index', ['queue' => 'processing']) }}"
            class="queue-tab {{ $queue === 'processing'
                ? 'queue-tab-active'
                : '' }}"
            @if ($queue === 'processing') aria-current="page" @endif
        >
            <span>{{ __('assignments.tabs.processing') }}</span>
            <span class="queue-count">{{ $queueCounts['processing'] }}</span>
        </a>

        <a
            href="{{ route('assignments.index', ['queue' => 'information']) }}"
            class="queue-tab {{ $queue === 'information'
                ? 'queue-tab-active'
                : '' }}"
            @if ($queue === 'information') aria-current="page" @endif
        >
            <span>{{ __('assignments.tabs.information') }}</span>
            <span class="queue-count">{{ $queueCounts['information'] }}</span>
        </a>

        <a
            href="{{ route('assignments.index', ['queue' => 'completed']) }}"
            class="queue-tab {{ $queue === 'completed'
                ? 'queue-tab-active'
                : '' }}"
            @if ($queue === 'completed') aria-current="page" @endif
        >
            <span>{{ __('assignments.tabs.completed') }}</span>
            <span class="queue-count">{{ $queueCounts['completed'] }}</span>
        </a>
    </nav>

    <div class="queue-context">
        <strong>
            {{ trans_choice(
                'assignments.count',
                $assignments->total(),
                ['count' => $assignments->total()]
            ) }}
        </strong>

        <span>
            @if ($queue === 'processing')
                {{ __('assignments.ordering.processing') }}
            @elseif ($queue === 'information')
                {{ __('assignments.ordering.information') }}
            @else
                {{ __('assignments.ordering.completed') }}
            @endif
        </span>
    </div>

    <div class="table-container">
        <table aria-label="{{ $displayTitle }}">
            <thead>
                <tr>
                    <th scope="col">{{ __('assignments.columns.protocol') }}</th>
                    <th scope="col">{{ __('assignments.columns.direction') }}</th>
                    <th scope="col">{{ __('assignments.columns.subject') }}</th>

                    @if ($canViewAllAssignments)
                        <th scope="col">{{ __('assignments.columns.assigned_to') }}</th>
                    @endif

                    <th scope="col">{{ __('assignments.columns.assigned_by') }}</th>
                    <th scope="col">{{ __('assignments.columns.assigned_at') }}</th>

                    @if ($queue === 'processing')
                        <th scope="col">{{ __('assignments.columns.deadline') }}</th>
                    @elseif ($queue === 'completed')
                        <th scope="col">{{ __('assignments.columns.completed_at') }}</th>
                    @endif

                    <th scope="col">{{ __('assignments.columns.action') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($assignments as $assignment)
                    <tr>
                        <td>
                            <a
                                href="{{ route(
                                    'protocols.show',
                                    $assignment->protocol
                                ) }}"
                                class="protocol-reference"
                            >
                                {{ $assignment->protocol->protocol_number }}/{{ $assignment->protocol->protocol_year }}
                            </a>
                        </td>

                        <td>
                            <span
                                class="direction-badge direction-{{ $assignment->protocol->direction }}"
                            >
                                {{ __('protocols.directions.' . $assignment->protocol->direction) }}
                            </span>
                        </td>

                        <td>{{ $assignment->protocol->subject }}</td>

                        @if ($canViewAllAssignments)
                            <td>
                                {{ $assignment->assignee?->name
                                    ?? __('assignments.former_user') }}
                            </td>
                        @endif

                        <td>
                            {{ $assignment->assigner?->name
                                ?? __('assignments.former_user') }}
                        </td>

                        <td>
                            {{ $assignment->created_at->format('d/m/Y') }}
                            <span class="assignment-date">
                                {{ $assignment->created_at->format('H:i') }}
                            </span>
                        </td>

                        @if ($queue === 'processing')
                            <td>
                                @if ($assignment->due_at === null)
                                    <span class="status-badge status-neutral">
                                        {{ __('assignments.statuses.no_deadline') }}
                                    </span>
                                @elseif ($assignment->due_at->isPast())
                                    <span class="status-badge status-overdue">
                                        {{ __('assignments.statuses.overdue') }}
                                    </span>
                                    <span class="assignment-date">
                                        {{ $assignment->due_at
                                            ->format('d/m/Y H:i') }}
                                    </span>
                                @else
                                    <span class="status-badge status-upcoming">
                                        {{ __('assignments.statuses.due') }}
                                    </span>
                                    <span class="assignment-date">
                                        {{ $assignment->due_at
                                            ->format('d/m/Y H:i') }}
                                    </span>
                                @endif
                            </td>
                        @elseif ($queue === 'completed')
                            <td>
                                <span class="status-badge status-completed">
                                    {{ __('assignments.statuses.completed') }}
                                </span>
                                <span class="assignment-date">
                                    {{ $assignment->completed_at
                                        ->format('d/m/Y H:i') }}
                                </span>
                            </td>
                        @endif

                        <td>
                            <a
                                href="{{ route(
                                    'protocols.show',
                                    $assignment->protocol
                                ) }}"
                                class="button button-primary button-small"
                            >
                                {{ __('assignments.actions.view_protocol') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}">
                            <div class="empty-state">
                                <h2>{{ __('assignments.empty.title') }}</h2>
                                <p>{{ $emptyMessage }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($assignments->hasPages())
        <div class="pagination-container">
            {{ $assignments->links() }}
        </div>
    @endif
@endsection
