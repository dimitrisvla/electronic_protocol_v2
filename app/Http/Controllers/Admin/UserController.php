<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display all registered users and their current roles.
     */
    public function index(Request $request): View
    {
        // Only an Administrator may access user management.
        abort_unless(
            $request->user()->isAdministrator(),
            403
        );

        // Sort users alphabetically and divide the results into pages.
        $users = User::query()
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Display the Administrator-only user creation form.
     */
    public function create(Request $request): View
    {
        // Authentication is handled by the route middleware. This additional
        // role check prevents Protocol Officers and Viewers from opening the
        // form by entering its URL manually.
        abort_unless(
            $request->user()->isAdministrator(),
            403
        );

        // Pass the enum cases to the view so the form cannot drift away from
        // the roles supported by the application.
        $roles = UserRole::cases();

        return view('admin.users.create', compact('roles'));
    }

    /**
     * Validate and create a user without changing the current session.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /*
         * Assign the role directly because it is intentionally excluded
         * from the User model's mass-assignable attributes.
         *
         * The User model's "hashed" password cast converts the submitted
         * plain-text password into a secure hash when the model is saved.
         */
        $user = new User();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = $validated['password'];
        $user->role = $validated['role'];
        $user->save();

        /*
         * Do not call Auth::login() or $request->session()->regenerate().
         * The Administrator who submitted the form must remain the current
         * authenticated user.
         */
        return redirect()
            ->route('admin.users.index')
            ->with('success', __('flash.users.created'));
    }

    /**
     * Update one user's application role.
     */
    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user
    ): RedirectResponse {
        // Assign the role directly rather than making it globally
        // mass assignable on the User model.
        $user->role = $request->validated('role');
        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('flash.users.role_updated'));
    }

    /**
     * Permanently delete one registered user.
     */
    public function destroy(
        Request $request,
        User $user
    ): RedirectResponse {
        // Authentication is enforced by the route. This role check prevents
        // Protocol Officers and Viewers from bypassing the interface with a
        // direct DELETE request.
        abort_unless(
            $request->user()->isAdministrator(),
            403
        );

        /*
         * Exact original-application parity allows an Administrator to
         * delete any account, including another Administrator or their own
         * account. Remember whether this is self-deletion before removing
         * the database record.
         */
        $deletingOwnAccount = $request->user()->is($user);

        $user->delete();

        if ($deletingOwnAccount) {
            /*
             * The authenticated account no longer exists. End the session
             * immediately so it cannot continue referring to the deleted
             * user. If this was the final Administrator, the login page will
             * now show the public Register link again.
             */
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('success', __('flash.users.own_account_deleted'));
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('flash.users.deleted'));
    }
}
