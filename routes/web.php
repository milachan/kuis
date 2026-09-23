<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Student\JoinController;
use App\Http\Controllers\Student\MissionController as StudentMissionController;
use App\Http\Controllers\Teacher\DashboardController;
use App\Http\Controllers\Teacher\MissionController as TeacherMissionController;
use App\Http\Controllers\Teacher\ReportController;
use App\Http\Controllers\Teacher\RoundController;
use App\Http\Controllers\Teacher\TeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman Awal
|--------------------------------------------------------------------------
| Halaman awal aplikasi adalah gerbang siswa menuju misi.
*/

Route::get('/', [JoinController::class, 'landing'])->name('home');

/*
|--------------------------------------------------------------------------
| Autentikasi Guru
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| SISWA (tanpa akun — kode sesi + nama kelompok)
|--------------------------------------------------------------------------
*/

Route::prefix('student')->name('student.')->group(function () {
    // Gerbang masuk siswa.
    Route::get('/join', [JoinController::class, 'showJoinForm'])->name('join');
    Route::post('/join', [JoinController::class, 'join'])
        ->middleware('throttle:20,1')
        ->name('join.attempt');

    // Halaman awal siswa (juga dipakai sebagai landing 'student').
    Route::get('/', [JoinController::class, 'landing'])->name('landing');
    Route::post('/leave', [JoinController::class, 'leave'])->name('leave');

    // Area yang membutuhkan identitas kelompok.
    Route::middleware('student')->group(function () {
        Route::get('/dashboard', [StudentMissionController::class, 'dashboard'])->name('dashboard');
        // Halaman menunggu ronde berikutnya dibuka guru.
        Route::get('/waiting', [StudentMissionController::class, 'waiting'])->name('waiting');
        // Status ronde aktif: dipakai halaman misi agar otomatis berpindah
        // ke ronde yang baru dibuka guru.
        Route::get('/round-status', [StudentMissionController::class, 'roundStatus'])->name('round.status');
        Route::get('/mission/{mission}', [StudentMissionController::class, 'show'])->name('mission.show');
        // Game arcade belajar (soal jadi mekanik permainan).
        Route::get('/mission/{mission}/game', [StudentMissionController::class, 'game'])->name('mission.game');
        Route::post('/mission/{mission}/game-answer', [StudentMissionController::class, 'gameAnswer'])->name('mission.game.answer');
        Route::post('/mission/{mission}/submit', [StudentMissionController::class, 'submit'])
            ->middleware('throttle:20,1')
            ->name('mission.submit');
        Route::post('/mission/{mission}/hint', [StudentMissionController::class, 'hint'])
            ->middleware('throttle:30,1')
            ->name('mission.hint');
    });
});

/*
|--------------------------------------------------------------------------
| GURU / ADMIN
|--------------------------------------------------------------------------
*/

Route::prefix('teacher')->name('teacher.')->middleware(['auth', 'teacher'])->group(function () {

    // Dashboard ringkasan.
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Sesi permainan.
    Route::get('/sessions', [DashboardController::class, 'sessions'])->name('sessions.index');
    Route::get('/sessions/create', [DashboardController::class, 'createSession'])->name('sessions.create');
    Route::post('/sessions', [DashboardController::class, 'storeSession'])->name('sessions.store');
    Route::get('/sessions/{session}', [DashboardController::class, 'showSession'])->name('sessions.show');
    Route::get('/sessions/{session}/edit', [DashboardController::class, 'editSession'])->name('sessions.edit');
    Route::put('/sessions/{session}', [DashboardController::class, 'updateSession'])->name('sessions.update');
    Route::post('/sessions/{session}/end', [DashboardController::class, 'endSession'])->name('sessions.end');
    Route::post('/sessions/{session}/reopen', [DashboardController::class, 'reopenSession'])->name('sessions.reopen');
    Route::delete('/sessions/{session}', [DashboardController::class, 'destroySession'])->name('sessions.destroy');

    // Mode permainan: layar proyektor + kendali ronde.
    Route::get('/sessions/{session}/screen', [RoundController::class, 'screen'])->name('sessions.screen');
    Route::get('/sessions/{session}/live', [RoundController::class, 'live'])->name('sessions.live');
    // Satu aksi utama: buka ronde berikutnya (ronde lama ditutup otomatis).
    Route::post('/sessions/{session}/round/next', [RoundController::class, 'next'])->name('sessions.round.next');
    // Buka ronde tertentu (bisa mundur ke ronde sebelumnya).
    Route::post('/sessions/{session}/round/open', [RoundController::class, 'openRound'])->name('sessions.round.open');
    // Buka BEBERAPA ronde sekaligus (guru mencentang ronde mana saja).
    Route::post('/sessions/{session}/rounds/open', [RoundController::class, 'openRounds'])->name('sessions.rounds.open');
    // Tutup semua ronde yang sedang terbuka.
    Route::post('/sessions/{session}/rounds/close', [RoundController::class, 'closeRounds'])->name('sessions.rounds.close');
    Route::post('/sessions/{session}/round/start', [RoundController::class, 'start'])->name('sessions.round.start');
    Route::post('/sessions/{session}/round/end', [RoundController::class, 'end'])->name('sessions.round.end');

    // Kendali jam kelas: guru bisa menambah waktu atau mematikan batasnya.
    Route::post('/sessions/{session}/time/extend', [RoundController::class, 'extendTime'])->name('sessions.time.extend');
    Route::post('/sessions/{session}/time/unlimited', [RoundController::class, 'removeTimeLimit'])->name('sessions.time.unlimited');
    Route::post('/sessions/{session}/round/reset', [RoundController::class, 'reset'])->name('sessions.round.reset');
    Route::post('/sessions/{session}/lobby', [RoundController::class, 'toggleLobby'])->name('sessions.lobby');

    // Kelompok.
    Route::get('/teams', [TeamController::class, 'index'])->name('teams');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::post('/teams/{team}/reset', [TeamController::class, 'reset'])->name('teams.reset');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('/teams/{team}/unlock', [TeamController::class, 'unlock'])->name('teams.unlock');
    Route::post('/teams/{team}/lock', [TeamController::class, 'lock'])->name('teams.lock');

    // Validasi bukti siswa.
    Route::get('/validations', [TeamController::class, 'validations'])->name('validations');
    Route::get('/validations/{submission}', [TeamController::class, 'showValidation'])->name('validations.show');
    Route::post('/validations/{submission}/approve', [TeamController::class, 'approve'])->name('validations.approve');
    Route::post('/validations/{submission}/revision', [TeamController::class, 'requestRevision'])->name('validations.revision');

    // Laporan.
    Route::get('/report', [ReportController::class, 'index'])->name('report');
    Route::get('/report/export', [ReportController::class, 'exportCsv'])->name('report.export');

    // Kelola misi.
    Route::get('/missions', [TeacherMissionController::class, 'index'])->name('missions');
    Route::get('/missions/{mission}/edit', [TeacherMissionController::class, 'edit'])->name('missions.edit');
    Route::put('/missions/{mission}', [TeacherMissionController::class, 'update'])->name('missions.update');
});
