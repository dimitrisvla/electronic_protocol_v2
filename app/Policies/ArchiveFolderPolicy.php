<?php

namespace App\Policies;

use App\Models\ArchiveFolder;
use App\Models\User;

/**
 * Authorization rules for the archive-folder catalogue.
 *
 * The original application let every authenticated user consult retention
 * definitions while showing modification controls only to Administrators.
 */
class ArchiveFolderPolicy
{
    /**
     * Every authenticated role may consult the catalogue.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Every authenticated role may view an individual definition.
     */
    public function view(User $user, ArchiveFolder $archiveFolder): bool
    {
        return true;
    }

    /**
     * Only Administrators may add archive folders.
     */
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Only Administrators may change archive folders.
     */
    public function update(User $user, ArchiveFolder $archiveFolder): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Only Administrators may remove archive folders.
     */
    public function delete(User $user, ArchiveFolder $archiveFolder): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Archive folders do not use soft deletion.
     */
    public function restore(User $user, ArchiveFolder $archiveFolder): bool
    {
        return false;
    }

    /**
     * Archive folders have no separate permanent-deletion operation.
     */
    public function forceDelete(User $user, ArchiveFolder $archiveFolder): bool
    {
        return false;
    }
}
