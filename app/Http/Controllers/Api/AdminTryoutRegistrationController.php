<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TryoutResult;
use App\Models\Tryout;
use App\Models\TryoutRegistration;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminTryoutRegistrationController extends Controller
{
    /**
     * Menampilkan daftar user yang sudah register tryout tetapi belum memulai.
     */
    public function pending(Request $request)
    {
        $perPage = max((int) $request->integer('per_page', 15), 1);
        $search = trim((string) $request->input('search', ''));
        $tryoutId = $request->integer('tryout_id');

        $query = TryoutRegistration::query()
            ->with([
                'user:id,name,email,image',
                'tryout:id,title,duration,type,twk_target,tiu_target,tkp_target',
            ])
            ->where('status', 'registered')
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
            ->orderByDesc('registered_at')
            ->orderByDesc('id');

        $registrations = $query->paginate($perPage)->withQueryString();

        $registrations->getCollection()->transform(function (TryoutRegistration $registration) {
            $user = $registration->user;
            $tryout = $registration->tryout;

            if (!$user || !$tryout) {
                return null;
            }

            $registeredAt = $registration->registered_at ? Carbon::parse($registration->registered_at) : null;
            $expiresAt = $registration->expires_at ? Carbon::parse($registration->expires_at) : null;

            return [
                'id' => $registration->id,
                'status' => (string) ($registration->status ?? 'registered'),
                'is_expired' => $expiresAt ? Carbon::now()->greaterThan($expiresAt) : false,
                'registered_at' => optional($registeredAt)->toDateTimeString(),
                'expires_at' => optional($expiresAt)->toDateTimeString(),
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
                    'type' => $tryout->type,
                ],
            ];
        });

        $registrations->setCollection($registrations->getCollection()->filter()->values());

        return response()->json([
            'status' => true,
            'data' => $registrations->items(),
            'meta' => [
                'current_page' => $registrations->currentPage(),
                'last_page' => $registrations->lastPage(),
                'per_page' => $registrations->perPage(),
                'total' => $registrations->total(),
                'from' => $registrations->firstItem(),
                'to' => $registrations->lastItem(),
                'search' => $search,
                'tryout_id' => $tryoutId > 0 ? $tryoutId : null,
            ],
        ]);
    }

    /**
     * Menampilkan rekap jumlah peserta per tryout berdasarkan status pengerjaan.
     */
    public function summary(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $tryouts = Tryout::query()
            ->select(['id', 'title', 'type', 'duration', 'status'])
            ->withCount([
                'registrations as completed_count' => function (Builder $builder) {
                    $builder->where('status', 'completed');
                },
                'registrations as started_count' => function (Builder $builder) {
                    $builder->where('status', 'started');
                },
                'registrations as registered_count' => function (Builder $builder) {
                    $builder->where('status', 'registered');
                },
                'registrations as total_count',
            ])
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%");
            })
            ->orderBy('title')
            ->get()
            ->map(function (Tryout $tryout, int $index) {
                return [
                    'id' => $tryout->id,
                    'name' => $tryout->title,
                    'type' => $tryout->type,
                    'duration' => (int) ($tryout->duration ?? 0),
                    'publish_status' => $tryout->status,
                    'completed_count' => (int) ($tryout->completed_count ?? 0),
                    'started_count' => (int) ($tryout->started_count ?? 0),
                    'registered_count' => (int) ($tryout->registered_count ?? 0),
                    'total_count' => (int) ($tryout->total_count ?? 0),
                    'order_number' => $index + 1,
                ];
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => $tryouts,
            'summary' => [
                'total_tryouts' => $tryouts->count(),
                'total_completed' => $tryouts->sum('completed_count'),
                'total_started' => $tryouts->sum('started_count'),
                'total_registered' => $tryouts->sum('registered_count'),
                'grand_total' => $tryouts->sum('total_count'),
                'search' => $search,
            ],
        ]);
    }

    /**
     * Menampilkan peserta tryout berdasarkan status tertentu.
     */
    public function participants(Request $request, int $tryoutId)
    {
        $status = (string) $request->input('status', 'registered');
        $search = trim((string) $request->input('search', ''));

        abort_unless(in_array($status, ['completed', 'started', 'registered'], true), 404);

        $tryout = Tryout::query()->select(['id', 'title', 'type', 'duration'])->findOrFail($tryoutId);

        $query = TryoutRegistration::query()
            ->with([
                'user:id,name,email,image',
                'tryout:id,title,duration,type',
            ])
            ->where('tryout_id', $tryout->id)
            ->where('status', $status)
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->whereHas('user', function (Builder $userQuery) use ($search) {
                    $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc($status === 'completed' ? 'finished_at' : ($status === 'started' ? 'started_at' : 'registered_at'))
            ->orderByDesc('id');

        $participants = $query->get()->map(function (TryoutRegistration $registration) use ($status) {
            $user = $registration->user;
            $tryout = $registration->tryout;

            if (!$user || !$tryout) {
                return null;
            }

            $registeredAt = $registration->registered_at ? Carbon::parse($registration->registered_at) : null;
            $startedAt = $registration->started_at ? Carbon::parse($registration->started_at) : null;
            $finishedAt = $registration->finished_at ? Carbon::parse($registration->finished_at) : null;
            $expiresAt = $registration->expires_at ? Carbon::parse($registration->expires_at) : null;

            $latestResult = TryoutResult::query()
                ->where('user_id', $registration->user_id)
                ->where('tryout_id', $registration->tryout_id)
                ->latestAttempt()
                ->first();

            $answeredCount = 0;
            $progressPercent = 0;
            $remainingSeconds = null;

            if ($status === 'started' && $latestResult) {
                $totalQuestions = ($tryout->twk_target ?? 0)
                    + ($tryout->tiu_target ?? 0)
                    + ($tryout->tkp_target ?? 0);

                $answeredCount = is_array($latestResult->answers)
                    ? collect($latestResult->answers)->filter(fn ($value) => $value !== null && $value !== '')->count()
                    : 0;

                $progressPercent = $totalQuestions > 0
                    ? round(($answeredCount / $totalQuestions) * 100, 1)
                    : 0;

                $deadline = $startedAt ? $startedAt->copy()->addMinutes($tryout->duration ?? 0) : null;
                $remainingSeconds = $deadline ? max(Carbon::now()->diffInSeconds($deadline, false), 0) : null;
            }

            return [
                'id' => $registration->id,
                'status' => (string) $registration->status,
                'registered_at' => optional($registeredAt)->toDateTimeString(),
                'started_at' => optional($startedAt)->toDateTimeString(),
                'finished_at' => optional($finishedAt)->toDateTimeString(),
                'expires_at' => optional($expiresAt)->toDateTimeString(),
                'is_expired' => $expiresAt ? Carbon::now()->greaterThan($expiresAt) : false,
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
                    'type' => $tryout->type,
                ],
                'progress_percent' => $progressPercent,
                'answered_count' => $answeredCount,
                'remaining_seconds' => $remainingSeconds,
                'attempt_number' => (int) ($latestResult?->attempt_number ?? 1),
            ];
        })->filter()->values();

        return response()->json([
            'status' => true,
            'data' => $participants,
            'meta' => [
                'status' => $status,
                'search' => $search,
                'total' => $participants->count(),
            ],
            'tryout' => [
                'id' => $tryout->id,
                'title' => $tryout->title,
                'type' => $tryout->type,
                'duration' => (int) ($tryout->duration ?? 0),
            ],
        ]);
    }
}
