<?php

namespace App\Policies;

use App\Models\User;

/**
 * Restricts application-wide configuration to Administrators.
 */
class ApplicationSettingPolicy
{
    /**
     * Determine whether the user may view the settings page.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user may update application settings.
     */
    public function updateAny(User $user): bool
    {
        return $user->isAdministrator();
    }
}
