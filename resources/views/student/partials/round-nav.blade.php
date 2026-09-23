{{--
    Pemilih ronde untuk halaman siswa.

    Anak-anak mudah tersesat setelah satu ronde selesai: kartu misi bercampur
    dengan ronde yang masih terkunci dan halaman ronde tidak punya tombol
    pindah. Bilah ini selalu ada di header (menempel saat halaman digulir) dan
    hanya berisi ronde yang MEMANG sedang dibuka guru — termasuk ronde yang
    belum mereka sentuh, sehingga mereka tahu apa saja yang bisa dikerjakan.

    - $roundNav      : hasil MissionService::roundChoices()
    - $currentMission: misi yang sedang dibuka (null di dashboard/halaman tunggu)
    - $autoOpen      : buka daftar langsung (dipakai di dashboard)
--}}
@php
    $roundNav = $roundNav ?? collect();
    $currentMissionId = isset($currentMission) ? $currentMission?->id : null;
    $autoOpen = $autoOpen ?? request()->routeIs('student.dashboard');
    $orders = $roundNav->pluck('order')->map(fn ($order) => (int) $order)->implode(', ');
@endphp

@if ($roundNav->isNotEmpty())
    <nav data-round-nav
         data-round-nav-orders="{{ $roundNav->pluck('order')->map(fn ($order) => (int) $order)->implode(',') }}"
         data-current-mission="{{ $currentMissionId ?? 0 }}"
         class="mt-3 rounded-2xl border-2 border-sky-200 bg-white px-3 py-2 shadow-sm">

        <details @if ($autoOpen) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                <span class="flex min-w-0 items-center gap-2 text-xs font-bold text-ink-700">
                    <span class="text-base">🎯</span>
                    <span class="min-w-0 truncate">
                        Ronde yang bisa dikerjakan:
                        <strong class="text-sky-600">{{ $orders }}</strong>
                    </span>
                    <span data-round-nav-new class="badge hidden bg-mint-400/25 text-mint-600">RONDE BARU</span>
                </span>

                <span class="shrink-0 rounded-xl border-2 border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-black text-sky-600">
                    Pindah Ronde
                </span>
            </summary>

            <ul data-round-nav-list class="mt-2 space-y-1.5">
                @foreach ($roundNav as $item)
                    @php $isCurrent = $currentMissionId !== null && (int) $item['id'] === (int) $currentMissionId; @endphp

                    <li>
                        @if ($isCurrent)
                            {{-- Ronde yang sedang dibuka: penanda, bukan tautan. --}}
                            <div class="flex items-center justify-between gap-3 rounded-2xl border-2 border-sky-400 bg-sky-50 px-3 py-2">
                                <span class="min-w-0 truncate text-xs font-bold text-ink-800">
                                    Ronde {{ $item['order'] }}: {{ $item['title'] }}
                                </span>
                                <span class="shrink-0 text-[11px] font-black text-sky-600">SEKARANG DI SINI</span>
                            </div>
                        @elseif ($item['is_locked'])
                            <div class="flex items-center justify-between gap-3 rounded-2xl border-2 border-sky-100 bg-sky-50/70 px-3 py-2 opacity-80">
                                <span class="min-w-0 truncate text-xs font-bold text-ink-500">
                                    Ronde {{ $item['order'] }}: {{ $item['title'] }}
                                </span>
                                <span class="shrink-0 text-[11px] font-black text-ink-500">🔒 TERKUNCI</span>
                            </div>
                        @else
                            <a href="{{ $item['url'] }}"
                               class="flex items-center justify-between gap-3 rounded-2xl border-2 border-sky-100 bg-white px-3 py-2 transition hover:border-sky-400 hover:bg-sky-50">
                                <span class="min-w-0 truncate text-xs font-bold text-ink-800">
                                    Ronde {{ $item['order'] }}: {{ $item['title'] }}
                                    @if ($item['is_game']) 🎮 @endif
                                </span>
                                <span class="shrink-0 text-[11px] font-black text-sky-600">
                                    @if ($item['is_done']) ✓ Selesai
                                    @elseif ($item['is_submitted']) ⏳ Terkirim
                                    @else Kerjakan →
                                    @endif
                                </span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>

            <p class="mt-2 text-[11px] font-semibold text-ink-500">
                Kamu bebas berpindah ke ronde mana pun di daftar ini, kapan saja.
                Ronde berikutnya akan muncul di sini begitu guru membukanya.
            </p>
        </details>
    </nav>
@endif
