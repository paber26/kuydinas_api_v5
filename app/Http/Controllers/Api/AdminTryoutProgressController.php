<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TryoutResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminTryoutProgressController extends Controller
{
    /**
     * Menampilkan daftar user yang sedang mengerjakan tryout (status in_progress).
     */
    public function index(Request $request)
    {
        $query = TryoutResult::with(['user:id,name,email,image', 'tryout:id,title,duration,twk_target,tiu_target,tkp_target'])
            ->where('status', 'in_progress')
            ->orderByDesc('started_at');

        // Filter berdasarkan tryout tertentu (opsional)
        if ($request->filled('tryout_id')) {
            $query->where('tryout_id', $request->tryout_id);
        }

        $results = $query->get();

        $data = $results->map(function (TryoutResult $result) {
            $tryout = $result->tryout;
            $user = $result->user;

            if (!$tryout || !$user) {
                return null;
            }

            $totalQuestions = ($tryout->twk_target ?? 0)
                + ($tryout->tiu_target ?? 0)
                + ($tryout->tkp_target ?? 0);

            $answeredCount = is_array($result->answers)
                ? collect($result->answers)->filter(fn($v) => $v !== null && $v !== '')->count()
                : 0;

            $startedAt = $result->started_at ? Carbon::parse($result->started_at) : null;
            $deadline = $startedAt ? $startedAt->copy()->addMinutes($tryout->duration ?? 0) : null;
            $remainingSeconds = $deadline ? max(Carbon::now()->diffInSeconds($deadline, false), 0) : null;

            return [
                'id' => $result->id,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'image' => $user->image,
                ],
                'tryout' => [
                    'id' => $tryout->id,
                    'title' => $tryout->title,
                    'duration' => (int) ($tryout->duration ?? 0),
                    'total_questions' => $totalQuestions,
                ],
                'answered_count' => $answeredCount,
                'total_questions' => $totalQuestions,
                'progress_percent' => $totalQuestions > 0
                    ? round(($answeredCount / $totalQuestions) * 100, 1)
                    : 0,
                'started_at' => optional($startedAt)->toDateTimeString(),
                'deadline' => optional($deadline)->toDateTimeString(),
                'remaining_seconds' => $remainingSeconds,
                'attempt_number' => (int) ($result->attempt_number ?? 1),
            ];
        })->filter()->values();

        return response()->json([
            'status' => true,
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * Menampilkan riwayat user yang sudah menyelesaikan tryout.
     */
    public function history(Request $request)
    {
        $perPage = max((int) $request->integer('per_page', 15), 1);
        $search = trim((string) $request->input('search', ''));
        $tryoutId = $request->integer('tryout_id');

        $query = TryoutResult::query()
            ->with([
                'user:id,name,email,image',
                'tryout:id,title,duration,twk_target,tiu_target,tkp_target,twk_pg,tiu_pg,tkp_pg',
                'tryout.soals:id,category,options,correct_answer',
            ])
            ->completed()
            ->when($tryoutId > 0, function (Builder $builder) use ($tryoutId) {
                $builder->where('tryout_id', $tryoutId);
            })
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $nested) use ($search) {
                    $nested->whereHas('user', function (Builder $userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('tryout', function (Builder $tryoutQuery) use ($search) {
                        $tryoutQuery->where('title', 'like', "%{$search}%");
                    });
                });
            })
            ->orderByDesc('finished_at')
            ->orderByDesc('id');

        $results = $query->paginate($perPage)->withQueryString();

        $results->getCollection()->transform(function (TryoutResult $result) {
            $user = $result->user;
            $tryout = $result->tryout;

            if (!$user || !$tryout) {
                return null;
            }

            $scores = $this->buildCategoryScores($result);

            return [
                'id' => $result->id,
                'attempt_number' => (int) ($result->attempt_number ?? 1),
                'status' => (string) ($result->status ?? 'completed'),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'image' => $user->image,
                ],
                'tryout' => [
                    'id' => $tryout->id,
                    'title' => $tryout->title,
                    'duration' => (int) ($tryout->duration ?? 0),
                ],
                'score' => [
                    'twk' => $scores['TWK'],
                    'tiu' => $scores['TIU'],
                    'tkp' => $scores['TKP'],
                    'total' => $scores['TWK'] + $scores['TIU'] + $scores['TKP'],
                ],
                'correct_answer' => (int) ($result->correct_answer ?? 0),
                'started_at' => optional($result->started_at)->toDateTimeString(),
                'finished_at' => optional($result->finished_at ?? $result->updated_at)->toDateTimeString(),
            ];
        });

        $results->setCollection($results->getCollection()->filter()->values());

        return response()->json([
            'status' => true,
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
                'search' => $search,
                'tryout_id' => $tryoutId > 0 ? $tryoutId : null,
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
