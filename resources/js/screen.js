/*
|--------------------------------------------------------------------------
| TIK Mission — Layar Proyektor (live)
|--------------------------------------------------------------------------
| Memuat data skor/ronde/timer dari endpoint JSON guru setiap beberapa detik,
| sehingga layar TV/proyektor selalu menampilkan kondisi permainan terbaru
| tanpa perlu guru me-refresh. Ditulis vanilla JS agar ringan di komputer lab.
*/

(function () {
    'use strict';

    const POLL_MS = 4000;

    function initScreen() {
        const root = document.querySelector('[data-screen-root]');
        if (!root) return;

        const url = root.dataset.liveUrl;
        if (!url) return;

        const el = (id) => document.getElementById(id);

        let lastTopTeam = null;
        let lastRoundKey = null;
        let countdownTimer = null;
        let remaining = null;

        // ---------------------------------------------------------------
        // Timer ronde: hitung lokal tiap detik agar tidak berkedip.
        // ---------------------------------------------------------------
        function renderCountdown() {
            const timerEl = el('scr-timer');
            if (!timerEl) return;

            if (remaining === null) {
                timerEl.textContent = '--:--';
                timerEl.classList.remove('text-rose-300');
                return;
            }

            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            timerEl.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            timerEl.classList.toggle('text-rose-300', remaining <= 60);
        }

        function startCountdown(seconds) {
            if (countdownTimer) clearInterval(countdownTimer);

            remaining = seconds === null ? null : Math.max(0, parseInt(seconds, 10));
            renderCountdown();

            if (remaining === null || remaining <= 0) return;

            countdownTimer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    remaining = 0;
                    renderCountdown();
                    clearInterval(countdownTimer);
                    // Waktu ronde habis: beri tahu kelas lewat suara & toast.
                    window.TikSound.play('error');
                    window.TikToast('Waktu ronde habis!', 'warning');
                    return;
                }
                renderCountdown();
            }, 1000);
        }

        // ---------------------------------------------------------------
        // Papan skor
        // ---------------------------------------------------------------
        function renderBoard(teams) {
            const board = el('scr-board');
            if (!board) return;

            if (!teams || teams.length === 0) {
                board.innerHTML = `
                    <p class="py-10 text-center text-lg text-white/40">
                        Belum ada kelompok yang bergabung. Bagikan kode sesi kepada siswa.
                    </p>`;
                return;
            }

            const medals = ['🥇', '🥈', '🥉'];

            // Bangun ulang isi papan skor.
            const html = teams
                .map((t, i) => {
                    const rank = medals[i] || `<span class="text-white/40">${i + 1}</span>`;
                    const isFinished = t.finished
                        ? '<span class="badge bg-emerald-500/15 text-emerald-300">SELESAI</span>'
                        : (t.current_mission
                            ? `<span class="text-xs text-cyan-accent">${t.current_mission}</span>`
                            : '');

                    const pct = Math.max(0, Math.min(100, t.percent || 0));

                    return `
                        <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-navy-900/60 px-5 py-4">
                            <div class="absolute inset-y-0 left-0 bg-cyan-strong/10" style="width:${pct}%"></div>
                            <div class="relative flex items-center gap-4">
                                <span class="w-9 shrink-0 text-center text-2xl font-black">${rank}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xl font-bold text-white sm:text-2xl">${escapeHtml(t.name)}</p>
                                    <p class="text-xs text-white/40">
                                        ${t.members} anggota · ${t.completed}/${t.total} misi · ${t.hints} petunjuk
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="font-mono text-3xl font-black text-gold tabular-nums sm:text-4xl">${t.xp}</p>
                                    <p class="text-[11px] uppercase tracking-wider text-white/40">XP</p>
                                </div>
                                <div class="hidden w-28 shrink-0 text-right sm:block">${isFinished}</div>
                            </div>
                        </div>`;
                })
                .join('');

            board.innerHTML = html;

            // Suara saat ada kelompok baru memimpin papan skor.
            const topTeam = teams[0];
            if (topTeam && lastTopTeam !== null && topTeam.id !== lastTopTeam) {
                window.TikSound.play('success');
                window.TikToast(`${topTeam.name} memimpin papan skor!`, 'info');
            }
            lastTopTeam = topTeam ? topTeam.id : null;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        // ---------------------------------------------------------------
        // Status ronde
        // ---------------------------------------------------------------
        // Daftar ronde yang sedang terbuka (dipakai bila guru membuka beberapa
        // ronde sekaligus). Dibuat lewat DOM node supaya judul misi aman.
        function renderOpenRounds(list) {
            const box = el('scr-open-rounds');
            const listEl = el('scr-open-rounds-list');

            if (!box || !listEl) return;

            listEl.innerHTML = '';

            if (!list || list.length <= 1) {
                box.classList.add('hidden');
                return;
            }

            box.classList.remove('hidden');

            list.forEach((r) => {
                const item = document.createElement('div');
                item.className = 'flex items-center gap-3 rounded-xl border border-cyan-accent/20 bg-cyan-strong/5 px-4 py-3';

                const order = document.createElement('span');
                order.className = 'font-mono text-lg font-black text-cyan-accent';
                order.textContent = String(r.order);

                const title = document.createElement('span');
                title.className = 'min-w-0 flex-1 truncate text-sm font-bold text-white';
                title.textContent = r.title || 'Misi tanpa judul';

                item.appendChild(order);
                item.appendChild(title);

                if (r.is_game) {
                    const badge = document.createElement('span');
                    badge.className = 'badge shrink-0 bg-grape-400/20 text-grape-400 text-[10px]';
                    badge.textContent = '🎮 GAME';
                    item.appendChild(badge);
                }

                listEl.appendChild(item);
            });
        }

        function renderRound(data) {
            const round = data.round || {};
            const openRounds = round.open_rounds || [];
            const multi = openRounds.length > 1;

            const roundEl = el('scr-round');
            if (roundEl) {
                // Beberapa ronde terbuka: angka besar menjadi jumlah ronde yang
                // terbuka, bukan nomor satu ronde saja.
                roundEl.textContent = multi
                    ? openRounds.length
                    : (round.current > 0 ? round.current : '—');
            }

            const roundLabel = el('scr-round-label');
            if (roundLabel) {
                roundLabel.textContent = multi ? 'Ronde Terbuka' : 'Ronde';
            }

            renderOpenRounds(openRounds);

            const timerBox = el('scr-timer-box');
            const timerNote = el('scr-timer-note');
            const badge = el('scr-round-badge');

            // Timer kelas hanya bermakna bila tepat satu ronde dibuka.
            if (timerBox) timerBox.classList.toggle('hidden', multi);
            if (timerNote) timerNote.classList.toggle('hidden', !multi);

            if (badge) {
                const map = {
                    idle: ['MENUNGGU', 'bg-white/5 text-white/60'],
                    running: ['RONDE BERJALAN', 'bg-emerald-500/20 text-emerald-200'],
                    ended: ['RONDE SELESAI', 'bg-amber-500/15 text-amber-200'],
                };
                const [label, cls] = map[round.status] || map.idle;
                badge.textContent = round.is_time_up ? 'WAKTU HABIS' : label;
                badge.className = `badge px-4 py-2 text-base ${round.is_time_up ? 'bg-rose-500/15 text-rose-200' : cls}`;
            }

            if (timerBox) {
                timerBox.classList.toggle('border-rose-400/40', !!round.is_time_up);
            }

            // Misi ronde ini.
            const card = el('scr-mission-card');
            const title = el('scr-mission-title');
            const objective = el('scr-mission-objective');
            const missionLine = el('scr-mission');

            if (card && title && round.mission_title) {
                card.classList.remove('hidden');
                title.textContent = round.mission_title;
                objective.textContent = round.mission_objective || '';
            }

            if (missionLine) {
                missionLine.textContent = round.mission_title
                    ? (round.is_running ? 'Misi sedang berjalan' : 'Misi ronde ini')
                    : 'Menunggu dimulai';
            }

            // Timer: mulai ulang hanya bila ronde/status berubah, agar hitungan
            // lokal yang halus tidak dipotong setiap polling.
            const key = `${round.current}-${round.status}-${round.duration_minutes}-${openRounds.map((r) => r.order).join('.')}`;
            if (key !== lastRoundKey) {
                lastRoundKey = key;
                startCountdown(round.is_running && !multi ? round.seconds_remaining : null);
            } else if (round.is_running && !multi && remaining !== null && round.seconds_remaining !== null) {
                // Sinkronkan kembali bila selisih melebihi 2 detik (mis. laptop sleep).
                if (Math.abs(round.seconds_remaining - remaining) > 2) {
                    startCountdown(round.seconds_remaining);
                }
            }
        }

        // ---------------------------------------------------------------
        // Ambil data & jadwalkan polling berikutnya
        // ---------------------------------------------------------------
        async function poll() {
            try {
                const res = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);

                const data = await res.json();

                renderRound(data);
                renderBoard(data.teams);

                const countEl = el('scr-team-count');
                if (countEl) countEl.textContent = data.stats?.teams ?? 0;

                const xpEl = el('scr-total-xp');
                if (xpEl) xpEl.textContent = data.stats?.total_xp ?? 0;

                const aiEl = el('scr-ai-count');
                if (aiEl) aiEl.textContent = data.stats?.scored_by_ai ?? 0;

                const indicator = document.querySelector('[data-ai-indicator]');
                if (indicator) {
                    indicator.textContent = data.ai_active
                        ? `AI aktif · ${data.stats?.scored_by_ai ?? 0} jawaban dinilai`
                        : 'AI nonaktif (isi API key di .env)';
                    indicator.className = data.ai_active
                        ? 'text-emerald-300/80'
                        : 'text-amber-300/80';
                }
            } catch (e) {
                // Jaringan sempat terputus: tampilkan status, lalu coba lagi.
                const indicator = document.querySelector('[data-ai-indicator]');
                if (indicator) indicator.textContent = 'Menyambung ulang…';
            } finally {
                setTimeout(poll, POLL_MS);
            }
        }

        poll();
    }

    document.addEventListener('DOMContentLoaded', initScreen);
})();
