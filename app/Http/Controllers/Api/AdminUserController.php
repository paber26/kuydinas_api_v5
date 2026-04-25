<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TryoutRegistration;
use App\Models\TryoutResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) ($request->input('per_page') ?? 20);
        $q = (string) ($request->input('q') ?? '');
        $isActive = $request->input('is_active');

        $query = User::query()->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($qBuilder) use ($q) {
                $qBuilder->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            });
        }

        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $users,
        ]);
    }

    public function activeCount(Request $request)
    {
        $count = User::where('is_active', true)->count();

        return response()->json([
            'status' => true,
            'data' => [
                'active' => (int) $count,
            ],
        ]);
    }

    public function totalCount(Request $request)
    {
        $count = User::count();

        return response()->json([
            'status' => true,
            'data' => [
                'total' => (int) $count,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user = User::findOrFail($id);
        $user->update(['is_active' => (bool) $data['is_active']]);

        return response()->json([
            'status' => true,
            'message' => 'Status user diperbarui',
            'data' => [
                'id' => $user->id,
                'is_active' => (bool) $user->is_active,
            ],
        ]);
    }

    public function locationStats(Request $request): JsonResponse
    {
        $provinceName = $request->input('province_name');

        if ($provinceName !== null && $provinceName !== '') {
            $rows = User::query()
                ->where('province_name', $provinceName)
                ->selectRaw('regency_name, COUNT(*) as count')
                ->groupBy('regency_name')
                ->orderByDesc('count')
                ->get();

            $countsByLabel = [];

            foreach ($rows as $row) {
                $label = ($row->regency_name === null || $row->regency_name === '')
                    ? 'Tidak Diketahui'
                    : (string) $row->regency_name;

                $countsByLabel[$label] = ($countsByLabel[$label] ?? 0) + (int) $row->count;
            }

            $data = collect($countsByLabel)
                ->map(fn ($count, $label) => [
                    'regency_name' => (string) $label,
                    'count'        => (int) $count,
                ])
                ->sortByDesc('count')
                ->values();

            $known = $data->filter(fn ($r) => $r['regency_name'] !== 'Tidak Diketahui')->values();
            $unknown = $data->filter(fn ($r) => $r['regency_name'] === 'Tidak Diketahui')->values();

            return response()->json([
                'status'        => true,
                'data'          => $known->concat($unknown)->values(),
                'province_name' => $provinceName,
            ]);
        }

        $rows = User::query()
            ->selectRaw('province_name, COUNT(*) as count')
            ->groupBy('province_name')
            ->orderByDesc('count')
            ->get();

        $countsByLabel = [];

        foreach ($rows as $row) {
            $label = ($row->province_name === null || $row->province_name === '')
                ? 'Tidak Diketahui'
                : (string) $row->province_name;

            $countsByLabel[$label] = ($countsByLabel[$label] ?? 0) + (int) $row->count;
        }

        $data = collect($countsByLabel)
            ->map(fn ($count, $label) => [
                'province_name' => (string) $label,
                'count'         => (int) $count,
            ])
            ->sortByDesc('count')
            ->values();

        $known = $data->filter(fn ($r) => $r['province_name'] !== 'Tidak Diketahui')->values();
        $unknown = $data->filter(fn ($r) => $r['province_name'] === 'Tidak Diketahui')->values();

        return response()->json([
            'status' => true,
            'data'   => $known->concat($unknown)->values(),
            'total'  => (int) collect($countsByLabel)->sum(),
        ]);
    }


    public function tryoutSummary(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $lastLogin = $user->last_login ? Carbon::parse($user->last_login) : null;

        $registrations = TryoutRegistration::query()
            ->with([
                'tryout:id,title,duration,type',
                'tryout.soals:id,category,options,correct_answer',
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->get();

        $completedResults = TryoutResult::query()
            ->with([
                'tryout:id,title,duration,type',
                'tryout.soals:id,category,options,correct_answer',
            ])
            ->where('user_id', $user->id)
            ->completed()
            ->latestAttempt()
            ->get()
            ->groupBy('tryout_id')
            ->map(fn ($items) => $items->first());

        $tryouts = $registrations->map(function (TryoutRegistration $registration) use ($completedResults) {
            $tryout = $registration->tryout;

            if (!$tryout) {
                return null;
            }

            $latestCompletedResult = $completedResults->get($registration->tryout_id);
            $score = $latestCompletedResult
                ? $this->buildCategoryScores($latestCompletedResult)
                : ['TWK' => 0, 'TIU' => 0, 'TKP' => 0];

            $registeredAt = $registration->registered_at ? Carbon::parse($registration->registered_at) : null;
            $startedAt = $registration->started_at ? Carbon::parse($registration->started_at) : null;
            $finishedAt = $registration->finished_at ? Carbon::parse($registration->finished_at) : null;
            $expiresAt = $registration->expires_at ? Carbon::parse($registration->expires_at) : null;

            return [
                'id' => $registration->id,
                'status' => (string) ($registration->status ?? 'registered'),
                'registered_at' => optional($registeredAt)->toDateTimeString(),
                'started_at' => optional($startedAt)->toDateTimeString(),
                'finished_at' => optional($finishedAt)->toDateTimeString(),
                'expires_at' => optional($expiresAt)->toDateTimeString(),
                'is_expired' => $expiresAt ? Carbon::now()->greaterThan($expiresAt) : false,
                'attempt_count' => TryoutResult::forUserTryout($registration->user_id, $registration->tryout_id)->count(),
                'latest_completed_attempt_number' => $latestCompletedResult?->attempt_number,
                'latest_completed_at' => optional($latestCompletedResult?->finished_at)->toDateTimeString(),
                'score' => $latestCompletedResult ? [
                    'twk' => $score['TWK'],
                    'tiu' => $score['TIU'],
                    'tkp' => $score['TKP'],
                    'total' => $score['TWK'] + $score['TIU'] + $score['TKP'],
                ] : null,
                'tryout' => [
                    'id' => $tryout->id,
                    'title' => $tryout->title,
                    'duration' => (int) ($tryout->duration ?? 0),
                    'type' => $tryout->type,
                ],
            ];
        })->filter()->values();

        return response()->json([
            'status' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'whatsapp' => $user->whatsapp,
                    'image' => $user->image,
                    'province_name' => $user->province_name,
                    'regency_name' => $user->regency_name,
                    'district_name' => $user->district_name,
                    'is_active' => (bool) $user->is_active,
                    'last_login' => optional($lastLogin)->toDateTimeString(),
                ],
                'registrations' => $tryouts,
                'summary' => [
                    'total_registered' => $tryouts->count(),
                    'total_completed' => $tryouts->where('status', 'completed')->count(),
                    'total_pending' => $tryouts->where('status', 'registered')->count(),
                    'total_started' => $tryouts->where('status', 'started')->count(),
                ],
            ],
        ]);
    }

    private function buildCategoryScores(TryoutResult $result): array
    {
        $answers = collect($result->answers ?? [])
            ->mapWithKeys(function ($answer, $questionId) {
                if ($answer === null || $answer === '') {
                    return [$questionId => null];
                }

                return [$questionId => strtoupper((string) $answer)];
            });

        $scores = [
            'TWK' => 0,
            'TIU' => 0,
            'TKP' => 0,
        ];

        $result->tryout?->soals?->each(function ($soal) use ($answers, &$scores) {
            $category = strtoupper(trim((string) $soal->category));
            $userAnswer = $answers[$soal->id] ?? $answers[(string) $soal->id] ?? null;

            if (!in_array($category, ['TWK', 'TIU', 'TKP'], true) || !$userAnswer) {
                return;
            }

            if ($category === 'TKP') {
                $selectedOption = collect($soal->options ?? [])
                    ->map(function ($option) {
                        $label = strtoupper((string) data_get($option, 'label', ''));

                        if ($label === '') {
                            return null;
                        }

                        return [
                            'key' => $label,
                            'score' => data_get($option, 'score') !== null ? (int) data_get($option, 'score') : null,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->firstWhere('key', $userAnswer);

                $scores['TKP'] += (int) ($selectedOption['score'] ?? 0);

                return;
            }

            if ($userAnswer === strtoupper((string) ($soal->correct_answer ?? ''))) {
                $scores[$category] += 5;
            }
        });

        return $scores;
    }
}
