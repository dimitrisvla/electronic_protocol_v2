<?php

namespace App\Policies;

// Import the models used by the authorization methods.
use App\Models\Protocol;
use App\Models\User;

class ProtocolPolicy
{
    /**
     * Determine whether the user may view the protocol list.
     *
     * The protocol routes already use the "auth" middleware,
     * so every user reaching this method is authenticated.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user may view a particular protocol.
     *
     * Our current rule allows every authenticated user to read
     * every protocol.
     */
    public function view(User $user, Protocol $protocol): bool
    {
        return true;
    }

    /**
     * Determine whether the user may open the recycle bin.
     *
     * Administrators need access to every deleted protocol.
     * Protocol Officers need access so that they can restore
     * protocols they created themselves.
     *
     * Viewers have read-only access to active protocols and must
     * not be able to open the deleted-protocol listing.
     */
    public function viewDeleted(User $user): bool
    {
        return $user->isAdministrator()
            || $user->isProtocolOfficer();
    }

    /**
     * Determine whether the user may create protocols.
     *
     * Administrators, Assigners, and Protocol Officers may create protocols.
     * Viewers have read-only access and therefore cannot create one.
     */
    public function create(User $user): bool
    {
        return $user->isAdministrator()
            || $user->isAssigner()
            || $user->isProtocolOfficer();
    }

    /**
     * Determine whether the user may update a protocol.
     *
     * Administrators may update every protocol, including ownerless
     * records imported from the legacy application.
     *
     * Assigners may edit protocols as part of their operational assignment
     * work, regardless of who originally created the protocol.
     *
     * Protocol Officers may update only protocols they created.
     * Viewers cannot update protocols.
     */
    public function update(User $user, Protocol $protocol): bool
    {
        // Administrators have global protocol-management permission.
        if ($user->isAdministrator()) {
            return true;
        }

        // Assigners need broad edit access to prepare and assign protocols.
        if ($user->isAssigner()) {
            return true;
        }

        // Viewers and any future unsupported roles are rejected.
        if (! $user->isProtocolOfficer()) {
            return false;
        }

        // A Protocol Officer must be the protocol's creator.
        return $this->userOwnsProtocol($user, $protocol);
    }

    /**
     * Determine whether the user may delete a protocol.
     *
     * Administrators may delete any protocol. Protocol Officers may delete
     * only protocols they created. Assigners can edit and assign protocols,
     * but deletion is outside their operational role.
     */
    public function delete(User $user, Protocol $protocol): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        return $user->isProtocolOfficer()
            && $this->userOwnsProtocol($user, $protocol);
    }

    /**
     * Determine whether the user may restore a soft-deleted protocol.
     *
     * Administrators may restore any deleted protocol, including an
     * ownerless legacy record. Protocol Officers may restore only
     * deleted protocols they originally created. Viewers cannot restore.
     */
    public function restore(User $user, Protocol $protocol): bool
    {
        return $this->delete($user, $protocol);
    }

    /**
     * Determine whether the user may permanently delete a protocol.
     *
     * Permanent deletion is irreversible. It removes the protocol,
     * its attachment records, and its private files.
     *
     * This high-risk operation is reserved for Administrators.
     */
    public function forceDelete(User $user, Protocol $protocol): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may create an assignment for a protocol.
     *
     * Assignment is limited to active protocols and to the Administrator and
     * Assigner roles. Protocol Officers process work but cannot assign it.
     */
    public function assign(User $user, Protocol $protocol): bool
    {
        if ($protocol->trashed()) {
            return false;
        }

        return $user->isAdministrator()
            || $user->isAssigner();
    }

    /**
     * Reassignment uses the same authorization rule as initial assignment.
     */
    public function reassign(User $user, Protocol $protocol): bool
    {
        return $this->assign($user, $protocol);
    }

    /**
     * Determine whether the supplied user created the protocol.
     *
     * Keeping this comparison in one private method ensures that the
     * ownership rule is applied consistently by update(), delete(),
     * and restore().
     */
    private function userOwnsProtocol(
        User $user,
        Protocol $protocol
    ): bool {
        /*
         * An ownerless legacy protocol cannot belong to a Protocol
         * Officer. Administrators are handled before this method is
         * called and can still manage those ownerless records.
         */
        return $protocol->created_by !== null
            && (int) $user->id === (int) $protocol->created_by;
    }
}