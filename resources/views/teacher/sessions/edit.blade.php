@extends('layouts.teacher')

@section('title', 'Edit Sesi')
@section('page-title', 'Edit Sesi')
@section('page-subtitle', $session->name)

@section('content')

    <form method="POST" action="{{ route('teacher.sessions.update', $session) }}" data-guard>
        @csrf
        @method('PUT')

        @include('teacher.sessions.partials.form', [
            'session' => $session,
            'missionList' => $missionList,
            'codes' => $codes,
        ])

        <div class="mt-6 flex flex-wrap gap-2">
            <button type="submit" class="btn-primary">Simpan Perubahan</button>
            <a href="{{ route('teacher.sessions.show', $session) }}" class="btn-secondary">Batal</a>
        </div>
    </form>

@endsection
