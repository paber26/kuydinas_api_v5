<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tryout;
use App\Models\TryoutResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index(Request $request, $tryoutId)
    {
        $userId = (int) $request->user()->id;
        $tryout = Tryout::with([
            'soals' => function ($query) {
                $this->applyTryoutQuestionOrdering($query);
            },
        ])
            ->where('status', 'publish')
            ->findOrFail($tryoutId);

        $rankingResults = Cache::remember("ranking_tryout_{$tryoutId}", 10, function () use ($tryoutId) {
            return $this->latestCompletedResultsQuery($tryoutId)
                ->with('user:id,name')
                ->select('tryout_results.*')
                ->orderByDesc('score')
                ->orderBy('user_id')
                ->get();
        });

        $leaderboard = $rankingResults
            ->values()
            ->map(function (TryoutResult $result, int $index) use ($tryout, $userId) {
                $categoryScores = $this->buildCategoryScores($result, $tryout);

                return [
                    'id' => $result->id,
                    'user_id' => (int) $result->user_id,
                    'rank' => $index + 1,
                    'name' => $result->user?->name ?? 'Peserta',
                    'region' => '-',
                    'twk' => $categoryScores['TWK'],
                    'tiu' => $categoryScores['TIU'],
                    'tkp' => $categoryScores['TKP'],
                    'total' => (int) ($result->score ?? 0),
                    'is_current_user' => (int) $result->user_id === $userId,
                ];
            })
            ->values();

        $currentUserRow = $leaderboard->firstWhere('is_current_user', true);

        return response()->json([
            'status' => true,
            'data' => [
                'tryout' => [
                    'id' => $tryout->id,
                    'title' => $tryout->title,
                    'duration' => (int) ($tryout->duration ?? 0),
                    'question_count' => $this->questionCount($tryout),
                ],
                'summary' => [
                    'total_participants' => $leaderboard->count(),
                    'user_rank' => $currentUserRow['rank'] ?? null,
                    'user_score' => $currentUserRow['total'] ?? null,
                ],
                'top_three' => $leaderboard->take(3)->values()->all(),
                'leaderboard' => $leaderboard->all(),
            ],
        ]);
    }

    public function myRank(Request $request, $tryoutId)
    {
        $userId = $request->user()->id;

        $userResult = $this->latestCompletedResultsQuery($tryoutId)
            ->select('tryout_results.user_id', 'tryout_results.score')
            ->where('tryout_results.user_id', $userId)
            ->first();

        if (!$userResult) {
            return response()->json([
                'status' => false,
                'message' => 'User belum mengerjakan tryout'
            ]);
        }

        $rank = $this->latestCompletedResultsQuery($tryoutId)
            ->where(function ($query) use ($userResult) {
                $query
                    ->where('tryout_results.score', '>', $userResult->score)
                    ->orWhere(function ($innerQuery) use ($userResult) {
                        $innerQuery
                            ->where('tryout_results.score', $userResult->score)
                            ->where('tryout_results.user_id', '<', $userResult->user_id);
                    });
            })
            ->count() + 1;

        return response()->json([
            'status' => true,
            'rank' => $rank
        ]);
    }

    private function buildCategoryScores(TryoutResult $result, Tryout $tryout): array
    {
        $answers = collect($result->answers ?? [])
            ->mapWithKeys(function ($answer, $questionId) {
                if ($answer === null || $answer === '') {
                    return [$questionId => null];
                }

                return [$questionId => strtoupper((string) $answer)];
            });

        $categoryScores = [
            'TWK' => 0,
            'TIU' => 0,
            'TKP' => 0,
        ];

        $tryout->soals->each(function ($soal) use ($answers, &$categoryScores) {
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

                $categoryScores['TKP'] += (int) ($selectedOption['score'] ?? 0);

                return;
            }

            if ($userAnswer === strtoupper((string) ($soal->correct_answer ?? ''))) {
                $categoryScores[$category] += 5;
            }
        });

        return $categoryScores;
    }

    private function questionCount(Tryout $tryout): int
    {
        return (int) ($tryout->twk_target ?? 0)
            + (int) ($tryout->tiu_target ?? 0)
            + (int) ($tryout->tkp_target ?? 0);
    }

    private function latestCompletedResultsQuery($tryoutId)
    {
        if (!TryoutResult::hasAttemptNumberColumn() || !TryoutResult::hasStatusColumn()) {
            return TryoutResult::query()->where('tryout_id', $tryoutId);
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

    private function applyTryoutQuestionOrdering($query): void
    {
        $query
            ->orderByRaw("
                CASE soals.category
                    WHEN 'TWK' THEN 1
                    WHEN 'TIU' THEN 2
                    WHEN 'TKP' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('pivot_urutan_soal')
            ->orderBy('soals.id');
    }
}
