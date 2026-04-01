<?php

namespace App\Http\Controllers\Api\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use App\Http\Controllers\Controller;

class UserAuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','unique:users,email'],
            'password' => ['required','min:6']
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'is_active' => true
        ]);

        $token = $user->createToken('user_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Register berhasil',
            'data' => [
                'user' => $this->serializeUser($user),
                'token' => $token
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required']
        ]);

        if (!Auth::attempt($credentials)) {

            return response()->json([
                'status' => false,
                'message' => 'Email atau password salah'
            ], 401);

        }

        $user = Auth::user();

        // Cek apakah akun aktif
        if (!$user->is_active) {

            Auth::logout();

            return response()->json([
                'status' => false,
                'message' => 'Akun dinonaktifkan'
            ], 403);

        }

        // Pastikan hanya user biasa
        if ($user->role !== 'user') {

            Auth::logout();

            return response()->json([
                'status' => false,
                'message' => 'Akses ditolak. Bukan akun user.'
            ], 403);

        }

        // Update last login
        $user->update([
            'last_login' => Carbon::now()
        ]);

        // Simpan / update device login
        UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device' => $request->header('User-Agent')
            ],
            [
                'device_type' => 'web',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'last_login' => Carbon::now()
            ]
        );

        $token = $user
            ->createToken('user_token')
            ->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login user berhasil',
            'data' => [
                'user' => $this->serializeUser($user),
                'token' => $token
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
            'data' => [
                'user' => $this->serializeUser($user),
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'whatsapp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'province_name' => ['nullable', 'string', 'max:255'],
            'regency_code' => ['nullable', 'string', 'max:20'],
            'regency_name' => ['nullable', 'string', 'max:255'],
            'district_code' => ['nullable', 'string', 'max:20'],
            'district_name' => ['nullable', 'string', 'max:255'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'confirmed', 'min:6'],
        ]);

        if (!empty($data['password']) && !Hash::check((string) ($data['current_password'] ?? ''), (string) $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Password saat ini tidak sesuai',
                'errors' => [
                    'current_password' => ['Password saat ini tidak sesuai'],
                ],
            ], 422);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'province_code' => $data['province_code'] ?? null,
            'province_name' => $data['province_name'] ?? null,
            'regency_code' => $data['regency_code'] ?? null,
            'regency_name' => $data['regency_name'] ?? null,
            'district_code' => $data['district_code'] ?? null,
            'district_name' => $data['district_name'] ?? null,
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);
        $user->refresh();

        return response()->json([
            'status' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => [
                'user' => $this->serializeUser($user),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Logout dari semua device
            $user->tokens()->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'coin_balance' => (int) ($user->coin_balance ?? 0),
            'last_login' => optional($user->last_login)->toDateTimeString(),
            'provider' => $user->provider,
            'image' => $user->image,
            'whatsapp' => $user->whatsapp,
            'province_code' => $user->province_code,
            'province_name' => $user->province_name,
            'regency_code' => $user->regency_code,
            'regency_name' => $user->regency_name,
            'district_code' => $user->district_code,
            'district_name' => $user->district_name,
        ];
    }
}
