<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArchiveFolderRequest;
use App\Http\Requests\UpdateArchiveFolderRequest;
use App\Models\ArchiveFolder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Displays and maintains the archive-folder and retention catalogue.
 *
 * The original application used one page for the list, insertion form, and
 * editing form. This controller preserves that workflow while delegating
 * authorization to ArchiveFolderPolicy and validation to Form Requests.
 */
class ArchiveFolderController extends Controller
{
    /**
     * Display the archive-folder catalogue and a blank insertion form.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', ArchiveFolder::class);

        return $this->catalogueView(new ArchiveFolder());
    }

    /**
     * Store a new archive-folder definition.
     */
    public function store(
        StoreArchiveFolderRequest $request
    ): RedirectResponse {
        DB::transaction(function () use ($request): void {
            ArchiveFolder::query()->create([
                ...$request->validated(),
                'is_selectable' => true,
                'is_active' => true,
                'sort_order' => ArchiveFolder::query()->max('sort_order') + 1,
            ]);

            $this->synchronizeCatalogueStructure();
        });

        return redirect()
            ->route('admin.archive-folders.index')
            ->with('success', __('flash.archive_folders.created'));
    }

    /**
     * Display the same catalogue page with one row loaded into the form.
     */
    public function edit(ArchiveFolder $archiveFolder): View
    {
        Gate::authorize('update', $archiveFolder);

        return $this->catalogueView($archiveFolder);
    }

    /**
     * Update an existing folder and its suggested retention value.
     */
    public function update(
        UpdateArchiveFolderRequest $request,
        ArchiveFolder $archiveFolder
    ): RedirectResponse {
        DB::transaction(function () use ($request, $archiveFolder): void {
            $archiveFolder->update($request->validated());

            /*
             * A changed code can also change the logical parent of this row or
             * its children, so rebuild the hierarchy and natural code order.
             */
            $this->synchronizeCatalogueStructure();
        });

        return redirect()
            ->route('admin.archive-folders.index')
            ->with('success', __('flash.archive_folders.updated'));
    }

    /**
     * Permanently remove one catalogue entry.
     *
     * Child rows are not deleted. The self-referencing foreign key first sets
     * their parent_id to null, and synchronization then resolves any remaining
     * parent code that exists in the catalogue.
     */
    public function destroy(ArchiveFolder $archiveFolder): RedirectResponse
    {
        Gate::authorize('delete', $archiveFolder);

        DB::transaction(function () use ($archiveFolder): void {
            $archiveFolder->delete();

            $this->synchronizeCatalogueStructure();
        });

        return redirect()
            ->route('admin.archive-folders.index')
            ->with('success', __('flash.archive_folders.deleted'));
    }

    /**
     * Build the shared one-page catalogue view.
     */
    private function catalogueView(ArchiveFolder $editingArchiveFolder): View
    {
        $archiveFolders = ArchiveFolder::query()
            ->with('parent')
            ->ordered()
            ->paginate(15);

        return view('admin.archive-folders.index', [
            'archiveFolders' => $archiveFolders,
            'editingArchiveFolder' => $editingArchiveFolder,
        ]);
    }

    /**
     * Recalculate parent relationships and natural folder ordering.
     *
     * Folder codes contain their hierarchy. For example, Φ.14.1.1 has the
     * immediate parent Φ.14.1. Recalculating after every administrative change
     * also repairs children when a missing parent is created later.
     */
    private function synchronizeCatalogueStructure(): void
    {
        $folders = ArchiveFolder::query()
            ->get(['id', 'code', 'parent_id', 'sort_order'])
            ->sort(
                fn (ArchiveFolder $left, ArchiveFolder $right): int =>
                    strnatcasecmp($left->code, $right->code)
            )
            ->values();

        $idsByCode = $folders->pluck('id', 'code');

        foreach ($folders as $position => $folder) {
            $parentCode = $this->parentCode($folder->code);
            $parentId = $parentCode === null
                ? null
                : $idsByCode->get($parentCode);
            $sortOrder = $position + 1;

            if (
                $folder->parent_id === $parentId
                && $folder->sort_order === $sortOrder
            ) {
                continue;
            }

            /*
             * Use the query builder so this structural normalization does not
             * make every reordered catalogue row appear manually edited.
             */
            DB::table('archive_folders')
                ->where('id', $folder->id)
                ->update([
                    'parent_id' => $parentId,
                    'sort_order' => $sortOrder,
                ]);
        }
    }

    /**
     * Extract the immediate parent code from a hierarchical folder code.
     */
    private function parentCode(string $code): ?string
    {
        if (substr_count($code, '.') < 2) {
            return null;
        }

        $lastSeparator = strrpos($code, '.');

        return $lastSeparator === false
            ? null
            : substr($code, 0, $lastSeparator);
    }
}
