<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Masuk') — TIK Mission</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('scripts')
</head>
<body class="min-h-screen bg-sky-50 font-sans text-ink-900 antialiased">

    {{-- Toast container --}}
    <div id="tik-toast-container"
         class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"></div>

    {{-- Flash message (sukses / error / warning) --}}
    @include('components.flash')

    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        @yield('content')
    </main>

</body>
</html>
