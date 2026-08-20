{{--
    This Blade view displays all soft-deleted protocols.

    The protocols are retrieved from the database by:

    ProtocolController::deleted()

    Soft-deleted protocols still exist in the protocols table,
    but their deleted_at column contains a date and time.

    Access to this page is controlled by:

    ProtocolPolicy::viewDeleted()

    Administrators can see every deleted protocol. Protocol Officers
    can see only deleted protocols they created. Viewers cannot open
    this page.

    File: resources/views/protocols/deleted.blade.php
--}}


{{--
    Inform Blade that this page uses the shared application layout.

    Therefore, this file does not need to contain its own:
    <!DOCTYPE html>, <html>, <head>, or <body> elements.
--}}
@extends('layouts.app')


{{--
    Provide the value for @yield('title') inside app.blade.php.

    The browser tab will display "Deleted Protocols".
--}}
@section('title', __('protocols.deleted.title'))


{{--
    Everything inside this section will be inserted into
    @yield('content') inside app.blade.php.
--}}
@section('content')


    {{--
        Page header.

        It contains the title of the current page and
        an action that returns to the active-protocol listing.
    --}}
    <div class="page-header">

        <h1>{{ __('protocols.deleted.title') }}</h1>


        {{--
            Contains actions related to the current page.
        --}}
        <div class="page-actions">

            {{--
                Return to the normal protocol listing.

                route('protocols.index') generates:

                /protocols
            --}}
            <a
                href="{{ route('protocols.index') }}"
                class="button button-secondary"
            >
                {{ __('protocols.actions.back_to_protocols') }}
            </a>

        </div>

    </div>


    {{--
        Check whether the deleted-protocol query returned
        at least one record.

        The $protocols variable is provided by:

        ProtocolController::deleted()
    --}}
    @if ($protocols->count() > 0)


        {{--
            This wrapper allows the table to scroll horizontally
            if its content is wider than the available screen.
        --}}
        <div class="table-container">

            <table>

                {{--
                    Table header.

                    Each <th> element describes the information
                    displayed in the corresponding table column.
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

                        <th>{{ __('protocols.columns.deleted_at') }}</th>

                        <th>{{ __('protocols.columns.actions') }}</th>
                    </tr>
                </thead>


                {{--
                    Table body.

                    Blade will create one table row for every
                    soft-deleted Protocol object.
                --}}
                <tbody>

                    {{--
                        Loop through the paginated collection of
                        deleted protocols.

                        During each iteration, $protocol represents
                        one soft-deleted Protocol model object.
                    --}}
                    @foreach ($protocols as $protocol)

                        <tr>

                            {{--
                                Display the protocol's database
                                primary key.
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
                                Display the protocol's subject.
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
                                Display the date and time when the
                                protocol was soft-deleted.

                                SoftDeletes automatically treats
                                deleted_at as a date object.

                                The ?-> operator prevents an error if
                                deleted_at is unexpectedly null.

                                The format produces a value similar to:

                                2026-08-15 17:30
                            --}}
                            <td>
                                {{ $protocol->deleted_at?->format('Y-m-d H:i') ?? '-' }}
                            </td>


                            <td>

                                <div class="table-actions">

                                    {{--
                                        Ask ProtocolPolicy whether the
                                        authenticated user may perform at
                                        least one recycle-bin action on this
                                        particular protocol.

                                        The current role rules mean:

                                        - An Administrator may restore or
                                          permanently delete any deleted
                                          protocol, including an ownerless
                                          legacy record.
                                        - A Protocol Officer may restore only
                                          a deleted protocol they created.
                                        - A Protocol Officer cannot permanently
                                          delete a protocol.
                                        - A Viewer cannot access this page.
                                    --}}
                                    @canany(['restore', 'forceDelete'], $protocol)

                                        {{--
                                            Ask ProtocolPolicy specifically
                                            whether the authenticated user may
                                            restore this protocol.

                                            Administrators pass this check for
                                            every deleted protocol. Protocol
                                            Officers pass it only for their own
                                            deleted protocols.

                                            ProtocolController::restore()
                                            repeats the policy check, so a
                                            manually submitted POST request is
                                            protected even when no button is
                                            rendered.
                                        --}}
                                        @can('restore', $protocol)

                                            {{--
                                                Submit a POST request to:

                                                /protocols/{protocol}/restore

                                                We use a form instead of a
                                                normal link because restoration
                                                changes data in the database.
                                            --}}
                                            <form
                                                method="POST"
                                                action="{{ route('protocols.restore', $protocol->id) }}"
                                            >
                                                {{--
                                                    Add Laravel's CSRF token.

                                                    Laravel checks this hidden
                                                    token to ensure that the
                                                    request came from a valid
                                                    application form.
                                                --}}
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="button button-primary"
                                                >
                                                    {{ __('protocols.actions.restore') }}
                                                </button>
                                            </form>

                                        @endcan


                                        {{--
                                            Ask ProtocolPolicy specifically
                                            whether the authenticated user may
                                            permanently delete this protocol.

                                            Only Administrators pass the
                                            forceDelete policy check. Protocol
                                            Officers never see this irreversible
                                            action, even for their own records.

                                            ProtocolController::forceDelete()
                                            repeats the authorization before it
                                            removes database records or files.
                                        --}}
                                        @can('forceDelete', $protocol)

                                            {{--
                                                Submit a DELETE request to:

                                                /protocols/{protocol}/force-delete

                                                HTML forms support only GET and
                                                POST directly. The @method
                                                directive adds a hidden field
                                                that tells Laravel to treat this
                                                request as DELETE.
                                            --}}
                                            <form
                                                method="POST"
                                                action="{{ route('protocols.force-delete', $protocol->id) }}"
                                                onsubmit="return confirm('{{ __('protocols.confirmations.force_delete') }}')"
                                            >
                                                {{--
                                                    Protect the form from
                                                    cross-site request forgery.
                                                --}}
                                                @csrf

                                                {{--
                                                    Convert the submitted POST
                                                    request into DELETE so that
                                                    it matches the route.
                                                --}}
                                                @method('DELETE')

                                                {{--
                                                    The danger style distinguishes
                                                    this irreversible action from
                                                    the reversible Restore action.
                                                --}}
                                                <button
                                                    type="submit"
                                                    class="button button-danger"
                                                >
                                                    {{ __('protocols.actions.force_delete') }}
                                                </button>
                                            </form>

                                        @endcan

                                    @else

                                        {{--
                                            If the authenticated user is not
                                            authorized to restore or permanently
                                            delete this record, do not display
                                            either action button.

                                            Under the current controller query,
                                            this is mainly a safe fallback for
                                            future roles or policy changes.
                                        --}}
                                        <span>-</span>

                                    @endcanany

                                </div>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>


        {{--
            Display Laravel's pagination navigation.

            This works because ProtocolController::deleted()
            uses paginate(10).

            Laravel automatically generates links for moving
            between the deleted-protocol pages.
        --}}
        <div class="pagination-container">

            {{ $protocols->links() }}

        </div>


    @else

        {{--
            Display this message when there are no
            soft-deleted protocols in the database.
        --}}
        <p>
            {{ __('protocols.deleted.empty') }}
        </p>

    @endif


{{-- End of the content section. --}}
@endsection
