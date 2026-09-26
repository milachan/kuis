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
        const roundUrl = root.dataset.roundStatus || null;
        const missionId = root.dataset.missionId || '0';
        const missionOrder = parseInt(root.dataset.missionOrder || '0', 10);
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

        // Papan hasil (perayaan + arahan ke soal uraian). Lapisan ini
        // ditampilkan/di-sembunyikan lewat atribut `hidden` — lihat .tik-hasil
        // di app.css — supaya tidak bentrok dengan display:flex-nya.
        const elHasil = document.getElementById('game-hasil');
        const elKonfeti = document.getElementById('hasil-konfeti');
        const elHasilBintang = document.getElementById('hasil-bintang');
        const elHasilJudul = document.getElementById('hasil-judul');
        const elHasilSebab = document.getElementById('hasil-sebab');
        const elHasilBenar = document.getElementById('hasil-benar');
        const elHasilAkurasi = document.getElementById('hasil-akurasi');
        const elHasilSkor = document.getElementById('hasil-skor');
        const elHasilXp = document.getElementById('hasil-xp');
        const elHasilXpRincian = document.getElementById('hasil-xp-rincian');
        const elHasilHitung = document.getElementById('hasil-hitung');
        const btnHasilUlang = document.getElementById('hasil-ulang');
        const btnHasilTutup = document.getElementById('hasil-tutup');
        const linkHasilLanjut = document.getElementById('hasil-lanjut');

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

        // Apakah game sedang DIBEKUKAN supaya anak bisa membaca soal dengan
        // tenang. Saat dibekukan, `game.langkah()` tidak dipanggil, jadi ular /
        // bola / burung tidak menabrak sementara anak berpikir.
        let soalTerbuka = false;

        // Batas nyawa. Ronde TIDAK berakhir saat nyawa habis — anak hanya
        // kehilangan "nyawa" sebagai mekanik game, lalu nyawa diisi ulang dan
        // sisa soal tetap bisa dikerjakan. Ronde baru benar-benar selesai bila
        // SEMUA soal sudah dijawab benar. Dengan begitu tidak ada lagi kejadian
        // "baru 2 soal sudah selesai".
        const NYAWA_AWAL = 3;

        // Catatan SETIAP pilihan yang ditekan anak selama ronde ini.
        // Inilah satu-satunya data yang dikirim ke server. Server menilai
        // ulang memakai kunci jawaban misi, jadi anak tidak bisa memalsukan
        // skor lewat DevTools. `benar`/`salah`/`game.skor` di sini hanya untuk
        // tampilan di layar, bukan sumber kebenaran.
        let catatanJawaban = [];

        // Sisa soal ronde ini yang belum dijawab BENAR. Diisi ulang hanya saat
        // ronde dimulai (mulaiMain), jadi soal yang sudah dijawab benar tidak
        // pernah muncul dua kali dalam satu ronde.
        let tumpukan = [];
        let tungguJawab = false;
        let loopId = null;
        let waktuLangkah = 0;

        // Hitung mundur "berpindah ke soal uraian" setelah ronde selesai.
        let hitungId = null;

        // Interval langkah per jenis game (ms).
        const intervalLangkah = jenis === 'snake' ? 130 : 16;

        function perbaruiStatistik() {
            elNyawa.textContent = '❤️'.repeat(Math.max(0, nyawa)) + '🖤'.repeat(Math.max(0, NYAWA_AWAL - nyawa));
            elSkor.textContent = game.skor;
            elBenar.textContent = benar;
        }

        // Sisa soal yang belum dijawab benar pada ronde ini.
        function sisaSoal() {
            return tumpukan.length + (soalSekarang ? 1 : 0);
        }

        // Apakah SEMUA soal ronde ini sudah dijawab benar.
        function semuaSoalSelesai() {
            return tumpukan.length === 0 && soalSekarang === null;
        }

        // -----------------------------------------------------------------
        // Mode layar penuh (fullscreen)
        //
        // Tujuannya: satu anak fokus menjaga nyawa (menggerakkan game),
        // anggota lain mencari jawaban dari buku/komputer lain. Karena itu
        // saat mulai bermain layar dibuat penuh.
        //
        // Layar penuh SENGAJA tidak ditutup otomatis saat permainan selesai,
        // supaya papan hasil tetap terlihat dan anak tidak terlempar dari mode
        // bermain tepat setelah menekan jawaban. Anak keluar dari layar penuh
        // lewat tombol "⤢ Keluar Layar Penuh" di panggung, atau otomatis saat
        // berpindah ke halaman soal uraian.
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

        // Ambil soal berikutnya dari sisa soal ronde. Tidak pernah mengisi ulang
        // daftar: bila kosong, artinya semua soal sudah dijawab benar.
        function ambilSoal() {
            return tumpukan.shift() || null;
        }

        // Nomor soal yang sedang ditampilkan (1..jumlah soal). Dipakai untuk
        // label "Soal 3 dari 8" — dihitung dari POSISI soal, bukan dari jumlah
        // jawaban benar, supaya angkanya tetap benar walau anak menjawab salah.
        let nomorSoal = 0;

        function tampilkanSoal() {
            if (soal.length === 0) {
                elSoal.textContent = 'Tidak ada soal untuk ronde ini.';
                elPilihan.innerHTML = '';
                return;
            }

            const berikutnya = ambilSoal();

            // Semua soal ronde sudah dijawab benar: inilah SATU-SATUNYA sebab
            // ronde dianggap selesai. Kehabisan nyawa tidak lagi mengakhiri ronde.
            if (!berikutnya) {
                soalSekarang = null;
                selesai('Semua soal selesai');
                return;
            }

            soalSekarang = berikutnya;
            nomorSoal += 1;

            // Soal baru terbuka: beri jeda singkat gerak game supaya anak sempat
            // MEMBACA soal dulu tanpa langsung menabrak. Setelah jeda, game
            // berjalan lagi — jadi permainan tetap "hidup", bukan beku total.
            soalTerbuka = true;

            gambarSoal();
            perbaruiStatistik();

            setTimeout(function () {
                // Hanya buka beku bila soal ini masih yang sedang aktif.
                if (soalSekarang === berikutnya) soalTerbuka = false;
            }, 2500);
        }

        // Gambar soal yang sedang aktif beserta tombol pilihannya.
        // Dipakai ulang saat soal yang sama harus ditawarkan lagi (jawaban salah).
        function gambarSoal() {
            if (!soalSekarang) return;

            // Label posisi soal (mis. "Soal 3 dari 8"), dihitung dari posisi.
            elSoal.textContent =
                'Soal ' + Math.min(nomorSoal, soal.length) + ' dari ' + soal.length + ': ' + soalSekarang.pertanyaan;
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

        // Catat pilihan anak. Ini SATU-SATUNYA data kiriman; server yang
        // menentukan benar/salah. `tepat` di sini hanya untuk tampilan lokal.
        function catatJawaban(pilihan, tepat) {
            if (!soalSekarang) return;

            catatanJawaban.push({
                pertanyaan: soalSekarang.pertanyaan,
                pilihan: soalSekarang.pilihan[pilihan] || '',
                // Dipakai hanya untuk pemantauan ronde (belum ada jawaban ->
                // tidak perlu kirim apa pun). Bukan sumber kebenaran skor.
                tepat: tepat,
            });
        }

        // Nyawa habis BUKAN akhir ronde: nyawa diisi ulang dan anak melanjutkan
        // sisa soal. Ronde hanya selesai bila semua soal sudah dijawab benar.
        function isiUlangNyawa() {
            nyawa = NYAWA_AWAL;
            perbaruiStatistik();

            window.TikToast &&
                window.TikToast('Nyawa habis, tapi permainan tetap lanjut! Nyawa sudah diisi ulang. Ayo kerjakan sisa soal.', 'info');
        }

        function jawab(pilihan) {
            if (!jalan || tungguJawab || !soalSekarang) return;

            tungguJawab = true;
            kunciPilihan(true);

            const tepat = pilihan === soalSekarang.jawaban;

            catatJawaban(pilihan, tepat);

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

                perbaruiStatistik();

                // Soal berikutnya setelah jeda singkat. Bila sisa soal sudah
                // habis, tampilkanSoal() yang menutup ronde.
                setTimeout(function () {
                    tungguJawab = false;
                    // Bekukan sebentar saat berganti soal baru (diatur di
                    // tampilkanSoal()), lalu game berjalan lagi.
                    tampilkanSoal();
                }, 900);

                return;
            }

            // Salah: nyawa berkurang, lalu soal yang SAMA ditawarkan lagi supaya
            // kelompok bisa berdiskusi dulu sebelum mencoba ulang. Anak tetap
            // melanjutkan permainan walau nyawanya habis.
            //
            // PENTING: `soalTerbuka` TIDAK diubah di sini. Soal ini sudah
            // dibiarkan terbaca (beku sebentar saat muncul), lalu game berjalan
            // lagi. Anak boleh mengulang menjawab sambil game tetap bergerak.
            salah += 1;
            nyawa -= 1;
            elPesan.textContent = '❌ Belum tepat. ' + soalSekarang.pilihan[soalSekarang.jawaban];
            elPesan.className = 'text-sm font-black text-coral-500';
            window.TikSound && window.TikSound.play('error');

            const nyawaHabis = nyawa <= 0;
            if (nyawaHabis) isiUlangNyawa();

            perbaruiStatistik();

            setTimeout(function () {
                tungguJawab = false;

                // Nyawa habis: ulang posisi game, lalu tawarkan soal yang sama lagi.
                if (nyawaHabis) {
                    const simpanSkor = game.skor;
                    game.reset();
                    game.skor = simpanSkor;
                }

                gambarSoal();
            }, 900);
        }

        function loop(waktu) {
            if (!jalan) return;

            // Langkah game hanya tiap interval (snake lebih lambat), dan TIDAK
            // berjalan selama sebuah soal terbuka. Ini memberi anak waktu
            // membaca/mendiskusikan soal tanpa ularnya menabrak.
            if (!soalTerbuka && waktu - waktuLangkah >= intervalLangkah) {
                waktuLangkah = waktu;

                const hasil = game.langkah();

                if (hasil === 'mati' || hasil === 'jatuh' || hasil === 'tabrak') {
                    nyawa -= 1;
                    perbaruiStatistik();

                    window.TikSound && window.TikSound.play('error');

                    // Kehabisan nyawa TIDAK mengakhiri ronde: nyawa diisi ulang,
                    // posisi game direset, dan anak melanjutkan soal yang sama.
                    if (nyawa <= 0) {
                        isiUlangNyawa();

                        const simpanSkor = game.skor;
                        game.reset();
                        game.skor = simpanSkor;
                        elPesan.textContent = 'Nyawa habis, diisi ulang. Lanjutkan soal ini.';
                        elPesan.className = 'text-sm font-black text-sun-500';

                        game.gambar();
                        loopId = requestAnimationFrame(loop);

                        return;
                    }

                    // Ulang posisi game, lanjut soal yang sama.
                    const simpanSkor = game.skor;
                    game.reset();
                    game.skor = simpanSkor;
                    elPesan.textContent = '⚠️ Hati-hati! Nyawa berkurang (bukan karena jawaban).';
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

            // Ronde baru: papan hasil dan arahan pindah ronde direset dulu.
            batalHitungMundur();
            if (elHasil) elHasil.hidden = true;

            // PENTING: memulai (atau mengulang) permainan TIDAK menghapus
            // catatan jawaban yang sudah ada. Dulu catatan ini dikosongkan di
            // sini, sehingga hasil main pertama hilang dan server hanya menilai
            // sesi terakhir. Sekarang catatan dikumpulkan sampai ronde benar-
            // benar selesai.
            //
            // Karena itu daftar soal hanya diisi bila belum ada sesi berjalan:
            // menekan "Main Lagi" di tengah ronde melanjutkan sisa soal, bukan
            // memulai daftar dari nol.
            const sesiBaru = catatanJawaban.length === 0 || semuaSoalSelesai();

            nyawa = NYAWA_AWAL;
            tungguJawab = false;
            soalTerbuka = false;

            if (sesiBaru) {
                benar = 0;
                salah = 0;
                nomorSoal = 0;
                catatanJawaban = [];
                // Daftar soal disiapkan ulang SETIAP sesi baru.
                tumpukan = acak(soal);
                soalSekarang = null;
            }

            // Kalau ada soal yang masih terbuka (Main Lagi di tengah ronde),
            // tetap tawarkan soal itu.
            game.reset();
            perbaruiStatistik();

            jalan = true;
            btnMulai.classList.add('hidden');
            panelSoal.classList.remove('opacity-50');

            if (soalSekarang) {
                gambarSoal();
            } else {
                tampilkanSoal();
            }

            elPesan.textContent = 'Baca soal, lalu pilih jawaban yang benar!';
            elPesan.className = 'text-sm font-black text-sky-600';

            loopId = requestAnimationFrame(loop);
        }

        // -----------------------------------------------------------------
        // Papan hasil: perayaan + arahan ke soal uraian
        //
        // Permainan hanya separuh ronde. Setelah selesai, anak HARUS diarahkan
        // ke soal uraian ronde tersebut (dinilai AI) — di situ XP misi menjadi
        // penuh. Karena itu papan hasil menampilkan XP yang baru didapat dan
        // memindahkan anak ke soal uraian setelah hitungan mundur singkat.
        // -----------------------------------------------------------------

        // Konfeti ringan tanpa pustaka luar: potongan warna jatuh dari atas.
        function hujanKonfeti() {
            const lapisan = elKonfeti || elHasil;

            if (!lapisan) return;

            const warna = ['#38bdf8', '#34d399', '#fbbf24', '#a78bfa', '#fb7185'];

            for (let i = 0; i < 36; i += 1) {
                const potongan = document.createElement('span');
                potongan.className = 'tik-confetti';
                potongan.style.left = Math.random() * 100 + '%';
                potongan.style.background = warna[i % warna.length];
                potongan.style.animationDelay = (Math.random() * 0.6).toFixed(2) + 's';
                potongan.style.animationDuration = (1.8 + Math.random() * 1.6).toFixed(2) + 's';

                lapisan.appendChild(potongan);

                // Bersihkan setelah animasinya selesai agar tidak menumpuk.
                setTimeout(function () { potongan.remove(); }, 3600);
            }
        }

        function batalHitungMundur() {
            if (hitungId) {
                clearInterval(hitungId);
                hitungId = null;
            }

            if (elHasilHitung) elHasilHitung.textContent = '';
        }

        // Arahkan anak ke soal uraian ronde ini. "Main Lagi" membatalkannya.
        function mulaiHitungMundur(detik) {
            batalHitungMundur();

            if (!linkHasilLanjut) return;

            let sisa = detik;

            function tulis() {
                elHasilHitung.textContent =
                    'Berpindah ke soal uraian dalam ' + sisa + ' detik… ' +
                    'Tekan 🔁 Main Lagi kalau mau mencoba permainannya sekali lagi.';
            }

            tulis();

            hitungId = setInterval(function () {
                sisa -= 1;

                if (sisa <= 0) {
                    batalHitungMundur();
                    window.location.href = linkHasilLanjut.getAttribute('href');

                    return;
                }

                tulis();
            }, 1000);
        }

        // Muatan ringkasan. Dipakai oleh kirimRingkasan(), sendBeacon saat tab
        // ditutup, dan simpanan cadangan di browser.
        //
        // Yang dikirim hanya DAFTAR PILIHAN yang ditekan anak (`jawaban`).
        // Server menilai ulang dengan kunci jawaban misi, jadi angka benar /
        // salah / skor tidak pernah datang dari browser.
        function muatanRingkasan() {
            return {
                url: jawabUrl,
                jawaban: catatanJawaban,
            };
        }

        // Kirim ringkasan permainan ke server (XP & catatan guru).
        // Mengembalikan respons server, atau null bila gagal terkirim.
        async function kirimRingkasan() {
            if (!jawabUrl || catatanJawaban.length === 0) return null;

            const muatan = muatanRingkasan();

            try {
                const res = await fetch(jawabUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ jawaban: muatan.jawaban }),
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);

                // Berhasil: tidak ada yang perlu dikirim ulang.
                window.TikPendingGame?.clear(missionId);

                return await res.json();
            } catch (e) {
                // Gagal terkirim (jaringan/server): simpan dulu supaya XP-nya
                // tidak hilang, lalu dikirim ulang saat halaman dibuka lagi
                // (lihat window.TikPendingGame di app.js).
                window.TikPendingGame?.save(missionId, muatan);

                return null;
            }
        }

        // -----------------------------------------------------------------
        // Ronde berhenti saat anak masih bermain
        // -----------------------------------------------------------------
        // Guru bisa menekan "Hentikan Ronde", atau timer ronde/sesi habis,
        // sementara anak masih bermain. Dalam kasus itu hasil yang sudah
        // dikerjakan dikirim sekarang juga — asal anak sudah menjawab sesuatu —
        // supaya XP-nya tidak hilang. Panel yang muncul adalah "permainan
        // dihentikan", BUKAN "ronde selesai", karena soal belum tentu habis.
        function awasiBerhentinyaRonde() {
            if (!roundUrl) return;

            setInterval(async function () {
                if (!jalan) return;              // hanya perlu saat masih bermain
                if (catatanJawaban.length === 0) return; // belum menjawab apa pun
                if (document.visibilityState === 'hidden') return;

                try {
                    const res = await fetch(roundUrl, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    });

                    if (!res.ok) return;

                    const data = await res.json();

                    if (data.session_ended) {
                        dihentikan('Sesi kelas berakhir');
                        return;
                    }

                    const masihTerbuka = (data.open_rounds || [])
                        .some((r) => parseInt(r.order, 10) === missionOrder);

                    // Server sudah menghitung apakah kelompok ini masih boleh
                    // mengerjakan RONDE INI (`work_allowed`, dikirim karena
                    // halaman game menyertakan ?mission=...). Klien memakai
                    // hasil yang sama supaya tidak ada beda pendapat dengan
                    // server: selama server masih menerima — mis. kelompok
                    // yang masuk terlambat dengan jatah waktunya sendiri —
                    // timer kelas/ronde TIDAK boleh menghentikan permainan.
                    if (data.work_allowed === false) {
                        dihentikan(masihTerbuka ? 'Waktu ronde habis' : 'Ronde dihentikan guru');
                        return;
                    }

                    // Cadangan untuk halaman yang belum mengirim `work_allowed`.
                    if (data.accepts_submissions === false) {
                        dihentikan('Waktu sesi habis');
                        return;
                    }

                    if (!masihTerbuka || data.status === 'ended') {
                        dihentikan('Ronde dihentikan guru');
                        return;
                    }

                    // Timer ronde habis (round_status masih running, tapi waktu habis).
                    if (data.is_running === false) {
                        dihentikan('Waktu ronde habis');
                    }
                } catch (e) {
                    // Jaringan sempat putus; coba lagi siklus berikutnya.
                }
            }, 10000);
        }

        // Permainan dihentikan dari luar (guru/waktu), bukan karena soal habis.
        // Hasil yang sudah dikerjakan tetap dikirim, tapi panel hasil TIDAK
        // mengaku "ronde selesai" — supaya anak/guru tidak salah paham bahwa
        // semua soal sudah tuntas.
        function dihentikan(sebab) {
            if (!jalan) return;

            selesai(sebab, { dihentikan: true });
        }

        // Usaha terakhir saat tab ditutup / halaman ditinggalkan di tengah
        // permainan: titipkan ringkasan lewat sendBeacon, DAN simpan cadangannya
        // di browser. Kalau titipannya gagal, halaman berikutnya yang mengirim.
        window.addEventListener('pagehide', function () {
            if (!jawabUrl || catatanJawaban.length === 0) return;

            const muatan = muatanRingkasan();

            window.TikPendingGame?.save(missionId, muatan);

            try {
                navigator.sendBeacon?.(
                    jawabUrl,
                    new Blob([
                        JSON.stringify({
                            jawaban: muatan.jawaban,
                            _token: csrf,
                        }),
                    ], { type: 'application/json' })
                );
            } catch (e) {
                // Diabaikan; cadangan di localStorage sudah disimpan.
            }
        });

        function tampilkanHasil(sebab, dihentikanGuru) {
            const total = benar + salah;
            const akurasi = total > 0 ? Math.round((benar / total) * 100) : 0;

            // Bintang mengikuti akurasi: 90% ke atas dapat tiga bintang.
            const bintang = akurasi >= 90 ? 3 : (akurasi >= 70 ? 2 : 1);

            // Jumlah soal di ronde ini: kalau tidak dihentikan, semua soal sudah
            // dijawab benar; kalau dihentikan, sisa soal mungkin masih ada.
            const totalSoal = soal.length;
            const sisa = dihentikanGuru ? sisaSoal() : 0;

            if (elHasilBintang) {
                elHasilBintang.textContent = dihentikanGuru
                    ? '⏸️'
                    : '⭐'.repeat(bintang) + '☆'.repeat(3 - bintang);
            }

            if (elHasilJudul) {
                if (dihentikanGuru) {
                    // Jangan mengaku "selesai" kalau soal belum tentu habis.
                    elHasilJudul.textContent = 'Permainan dihentikan';
                    elHasilJudul.className = 'mt-0.5 text-lg font-black text-sun-500';
                } else {
                    elHasilJudul.textContent = bintang === 3
                        ? 'Luar biasa! Ronde selesai!'
                        : (bintang === 2 ? 'Bagus! Ronde selesai!' : 'Ronde selesai!');
                    elHasilJudul.className = 'mt-0.5 text-lg font-black text-mint-600';
                }
            }

            if (elHasilSebab) {
                elHasilSebab.textContent = dihentikanGuru
                    ? sebab + ' · kamu menjawab benar ' + benar + ' soal' +
                      (sisa > 0 ? ', masih ada ' + sisa + ' soal belum selesai.' : '.')
                    : sebab + ' · jawaban benar ' + benar + ' dari ' + totalSoal + ' soal';
            }

            if (elHasilBenar) {
                elHasilBenar.textContent = dihentikanGuru
                    ? benar + ' soal'
                    : benar + '/' + totalSoal;
            }

            if (elHasilAkurasi) elHasilAkurasi.textContent = akurasi + '%';
            if (elHasilSkor) elHasilSkor.textContent = String(game.skor);
            if (elHasilXp) elHasilXp.textContent = '…';

            if (elHasil) elHasil.hidden = false;

            if (!dihentikanGuru) hujanKonfeti();

            // XP baru diketahui setelah server mencatat hasilnya. Angka yang
            // ditampilkan di sini adalah hasil PENILAIAN SERVER, bukan hitungan
            // browser — jadi papan hasil tidak bisa "diakali" dari DevTools.
            kirimRingkasan().then(function (data) {
                if (data && typeof data.xp_gain === 'number') {
                    // Selaraskan tampilan dengan hasil resmi server.
                    const totalResmi = data.total;
                    const benarResmi = data.benar;
                    const akurasiResmi = totalResmi > 0
                        ? Math.round((benarResmi / totalResmi) * 100)
                        : 0;

                    if (elHasilBenar && !dihentikanGuru) {
                        elHasilBenar.textContent = benarResmi + '/' + totalSoal;
                    }
                    if (elHasilAkurasi) elHasilAkurasi.textContent = akurasiResmi + '%';
                    if (elHasilSkor) elHasilSkor.textContent = String(data.skor);

                    if (elHasilXp) elHasilXp.textContent = '+' + data.xp_gain + ' XP';

                    if (elHasilXpRincian) {
                        elHasilXpRincian.textContent = dihentikanGuru
                            ? 'Hasil yang sudah kamu kerjakan sudah tersimpan. Buka lagi ronde ini saat guru membukanya untuk melanjutkan sisa soal.'
                            : 'Total XP misi ini ' + data.xp + ' · total XP kelompok ' + data.total_xp +
                              '. Tulis soal uraian ronde ini untuk menambah XP sampai maksimum misi.';
                    }

                    return;
                }

                if (elHasilXp) elHasilXp.textContent = '—';

                if (elHasilXpRincian) {
                    elHasilXpRincian.textContent =
                        'Hasil belum tercatat di server (jaringan?). Muat ulang halaman ini supaya XP-nya tersimpan.';
                }
            });

            // Hanya arahkan pindah ke soal uraian bila ronde memang benar-benar
            // tuntas. Kalau dihentikan, biarkan anak di halaman ini.
            if (!dihentikanGuru) {
                mulaiHitungMundur(10);
            }
        }

        // Dipanggil ketika permainan selesai / dihentikan.
        //   - `opsi.dihentikan` = true -> permainan dihentikan guru / waktu habis
        //     (soal belum tentu habis).
        //   - selain itu -> SEMUA soal sudah dijawab benar (ronde benar-benar tuntas).
        //
        // Kehabisan nyawa TIDAK memanggil fungsi ini lagi.
        function selesai(sebab, opsi) {
            const dihentikanGuru = !!(opsi && opsi.dihentikan);

            jalan = false;
            soalTerbuka = false;
            soalSekarang = null;

            if (loopId) {
                cancelAnimationFrame(loopId);
                loopId = null;
            }

            game.gambar();

            panelSoal.classList.add('opacity-50');
            kunciPilihan(true);

            // Tombol mulai digantikan papan hasil (tombol Main Lagi ada di sana).
            btnMulai.classList.add('hidden');

            if (dihentikanGuru) {
                elPesan.textContent = '⏸️ Permainan dihentikan (' + sebab + ').';
                elPesan.className = 'text-sm font-black text-sun-500';
                window.TikSound && window.TikSound.play('error');
                window.TikToast &&
                    window.TikToast('Permainan dihentikan. Hasil yang sudah kamu kerjakan tetap dicatat.', 'warning');
            } else {
                elPesan.textContent = '🏁 Ronde selesai (' + sebab + ').';
                elPesan.className = 'text-sm font-black text-ink-800';
                window.TikSound && window.TikSound.play('complete');
                window.TikToast && window.TikToast('Ronde selesai! Lanjut ke soal uraian ya.', 'info');
            }

            tampilkanHasil(sebab, dihentikanGuru);
        }

        // -----------------------------------------------------------------
        // Kendali
        // -----------------------------------------------------------------
        btnMulai.addEventListener('click', mulaiMain);

        // "Main Lagi" dari papan hasil: memulai sesi permainan BARU — daftar
        // soal diacak ulang dari awal, skor main nol, dan catatan jawaban
        // dikosongkan (karena kiriman sebelumnya sudah dinilai & terkirim).
        if (btnHasilUlang) {
            btnHasilUlang.addEventListener('click', function () {
                if (elHasil) elHasil.hidden = true;
                batalHitungMundur();

                // Tandai sesi selesai supaya mulaiMain() menyiapkan sesi baru.
                benar = 0;
                salah = 0;
                catatanJawaban = [];
                tumpukan = [];
                soalSekarang = null;

                mulaiMain();
            });
        }

        // "Tutup hasil": lihat papan permainan lagi tanpa memulai ulang dan
        // tanpa menunggu hitungan mundur. Ronde tetap dianggap selesai.
        if (btnHasilTutup) {
            btnHasilTutup.addEventListener('click', function () {
                if (elHasil) elHasil.hidden = true;
                batalHitungMundur();

                // Kalau ringkasan tadi gagal terkirim, coba lagi sekarang.
                window.TikPendingGame?.flush();
            });
        }

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

        // Pantau apakah ronde masih berjalan (guru bisa menghentikannya).
        awasiBerhentinyaRonde();

        // Gambar papan kosong sebagai pratinjau awal.
        game.gambar();
        perbaruiStatistik();
    }

    document.addEventListener('DOMContentLoaded', mulai);
})();
