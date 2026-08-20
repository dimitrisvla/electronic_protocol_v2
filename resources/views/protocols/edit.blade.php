{{--
    This view displays the form used to edit an existing protocol.

    The $protocol variable is provided by the edit() method
    inside ProtocolController.

    resources/views/protocols/edit.blade.php
--}}


{{--
    Inform Blade that this page uses the shared application layout.

    Laravel will look for:
    resources/views/layouts/app.blade.php
--}}
@extends('layouts.app')


{{--
    Provide the value for @yield('title')
    inside app.blade.php.
--}}
@section('title', __('protocols.edit.title'))


{{--
    Everything inside this section will be inserted into the
    @yield('content') position of app.blade.php.
--}}
@section('content')

    {{--
        These styles are used only by the Edit Protocol form.

        The shared layout is still responsible for the general page,
        button and application styles.
    --}}
    <style>
        .protocol-form {
            max-width: 800px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #333333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        /*
         * Blade adds this class when a field has
         * a validation error.
         */
        .form-group .invalid-field {
            border-color: #dc3545;
        }

        .error-message {
            display: block;
            margin-top: 5px;
            color: #dc3545;
            font-size: 14px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
    </style>


    {{--
        The page header contains the title of the page.
    --}}
    <div class="page-header">

        <h1>{{ __('protocols.edit.title') }}</h1>

    </div>


    {{--
        The form sends the submitted data to the update route.

        $protocol is the Protocol object provided by
        ProtocolController's edit() method.

        Laravel uses the protocol to generate a URL such as:

        /protocols/5
    --}}
    <form
        action="{{ route('protocols.update', $protocol) }}"
        method="POST"
        enctype="multipart/form-data"
        class="protocol-form"
    >
        {{--
            Laravel requires a CSRF token for forms that modify data.

            This protects the application against cross-site
            request forgery attacks.
        --}}
        @csrf

        {{--
            HTML forms directly support only GET and POST.

            @method('PUT') adds a hidden _method field that tells
            Laravel to treat this POST request as a PUT request.

            Resource routes use PUT or PATCH when updating a record.
        --}}
        @method('PUT')


        {{-- Protocol number --}}
        <div class="form-group">
            <label for="protocol_number">
                {{ __('protocols.fields.protocol_number') }}
            </label>

            {{--
                old() first checks whether a previously submitted value
                exists, which happens when validation fails.

                If no old value exists, Laravel displays the protocol
                number currently stored in the database.
            --}}
            <input
                type="number"
                id="protocol_number"
                name="protocol_number"
                min="1"
                value="{{ old('protocol_number', $protocol->protocol_number) }}"
                class="@error('protocol_number') invalid-field @enderror"
                required
            >

            {{--
                If validation fails, Laravel places the validation
                message for protocol_number inside $message.
            --}}
            @error('protocol_number')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Protocol year --}}
        <div class="form-group">
            <label for="protocol_year">{{ __('protocols.fields.protocol_year') }}</label>

            <input
                type="number"
                id="protocol_year"
                name="protocol_year"
                min="1000"
                max="9999"
                value="{{ old('protocol_year', $protocol->protocol_year) }}"
                class="@error('protocol_year') invalid-field @enderror"
                required
            >

            @error('protocol_year')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Protocol date --}}
        <div class="form-group">
            <label for="protocol_date">{{ __('protocols.fields.protocol_date') }}</label>

            {{--
                The Protocol model casts protocol_date to a date object.

                format('Y-m-d') converts it to the value format required
                by an HTML date input, for example: 2026-08-14.
            --}}
            <input
                type="date"
                id="protocol_date"
                name="protocol_date"
                value="{{ old('protocol_date', $protocol->protocol_date->format('Y-m-d')) }}"
                class="@error('protocol_date') invalid-field @enderror"
                required
            >

            @error('protocol_date')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Protocol direction --}}
        <div class="form-group">
            <label for="direction">
                {{ __('protocols.directions.label') }}
            </label>

            <select
                id="direction"
                name="direction"
                class="@error('direction') invalid-field @enderror"
                required
            >
                {{--
                    old() uses the previously submitted value when
                    validation fails.

                    If there is no previous submission, it uses the
                    direction currently stored in the database.

                    @selected adds the selected HTML attribute when
                    the provided condition is true.
                --}}
                <option
                    value="incoming"
                    @selected(old('direction', $protocol->direction) === 'incoming')
                >
                    {{ __('protocols.directions.incoming') }}
                </option>

                <option
                    value="outgoing"
                    @selected(old('direction', $protocol->direction) === 'outgoing')
                >
                    {{ __('protocols.directions.outgoing') }}
                </option>
            </select>

            @error('direction')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Protocol subject --}}
        <div class="form-group">
            <label for="subject">{{ __('protocols.fields.subject') }}</label>

            <input
                type="text"
                id="subject"
                name="subject"
                maxlength="255"
                value="{{ old('subject', $protocol->subject) }}"
                class="@error('subject') invalid-field @enderror"
                required
            >

            @error('subject')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Sender is optional --}}
        <div class="form-group">
            <label for="sender">{{ __('protocols.fields.sender') }}</label>

            <input
                type="text"
                id="sender"
                name="sender"
                maxlength="255"
                value="{{ old('sender', $protocol->sender) }}"
                class="@error('sender') invalid-field @enderror"
            >

            @error('sender')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Recipient is optional --}}
        <div class="form-group">
            <label for="recipient">{{ __('protocols.fields.recipient') }}</label>

            <input
                type="text"
                id="recipient"
                name="recipient"
                maxlength="255"
                value="{{ old('recipient', $protocol->recipient) }}"
                class="@error('recipient') invalid-field @enderror"
            >

            @error('recipient')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Notes are optional --}}
        <div class="form-group">
            <label for="notes">{{ __('protocols.fields.notes') }}</label>

            {{--
                A textarea does not use a value attribute.

                Its value must be placed between the opening and
                closing textarea tags.
            --}}
            <textarea
                id="notes"
                name="notes"
                class="@error('notes') invalid-field @enderror"
            >{{ old('notes', $protocol->notes) }}</textarea>

            @error('notes')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Optional archive classification from the official catalogue. --}}
        <div class="form-group">
            <label for="archive_folder_id">
                {{ __('protocols.fields.archive_folder') }}
            </label>

            <select
                id="archive_folder_id"
                name="archive_folder_id"
                class="@error('archive_folder_id') invalid-field @enderror"
            >
                <option value="">
                    {{ __('protocols.archive.no_folder') }}
                </option>

                @foreach ($archiveFolders as $archiveFolder)
                    <option
                        value="{{ $archiveFolder->id }}"
                        @selected(
                            (string) old(
                                'archive_folder_id',
                                $protocol->archive_folder_id
                            ) === (string) $archiveFolder->id
                        )
                    >
                        {{ $archiveFolder->code }} — {{ $archiveFolder->description }}

                        @if (! $archiveFolder->is_active || ! $archiveFolder->is_selectable)
                            ({{ __('protocols.archive.current_unavailable') }})
                        @endif
                    </option>
                @endforeach
            </select>

            <small>
                {{ __('protocols.archive.selection_help') }}
            </small>

            @error('archive_folder_id')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Add, retain or remove links to active protocols. --}}
        @include('protocols.partials.related-protocol-fields', [
            'relatedProtocols' => $relatedProtocols,
        ])


        {{-- Add new PDF attachments --}}
        <div class="form-group">
            <label for="attachments">
                {{ __('protocols.edit.attachments_label') }}
            </label>

            {{--
                attachments[] submits the selected files as an array.

                multiple allows several PDFs to be selected in one
                submission. Existing attachments are not removed or
                replaced; these files are added to the protocol.

                accept improves the browser's file picker, while
                UpdateProtocolRequest performs the real server-side
                file type, count, and size validation.

                Browsers do not allow file fields to be repopulated
                after validation fails, so files must be reselected.
            --}}
            <input
                type="file"
                id="attachments"
                name="attachments[]"
                accept=".pdf,application/pdf"
                class="@error('attachments') invalid-field @enderror
                       @error('attachments.*') invalid-field @enderror"
                multiple
            >

            <small>
                {{ __('protocols.edit.attachments_help') }}
            </small>

            @error('attachments')
                <span class="error-message">{{ $message }}</span>
            @enderror

            @error('attachments.*')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>


        {{-- Contains the actions related to this form. --}}
        <div class="form-actions">

            {{--
                Submit the form and execute the update() method
                inside ProtocolController.
            --}}
            <button
                type="submit"
                class="button button-primary"
            >
                {{ __('protocols.edit.submit') }}
            </button>

            {{--
                Return to the protocol's details page without
                submitting the form or changing the protocol.
            --}}
            <a
                href="{{ route('protocols.show', $protocol) }}"
                class="button button-secondary"
            >
                {{ __('common.actions.cancel') }}
            </a>

        </div>

    </form>

@endsection
