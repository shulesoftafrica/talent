<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ dark: localStorage.getItem('ttn-dark') === '1' }" x-init="$watch('dark', v => { localStorage.setItem('ttn-dark', v ? '1' : '0'); document.documentElement.classList.toggle('dark', v); }); document.documentElement.classList.toggle('dark', dark);" :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ShuleSoft Talent Network' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    {{ $slot }}
</body>
</html>
