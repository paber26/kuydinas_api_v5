<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->filled('code') || $request->filled('error')) {
            return $this->callback($request);
        }

        $requestedScope = $this->resolveScope($request);
        $requestedRedirectUrl = $this->resolveRequestedRedirectUrl($request, $requestedScope);

        config([
            'services.google.redirect' => $this->resolveGoogleRedirectUri($request),
        ]);

        return Socialite::driver('google')
            ->stateless()
            ->with([
                'state' => $this->encodeState([
                    'scope' => $requestedScope,
                    'redirect_url' => $requestedRedirectUrl,
                ]),
            ])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $requestedScope = $this->resolveScope($request);
        $state = $this->decodeState($request->query('state'));
        $requestedRedirectUrl = $state['redirect_url']
            ?? $this->resolveDefaultFrontendRedirectUrl($requestedScope);

        config([
            'services.google.redirect' => $this->resolveGoogleRedirectUri($request),
        ]);

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();
        } catch (Throwable $exception) {
            return $this->redirectWithError(
                $requestedRedirectUrl,
                'Google authentication failed'
            );
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            $user = new User([
                'email' => $googleUser->getEmail(),
                'password' => bcrypt(Str::random(32)),
                'role' => $requestedScope === 'admin' ? 'admin' : 'user',
                'is_active' => true,
            ]);
        }

        $isActive = $user->is_active;
        $user->name = $googleUser->getName() ?: $user->name ?: $googleUser->getEmail();
        $user->provider = 'google';
        $user->provider_id = $googleUser->getId();
        $user->is_active = $isActive;
        $user->save();

        $actualScope = $this->scopeFromRole($user->role);
        $targetRedirectUrl = $this->resolveTargetRedirectUrl(
            $requestedScope,
            $actualScope,
            $requestedRedirectUrl
        );

        if (!$user->is_active) {
            return $this->redirectWithError(
                $targetRedirectUrl,
                'Akun dinonaktifkan'
            );
        }

        $this->recordLogin($user, $request);

        $token = $user->createToken("{$actualScope}_google_token")->plainTextToken;

        return $this->redirectWithAuth($targetRedirectUrl, $token, $user);
    }

    private function resolveScope(Request $request): string
    {
        $scope = $request->route('scope');

        if (in_array($scope, ['admin', 'user'], true)) {
            return $scope;
        }

        return $request->is('api/admin/*') ? 'admin' : 'user';
    }

    private function scopeFromRole(?string $role): string
    {
        return $role === 'admin' ? 'admin' : 'user';
    }

    private function resolveGoogleRedirectUri(Request $request): string
    {
        return $request->url();
    }

    private function resolveRequestedRedirectUrl(Request $request, string $scope): string
    {
        $redirectUrl = $request->query('redirect_url');

        if (is_string($redirectUrl) && filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            return $redirectUrl;
        }

        return $this->resolveDefaultFrontendRedirectUrl($scope);
    }

    private function resolveDefaultFrontendRedirectUrl(string $scope): string
    {
        $baseUrl = $scope === 'admin'
            ? env('FRONTEND_ADMIN_URL', env('FRONTEND_URL', 'http://localhost:5173'))
            : env('FRONTEND_USER_URL', env('FRONTEND_URL', 'http://localhost:5173'));

        $baseUrl = rtrim($baseUrl, '/');

        if (str_contains($baseUrl, '/auth/google/callback')) {
            return $baseUrl;
        }

        return $baseUrl . '/auth/google/callback';
    }

    private function resolveTargetRedirectUrl(
        string $requestedScope,
        string $actualScope,
        string $requestedRedirectUrl
    ): string {
        if ($requestedScope === $actualScope) {
            return $requestedRedirectUrl;
        }

        return $this->resolveDefaultFrontendRedirectUrl($actualScope);
    }

    private function recordLogin(User $user, Request $request): void
    {
        $timestamp = Carbon::now();

        $user->update([
            'last_login' => $timestamp,
            'device_login' => $request->header('User-Agent'),
        ]);

        UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device' => $request->header('User-Agent'),
            ],
            [
                'device_type' => 'web',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'last_login' => $timestamp,
            ]
        );
    }

    private function redirectWithAuth(string $redirectUrl, string $token, User $user): RedirectResponse
    {
        $url = $this->appendQuery($redirectUrl, [
            'token' => $token,
            'role' => $user->role,
            'user' => json_encode([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]),
        ]);

        return redirect()->away($url);
    }

    private function redirectWithError(string $redirectUrl, string $message): RedirectResponse
    {
        return redirect()->away($this->appendQuery($redirectUrl, [
            'error' => $message,
        ]));
    }

    private function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($params);
    }

    private function encodeState(array $state): string
    {
        return base64_encode(json_encode($state));
    }

    private function decodeState(?string $state): array
    {
        if (!$state) {
            return [];
        }

        $decoded = json_decode(base64_decode($state), true);

        return is_array($decoded) ? $decoded : [];
    }
}
