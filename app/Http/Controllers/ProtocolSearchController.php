<?php

namespace App\Http\Controllers;

use App\Enums\ProtocolAssignmentPurpose;
use App\Enums\ProtocolSearchField;
use App\Http\Requests\SearchProtocolsRequest;
use App\Models\Protocol;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Reproduce the original multi-criterion search with safe allow-listed fields. */
class ProtocolSearchController extends Controller
{
    public function index(
        SearchProtocolsRequest $request
    ): View|RedirectResponse {
        $validated = $request->validated();
        $exactNotFound = false;

        if (isset($validated['exact_number'], $validated['exact_year'])) {
            $exactProtocol = Protocol::query()
                ->where('protocol_number', $validated['exact_number'])
                ->where('protocol_year', $validated['exact_year'])
                ->first();

            if ($exactProtocol !== null) {
                return redirect()->route('protocols.show', $exactProtocol);
            }

            $exactNotFound = true;
        }

        $hasCriteria = $this->hasAdvancedCriteria($validated);
        $protocols = null;

        if ($hasCriteria) {
            $query = Protocol::query()->with('archiveFolder');

            $query
                ->when(isset($validated['number_from']), fn (Builder $query) =>
                    $query->where('protocol_number', '>=', $validated['number_from']))
                ->when(isset($validated['number_to']), fn (Builder $query) =>
                    $query->where('protocol_number', '<=', $validated['number_to']))
                ->when(isset($validated['protocol_year']), fn (Builder $query) =>
                    $query->where('protocol_year', $validated['protocol_year']))
                ->when(isset($validated['date_from']), fn (Builder $query) =>
                    $query->whereDate('protocol_date', '>=', $validated['date_from']))
                ->when(isset($validated['date_to']), fn (Builder $query) =>
                    $query->whereDate('protocol_date', '<=', $validated['date_to']))
                ->when(isset($validated['direction']), fn (Builder $query) =>
                    $query->where('direction', $validated['direction']));

            foreach (range(1, 3) as $position) {
                $field = ProtocolSearchField::tryFrom(
                    (string) ($validated["field_{$position}"] ?? '')
                );

                if ($field === null) {
                    continue;
                }

                $this->applyCriterion(
                    $query,
                    $field,
                    trim((string) ($validated["term_{$position}"] ?? '')),
                    (bool) ($validated["empty_{$position}"] ?? false)
                );
            }

            $protocols = $query
                ->orderByDesc('protocol_date')
                ->orderByDesc('protocol_number')
                ->paginate(15)
                ->withQueryString();
        }

        return view('protocols.search', [
            'protocols' => $protocols,
            'searchFields' => ProtocolSearchField::cases(),
            'hasCriteria' => $hasCriteria,
            'exactNotFound' => $exactNotFound,
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function hasAdvancedCriteria(array $validated): bool
    {
        foreach (['number_from', 'number_to', 'protocol_year', 'date_from', 'date_to', 'direction'] as $key) {
            if (isset($validated[$key]) && $validated[$key] !== '') {
                return true;
            }
        }

        foreach (range(1, 3) as $position) {
            if (isset($validated["field_{$position}"])) {
                return true;
            }
        }

        return false;
    }

    private function applyCriterion(
        Builder $query,
        ProtocolSearchField $field,
        string $term,
        bool $searchEmpty
    ): void {
        $column = match ($field) {
            ProtocolSearchField::Subject => 'subject',
            ProtocolSearchField::Sender => 'sender',
            ProtocolSearchField::Recipient => 'recipient',
            ProtocolSearchField::Notes => 'notes',
            default => null,
        };

        if ($column !== null) {
            $searchEmpty
                ? $query->where(fn (Builder $query) => $query
                    ->whereNull($column)->orWhere($column, ''))
                : $query->where($column, 'like', "%{$term}%");

            return;
        }

        if ($field === ProtocolSearchField::ArchiveFolder) {
            $searchEmpty
                ? $query->whereDoesntHave('archiveFolder')
                : $query->whereHas('archiveFolder', fn (Builder $query) =>
                    $query->where(fn (Builder $query) => $query
                        ->where('code', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")));

            return;
        }

        if ($field === ProtocolSearchField::AttachmentName) {
            $searchEmpty
                ? $query->whereDoesntHave('attachments')
                : $query->whereHas('attachments', fn (Builder $query) =>
                    $query->where('original_name', 'like', "%{$term}%"));

            return;
        }

        $currentProcessing = fn (Builder $query) => $query
            ->where('purpose', ProtocolAssignmentPurpose::Processing->value)
            ->whereNull('completed_at')
            ->whereNull('superseded_at');

        if ($searchEmpty) {
            $query->whereDoesntHave('assignments', $currentProcessing);

            return;
        }

        $query->whereHas('assignments', function (Builder $query) use (
            $currentProcessing,
            $term
        ): void {
            $currentProcessing($query);
            $query->whereHas('assignee', fn (Builder $query) =>
                $query->where('name', 'like', "%{$term}%"));
        });
    }
}
