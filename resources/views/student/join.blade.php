@extends('layouts.guest')

@section('title', 'Masuk Kelompok')

@section('content')
<div class="w-full max-w-lg">

    {{-- Brand --}}
    <div class="mb-6 text-center">
        <a href="{{ route('student.landing') }}" class="inline-block">
            <h1 class="text-2xl font-black tracking-tight text-cyan-accent">TIK MISSION</h1>
            <p class="text-sm text-white/60">Operasi File Rahasia</p>
        </a>
    </div>

    <div class="panel p-6">
        <h2 class="mb-1 text-lg font-semibold">Masuk Kelompok</h2>
        <p class="mb-5 text-sm text-white/60">
            Masukkan kode sesi dari gurumu, lalu isi nama kelompok dan anggotanya.
            Setiap anggota ditulis dengan format <strong class="text-white/80">Nama Lengkap - No Absen</strong>.
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
                       class="input-field text-center text-lg font-bold uppercase tracking-widest @error('code') border-rose-400/60 @enderror">
                <p class="mt-1.5 text-xs text-white/50">Kode diberikan oleh guru di depan kelas.</p>
                @error('code')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
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
                       placeholder="Contoh: Kelompok 3"
                       class="input-field @error('team_name') border-rose-400/60 @enderror">
                @error('team_name')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            {{-- Anggota --}}
            <div>
                <label class="label-field">Nama Anggota Kelompok</label>
                <p class="mb-2 text-xs text-white/60">
                    Tulis dengan format
                    <span class="font-semibold text-cyan-accent">Nama Lengkap - No Absen</span>.
                    <br>
                    Contoh: <span class="font-mono text-white/80">Ahmad Rizki Pratama - 07</span>
                </p>

                <div class="space-y-2" id="member-fields">
                    @php
                        $oldMembers = old('members', ['', '']);
                    @endphp
                    @for ($i = 0; $i < max(2, count($oldMembers)); $i++)
                        <div class="flex items-center gap-2">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/5 text-xs font-bold text-white/40">
                                {{ $i + 1 }}
                            </span>
                            <input type="text"
                                   name="members[]"
                                   value="{{ $oldMembers[$i] ?? '' }}"
                                   placeholder="Nama Lengkap - No Absen"
                                   class="input-field member-input @error('members.'.$i) border-rose-400/60 @enderror">
                        </div>
                    @endfor
                </div>

                <button type="button"
                        id="add-member"
                        class="mt-2 text-xs font-semibold text-cyan-accent transition hover:text-cyan-accent/80">
                    + Tambah anggota
                </button>

                @error('members')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
                @error('members.*')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary w-full py-3 text-base">
                Masuk & Mulai Misi
            </button>
        </form>
    </div>

    <p class="mt-5 text-center text-sm text-white/50">
        Guru?
        <a href="{{ route('login') }}" class="font-semibold text-cyan-accent hover:underline">
            Masuk panel guru
        </a>
    </p>
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
            number.className = 'grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/5 text-xs font-bold text-white/40';
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
