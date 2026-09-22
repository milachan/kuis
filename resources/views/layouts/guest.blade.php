<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'TIK Mission') — TIK Mission</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative min-h-screen bg-navy-950 font-sans text-white antialiased">

    {{-- Latar dekoratif ringan (CSS saja, tanpa particle/WebGL) --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-strong/10 blur-3xl"></div>
        <div class="absolute -bottom-32 -right-24 h-80 w-80 rounded-full bg-teal-accent/10 blur-3xl"></div>
    </div>

    {{-- Toast container --}}
    <div id="tik-toast-container"
         class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"></div>

    @include('components.flash')

    <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-10">
        @yield('content')
    </div>

</body>
</html>
