<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tryout;
use App\Models\Soal;
use App\Models\TryoutRegistration;
use App\Models\TryoutResult;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TryoutController extends Controller
{

    /* ===============================
       LIST TRYOUT
    ================================= */

    public function index()
    {
        $tryouts = Tryout::withCount('soals')
            ->withCount('registrations')
            ->latest()
            ->get()
            ->map(function ($tryout) {

                $questionCount =
                    ($tryout->twk_target ?? 0) +
                    ($tryout->tiu_target ?? 0) +
                    ($tryout->tkp_target ?? 0);

                $data = $tryout->toArray();

                // tambahan untuk frontend (PromoCard)
                $data['subtitle'] = 'Simulasi lengkap seperti ujian asli';
                $data['category'] = 'SKD CPNS';
                $data['isFree'] = $tryout->type === 'free';
                $data['questionCount'] = $questionCount;
                $data['highlight'] = $tryout->type === 'free';
                $data['tag'] = $tryout->type;
                $data['level'] = 'Menengah';

                $data['seatsLeft'] = $tryout->quota
                    ? max($tryout->quota - $tryout->registrations_count, 0)
                    : null;

                return $data;
            });

        return response()->json([
            'status' => true,
            'data' => $tryouts
        ]);
    }


    /* ===============================
       DETAIL TRYOUT + SOAL
    ================================= */

    public function show($id)
    {
        $tryout = Tryout::with('soals')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $tryout
        ]);
    }


    /* ===============================
       CREATE TRYOUT
    ================================= */

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'duration' => 'required|integer',
            'type' => 'required|in:free,premium',
            'quota' => 'nullable|integer|min:1',
            'price' => 'nullable|integer|min:0',
            'discount' => 'nullable|integer|min:0|max:100',
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
            'data' => $tryout
        ], 201);
    }


    /* ===============================
       ATTACH SOAL
    ================================= */

    public function attachSoal(Request $request, $id)
    {
        $tryout = Tryout::with('soals')->findOrFail($id);

        if ($tryout->status === 'publish') {
            return response()->json([
                'status' => false,
                'message' => 'Tryout sudah dipublish'
            ], 422);
        }

        $request->validate([
            'soal_id' => 'required|exists:soals,id'
        ]);

        $soal = Soal::findOrFail($request->soal_id);

        if ($tryout->soals->contains($soal->id)) {
            return response()->json([
                'status' => false,
                'message' => 'Soal sudah ada'
            ], 422);
        }

        $currentCount = $tryout->soals
            ->where('category', $soal->category)
            ->count();

        $target = match ($soal->category) {
            'TWK' => $tryout->twk_target,
            'TIU' => $tryout->tiu_target,
            'TKP' => $tryout->tkp_target,
            default => 0
        };

        if ($currentCount >= $target) {
            return response()->json([
                'status' => false,
                'message' => 'Kuota kategori penuh'
            ], 422);
        }

        $lastOrder = DB::table('tryout_soal')
            ->where('tryout_id', $id)
            ->max('urutan_soal');

        $nextOrder = $lastOrder ? $lastOrder + 1 : 1;

        $tryout->soals()->attach($soal->id, [
            'urutan_soal' => $nextOrder
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Soal berhasil ditambahkan'
        ]);
    }


    /* ===============================
       DETACH SOAL
    ================================= */

    public function detachSoal($id, $soalId)
    {
        $tryout = Tryout::findOrFail($id);

        if ($tryout->status === 'publish') {
            return response()->json([
                'status' => false,
                'message' => 'Tryout sudah dipublish'
            ], 422);
        }

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
                    'urutan_soal' => $order
                ]);

            $order++;
        }

        return response()->json([
            'status' => true,
            'message' => 'Soal berhasil dihapus'
        ]);
    }


    /* ===============================
       PUBLISH TRYOUT
    ================================= */

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
                'message' => 'Komposisi belum sesuai'
            ], 422);
        }

        $tryout->update([
            'status' => 'publish'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tryout berhasil dipublish'
        ]);
    }


    /* ===============================
       START TRYOUT
    ================================= */

    public function start(Request $request, $id)
    {
        $user = $request->user();

        $tryout = Tryout::with([
            'soals' => function ($q) {
                $q->orderBy('pivot_urutan_soal');
            }
        ])->findOrFail($id);

        $registration = TryoutRegistration::where('user_id', $user->id)
            ->where('tryout_id', $tryout->id)
            ->first();

        if (!$registration) {
            return response()->json([
                'status' => false,
                'message' => 'Silakan registrasi tryout terlebih dahulu'
            ], 422);
        }

        $session = TryoutResult::firstOrCreate(
            [
                'user_id' => $user->id,
                'tryout_id' => $tryout->id
            ],
            [
                'answers' => [],
                'score' => 0,
                'correct_answer' => 0,
                'started_at' => Carbon::now()
            ]
        );

        if ($registration->status === 'registered') {
            $registration->update([
                'status' => 'started',
                'started_at' => $session->started_at ?? Carbon::now(),
            ]);
        } elseif (!$registration->started_at && $session->started_at) {
            $registration->update([
                'started_at' => $session->started_at,
            ]);
        }

        $endTime = Carbon::parse($session->started_at)
            ->addMinutes($tryout->duration);

        return response()->json([
            'status' => true,
            'data' => [
                'started_at' => $session->started_at,
                'end_time' => $endTime,
                'duration' => $tryout->duration,
                'questions' => $tryout->soals
            ]
        ]);
    }

    /* ===============================
       AUTOSAVE
    ================================= */

    public function autosave(Request $request, $id)
    {
        $user = $request->user();

        $request->validate([
            'answers' => 'required|array'
        ]);

        TryoutResult::where('user_id', $user->id)
            ->where('tryout_id', $id)
            ->update([
                'answers' => $request->answers
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Jawaban tersimpan'
        ]);
    }


    /* ===============================
       REMAINING TIME
    ================================= */

    public function remainingTime(Request $request, $id)
    {
        $user = $request->user();

        $tryout = Tryout::findOrFail($id);

        $result = TryoutResult::where('user_id', $user->id)
            ->where('tryout_id', $tryout->id)
            ->first();

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
            'remaining_seconds' => max($remaining, 0)
        ]);
    }


    /* ===============================
       SUBMIT TRYOUT
    ================================= */

    public function submit(Request $request, $id)
    {
        $user = $request->user();

        $tryout = Tryout::with([
            'soals' => function ($q) {
                $q->orderBy('pivot_urutan_soal');
            }
        ])->findOrFail($id);

        $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'nullable|string',
        ]);

        $answers = collect($request->input('answers', []))
            ->mapWithKeys(function ($answer, $questionId) {
                if ($answer === null) {
                    return [$questionId => null];
                }

                return [$questionId => strtoupper(trim((string) $answer))];
            })
            ->all();

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

        TryoutResult::updateOrCreate(
            [
                'user_id' => $user->id,
                'tryout_id' => $tryout->id
            ],
            [
                'score' => $score,
                'correct_answer' => $correctAnswer,
                'answers' => $answers
            ]
        );

        TryoutRegistration::where('user_id', $user->id)
            ->where('tryout_id', $tryout->id)
            ->update([
                'status' => 'completed',
                'finished_at' => $finishedAt,
            ]);

        Cache::forget("ranking_tryout_{$id}");

        return response()->json([
            'status' => true,
            'message' => 'Tryout selesai',
            'data' => [
                'score' => $score,
                'correct_answer' => $correctAnswer,
                'answers' => $answers,
                'finished_at' => $finishedAt->toDateTimeString(),
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
            'message' => 'Tryout berhasil dihapus'
        ]);
    }


    public function update(Request $request, $id)
    {
        $tryout = Tryout::findOrFail($id);

        if ($tryout->status === 'publish') {
            return response()->json([
                'status' => false,
                'message' => 'Tryout sudah dipublish dan tidak dapat diubah'
            ], 422);
        }

        $request->validate([
            'twk_count' => 'required|integer|min:0',
            'tiu_count' => 'required|integer|min:0',
            'tkp_count' => 'required|integer|min:0',
            'type' => 'nullable|in:free,premium',
            'price' => 'nullable|integer|min:0',
            'discount' => 'nullable|integer|min:0|max:100',
        ]);

        $tryout->update([
            'twk_target' => $request->twk_count,
            'tiu_target' => $request->tiu_count,
            'tkp_target' => $request->tkp_count,

            'type' => $request->type ?? $tryout->type,
            'price' => $request->price ?? $tryout->price,
            'discount' => $request->discount ?? $tryout->discount,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tryout berhasil diperbarui',
            'data' => $tryout
        ]);
    }


    public function reorder(Request $request, $id)
    {
        $request->validate([
            'orders' => 'required|array'
        ]);

        foreach ($request->orders as $soalId => $order) {

            DB::table('tryout_soal')
                ->where([
                    'tryout_id' => $id,
                    'soal_id' => $soalId
                ])
                ->update([
                    'urutan_soal' => $order
                ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Urutan soal berhasil diperbarui'
        ]);
    }

}
