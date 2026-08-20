{{-- Code: resposible for displaying the form that is used
          to create a new protocol
    
    resources/views/layouts/app.blade.php
--}}


{{-- Inform blade that this page uses the shared layout.
     It means that create.blade doesn't need it's own HTML document. 
     Already provided my resources/views/layout/app/blade.php
--}}
@extends('layouts.app')



{{-- Provides the value for @yield('title') which is inside
     app.blade.php   
--}}
@section('title', __('protocols.create.title'))


{{-- Everything inside the section will be inserted into
     @yield('content') inside app.blade.php  --}}
@section('content')


    {{-- Page header --}
    <div class="page-header">
    
        <h1>{{ __('protocols.create.title') }}</h1>

        {{-- Actions related to the page --}}
        <div class="page-actions">


            {{-- Return to the protocols list.

                route('protocols.index'): refers to the Laravel route.
        
            --}}   
            <a href="{{ route('protocols.index') }}"
               class="button button-secondary"
            >
                {{ __('common.actions.back') }}
            </a>

        </div>

    </div>



    {{--  Display validation error

        When StoreProtocolRequest rejects the user's submitted data, then
        Laravel redirects back to this page and stores the validation
        errors in the $errors variable.

    --}}
    @if ($errors->any())

        <div class="validation-errors">

            <strong>
                {{ __('protocols.form.validation_summary') }}
            </strong>

            <ul>

                {{-- Loop through all validation error messages --}}
                @foreach ($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif



    {{--
        Form for creating a new Protocol.

        route('protocols.store') generates the URL for the
        protocols.store route.

        Because this form creates a new DB record,
        it uses the POST HTTP method.

        The submitted request will eventually be handled by:

            ProtocolController::store()
    --}}
    <form
        action="{{ route('protocols.store') }}"
        method="POST"
        enctype="multipart/form-data"
    >

        @csrf



        {{-- Protocol number --}}
        <div class="form-group">

            <label for="protocol_number">
                {{ __('protocols.fields.protocol_number') }}
            </label>

            {{--
                Automatic mode displays the current proposal as read-only.
                The server recalculates it during storage, so this preview may
                advance if another user creates a protocol first.

                Manual mode keeps the proposal editable and restores old input
                after a validation failure.
            --}}
            <input
                type="number"
                id="protocol_number"
                name="protocol_number"
                value="{{ $automaticProtocolNumbering
                    ? $suggestedProtocolNumber
                    : old('protocol_number', $suggestedProtocolNumber) }}"
                min="1"
                max="4294967295"
                class="@error('protocol_number') invalid-field @enderror"
                @readonly($automaticProtocolNumbering)
                required
            >

            <small>
                {{ $automaticProtocolNumbering
                    ? __('protocols.numbering.automatic_help')
                    : __('protocols.numbering.manual_help') }}
            </small>

            @error('protocol_number')
                <span class="error-message">{{ $message }}</span>
            @enderror

        </div>



        {{-- Protocol year --}}
        <div class="form-group">

            <label for="protocol_year">
                {{ __('protocols.fields.protocol_year') }}
            </label>

            <input
                type="number"
                id="protocol_year"
                name="protocol_year"
                value="{{ old('protocol_year', $protocolYear) }}"
                min="1000"
                max="9999"
                class="@error('protocol_year') invalid-field @enderror"
                required
            >

            <small>
                {{ __('protocols.numbering.year_help') }}
            </small>

            @error('protocol_year')
                <span class="error-message">{{ $message }}</span>
            @enderror

        </div>



        {{-- Protocol date --}}
        <div class="form-group">

            <label for="protocol_date">
                {{ __('protocols.fields.protocol_date') }}
            </label>

            <input
                type="date"
                id="protocol_date"
                name="protocol_date"
                value="{{ old('protocol_date') }}"
                required
            >

        </div>



        {{-- Protocol direction --}}
        <div class="form-group">

            <label for="direction">
                {{ __('protocols.directions.label') }}
            </label>

            {{--
                The values must correspond to the values accepted
                by StoreProtocolRequest and remain in English.
            --}}
            <select
                id="direction"
                name="direction"
                required
            >

                <option value="">
                    {{ __('protocols.directions.select') }}
                </option>

                <option
                    value="incoming"
                    @selected(old('direction') === 'incoming')
                >
                    {{ __('protocols.directions.incoming') }}
                </option>

                <option
                    value="outgoing"
                    @selected(old('direction') === 'outgoing')
                >
                    {{ __('protocols.directions.outgoing') }}
                </option>

            </select>

        </div>



        {{-- Subject --}}
        <div class="form-group">

            <label for="subject">
                {{ __('protocols.fields.subject') }}
            </label>

            <input
                type="text"
                id="subject"
                name="subject"
                value="{{ old('subject') }}"
                maxlength="255"
                required
            >

        </div>



        {{-- Sender --}}
        <div class="form-group">

            <label for="sender">
                {{ __('protocols.fields.sender') }}
            </label>

            {{--
                Sender is optional because the database column
                and our validation allow it to be NULL.
            --}}
            <input
                type="text"
                id="sender"
                name="sender"
                value="{{ old('sender') }}"
            >

        </div>



        {{-- Recipient --}}
        <div class="form-group">

            <label for="recipient">
                {{ __('protocols.fields.recipient') }}
            </label>

            {{--
                Recipient is optional as well.
            --}}
            <input
                type="text"
                id="recipient"
                name="recipient"
                value="{{ old('recipient') }}"
            >

        </div>




        {{-- Notes --}}
        <div class="form-group">

            <label for="notes">
                {{ __('protocols.fields.notes') }}
            </label>

            {{--
                A textarea usage because notes
                can contain longer text.

                Notice that textarea does not use the
                value="" attribute.

                Its value goes between the opening and
                closing textarea tags.
            --}}
            <textarea
                id="notes"
                name="notes"
                rows="5"
            >{{ old('notes') }}</textarea>

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
                            (string) old('archive_folder_id')
                            === (string) $archiveFolder->id
                        )
                    >
                        {{ $archiveFolder->code }} — {{ $archiveFolder->description }}
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



        {{-- Optional normalized links to existing protocols. --}}
        @include('protocols.partials.related-protocol-fields')



        {{-- Protocol PDF attachments --}}
        <div class="form-group">

            <label for="attachments">
                {{ __('protocols.create.attachments_label') }}
            </label>

            {{--
                The brackets in attachments[] tell the browser to
                submit the selected files as an array.

                multiple allows the user to select several files.

                accept limits the file picker to PDF files. This is
                only a browser hint; StoreProtocolRequest performs
                the authoritative server-side validation.

                File inputs cannot be restored with old() after a
                validation failure. The user must select them again.
            --}}
            <input
                type="file"
                id="attachments"
                name="attachments[]"
                accept=".pdf,application/pdf"
                multiple
            >

            <small>
                {{ __('protocols.create.attachments_help') }}
            </small>

            {{-- Display an error concerning the attachments array. --}}
            @error('attachments')
                <span class="error-message">{{ $message }}</span>
            @enderror

            {{-- Display an error concerning an individual PDF. --}}
            @error('attachments.*')
                <span class="error-message">{{ $message }}</span>
            @enderror

        </div>



        {{-- Form actions --}}
        <div class="form-actions">

            {{--
                Submit the form.

                This sends:  POST /protocols

                which Laravel maps to: ProtocolController::store()
            --}}
            <button
                type="submit"
                class="button button-primary"
            >
                {{ __('protocols.create.submit') }}
            </button>

            {{-- Cancel and return to the protocols list --}}
            <a
                href="{{ route('protocols.index') }}"
                class="button button-secondary"
            >
                {{ __('common.actions.cancel') }}
            </a>

        </div>


    </form>

{{-- End of the content section --}}
@endsection
