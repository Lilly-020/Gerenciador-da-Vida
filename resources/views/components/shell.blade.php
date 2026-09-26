@props(['title' => null, 'align' => 'center'])

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name', 'Gerenciador da Vida') }}</title>

        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <meta name="theme-color" content="#020617">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Gerenciador">
        <meta name="mobile-web-app-capable" content="yes">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 font-sans text-white antialiased">
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_70%_60%_at_15%_100%,rgba(37,99,235,0.35),transparent)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_100%_0%,rgba(30,41,59,0.6),transparent)]"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            {{ $header ?? '' }}

            <main class="flex flex-1 justify-center px-6 pb-24 {{ $align === 'start' ? 'items-start pt-16' : 'items-center py-24' }}">
                {{ $slot }}
            </main>
        </div>

        {{ $modals ?? '' }}
    </body>
</html>
