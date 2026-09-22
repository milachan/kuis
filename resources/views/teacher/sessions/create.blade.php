@extends('layouts.teacher')

@section('title', 'Buat Sesi')
@section('page-title', 'Buat Sesi Baru')
@section('page-subtitle', 'Atur kode sesi, timer, dan opsi permainan')

@section('content')

    <form method="POST" action="{{ route('teacher.sessions.store') }}" data-guard>
        @csrf

        @include('teacher.sessions.partials.form', [
            'session' => null,
            'missionList' => \App\Models\Mission::query()->active()->ordered()->get(),
        ])

        <div class="mt-6 flex flex-wrap gap-2">
            <button type="submit" class="btn-primary">Simpan Sesi</button>
            <a href="{{ route('teacher.sessions.index') }}" class="btn-secondary">Batal</a>
        </div>
    </form>

@endsection
