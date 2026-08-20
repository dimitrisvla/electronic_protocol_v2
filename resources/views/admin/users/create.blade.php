@extends('layouts.app')

@section('title', __('users.create.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('users.create.title') }}</h1>
            <p>{{ __('users.create.description') }}</p>
        </div>

        <a
            href="{{ route('admin.users.index') }}"
            class="button button-secondary"
        >
            {{ __('users.actions.back_to_users') }}
        </a>
    </div>

    {{--
        The form is available only to Administrators. StoreUserRequest repeats
        that authorization on the server and validates every submitted field.
    --}}
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf

        <div class="form-group">
            <label for="name">{{ __('users.fields.name') }}</label>

            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
                maxlength="255"
                required
                autofocus
                autocomplete="name"
                @class(['invalid-field' => $errors->has('name')])
            >

            @error('name')
                <span class="error-message" role="alert">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="email">{{ __('users.fields.email') }}</label>

            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                maxlength="255"
                required
                autocomplete="email"
                @class(['invalid-field' => $errors->has('email')])
            >

            @error('email')
                <span class="error-message" role="alert">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">{{ __('users.fields.password') }}</label>

            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="new-password"
                @class(['invalid-field' => $errors->has('password')])
            >

            @error('password')
                <span class="error-message" role="alert">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">
                {{ __('users.fields.password_confirmation') }}
            </label>

            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
            >
        </div>

        <div class="form-group">
            <label for="role">{{ __('users.fields.role') }}</label>

            <select
                id="role"
                name="role"
                required
                @class(['invalid-field' => $errors->has('role')])
            >
                @foreach ($roles as $role)
                    <option
                        value="{{ $role->value }}"
                        @selected(old('role', \App\Enums\UserRole::Viewer->value) === $role->value)
                    >
                        {{ $role->label() }}
                    </option>
                @endforeach
            </select>

            @error('role')
                <span class="error-message" role="alert">{{ $message }}</span>
            @enderror
        </div>

        <div class="actions">
            <button type="submit" class="button button-primary">
                {{ __('users.actions.create') }}
            </button>

            <a
                href="{{ route('admin.users.index') }}"
                class="button button-secondary"
            >
                {{ __('common.actions.cancel') }}
            </a>
        </div>
    </form>
@endsection
