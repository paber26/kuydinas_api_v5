<?php

namespace App\Console\Commands;

use App\Models\TryoutResult;
use Illuminate\Console\Command;

class RecalculateTryoutScores extends Command
{
    protected $signature = 'tryout:recalculate-scores {--dry-run : Preview changes without saving}';

    protected $description = 'Recalculate and fix stored scores for all completed tryout results';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $results = TryoutResult::with(['tryout.soals'])
            ->where('status', 'completed')
            ->get();

        $this->info("Found {$results->count()} completed results.");

        $fixed = 0;
        $skipped = 0;
        $noTryout = 0;

        foreach ($results as $result) {
            if (!$result->tryout || $result->tryout->soals->isEmpty()) {
                $noTryout++;
                continue;
            }

            [$recalcScore, $recalcCorrect] = $this->recalculate($result);

            if ($result->score === $recalcScore) {
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                'ID:%d Tryout:%d Stored:%d → Recalc:%d',
                $result->id,
                $result->tryout_id,
                $result->score,
                $recalcScore,
            ));

            if (!$isDryRun) {
                $result->update([
                    'score' => $recalcScore,
                    'correct_answer' => $recalcCorrect,
                ]);
            }

            $fixed++;
        }

        $this->info("Done. Fixed: {$fixed}, Already correct: {$skipped}, No tryout data: {$noTryout}");

        if ($isDryRun) {
            $this->warn('Dry run — no changes saved.');
        }

        return self::SUCCESS;
    }

    private function recalculate(TryoutResult $result): array
    {
        $answers = collect($result->answers ?? [])
            ->mapWithKeys(fn($a, $id) => [$id => $a ? strtoupper((string) $a) : null]);

        $score = 0;
        $correct = 0;

        foreach ($result->tryout->soals as $soal) {
            $userAnswer = $answers[$soal->id] ?? $answers[(string) $soal->id] ?? null;

            if (!$userAnswer) {
                continue;
            }

            if ($soal->category !== 'TKP') {
                if ($userAnswer === strtoupper((string) ($soal->correct_answer ?? ''))) {
                    $score += 5;
                    $correct++;
                }
            } else {
                $selected = collect($soal->options ?? [])
                    ->first(fn($o) => strtoupper((string) data_get($o, 'label', '')) === $userAnswer);

                if ($selected && isset($selected['score'])) {
                    $selectedScore = (int) $selected['score'];
                    $score += $selectedScore;
                }
            }
        }

        return [$score, $correct];
    }
}
