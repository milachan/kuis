<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Membuat file praktik awal untuk siswa di storage/app/public/practice.
 *
 * File DOCX/XLSX/PPTX sebenarnya adalah arsip ZIP berisi XML.
 * Seeder ini membuat file .docx dan .xlsx minimal yang bisa dibuka
 * Microsoft Office / LibreOffice, sehingga guru tidak harus menyiapkan
 * file dari nol. Guru dapat menggantinya dengan file versi sendiri.
 */
class PracticeFileSeeder extends Seeder
{
    public function run(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->command?->warn(
                'Ekstensi PHP "zip" tidak aktif. File praktik dilewati. '.
                'Aktifkan extension=zip lalu jalankan ulang seeder ini.'
            );

            return;
        }

        $folder = config('tikmission.practice_folder');

        Storage::disk('public')->makeDirectory($folder);

        $this->createDocx($folder.'/mission-01-format.docx', $this->mission1Paragraphs());
        $this->createDocx($folder.'/mission-02-word.docx', $this->mission2Paragraphs());
        $this->createDocx($folder.'/mission-03-screenshot.docx', $this->mission3Paragraphs());

        $this->createXlsx($folder.'/mission-02-data.xlsx');
        $this->createPptx($folder.'/mission-02-slide.pptx');
        $this->createReportAssetsZip($folder.'/mission-04-report-assets.zip');

        // Catatan untuk guru tentang file praktik.
        Storage::disk('public')->put($folder.'/README-FILE-PRAKTIK.txt', $this->readme());

        $this->command?->info('File praktik dibuat di storage/app/public/'.$folder);
    }

    /**
     * Bangun file DOCX minimal dari daftar paragraf.
     */
    protected function createDocx(string $relativePath, array $paragraphs): void
    {
        $body = '';

        foreach ($paragraphs as $p) {
            $text = htmlspecialchars($p, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $body .= '<w:p><w:r><w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';
        }

        $document = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>
{$body}
<w:sectPr><w:pgSz w:w="11906" w:h="16838"/></w:sectPr>
</w:body>
</w:document>
XML;

        $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $rels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $this->writeZip($relativePath, [
            '[Content_Types].xml' => $contentTypes,
            '_rels/.rels' => $rels,
            'word/document.xml' => $document,
        ]);
    }

    /**
     * Bangun file XLSX minimal berisi tabel data rahasia.
     */
    protected function createXlsx(string $relativePath): void
    {
        // Baris: [Kode, Nama Dokumen, Status, Petunjuk]
        $rows = [
            ['KODE', 'NAMA DOKUMEN', 'STATUS', 'PETUNJUK'],
            ['DOC-01', 'Laporan Format', 'TERBUKA', 'Periksa bagian header'],
            ['DOC-02', 'Data Clipboard', 'TERBUKA', 'Copy tabel ini ke Word'],
            ['DOC-03', 'Berkas Screenshot', 'TERKUNCI', 'Gunakan Windows + Shift + S'],
            ['DOC-04', 'Laporan Akhir', 'TERKUNCI', 'Perlu ekspor PDF'],
        ];

        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach ($row as $colIndex => $value) {
                $col = chr(65 + $colIndex); // A, B, C, D
                $cellRef = $col.($rowIndex + 1);
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
            }
            $sheetRows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        $sheet = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>{$sheetRows}</sheetData>
</worksheet>
XML;

        $workbook = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Data Rahasia" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML;

        $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;

        $rels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;

        $workbookRels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;

        $this->writeZip($relativePath, [
            '[Content_Types].xml' => $contentTypes,
            '_rels/.rels' => $rels,
            'xl/workbook.xml' => $workbook,
            'xl/_rels/workbook.xml.rels' => $workbookRels,
            'xl/worksheets/sheet1.xml' => $sheet,
        ]);
    }

    /**
     * Bangun file PPTX minimal berisi satu slide.
     */
    protected function createPptx(string $relativePath): void
    {
        $slideText = 'Operasi Clipboard — Data Rahasia';

        $slide = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<p:cSld><p:spTree>
<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
<p:grpSpPr/>
<p:sp>
<p:nvSpPr><p:cNvPr id="2" name="Teks"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>
<p:spPr><a:xfrm><a:off x="914400" y="1828800"/><a:ext cx="7315200" cy="1143000"/></a:xfrm></p:spPr>
<p:txBody><a:bodyPr/><a:lstStyle/>
<a:p><a:r><a:rPr lang="id-ID" sz="2800"/><a:t>{$slideText}</a:t></a:r></a:p>
</p:txBody></p:sp>
</p:spTree></p:cSld>
</p:sld>
XML;

        $presentation = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
                xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst>
<p:sldSz cx="9144000" cy="6858000"/><p:notesSz cx="6858000" cy="9144000"/>
</p:presentation>
XML;

        $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
<Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>
</Types>
XML;

        $rels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML;

        $presentationRels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML;

        $this->writeZip($relativePath, [
            '[Content_Types].xml' => $contentTypes,
            '_rels/.rels' => $rels,
            'ppt/presentation.xml' => $presentation,
            'ppt/_rels/presentation.xml.rels' => $presentationRels,
            'ppt/slides/slide1.xml' => $slide,
        ]);
    }

    /**
     * Buat paket bahan laporan Misi 04 (teks, tabel CSV, dan gambar PNG).
     */
    protected function createReportAssetsZip(string $relativePath): void
    {
        $teks = <<<'TXT'
BAHAN LAPORAN MISI 04 — OPERASI LAPORAN
=======================================

Judul laporan: Pemanfaatan Teknologi Informasi dan Komunikasi di Sekolah

Paragraf penjelasan (salin lalu rapikan):
Teknologi informasi dan komunikasi membantu kegiatan belajar di sekolah.
Dengan aplikasi pengolah kata, siswa dapat menyusun laporan yang rapi.
Aplikasi spreadsheet membantu menghitung data, sedangkan presentasi
membantu menyampaikan hasil kerja kepada teman sekelas.
Gunakan paragraf ini sebagai isi laporan kalian, lalu rapikan formatnya.

Langkah berikutnya:
1. Buat dokumen baru dan tulis judul laporan.
2. Tulis nama kelompok dan anggota.
3. Tempel paragraf penjelasan di atas.
4. Sisipkan tabel dari file "tabel-data.csv".
5. Sisipkan gambar dari file "gambar-pendukung.png".
6. Tambahkan header, footer, dan nomor halaman.
7. Simpan sebagai DOCX, lalu ekspor menjadi PDF.
TXT;

        $tabel = <<<'CSV'
No,Kegiatan,Peralatan yang Digunakan,Keterangan
1,Menulis laporan,Pengolah kata,Selesai
2,Mengolah data,Spreadsheet,Selesai
3,Membuat presentasi,Aplikasi presentasi,Selesai
4,Mengambil bukti,Snipping Tool,Selesai
5,Menggabungkan laporan,Pengolah kata,Selesai
CSV;

        $this->writeZip($relativePath, [
            'bahan-teks.txt' => $teks,
            'tabel-data.csv' => $tabel,
            'gambar-pendukung.png' => $this->simplePng(),
        ]);
    }

    /**
     * Buat gambar PNG sederhana (80x80) memakai GD agar siswa punya gambar nyata.
     */
    protected function simplePng(): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            // Fallback: PNG 1x1 transparan bila GD tidak tersedia.
            return base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
            );
        }

        $size = 160;
        $img = imagecreatetruecolor($size, $size);

        $navy = imagecolorallocate($img, 10, 20, 40);
        $cyan = imagecolorallocate($img, 34, 211, 238);
        $gold = imagecolorallocate($img, 251, 191, 36);
        $white = imagecolorallocate($img, 255, 255, 255);

        imagefill($img, 0, 0, $navy);

        // Kotak aksen cyan dan gold sebagai ornamen.
        imagefilledrectangle($img, 16, 16, 144, 144, $cyan);
        imagefilledrectangle($img, 34, 34, 126, 126, $navy);
        imagefilledellipse($img, 80, 80, 70, 70, $gold);

        // Teks "TIK" di tengah.
        imagestring($img, 5, 62, 74, 'TIK', $navy);

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }

    /**
     * Tulis sekumpulan file ke dalam satu arsip ZIP di disk publik.
     *
     * @param  array<string, string>  $files
     */
    protected function writeZip(string $relativePath, array $files): void
    {
        $disk = Storage::disk('public');
        $absolute = $disk->path($relativePath);

        // Pastikan folder tujuan ada.
        $dir = dirname($absolute);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();
    }

    // -----------------------------------------------------------------
    // Isi teks file praktik
    // -----------------------------------------------------------------

    protected function mission1Paragraphs(): array
    {
        return [
            'DOKUMEN RAHASIA — OPERASI FILE',
            'Dokumen ini sengaja dibuat dengan format yang berantakan. Tugas kalian adalah merapikannya sesuai instruksi misi.',
            'Kata kunci pertama adalah RAHASIA. Kata ini harus dibuat Bold saat kalian mengerjakan misi.',
            'Kata kunci kedua adalah DIGITAL. Kata ini harus diberi Highlight warna pada dokumen ini.',
            'Simpan hasil pekerjaan kalian menggunakan Save As, bukan Save biasa.',
            'Setelah semua format diperbaiki, dokumen ini akan menjadi bukti bahwa kalian memahami objek dokumen.',
        ];
    }

    protected function mission2Paragraphs(): array
    {
        return [
            'INFORMASI DATA CLIPBOARD',
            'Paragraf ini harus kalian Copy ke dalam slide PowerPoint pada Misi 02.',
            'Setelah di-Copy, paragraf asli di dokumen ini akan tetap ada. Inilah perbedaan Copy dengan Cut.',
            'Copy berarti menggandakan objek, sedangkan Cut memindahkan objek dari tempat asalnya.',
            'Paste adalah perintah untuk menempelkan objek yang tersimpan di clipboard.',
        ];
    }

    protected function mission3Paragraphs(): array
    {
        return [
            'BERKAS SCREENSHOT INVESTIGATOR',
            'Dokumen ini berisi area yang harus kalian potret memakai Windows + Shift + S.',
            'Screenshot yang baik hanya mengambil bagian yang dibutuhkan, tidak seluruh layar.',
            'Sisipkan hasil screenshot kalian kembali ke dalam dokumen ini sebagai bukti.',
            'Setelah selesai, simpan dokumen dan unggah hasil akhirnya.',
        ];
    }

    protected function readme(): string
    {
        return <<<'TXT'
FILE PRAKTIK — TIK MISSION
==========================

Folder ini berisi file awal untuk siswa. Guru dapat mengganti
file di folder ini dengan file versi sendiri (nama file harus sama).

Daftar file:
1. mission-01-format.docx      -> Misi 01 Operasi Format
2. mission-02-word.docx        -> Misi 02 sumber Copy (Word)
3. mission-02-data.xlsx        -> Misi 02 tabel sumber (Excel)
4. mission-02-slide.pptx       -> Misi 02 tujuan Paste (PowerPoint)
5. mission-03-screenshot.docx  -> Misi 03 dokumen kerja
6. mission-04-report-assets.zip -> Misi 04 bahan laporan (teks, tabel, gambar)

Cara mengganti file:
1. Siapkan file Word/Excel/PowerPoint kalian.
2. Beri nama file sama seperti daftar di atas.
3. Timpa file di folder storage/app/public/practice/
4. Tidak perlu mengubah kode aplikasi.

Cara siswa mengunduh:
- File dapat diunduh langsung dari URL:
  https://domain-anda.com/storage/practice/nama-file.docx
- Bagikan tautan ini melalui Google Classroom / WhatsApp kelas.

Catatan: file contoh dibuat otomatis oleh PracticeFileSeeder (struktur minimal).
Untuk aktivitas kelas yang lengkap, guru sebaiknya menyiapkan file versi sendiri.
TXT;
    }
}
