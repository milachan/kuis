{{--
    Form sesi, dipakai bersama oleh halaman "create" dan "edit".
    Variabel: $session (nullable), $durationOptions, $missionList
--}}
@php
    $isEdit = isset($session) && $session->exists;
@endphp

<div class="grid gap-5 lg:grid-cols-3">

    {{-- Kolom kiri: pengaturan sesi --}}
    <div class="space-y-5 lg:col-span-2">
        <div class="panel p-5">
            <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-cyan-accent">Pengaturan Sesi</h2>

            <div class="space-y-4">
                {{-- Nama sesi --}}
                <div>
                    <label for="name" class="label-field">Nama Sesi <span class="text-rose-300">*</span></label>
                    <input id="name"
                           type="text"
                           name="name"
                           value="{{ old('name', $session->name ?? '') }}"
                           required
                           class="input-field @error('name') border-rose-400/60 @enderror"
                           placeholder="Contoh: TIK Kelas 8A — Pertemuan 3">
                    @error('name')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Kode sesi --}}
                <div>
                    <label for="code" class="label-field">Kode Sesi <span class="text-rose-300">*</span></label>
                    <input id="code"
                           type="text"
                           name="code"
                           value="{{ old('code', $session->code ?? '') }}"
                           required
                           class="input-field text-center font-mono font-bold uppercase tracking-widest @error('code') border-rose-400/60 @enderror"
                           placeholder="TIK8-2026">
                    <p class="mt-1 text-xs text-white/40">
                        Kode ini dibagikan ke siswa. Hanya huruf, angka, dan tanda minus.
                    </p>
                    @error('code')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Durasi --}}
                <div>
                    <label for="duration_minutes" class="label-field">Batas Waktu <span class="text-rose-300">*</span></label>
                    <select id="duration_minutes"
                            name="duration_minutes"
                            required
                            class="input-field @error('duration_minutes') border-rose-400/60 @enderror">
                        @foreach ($durationOptions as $option)
                            <option value="{{ $option }}"
                                @selected((int) old('duration_minutes', $session->duration_minutes ?? 60) === (int) $option)>
                                {{ $option === 0 ? 'Tanpa timer' : $option.' menit' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-white/50">
                        Jam ini mulai berjalan saat kamu membuka ronde pertama, bukan saat sesi dibuat —
                        jadi waktu persiapan kelas tidak ikut terhitung. Bisa ditambah atau dimatikan
                        kapan saja dari halaman sesi.
                    </p>
                    @error('duration_minutes')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Opsi tambahan --}}
                <div class="space-y-3 border-t border-white/10 pt-4">
                    <label class="flex items-start gap-3">
                        <input type="checkbox"
                               name="hints_enabled"
                               value="1"
                               @checked(old('hints_enabled', $session->hints_enabled ?? true))
                               class="mt-0.5 h-4 w-4 rounded border-white/20 bg-navy-900 text-cyan-strong focus:ring-cyan-accent">
                        <span>
                            <span class="block text-sm font-medium text-white/90">Aktifkan sistem petunjuk</span>
                            <span class="block text-xs text-white/50">
                                Siswa dapat membuka petunjuk, tetapi XP berkurang
                                {{ config('tikmission.hint_penalty_xp') }} per petunjuk.
                            </span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3">
                        <input type="checkbox"
                               name="leaderboard_enabled"
                               value="1"
                               @checked(old('leaderboard_enabled', $session->leaderboard_enabled ?? false))
                               class="mt-0.5 h-4 w-4 rounded border-white/20 bg-navy-900 text-cyan-strong focus:ring-cyan-accent">
                        <span>
                            <span class="block text-sm font-medium text-white/90">Aktifkan papan skor</span>
                            <span class="block text-xs text-white/50">
                                Opsional. Papan skor tidak wajib dipakai.
                            </span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- Kolom kanan: ringkasan misi --}}
    <div class="space-y-5">
        <div class="panel p-5">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-white/70">Daftar Misi</h2>
            <ol class="space-y-2 text-sm">
                @forelse ($missionList ?? [] as $mission)
                    <li class="flex items-center gap-2 text-white/70">
                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded bg-cyan-strong/15 text-[10px] font-bold text-cyan-accent">
                            {{ $mission->order }}
                        </span>
                        <span class="truncate">{{ $mission->title }}</span>
                    </li>
                @empty
                    <li class="text-sm text-white/40">Misi belum tersedia. Jalankan seeder.</li>
                @endforelse
            </ol>
            <p class="mt-4 text-xs text-white/40">
                Misi dikelola di menu <strong>Misi</strong>.
            </p>
        </div>

        <div class="panel p-5">
            <h2 class="mb-2 text-sm font-bold uppercase tracking-wider text-white/70">Langkah Selanjutnya</h2>
            <ul class="space-y-2 text-xs text-white/60">
                <li>1. Simpan sesi dan salin kode sesi.</li>
                <li>2. Bagikan kode ke siswa di laboratorium.</li>
                <li>3. Siswa masuk dengan kode + nama kelompok.</li>
                <li>4. Validasi bukti dari menu <strong>Validasi</strong>.</li>
            </ul>
        </div>
    </div>
</div>
