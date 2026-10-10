<?php

namespace App\Http\Controllers\Auth;

use App\GoogleIdTokenVerifier;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GoogleLoginController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! filled(config('services.google_login.client_id')) || ! filled(config('services.google_login.client_secret'))) {
            return redirect()->route('login')->withErrors(['google' => 'Login Google belum dikonfigurasi.']);
        }

        $state = Str::random(48);
        $nonce = Str::random(48);
        $codeVerifier = Str::random(64);
        $request->session()->put('google_login_oauth', [
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'started_at' => time(),
        ]);

        $authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_login.client_id'),
            'redirect_uri' => route('login.google.callback'),
            'response_type' => 'code',
            'scope' => 'openid email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]);

        return redirect()->away($authorizationUrl);
    }

    public function callback(Request $request, GoogleIdTokenVerifier $verifier): RedirectResponse
    {
        $oauth = $request->session()->pull('google_login_oauth');
        $state = $request->query('state');

        abort_unless(is_array($oauth)
            && is_string($oauth['state'] ?? null)
            && is_string($oauth['nonce'] ?? null)
            && is_string($oauth['code_verifier'] ?? null)
            && is_int($oauth['started_at'] ?? null)
            && $oauth['started_at'] >= time() - 600
            && is_string($state)
            && hash_equals($oauth['state'], $state), 403);

        if ($request->query('error') !== null) {
            return redirect()->route('login')->withErrors(['google' => 'Login Google dibatalkan.']);
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return redirect()->route('login')->withErrors(['google' => 'Kode login Google tidak tersedia. Coba lagi.']);
        }

        try {
            $tokens = Http::asForm()->connectTimeout(5)->timeout(20)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => $code,
                    'client_id' => config('services.google_login.client_id'),
                    'client_secret' => config('services.google_login.client_secret'),
                    'redirect_uri' => route('login.google.callback'),
                    'code_verifier' => $oauth['code_verifier'],
                    'grant_type' => 'authorization_code',
                ]);

            $idToken = $tokens->json('id_token');
            if (! $tokens->successful() || ! is_string($idToken)) {
                throw new RuntimeException('Google tidak dapat memverifikasi login. Coba lagi.');
            }

            $identity = $verifier->verify($idToken, $oauth['nonce']);
            $user = $this->findRegisteredUser($identity['email'], $identity['sub']);
        } catch (RuntimeException|UniqueConstraintViolationException $exception) {
            return redirect()->route('login')->withErrors(['google' => 'Identitas Google tidak dapat diverifikasi. Coba lagi.']);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors(['google' => 'Layanan login Google sedang bermasalah. Coba lagi.']);
        }

        if ($user === null) {
            return redirect()->route('login')->withErrors(['google' => 'Akun Google tidak terdaftar, tidak aktif, atau tidak sesuai dengan akun yang terhubung.']);
        }

        if (! $user->hasRole('admin') && ! $user->hasRole('user')) {
            return redirect()->route('login')->withErrors(['google' => 'Akun belum memiliki hak akses. Hubungi admin.']);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $home = $user->hasRole('admin') ? 'admin.dashboard' : 'dashboard';

        if ($user->hasRole('admin') && $request->session()->get('url.intended') === route('dashboard')) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route($home, absolute: false));
    }

    private function findRegisteredUser(string $email, string $googleSub): ?User
    {
        return DB::transaction(function () use ($email, $googleSub): ?User {
            $users = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->lockForUpdate()
                ->limit(2)
                ->get();

            if ($users->count() !== 1) {
                return null;
            }

            $user = $users->first();
            if (! $user->is_active || ($user->google_sub !== null && ! hash_equals($user->google_sub, $googleSub))) {
                return null;
            }

            if ($user->google_sub === null && ($user->hasRole('admin') || $user->hasRole('user'))) {
                $user->google_sub = $googleSub;
                $user->save();
            }

            return $user;
        });
    }
}
