/*
|--------------------------------------------------------------------------
| TIK Mission — Timer Ronde, Pemilih Ronde & Simpan Ketikan Siswa
|--------------------------------------------------------------------------
| Tiga tugas:
|   1. Menyimpan ketikan siswa di browser (localStorage) supaya tidak hilang
|      saat halaman berpindah ronde atau browser tertutup.
|   2. Menjaga bilah "Pindah Ronde" di header tetap akurat: begitu guru
|      membuka ronde baru, daftarnya ikut berubah tanpa perlu me-refresh.
|   3. Memberi tahu siswa bahwa ronde baru sudah dibuka. Anak yang sedang
|      menulis TIDAK diseret ke halaman lain — dia yang memutuskan kapan
|      pindah. Anak yang belum menulis apa-apa dipindahkan otomatis supaya
|      tidak tertinggal.

Timer per siswa: dihitung dari saat siswa membuka halaman ronde ini,
bukan dari saat guru membuka ronde.
*/

(function () {
    'use strict';

    // Kunci penyimpanan draf, dibedakan per misi.
    function draftKey(missionId) {
        return 'tik-draft-mission-' + missionId;
    }

    function initDraft() {
        const form = document.getElementById('submission-form');
        if (!form) return;

        const area = document.getElementById('answer');
        const missionId = form.dataset.missionId;

        if (!area || !missionId) return;

        // Kembalikan draf yang belum terkirim (bila ada).
        try {
            const saved = localStorage.getItem(draftKey(missionId));
            if (saved && !area.value) {
                area.value = saved;
                // Beri tahu siswa bahwa ketikannya dipulihkan.
                if (window.TikToast && saved.length > 0) {
                    window.TikToast('Ketikan sebelumnya dipulihkan.', 'info');
                }
                area.dispatchEvent(new Event('input'));
            }
        } catch (e) {
            // localStorage bisa diblokir; abaikan saja.
        }

        // Simpan draf setiap kali siswa mengetik.
        area.addEventListener('input', function () {
            try {
                if (area.value.trim() === '') {
                    localStorage.removeItem(draftKey(missionId));
                } else {
                    localStorage.setItem(draftKey(missionId), area.value);
                }
            } catch (e) {
                // Diabaikan.
            }
        });

        // Setelah terkirim sukses, hapus draf (ditandai oleh halaman).
        if (form.dataset.submitted === 'true') {
            try {
                localStorage.removeItem(draftKey(missionId));
            } catch (e) {
                // Diabaikan.
            }
        }
    }

    // -----------------------------------------------------------------
    // Timer per siswa
    // -----------------------------------------------------------------
    function initWorkTimer() {
        const el = document.getElementById('work-timer');
        if (!el) return;

        let remaining = parseInt(el.dataset.remaining || '', 10);

        if (isNaN(remaining)) {
            el.textContent = 'Tanpa batas';
            return;
        }

        function render() {
            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            el.classList.toggle('text-rose-300', remaining <= 60);
        }

        render();

        const tick = setInterval(function () {
            remaining -= 1;

            if (remaining <= 0) {
                remaining = 0;
                render();
                clearInterval(tick);

                // Waktu habis: nonaktifkan tombol kirim dan beri tahu siswa.
                document.getElementById('submit-button')?.setAttribute('disabled', 'disabled');
                window.TikSound?.play('error');
                window.TikToast?.('Waktu pengerjaanmu habis. Ketikanmu masih tersimpan.', 'warning');

                return;
            }

            render();
        }, 1000);
    }

    // -----------------------------------------------------------------
    // Bilah "Pindah Ronde" di header
    // -----------------------------------------------------------------
    // Isi awalnya dirender server. Fungsi ini menyegarkannya saat guru membuka
    // atau menutup ronde, supaya anak tidak perlu me-refresh untuk tahu ronde
    // mana saja yang sekarang bisa dikerjakan.
    function roundNavItem(item, currentMissionId) {
        const li = document.createElement('li');
        const label = 'Ronde ' + item.order + ': ' + item.title + (item.is_game ? ' 🎮' : '');

        // Ronde yang sedang dibuka: penanda, bukan tautan.
        if (parseInt(item.id, 10) === currentMissionId) {
            const current = document.createElement('div');
            current.className =
                'flex items-center justify-between gap-3 rounded-2xl border-2 border-sky-400 bg-sky-50 px-3 py-2';

            const text = document.createElement('span');
            text.className = 'min-w-0 truncate text-xs font-bold text-ink-800';
            text.textContent = label;

            const marker = document.createElement('span');
            marker.className = 'shrink-0 text-[11px] font-black text-sky-600';
            marker.textContent = 'SEKARANG DI SINI';

            current.appendChild(text);
            current.appendChild(marker);
            li.appendChild(current);

            return li;
        }

        const link = document.createElement('a');
        link.href = item.url;
        link.className =
            'flex items-center justify-between gap-3 rounded-2xl border-2 border-sky-100 bg-white px-3 py-2 transition hover:border-sky-400 hover:bg-sky-50';

        const text = document.createElement('span');
        text.className = 'min-w-0 truncate text-xs font-bold text-ink-800';
        text.textContent = label;

        const cta = document.createElement('span');
        cta.className = 'shrink-0 text-[11px] font-black text-sky-600';
        cta.textContent = 'Kerjakan →';

        link.appendChild(text);
        link.appendChild(cta);
        li.appendChild(link);

        return li;
    }

    /**
     * Segarkan bilah ronde dari data server.
     * Mengembalikan true bila daftar ronde yang terbuka benar-benar berubah.
     */
    function syncRoundNav(data) {
        const nav = document.querySelector('[data-round-nav]');
        if (!nav) return false;

        const list = data.open_rounds || [];
        const signature = list.map(function (r) { return r.order; }).join(',');

        if (signature === (nav.dataset.roundNavOrders || '')) return false;

        const listEl = nav.querySelector('[data-round-nav-list]');
        const ordersEl = nav.querySelector('[data-round-nav-orders]');
        const currentMissionId = parseInt(nav.dataset.currentMission || '0', 10);

        if (listEl) {
            listEl.innerHTML = '';
            list.forEach(function (item) {
                listEl.appendChild(roundNavItem(item, currentMissionId));
            });
        }

        if (ordersEl) {
            ordersEl.textContent = signature === ''
                ? 'belum ada'
                : signature.split(',').join(', ');
        }

        nav.dataset.roundNavOrders = signature;

        // Tandai bahwa ada ronde baru, supaya anak yang tidak memperhatikan
        // bilah di bawah tetap tahu.
        if (list.length > 0) {
            nav.querySelector('[data-round-nav-new]')?.classList.remove('hidden');
        }

        return true;
    }

    // Banner "ronde baru dibuka". Dipakai saat anak sedang mengerjakan: dia
    // diberi tahu, tetapi tidak dipindahkan paksa.
    function showRoundBanner(round) {
        const banner = document.getElementById('round-banner');
        if (!banner || !round) return;

        const title = document.getElementById('round-banner-title');
        const text = document.getElementById('round-banner-text');
        const button = document.getElementById('round-banner-button');

        if (title) title.textContent = 'Ronde ' + round.order + ' sudah dibuka: ' + round.title;

        if (text) {
            text.textContent =
                'Kamu bisa pindah sekarang, atau selesaikan dulu jawabanmu di halaman ini. '
                + 'Semua ronde yang terbuka ada di bilah "Pindah Ronde" di atas.';
        }

        if (button) {
            button.href = round.url;
            button.textContent = 'Kerjakan Ronde ' + round.order;
            button.classList.remove('hidden');
        }

        banner.classList.remove('hidden');
        window.TikSound?.play('success');
        window.TikToast?.('Ronde ' + round.order + ' sudah dibuka!', 'success');
    }

    function initRoundBanner() {
        const banner = document.getElementById('round-banner');
        if (!banner) return;

        banner.querySelector('[data-round-banner-close]')?.addEventListener('click', function () {
            banner.classList.add('hidden');
        });
    }

    // -----------------------------------------------------------------
    // Pemantau ronde di halaman misi
    // -----------------------------------------------------------------
    // Anak yang belum menulis apa-apa dipindahkan otomatis ke ronde baru supaya
    // tidak tertinggal. Anak yang sedang menulis hanya diberi tahu — jawaban
    // setengah jadi tidak boleh terkirim sendiri hanya karena guru menekan
    // tombol "Ronde Berikutnya".
    function initRoundWatcher() {
        const form = document.getElementById('submission-form');
        const watch = document.querySelector('[data-round-watch]');

        if (!watch || !form) return;
        if (watch.dataset.autoRedirect === 'false') return; // halaman ini tidak boleh memaksa pindah

        const url = watch.dataset.roundWatch;
        if (!url) return;

        const area = document.getElementById('answer');
        const currentMissionId = parseInt(form.dataset.missionId || '0', 10);
        const sudahDiberitahu = {}; // agar banner tidak muncul berulang untuk ronde yang sama

        function sedangMenulis() {
            return !!area && area.value.trim() !== '';
        }

        setInterval(async function () {
            try {
                const res = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!res.ok) return;

                const data = await res.json();

                // Bilah "Pindah Ronde" selalu ikut diperbarui.
                const berubah = syncRoundNav(data);

                if (data.session_ended) return;

                // Guru membuka BEBERAPA ronde sekaligus: kelompok bebas memilih
                // mau mengerjakan yang mana, jadi jangan pindah paksa.
                if (data.auto_switch === false) return;

                if (!data.round || !data.mission) return;

                // Sudah berada di ronde yang dimaksud.
                if (parseInt(data.mission.id, 10) === currentMissionId) return;

                // Ronde itu belum boleh dikerjakan kelompok ini.
                if (!data.can_work) return;

                if (sedangMenulis()) {
                    if (berubah && !sudahDiberitahu[data.round]) {
                        sudahDiberitahu[data.round] = true;
                        showRoundBanner({
                            order: data.round,
                            title: data.mission.title,
                            url: data.mission.url,
                        });
                    }

                    return;
                }

                // Tidak ada ketikan yang bisa hilang: bantu anak yang tertinggal.
                window.location.href = data.mission.url;
            } catch (e) {
                // Jaringan sempat putus; coba lagi siklus berikutnya.
            }
        }, 5000);
    }

    // -----------------------------------------------------------------
    // Pemantau ronde di dashboard
    // -----------------------------------------------------------------
    // Dashboard adalah tempat anak menunggu aba-aba guru. Begitu daftar ronde
    // yang terbuka berubah, halaman dimuat ulang supaya kartu misi yang ditandai
    // "SEDANG DIBUKA" juga ikut akurat.
    function initDashboardRoundWatcher() {
        const watch = document.querySelector('[data-round-watch]');
        const nav = document.querySelector('[data-round-nav]');

        if (!watch || !nav) return;
        if (watch.dataset.autoRedirect !== 'false') return; // halaman misi punya pemantau sendiri
        if (document.getElementById('wait-rounds')) return; // halaman tunggu punya pemantau sendiri

        const url = watch.dataset.roundWatch;
        if (!url) return;

        let memuatUlang = false;

        setInterval(async function () {
            if (memuatUlang) return;

            try {
                const res = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!res.ok) return;

                if (syncRoundNav(await res.json())) {
                    memuatUlang = true;
                    window.TikSound?.play('success');
                    window.TikToast?.('Daftar ronde berubah. Memuat ulang…', 'info');
                    setTimeout(function () { window.location.reload(); }, 1200);
                }
            } catch (e) {
                // Abaikan; coba lagi siklus berikutnya.
            }
        }, 8000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDraft();
        initWorkTimer();
        initRoundBanner();
        initRoundWatcher();
        initDashboardRoundWatcher();
    });
})();
