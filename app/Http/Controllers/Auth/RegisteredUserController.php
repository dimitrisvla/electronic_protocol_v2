<?php

namespace App\Http\Controllers\Auth;

// Import the application's roles so public bootstrap registration can
// explicitly create the first Administrator.
use App\Enums\UserRole;

// Import the application's base controller.
use App\Http\Controllers\Controller;

// Import the User model so that we can create a user record.
use App\Models\User;

// Import the event Laravel dispatches after registration.
use Illuminate\Auth\Events\Registered;

// Used as the return type when redirecting the user.
use Illuminate\Http\RedirectResponse;

// Represents the incoming HTTP request containing the form data.
use Illuminate\Http\Request;

// Provides Laravel's authentication methods.
use Illuminate\Support\Facades\Auth;

// Securely hashes the user's password before database storage.
use Illuminate\Support\Facades\Hash;

// Provides Laravel's standard password-validation rules.
use Illuminate\Validation\Rules\Password;

// Used as the return type for methods that return a Blade view.
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration form.
     *
     * This method handles:
     * GET /register
     */
    public function create(): View
    {
        // Laravel will try to find:
        // resources/views/auth/register.blade.php
        return view('auth.register');
    }

    /**
     * Validate the registration data and create the user.
     *
     * This method handles:
     * POST /register
     */
    public function store(Request $request): RedirectResponse
    {
        /**
         * Validate all the submitted registration fields.
         *
         * If validation fails, Laravel automatically:
         * 1. Redirects the user back to the registration form.
         * 2. Stores validation errors in the session.
         * 3. Makes the errors available to the Blade view.
         */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255',],

            'email' => ['required', 'string', 'email', 'max:255',

                // The email must not already exist in the users table.
                'unique:users,email',
            ],

            'password' => [
                'required',

                /**
                 * Requires a matching password_confirmation field.
                 *
                 * The registration form must therefore contain:
                 * - password
                 * - password_confirmation
                 */
                'confirmed',

                // Uses Laravel's default password requirements.
                Password::defaults(),
            ],
        ]);

        /**
         * Create and save the new user.
         *
         * Hash::make() converts the plain-text password into a
         * secure one-way hash before it is stored in the database.
         */
        $user = new User();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = Hash::make($validated['password']);

        /*
         * Public registration is available only when no Administrator
         * exists. The registration middleware enforces that condition for
         * both GET /register and POST /register.
         *
         * Assign the role directly because "role" is intentionally excluded
         * from the User model's mass-assignable attributes. Any role value
         * manually submitted by a visitor is ignored.
         */
        $user->role = UserRole::Administrator;
        $user->save();

        /**
         * Dispatch Laravel's Registered event.
         *
         * This allows registration-related listeners, such as email
         * verification, to run if we enable them in the future.
         */
        event(new Registered($user));

        /**
         * Authenticate the newly registered user immediately.
         */
        Auth::login($user);

        /**
         * Generate a new session ID after authentication.
         *
         * This protects the user against session-fixation attacks.
         */
        $request->session()->regenerate();

        /**
         * Redirect the authenticated user to the protocol list.
         *
         * The success message will be stored temporarily in the session.
         */
        return redirect()
            ->route('protocols.index')
            ->with('success', __('flash.auth.registered'));
    }
}
