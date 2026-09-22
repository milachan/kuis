@extends('layouts.guest')

@section('title', 'Masuk Kelompok')

@section('content')
<div class="w-full max-w-lg">

    {{-- Brand --}}
    <div class="mb-6 text-center">
        <a href="{{ route('student.landing') }}" class="inline-block">
            <span class="grid h-16 w-16 mx-auto place-items-center rounded-3xl bg-sky-500 text-3xl shadow-sm">🎓</span>
            <h1 class="mt-3 text-2xl font-black tracking-tight text-ink-900">TIK MISSION</h1>
            <p class="text-sm font-bold text-sky-600">Bab 4 · Sistem Komputer</p>
        </a>
    </div>

    <div class="card-bright">
        <h2 class="mb-1 text-lg font-black text-ink-900">Masuk Kelompok</h2>
        <p class="mb-5 text-sm font-semibold text-ink-700">
            Masukkan kode sesi dari gurumu, lalu isi nama kelompok dan anggotanya.
            Setiap anggota ditulis dengan format <strong class="text-ink-900">Nama Lengkap - No Absen</strong>.
        </p>

        <form method="POST" action="{{ route('student.join.attempt') }}" class="space-y-5" data-guard>
            @csrf

            {{-- Kode sesi --}}
            <div>
                <label for="code" class="label-field">Kode Sesi</label>
                <input id="code"
                       type="text"
                       name="code"
                       value="{{ old('code', $prefillCode) }}"
                       required
                       autofocus
                       autocomplete="off"
                       placeholder="Contoh: TIK8-DEMO"
                       class="input-field text-center text-lg font-black uppercase tracking-widest @error('code') border-coral-400 @enderror">
                <p class="mt-1.5 text-xs font-semibold text-ink-600">Kode diberikan oleh guru di depan kelas.</p>
                @error('code')
                    <p class="mt-1 text-xs font-bold text-coral-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Nama kelompok --}}
            <div>
                <label for="team_name" class="label-field">Nama Kelompok</label>
                <input id="team_name"
                       type="text"
                       name="team_name"
                       value="{{ old('team_name') }}"
                       required
                       autocomplete="off"
                       placeholder="Contoh: Kelompok 1"
                       class="input-field @error('team_name') border-coral-400 @enderror">
                <p class="mt-1.5 text-xs font-semibold text-ink-600">
                    Pakai nama yang mudah dikenali guru, misalnya "Kelompok 3".
                </p>
                @error('team_name')
                    <p class="mt-1 text-xs font-bold text-coral-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Anggota --}}
            <div>
                <label class="label-field">Nama Anggota Kelompok</label>
                <p class="mb-2 text-xs font-semibold text-ink-600">
                    Tulis dengan format
                    <span class="font-black text-sky-600">Nama Lengkap - No Absen</span>.
                    <br>
                    Contoh: <span class="font-mono font-bold text-ink-800">Ahmad Rizki Pratama - 07</span>
                </p>

                <div class="space-y-2" id="member-fields">
                    @php
                        $oldMembers = old('members', ['', '']);
                    @endphp
                    @for ($i = 0; $i < max(2, count($oldMembers)); $i++)
                        <div class="flex items-center gap-2">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-sky-100 text-xs font-black text-sky-700">
                                {{ $i + 1 }}
                            </span>
                            <input type="text"
                                   name="members[]"
                                   value="{{ $oldMembers[$i] ?? '' }}"
                                   placeholder="Nama Lengkap - No Absen"
                                   class="input-field member-input @error('members.'.$i) border-coral-400 @enderror">
                        </div>
                    @endfor
                </div>

                <button type="button"
                        id="add-member"
                        class="mt-2 text-xs font-black text-sky-600 transition hover:text-sky-700">
                    + Tambah anggota
                </button>

                @error('members')
                    <p class="mt-1 text-xs font-bold text-coral-500">{{ $message }}</p>
                @enderror
                @error('members.*')
                    <p class="mt-1 text-xs font-bold text-coral-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Tombol masuk --}}
            <button type="submit" class="btn-primary w-full py-3.5 text-base">
                🚀 Masuk & Mulai Bermain
            </button>
        </form>
    </div>

    {{-- Aturan singkat --}}
    <div class="card-soft mt-4">
        <p class="text-xs font-black uppercase tracking-wider text-ink-700">📌 Aturan Main</p>
        <ul class="mt-2 space-y-1.5 text-xs font-semibold text-ink-700">
            <li class="flex gap-2"><span class="text-mint-500">✓</span> Satu kelompok pakai satu komputer.</li>
            <li class="flex gap-2"><span class="text-mint-500">✓</span> Jawab dengan bahasamu sendiri, jangan menyalin.</li>
            <li class="flex gap-2"><span class="text-mint-500">✓</span> Jawaban tetap mendapat nilai walau belum tepat.</li>
        </ul>
    </div>
</div>

<script>
    // Tambah kolom anggota (maksimal 10) — vanilla JS sederhana.
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('member-fields');
        const addBtn = document.getElementById('add-member');

        addBtn.addEventListener('click', function () {
            const inputs = container.querySelectorAll('.member-input');

            if (inputs.length >= 10) {
                window.TikToast('Maksimal 10 anggota.', 'warning');
                return;
            }

            const index = inputs.length + 1;

            // Baris baru dibuat sama seperti baris bawaan: nomor + kolom isian.
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 animate-fade-up';

            const number = document.createElement('span');
            number.className = 'grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-sky-100 text-xs font-black text-sky-700';
            number.textContent = index;

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'members[]';
            input.placeholder = 'Nama Lengkap - No Absen';
            input.className = 'input-field member-input';

            row.appendChild(number);
            row.appendChild(input);
            container.appendChild(row);
            input.focus();
        });
    });
</script>
@endsection
