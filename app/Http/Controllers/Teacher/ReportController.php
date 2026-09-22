<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Mission;
use App\Models\Team;
use App\Models\TeamProgress;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan hasil belajar per kelompok + export CSV.
 */
class ReportController extends Controller
{
    /**
     * Tampilan laporan.
     */
    public function index(Request $request)
    {
        $sessionFilter = $request->query('session_id');

        $sessionOptions = GameSession::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code']);

        $totalMissions = Mission::query()->active()->count();

        $teams = Team::query()
            ->with(['gameSession', 'members'])
            ->when($sessionFilter, fn ($q) => $q->where('game_session_id', $sessionFilter))
            ->withCount([
                'progress as completed_count' => fn ($q) => $q->where('status', TeamProgress::STATUS_COMPLETED),
            ])
            ->withSum('progress as total_hints', 'hints_used')
            ->orderByDesc('xp')
            ->get();

        return view('teacher.report.index', compact(
            'teams', 'sessionOptions', 'sessionFilter', 'totalMissions'
        ));
    }

    /**
     * Export CSV laporan.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $sessionFilter = $request->query('session_id');

        $teams = Team::query()
            ->with(['gameSession', 'members'])
            ->when($sessionFilter, fn ($q) => $q->where('game_session_id', $sessionFilter))
            ->withCount([
                'progress as completed_count' => fn ($q) => $q->where('status', TeamProgress::STATUS_COMPLETED),
            ])
            ->withSum('progress as total_hints', 'hints_used')
            ->orderByDesc('xp')
            ->get();

        $totalMissions = Mission::query()->active()->count();
        $filename = 'laporan-tik-mission-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($teams, $totalMissions) {
            $out = fopen('php://output', 'w');

            // BOM agar Excel membaca UTF-8 dengan benar.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Nama Kelompok',
                'Sesi',
                'Kode Sesi',
                'Anggota',
                'Misi Selesai',
                'Total Misi',
                'XP',
                'Petunjuk Dipakai',
                'Waktu Mulai',
                'Waktu Selesai',
                'Status Akhir',
            ], ';');

            foreach ($teams as $team) {
                $completed = $team->completed_count;
                $isDone = $completed >= $totalMissions && $totalMissions > 0;

                fputcsv($out, [
                    $team->name,
                    $team->gameSession->name ?? '-',
                    $team->gameSession->code ?? '-',
                    $team->members->pluck('name')->implode(', '),
                    $completed,
                    $totalMissions,
                    $team->xp,
                    (int) ($team->total_hints ?? 0),
                    $team->started_at?->format('d/m/Y H:i') ?? '-',
                    $team->completed_at?->format('d/m/Y H:i') ?? '-',
                    $isDone ? 'Selesai' : 'Belum Selesai',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
