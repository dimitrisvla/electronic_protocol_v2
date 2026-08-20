{{--
    This Blade view is responsible for displaying the list
    of all active protocols.

    The protocols are retrieved from the database by:

    ProtocolController::index()

    File:
    resources/views/protocols/index.blade.php
--}}


{{--
    Inform Blade that this page uses the shared application layout.

    Therefore, index.blade.php does not need its own HTML document
    containing <!DOCTYPE html>, <html>, <head>, or <body>.
--}}
@extends('layouts.app')


{{--
    Provide the value for @yield('title') inside app.blade.php.

    This value is normally displayed in the browser tab.
--}}
@section('title', __('protocols.index.title'))


{{--
    Everything inside this section will be inserted into
    @yield('content') inside app.blade.php.
--}}
@section('content')


    {{--
        Page header.

        It contains the title of the current page and the actions
        that are available from the main protocol listing.
    --}}
    <div class="page-header">

        <h1>{{ __('protocols.index.title') }}</h1>


        {{--
            Contains buttons related to the protocol-listing page.
        --}}
        <div class="page-actions">

            {{--
                Open the page containing all soft-deleted protocols.

                route('protocols.deleted') refers to the named route:

                protocols.deleted

                Laravel generates the following URL:

                /protocols/deleted

                @can asks ProtocolPolicy::viewDeleted() whether the
                authenticated user may access the recycle bin.

                Administrators and Protocol Officers see this link.
                Viewers do not see it.
            --}}
            @can('viewDeleted', \App\Models\Protocol::class)

                <a
                    href="{{ route('protocols.deleted') }}"
                    class="button button-secondary"
                >
                    {{ __('protocols.actions.deleted_protocols') }}
                </a>

            @endcan


            {{--
                Open the form for creating a new protocol.

                route('protocols.create') refers to the named route:

                protocols.create

                Laravel generates the following URL:

                /protocols/create

                @can asks ProtocolPolicy::create() whether the
                authenticated user may create protocols.

                Administrators and Protocol Officers see this link.
                Viewers do not see it.
            --}}
            @can('create', \App\Models\Protocol::class)

                <a
                    href="{{ route('protocols.create') }}"
                    class="button button-primary"
                >
                    {{ __('protocols.actions.create') }}
                </a>

            @endcan

        </div>

    </div>


    {{--
        Search and filter form.

        The form uses the GET method because searching and filtering
        only retrieve records; they do not change database data.

        When this form is submitted, its values are added to the URL
        as query-string parameters.

        Example:

        /protocols?search=invoice&protocol_year=2026&direction=incoming

        ProtocolController::index() reads these values and applies
        the corresponding conditions to the Protocol query.
    --}}
    <form
        method="GET"
        action="{{ route('protocols.index') }}"
        class="filter-form"
    >

        {{--
            Group the search field and filter dropdowns together.

            The class can be styled in layouts/app.blade.php so that
            the controls appear in columns on wider screens and stack
            vertically on smaller screens.
        --}}
        <div class="form-grid filter-grid">

            {{--
                Search field.

                The controller uses this value to search for:

                - An exact protocol number.
                - A subject containing the entered text.

                request('search') reads the current search value from
                the URL and places it back in the field after the form
                is submitted.
            --}}
            <div class="form-group">

                <label for="search">
                    {{ __('protocols.filters.number_or_subject') }}
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="{{ __('protocols.filters.search_placeholder') }}"
                >

            </div>


            {{--
                Protocol-year filter.

                $protocolYears is provided by
                ProtocolController::index().

                It contains each distinct year found among active
                protocols, ordered from the newest year to the oldest.
            --}}
            <div class="form-group">

                <label for="protocol_year">
                    {{ __('protocols.filters.year') }}
                </label>

                <select
                    id="protocol_year"
                    name="protocol_year"
                >

                    {{--
                        An empty value means that protocols from every
                        available year should be displayed.
                    --}}
                    <option value="">
                        {{ __('protocols.filters.all_years') }}
                    </option>


                    {{--
                        Create one option for every distinct protocol
                        year returned by the controller.
                    --}}
                    @foreach ($protocolYears as $protocolYear)

                        <option
                            value="{{ $protocolYear }}"
                            @selected(
                                (string) request('protocol_year')
                                === (string) $protocolYear
                            )
                        >
                            {{ $protocolYear }}
                        </option>

                    @endforeach

                </select>

            </div>


            {{--
                Protocol-direction filter.

                The option values remain identical to those stored in
                the direction column of the protocols database table.
            --}}
            <div class="form-group">

                <label for="direction">
                    {{ __('protocols.directions.label') }}
                </label>

                <select
                    id="direction"
                    name="direction"
                >

                    {{--
                        An empty value means that both incoming and
                        outgoing protocols should be displayed.
                    --}}
                    <option value="">
                        {{ __('protocols.directions.all') }}
                    </option>

                    <option
                        value="incoming"
                        @selected(request('direction') === 'incoming')
                    >
                        {{ __('protocols.directions.incoming') }}
                    </option>

                    <option
                        value="outgoing"
                        @selected(request('direction') === 'outgoing')
                    >
                        {{ __('protocols.directions.outgoing') }}
                    </option>

                </select>

            </div>

        </div>


        {{--
            Form actions.

            The Filter button submits the selected search and filter
            values to protocols.index.

            The Reset link opens protocols.index without a query
            string, removing every active search and filter.
        --}}
        <div class="form-actions filter-actions">

            <button
                type="submit"
                class="button button-primary"
            >
                {{ __('protocols.filters.apply') }}
            </button>

            <a
                href="{{ route('protocols.index') }}"
                class="button button-secondary"
            >
                {{ __('protocols.filters.reset') }}
            </a>

        </div>

    </form>


    {{--
        Check whether the controller returned at least one protocol.

        The $protocols variable is provided to this Blade view by:

        ProtocolController::index()
    --}}
    @if ($protocols->count() > 0)


        {{--
            Wrapper around the table.

            This is useful when the table becomes wider than
            the available screen space.
        --}}
        <div class="table-container">

            <table>

                {{--
                    Table header.

                    Each <th> element describes the information
                    displayed in its corresponding table column.
                --}}
                <thead>
                    <tr>
                        <th>{{ __('protocols.columns.id') }}</th>

                        <th>{{ __('protocols.columns.protocol_number') }}</th>

                        <th>{{ __('protocols.columns.date') }}</th>

                        <th>{{ __('protocols.columns.direction') }}</th>

                        <th>{{ __('protocols.columns.subject') }}</th>

                        <th>{{ __('protocols.columns.sender') }}</th>

                        <th>{{ __('protocols.columns.recipient') }}</th>

                        <th>{{ __('protocols.columns.actions') }}</th>
                    </tr>
                </thead>


                {{--
                    Table body.

                    Blade will create one <tr> element for every
                    Protocol object contained in $protocols.
                --}}
                <tbody>

                    {{--
                        Loop through the active protocols retrieved
                        from the database.

                        During each iteration, $protocol represents
                        one Protocol model object.
                    --}}
                    @foreach ($protocols as $protocol)

                        <tr>

                            {{--
                                Display the database primary key.

                                This corresponds to the following
                                column in the protocols migration:

                                $table->id();
                            --}}
                            <td>
                                {{ $protocol->id }}
                            </td>


                            {{--
                                Combine the protocol number and
                                protocol year.

                                Example:

                                15/2026
                            --}}
                            <td>
                                {{ $protocol->protocol_number }}/{{ $protocol->protocol_year }}
                            </td>


                            {{--
                                Display the official protocol date.
                            --}}
                            <td>
                                {{ $protocol->protocol_date }}
                            </td>


                            {{--
                                Display whether the protocol is
                                incoming or outgoing.
                            --}}
                            <td>
                                {{ __('protocols.directions.' . $protocol->direction) }}
                            </td>


                            {{--
                                Display the protocol subject.
                            --}}
                            <td>
                                {{ $protocol->subject }}
                            </td>


                            {{--
                                Display the sender.

                                Because sender may be null, display
                                a dash when no sender was provided.
                            --}}
                            <td>
                                {{ $protocol->sender ?? '-' }}
                            </td>


                            {{--
                                Display the recipient.

                                Because recipient may be null, display
                                a dash when no recipient was provided.
                            --}}
                            <td>
                                {{ $protocol->recipient ?? '-' }}
                            </td>


                            {{--
                                Display the actions available for
                                this particular protocol.
                            --}}
                            <td>

                                <div class="table-actions">

                                    {{--
                                        Open the details page for
                                        the selected protocol.

                                        Passing $protocol allows Laravel
                                        to use the protocol's ID when
                                        generating the URL.

                                        For example, if the protocol ID
                                        is 5, Laravel generates:

                                        /protocols/5
                                    --}}
                                    <a
                                        href="{{ route('protocols.show', $protocol) }}"
                                        class="button button-secondary"
                                    >
                                        {{ __('protocols.actions.view') }}
                                    </a>


                                    {{--
                                        Display Edit only when
                                        ProtocolPolicy::update() allows it.

                                        This means:

                                        - An Administrator may edit any
                                          active protocol.
                                        - A Protocol Officer may edit only
                                          a protocol they created.
                                        - A Viewer cannot edit protocols.

                                        The policy check in the controller
                                        still protects the edit route if a
                                        user manually enters its URL.
                                    --}}
                                    @can('update', $protocol)

                                        <a
                                            href="{{ route('protocols.edit', $protocol) }}"
                                            class="button button-primary"
                                        >
                                            {{ __('protocols.actions.edit') }}
                                        </a>

                                    @endcan


                                    {{--
                                        Display Delete only when
                                        ProtocolPolicy::delete() allows it.

                                        The delete action uses a form because
                                        it changes database data. @csrf adds
                                        Laravel's CSRF token, and @method()
                                        makes the request use HTTP DELETE.

                                        Administrators may delete any active
                                        protocol. Protocol Officers may delete
                                        only their own protocols. Viewers do
                                        not see this action.
                                    --}}
                                    @can('delete', $protocol)

                                        <form
                                            method="POST"
                                            action="{{ route('protocols.destroy', $protocol) }}"
                                            onsubmit="return confirm('{{ __('protocols.confirmations.delete') }}');"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="button button-danger"
                                            >
                                                {{ __('protocols.actions.delete') }}
                                            </button>
                                        </form>

                                    @endcan

                                </div>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>


        {{--
            Display Laravel's pagination navigation.

            This works because ProtocolController::index()
            creates $protocols using paginate(10).

            Laravel automatically generates links for moving
            between the protocol-listing pages.
        --}}
        <div class="pagination-container">

            {{ $protocols->links() }}

        </div>


    @else

        {{--
            If at least one search or filter value is active, an empty
            result means that no active protocols match those values.

            Otherwise, the database does not contain any active
            protocols. Soft-deleted protocols do not count because
            the normal Protocol query automatically excludes them.
        --}}
        @if (
            request()->filled('search')
            || request()->filled('protocol_year')
            || request()->filled('direction')
        )

            <p>
                {{ __('protocols.index.no_results') }}
            </p>

        @else

            <p>
                {{ __('protocols.index.empty') }}
            </p>

        @endif

    @endif


{{-- End of the content section. --}}
@endsection
