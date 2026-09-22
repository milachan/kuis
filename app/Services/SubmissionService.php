<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamProgress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menangani pengiriman tugas siswa: simpan jawaban, unggah bukti,
 * dan ubah status misi menjadi WAITING VALIDATION.
 */
class SubmissionService
{
    /**
     * Simpan kiriman siswa untuk sebuah misi.
     *
     * @param  array{answer?: ?string, evidence?: ?UploadedFile, file?: ?UploadedFile}  $data
     */
    public function store(Team $team, TeamProgress $progress, array $data): Submission
    {
        return DB::transaction(function () use ($team, $progress, $data) {
            $submission = Submission::query()->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'mission_id' => $progress->mission_id,
                ],
                [
                    'status' => Submission::STATUS_WAITING,
                ]
            );

            // Simpan jawaban teks (boleh kosong bila misi hanya butuh bukti).
            if (array_key_exists('answer', $data)) {
                $submission->answer = $data['answer'];
            }

            // Unggah bukti screenshot.
            if (! empty($data['evidence'])) {
                $this->deleteStored($submission->evidence_path);
                $submission->evidence_path = $this->storeFile(
                    $data['evidence'],
                    $team,
                    'evidence'
                );
            }

            // Unggah file tambahan (mis. PDF hasil ekspor).
            if (! empty($data['file'])) {
                $this->deleteStored($submission->file_path);
                $submission->file_path = $this->storeFile($data['file'], $team, 'file');
            }

            $submission->status = Submission::STATUS_WAITING;
            $submission->teacher_comment = null;
            $submission->submitted_at = now();
            $submission->validated_at = null;
            $submission->save();

            // Misi berpindah ke status menunggu validasi.
            $progress->update([
                'status' => TeamProgress::STATUS_WAITING_VALIDATION,
            ]);

            return $submission;
        });
    }

    /**
     * Simpan file dengan nama acak (mencegah tabrakan nama & path traversal).
     * Format: {folder}/{team_id}/{random32}_{nama-asli-yang-dibersihkan}
     */
    protected function storeFile(UploadedFile $file, Team $team, string $type): string
    {
        $folder = $type === 'evidence'
            ? config('tikmission.evidence_folder')
            : config('tikmission.evidence_folder').'/files';

        $extension = strtolower($file->getClientOriginalExtension());

        // Bersihkan nama asli: hanya huruf, angka, dash, underscore, dan titik.
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = Str::slug($originalName);
        $safeName = $safeName !== '' ? $safeName : 'bukti';

        $filename = Str::random(32).'_'.$safeName.'.'.$extension;

        $path = $folder.'/'.$team->id;

        $file->storeAs($path, $filename, 'public');

        return $path.'/'.$filename;
    }

    /**
     * Hapus file lama dengan aman (abaikan jika tidak ada).
     */
    protected function deleteStored(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Hapus semua bukti milik sebuah kelompok (dipakai saat reset progres).
     */
    public function deleteAllForTeam(Team $team): void
    {
        foreach ($team->submissions as $submission) {
            $this->deleteStored($submission->evidence_path);
            $this->deleteStored($submission->file_path);
        }

        $team->submissions()->delete();
    }
}
