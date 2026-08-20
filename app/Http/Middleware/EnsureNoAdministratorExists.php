<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNoAdministratorExists
{
    /**
     * Allow public registration only while no Administrator exists.
     *
     * Applying this middleware to both registration routes prevents a guest
     * from bypassing the hidden form by submitting POST /register manually.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response|RedirectResponse {
        $administratorExists = User::query()
            ->where('role', UserRole::Administrator->value)
            ->exists();

        if ($administratorExists) {
            return redirect()
                ->route('login')
                ->with(
                    'error',
                    'Registration is unavailable. Please log in.'
                );
        }

        return $next($request);
    }
}
