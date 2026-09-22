/*
|--------------------------------------------------------------------------
| TIK Mission — Mesin Game Arcade Belajar
|--------------------------------------------------------------------------
| Tiga game klasik yang dimainkan sambil menjawab soal materi:
|   - snake   : jawab benar -> ular bertambah panjang; salah -> nyawa berkurang
|   - breakout: jawab benar -> bata pecah; salah -> bola berkurang
|   - flappy  : jawab benar -> terbang; salah -> jatuh
|
| Cara belajar: soal muncul sebagai "pintu" yang harus dilewati dengan jawaban
| benar. Jadi anak tidak bisa asal main — harus memahami materi dulu.
|
| Ditulis dengan vanilla JS + Canvas agar ringan di komputer laboratorium.
| Tidak ada dependency eksternal, tidak ada aset gambar (semua digambar kode).
|
| Komunikasi ke server: setiap jawaban dikirim ke endpoint agar XP & catatan
| tersimpan, sehingga guru tetap bisa melihat hasil belajar anak.
*/

(function () {
    'use strict';

    // -----------------------------------------------------------------
    // Utilitas
    // -----------------------------------------------------------------
    function bacaData(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    // Acak urutan array tanpa mengubah aslinya.
    function acak(arr) {
        const a = arr.slice();
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    }

    // -----------------------------------------------------------------
    // Game: Ular Pintar (Snake)
    // -----------------------------------------------------------------
    function buatSnake(ctx, W, H) {
        const kotak = 20;
        const kolom = Math.floor(W / kotak);
        const baris = Math.floor(H / kotak);

        return {
            nama: 'snake',
            ular: [{ x: 5, y: 5 }],
            arah: { x: 1, y: 0 },
            arahBerikut: { x: 1, y: 0 },
            makanan: { x: 10, y: 10 },
            bijiSoal: { x: 14, y: 7 },
            panjang: 3,
            skor: 0,

            reset() {
                this.ular = [{ x: 5, y: 5 }];
                this.arah = { x: 1, y: 0 };
                this.arahBerikut = { x: 1, y: 0 };
                this.panjang = 3;
                this.skor = 0;
                this.acakMakanan();
            },

            acakMakanan() {
                this.makanan = {
                    x: Math.floor(Math.random() * (kolom - 2)) + 1,
                    y: Math.floor(Math.random() * (baris - 2)) + 1,
                };
            },

            aturArah(dx, dy) {
                // Tidak boleh berbalik arah 180 derajat.
                if (this.arah.x === -dx && this.arah.y === -dy) return;
                this.arahBerikut = { x: dx, y: dy };
            },

            langkah() {
                this.arah = this.arahBerikut;

                const kepala = {
                    x: this.ular[0].x + this.arah.x,
                    y: this.ular[0].y + this.arah.y,
                };

                // Nabrak dinding atau badan sendiri = mati.
                if (kepala.x < 0 || kepala.x >= kolom || kepala.y < 0 || kepala.y >= baris) {
                    return 'mati';
                }
                for (const b of this.ular) {
                    if (b.x === kepala.x && b.y === kepala.y) return 'mati';
                }

                this.ular.unshift(kepala);

                // Makan buah = bertambah panjang.
                if (kepala.x === this.makanan.x && kepala.y === this.makanan.y) {
                    this.panjang += 1;
                    this.skor += 10;
                    this.acakMakanan();
                }

                while (this.ular.length > this.panjang) {
                    this.ular.pop();
                }

                return 'ok';
            },

            gambar() {
                // Latar papan
                ctx.fillStyle = '#eff9ff';
                ctx.fillRect(0, 0, W, H);

                // Kisi halus
                ctx.strokeStyle = '#dff2fe';
                ctx.lineWidth = 1;
                for (let x = 0; x <= kolom; x++) {
                    ctx.beginPath();
                    ctx.moveTo(x * kotak, 0);
                    ctx.lineTo(x * kotak, H);
                    ctx.stroke();
                }
                for (let y = 0; y <= baris; y++) {
                    ctx.beginPath();
                    ctx.moveTo(0, y * kotak);
                    ctx.lineTo(W, y * kotak);
                    ctx.stroke();
                }

                // Buah (makanan)
                ctx.font = `${kotak - 2}px serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('🍎', this.makanan.x * kotak + kotak / 2, this.makanan.y * kotak + kotak / 2);

                // Ular
                this.ular.forEach((b, i) => {
                    ctx.fillStyle = i === 0 ? '#0284c7' : '#0ea5e9';
                    ctx.beginPath();
                    ctx.roundRect(b.x * kotak + 1, b.y * kotak + 1, kotak - 2, kotak - 2, 4);
                    ctx.fill();

                    // Mata pada kepala
                    if (i === 0) {
                        ctx.fillStyle = '#fff';
                        ctx.beginPath();
                        ctx.arc(b.x * kotak + kotak * 0.35, b.y * kotak + kotak * 0.4, 2, 0, 7);
                        ctx.arc(b.x * kotak + kotak * 0.65, b.y * kotak + kotak * 0.4, 2, 0, 7);
                        ctx.fill();
                    }
                });
            },
        };
    }

    // -----------------------------------------------------------------
    // Game: Pecahkan Target (Breakout)
    // -----------------------------------------------------------------
    function buatBreakout(ctx, W, H) {
        const lebarBata = 72;
        const tinggiBata = 24;

        return {
            nama: 'breakout',
            pemukul: { x: W / 2 - 45, w: 90, h: 12, kecepatan: 9 },
            // Arah pemukul dari keyboard: -1 kiri, 0 diam, +1 kanan.
            arahPemukul: 0,
            bola: { x: W / 2, y: H - 50, dx: 3.2, dy: -3.2, r: 7 },
            bata: [],
            skor: 0,
            baris: 4,
            kolom: Math.floor(W / (lebarBata + 4)),

            reset() {
                this.bata = [];
                this.skor = 0;
                this.arahPemukul = 0;
                this.pemukul.x = W / 2 - this.pemukul.w / 2;
                this.bola.x = W / 2;
                this.bola.y = H - 50;
                this.bola.dx = 3.2;
                this.bola.dy = -3.2;

                const mulaiKiri = (W - this.kolom * (lebarBata + 4)) / 2;

                for (let r = 0; r < this.baris; r++) {
                    for (let k = 0; k < this.kolom; k++) {
                        this.bata.push({
                            x: mulaiKiri + k * (lebarBata + 4),
                            y: 40 + r * (tinggiBata + 4),
                            w: lebarBata,
                            h: tinggiBata,
                            warna: ['#38bdf8', '#34d399', '#fbbf24', '#a78bfa'][r % 4],
                        });
                    }
                }
            },

            /**
             * Atur arah pemukul dari keyboard: -1 kiri, 0 berhenti, +1 kanan.
             */
            aturArahPemukul(arah) {
                this.arahPemukul = arah;
            },

            gerakPemukul(x) {
                this.pemukul.x = Math.max(0, Math.min(W - this.pemukul.w, x - this.pemukul.w / 2));
            },

            langkah() {
                // Pemukul digerakkan keyboard (tanpa mouse).
                if (this.arahPemukul !== 0) {
                    this.pemukul.x += this.arahPemukul * this.pemukul.kecepatan;
                    this.pemukul.x = Math.max(0, Math.min(W - this.pemukul.w, this.pemukul.x));
                }

                this.bola.x += this.bola.dx;
                this.bola.y += this.bola.dy;

                // Pantul dinding
                if (this.bola.x - this.bola.r < 0 || this.bola.x + this.bola.r > W) {
                    this.bola.dx *= -1;
                }
                if (this.bola.y - this.bola.r < 0) {
                    this.bola.dy *= -1;
                }

                // Bola jatuh ke bawah = kehilangan satu nyawa.
                if (this.bola.y > H) {
                    return 'jatuh';
                }

                // Pantul pemukul
                const py = H - 30;
                if (
                    this.bola.dy > 0 &&
                    this.bola.y + this.bola.r >= py &&
                    this.bola.y - this.bola.r <= py + this.pemukul.h &&
                    this.bola.x >= this.pemukul.x &&
                    this.bola.x <= this.pemukul.x + this.pemukul.w
                ) {
                    this.bola.dy = -Math.abs(this.bola.dy);
                    // Arah horizontal mengikuti posisi pantulan.
                    const tengah = this.pemukul.x + this.pemukul.w / 2;
                    this.bola.dx = ((this.bola.x - tengah) / (this.pemukul.w / 2)) * 4;
                }

                // Pecahkan bata
                for (let i = 0; i < this.bata.length; i++) {
                    const b = this.bata[i];
                    if (
                        this.bola.x + this.bola.r > b.x &&
                        this.bola.x - this.bola.r < b.x + b.w &&
                        this.bola.y + this.bola.r > b.y &&
                        this.bola.y - this.bola.r < b.y + b.h
                    ) {
                        this.bata.splice(i, 1);
                        this.bola.dy *= -1;
                        this.skor += 5;
                        break;
                    }
                }

                return 'ok';
            },

            gambar() {
                ctx.fillStyle = '#eff9ff';
                ctx.fillRect(0, 0, W, H);

                // Bata
                this.bata.forEach((b) => {
                    ctx.fillStyle = b.warna;
                    ctx.beginPath();
                    ctx.roundRect(b.x, b.y, b.w, b.h, 6);
                    ctx.fill();
                });

                // Pemukul
                ctx.fillStyle = '#0369a1';
                ctx.beginPath();
                ctx.roundRect(this.pemukul.x, H - 30, this.pemukul.w, this.pemukul.h, 6);
                ctx.fill();

                // Bola
                ctx.fillStyle = '#0f2a43';
                ctx.beginPath();
                ctx.arc(this.bola.x, this.bola.y, this.bola.r, 0, 7);
                ctx.fill();
            },
        };
    }

    // -----------------------------------------------------------------
    // Game: Terbang Tinggi (Flappy)
    // -----------------------------------------------------------------
    function buatFlappy(ctx, W, H) {
        return {
            nama: 'flappy',
            burung: { y: H / 2, vy: 0, r: 14 },
            rintangan: [],
            skor: 0,
            gravitasi: 0.42,
            lompat: -7.2,
            jarak: 200,
            celah: 150,

            reset() {
                this.burung = { y: H / 2, vy: 0, r: 14 };
                this.rintangan = [];
                this.skor = 0;
                this.tambahRintangan(W + 60);
            },

            tambahRintangan(x) {
                const atas = 40 + Math.random() * (H - this.celah - 120);
                this.rintangan.push({ x: x, atas: atas, lebar: 60, lewat: false });
            },

            kepak() {
                this.burung.vy = this.lompat;
            },

            langkah() {
                this.burung.vy += this.gravitasi;
                this.burung.y += this.burung.vy;

                // Terbang terlalu tinggi atau jatuh = mati.
                if (this.burung.y - this.burung.r < 0) {
                    this.burung.y = this.burung.r;
                    this.burung.vy = 0;
                }
                if (this.burung.y + this.burung.r > H) {
                    return 'jatuh';
                }

                // Geser rintangan
                this.rintangan.forEach((r) => (r.x -= 2.6));

                // Tambah rintangan baru & hitung skor
                const terakhir = this.rintangan[this.rintangan.length - 1];
                if (terakhir && terakhir.x < W - this.jarak) {
                    this.tambahRintangan(W + 20);
                }

                this.rintangan.forEach((r) => {
                    if (!r.lewat && r.x + r.lebar < 0) {
                        r.lewat = true;
                        this.skor += 5;
                    }
                });

                this.rintangan = this.rintangan.filter((r) => r.x + r.lebar > -20);

                // Tabrakan
                for (const r of this.rintangan) {
                    const kenaX = this.burung.r * 1.2 + r.lebar / 2 > Math.abs(this.burung.x ?? W / 3 - (r.x + r.lebar / 2));
                    const bx = W / 3;

                    if (bx + this.burung.r > r.x && bx - this.burung.r < r.x + r.lebar) {
                        if (this.burung.y - this.burung.r < r.atas || this.burung.y + this.burung.r > r.atas + this.celah) {
                            return 'tabrak';
                        }
                    }
                }

                return 'ok';
            },

            gambar() {
                // Langit
                const grad = ctx.createLinearGradient(0, 0, 0, H);
                grad.addColorStop(0, '#dff2fe');
                grad.addColorStop(1, '#eff9ff');
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, W, H);

                // Awan sederhana
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(W * 0.2, 50, 22, 0, 7);
                ctx.arc(W * 0.28, 55, 16, 0, 7);
                ctx.arc(W * 0.72, 90, 20, 0, 7);
                ctx.arc(W * 0.8, 95, 14, 0, 7);
                ctx.fill();

                // Rintangan (pipa hijau)
                this.rintangan.forEach((r) => {
                    ctx.fillStyle = '#34d399';
                    ctx.beginPath();
                    ctx.roundRect(r.x, 0, r.lebar, r.atas, 8);
                    ctx.fill();

                    ctx.beginPath();
                    ctx.roundRect(r.x, r.atas + this.celah, r.lebar, H - (r.atas + this.celah), 8);
                    ctx.fill();
                });

                // Burung
                const bx = W / 3;
                ctx.font = '30px serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('🐦', bx, this.burung.y);
            },
        };
    }

    // -----------------------------------------------------------------
    // Papan kendali utama (menyatukan game + soal)
    // -----------------------------------------------------------------
    function mulai() {
        const root = document.getElementById('game-root');
        if (!root) return;

        const jenis = root.dataset.game;
        const soal = bacaData('game-questions') || [];
        const jawabUrl = root.dataset.answerUrl;
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        const canvas = document.getElementById('game-canvas');
        const ctx = canvas.getContext('2d');

        // Elemen yang dimasukkan ke mode layar penuh (fokus bermain).
        const panggung = document.getElementById('game-stage') || canvas;
        const btnKeluarFs = document.getElementById('btn-keluar-fullscreen');

        // Ukuran canvas responsif (dibatasi agar tetap tajam).
        const W = 720;
        const H = 420;
        canvas.width = W;
        canvas.height = H;

        const elNyawa = document.getElementById('stat-nyawa');
        const elSkor = document.getElementById('stat-skor');
        const elBenar = document.getElementById('stat-benar');
        const elSoal = document.getElementById('soal-teks');
        const elPilihan = document.getElementById('soal-pilihan');
        const elPesan = document.getElementById('game-pesan');
        const panelSoal = document.getElementById('panel-soal');
        const btnMulai = document.getElementById('btn-mulai');

        // Pilih mesin game sesuai ronde.
        let game;

        if (jenis === 'breakout') {
            game = buatBreakout(ctx, W, H);
        } else if (jenis === 'flappy') {
            game = buatFlappy(ctx, W, H);
        } else {
            game = buatSnake(ctx, W, H);
        }

        // Status permainan
        let jalan = false;
        let nyawa = 3;
        let benar = 0;
        let salah = 0;
        let soalSekarang = null;
        let indeksSoal = 0;
        let tumpukan = [];
        let tungguJawab = false;
        let loopId = null;
        let waktuLangkah = 0;

        // Interval langkah per jenis game (ms).
        const intervalLangkah = jenis === 'snake' ? 130 : 16;

        function perbaruiStatistik() {
            elNyawa.textContent = '❤️'.repeat(Math.max(0, nyawa)) + '🖤'.repeat(Math.max(0, 3 - nyawa));
            elSkor.textContent = game.skor;
            elBenar.textContent = benar;
        }

        // -----------------------------------------------------------------
        // Mode layar penuh (fullscreen)
        //
        // Tujuannya: satu anak fokus menjaga nyawa (menggerakkan game),
        // anggota lain mencari jawaban dari buku/komputer lain. Karena itu
        // saat mulai bermain layar dibuat penuh, dan otomatis dikembalikan
        // saat soal selesai supaya diskusi kelompok bisa lanjut.
        // -----------------------------------------------------------------
        function masukLayarPenuh() {
            // requestFullscreen hanya boleh dipanggil dari aksi pengguna
            // (tekanan tombol), jadi dipanggil langsung dari klik "Mulai".
            if (!document.fullscreenElement) {
                const janji = panggung.requestFullscreen
                    ? panggung.requestFullscreen({ navigationUI: 'hide' })
                    : null;

                if (janji && janji.catch) {
                    janji.catch(function () {
                        // Browser menolak (mis. kebijakan sekolah): abaikan saja.
                        window.TikToast &&
                            window.TikToast('Mode layar penuh tidak diizinkan browser. Tekan F11 manual.', 'warning');
                    });
                }
            }
        }

        function keluarLayarPenuh() {
            if (document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().catch(function () {});
            }
        }

        // Perbarui tampilan tombol keluar sesuai kondisi layar.
        function perbaruiTombolFs() {
            if (!btnKeluarFs) return;

            const penuh = !!document.fullscreenElement;
            btnKeluarFs.textContent = penuh ? '⤢ Keluar Layar Penuh' : '⤢ Layar Penuh';
            btnKeluarFs.setAttribute('aria-pressed', penuh ? 'true' : 'false');
        }

        if (btnKeluarFs) {
            btnKeluarFs.addEventListener('click', function () {
                // Tombol ini bisa dipakai untuk masuk ATAU keluar layar penuh.
                if (document.fullscreenElement) {
                    keluarLayarPenuh();
                } else {
                    masukLayarPenuh();
                }
            });
        }

        document.addEventListener('fullscreenchange', function () {
            perbaruiTombolFs();
            document.body.classList.toggle('tik-fs', !!document.fullscreenElement);
        });

        perbaruiTombolFs();

        function ambilSoal() {
            // Ronde ini hanya punya sedikit soal (biasanya 1). Bila soal sudah
            // habis dijawab, permainan dianggap tuntas — tidak diulang-ulang.
            if (tumpukan.length === 0) {
                tumpukan = acak(soal);
            }
            const q = tumpukan.shift();
            indeksSoal += 1;

            return q;
        }

        function tampilkanSoal() {
            if (soal.length === 0) {
                elSoal.textContent = 'Tidak ada soal untuk ronde ini.';
                elPilihan.innerHTML = '';
                return;
            }

            soalSekarang = ambilSoal();
            elSoal.textContent = soalSekarang.pertanyaan;
            elPilihan.innerHTML = '';

            soalSekarang.pilihan.forEach(function (teks, i) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className =
                    'rounded-2xl border-2 border-sky-200 bg-white px-4 py-3 text-left text-sm font-bold text-ink-800 transition hover:border-sky-400 hover:bg-sky-50';
                btn.textContent = teks;
                btn.addEventListener('click', function () {
                    jawab(i);
                });
                elPilihan.appendChild(btn);
            });
        }

        function kunciPilihan(kunci) {
            elPilihan.querySelectorAll('button').forEach(function (b) {
                b.disabled = kunci;
            });
        }

        // Kirim jawaban ke server (untuk XP & catatan guru).
        function laporJawaban(pilihan, tepat) {
            if (!jawabUrl) return;

            fetch(jawabUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    pertanyaan: soalSekarang ? soalSekarang.pertanyaan : '',
                    pilihan: pilihan,
                    tepat: tepat,
                }),
            }).catch(function () {
                // Kegagalan jaringan tidak boleh menghentikan permainan.
            });
        }

        function jawab(pilihan) {
            if (!jalan || tungguJawab || !soalSekarang) return;

            tungguJawab = true;
            kunciPilihan(true);

            const tepat = pilihan === soalSekarang.jawaban;

            laporJawaban(pilihan, tepat);

            if (tepat) {
                benar += 1;
                game.skor += 15;
                elPesan.textContent = '✅ Benar! Teruskan!';
                elPesan.className = 'text-sm font-black text-mint-600';
                window.TikSound && window.TikSound.play('success');

                // Efek sesuai game
                if (game.nama === 'snake') {
                    game.panjang += 2;
                } else if (game.nama === 'flappy') {
                    game.burung.vy = game.lompat * 1.4;
                }
            } else {
                salah += 1;
                nyawa -= 1;
                elPesan.textContent = '❌ Belum tepat. ' + soalSekarang.pilihan[soalSekarang.jawaban];
                elPesan.className = 'text-sm font-black text-coral-500';
                window.TikSound && window.TikSound.play('error');
            }

            perbaruiStatistik();

            if (nyawa <= 0) {
                selesai('Semua nyawa habis');
                return;
            }

            // Semua soal sudah dijawab benar: permainan selesai.
            if (indeksSoal >= soal.length && tepat) {
                selesai('Semua soal selesai');
                return;
            }

            // Soal berikutnya setelah jeda singkat.
            setTimeout(function () {
                tungguJawab = false;
                tampilkanSoal();
            }, 900);
        }

        function loop(waktu) {
            if (!jalan) return;

            // Langkah game hanya tiap interval (snake lebih lambat).
            if (waktu - waktuLangkah >= intervalLangkah) {
                waktuLangkah = waktu;

                const hasil = game.langkah();

                if (hasil === 'mati' || hasil === 'jatuh' || hasil === 'tabrak') {
                    nyawa -= 1;
                    perbaruiStatistik();

                    window.TikSound && window.TikSound.play('error');

                    if (nyawa <= 0) {
                        selesai('Nyawa habis');
                        return;
                    }

                    // Ulang posisi game, lanjut soal yang sama.
                    const simpanSkor = game.skor;
                    game.reset();
                    game.skor = simpanSkor;
                    elPesan.textContent = '⚠️ Hati-hati! Nyawa berkurang.';
                    elPesan.className = 'text-sm font-black text-sun-500';
                }
            }

            game.gambar();
            loopId = requestAnimationFrame(loop);
        }

        function mulaiMain() {
            if (soal.length === 0) {
                elPesan.textContent = 'Guru belum menyiapkan soal untuk ronde ini.';
                elPesan.className = 'text-sm font-black text-coral-500';
                return;
            }

            // Masuk layar penuh agar anak fokus bermain.
            masukLayarPenuh();

            nyawa = 3;
            benar = 0;
            salah = 0;
            tumpukan = [];
            tungguJawab = false;
            game.reset();
            perbaruiStatistik();

            jalan = true;
            btnMulai.classList.add('hidden');
            panelSoal.classList.remove('opacity-50');

            tampilkanSoal();

            elPesan.textContent = 'Baca soal, lalu pilih jawaban yang benar!';
            elPesan.className = 'text-sm font-black text-sky-600';

            loopId = requestAnimationFrame(loop);
        }

        function selesai(sebab) {
            jalan = false;

            // Kembalikan layar supaya kelompok bisa berdiskusi lagi.
            keluarLayarPenuh();

            if (loopId) {
                cancelAnimationFrame(loopId);
                loopId = null;
            }

            game.gambar();

            const total = benar + salah;
            const nilai = total > 0 ? Math.round((benar / total) * 100) : 0;

            elPesan.innerHTML =
                '🏁 Permainan selesai (' + sebab + ').<br>' +
                'Jawaban benar: <strong>' + benar + '</strong> dari ' + total +
                ' · Akurasi: <strong>' + nilai + '%</strong>';

            elPesan.className = 'text-sm font-black text-ink-800';

            panelSoal.classList.add('opacity-50');
            kunciPilihan(true);

            btnMulai.classList.remove('hidden');
            btnMulai.textContent = '🔁 Main Lagi';

            window.TikSound && window.TikSound.play('success');

            // Kirim ringkasan ke server agar guru melihat hasilnya.
            if (jawabUrl) {
                fetch(jawabUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        ringkasan: true,
                        benar: benar,
                        salah: salah,
                        skor: game.skor,
                    }),
                }).catch(function () {});
            }
        }

        // -----------------------------------------------------------------
        // Kendali
        // -----------------------------------------------------------------
        btnMulai.addEventListener('click', mulaiMain);

        // -----------------------------------------------------------------
        // Kendali keyboard murni
        //
        // Semua game dikendalikan keyboard supaya anak yang mengetik jawaban
        // tidak terganggu (tidak ada mouse yang bergerak atau spasi yang
        // ikut tertekan saat menulis). Karena itu:
        //   - snake   : panah / W A S D
        //   - breakout: panah kiri-kanan / A D
        //   - flappy  : panah atas / W  (BUKAN spasi, agar bisa mengetik)
        //
        // Bila fokus sedang di kolom isian (mis. anak mengetik jawaban),
        // tombol game diabaikan supaya tidak mengganggu pengetikan.
        // -----------------------------------------------------------------

        // Apakah pengguna sedang mengetik di kolom isian?
        function sedangMengetik(target) {
            if (!target) return false;

            const tag = (target.tagName || '').toLowerCase();

            return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
        }

        function tombolDitekan(e) {
            if (!jalan) return;

            // Jangan rebut tombol saat anak mengetik jawaban.
            if (sedangMengetik(e.target)) return;

            const kiri = e.key === 'ArrowLeft' || e.key === 'a' || e.key === 'A';
            const kanan = e.key === 'ArrowRight' || e.key === 'd' || e.key === 'D';
            const atas = e.key === 'ArrowUp' || e.key === 'w' || e.key === 'W';
            const bawah = e.key === 'ArrowDown' || e.key === 's' || e.key === 'S';

            if (game.nama === 'snake') {
                if (atas) game.aturArah(0, -1);
                if (bawah) game.aturArah(0, 1);
                if (kiri) game.aturArah(-1, 0);
                if (kanan) game.aturArah(1, 0);

                if (atas || bawah || kiri || kanan) e.preventDefault();
            } else if (game.nama === 'breakout') {
                // Tahan tombol untuk terus menggerakkan pemukul.
                if (kiri) {
                    game.aturArahPemukul(-1);
                    e.preventDefault();
                }
                if (kanan) {
                    game.aturArahPemukul(1);
                    e.preventDefault();
                }
            } else if (game.nama === 'flappy') {
                // Panah atas / W untuk mengepak (bukan spasi, agar aman saat mengetik).
                if (atas) {
                    e.preventDefault();
                    game.kepak();
                }
            }
        }

        // Lepas tombol: hentikan gerak pemukul agar tidak terus melaju.
        function tombolDilepas(e) {
            if (game.nama !== 'breakout') return;

            const kiri = e.key === 'ArrowLeft' || e.key === 'a' || e.key === 'A';
            const kanan = e.key === 'ArrowRight' || e.key === 'd' || e.key === 'D';

            if (kiri || kanan) {
                game.aturArahPemukul(0);
            }
        }

        document.addEventListener('keydown', tombolDitekan);
        document.addEventListener('keyup', tombolDilepas);

        // -----------------------------------------------------------------
        // Klik pada papan
        //
        // Hanya untuk: (1) mengepak pada flappy lewat klik (opsional),
        // dan (2) mengembalikan fokus keyboard ke papan.
        // Tidak ada lagi kontrol mouse untuk menggerakkan pemukul.
        // -----------------------------------------------------------------
        canvas.addEventListener('pointerdown', function (e) {
            if (!jalan) return;
            if (game.nama === 'flappy') {
                e.preventDefault();
                game.kepak();
            }
        });

        // Saat fokus kembali ke papan, pastikan keyboard siap menerima.
        canvas.setAttribute('tabindex', '0');

        // Gambar papan kosong sebagai pratinjau awal.
        game.gambar();
        perbaruiStatistik();
    }

    document.addEventListener('DOMContentLoaded', mulai);
})();
