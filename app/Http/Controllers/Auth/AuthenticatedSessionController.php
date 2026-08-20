<?php

namespace App\Http\Controllers\Auth;

// Import the application's base controller.
use App\Http\Controllers\Controller;

// Used as the return type for redirects.
use Illuminate\Http\RedirectResponse;

// Represents the incoming HTTP request.
use Illuminate\Http\Request;

// Provides Laravel's authentication functionality.
use Illuminate\Support\Facades\Auth;

// Used as the return type for methods that return a Blade view.
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login form.
     *
     * Handles:
     * GET /login
     */
    public function create(): View
    {
        // Laravel will look for:
        // resources/views/auth/login.blade.php
        return view('auth.login');
    }

    /**
     * Validate the credentials and authenticate the user.
     *
     * Handles:
     * POST /login
     */
    public function store(Request $request): RedirectResponse
    {
        /**
         * Validate the submitted login data.
         *
         * The "remember" field is optional because the user
         * may leave the checkbox unchecked.
         */
        $request->validate([
            'email' => ['required', 'email',],

            'password' => ['required', 'string',],

            'remember' => ['sometimes', 'boolean',],
        ]);

        /**
         * Only email and password are authentication credentials.
         *
         * The "remember" value must not be included in this array
         * because it is not a column used to identify the user.
         */
        $credentials = $request->only('email', 'password');

        /**
         * Attempt to authenticate the user.
         *
         * Auth::attempt() finds the user by email and securely compares
         * the submitted password with the stored password hash.
         *
         * We must not hash the submitted login password ourselves.
         */
        if (! Auth::attempt(
            $credentials,
            $request->boolean('remember')
        )) {
            /**
             * Authentication failed.
             *
             * Return to the login form with a general error message.
             * We intentionally do not reveal whether the email address
             * or the password was incorrect.
             */
            return back()
                ->withErrors([
                    'email' => __('auth.failed'),
                ])
                ->onlyInput('email');
        }

        /**
         * Authentication succeeded.
         *
         * Regenerate the session ID to prevent session-fixation attacks.
         */
        $request->session()->regenerate();

        /**
         * Redirect the user to the page they originally requested.
         *
         * If there is no intended page, redirect to the protocol list.
         */
        return redirect()
            ->intended(route('protocols.index'));
    }

    /**
     * Log the authenticated user out securely.
     *
     * Handles:
     * POST /logout
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Remove the user's authentication information.
        Auth::logout();

        /**
         * Invalidate all data associated with the current session.
         *
         * This prevents the old authenticated session from being reused.
         */
        $request->session()->invalidate();

        /**
         * Generate a new CSRF token because the previous token belonged
         * to the session that has just been invalidated.
         */
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', __('flash.auth.logged_out'));
    }
}
