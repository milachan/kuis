/*
|--------------------------------------------------------------------------
| TIK Mission — Pengawas Ronde untuk Siswa
|--------------------------------------------------------------------------
| Guru memulai ronde baru dari layar proyektor. Halaman siswa tidak otomatis
| tahu, sehingga murid bisa tertinggal di ronde lama. Skrip ini memeriksa
| status ronde secara berkala, lalu:
|   - memberi tahu saat ronde baru dibuka, dan
|   - memindahkan murid ke misi ronde itu (atau mengarahkan ke dashboard).
|
| Dipasang di dashboard & halaman misi siswa.
*/

(function () {
    'use strict';

    const POLL_MS = 5000;

    function init() {
        const root = document.querySelector('[data-round-watch]');
        if (!root) return;

        const url = root.dataset.roundWatch;
        const currentMissionId = parseInt(root.dataset.currentMission || '0', 10);
        const autoRedirect = root.dataset.autoRedirect === 'true';

        if (!url) return;

        const banner = document.getElementById('round-banner');
        const bannerTitle = document.getElementById('round-banner-title');
        const bannerText = document.getElementById('round-banner-text');
        const bannerButton = document.getElementById('round-banner-button');

        // Ronde yang sudah pernah diberitahukan, agar tidak berulang.
        let notifiedRound = null;
        // Jangan lompat lagi setelah murid menutup pemberitahuan ronde ini.
        let dismissedRound = null;

        function showBanner(round, missionUrl, canWork) {
            if (!banner) return;

            banner.classList.remove('hidden');

            if (bannerTitle) {
                bannerTitle.textContent = `Ronde ${round.round} dibuka!`;
            }

            if (bannerText) {
                bannerText.textContent = canWork
                    ? 'Guru sudah membuka ronde baru. Klik tombol untuk mulai mengerjakan.'
                    : 'Guru sudah membuka ronde baru. Tunggu sebentar, misimu sedang disiapkan.';
            }

            if (bannerButton) {
                if (missionUrl) {
                    bannerButton.href = missionUrl;
                    bannerButton.classList.remove('hidden');
                } else {
                    bannerButton.classList.add('hidden');
                }
            }
        }

        async function check() {
            try {
                const res = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);

                const data = await res.json();

                // Sesi berakhir: beri tahu, jangan pindah ke mana-mana.
                if (data.session_ended) {
                    if (banner && bannerTitle && bannerText) {
                        banner.classList.remove('hidden');
                        bannerTitle.textContent = 'Sesi sudah berakhir';
                        bannerText.textContent = 'Terima kasih sudah bermain! Jawabanmu tetap tersimpan.';
                        bannerButton?.classList.add('hidden');
                    }

                    return;
                }

                if (!data.round || !data.mission) return;

                // Belum ada ronde berjalan, atau murid sudah berada di ronde itu.
                const alreadyHere = data.mission.id === currentMissionId;

                if (notifiedRound === data.round || dismissedRound === data.round) {
                    return;
                }

                if (data.is_running && !alreadyHere) {
                    notifiedRound = data.round;

                    // Suara + notifikasi agar murid sadar ronde baru dibuka.
                    window.TikSound?.play('success');
                    window.TikToast?.(`Ronde ${data.round} dimulai!`, 'info');

                    if (data.can_work && autoRedirect) {
                        // Di halaman misi lama: langsung pindah ke ronde baru
                        // supaya murid tidak menunggu tanpa tahu harus apa.
                        banner && showBanner(data, data.mission.url, true);

                        setTimeout(() => {
                            window.location.href = data.mission.url;
                        }, 1200);

                        return;
                    }

                    showBanner(data, data.mission.url, data.can_work);

                    if (data.can_work && bannerButton) {
                        bannerButton.addEventListener('click', () => {
                            dismissedRound = data.round;
                        }, { once: true });
                    }
                }
            } catch (e) {
                // Jaringan sempat putus: coba lagi pada siklus berikutnya.
            } finally {
                setTimeout(check, POLL_MS);
            }
        }

        // Tombol tutup pada banner.
        document.querySelectorAll('[data-round-banner-close]').forEach((btn) => {
            btn.addEventListener('click', () => {
                dismissedRound = notifiedRound;
                banner?.classList.add('hidden');
            });
        });

        check();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
