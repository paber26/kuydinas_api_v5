<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Soal;
use App\Models\Tryout;
use App\Models\TryoutRegistration;
use App\Models\TryoutResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TryoutController extends Controller
{
    public function index()
    {
        $tryouts = Tryout::withCount('soals')
            ->withCount('registrations')
            ->latest()
            ->get()
            ->map(function ($tryout) {
                if ($tryout->status === 'publish' && $tryout->type === 'free' && $tryout->free_valid_until && Carbon::now()->greaterThan(Carbon::parse($tryout->free_valid_until)->endOfDay())) {
                    $tryout->update(['type' => 'premium']);
                }

                $questionCount =
                    ($tryout->twk_target ?? 0) +
                    ($tryout->tiu_target ?? 0) +
                    ($tryout->tkp_target ?? 0);

                $data = $tryout->toArray();
                $data['subtitle'] = 'Simulasi lengkap seperti ujian asli';
                $data['category'] = 'SKD CPNS';
                $data['isFree'] = $tryout->type === 'free';
                $data['free_start_date'] = optional($tryout->free_start_date)->toDateTimeString();
                $data['questionCount'] = $questionCount;
                $data['highlight'] = $tryout->type === 'free';
                $data['info_ig'] = $tryout->info_ig;
                $data['info_wa'] = $tryout->info_wa;
                $data['tag'] = $tryout->type;
                $data['level'] = 'Menengah';
                $data['seatsLeft'] = $tryout->quota
                    ? max($tryout->quota - $tryout->registrations_count, 0)
                    : null;

                return $data;
            });

        return response()->json([
            'status' => true,
            'data' => $tryouts,
        ]);
    }

    public function show($id)
    {
        $tryout = Tryout::with('soals')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $tryout,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'duration' => 'required|integer',
            'type' => 'required|in:free,premium',
            'quota' => 'nullable|integer|min:1',
            'price' => 'nullable|integer|min:0',
            'discount' => 'nullable|integer|min:0|max:100',
            'free_start_date' => 'nullable|date',
            'free_valid_until' => 'nullable|date',
            'info_ig' => 'nullable|url',
            'info_wa' => 'nullable|url',
            'twk_count' => 'required|integer',
            'tiu_count' => 'required|integer',
            'tkp_count' => 'required|integer',
            'twk_pg' => 'required|integer',
            'tiu_pg' => 'required|integer',
            'tkp_pg' => 'required|integer',
        ]);

        $tryout = Tryout::create([
            'title' => $request->title,
            'duration' => $request->duration,
            'status' => 'draft',
            'type' => $request->type,
            'quota' => $request->quota,
            'price' => $request->price ?? 0,
            'discount' => $request->discount ?? 0,
            'free_start_date' => $request->type === 'free' && $request->filled('free_start_date')
                ? Carbon::parse($request->free_start_date)->toDateTimeString()
                : null,
            'free_valid_until' => $request->type === 'free' && $request->filled('free_valid_until')
                ? Carbon::parse($request->free_valid_until)->toDateTimeString()
                : null,
            'info_ig' => $request->type === 'free' ? $request->info_ig : null,
            'info_wa' => $request->type === 'free' ? $request->info_wa : null,
            'twk_target' => $request->twk_count,
            'tiu_target' => $request->tiu_count,
            'tkp_target' => $request->tkp_count,
            'twk_pg' => $request->twk_pg,
            'tiu_pg' => $request->tiu_pg,
            'tkp_pg' => $request->tkp_pg,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tryout berhasil dibuat',
            'data' => $tryout,
        ], 201);
    }

    public function attachSoal(Request $request, $id)
    {
        $tryout = Tryout::with('soals')->findOrFail($id);



        $request->validate([
            'soal_id' => 'required|exists:soals,id',
        ]);

        $soal = Soal::findOrFail($request->soal_id);

        if ($tryout->soals->contains($soal->id)) {
            return response()->json([
                'status' => false,
                'message' => 'Soal sudah ada',
            ], 422);
        }

        $currentCount = $tryout->soals
            ->where('category', $soal->category)
            ->count();

        $target = match ($soal->category) {
            'TWK' => $tryout->twk_target,
            'TIU' => $tryout->tiu_target,
            'TKP' => $tryout->tkp_target,
            default => 0,
        };

        if ($currentCount >= $target) {
            return response()->json([
                'status' => false,
                'message' => 'Kuota kategori penuh',
            ], 422);
        }

        $lastOrder = DB::table('tryout_soal')
            ->where('tryout_id', $id)
            ->max('urutan_soal');

        $nextOrder = $lastOrder ? $lastOrder + 1 : 1;

        $tryout->soals()->attach($soal->id, [
            'urutan_soal' => $nextOrder,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Soal berhasil ditambahkan',
        ]);
    }

    public function detachSoal($id, $soalId)
    {
        $tryout = Tryout::findOrFail($id);



        $tryout->soals()->detach($soalId);

        $soals = DB::table('tryout_soal')
            ->where('tryout_id', $id)
            ->orderBy('urutan_soal')
            ->get();

        $order = 1;

        foreach ($soals as $item) {
            DB::table('tryout_soal')
                ->where('tryout_id', $id)
                ->where('soal_id', $item->soal_id)
                ->update([
                    'urutan_soal' => $order,
                ]);

            $order++;
        }

        return response()->json([
            'status' => true,
            'message' => 'Soal berhasil dihapus',
        ]);
    }

    public function publish($id)
    {
        $tryout = Tryout::with('soals')->findOrFail($id);

        $twk = $tryout->soals->where('category', 'TWK')->count();
        $tiu = $tryout->soals->where('category', 'TIU')->count();
        $tkp = $tryout->soals->where('category', 'TKP')->count();

        if (
            $twk !== $tryout->twk_target ||
            $tiu !== $tryout->tiu_target ||
            $tkp !== $tryout->tkp_target
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Komposisi belum sesuai',
            ], 422);
        }

        $tryout->update([
            'status' => 'publish',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tryout berhasil dipublish',
        ]);
    }

    public function start(Request $request, $id)
    {
        $user = $request->user();

        $tryout = Tryout::with([
            'soals' => function ($query) {
                $this->applyTryoutQuestionOrdering($query);
            }
        ])->findOrFail($id);

        $registration = TryoutRegistration::where('user_id', $user->id)
            ->where('tryout_id', $tryout->id)
            ->first();

        if (!$registration) {
            return response()->json([
                'status' => false,
                'message' => 'Silakan registrasi tryout terlebih dahulu',
            ], 422);
        }

        if ($registration->expires_at && Carbon::now()->greaterThan($registration->expires_at)) {
            return response()->json([
                'status' => false,
                'message' => 'Masa berlaku tryout sudah habis. Silakan registrasi ulang.',
            ], 422);
        }

        $session = $this->resolveSessionForStart($user->id, $tryout->id);

        if ($this->hasSessionStateColumn() && empty($session->session_state)) {
            $session->session_state = $this->defaultSessionState();
            $session->save();
        }

        if (in_array($registration->status, ['registered', 'completed'], true)) {
            $registration->update([
                'status' => 'started',
                'started_at' => $session->started_at ?? Carbon::now(),
                'finished_at' => null,
            ]);
            $registration->refresh();
        } elseif (!$registration->started_at && $session->started_at) {
            $registration->update([
                'started_at' => $session->started_at,
            ]);
            $registration->refresh();
        }

        $endTime = Carbon::parse($session->started_at)->addMinutes($tryout->duration);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $tryout->id,
                'title' => $tryout->title,
                'duration' => $tryout->duration,
                'started_at' => optional($session->started_at)->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'registration_status' => $registration->status,
                'finished_at' => optional($registration->finished_at)->toDateTimeString(),
                'attempt_number' => (int) ($session->attempt_number ?? 1),
                'answers' => $session->answers ?? [],
                'session_state' => $this->sessionStateForResponse($session),
                'questions' => $tryout->soals,
            ]
        ]);
    }

    public function autosave(Request $request, $id)
    {
        $user = $request->user();
        $tryout = Tryout::findOrFail($id);

        $registration = TryoutRegistration::where('user_id', $user->id)
            ->where('tryout_id', $tryout->id)
            ->first();

        if (!$registration) {
            return response()->json([
                'status' => false,
                'message' => 'Silakan registrasi tryout terlebih dahulu',
            ], 422);
        }

        $validated = $request->validate([
            'answers' => 'nullable|array',
            'session_state' => 'nullable|array',
            'session_state.current_index' => 'nullable|integer|min:0',
            'session_state.current_question_id' => 'nullable|integer',
            'session_state.flagged_question_ids' => 'nullable|array',
            'session_state.flagged_question_ids.*' => 'integer',
            'session_state.visited_question_ids' => 'nullable|array',
            'session_state.visited_question_ids.*' => 'integer',
            'session_state.last_interaction' => 'nullable|array',
            'session_state.last_interaction.type' => 'nullable|string|max:50',
            'session_state.last_interaction.question_id' => 'nullable|integer',
            'session_state.last_interaction.option_label' => 'nullable|string|max:10',
            'session_state.last_interaction.at' => 'nullable|date',
        ]);

        $session = $this->latestInProgressSession($user->id, $tryout->id)
            ?? $this->resolveSessionForStart($user->id, $tryout->id);

        $answers = $this->normalizeAnswers($validated['answers'] ?? ($session->answers ?? []));
        $sessionState = $this->normalizeSessionState(
            $validated['session_state'] ?? ($this->hasSessionStateColumn() ? ($session->session_state ?? []) : [])
        );

        $session->update($this->sessionWriteAttributes([
            'answers' => $answers,
            'session_state' => $sessionState,
            'started_at' => $session->started_at ?? Carbon::now(),
        ]));

        if (in_array($registration->status, ['registered', 'completed'], true)) {
            $registration->update([
                'status' => 'started',
                'started_at' => $session->started_at ?? Carbon::now(),
                'finished_at' => null,
            ]);
        } elseif (!$registration->started_at && $session->started_at) {
            $registration->update([
                'started_at' => $session->started_at,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Interaksi tryout tersimpan',
            'data' => [
                'answers' => $answers,
                'session_state' => $sessionState,
                'saved_at' => Carbon::now()->toDateTimeString(),
            ]
        ]);
    }

    public function remainingTime(Request $request, $id)
    {
        $user = $request->user();
        $tryout = Tryout::findOrFail($id);

        $result = $this->latestInProgressSession($user->id, $tryout->id);

        if (!$result || !$result->started_at) {
            return response()->json([
                'status' => true,
                'message' => 'Sesi tryout belum dimulai',
                'remaining_seconds' => null,
            ]);
        }

        $end = Carbon::parse($result->started_at)->addMinutes($tryout->duration);
        $remaining = Carbon::now()->diffInSeconds($end, false);

        return response()->json([
            'status' => true,
            'remaining_seconds' => max($remaining, 0),
        ]);
    }

    public function submit(Request $request, $id)
    {
        $user = $request->user();

        $tryout = Tryout::with([
            'soals' => function ($query) {
                $this->applyTryoutQuestionOrdering($query);
            }
        ])->findOrFail($id);

        $registration = TryoutRegistration::where('user_id', $user->id)
            ->where('tryout_id', $tryout->id)
            ->first();

        if (!$registration) {
            return response()->json([
                'status' => false,
                'message' => 'Silakan registrasi tryout terlebih dahulu',
            ], 422);
        }

        $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'nullable|string',
            'session_state' => 'nullable|array',
        ]);

        $answers = $this->normalizeAnswers($request->input('answers', []));
        $score = 0;
        $correctAnswer = 0;

        foreach ($tryout->soals as $soal) {
            $userAnswer = $answers[$soal->id] ?? $answers[(string) $soal->id] ?? null;

            if ($userAnswer === null || $userAnswer === '') {
                continue;
            }

            if ($soal->category !== 'TKP') {
                $availableLabels = collect(is_array($soal->options) ? $soal->options : [])
                    ->pluck('label')
                    ->filter()
                    ->map(fn($label) => strtoupper((string) $label))
                    ->values()
                    ->all();

                if ($availableLabels && !in_array($userAnswer, $availableLabels, true)) {
                    throw ValidationException::withMessages([
                        "answers.{$soal->id}" => 'Pilihan jawaban tidak valid.',
                    ]);
                }

                if ($userAnswer === strtoupper((string) $soal->correct_answer)) {
                    $correctAnswer++;
                    $score++;
                }
            } else {
                $selected = collect(is_array($soal->options) ? $soal->options : [])
                    ->first(function ($option) use ($userAnswer) {
                        return strtoupper((string) data_get($option, 'label')) === $userAnswer;
                    });

                if (!$selected) {
                    throw ValidationException::withMessages([
                        "answers.{$soal->id}" => 'Pilihan jawaban tidak valid.',
                    ]);
                }

                if (isset($selected['score'])) {
                    $selectedScore = (int) $selected['score'];
                    $score += $selectedScore;

                    if ($selectedScore === 5) {
                        $correctAnswer++;
                    }
                }
            }
        }

        $finishedAt = Carbon::now();

        if (!Schema::hasColumn('tryout_results', 'correct_answer')) {
            return response()->json([
                'status' => false,
                'message' => 'Kolom correct_answer belum tersedia pada tabel tryout_results.',
                'errors' => [
                    'correct_answer' => [
                        'Tambahkan kolom correct_answer bertipe integer dengan default 0.',
                    ],
                ],
            ], 422);
        }

        $session = $this->latestInProgressSession($user->id, $tryout->id)
            ?? $this->resolveSessionForStart($user->id, $tryout->id);

        $sessionState = $this->normalizeSessionState(
            $request->input('session_state', $this->hasSessionStateColumn() ? ($session->session_state ?? []) : [])
        );
        $sessionState['submitted_at'] = $finishedAt->toDateTimeString();

        $session->update($this->sessionWriteAttributes([
            'status' => 'completed',
            'score' => $score,
            'correct_answer' => $correctAnswer,
            'answers' => $answers,
            'session_state' => $sessionState,
            'started_at' => $session->started_at ?? Carbon::now(),
            'finished_at' => $finishedAt,
        ]));

        $registration->update([
            'status' => 'completed',
            'started_at' => $registration->started_at ?? $session->started_at ?? Carbon::now(),
            'finished_at' => $finishedAt,
        ]);

        Cache::forget("ranking_tryout_{$id}");

        $rank = $this->resolveRank($tryout->id, $score, $user->id);

        return response()->json([
            'status' => true,
            'message' => 'Tryout selesai',
            'data' => [
                'score' => $score,
                'rank' => $rank,
                'correct_answer' => $correctAnswer,
                'answers' => $answers,
                'session_state' => $sessionState,
                'finished_at' => $finishedAt->toDateTimeString(),
                'attempt_number' => (int) ($session->attempt_number ?? 1),
            ]
        ]);
    }

    public function destroy($id)
    {
        $tryout = Tryout::findOrFail($id);
        $tryout->soals()->detach();
        $tryout->delete();

        return response()->json([
            'status' => true,
            'message' => 'Tryout berhasil dihapus',
        ]);
    }

    public function update(Request $request, $id)
    {
        $tryout = Tryout::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'status' => 'nullable|in:draft,publish',
            'quota' => 'nullable|integer|min:1',
            'twk_count' => 'required|integer|min:0',
            'tiu_count' => 'required|integer|min:0',
            'tkp_count' => 'required|integer|min:0',
            'type' => 'nullable|in:free,premium',
            'price' => 'nullable|integer|min:0',
            'discount' => 'nullable|integer|min:0|max:100',
            'free_start_date' => 'nullable|date',
            'free_valid_until' => 'nullable|date',
            'info_ig' => 'nullable|url',
            'info_wa' => 'nullable|url',
            'twk_pg' => 'nullable|integer|min:0',
            'tiu_pg' => 'nullable|integer|min:0',
            'tkp_pg' => 'nullable|integer|min:0',
        ]);

        $this->backfillMissingRegistrationExpiry($tryout);

        $nextType = $request->type ?? $tryout->type;
        $nextStatus = $request->status ?? $tryout->status;
        $nextFreeValidUntil = null;
        $nextFreeStartDate = null;

        if ($request->filled('free_start_date')) {
            $nextFreeStartDate = Carbon::parse($request->free_start_date)->toDateTimeString();
        } elseif ($request->has('free_start_date')) {
            $nextFreeStartDate = null;
        } else {
            $nextFreeStartDate = optional($tryout->free_start_date)->toDateTimeString();
        }

        if ($request->filled('free_valid_until')) {
            $nextFreeValidUntil = Carbon::parse($request->free_valid_until)->toDateTimeString();
        } elseif ($request->has('free_valid_until')) {
            $nextFreeValidUntil = null;
        } else {
            $nextFreeValidUntil = optional($tryout->free_valid_until)->toDateTimeString();
        }

        $nextTwkTarget = (int) $request->twk_count;
        $nextTiuTarget = (int) $request->tiu_count;
        $nextTkpTarget = (int) $request->tkp_count;





        $tryout->update([
            'title' => $request->title ?? $tryout->title,
            'duration' => $request->duration ?? $tryout->duration,
            'status' => $nextStatus,
            'quota' => $request->quota ?? $tryout->quota,
            'twk_target' => $nextTwkTarget,
            'tiu_target' => $nextTiuTarget,
            'tkp_target' => $nextTkpTarget,
            'type' => $nextType,
            'price' => $request->price ?? $tryout->price,
            'discount' => $request->discount ?? $tryout->discount,
            'free_start_date' => $nextType === 'free'
                ? $nextFreeStartDate
                : $tryout->free_start_date,
            'free_valid_until' => $nextType === 'free'
                ? $nextFreeValidUntil
                : $tryout->free_valid_until,
            'info_ig' => $nextType === 'free' ? ($request->has('info_ig') ? $request->info_ig : $tryout->info_ig) : null,
            'info_wa' => $nextType === 'free' ? ($request->has('info_wa') ? $request->info_wa : $tryout->info_wa) : null,
            'twk_pg' => $request->twk_pg ?? $tryout->twk_pg,
            'tiu_pg' => $request->tiu_pg ?? $tryout->tiu_pg,
            'tkp_pg' => $request->tkp_pg ?? $tryout->tkp_pg,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tryout berhasil diperbarui',
            'data' => $tryout,
        ]);
    }

    private function ensurePublishCompositionMatches(Tryout $tryout, int $twkTarget, int $tiuTarget, int $tkpTarget): void
    {
        $soals = $tryout->relationLoaded('soals') ? $tryout->soals : $tryout->soals()->get();

        $twk = $soals->where('category', 'TWK')->count();
        $tiu = $soals->where('category', 'TIU')->count();
        $tkp = $soals->where('category', 'TKP')->count();

        if ($twk !== $twkTarget || $tiu !== $tiuTarget || $tkp !== $tkpTarget) {
            throw ValidationException::withMessages([
                'status' => 'Komposisi tryout belum sesuai untuk status publish.',
            ]);
        }
    }

    private function backfillMissingRegistrationExpiry(Tryout $tryout): void
    {
        if ($tryout->type !== 'free') {
            return;
        }

        $registrations = TryoutRegistration::where('tryout_id', $tryout->id)
            ->whereNull('expires_at')
            ->get();

        if ($registrations->isEmpty()) {
            return;
        }

        foreach ($registrations as $registration) {
            $registration->update([
                'expires_at' => $this->resolveFreeRegistrationExpiry($tryout, $registration),
            ]);
        }
    }

    private function resolveFreeRegistrationExpiry(Tryout $tryout, TryoutRegistration $registration): ?Carbon
    {
        if ($tryout->free_valid_until) {
            return Carbon::parse($tryout->free_valid_until)->endOfDay();
        }

        $registeredAt = $registration->registered_at ?? Carbon::now();
        return Carbon::parse($registeredAt)->addDays(7);
    }

    public function reorder(Request $request, $id)
    {
        $request->validate([
            'orders' => 'required|array',
        ]);

        foreach ($request->orders as $soalId => $order) {
            DB::table('tryout_soal')
                ->where([
                    'tryout_id' => $id,
                    'soal_id' => $soalId,
                ])
                ->update([
                    'urutan_soal' => $order,
                ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Urutan soal berhasil diperbarui',
        ]);
    }

    private function defaultSessionState(): array
    {
        return [
            'current_index' => 0,
            'current_question_id' => null,
            'flagged_question_ids' => [],
            'visited_question_ids' => [],
            'last_interaction' => null,
        ];
    }

    private function hasSessionStateColumn(): bool
    {
        static $hasSessionStateColumn = null;

        if ($hasSessionStateColumn === null) {
            $hasSessionStateColumn = Schema::hasColumn('tryout_results', 'session_state');
        }

        return $hasSessionStateColumn;
    }

    private function sessionCreateDefaults(): array
    {
        $defaults = [
            'answers' => [],
            'score' => 0,
            'correct_answer' => 0,
            'started_at' => Carbon::now(),
        ];

        if (TryoutResult::hasAttemptNumberColumn()) {
            $defaults['attempt_number'] = 1;
        }

        if (TryoutResult::hasStatusColumn()) {
            $defaults['status'] = 'in_progress';
        }

        if (TryoutResult::hasFinishedAtColumn()) {
            $defaults['finished_at'] = null;
        }

        if ($this->hasSessionStateColumn()) {
            $defaults['session_state'] = $this->defaultSessionState();
        }

        return $defaults;
    }

    private function sessionWriteAttributes(array $attributes): array
    {
        if (!$this->hasSessionStateColumn()) {
            unset($attributes['session_state']);
        }

        if (!TryoutResult::hasAttemptNumberColumn()) {
            unset($attributes['attempt_number']);
        }

        if (!TryoutResult::hasStatusColumn()) {
            unset($attributes['status']);
        }

        if (!TryoutResult::hasFinishedAtColumn()) {
            unset($attributes['finished_at']);
        }

        return $attributes;
    }

    private function latestSession(int $userId, int $tryoutId): ?TryoutResult
    {
        return TryoutResult::forUserTryout($userId, $tryoutId)
            ->latestAttempt()
            ->first();
    }

    private function latestInProgressSession(int $userId, int $tryoutId): ?TryoutResult
    {
        return TryoutResult::forUserTryout($userId, $tryoutId)
            ->inProgress()
            ->latestAttempt()
            ->first();
    }

    private function createNewSession(int $userId, int $tryoutId): TryoutResult
    {
        if (!TryoutResult::hasAttemptNumberColumn()) {
            return TryoutResult::firstOrCreate(
                [
                    'user_id' => $userId,
                    'tryout_id' => $tryoutId,
                ],
                $this->sessionCreateDefaults()
            );
        }

        $attemptNumber = (int) TryoutResult::forUserTryout($userId, $tryoutId)->max('attempt_number') + 1;

        return TryoutResult::create(array_merge($this->sessionCreateDefaults(), [
            'user_id' => $userId,
            'tryout_id' => $tryoutId,
            'attempt_number' => max($attemptNumber, 1),
        ]));
    }

    private function resolveSessionForStart(int $userId, int $tryoutId): TryoutResult
    {
        $activeSession = $this->latestInProgressSession($userId, $tryoutId);

        if ($activeSession) {
            return $activeSession;
        }

        return $this->createNewSession($userId, $tryoutId);
    }

    private function latestCompletedScoresQuery(int $tryoutId)
    {
        if (!TryoutResult::hasAttemptNumberColumn() || !TryoutResult::hasStatusColumn()) {
            return TryoutResult::query()
                ->where('tryout_id', $tryoutId);
        }

        $latestCompletedAttempts = TryoutResult::query()
            ->select('user_id', DB::raw('MAX(attempt_number) as latest_attempt_number'))
            ->where('tryout_id', $tryoutId)
            ->where('status', 'completed')
            ->groupBy('user_id');

        return TryoutResult::query()
            ->joinSub($latestCompletedAttempts, 'latest_completed_attempts', function ($join) {
                $join->on('tryout_results.user_id', '=', 'latest_completed_attempts.user_id')
                    ->on('tryout_results.attempt_number', '=', 'latest_completed_attempts.latest_attempt_number');
            })
            ->where('tryout_results.tryout_id', $tryoutId)
            ->where('tryout_results.status', 'completed');
    }

    private function resolveRank(int $tryoutId, int $score, int $userId): int
    {
        return (clone $this->latestCompletedScoresQuery($tryoutId))
            ->where(function ($query) use ($score, $userId) {
                $query
                    ->where('tryout_results.score', '>', $score)
                    ->orWhere(function ($innerQuery) use ($score, $userId) {
                        $innerQuery
                            ->where('tryout_results.score', $score)
                            ->where('tryout_results.user_id', '<', $userId);
                    });
            })
            ->count() + 1;
    }

    private function sessionStateForResponse(?TryoutResult $session): array
    {
        if (!$this->hasSessionStateColumn()) {
            return $this->defaultSessionState();
        }

        return $this->normalizeSessionState($session?->session_state ?? []);
    }

    private function normalizeAnswers(array $answers): array
    {
        return collect($answers)
            ->mapWithKeys(function ($answer, $questionId) {
                if (is_array($answer)) {
                    $answer = collect($answer)
                        ->filter(fn($item) => $item !== null && trim((string) $item) !== '')
                        ->map(fn($item) => strtoupper(trim((string) $item)))
                        ->first();
                }

                if ($answer === null) {
                    return [(string) $questionId => null];
                }

                $normalized = strtoupper(trim((string) $answer));

                return [(string) $questionId => $normalized === '' ? null : $normalized];
            })
            ->filter(fn($answer) => $answer !== null)
            ->all();
    }

    private function normalizeSessionState(?array $sessionState): array
    {
        $state = array_merge($this->defaultSessionState(), is_array($sessionState) ? $sessionState : []);
        $state['current_index'] = max((int) ($state['current_index'] ?? 0), 0);
        $state['current_question_id'] = isset($state['current_question_id']) ? (int) $state['current_question_id'] : null;
        $state['flagged_question_ids'] = $this->normalizeQuestionIds($state['flagged_question_ids'] ?? []);
        $state['visited_question_ids'] = $this->normalizeQuestionIds($state['visited_question_ids'] ?? []);

        $lastInteraction = is_array($state['last_interaction'] ?? null)
            ? $state['last_interaction']
            : [];

        $state['last_interaction'] = [
            'type' => isset($lastInteraction['type']) ? substr((string) $lastInteraction['type'], 0, 50) : null,
            'question_id' => isset($lastInteraction['question_id']) ? (int) $lastInteraction['question_id'] : null,
            'option_label' => isset($lastInteraction['option_label'])
                ? strtoupper(substr(trim((string) $lastInteraction['option_label']), 0, 10))
                : null,
            'at' => $this->normalizeTimestamp($lastInteraction['at'] ?? null) ?? Carbon::now()->toDateTimeString(),
        ];

        if (isset($state['submitted_at'])) {
            $state['submitted_at'] = $this->normalizeTimestamp($state['submitted_at']);
        }

        return $state;
    }

    private function normalizeQuestionIds(array $questionIds): array
    {
        return collect($questionIds)
            ->map(fn($questionId) => is_numeric($questionId) ? (int) $questionId : null)
            ->filter(fn($questionId) => $questionId !== null)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeTimestamp($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function applyTryoutQuestionOrdering($query): void
    {
        $query
            ->orderByRaw("
                CASE category
                    WHEN 'TWK' THEN 1
                    WHEN 'TIU' THEN 2
                    WHEN 'TKP' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('pivot_urutan_soal')
            ->orderBy('id');
    }
}
