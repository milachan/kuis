<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guru dapat melihat dan mengubah isi misi (instruksi, XP, petunjuk).
 * Misal: guru ingin menyesuaikan misi dengan materi kelasnya.
 */
class MissionController extends Controller
{
    /**
     * Daftar misi.
     */
    public function index()
    {
        $missions = Mission::query()->ordered()->get();

        return view('teacher.missions.index', compact('missions'));
    }

    /**
     * Form edit misi.
     */
    public function edit(Mission $mission)
    {
        return view('teacher.missions.edit', compact('mission'));
    }

    /**
     * Update misi.
     */
    public function update(Request $request, Mission $mission)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'difficulty' => ['required', Rule::in(['Mudah', 'Sedang', 'Sulit'])],
            'xp' => ['required', 'integer', 'min:0', 'max:1000'],
            'story' => ['required', 'string', 'max:2000'],
            'objective' => ['required', 'string', 'max:2000'],
            'instructions' => ['required', 'string', 'max:8000'],
            'code_prompt' => ['nullable', 'string', 'max:500'],
            'hint_1' => ['nullable', 'string', 'max:1000'],
            'hint_2' => ['nullable', 'string', 'max:1000'],
            'reflection_question' => ['nullable', 'string', 'max:500'],
            'requires_pdf' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Judul misi wajib diisi.',
            'xp.required' => 'XP wajib diisi.',
            'story.required' => 'Cerita misi wajib diisi.',
            'objective.required' => 'Tujuan pembelajaran wajib diisi.',
            'instructions.required' => 'Instruksi praktik wajib diisi.',
        ]);

        // Instruksi diketik satu baris per langkah di textarea.
        $instructions = collect(preg_split('/\r\n|\r|\n/', $validated['instructions']))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $mission->update([
            'title' => $validated['title'],
            'difficulty' => $validated['difficulty'],
            'xp' => $validated['xp'],
            'story' => $validated['story'],
            'objective' => $validated['objective'],
            'instructions' => $instructions,
            'code_prompt' => $validated['code_prompt'] ?? null,
            'hint_1' => $validated['hint_1'] ?? null,
            'hint_2' => $validated['hint_2'] ?? null,
            'reflection_question' => $validated['reflection_question'] ?? null,
            'requires_pdf' => $request->boolean('requires_pdf'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('teacher.missions')
            ->with('success', 'Misi "'.$mission->title.'" berhasil diperbarui.');
    }
}
