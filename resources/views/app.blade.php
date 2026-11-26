<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php($nonce = \Illuminate\Support\Facades\Vite::cspNonce())
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $site['primary'] }}">
    @if (! empty($site['description']))
        <meta name="description" content="{{ $site['description'] }}">
    @endif
    <title>{{ $site['name'] }}</title>
    @if (! empty($site['favicon_url']))
        <link rel="icon" href="{{ $site['favicon_url'] }}">
    @endif
    <style id="site-theme"@if (! empty($nonce)) nonce="{{ $nonce }}"@endif>{!! $site['css'] !!}</style>
    <script @if (! empty($nonce)) nonce="{{ $nonce }}" @endif>window.__SITE__ = @json($site['bootstrap']);</script>
    @vite(['resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>