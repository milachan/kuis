@extends('layouts.guest')

@section('title', 'Masuk Guru')

@section('content')
<div class="w-full max-w-md">

    {{-- Brand --}}
    <div class="mb-6 text-center">
        <div class="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-xl bg-gradient-to-br from-cyan-accent to-teal-accent text-2xl font-black text-navy-950">
            T
        </div>
        <h1 class="text-xl font-bold tracking-tight">TIK MISSION</h1>
        <p class="text-sm text-cyan-accent/80">Operasi File Rahasia — Panel Guru</p>
    </div>

    {{-- Kartu login --}}
    <div class="panel p-6">
        <h2 class="mb-1 text-lg font-semibold">Masuk Guru</h2>
        <p class="mb-5 text-sm text-white/60">Masukkan email dan password akun guru.</p>

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="label-field">Email</label>
                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       autofocus
                       autocomplete="username"
                       class="input-field @error('email') border-rose-400/60 @enderror"
                       placeholder="guru@sekolah.sch.id">
                @error('email')
                    <p class="mt-1.5 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="label-field">Password</label>
                <input id="password"
                       type="password"
                       name="password"
                       required
                       autocomplete="current-password"
                       class="input-field @error('password') border-rose-400/60 @enderror"
                       placeholder="••••••••">
                @error('password')
                    <p class="mt-1.5 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-white/70">
                <input type="checkbox"
                       name="remember"
                       value="1"
                       class="h-4 w-4 rounded border-white/20 bg-navy-900 text-cyan-strong focus:ring-cyan-accent">
                Ingat saya
            </label>

            <button type="submit" class="btn-primary w-full">
                Masuk
            </button>
        </form>

        {{-- Info akun demo --}}
        <div class="mt-6 rounded-lg border border-gold/25 bg-gold/5 p-3 text-xs text-gold/90">
            <p class="font-semibold">Akun demo</p>
            <p class="mt-0.5">Email: admin@example.com · Password: password</p>
        </div>
    </div>

    <p class="mt-5 text-center text-sm text-white/50">
        Kamu siswa?
        <a href="{{ route('student.join') }}" class="font-semibold text-cyan-accent hover:underline">
            Masuk dengan kode sesi
        </a>
    </p>
</div>
@endsection
