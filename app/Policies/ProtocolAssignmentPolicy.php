<?php

namespace App\Policies;

use App\Enums\ProtocolAssignmentPurpose;
use App\Models\ProtocolAssignment;
use App\Models\User;

class ProtocolAssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Every authenticated role may have a scoped assignment queue.
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProtocolAssignment $protocolAssignment): bool
    {
        if ($user->isAdministrator() || $user->isAssigner()) {
            return true;
        }

        return $protocolAssignment->assigned_to !== null
            && (int) $protocolAssignment->assigned_to === (int) $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdministrator()
            || $user->isAssigner();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProtocolAssignment $protocolAssignment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProtocolAssignment $protocolAssignment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProtocolAssignment $protocolAssignment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProtocolAssignment $protocolAssignment): bool
    {
        return false;
    }

    /**
     * Determine whether the user may complete processing work.
     *
     * Only an active processing assignment can be completed. Administrators
     * may complete any active processing assignment, while a Protocol Officer
     * may complete only the active assignment addressed to them.
     */
    public function complete(
        User $user,
        ProtocolAssignment $protocolAssignment
    ): bool {
        if (
            $protocolAssignment->purpose
                !== ProtocolAssignmentPurpose::Processing
            || $protocolAssignment->completed_at !== null
            || $protocolAssignment->superseded_at !== null
        ) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        return $user->isProtocolOfficer()
            && $protocolAssignment->assigned_to !== null
            && (int) $protocolAssignment->assigned_to === (int) $user->id;
    }
}
