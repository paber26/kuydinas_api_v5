<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tryout;
use App\Models\TryoutRegistration;
use App\Models\TryoutResult;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();

        $latestCompletedResult = $this->latestCompletedResult($user->id);
        $latestCompletedPerTryout = $this->latestCompletedPerTryoutQuery($user->id)
            ->with('tryout')
            ->get();
        $currentTryout = $this->currentTryout($user->id);
        $promoTryout = $this->recommendedPromoTryout($user->id);

        $latestRank = null;
        $latestTopPercentage = null;
        $latestParticipantCount = 0;

        if ($latestCompletedResult) {
            $latestRank = $this->resolveRank(
                (int) $latestCompletedResult->tryout_id,
                (int) ($latestCompletedResult->score ?? 0),
                (int) $user->id
            );

            $latestParticipantCount = (clone $this->latestCompletedResultsQuery((int) $latestCompletedResult->tryout_id))
                ->count();

            if ($latestParticipantCount > 0 && $latestRank !== null) {
                $latestTopPercentage = $latestParticipantCount === 1
                    ? 1
                    : max(1, (int) ceil(($latestRank / $latestParticipantCount) * 100));
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'stats' => [
                    'completed_tryouts' => $latestCompletedPerTryout->count(),
                    'average_score' => $this->averageScore($latestCompletedPerTryout),
                    'latest_rank' => $latestRank,
                    'latest_top_percentage' => $latestTopPercentage,
                    'latest_participant_count' => $latestParticipantCount,
                ],
                'current_tryout' => $currentTryout,
                'primary_action' => $this->primaryAction($currentTryout, $latestCompletedResult, $promoTryout),
                'latest_tryout' => $this->latestTryoutPayload($latestCompletedResult),
                'learning_path' => $this->learningPathPayload($latestCompletedResult),
                'promo_tryout' => $this->promoPayload($promoTryout),
            ],
        ]);
    }

    private function latestCompletedResult(int $userId): ?TryoutResult
    {
        $query = TryoutResult::with([
            'tryout.soals' => function ($query) {
                $this->applyTryoutQuestionOrdering($query);
            },
        ])->where('user_id', $userId);

        if (TryoutResult::hasStatusColumn()) {
            $query->where('status', 'completed');
        }

        if (TryoutResult::hasFinishedAtColumn()) {
            $query->orderByDesc('finished_at');
        }

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    private function latestCompletedPerTryoutQuery(int $userId)
    {
        $latestResults = TryoutResult::query()
            ->select(DB::raw('MAX(id) as id'))
            ->where('user_id', $userId);

        if (TryoutResult::hasStatusColumn()) {
            $latestResults->where('status', 'completed');
        }

        $latestResults->groupBy('tryout_id');

        return TryoutResult::query()
            ->whereIn('id', $latestResults);
    }

    private function currentTryout(int $userId): ?array
    {
        $activeSession = TryoutResult::with('tryout')
            ->where('user_id', $userId)
            ->when(
                TryoutResult::hasStatusColumn(),
                fn($query) => $query->where('status', 'in_progress')
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($activeSession?->tryout) {
            return [
                'tryout_id' => $activeSession->tryout->id,
                'title' => $activeSession->tryout->title,
                'status' => 'in_progress',
                'duration' => (int) ($activeSession->tryout->duration ?? 0),
                'question_count' => $this->questionCount($activeSession->tryout),
            ];
        }

        $registration = TryoutRegistration::with('tryout')
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query
                    ->whereNull('status')
                    ->orWhere('status', '!=', 'completed');
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (!$registration?->tryout) {
            return null;
        }

        return [
            'tryout_id' => $registration->tryout->id,
            'title' => $registration->tryout->title,
            'status' => 'registered',
            'duration' => (int) ($registration->tryout->duration ?? 0),
            'question_count' => $this->questionCount($registration->tryout),
        ];
    }

    private function primaryAction(?array $currentTryout, ?TryoutResult $latestCompletedResult, ?Tryout $promoTryout): array
    {
        if ($currentTryout && $currentTryout['status'] === 'in_progress') {
            return [
                'kind' => 'continue',
                'label' => 'Lanjutkan Tryout',
                'tryout_id' => $currentTryout['tryout_id'],
            ];
        }

        if ($currentTryout && $currentTryout['status'] === 'registered') {
            return [
                'kind' => 'start',
                'label' => 'Mulai Tryout',
                'tryout_id' => $currentTryout['tryout_id'],
            ];
        }

        if ($latestCompletedResult?->tryout) {
            return [
                'kind' => 'retry',
                'label' => 'Kerjakan Ulang',
                'tryout_id' => $latestCompletedResult->tryout->id,
            ];
        }

        return [
            'kind' => 'browse',
            'label' => $promoTryout ? 'Lihat Promo Tryout' : 'Mulai Tryout',
            'tryout_id' => $promoTryout?->id,
        ];
    }

    private function latestTryoutPayload(?TryoutResult $result): ?array
    {
        if (!$result?->tryout) {
            return null;
        }

        $tryout = $result->tryout;
        $metrics = $this->buildCategoryMetrics($result, $tryout);
        $passingGrade = (int) ($tryout->twk_pg ?? 0) + (int) ($tryout->tiu_pg ?? 0) + (int) ($tryout->tkp_pg ?? 0);
        $score = (int) ($result->score ?? 0);
        $progressPercentage = $passingGrade > 0
            ? (int) round(($score / $passingGrade) * 100)
            : 0;

        return [
            'tryout_id' => $tryout->id,
            'title' => $tryout->title,
            'finished_at' => optional($result->finished_at ?? $result->updated_at)->toDateTimeString(),
            'question_count' => $this->questionCount($tryout),
            'score' => $score,
            'passing_grade' => $passingGrade,
            'progress_percentage' => $progressPercentage,
            'progress_message' => $metrics['passed']
                ? 'Mantap! Kamu sudah melewati passing grade. Pertahankan performamu.'
                : 'Nilaimu belum melewati passing grade. Fokus pada kategori yang masih lemah lalu coba lagi.',
            'passed' => $metrics['passed'],
            'attempt_number' => (int) ($result->attempt_number ?? 1),
        ];
    }

    private function learningPathPayload(?TryoutResult $result): array
    {
        $defaults = collect(['TWK', 'TIU', 'TKP'])
            ->map(fn(string $category) => $this->emptyCategoryPayload($category))
            ->values()
            ->all();

        if (!$result?->tryout || !$result->tryout->relationLoaded('soals')) {
            return $defaults;
        }

        $metrics = $this->buildCategoryMetrics($result, $result->tryout);

        return collect(['TWK', 'TIU', 'TKP'])
            ->map(function (string $category) use ($metrics) {
                $score = (int) ($metrics['category_scores'][$category] ?? 0);
                $maxScore = max((int) ($metrics['category_max_scores'][$category] ?? 0), 0);
                $progress = $maxScore > 0
                    ? min(100, (int) round(($score / $maxScore) * 100))
                    : 0;
                $status = $progress >= 75 ? 'strong' : ($progress >= 50 ? 'medium' : 'weak');

                return [
                    'category' => $category,
                    'title' => $this->categoryTitle($category),
                    'status' => $status,
                    'status_label' => $this->categoryStatusLabel($status),
                    'description' => $this->categoryDescription($category, $status),
                    'progress' => $progress,
                    'score' => $score,
                    'max_score' => $maxScore,
                ];
            })
            ->values()
            ->all();
    }

    private function recommendedPromoTryout(int $userId): ?Tryout
    {
        $registeredTryoutIds = TryoutRegistration::where('user_id', $userId)->pluck('tryout_id');

        return Tryout::query()
            ->where('status', 'publish')
            ->when(
                $registeredTryoutIds->isNotEmpty(),
                fn($query) => $query->whereNotIn('id', $registeredTryoutIds)
            )
            ->orderByRaw("
                CASE
                    WHEN type = 'premium' AND COALESCE(discount, 0) > 0 THEN 0
                    WHEN type = 'premium' THEN 1
                    WHEN type = 'free' THEN 2
                    ELSE 3
                END
            ")
            ->orderByDesc('discount')
            ->orderByDesc('id')
            ->first();
    }

    private function promoPayload(?Tryout $tryout): ?array
    {
        if (!$tryout) {
            return null;
        }

        $price = (int) ($tryout->price ?? 0);
        $discount = (int) ($tryout->discount ?? 0);
        $finalPrice = max(0, $price - (int) round(($price * $discount) / 100));

        return [
            'id' => $tryout->id,
            'title' => $tryout->title,
            'type' => $tryout->type,
            'discount' => $discount,
            'price' => $price,
            'final_price' => $finalPrice,
            'free_start_date' => optional($tryout->free_start_date)->toDateString(),
            'free_valid_until' => optional($tryout->free_valid_until)->toDateString(),
            'duration' => (int) ($tryout->duration ?? 0),
            'question_count' => $this->questionCount($tryout),
        ];
    }

    private function averageScore(Collection $results): float|int
    {
        if ($results->isEmpty()) {
            return 0;
        }

        return round((float) $results->avg(fn(TryoutResult $result) => (int) ($result->score ?? 0)), 1);
    }

    private function questionCount(Tryout $tryout): int
    {
        return (int) ($tryout->twk_target ?? 0)
            + (int) ($tryout->tiu_target ?? 0)
            + (int) ($tryout->tkp_target ?? 0);
    }

    private function emptyCategoryPayload(string $category): array
    {
        return [
            'category' => $category,
            'title' => $this->categoryTitle($category),
            'status' => 'idle',
            'status_label' => 'Mulai',
            'description' => 'Selesaikan tryout pertamamu untuk mendapatkan rekomendasi belajar yang lebih akurat.',
            'progress' => 0,
            'score' => 0,
            'max_score' => 0,
        ];
    }

    private function categoryTitle(string $category): string
    {
        return match ($category) {
            'TWK' => 'TWK - Wawasan Kebangsaan',
            'TIU' => 'TIU - Intelegensia Umum',
            'TKP' => 'TKP - Karakteristik Pribadi',
            default => $category,
        };
    }

    private function categoryStatusLabel(string $status): string
    {
        return match ($status) {
            'strong' => 'Kuat',
            'medium' => 'Sedang',
            default => 'Lemah',
        };
    }

    private function categoryDescription(string $category, string $status): string
    {
        $descriptions = [
            'TWK' => [
                'weak' => 'Perkuat Pancasila, UUD 1945, NKRI, dan Bhinneka Tunggal Ika.',
                'medium' => 'Nilai TWK cukup baik, rapikan lagi konsep kebangsaan dan konstitusi.',
                'strong' => 'TWK sudah kuat. Pertahankan dengan review singkat dan latihan campuran.',
            ],
            'TIU' => [
                'weak' => 'Fokus latihan numerik, logika, analitis, dan silogisme.',
                'medium' => 'TIU sudah cukup, tambah latihan kecepatan hitung dan penalaran.',
                'strong' => 'TIU sudah stabil. Jaga ritme dengan soal campuran berwaktu.',
            ],
            'TKP' => [
                'weak' => 'Perbanyak latihan studi kasus pelayanan publik dan profesionalisme.',
                'medium' => 'TKP cukup baik, tingkatkan konsistensi memilih opsi bernilai tertinggi.',
                'strong' => 'TKP sudah kuat. Pertahankan dengan simulasi kasus terbaru.',
            ],
        ];

        return $descriptions[$category][$status] ?? 'Lanjutkan latihanmu untuk memperkuat kategori ini.';
    }

    private function buildCategoryMetrics(TryoutResult $result, Tryout $tryout): array
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

            $options = collect($soal->options ?? [])
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
                ->values();

            if ($category === 'TKP') {
                $selected = $options->firstWhere('key', $userAnswer);
                $categoryScores['TKP'] += (int) ($selected['score'] ?? 0);

                return;
            }

            $correctAnswer = strtoupper((string) ($soal->correct_answer ?? ''));

            if ($userAnswer === $correctAnswer) {
                $categoryScores[$category] += 5;
            }
        });

        $categoryMaxScores = [
            'TWK' => (int) ($tryout->twk_target ?? 0) * 5,
            'TIU' => (int) ($tryout->tiu_target ?? 0) * 5,
            'TKP' => (int) ($tryout->tkp_target ?? 0) * 5,
        ];

        $passed = $categoryScores['TWK'] >= (int) ($tryout->twk_pg ?? 0)
            && $categoryScores['TIU'] >= (int) ($tryout->tiu_pg ?? 0)
            && $categoryScores['TKP'] >= (int) ($tryout->tkp_pg ?? 0);

        return [
            'category_scores' => $categoryScores,
            'category_max_scores' => $categoryMaxScores,
            'passed' => $passed,
        ];
    }

    private function latestCompletedResultsQuery(int $tryoutId)
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

    private function resolveRank(int $tryoutId, int $score, int $userId): int
    {
        return (clone $this->latestCompletedResultsQuery($tryoutId))
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
