<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tryout;
use App\Models\TryoutRegistration;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TryoutRegistrationController extends Controller
{
    public function register(Request $request, $id)
    {
        $user = $request->user();
        $tryout = Tryout::where('status', 'publish')->findOrFail($id);

        $result = DB::transaction(function () use ($user, $tryout) {
            $existingRegistration = TryoutRegistration::where('user_id', $user->id)
                ->where('tryout_id', $tryout->id)
                ->lockForUpdate()
                ->first();

            if ($existingRegistration) {
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
        ], 201);
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

                return [
                    'id' => $registration->id,
                    'tryout_id' => $registration->tryout_id,
                    'status' => $registration->status,
                    'registered_at' => $registration->registered_at,
                    'started_at' => $registration->started_at,
                    'finished_at' => $registration->finished_at,
                    'title' => $tryout->title,
                    'duration' => $tryout->duration,
                    'price' => $tryout->price ?? 0,
                    'discount' => $tryout->discount,
                    'type' => $tryout->type,
                    'isFree' => $tryout->type === 'free',
                    'questionCount' => $questionCount,
                    'tryout' => $tryout,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $registrations,
        ]);
    }
}
