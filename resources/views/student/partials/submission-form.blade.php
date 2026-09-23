{{--
    Form jawaban siswa (mode materi penuh).
    Siswa menjawab daftar pertanyaan uraian dalam satu kolom jawaban besar,
    diberi penomoran agar AI mudah menilai per pertanyaan. Tanpa unggah wajib.
    Tombol submit berada di dalam form ini.
--}}
@php
    $questions = $mission->questionList();

    // Label & warna per jenis soal agar anak tahu bentuk soalnya.
    $jenisLabel = [
        'materi' => ['label' => 'MATERI', 'class' => 'bg-sky-100 text-sky-700', 'icon' => '📖'],
        'pendapat' => ['label' => 'PENDAPAT', 'class' => 'bg-grape-400/15 text-grape-500', 'icon' => '💭'],
        'saran' => ['label' => 'SARAN', 'class' => 'bg-mint-400/15 text-mint-600', 'icon' => '💡'],
        'kasus' => ['label' => 'KASUS', 'class' => 'bg-sun-300/50 text-ink-800', 'icon' => '🧩'],
        'teka-teki' => ['label' => 'TEKA-TEKI', 'class' => 'bg-coral-400/15 text-coral-500', 'icon' => '🔍'],
        'pengalaman' => ['label' => 'PENGALAMAN', 'class' => 'bg-sky-100 text-sky-700', 'icon' => '🙋'],
    ];
@endphp

<form id="submission-form"
      method="POST"
      action="{{ route('student.mission.submit', $mission) }}"
      class="space-y-4"
      data-guard
      data-mission-id="{{ $mission->id }}"
      data-submitted="{{ $submission && $submission->status === \App\Models\Submission::STATUS_WAITING ? 'true' : 'false' }}">
    @csrf

    {{-- Daftar pertanyaan yang harus dijawab --}}
    @if (count($questions) > 0)
        <div class="rounded-3xl border-2 border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-black uppercase tracking-wider text-sky-600">
                Pertanyaan ({{ count($questions) }})
            </p>
            <ol class="mt-3 space-y-3">
                @foreach ($questions as $i => $q)
                    @php
                        $jenis = $q['jenis'] ?? null;
                        $meta = $jenisLabel[$jenis] ?? null;
                    @endphp
                    <li class="rounded-2xl border-2 border-sky-100 bg-white p-3">
                        <div class="flex items-start gap-2">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-sky-500 text-xs font-black text-white">
                                {{ $i + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                @if ($meta)
                                    <span class="badge {{ $meta['class'] }} mb-1.5">
                                        {{ $meta['icon'] }} {{ $meta['label'] }}
                                    </span>
                                @endif
                                <p class="text-sm font-semibold leading-relaxed text-ink-900">
                                    {{ $q['pertanyaan'] }}
                                </p>
                                @if (! empty($q['petunjuk']))
                                    <p class="mt-1 text-xs leading-relaxed text-ink-600">
                                        💬 {{ $q['petunjuk'] }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Kolom jawaban --}}
    <div>
        <label for="answer" class="label-field">
            Jawabanmu <span class="text-coral-500">*</span>
        </label>
        <textarea id="answer"
                  name="answer"
                  rows="10"
                  class="input-field resize-y"
                  required
                  placeholder="Tulis jawabanmu berurutan sesuai nomor soal, misalnya:&#10;1. ...&#10;2. ...">{{ old('answer', $submission?->essayAnswer() ?? '') }}</textarea>

        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold text-ink-600">
                Jawab berurutan sesuai nomor. Tulis dengan bahasamu sendiri ya!
            </p>
            <p class="text-xs font-bold text-ink-500">
                <span id="answer-count">0</span> karakter
            </p>
        </div>

        @error('answer')
            <p class="mt-1 text-xs font-bold text-coral-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- Lampiran opsional (tidak wajib) --}}
    <details class="rounded-2xl border-2 border-sky-100 bg-sky-50 p-3">
        <summary class="cursor-pointer text-xs font-bold text-ink-700">
            📎 Lampiran opsional (tidak wajib)
        </summary>
        <div class="mt-3 space-y-3">
            <div>
                <label for="evidence" class="label-field">Gambar / Screenshot</label>
                <input id="evidence"
                       type="file"
                       name="evidence"
                       accept=".jpg,.jpeg,.png,.webp,.pdf,.docx"
                       class="block w-full cursor-pointer rounded-2xl border-2 border-sky-200 bg-white text-sm text-ink-700
                              file:mr-3 file:cursor-pointer file:rounded-l-2xl file:border-0 file:bg-sky-100
                              file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-sky-700
                              hover:file:bg-sky-200">
                <p class="mt-1 text-xs font-semibold text-ink-600">Boleh dikosongkan. Maksimal 10 MB.</p>
                @error('evidence')
                    <p class="mt-1 text-xs font-bold text-coral-500">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </details>

    {{-- Tombol submit --}}
    <div class="flex flex-col gap-2 border-t-2 border-sky-100 pt-4 sm:flex-row">
        <button type="submit" id="submit-button" class="btn-primary flex-1 py-3.5 text-base">
            {{ $submission ? '🔁 Kirim Ulang Jawaban' : '🚀 Kirim Jawaban' }}
        </button>
    </div>

    <p class="text-center text-[11px] font-semibold text-ink-500">
        Jawabanmu akan dinilai otomatis oleh AI, lalu dapat disesuaikan oleh gurumu.
    </p>
</form>

<script>
    // Hitung karakter jawaban secara langsung agar siswa tahu panjang tulisannya.
    (function () {
        var area = document.getElementById('answer');
        var out = document.getElementById('answer-count');
        if (!area || !out) return;

        var update = function () { out.textContent = area.value.length; };
        area.addEventListener('input', update);
        update();
    })();
</script>
