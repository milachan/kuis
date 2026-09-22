/*
|--------------------------------------------------------------------------
| TIK Mission — Timer Ronde & Simpan Ketikan Siswa
|--------------------------------------------------------------------------
| Dua tugas:
|   1. Menyimpan ketikan siswa di browser (localStorage) supaya tidak hilang
|      saat halaman berpindah ronde atau browser tertutup.
|   2. Saat guru membuka ronde baru, bila siswa SUDAH menulis jawaban di ronde
|      lama, jawaban itu dikirim otomatis (via fetch) supaya tidak terbuang.
|
| Timer per siswa: dihitung dari saat siswa membuka halaman ronde ini,
| bukan dari saat guru membuka ronde.
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
    // Kirim otomatis saat ronde berganti
    // -----------------------------------------------------------------
    function initAutoSubmitOnRoundChange() {
        const form = document.getElementById('submission-form');
        if (!form) return;

        const area = document.getElementById('answer');
        if (!area) return;

        const url = document.getElementById('work-timer')?.dataset.roundStatus;
        const currentMissionId = form.dataset.missionId;
        const submitUrl = form.getAttribute('action');

        if (!url) return;

        // Hanya berlaku bila siswa sudah menulis sesuatu.
        function hasUnsavedAnswer() {
            return area.value.trim().length >= 10;
        }

        async function finishBeforeMoving(roundUrl) {
            // Kirim jawaban yang sudah ditulis lewat fetch, lalu pindah halaman.
            const data = new FormData();
            data.append('_token', form.querySelector('input[name="_token"]').value);
            data.append('answer', area.value);

            try {
                await fetch(submitUrl, {
                    method: 'POST',
                    body: data,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                // Bersihkan draf karena sudah terkirim.
                try {
                    localStorage.removeItem(draftKey(currentMissionId));
                } catch (e) {
                    // Diabaikan.
                }

                window.TikToast?.('Jawabanmu otomatis terkirim sebelum pindah ronde.', 'success');
            } catch (e) {
                // Kalau gagal, ketikan tetap ada di localStorage.
                window.TikToast?.('Gagal mengirim otomatis. Ketikanmu tersimpan.', 'warning');
            }

            window.location.href = roundUrl;
        }

        let busy = false;

        setInterval(async function () {
            if (busy) return;

            try {
                const res = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!res.ok) return;

                const data = await res.json();
                if (!data.round || !data.mission) return;

                // Masih di ronde yang sama: tidak perlu apa-apa.
                if (data.mission.id === parseInt(currentMissionId, 10)) return;

                busy = true;

                // Kalau ada ketikan yang belum dikirim, kirim dulu.
                if (hasUnsavedAnswer()) {
                    await finishBeforeMoving(data.mission.url);

                    return;
                }

                // Tidak ada ketikan: langsung pindah ke ronde baru.
                window.location.href = data.mission.url;
            } catch (e) {
                // Jaringan sempat putus; coba lagi siklus berikutnya.
            }
        }, 5000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDraft();
        initWorkTimer();
        initAutoSubmitOnRoundChange();
    });
})();
