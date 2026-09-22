<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Team;
use App\Services\RoundService;
use App\Services\StudentAuthService;
use App\Services\TeamService;
use Illuminate\Http\Request;

/**
 * Masuk siswa memakai kode sesi + nama kelompok + nama anggota.
 * Tidak ada registrasi akun.
 */
class JoinController extends Controller
{
    public function __construct(
        protected StudentAuthService $studentAuth,
        protected TeamService $teams,
        protected RoundService $rounds,
    ) {}

    /**
     * Halaman awal "TIK MISSION".
     */
    public function landing()
    {
        // Jika sudah masuk, langsung ke dashboard misi.
        if ($this->studentAuth->check()) {
            return redirect()->route('student.dashboard');
        }

        return view('student.landing');
    }

    /**
     * Form masuk (kode sesi + kelompok + anggota).
     */
    public function showJoinForm(Request $request)
    {
        if ($this->studentAuth->check()) {
            return redirect()->route('student.dashboard');
        }

        // Dukung tautan langsung dengan ?code=XXXX
        $prefillCode = $request->query('code');

        return view('student.join', compact('prefillCode'));
    }

    /**
     * Proses masuk siswa.
     */
    public function join(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'team_name' => ['required', 'string', 'max:80'],
            'members' => ['required', 'array', 'min:1', 'max:10'],
            'members.*' => ['nullable', 'string', 'max:80'],
        ], [
            'code.required' => 'Kode sesi wajib diisi.',
            'team_name.required' => 'Nama kelompok wajib diisi.',
            'team_name.max' => 'Nama kelompok terlalu panjang (maksimal 80 karakter).',
            'members.required' => 'Isi minimal satu nama anggota.',
            'members.min' => 'Isi minimal satu nama anggota.',
        ]);

        // Cari sesi berdasarkan kode (tanpa membedakan huruf besar/kecil).
        $session = GameSession::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($validated['code']))])
            ->first();

        if (! $session) {
            return back()
                ->withInput()
                ->with('error', 'Kode sesi tidak ditemukan. Periksa kembali kode dari gurumu.');
        }

        if ($session->isEnded()) {
            return back()
                ->withInput()
                ->with('error', 'Sesi ini sudah berakhir. Hubungi gurumu.');
        }

        // Anggota yang benar-benar terisi.
        $members = collect($validated['members'])
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            ->values();

        // Ingatkan (tidak menolak) bila format "Nama Lengkap - No Absen"
        // belum dipakai, supaya guru mudah mencocokkan dengan daftar kelas.
        $tanpaFormat = $members->reject(
            fn ($m) => (bool) preg_match('/-\s*\d+\s*$/', $m)
        );

        $members = $members->all();

        if (empty($members)) {
            return back()
                ->withInput()
                ->with('error', 'Isi minimal satu nama anggota.');
        }

        if ($tanpaFormat->isNotEmpty()) {
            session()->flash('warning',
                'Nama anggota sebaiknya ditulis dengan format "Nama Lengkap - No Absen", '
                .'contoh: Ahmad Rizki Pratama - 07.');
        }

        $teamName = trim($validated['team_name']);

        // Kelompok dengan nama sama di sesi yang sama akan dipakai ulang
        // (agar siswa yang tidak sengaja keluar bisa masuk kembali).
        $team = Team::query()
            ->where('game_session_id', $session->id)
            ->where('name', $teamName)
            ->first();

        // Saat ronde berjalan, lobby dikunci: hanya kelompok yang sudah ada
        // yang boleh masuk kembali, kelompok baru diblokir.
        if (! $team && ! $this->rounds->lobbyIsOpen($session)) {
            // Diarahkan ke rute form secara eksplisit (bukan back()) agar
            // halaman tujuan pasti dan tidak memantul dari form yang basi.
            return redirect()
                ->route('student.join')
                ->withInput()
                ->with('error', 'Ronde sedang berjalan sehingga kelompok baru belum bisa bergabung. '
                    .'Tunggu aba-aba guru, atau minta guru membuka lobi.');
        }

        if ($team) {
            // Perbarui daftar anggota jika berbeda.
            $this->teams->syncMembers($team, $members);

            // Pastikan progres misi tetap lengkap.
            session()->flash('info', 'Selamat datang kembali, '.$team->name.'.');
        } else {
            $team = $this->teams->create($session, $teamName, $members);
            session()->flash('success', 'Kelompok '.$team->name.' berhasil masuk. Selamat mengerjakan misi!');
        }

        $this->studentAuth->login($team);

        return redirect()->route('student.dashboard');
    }

    /**
     * Keluar dari identitas kelompok.
     */
    public function leave()
    {
        $this->studentAuth->logout();

        return redirect()->route('student.landing')
            ->with('success', 'Kamu telah keluar dari sesi kelompok.');
    }
}
