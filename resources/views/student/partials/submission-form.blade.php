{{--
    Form jawaban siswa (mode materi penuh).
    Siswa menjawab daftar pertanyaan uraian dalam satu kolom jawaban besar,
    diberi penomoran agar AI mudah menilai per pertanyaan. Tanpa unggah file.
    Tombol submit berada di luar form (di kolom kanan) memakai atribut form="submission-form".
--}}
@php
    $questions = $mission->questionList();
@endphp

<form id="submission-form"
      method="POST"
      action="{{ route('student.mission.submit', $mission) }}"
      class="space-y-4"
      data-guard>
    @csrf

    {{-- Daftar pertanyaan yang harus dijawab --}}
    @if (count($questions) > 0)
        <div class="rounded-xl border border-cyan-accent/20 bg-cyan-strong/5 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-accent">
                Pertanyaan ({{ count($questions) }})
            </p>
            <ol class="mt-2 space-y-2 text-sm text-white/85">
                @foreach ($questions as $i => $q)
                    <li class="flex gap-2">
                        <span class="shrink-0 font-bold text-cyan-accent">{{ $i + 1 }}.</span>
                        <span>
                            {{ $q['pertanyaan'] }}
                            @if (! empty($q['petunjuk']))
                                <span class="mt-0.5 block text-xs text-white/45">
                                    Petunjuk: {{ $q['petunjuk'] }}
                                </span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Kolom jawaban --}}
    <div>
        <label for="answer" class="label-field">
            Jawabanmu <span class="text-rose-300">*</span>
        </label>
        <textarea id="answer"
                  name="answer"
                  rows="10"
                  class="input-field resize-y"
                  required
                  placeholder="Tulis jawabanmu berurutan sesuai nomor pertanyaan, misalnya:&#10;1. ...&#10;2. ...&#10;3. ...">{{ old('answer', $submission->answer ?? '') }}</textarea>

        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-white/40">
                Jawab berurutan sesuai nomor. Gunakan bahasamu sendiri minimal beberapa kalimat.
            </p>
            <p class="text-xs text-white/40">
                <span id="answer-count">0</span> karakter
            </p>
        </div>

        @error('answer')
            <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
        @enderror
    </div>

    {{-- Lampiran opsional (tidak wajib) --}}
    <details class="rounded-lg border border-white/10 bg-navy-900/40 p-3">
        <summary class="cursor-pointer text-xs font-semibold text-white/60">
            Lampiran opsional (tidak wajib)
        </summary>
        <div class="mt-3 space-y-3">
            <div>
                <label for="evidence" class="label-field">Gambar / Screenshot</label>
                <input id="evidence"
                       type="file"
                       name="evidence"
                       accept=".jpg,.jpeg,.png,.webp,.pdf,.docx"
                       class="block w-full cursor-pointer rounded-lg border border-white/15 bg-navy-900/70 text-sm text-white/70
                              file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-cyan-strong/20
                              file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-cyan-accent
                              hover:file:bg-cyan-strong/30">
                <p class="mt-1 text-xs text-white/40">Boleh dikosongkan. Maksimal 10 MB.</p>
                @error('evidence')
                    <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </details>

    {{-- Tombol submit --}}
    <div class="flex flex-col gap-2 border-t border-white/10 pt-4 sm:flex-row">
        <button type="submit" class="btn-primary flex-1 py-3">
            {{ $submission ? 'Kirim Ulang Jawaban' : 'Kirim Jawaban' }}
        </button>
    </div>

    <p class="text-center text-[11px] text-white/40">
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
