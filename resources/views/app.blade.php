<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
        <meta name="theme-color" content="#ffffff" />
        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" />
        @inertiaHead
        @vite('resources/js/app.jsx')
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>