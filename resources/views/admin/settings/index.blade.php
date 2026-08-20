@extends('layouts.app')

@section('title', __('settings.index.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('settings.index.title') }}</h1>
            <p>{{ __('settings.index.description') }}</p>
        </div>

        <div class="page-actions">
            <a
                href="{{ route('protocols.index') }}"
                class="button button-secondary"
            >
                {{ __('common.actions.back') }}
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="error-summary" role="alert">
            <strong>{{ __('settings.form.validation_summary') }}</strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="content-section">
        <div class="section-header">
            <div>
                <h2>{{ __('settings.sections.protocol_operation') }}</h2>
                <p class="muted-text">
                    {{ __('settings.sections.protocol_operation_help') }}
                </p>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('admin.settings.update') }}"
        >
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="organization_name">
                    {{ __('settings.fields.organization_name') }}
                </label>

                <input
                    id="organization_name"
                    name="organization_name"
                    type="text"
                    maxlength="255"
                    value="{{ old('organization_name', $organizationName) }}"
                    class="@error('organization_name') invalid-field @enderror"
                    required
                    autofocus
                >

                @error('organization_name')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="active_protocol_year">
                        {{ __('settings.fields.active_protocol_year') }}
                    </label>

                    <input
                        id="active_protocol_year"
                        name="active_protocol_year"
                        type="number"
                        min="1000"
                        max="9999"
                        value="{{ old(
                            'active_protocol_year',
                            $activeProtocolYear
                        ) }}"
                        class="@error('active_protocol_year') invalid-field @enderror"
                    >

                    <p class="field-help">
                        {{ __('settings.help.active_protocol_year') }}
                    </p>

                    @error('active_protocol_year')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="starting_protocol_number">
                        {{ __('settings.fields.starting_protocol_number') }}
                    </label>

                    <input
                        id="starting_protocol_number"
                        name="starting_protocol_number"
                        type="number"
                        min="1"
                        max="4294967295"
                        value="{{ old(
                            'starting_protocol_number',
                            $startingProtocolNumber
                        ) }}"
                        class="@error('starting_protocol_number') invalid-field @enderror"
                        required
                    >

                    <p class="field-help">
                        {{ __('settings.help.starting_protocol_number') }}
                    </p>

                    @error('starting_protocol_number')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="automatic_protocol_numbering">
                    {{ __('settings.fields.automatic_protocol_numbering') }}
                </label>

                <select
                    id="automatic_protocol_numbering"
                    name="automatic_protocol_numbering"
                    class="@error('automatic_protocol_numbering') invalid-field @enderror"
                    required
                >
                    <option
                        value="1"
                        @selected((string) old(
                            'automatic_protocol_numbering',
                            $automaticProtocolNumbering ? '1' : '0'
                        ) === '1')
                    >
                        {{ __('common.yes') }}
                    </option>
                    <option
                        value="0"
                        @selected((string) old(
                            'automatic_protocol_numbering',
                            $automaticProtocolNumbering ? '1' : '0'
                        ) === '0')
                    >
                        {{ __('common.no') }}
                    </option>
                </select>

                <p class="field-help">
                    {{ __('settings.help.automatic_protocol_numbering') }}
                </p>

                @error('automatic_protocol_numbering')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="actions">
                <button type="submit" class="button button-primary">
                    {{ __('settings.actions.save') }}
                </button>
            </div>
        </form>
    </section>
@endsection
