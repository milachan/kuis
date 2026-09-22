@extends('layouts.teacher')

@section('title', 'Validasi Jawaban')
@section('page-title', 'Validasi Jawaban')
@section('page-subtitle', ($submission->team->name ?? '-').' Â· '.($submission->mission->title ?? '-'))

@section('page-actions')
    <a href="{{ route('teacher.validations') }}" class="btn-secondary">Kembali</a>
@endsection

@section('content')

    <div class="grid gap-5 lg:grid-cols-3">

        {{-- Kolom kiri: bukti --}}
        <div class="space-y-5 lg:col-span-2">

            {{-- Status --}}
            <div class="panel p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-white/50">Status Kiriman</p>
                        <div class="mt-1.5">
                            <x-status-badge :status="$submission->status" type="submission" />
                        </div>
                    </div>

                    <div class="text-right text-xs text-white/50">
                        <p>Dikirim: {{ $submission->submitted_at?->format('d/m/Y H:i') ?? '-' }}</p>
                        @if ($submission->validated_at)
                            <p>Divalidasi: {{ $submission->validated_at->format('d/m/Y H:i') }}</p>
                        @endif
                    </div>
                </div>

                {{-- Daftar pertanyaan misi, agar guru tahu apa yang ditanyakan --}}
                @if ($submission->mission && $submission->mission->questionCount() > 0)
                    <div class="mt-4 border-t border-white/10 pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-white/50">
                            Pertanyaan Misi ({{ $submission->mission->questionCount() }})
                        </p>
                        <ol class="space-y-1.5 text-xs text-white/60">
                            @foreach ($submission->mission->questionList() as $i => $q)
                                <li class="flex gap-2">
                                    <span class="shrink-0 font-bold text-cyan-accent">{{ $i + 1 }}.</span>
                                    <span>
                                        @if (! empty($q['jenis']))
                                            <span class="mr-1 rounded bg-white/10 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-white/50">
                                                {{ $q['jenis'] }}
                                            </span>
                                        @endif
                                        {{ $q['pertanyaan'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                {{-- Jawaban siswa --}}
                @if ($submission->answer)
                    <div class="mt-4 border-t border-white/10 pt-4">
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">
                            Jawaban Siswa
                        </p>
                        <p class="whitespace-pre-line rounded-lg border border-white/10 bg-navy-900/60 p-3 text-sm leading-relaxed text-white/80">{{ $submission->answer }}</p>
                    </div>
                @endif

                {{-- Komentar sebelumnya --}}
                @if ($submission->teacher_comment)
                    <div class="mt-4 rounded-lg border border-cyan-accent/25 bg-cyan-accent/5 p-3">
                        <p class="text-xs font-semibold text-cyan-accent">Catatan Terakhir</p>
                        <p class="mt-1 text-sm text-white/80">{{ $submission->teacher_comment }}</p>
                    </div>
                @endif
            </div>

            {{-- Lampiran opsional --}}
            <div class="panel p-5">
                <h2 class="mb-1 text-sm font-bold uppercase tracking-wider text-cyan-accent">ðŸ“Ž Lampiran (opsional)</h2>
                <p class="mb-3 text-[11px] text-white/40">
                    Murid tidak diwajibkan mengunggah apa pun. Bagian ini hanya terisi bila mereka melampirkan sendiri.
                </p>

                @if ($submission->evidence_path)
                    @if ($submission->evidenceIsImage())
                        <a href="{{ $submission->evidenceUrl() }}" target="_blank" class="block">
                            <img src="{{ $submission->evidenceUrl() }}"
                                 alt="Bukti praktik siswa"
                                 class="max-h-[28rem] w-full rounded-lg border border-white/10 bg-navy-900/60 object-contain">
                        </a>
                        <p class="mt-2 text-center text-xs text-white/40">
                            Klik gambar untuk membuka ukuran penuh.
                        </p>
                    @else
                        <a href="{{ $submission->evidenceUrl() }}" target="_blank"
                           class="flex items-center gap-3 rounded-lg border border-white/10 bg-navy-900/60 p-4 transition hover:border-cyan-accent/40">
                            <span class="text-3xl">ðŸ“„</span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-cyan-accent">
                                    {{ $submission->evidenceOriginalName() }}
                                </span>
                                <span class="block text-xs text-white/40">Klik untuk membuka file</span>
                            </span>
                        </a>
                    @endif
                @else
                    <p class="py-6 text-center text-sm text-white/40">Tidak ada lampiran.</p>
                @endif
            </div>

            {{-- File tambahan --}}
            @if ($submission->file_path)
                <div class="panel p-5">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-cyan-accent">ðŸ“Ž File Tambahan</h2>
                    <a href="{{ $submission->fileUrl() }}" target="_blank"
                       class="flex items-center gap-3 rounded-lg border border-white/10 bg-navy-900/60 p-4 transition hover:border-cyan-accent/40">
                        <span class="text-3xl">ðŸ“„</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-cyan-accent">
                                {{ $submission->fileOriginalName() }}
                            </span>
                            <span class="block text-xs text-white/40">Klik untuk membuka file</span>
                        </span>
                    </a>
                </div>
            @endif
        </div>

        {{-- Kolom kanan: keputusan --}}
        <div class="space-y-5">

            {{-- Hasil penilaian AI --}}
            <div class="panel border-cyan-accent/25 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-cyan-accent">ðŸ¤– Penilaian AI</h2>
                    <span class="badge bg-white/5 text-white/60">{{ $submission->aiStatusLabel() }}</span>
                </div>

                @if ($submission->hasAiScore())
                    <div class="mt-3 flex items-end gap-3">
                        <p class="font-mono text-4xl font-black {{ $submission->passedAi() ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $submission->ai_score }}
                        </p>
                        <p class="pb-1 text-xs text-white/50">/ 100 Â· ambang lulus {{ config('ai.passing_score') }}</p>
                    </div>

                    @if ($submission->ai_feedback)
                        <p class="mt-3 rounded-lg border border-white/10 bg-navy-900/60 p-3 text-sm leading-relaxed text-white/80">
                            {{ $submission->ai_feedback }}
                        </p>
                    @endif

                    @if (! empty($submission->aiCriteria()))
                        <dl class="mt-3 space-y-1.5 text-xs">
                            @foreach ($submission->aiCriteria() as $criterion)
                                <div class="flex items-start justify-between gap-2">
                                    <dt class="text-white/60">
                                        {{ $criterion['kriteria'] ?? '-' }}
                                        @if (! empty($criterion['catatan']))
                                            <span class="block text-white/35">{{ $criterion['catatan'] }}</span>
                                        @endif
                                    </dt>
                                    <dd class="shrink-0 font-mono font-bold text-white/80">{{ $criterion['nilai'] ?? '-' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    <p class="mt-3 text-[11px] text-white/35">
                        Model: {{ $submission->ai_model ?? '-' }} Â·
                        {{ $submission->ai_reviewed_at?->diffForHumans() }}
                    </p>
                    <p class="mt-2 rounded-lg bg-white/5 p-2 text-[11px] leading-relaxed text-white/50">
                    AI menilai <strong>jawaban uraian</strong> siswa. Periksa sendiri
                    apakah jawabannya masuk akal sebelum menekan Lulus.

                    </p>
                @else
                    <p class="mt-3 text-sm text-white/50">
                        {{ $submission->ai_feedback ?: 'Jawaban ini belum dinilai AI.' }}
                    </p>
                    <p class="mt-2 text-[11px] text-white/35">
                        Aktifkan dengan mengisi <span class="font-mono">AI_API_KEY</span> di file
                        <span class="font-mono">.env</span>.
                    </p>
                @endif
            </div>

            {{-- Indikator keaslian jawaban --}}
            @if ($submission->hasAuthenticity())
                <div class="panel p-5
                            {{ $submission->needsAuthenticityCheck() ? 'border-amber-400/40' : 'border-white/10' }}">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-white/70">
                            🔍 Indikator Keaslian
                        </h2>
                        <span class="badge {{ $submission->authenticityColor() }}">
                            {{ $submission->authenticity_score }}/5 · {{ $submission->authenticityLabel() }}
                        </span>
                    </div>

                    {{-- Bar tingkat keaslian --}}
                    <div class="mt-3 flex gap-1">
                        @for ($i = 1; $i <= 5; $i++)
                            <div class="h-1.5 flex-1 rounded-full
                                        {{ $i <= $submission->authenticity_score
                                            ? ($submission->needsAuthenticityCheck() ? 'bg-rose-400/70' : 'bg-emerald-400/60')
                                            : 'bg-white/10' }}"></div>
                        @endfor
                    </div>

                    @if ($submission->authenticity_note)
                        <p class="mt-3 text-xs leading-relaxed text-white/70">
                            {{ $submission->authenticity_note }}
                        </p>
                    @endif

                    @if ($submission->needsAuthenticityCheck())
                        <p class="mt-3 rounded-lg border border-amber-400/30 bg-amber-500/10 p-2.5 text-[11px] leading-relaxed text-amber-100/90">
                            <strong>Saran:</strong> tanyakan lisan ke anggota kelompok tentang
                            bagian ini. Ini hanya <strong>sinyal</strong>, bukan bukti — jangan
                            langsung menuduh.
                        </p>
                    @else
                        <p class="mt-3 text-[11px] leading-relaxed text-white/40">
                            Indikator ini hanya membantu Anda memilih kiriman mana yang perlu
                            ditanya lisan. Tulisan rapi bukan berarti hasil salinan.
                        </p>
                    @endif

                    {{-- Bonus usaha: bahasa sendiri --}}
                    @if ($submission->own_words === true)
                        <div class="mt-3 rounded-lg border border-emerald-400/30 bg-emerald-500/10 p-2.5">
                            <p class="text-[11px] font-bold text-emerald-200">
                                💪 BONUS USAHA: +{{ (int) $submission->own_words_bonus }} XP
                            </p>
                            <p class="mt-1 text-[11px] leading-relaxed text-emerald-100/80">
                                Siswa menjawab dengan bahasanya sendiri. Bonus ini diberikan
                                meskipun isi jawabannya kurang tepat, untuk menghargai usaha.
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Info kelompok --}}
            <div class="panel p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-white/70">Informasi</h2>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-white/50">Kelompok</dt>
                        <dd class="font-semibold">{{ $submission->team->name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-white/50">Sesi</dt>
                        <dd class="truncate text-right text-white/80">
                            {{ $submission->team->gameSession->name ?? '-' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-white/50">Misi</dt>
                        <dd class="truncate text-right text-white/80">
                            {{ $submission->mission->title ?? '-' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-white/50">Petunjuk dipakai</dt>
                        <dd class="font-semibold text-gold">{{ $progress->hints_used ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-t border-white/10 pt-2">
                        <dt class="text-white/50">XP jika lulus</dt>
                        <dd class="font-bold text-cyan-accent">+{{ $previewXp }} XP</dd>
                    </div>
                </dl>

                @if ($progress)
                    <div class="mt-3">
                        <x-status-badge :status="$progress->status" />
                    </div>
                @endif
            </div>

            {{-- Keputusan guru --}}
            @if ($submission->status === \App\Models\Submission::STATUS_WAITING)
                <div class="panel p-5">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-white/70">Keputusan</h2>

                    {{-- Lulus --}}
                    <form method="POST"
                          action="{{ route('teacher.validations.approve', $submission) }}"
                          class="space-y-3">
                        @csrf
                        <div>
                            <label for="approve-comment" class="label-field">Catatan (opsional)</label>
                            <textarea id="approve-comment"
                                      name="teacher_comment"
                                      rows="2"
                                      class="input-field resize-y"
                                      placeholder="Contoh: Format sudah rapi, pekerjaan baik."></textarea>
                        </div>
                        <button type="submit" class="btn-success w-full">
                            âœ“ Lulus
                        </button>
                    </form>

                    <div class="my-4 flex items-center gap-3">
                        <span class="h-px flex-1 bg-white/10"></span>
                        <span class="text-xs text-white/40">atau</span>
                        <span class="h-px flex-1 bg-white/10"></span>
                    </div>

                    {{-- Perlu perbaikan --}}
                    <form method="POST"
                          action="{{ route('teacher.validations.revision', $submission) }}"
                          class="space-y-3">
                        @csrf
                        <div>
                            <label for="revision-comment" class="label-field">
                                Catatan Perbaikan <span class="text-rose-300">*</span>
                            </label>
                            <textarea id="revision-comment"
                                      name="teacher_comment"
                                      rows="3"
                                      required
                                      class="input-field resize-y @error('teacher_comment') border-rose-400/60 @enderror"
                                      placeholder="Contoh: Jawaban nomor 2 masih kurang tepat, tolong jelaskan ulang dengan istilah yang benar."></textarea>
                            @error('teacher_comment')
                                <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn-danger w-full">
                            âœ— Perlu Perbaikan
                        </button>
                    </form>

                    <p class="mt-3 text-center text-[11px] text-white/40">
                        Menekan Lulus akan menambah XP dan membuka misi berikutnya.
                    </p>
                </div>
            @elseif ($submission->status === \App\Models\Submission::STATUS_APPROVED)
                <div class="panel border-emerald-400/20 p-5 text-center">
                    <p class="text-3xl">âœ…</p>
                    <p class="mt-2 text-sm font-semibold text-emerald-200">Sudah dinyatakan LULUS</p>
                    <p class="mt-1 text-xs text-white/50">
                        XP telah ditambahkan dan misi berikutnya terbuka.
                    </p>
                    <a href="{{ route('teacher.teams.show', $submission->team) }}"
                       class="btn-secondary mt-4 w-full">Lihat Progres Kelompok</a>
                </div>
            @else
                <div class="panel border-rose-400/20 p-5 text-center">
                    <p class="text-3xl">âœ—</p>
                    <p class="mt-2 text-sm font-semibold text-rose-200">Menunggu perbaikan siswa</p>
                    <p class="mt-1 text-xs text-white/50">
                        Siswa dapat mengirim ulang jawaban pada misi ini.
                    </p>
                </div>
            @endif
        </div>
    </div>

@endsection
