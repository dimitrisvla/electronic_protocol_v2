<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ __('protocols.receipt.page_title') }}</title>

    <style>
        :root {
            color-scheme: light;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #f3f4f6;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px 16px;
            background: #f3f4f6;
        }

        .receipt {
            width: min(820px, 100%);
            margin: 0 auto;
            padding: 40px;
            border: 1px solid #9ca3af;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 10px 25px rgb(15 23 42 / 10%);
        }

        .receipt-header {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid #111827;
            text-align: center;
        }

        .organization-name {
            margin: 0 0 12px;
            font-size: 1.1rem;
            font-weight: 700;
        }

        h1 {
            margin: 0;
            font-size: 1.45rem;
            line-height: 1.35;
        }

        .receipt-description {
            margin: 0 0 24px;
            line-height: 1.6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 11px 12px;
            border: 1px solid #6b7280;
            text-align: left;
            vertical-align: top;
        }

        th {
            width: 34%;
            background: #f9fafb;
            font-weight: 700;
        }

        .receipt-footer {
            margin-top: 24px;
            text-align: right;
            font-size: 0.9rem;
        }

        .screen-actions {
            display: flex;
            width: min(820px, 100%);
            margin: 20px auto 0;
            gap: 12px;
            justify-content: flex-end;
        }

        .button {
            display: inline-block;
            padding: 10px 16px;
            border: 1px solid #374151;
            border-radius: 6px;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .button-primary {
            color: #ffffff;
            background: #1d4ed8;
            border-color: #1d4ed8;
        }

        .button-secondary {
            color: #111827;
            background: #ffffff;
        }

        @page {
            size: A4 portrait;
            margin: 18mm;
        }

        @media print {
            :root,
            body {
                background: #ffffff;
            }

            body {
                padding: 0;
            }

            .receipt {
                width: 100%;
                padding: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .screen-actions {
                display: none !important;
            }

            th {
                background: #ffffff;
            }
        }
    </style>
</head>
<body>
    <main class="receipt">
        <header class="receipt-header">
            <p class="organization-name">{{ $organizationName }}</p>
            <h1>{{ __('protocols.receipt.heading') }}</h1>
        </header>

        <p class="receipt-description">
            {{ __('protocols.receipt.description') }}
        </p>

        <table>
            <tbody>
                <tr>
                    <th scope="row">{{ __('protocols.receipt.reference') }}</th>
                    <td>
                        <strong>
                            {{ $protocol->protocol_number }}/{{ $protocol->protocol_year }}
                        </strong>
                    </td>
                </tr>
                <tr>
                    <th scope="row">{{ __('protocols.fields.protocol_date') }}</th>
                    <td>{{ $protocol->protocol_date->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <th scope="row">{{ __('protocols.fields.direction') }}</th>
                    <td>{{ __('protocols.directions.' . $protocol->direction) }}</td>
                </tr>
                <tr>
                    <th scope="row">{{ __('protocols.fields.subject') }}</th>
                    <td>{{ $protocol->subject }}</td>
                </tr>
                <tr>
                    <th scope="row">{{ __('protocols.fields.sender') }}</th>
                    <td>{{ $protocol->sender ?: '—' }}</td>
                </tr>
                <tr>
                    <th scope="row">{{ __('protocols.fields.recipient') }}</th>
                    <td>{{ $protocol->recipient ?: '—' }}</td>
                </tr>
            </tbody>
        </table>

        <footer class="receipt-footer">
            {{ __('protocols.receipt.printed_at') }}:
            {{ $printedAt->format('d/m/Y H:i:s') }}
        </footer>
    </main>

    <nav class="screen-actions" aria-label="{{ __('protocols.receipt.page_title') }}">
        <a
            href="{{ route('protocols.show', $protocol) }}"
            class="button button-secondary"
        >
            {{ __('protocols.receipt.back') }}
        </a>

        <button
            type="button"
            class="button button-primary"
            onclick="window.print()"
        >
            {{ __('protocols.receipt.print') }}
        </button>
    </nav>
</body>
</html>
