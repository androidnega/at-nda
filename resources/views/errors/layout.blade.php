<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">

        <title>@yield('title')</title>

        <style>
            html, body {
                background-color: #fff;
                color: #111827;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                margin: 0;
                min-height: 100vh;
            }

            .wrap {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }

            .message {
                text-align: center;
                font-size: 1.1rem;
                font-weight: 600;
                color: #1f2937;
                border: 1px solid #e5e7eb;
                border-radius: 0.9rem;
                padding: 1rem 1.25rem;
            }
        </style>
    </head>
    <body>
        <div class="wrap">
            <div class="message">
                @yield('message')
            </div>
        </div>
    </body>
</html>
