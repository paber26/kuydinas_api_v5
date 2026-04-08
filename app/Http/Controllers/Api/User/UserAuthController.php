<?php

namespace App\Http\Controllers\Api\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Throwable;

class UserAuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','unique:users,email'],
            'password' => ['required', 'confirmed', 'min:6']
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'is_active' => true
        ]);

        $verificationEmailSent = true;
        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            $verificationEmailSent = false;
            Log::error('Failed to send verification email after registration.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }
        $token = $user->createToken('user_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => $verificationEmailSent
                ? 'Register berhasil. Silakan cek email untuk verifikasi akun.'
                : 'Register berhasil, namun email verifikasi gagal dikirim. Silakan coba kirim ulang dari halaman profil.',
            'data' => [
                'user' => $this->serializeUser($user),
                'token' => $token,
                'verification_email_sent' => $verificationEmailSent,
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

        $user = User::where('email', $credentials['email'])->firstOrFail();

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

    public function sendVerificationNotification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => true,
                'message' => 'Email akun sudah terverifikasi.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'status' => true,
            'message' => 'Email verifikasi berhasil dikirim ulang.',
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::find($id);

        if (!$user || !hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->redirectToFrontend('verification', 'invalid');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $this->redirectToFrontend('verification', 'success');
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Akun dengan email tersebut tidak ditemukan.',
                'errors' => [
                    'email' => ['Akun dengan email tersebut tidak ditemukan.'],
                ],
            ], 422);
        }

        if ($user->provider === 'google') {
            return response()->json([
                'status' => false,
                'message' => 'Akun ini menggunakan login Google. Silakan masuk dengan Google.',
                'data' => [
                    'provider' => 'google',
                    'action' => 'google_login',
                ],
                'errors' => [
                    'email' => ['Akun ini menggunakan login Google.'],
                ],
            ], 422);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'status' => false,
                'message' => 'Email belum diverifikasi. Verifikasi email dulu sebelum reset password.',
                'errors' => [
                    'email' => ['Email belum diverifikasi.'],
                ],
            ], 422);
        }

        $status = Password::sendResetLink([
            'email' => $data['email'],
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => false,
                'message' => __($status),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Link reset password berhasil dikirim ke email kamu.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'status' => false,
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Password berhasil direset. Silakan login dengan password baru.',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $originalEmail = (string) $user->email;

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

        if ($originalEmail !== (string) $data['email']) {
            $payload['email_verified_at'] = null;
        }

        $user->update($payload);
        $user->refresh();

        if ($originalEmail !== (string) $user->email) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'status' => true,
            'message' => $originalEmail !== (string) $user->email
                ? 'Profil berhasil diperbarui. Email berubah, silakan verifikasi ulang email baru kamu.'
                : 'Profil berhasil diperbarui',
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
            'email_verified_at' => optional($user->email_verified_at)->toDateTimeString(),
            'is_email_verified' => $user->hasVerifiedEmail(),
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

    private function redirectToFrontend(string $type, string $status)
    {
        $frontendUserUrl = rtrim(
            (string) (env('FRONTEND_USER_URL') ?: env('FRONTEND_URL') ?: 'http://localhost:5174'),
            '/'
        );

        return redirect()->away($frontendUserUrl."/login?".$type."=".$status);
    }
}
