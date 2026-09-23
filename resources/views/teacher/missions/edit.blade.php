@extends('layouts.teacher')

@section('title', 'Edit Misi')
@section('page-title', 'Edit Misi')
@section('page-subtitle', $mission->title)

@section('page-actions')
    <a href="{{ route('teacher.missions') }}" class="btn-secondary">Kembali</a>
@endsection

@section('content')

    <form method="POST" action="{{ route('teacher.missions.update', $mission) }}" data-guard>
        @csrf
        @method('PUT')

        <div class="grid gap-5 lg:grid-cols-3">

            {{-- Kolom kiri --}}
            <div class="space-y-5 lg:col-span-2">
                <div class="panel p-5">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-cyan-accent">Informasi Misi</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="title" class="label-field">Judul Misi <span class="text-rose-300">*</span></label>
                            <input id="title" type="text" name="title"
                                   value="{{ old('title', $mission->title) }}" required
                                   class="input-field @error('title') border-rose-400/60 @enderror">
                            @error('title')
                                <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="difficulty" class="label-field">Tingkat Kesulitan</label>
                                <select id="difficulty" name="difficulty" class="input-field">
                                    @foreach (['Mudah', 'Sedang', 'Sulit'] as $level)
                                        <option value="{{ $level }}" @selected(old('difficulty', $mission->difficulty) === $level)>
                                            {{ $level }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="xp" class="label-field">XP Dasar</label>
                                <input id="xp" type="number" name="xp" min="0" max="1000"
                                       value="{{ old('xp', $mission->xp) }}" required
                                       class="input-field @error('xp') border-rose-400/60 @enderror">
                                @error('xp')
                                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="story" class="label-field">Cerita Pembuka <span class="text-rose-300">*</span></label>
                            <textarea id="story" name="story" rows="3" required
                                      class="input-field resize-y @error('story') border-rose-400/60 @enderror">{{ old('story', $mission->story) }}</textarea>
                            @error('story')
                                <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="objective" class="label-field">Tujuan Pembelajaran <span class="text-rose-300">*</span></label>
                            <textarea id="objective" name="objective" rows="2" required
                                      class="input-field resize-y @error('objective') border-rose-400/60 @enderror">{{ old('objective', $mission->objective) }}</textarea>
                            @error('objective')
                                <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="instructions" class="label-field">
                                Instruksi Praktik <span class="text-rose-300">*</span>
                            </label>
                            <textarea id="instructions" name="instructions" rows="12" required
                                      class="input-field resize-y font-mono text-xs leading-relaxed @error('instructions') border-rose-400/60 @enderror">{{ old('instructions', implode("\n", $mission->instructions ?? [])) }}</textarea>
                            <p class="mt-1 text-xs text-white/40">
                                Tulis satu langkah per baris. Setiap baris menjadi satu poin bernomor.
                            </p>
                            @error('instructions')
                                <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="panel p-5">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-white/70">Refleksi</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="reflection_question" class="label-field">Pertanyaan Refleksi (opsional)</label>
                            <textarea id="reflection_question" name="reflection_question" rows="2"
                                      class="input-field resize-y">{{ old('reflection_question', $mission->reflection_question) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kolom kanan --}}
            <div class="space-y-5">
                <div class="panel p-5">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-white/70">Petunjuk</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="hint_1" class="label-field">Petunjuk 1</label>
                            <textarea id="hint_1" name="hint_1" rows="3"
                                      class="input-field resize-y">{{ old('hint_1', $mission->hint_1) }}</textarea>
                            <p class="mt-1 text-xs text-white/40">
                                Beri arahan, bukan jawaban langsung.
                            </p>
                        </div>

                        <div>
                            <label for="hint_2" class="label-field">Petunjuk 2</label>
                            <textarea id="hint_2" name="hint_2" rows="3"
                                      class="input-field resize-y">{{ old('hint_2', $mission->hint_2) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="panel p-5">
                    <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-white/70">Opsi</h2>

                    <div class="space-y-3">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="requires_pdf" value="1"
                                   @checked(old('requires_pdf', $mission->requires_pdf))
                                   class="mt-0.5 h-4 w-4 rounded border-white/20 bg-navy-900 text-cyan-strong focus:ring-cyan-accent">
                            <span class="text-sm text-white/80">Wajib mengunggah file PDF</span>
                        </label>

                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="is_active" value="1"
                                   @checked(old('is_active', $mission->is_active))
                                   class="mt-0.5 h-4 w-4 rounded border-white/20 bg-navy-900 text-cyan-strong focus:ring-cyan-accent">
                            <span class="text-sm text-white/80">Aktifkan misi ini</span>
                        </label>
                    </div>
                </div>

                <div class="panel p-5">
                    <button type="submit" class="btn-primary w-full">Simpan Perubahan</button>
                    <a href="{{ route('teacher.missions') }}" class="btn-secondary mt-2 w-full">Batal</a>
                </div>
            </div>
        </div>
    </form>

@endsection
