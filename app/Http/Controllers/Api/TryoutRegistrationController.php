<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tryout;
use App\Models\TryoutRegistration;
use App\Models\TryoutResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TryoutRegistrationController extends Controller
{
    public function register(Request $request, $id)
    {
        $user = $request->user();
        $tryout = Tryout::where('status', 'publish')->findOrFail($id);

        if ($this->isFreeTryoutExpired($tryout)) {
            return response()->json([
                'status' => false,
                'message' => 'Masa berlaku tryout gratis ini sudah berakhir.',
                'data' => [],
            ], 422);
        }

        if (!$this->isFreeTryoutStarted($tryout)) {
            return response()->json([
                'status' => false,
                'message' => 'Masa berlaku tryout gratis ini belum dimulai.',
                'data' => [],
            ], 422);
        }

        $result = DB::transaction(function () use ($user, $tryout) {
            $existingRegistration = TryoutRegistration::where('user_id', $user->id)
                ->where('tryout_id', $tryout->id)
                ->lockForUpdate()
                ->first();

            if ($existingRegistration) {
                if ($existingRegistration->expires_at && Carbon::now()->greaterThan($existingRegistration->expires_at)) {
                    $existingRegistration->update([
                        'status' => 'registered',
                        'registered_at' => Carbon::now(),
                        'started_at' => null,
                        'finished_at' => null,
                        'expires_at' => $this->resolveRegistrationExpiry($tryout),
                    ]);

                    return [
                        'type' => 'renewed',
                        'registration' => $existingRegistration->load('tryout'),
                    ];
                }

                return [
                    'type' => 'duplicate',
                    'registration' => $existingRegistration->load('tryout'),
                ];
            }

            if ($tryout->quota) {
                $registeredCount = TryoutRegistration::where('tryout_id', $tryout->id)
                    ->lockForUpdate()
                    ->count();

                if ($registeredCount >= $tryout->quota) {
                    return [
                        'type' => 'full',
                        'registration' => null,
                    ];
                }
            }

            return [
                'type' => 'created',
                'registration' => TryoutRegistration::create([
                    'user_id' => $user->id,
                    'tryout_id' => $tryout->id,
                    'status' => 'registered',
                    'registered_at' => Carbon::now(),
                    'expires_at' => $this->resolveRegistrationExpiry($tryout),
                ])->load('tryout'),
            ];
        });

        if ($result['type'] === 'full') {
            return response()->json([
                'status' => false,
                'message' => 'Kuota tryout sudah penuh',
                'data' => [],
            ], 422);
        }

        if ($result['type'] === 'duplicate') {
            return response()->json([
                'status' => false,
                'message' => 'User sudah terdaftar pada tryout ini',
                'data' => $result['registration'],
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $result['registration'],
        ], $result['type'] === 'renewed' ? 200 : 201);
    }

    public function history(Request $request)
    {
        $registrations = TryoutRegistration::with('tryout')
            ->where('user_id', $request->user()->id)
            ->latest('registered_at')
            ->get()
            ->map(function ($registration) {
                $tryout = $registration->tryout;
                $questionCount =
                    ($tryout->twk_target ?? 0) +
                    ($tryout->tiu_target ?? 0) +
                    ($tryout->tkp_target ?? 0);

                $latestSession = TryoutResult::forUserTryout($registration->user_id, $registration->tryout_id)
                    ->latestAttempt()
                    ->first();

                $activeSession = TryoutResult::forUserTryout($registration->user_id, $registration->tryout_id)
                    ->inProgress()
                    ->latestAttempt()
                    ->first();

                $latestCompletedSession = TryoutResult::forUserTryout($registration->user_id, $registration->tryout_id)
                    ->completed()
                    ->latestAttempt()
                    ->first();

                $bestCompletedSession = TryoutResult::forUserTryout($registration->user_id, $registration->tryout_id)
                    ->completed()
                    ->orderByDesc('score')
                    ->orderByDesc('attempt_number')
                    ->first();

                $progressSource = $activeSession ?? $latestCompletedSession ?? $latestSession;

                $answeredCount = collect(is_array($progressSource?->answers) ? $progressSource->answers : [])
                    ->filter(fn($answer) => $answer !== null && $answer !== '')
                    ->count();

                $progress = $questionCount > 0
                    ? min((int) round(($answeredCount / $questionCount) * 100), 100)
                    : 0;

                $effectiveStatus = $registration->status ?: 'not_started';

                if ($activeSession) {
                    $effectiveStatus = 'in_progress';
                } elseif ($registration->status === 'completed' || $latestCompletedSession) {
                    $effectiveStatus = 'completed';
                }

                if ($effectiveStatus === 'completed') {
                    $progress = 100;
                }

                return [
                    'id' => $registration->id,
                    'tryout_id' => $registration->tryout_id,
                    'status' => $effectiveStatus,
                    'registration_status' => $registration->status,
                    'registered_at' => $registration->registered_at,
                    'started_at' => $registration->started_at,
                    'finished_at' => $registration->finished_at,
                    'expires_at' => $registration->expires_at,
                    'isExpired' => $registration->expires_at ? Carbon::now()->greaterThan($registration->expires_at) : false,
                    'title' => $tryout->title,
                    'duration' => $tryout->duration,
                    'price' => $tryout->price ?? 0,
                    'discount' => $tryout->discount,
                    'type' => $tryout->type,
                    'isFree' => $tryout->type === 'free',
                    'free_start_date' => optional($tryout->free_start_date)->toDateString(),

                    'free_valid_until' => optional($tryout->free_valid_until)->toDateString(),
                    'questionCount' => $questionCount,
                    'progress' => $progress,
                    'score' => $bestCompletedSession?->score,
                    'latest_score' => $latestCompletedSession?->score,
                    'correct_answer' => $latestCompletedSession?->correct_answer,
                    'answered_count' => $answeredCount,
                    'last_interaction_at' => data_get($progressSource?->session_state, 'last_interaction.at'),
                    'attempt_count' => TryoutResult::forUserTryout($registration->user_id, $registration->tryout_id)->count(),
                    'latest_attempt_number' => $latestSession?->attempt_number,
                    'latest_completed_attempt_number' => $latestCompletedSession?->attempt_number,
                    'latest_completed_at' => optional($latestCompletedSession?->finished_at)->toDateTimeString(),
                    'tryout' => $tryout,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $registrations,
        ]);
    }

    private function resolveRegistrationExpiry(Tryout $tryout): ?Carbon
    {
        if ($tryout->type !== 'free') {
            return null;
        }

        if ($tryout->free_valid_until) {
            return Carbon::parse($tryout->free_valid_until)->endOfDay();
        }

        return Carbon::now()->addDays(7);
    }

    private function isFreeTryoutExpired(Tryout $tryout): bool
    {
        if ($tryout->type !== 'free' || !$tryout->free_valid_until) {
            return false;
        }

        return Carbon::now()->greaterThan(Carbon::parse($tryout->free_valid_until)->endOfDay());
    }

    private function isFreeTryoutStarted(Tryout $tryout): bool
    {
        if ($tryout->type !== 'free' || !$tryout->free_start_date) {
            return true;
        }

        return Carbon::now()->greaterThanOrEqualTo(Carbon::parse($tryout->free_start_date)->startOfDay());
    }

    private function resolveEffectiveExpiry(TryoutRegistration $registration, Tryout $tryout): ?Carbon
    {
        if ($registration->expires_at) {
            return Carbon::parse($registration->expires_at);
        }

        if ($tryout->free_valid_until) {
            return Carbon::parse($tryout->free_valid_until)->endOfDay();
        }

        if ($registration->registered_at) {
            return Carbon::parse($registration->registered_at)->addDays(7);
        }

        return null;
    }
}
