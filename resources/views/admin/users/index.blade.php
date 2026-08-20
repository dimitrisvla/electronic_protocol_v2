@extends('layouts.app')

@section('title', __('users.index.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('users.index.title') }}</h1>
            <p>{{ __('users.index.description') }}</p>
        </div>

        <div class="page-actions">
            <a
                href="{{ route('admin.users.create') }}"
                class="button button-primary"
            >
                {{ __('users.actions.create') }}
            </a>

            <a
                href="{{ route('protocols.index') }}"
                class="button button-secondary"
            >
                {{ __('users.actions.back_to_protocols') }}
            </a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('users.fields.name') }}</th>
                <th>{{ __('users.fields.email') }}</th>
                <th>{{ __('users.fields.current_role') }}</th>
                <th>{{ __('users.columns.change_role') }}</th>
                <th>{{ __('users.columns.delete_user') }}</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>

                    <td>
                        {{ $user->role->label() }}
                    </td>

                    <td>
                        <form
                            method="POST"
                            action="{{ route('admin.users.role.update', $user) }}"
                        >
                            @csrf
                            @method('PATCH')

                            <select name="role">
                                @foreach (\App\Enums\UserRole::cases() as $role)
                                    <option
                                        value="{{ $role->value }}"
                                        @selected($user->role === $role)
                                    >
                                        {{ $role->label() }}
                                    </option>
                                @endforeach
                            </select>

                            <button type="submit" class="button">
                                {{ __('users.actions.update_role') }}
                            </button>
                        </form>
                    </td>

                    <td>
                        {{--
                            HTML forms support only GET and POST directly.
                            @method('DELETE') adds Laravel's hidden method field,
                            allowing this POST form to reach the DELETE route:

                            admin.users.destroy

                            The route and UserController::destroy() repeat the
                            authorization check on the server. Therefore, this
                            button is an interface control rather than the only
                            security protection.
                        --}}
                        <form
                            method="POST"
                            action="{{ route('admin.users.destroy', $user) }}"
                            onsubmit="return confirm(
                                '{{ __('users.confirmations.delete') }}'
                            );"
                        >
                            @csrf
                            @method('DELETE')

                            {{--
                                When the row belongs to the current
                                Administrator, make the consequence explicit.
                                The controller will log them out immediately
                                after deleting their account.
                            --}}
                            <button
                                type="submit"
                                class="button button-danger"
                            >
                                @if (auth()->id() === $user->id)
                                    {{ __('users.actions.delete_my_account') }}
                                @else
                                    {{ __('common.actions.delete') }}
                                @endif
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    {{-- The table now contains five columns. --}}
                    <td colspan="5">{{ __('users.index.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $users->links() }}
@endsection
