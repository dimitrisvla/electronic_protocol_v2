{{-- Use the application's main HTML layout. --}}
@extends('layouts.app')

{{-- Provide the localized browser-tab title. --}}
@section('title', __('auth.login.title'))

{{-- Insert this content into the layout's @yield('content') location. --}}
@section('content')
    <h1>{{ __('auth.login.heading') }}</h1>

    {{--
        Submit the form to POST /login.

        route('login') returns the /login URL from the named GET route.
        Laravel selects the POST route when this form is submitted.
    --}}
    <form method="POST" action="{{ route('login') }}">
        {{-- Protect the POST request against CSRF attacks. --}}
        @csrf

        <div>
            <label for="email">{{ __('auth.login.email') }}</label>

            {{--
                old('email') restores the email after a validation
                or authentication failure.
            --}}
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
            >

            {{--
                This also displays the general authentication error
                returned when the credentials do not match.
            --}}
            @error('email')
                <div role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="password">{{ __('auth.login.password') }}</label>

            {{-- Never restore a submitted password with old(). --}}
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
            >

            @error('password')
                <div role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div>
            {{--
                An unchecked checkbox is not included in the request.
                A checked checkbox submits remember=1.
            --}}
            <input
                type="checkbox"
                id="remember"
                name="remember"
                value="1"
                @checked(old('remember'))
            >

            <label for="remember">{{ __('auth.login.remember') }}</label>
        </div>

        <button type="submit">{{ __('auth.login.submit') }}</button>
    </form>

@endsection