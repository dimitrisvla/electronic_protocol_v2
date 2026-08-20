<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ProtocolAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProtocolAssignmentQueueController extends Controller
{
    /**
     * Queue names accepted through the query string.
     *
     * @var array<string, string>
     */
    private const QUEUES = [
        'processing' => 'My Processing Work',
        'information' => 'For Information',
        'completed' => 'Completed Work',
    ];

    /**
     * Display one role-scoped assignment queue.
     *
     * Administrators and Assigners receive organization-wide oversight.
     * Every other role is constrained to rows addressed to that user before
     * the selected queue type, ordering, or pagination is applied.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProtocolAssignment::class);

        $user = $request->user();
        $queue = $this->resolveQueue($request, $user);
        $canViewAllAssignments = $user->isAdministrator()
            || $user->role === UserRole::Assigner;

        $visibleAssignments = ProtocolAssignment::query()
            // A soft-deleted protocol must disappear from every active queue.
            ->whereHas('protocol')
            ->when(
                ! $canViewAllAssignments,
                fn (Builder $query): Builder => $query->where(
                    'assigned_to',
                    $user->getKey()
                )
            );

        $queueCounts = collect(array_keys(self::QUEUES))
            ->mapWithKeys(fn (string $queueName): array => [
                $queueName => $this->applyQueueFilter(
                    clone $visibleAssignments,
                    $queueName
                )->count(),
            ]);

        $assignments = $this->applyQueueFilter(
            clone $visibleAssignments,
            $queue
        )
            ->with(['protocol', 'assigner', 'assignee'])
            ->when(
                $queue === 'processing',
                fn (Builder $query): Builder => $query
                    // Known deadlines come first, earliest deadline first.
                    ->orderByRaw(
                        'CASE WHEN due_at IS NULL THEN 1 ELSE 0 END'
                    )
                    ->orderBy('due_at')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id'),
                fn (Builder $query): Builder => $query
                    ->orderByDesc(
                        $queue === 'completed'
                            ? 'completed_at'
                            : 'created_at'
                    )
                    ->orderByDesc('id')
            )
            ->paginate(15)
            ->withQueryString();

        return view('assignments.index', [
            'assignments' => $assignments,
            'queue' => $queue,
            'queueTitle' => self::QUEUES[$queue],
            'queueCounts' => $queueCounts,
            'canViewAllAssignments' => $canViewAllAssignments,
        ]);
    }

    /**
     * Resolve and validate the requested queue name.
     */
    private function resolveQueue(Request $request, User $user): string
    {
        $requestedQueue = $request->query('queue');

        abort_unless(
            $requestedQueue === null || is_string($requestedQueue),
            404
        );

        $queue = $requestedQueue;

        if ($queue === null || $queue === '') {
            $queue = $user->isViewer()
                ? 'information'
                : 'processing';
        }

        abort_unless(array_key_exists($queue, self::QUEUES), 404);

        return $queue;
    }

    /**
     * Apply the lifecycle conditions belonging to one queue.
     */
    private function applyQueueFilter(
        Builder $query,
        string $queue
    ): Builder {
        return match ($queue) {
            'processing' => $query->pending(),
            'information' => $query->information(),
            'completed' => $query->completed(),
        };
    }
}