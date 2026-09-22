{{--
    Tampilan read-only bukti yang sudah dikirim (untuk misi yang sudah selesai).
--}}
@if (! $submission)
    <p class="text-sm text-white/50">Tidak ada data kiriman.</p>
@else
    <div class="space-y-4">
        {{-- Status --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge {{ $submission->status === \App\Models\Submission::STATUS_APPROVED
                ? 'bg-emerald-500/15 text-emerald-300'
                : 'bg-white/5 text-white/60' }}">
                {{ $submission->statusLabel() }}
            </span>
            @if ($submission->submitted_at)
                <span class="text-xs text-white/40">
                    Dikirim {{ $submission->submitted_at->format('d/m/Y H:i') }}
                </span>
            @endif
        </div>

        {{-- Komentar guru --}}
        @if ($submission->teacher_comment)
            <div class="rounded-lg border border-cyan-accent/25 bg-cyan-accent/5 p-3">
                <p class="text-xs font-semibold text-cyan-accent">Catatan Guru</p>
                <p class="mt-1 text-sm text-white/80">{{ $submission->teacher_comment }}</p>
            </div>
        @endif

        {{-- Jawaban --}}
        @if ($submission->answer)
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-white/50">Jawabanmu</p>
                <p class="rounded-lg border border-white/10 bg-navy-900/60 p-3 text-sm leading-relaxed text-white/80">
                    {{ $submission->answer }}
                </p>
            </div>
        @endif

        {{-- Bukti --}}
        @if ($submission->evidence_path)
            <div>
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">Bukti Screenshot</p>
                @if ($submission->evidenceIsImage())
                    <a href="{{ $submission->evidenceUrl() }}" target="_blank" class="block">
                        <img src="{{ $submission->evidenceUrl() }}"
                             alt="Bukti screenshot"
                             class="max-h-80 w-full rounded-lg border border-white/10 object-contain bg-navy-900/60">
                    </a>
                @else
                    <a href="{{ $submission->evidenceUrl() }}"
                       target="_blank"
                       class="flex items-center gap-2 rounded-lg border border-white/10 bg-navy-900/60 p-3 text-sm text-cyan-accent hover:underline">
                        📄 {{ $submission->evidenceOriginalName() }}
                    </a>
                @endif
            </div>
        @endif

        {{-- File tambahan --}}
        @if ($submission->file_path)
            <div>
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">File Tambahan</p>
                <a href="{{ $submission->fileUrl() }}"
                   target="_blank"
                   class="flex items-center gap-2 rounded-lg border border-white/10 bg-navy-900/60 p-3 text-sm text-cyan-accent hover:underline">
                    📄 {{ $submission->fileOriginalName() }}
                </a>
            </div>
        @endif
    </div>
@endif
