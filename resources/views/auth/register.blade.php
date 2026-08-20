{{-- Use the application's main HTML layout. --}}
@extends('layouts.app')

{{-- Provide the localized browser-tab title. --}}
@section('title', __('auth.register.title'))

{{-- Insert this content into the layout's @yield('content') location. --}}
@section('content')
    <h1>{{ __('auth.register.heading') }}</h1>

    <p>{{ __('auth.register.administrator_notice') }}</p>

    {{--
        Submit the form to POST /register.

        route('register') generates the /register URL from the
        named GET route. The browser submits to the same URL using POST,
        so Laravel selects our POST /register route.
    --}}
    <form method="POST" action="{{ route('register') }}">
        {{--
            Add Laravel's CSRF token to the form.

            Laravel rejects the request if this token is missing
            or does not match the user's session.
        --}}
        @csrf

        <div>
            <label for="name">{{ __('auth.register.name') }}</label>

            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
            >

            {{--
                Display the validation error associated with "name".
                $message is automatically provided inside @error.
            --}}
            @error('name')
                <div role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="email">{{ __('auth.register.email') }}</label>

            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="username"
            >

            @error('email')
                <div role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="password">{{ __('auth.register.password') }}</label>

            {{--
                Password values are deliberately not restored with old().
                A password should not be placed back into the HTML response.
            --}}
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="new-password"
            >

            @error('password')
                <div role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="password_confirmation">
                {{ __('auth.register.password_confirmation') }}
            </label>

            {{--
                The "confirmed" validation rule expects this exact name:
                password_confirmation
            --}}
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
            >
        </div>

        <button type="submit">{{ __('auth.register.submit') }}</button>
    </form>

    <p>
        {{ __('auth.register.already_registered') }}
        <a href="{{ route('login') }}">
            {{ __('auth.register.login_link') }}
        </a>
    </p>
@endsection
