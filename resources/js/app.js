/*
|--------------------------------------------------------------------------
| TIK Mission — Skrip ringan untuk UI
|--------------------------------------------------------------------------
| Berisi: toast notification, sound toggle, timer countdown, dan dialog
| konfirmasi sederhana. Ditulis vanilla JS agar ringan di komputer lab.
*/

(function () {
    'use strict';

    // -----------------------------------------------------------------
    // Preferensi suara (default OFF sesuai spesifikasi)
    // -----------------------------------------------------------------
    const SOUND_KEY = 'tik-sound-enabled';

    window.TikSound = {
        isEnabled() {
            return localStorage.getItem(SOUND_KEY) === 'on';
        },
        setEnabled(enabled) {
            localStorage.setItem(SOUND_KEY, enabled ? 'on' : 'off');
            document.dispatchEvent(
                new CustomEvent('tik:sound-changed', { detail: { enabled } })
            );
        },
        toggle() {
            const next = !this.isEnabled();
            this.setEnabled(next);
            return next;
        },
        /**
         * Mainkan bunyi pendek memakai Web Audio API (tanpa file audio).
         */
        play(type) {
            if (!this.isEnabled()) {
                return;
            }

            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;

                const ctx = new AudioCtx();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                // Nada berbeda untuk setiap jenis notifikasi.
                const tones = {
                    click: [520, 0.06],
                    success: [740, 0.14],
                    complete: [880, 0.22],
                    error: [220, 0.2],
                };

                const [freq, duration] = tones[type] || tones.click;

                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.08, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(
                    0.0001,
                    ctx.currentTime + duration
                );

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + duration);

                osc.onended = () => ctx.close();
            } catch (e) {
                // Audio bersifat opsional; abaikan jika browser memblokir.
            }
        },
    };

    // -----------------------------------------------------------------
    // Toast notification
    // -----------------------------------------------------------------
    window.TikToast = function (message, type = 'info') {
        const container = document.getElementById('tik-toast-container');
        if (!container) return;

        const styles = {
            success: {
                border: 'border-emerald-400/40',
                bg: 'bg-emerald-500/10',
                text: 'text-emerald-200',
                icon: '✓',
            },
            error: {
                border: 'border-rose-400/40',
                bg: 'bg-rose-500/10',
                text: 'text-rose-200',
                icon: '✕',
            },
            warning: {
                border: 'border-amber-400/40',
                bg: 'bg-amber-500/10',
                text: 'text-amber-200',
                icon: '⚠',
            },
            info: {
                border: 'border-cyan-400/40',
                bg: 'bg-cyan-500/10',
                text: 'text-cyan-200',
                icon: 'i',
            },
        };

        const s = styles[type] || styles.info;

        const el = document.createElement('div');
        el.className = `animate-fade-up flex items-start gap-3 rounded-lg border ${s.border} ${s.bg} px-4 py-3 shadow-lg backdrop-blur-sm`;
        el.setAttribute('role', 'status');
        el.innerHTML = `
            <span class="mt-0.5 text-sm font-bold ${s.text}">${s.icon}</span>
            <span class="text-sm text-white/90">${message}</span>
        `;

        container.appendChild(el);

        // Bunyi mengikuti jenis toast.
        if (type === 'success') window.TikSound.play('success');
        else if (type === 'error') window.TikSound.play('error');

        setTimeout(() => {
            el.style.transition = 'opacity 0.3s, transform 0.3s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-6px)';
            setTimeout(() => el.remove(), 300);
        }, 3600);
    };

    // -----------------------------------------------------------------
    // Timer countdown
    // -----------------------------------------------------------------
    function initTimers() {
        document.querySelectorAll('[data-timer]').forEach((el) => {
            const remaining = parseInt(el.dataset.timer, 10);
            if (isNaN(remaining)) return;

            let seconds = remaining;

            const render = () => {
                const h = Math.floor(seconds / 3600);
                const m = Math.floor((seconds % 3600) / 60);
                const s = seconds % 60;

                el.textContent =
                    h > 0
                        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
                        : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            };

            render();

            if (seconds <= 0) return;

            const interval = setInterval(() => {
                seconds -= 1;
                if (seconds <= 0) {
                    seconds = 0;
                    render();
                    clearInterval(interval);
                    // Waktu habis: muat ulang agar status terkunci berlaku.
                    window.TikToast('Waktu sesi telah habis.', 'warning');
                    setTimeout(() => window.location.reload(), 1500);
                    return;
                }
                render();

                // Ubah warna saat 5 menit terakhir.
                if (seconds <= 300) {
                    el.classList.add('text-rose-300');
                }
            }, 1000);
        });
    }

    // -----------------------------------------------------------------
    // Dialog konfirmasi sederhana
    // -----------------------------------------------------------------
    function initConfirm() {
        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                if (form.dataset.confirmed === 'yes') return;
                if (!window.confirm(form.dataset.confirm)) {
                    e.preventDefault();
                } else {
                    form.dataset.confirmed = 'yes';
                }
            });
        });
    }

    // -----------------------------------------------------------------
    // Tombol Sound ON/OFF (tersimpan di localStorage)
    // -----------------------------------------------------------------
    function initSoundToggle() {
        const buttons = document.querySelectorAll('[data-sound-toggle]');

        const sync = () => {
            const on = window.TikSound.isEnabled();
            buttons.forEach((btn) => {
                btn.textContent = on ? '🔊 Sound ON' : '🔇 Sound OFF';
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        };

        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const on = window.TikSound.toggle();
                sync();
                if (on) window.TikSound.play('click');
                window.TikToast(
                    on ? 'Suara diaktifkan.' : 'Suara dimatikan.',
                    'info'
                );
            });
        });

        document.addEventListener('tik:sound-changed', sync);
        sync();
    }

    // -----------------------------------------------------------------
    // Kirim form yang punya data-loading → nonaktifkan tombol agar
    // siswa tidak mengirim dua kali.
    // -----------------------------------------------------------------
    function initSubmitGuard() {
        document.querySelectorAll('form[data-guard]').forEach((form) => {
            form.addEventListener('submit', () => {
                const btn = form.querySelector('[type="submit"]');
                if (!btn) return;
                btn.disabled = true;
                btn.dataset.originalText = btn.textContent;
                btn.textContent = 'Mengirim…';
            });
        });
    }

    // -----------------------------------------------------------------
    // Tombol layar penuh (dipakai di layar proyektor guru).
    // -----------------------------------------------------------------
    function initFullscreen() {
        document.querySelectorAll('[data-screen-fullscreen]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = document.getElementById('tik-screen') || document.documentElement;

                if (document.fullscreenElement) {
                    document.exitFullscreen?.();
                    return;
                }

                target.requestFullscreen?.().catch(() => {
                    window.TikToast('Browser menolak mode layar penuh. Tekan F11.', 'warning');
                });
            });
        });
    }

    // -----------------------------------------------------------------
    // Kebocoran kode: klik kanan & inspect pada kartu misi terkunci
    // tidak diperlukan karena kode memang tidak pernah dikirim ke klien.
    // -----------------------------------------------------------------

    // -----------------------------------------------------------------
    // Ringkasan game yang belum berhasil terkirim
    // -----------------------------------------------------------------
    // XP permainan baru diberikan setelah server menerima ringkasan ronde.
    // Bila jaringan mati, tab ditutup, atau guru menghentikan ronde tepat saat
    // anak masih bermain, ringkasannya disimpan di browser dan dikirim ulang
    // begitu halaman siswa dibuka lagi — supaya XP yang sudah dikumpulkan anak
    // tidak hilang.
    const PENDING_GAME_KEY = 'tik-game-pending-';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    /**
     * Kirim satu ringkasan permainan. Mengembalikan true bila server menerimanya.
     *
     * Yang dikirim hanya DAFTAR PILIHAN yang ditekan anak; server menilai
     * ulang memakai kunci jawaban misi, jadi skor tidak bisa dipalsukan dari
     * browser dan kiriman ganda tidak menggandakan XP.
     */
    async function kirimRingkasanGame(data) {
        try {
            const res = await fetch(data.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    jawaban: data.jawaban || [],
                    _token: csrfToken(),
                }),
            });

            return res.ok;
        } catch (e) {
            return false;
        }
    }

    window.TikPendingGame = {
        /** Simpan ringkasan yang gagal terkirim, dibedakan per misi. */
        save(missionId, data) {
            try {
                localStorage.setItem(PENDING_GAME_KEY + missionId, JSON.stringify(data));
            } catch (e) {
                // localStorage bisa diblokir; abaikan saja.
            }
        },

        clear(missionId) {
            try {
                localStorage.removeItem(PENDING_GAME_KEY + missionId);
            } catch (e) {
                // Diabaikan.
            }
        },

        /**
         * Kirim ulang semua ringkasan yang tertunda.
         *
         * Aman dipanggil berkali-kali: server menghitung XP dari daftar
         * jawaban yang dikirim (bukan menambahkannya), jadi kiriman ganda tidak
         * menggandakan XP.
         */
        flush() {
            const keys = [];

            try {
                for (let i = 0; i < localStorage.length; i += 1) {
                    const key = localStorage.key(i);
                    if (key && key.indexOf(PENDING_GAME_KEY) === 0) keys.push(key);
                }
            } catch (e) {
                return;
            }

            keys.forEach((key) => {
                let data = null;

                try {
                    data = JSON.parse(localStorage.getItem(key));
                } catch (e) {
                    data = null;
                }

                // Data rusak / tanpa alamat tujuan: buang supaya tidak menumpuk.
                if (!data || !data.url) {
                    try { localStorage.removeItem(key); } catch (e) { /* diabaikan */ }
                    return;
                }

                kirimRingkasanGame(data).then((ok) => {
                    if (!ok) return; // biarkan tersimpan untuk dicoba lagi nanti

                    try { localStorage.removeItem(key); } catch (e) { /* diabaikan */ }
                });
            });
        },
    };

    document.addEventListener('DOMContentLoaded', () => {
        initTimers();
        initConfirm();
        initSoundToggle();
        initSubmitGuard();
        initFullscreen();

        // Kirim ulang ringkasan permainan yang dulu gagal terkirim.
        window.TikPendingGame.flush();

        // Tampilkan flash message dari server sebagai toast.
        const flash = document.getElementById('tik-flash');
        if (flash) {
            const messages = JSON.parse(flash.textContent || '[]');
            messages.forEach((m) => window.TikToast(m.message, m.type));
        }
    });
})();
