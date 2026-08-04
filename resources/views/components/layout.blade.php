<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ dark: localStorage.getItem('ttn-dark') === '1' }" x-init="$watch('dark', v => { localStorage.setItem('ttn-dark', v ? '1' : '0'); document.documentElement.classList.toggle('dark', v); }); document.documentElement.classList.toggle('dark', dark);" :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @isset($activityPageToken)
        <meta name="activity-page-token" content="{{ $activityPageToken }}">
    @endisset
    <title>{{ $title ?? 'ShuleSoft Talent Network' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    {{ $slot }}

    @isset($activityPageToken)
        <script>
            (function () {
                var token = document.querySelector('meta[name="activity-page-token"]')?.content;
                if (!token) return;
                var start = performance.now();
                var sent = false;
                var send = function () {
                    if (sent) return;
                    sent = true;
                    var duration = Math.round(performance.now() - start);
                    var payload = new Blob([JSON.stringify({ token: token, duration_ms: duration })], { type: 'application/json' });
                    navigator.sendBeacon('{{ route('activity.ping') }}', payload);
                };
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'hidden') send();
                });
                window.addEventListener('pagehide', send);
            })();
        </script>
    @endisset
</body>
</html>
