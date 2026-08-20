{{-- Code: defines the shared Blade layout for the Laravel 13 app.
           It provides the page title, menu, messages, etc.

     resources/views/layouts/app.blade.php
--}}


<!DOCTYPE html>

{{-- Use Laravel's active locale as the document language. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    {{--
        Use an encoding that supports English, Greek, other
        writing systems, symbols, and emojis.
    --}}
    <meta charset="UTF-8">


    {{--
        Make the page responsive on mobile devices.

        The page width matches the width of the device, and the
        initial zoom level is set to 100%.
    --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    {{--
        Use the default title "Electronic Protocol" if a child
        view does not define its own title section.

        A child view can provide a title with:

        @section('title', 'Protocols')
    --}}
    <title>@yield('title', __('common.app_name'))</title>


    {{--
        These styles are shared by every Blade view that extends
        layouts.app.

        Individual views can use these CSS classes without
        repeating the styles in every file.
    --}}
    <style>
        /*
         * Include padding and borders inside the calculated
         * width and height of every HTML element.
         */
        * {
            box-sizing: border-box;
        }


        /*
         * General page appearance.
         */
        body {
            margin: 0;
            background-color: #f4f6f8;
            color: #333333;
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
        }


        /*
         * Shared application header.
         */
        header {
            background-color: #212529;
            color: white;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 30px;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
        }


        /*
         * Navigation links displayed inside the shared header.
         */
        header nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        header nav a {
            color: white;
            font-weight: bold;
            text-decoration: none;
        }

        header nav a:hover {
            text-decoration: underline;
        }

        /* Highlight the navigation destination containing the current page. */
        .navigation-link-active {
            padding-bottom: 3px;
            border-bottom: 3px solid #6ea8fe;
        }

        /*
         * Display the authenticated user's name without making
         * it look like a navigation link.
         */
        .authenticated-user {
            color: #dee2e6;
        }

        /*
         * Remove the browser's default form margin so the logout
         * form aligns correctly with the navigation links.
         */
        .logout-form {
            margin: 0;
        }

        /*
         * Make the logout button look like the other header links.
         * It remains a real button inside a POST form because logout
         * changes the user's authenticated session.
         */
        .logout-button {
            padding: 0;
            border: none;
            background: none;
            color: white;
            font-family: inherit;
            font-size: inherit;
            font-weight: bold;
            cursor: pointer;
        }

        .logout-button:hover {
            text-decoration: underline;
        }


        /*
         * Main page container.
         *
         * The page-specific content from each child view is
         * inserted into this element.
         */
        main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }


        /*
         * Place the page title and its related actions
         * next to each other.
         */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0;
        }

        .page-header p {
            margin: 6px 0 0;
            color: #6c757d;
        }


        /*
         * Keep the action buttons together and allow them
         * to move to another line when space is limited.
         */
        .page-actions,
        .table-actions,
        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }


        /*
         * Remove the browser's default margin from a form
         * placed inside the page actions.
         */
        .page-actions form,
        .table-actions form {
            margin: 0;
        }


        /*
         * Shared appearance for links and buttons that
         * represent application actions.
         */
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border: none;
            border-radius: 4px;
            color: white;
            font-family: inherit;
            font-size: 16px;
            line-height: 1.2;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }


        /*
         * Primary buttons are used for important positive actions,
         * such as creating or editing a protocol.
         */
        .button-primary {
            background-color: #0d6efd;
        }

        .button-primary:hover {
            background-color: #0b5ed7;
        }


        /*
         * Secondary buttons are used for navigation actions,
         * such as returning to the protocols list.
         */
        .button-secondary {
            background-color: #6c757d;
        }

        .button-secondary:hover {
            background-color: #5c636a;
        }


        /*
         * Danger buttons are used for destructive actions,
         * such as deleting a protocol.
         */
        .button-danger {
            background-color: #dc3545;
        }

        .button-danger:hover {
            background-color: #bb2d3b;
        }

        /* Success buttons are used for completing operational work. */
        .button-success {
            background-color: #198754;
        }

        .button-success:hover {
            background-color: #157347;
        }


        /*
         * Provide a visible keyboard-focus indicator for
         * links, buttons, and form controls.
         */
        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            outline: 3px solid rgba(13, 110, 253, 0.35);
            outline-offset: 2px;
        }


        /*
         * The table container allows horizontal scrolling
         * when the table is wider than the available space.
         */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #dddddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f1f3f5;
            color: #333333;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }


        /*
         * Space between the protocols table and Laravel's
         * pagination navigation.
         */
        .pagination-container {
            margin-top: 25px;
        }


        /*
         * Prevent pagination arrow icons from appearing
         * excessively large when no CSS framework is installed.
         */
        .pagination-container svg {
            width: 20px;
            height: 20px;
        }


        /*
         * Shared styling for forms.
         */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #333333;
            font-weight: bold;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            background-color: white;
            font-family: inherit;
            font-size: 16px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }


        /*
         * Blade's validation-error handling adds this class when
         * the corresponding field has a validation error.
         */
        .invalid-field {
            border-color: #dc3545;
        }

        .error-message {
            display: block;
            margin-top: 5px;
            color: #dc3545;
            font-size: 14px;
        }


        /*
         * Add space above a group of form action buttons.
         */
        .actions {
            margin-top: 25px;
        }


        /*
         * Style the temporary success message displayed
         * after an operation is completed successfully.
         */
        .success-message {
            margin-bottom: 25px;
            padding: 12px 16px;
            border: 1px solid #badbcc;
            border-radius: 4px;
            background-color: #d1e7dd;
            color: #0f5132;
        }


        /*
         * Display operational errors returned by an action, such as an
         * assignment that became unavailable before completion.
         */
        .error-summary {
            margin-bottom: 25px;
            padding: 12px 16px;
            border: 1px solid #f5c2c7;
            border-radius: 4px;
            background-color: #f8d7da;
            color: #842029;
        }


        /* Shared sections used by the protocol detail page. */
        .content-section {
            margin-top: 30px;
            padding-top: 5px;
            border-top: 1px solid #dee2e6;
        }

        .section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin: 20px 0;
        }

        .section-header h2,
        .assignment-card h3,
        .assignment-management h3,
        .assignment-history h3 {
            margin-top: 0;
        }

        .section-header p {
            margin: 4px 0 0;
        }

        .muted-text,
        .field-help {
            color: #6c757d;
        }

        .field-help {
            margin-top: 0;
            font-size: 14px;
        }


        /* Responsive protocol information grid. */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin: 0;
        }

        .detail-item {
            padding: 16px;
            border: 1px solid #e2e6ea;
            border-radius: 6px;
            background-color: #f8f9fa;
        }

        .detail-item-wide {
            grid-column: 1 / -1;
        }

        .detail-item dt,
        .assignment-details dt {
            margin-bottom: 4px;
            color: #6c757d;
            font-size: 14px;
            font-weight: bold;
        }

        .detail-item dd,
        .assignment-details dd {
            margin: 0;
        }


        /* Assignment management form. */
        .assignment-management {
            margin-bottom: 24px;
            padding: 22px;
            border: 1px solid #b6d4fe;
            border-radius: 8px;
            background-color: #f5f9ff;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .recipient-fieldset {
            margin: 0;
            padding: 18px;
            border: 1px solid #ced4da;
            border-radius: 6px;
        }

        .recipient-fieldset legend {
            padding: 0 6px;
            font-weight: bold;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background-color: white;
            font-weight: normal;
            cursor: pointer;
        }

        .checkbox-item:hover {
            border-color: #86b7fe;
        }

        .checkbox-item input {
            width: auto;
            margin-top: 4px;
        }

        .checkbox-item span,
        .checkbox-item small {
            display: block;
        }

        .checkbox-item small {
            margin-top: 2px;
            color: #6c757d;
        }


        /* Current assignment and information-recipient cards. */
        .assignment-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .assignment-card {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background-color: white;
        }

        .assignment-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .assignment-card-header h3 {
            margin-bottom: 0;
        }

        .assignment-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin: 0;
        }

        .assignment-complete-form {
            margin-top: 20px;
        }

        .recipient-summary {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 10px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .recipient-summary:first-of-type {
            padding-top: 0;
        }

        .recipient-summary:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .direction-badge,
        .status-badge {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #664d03;
        }

        .status-information {
            background-color: #cff4fc;
            color: #055160;
        }

        .status-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-superseded {
            background-color: #e2e3e5;
            color: #41464b;
        }

        .direction-incoming {
            background-color: #cff4fc;
            color: #055160;
        }

        .direction-outgoing {
            background-color: #e2e3e5;
            color: #41464b;
        }

        .status-overdue {
            background-color: #f8d7da;
            color: #842029;
        }

        .status-upcoming {
            background-color: #fff3cd;
            color: #664d03;
        }

        .status-neutral {
            background-color: #e9ecef;
            color: #495057;
        }

        .assignment-history {
            margin-top: 24px;
        }


        /* Assignment queue tabs, summary, table details, and empty state. */
        .queue-tabs {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .queue-tab {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 58px;
            padding: 12px 16px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            background-color: #ffffff;
            color: #343a40;
            font-weight: bold;
            text-decoration: none;
        }

        .queue-tab:hover {
            border-color: #86b7fe;
            background-color: #f8f9fa;
        }

        .queue-tab-active {
            border-color: #0d6efd;
            background-color: #e7f1ff;
            color: #084298;
        }

        .queue-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            min-height: 32px;
            padding: 3px 9px;
            border-radius: 999px;
            background-color: #e9ecef;
            color: #343a40;
            font-size: 14px;
        }

        .queue-tab-active .queue-count {
            background-color: #0d6efd;
            color: #ffffff;
        }

        .queue-context {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
            padding: 14px 16px;
            border-left: 4px solid #0d6efd;
            border-radius: 4px;
            background-color: #f8f9fa;
        }

        .queue-context span {
            color: #6c757d;
            text-align: right;
        }

        .protocol-reference {
            color: #0d6efd;
            font-weight: bold;
            text-decoration: none;
            white-space: nowrap;
        }

        .protocol-reference:hover {
            text-decoration: underline;
        }

        .assignment-date {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 13px;
            white-space: nowrap;
        }

        .button-small {
            padding: 7px 12px;
            font-size: 14px;
            white-space: nowrap;
        }

        .empty-state {
            padding: 38px 20px;
            text-align: center;
        }

        .empty-state h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .empty-state p {
            margin: 0;
            color: #6c757d;
        }


        /* Attachment cards and record timestamps. */
        .attachment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .attachment-item:first-of-type {
            padding-top: 0;
        }

        .attachment-item:last-child {
            border-bottom: none;
        }

        .attachment-details p {
            margin: 3px 0;
        }

        .record-metadata {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
        }

        .record-metadata p {
            margin: 0;
        }


        /*
         * Adjust the layout for tablets and mobile devices.
         */
        @media (max-width: 700px) {
            .header-content,
            .page-header,
            .attachment-item {
                align-items: flex-start;
                flex-direction: column;
            }

            .detail-grid,
            .form-grid,
            .checkbox-grid,
            .queue-tabs,
            .assignment-grid,
            .assignment-details {
                grid-template-columns: 1fr;
            }

            .queue-context {
                align-items: flex-start;
                flex-direction: column;
            }

            .queue-context span {
                text-align: left;
            }

            header nav {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }

            main {
                margin: 15px;
                padding: 20px;
            }
        }
    </style>
</head>


<body>

    {{-- Header displayed on all pages using this layout. --}}
    <header>

        {{--
            Keep the header content aligned with the maximum
            width of the main page content.
        --}}
        <div class="header-content">

            <h1>{{ __('common.app_name') }}</h1>


            <nav aria-label="{{ __('common.navigation.aria_label') }}">
                {{--
                    @auth displays this section only when Laravel's
                    current session belongs to an authenticated user.
                --}}
                @auth
                    {{--
                        route() generates the URL of a named route.

                        protocols.index points to the protocol list page:

                        GET /protocols
                    --}}
                    <a
                        href="{{ route('protocols.index') }}"
                        class="{{ request()->routeIs(
                            'protocols.index',
                            'protocols.show',
                            'protocols.edit',
                            'protocols.deleted'
                        )
                            ? 'navigation-link-active'
                            : '' }}"
                        @if (request()->routeIs(
                            'protocols.index',
                            'protocols.show',
                            'protocols.edit',
                            'protocols.deleted'
                        ))
                            aria-current="page"
                        @endif
                    >
                        {{ __('common.navigation.protocols') }}
                    </a>


                    {{--
                        Every authenticated role has an assignment queue.
                        The link wording and default destination reflect the
                        role while the controller enforces the database scope.
                    --}}
                    @php
                        $navigationUser = auth()->user();
                        $hasAssignmentOversight =
                            $navigationUser->isAdministrator()
                            || $navigationUser->role
                                === \App\Enums\UserRole::Assigner;
                        $navigationQueue = $navigationUser->isViewer()
                            ? 'information'
                            : 'processing';
                        $assignmentNavigationLabel = match (true) {
                            $hasAssignmentOversight => __(
                                'common.navigation.assignment_oversight'
                            ),
                            $navigationUser->isViewer() => __(
                                'common.navigation.for_information'
                            ),
                            default => __(
                                'common.navigation.my_processing_work'
                            ),
                        };
                    @endphp

                    <a
                        href="{{ route('assignments.index', [
                            'queue' => $navigationQueue,
                        ]) }}"
                        class="{{ request()->routeIs('assignments.*')
                            ? 'navigation-link-active'
                            : '' }}"
                        @if (request()->routeIs('assignments.*'))
                            aria-current="page"
                        @endif
                    >
                        {{ $assignmentNavigationLabel }}
                    </a>


                    {{--
                        protocols.create points to the form for
                        creating a new protocol:

                        GET /protocols/create

                        @can asks ProtocolPolicy::create() whether the
                        authenticated user may create protocols.

                        The current role rules mean:

                        - Administrators and Assigners see this link.
                        - Protocol Officers see this navigation link.
                        - Viewers do not see this navigation link.

                        ProtocolController::create() and store() repeat
                        the same authorization on the server, so hiding
                        this link is not the only security protection.
                    --}}
                    @can('create', \App\Models\Protocol::class)

                        <a
                            href="{{ route('protocols.create') }}"
                            class="{{ request()->routeIs('protocols.create')
                                ? 'navigation-link-active'
                                : '' }}"
                            @if (request()->routeIs('protocols.create'))
                                aria-current="page"
                            @endif
                        >
                            {{ __('common.navigation.create_protocol') }}
                        </a>

                    @endcan


                    {{--
                        Display the user management link only when the
                        authenticated user has the Administrator role.

                        isAdministrator() compares the user's role with
                        UserRole::Administrator.

                        The current role rules mean:

                        - Administrators see this navigation link.
                        - Protocol Officers do not see this link.
                        - Viewers do not see this link.

                        Hiding the link controls only the interface.
                        Admin\UserController and UpdateUserRoleRequest
                        repeat the authorization checks on the server,
                        so a non-administrator cannot gain access by
                        manually entering the URL or submitting a request.

                        admin.users.index points to the user management page:

                        GET /admin/users
                    --}}
                    @if (auth()->user()->isAdministrator())

                        <a
                            href="{{ route('admin.users.index') }}"
                            class="{{ request()->routeIs('admin.users.*')
                                ? 'navigation-link-active'
                                : '' }}"
                            @if (request()->routeIs('admin.users.*'))
                                aria-current="page"
                            @endif
                        >
                            {{ __('common.navigation.user_management') }}
                        </a>

                        <a
                            href="{{ route('admin.settings.index') }}"
                            class="{{ request()->routeIs('admin.settings.*')
                                ? 'navigation-link-active'
                                : '' }}"
                            @if (request()->routeIs('admin.settings.*'))
                                aria-current="page"
                            @endif
                        >
                            {{ __('settings.navigation') }}
                        </a>

                    @endif


                    {{--
                        Every authenticated role may consult the archive-folder
                        and retention catalogue, as in the original project.
                        ArchiveFolderPolicy shows modification controls only to
                        Administrators and protects the write endpoints even if
                        another role manually constructs a request.
                    --}}
                    <a
                        href="{{ route('admin.archive-folders.index') }}"
                        class="{{ request()->routeIs('admin.archive-folders.*')
                            ? 'navigation-link-active'
                            : '' }}"
                        @if (request()->routeIs('admin.archive-folders.*'))
                            aria-current="page"
                        @endif
                    >
                        {{ __('archive_folders.navigation') }}
                    </a>

                    <a
                        href="{{ route('protocols.search') }}"
                        class="{{ request()->routeIs('protocols.search')
                            ? 'navigation-link-active'
                            : '' }}"
                        @if (request()->routeIs('protocols.search'))
                            aria-current="page"
                        @endif
                    >
                        {{ __('search.navigation') }}
                    </a>


                    {{--
                        auth()->user() returns the User model belonging
                        to the current authenticated session.

                        Blade's {{ }} syntax escapes the name before it
                        is inserted into the HTML page.
                    --}}
                    <span class="authenticated-user">
                        {{ __('common.navigation.logged_in_as', [
                            'name' => auth()->user()->name,
                        ]) }}
                    </span>


                    {{--
                        Logout uses POST because it changes authentication
                        state. @csrf adds the security token Laravel checks
                        before accepting the request.
                    --}}
                    <form
                        class="logout-form"
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf

                        <button class="logout-button" type="submit">
                            {{ __('common.navigation.logout') }}
                        </button>
                    </form>
                @endauth


                {{--
                    @guest displays these links only when there is no
                    authenticated user in the current session.

                    Login is always available to guests. Register is shown
                    only while no Administrator exists, matching the
                    EnsureNoAdministratorExists middleware that protects the
                    registration routes on the server.
                --}}
                @guest
                    <a href="{{ route('login') }}">
                        {{ __('common.navigation.login') }}
                    </a>

                    @if (! \App\Models\User::query()
                        ->where(
                            'role',
                            \App\Enums\UserRole::Administrator->value
                        )
                        ->exists())

                        <a href="{{ route('register') }}">
                            {{ __('common.navigation.register') }}
                        </a>

                    @endif
                @endguest
            </nav>

        </div>

    </header>


    <main>

        {{--
            Display the temporary success message attached by a
            controller when redirecting after a successful operation.

            1. The controller completes an operation.
            2. The controller redirects and attaches a temporary
               success message.
            3. This layout checks whether that message exists.
            4. If it exists, the layout displays it.
        --}}
        @if (session('success'))

            <div class="success-message">
                {{ session('success') }}
            </div>

        @endif


        {{--
            Display an assignment lifecycle error returned by the completion
            endpoint. Field-specific assignment-form errors remain beside the
            controls that need correction.
        --}}
        @if ($errors->has('assignment'))

            <div class="error-summary" role="alert">
                {{ $errors->first('assignment') }}
            </div>

        @endif


        {{-- Insert the page-specific HTML here. --}}
        @yield('content')

    </main>

</body>
</html>   
