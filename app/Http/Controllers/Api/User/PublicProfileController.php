<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\TryoutResult;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicProfileController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

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

        $tryouts = $completedResults->map(function (TryoutResult $result) {
            $tryout = $result->tryout;

            if (!$tryout) {
                return null;
            }

            $score = $this->buildCategoryScores($result);

            return [
                'tryout' => [
                    'id' => $tryout->id,
                    'title' => $tryout->title,
                    'type' => $tryout->type,
                ],
                'score' => [
                    'twk' => $score['TWK'],
                    'tiu' => $score['TIU'],
                    'tkp' => $score['TKP'],
                    'total' => $score['TWK'] + $score['TIU'] + $score['TKP'],
                ],
                'finished_at' => $result->finished_at ? \Carbon\Carbon::parse($result->finished_at)->toDateTimeString() : null,
            ];
        })->filter()->values();

        return response()->json([
            'status' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'image' => $user->image,
                    'regency_name' => $user->regency_name,
                    'province_name' => $user->province_name,
                ],
                'tryouts' => $tryouts,
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
