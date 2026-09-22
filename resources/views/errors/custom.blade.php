<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Error') — TIK Mission</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-navy-950 font-sans text-white antialiased">

    @include('components.flash')

    <div class="px-4 text-center">
        <p class="text-6xl font-black text-cyan-accent/30">{{ $status ?? 404 }}</p>
        <h1 class="mt-2 text-xl font-bold">{{ $message ?? 'Terjadi kesalahan.' }}</h1>

        <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}"
               class="btn-secondary">Kembali</a>
            <a href="{{ url('/') }}" class="btn-primary">Ke Halaman Awal</a>
        </div>
    </div>

</body>
</html>
