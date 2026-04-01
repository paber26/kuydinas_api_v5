<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tryout;
use App\Models\TryoutResult;
use Illuminate\Support\Facades\Schema;

class DummyRankingSeeder extends Seeder
{
    public function run()
    {
        $tryout = Tryout::where('status', 'publish')->with('soals')->first();

        if (!$tryout) {
            $this->command->error("Tidak ada tryout publish untuk di-seed!");
            return;
        }

        $this->command->info("Seeding data ranking untuk Tryout: " . $tryout->title);

        for ($i = 1; $i <= 10; $i++) {
            $user = User::firstOrCreate(
                ['email' => "dummy{$i}@kuydinas.id"],
                [
                    'name' => "Siswa Juara {$i}",
                    'password' => bcrypt('password'),
                    'role' => 'user',
                    'is_active' => true,
                ]
            );

            $answers = [];
            $score = 0;

            foreach ($tryout->soals as $soal) {
                $category = strtoupper(trim((string) $soal->category));
                
                if ($category === 'TKP') {
                    $options = collect($soal->options ?? [])->shuffle();
                    $opt = $options->first();
                    $label = data_get($opt, 'label');
                    
                    if ($label) {
                        $answers[$soal->id] = strtoupper($label);
                        $score += (int) data_get($opt, 'score', 0);
                    } else {
                        $answers[$soal->id] = 'A';
                    }
                } else {
                    // simulasi benar/salah acak 80% benar
                    $isCorrect = rand(1, 10) <= 8; 
                    if ($isCorrect) {
                        $answers[$soal->id] = strtoupper($soal->correct_answer ?? 'A');
                        $score += 5;
                    } else {
                        $answers[$soal->id] = 'Z'; 
                    }
                }
            }

            TryoutResult::where('user_id', $user->id)
                ->where('tryout_id', $tryout->id)
                ->delete();

            $payload = [
                'user_id' => $user->id,
                'tryout_id' => $tryout->id,
                'score' => $score,
                'answers' => $answers,
            ];

            if (Schema::hasColumn('tryout_results', 'attempt_number')) {
                $payload['attempt_number'] = 1;
            }
            if (Schema::hasColumn('tryout_results', 'status')) {
                $payload['status'] = 'completed';
            }

            TryoutResult::create($payload);
        }

        $this->command->info("Berhasil menambahkan 10 peserta dummy (beserta nilai dan jawaban) ke tryout ID {$tryout->id}!");
    }
}
