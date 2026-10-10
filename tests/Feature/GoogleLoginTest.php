<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_option_uses_existing_oauth_client_configuration(): void
    {
        config()->set('services.google_drive.client_id', 'drive-client-id');
        config()->set('services.google_drive.client_secret', 'drive-client-secret');
        config()->set('services.google_login.client_id', null);
        config()->set('services.google_login.client_secret', null);

        $this->get(route('login'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login')->where('googleLoginEnabled', false));
        $this->get(route('login.google.redirect'))->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->configureGoogleLogin();
        $this->get(route('login'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login')->where('googleLoginEnabled', true));
    }

    public function test_registered_active_user_can_sign_in_and_is_bound_to_google_subject(): void
    {
        $this->configureGoogleLogin();
        $user = User::factory()->create(['email' => 'petugas@example.test']);
        $this->get('/dashboard')->assertRedirect('/login');
        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, 'petugas@example.test', 'google-sub-123');

        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-sub-123', $user->fresh()->google_sub);
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request->data()['code_verifier'] === $oauth['code_verifier']
            && $request->data()['client_id'] === 'google-client-id'
            && $request->data()['client_secret'] === 'google-client-secret');
    }

    public function test_registered_admin_returns_to_admin_dashboard(): void
    {
        $this->configureGoogleLogin();
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        $admin->assignRole('admin');
        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, 'admin@example.test', 'google-admin-sub');

        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_opening_pwa_dashboard_before_google_login_returns_to_admin_dashboard(): void
    {
        $this->configureGoogleLogin();
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        $admin->assignRole('admin');
        $this->get('/dashboard')->assertRedirect('/login');
        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, $admin->email, 'google-admin-sub');

        $response = $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']));

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->get($response->headers->get('Location'))->assertOk();
    }

    public function test_roleless_google_account_receives_login_error_instead_of_dashboard_403(): void
    {
        $this->configureGoogleLogin();
        $user = User::query()->create([
            'name' => 'Belum Diberi Role',
            'email' => 'tanpa-role@example.test',
            'password' => 'password',
        ]);
        $this->get('/dashboard')->assertRedirect('/login');
        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, $user->email, 'google-roleless-sub');

        $response = $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']));

        $this->assertGuest();
        $response->assertRedirect(route('login'))->assertSessionHasErrors([
            'google' => 'Akun belum memiliki hak akses. Hubungi admin.',
        ]);
        $this->assertNull($user->fresh()->google_sub);
    }

    public function test_unregistered_google_account_does_not_create_a_user(): void
    {
        $this->configureGoogleLogin();
        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, 'unknown@example.test', 'unknown-sub');

        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_inactive_or_differently_bound_account_cannot_sign_in(): void
    {
        $this->configureGoogleLogin();
        $user = User::factory()->create(['email' => 'petugas@example.test', 'is_active' => false]);
        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, $user->email, 'google-sub-123');

        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_sub);

        $user->update(['is_active' => true]);
        $user->google_sub = 'different-sub';
        $user->save();
        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, $user->email, 'google-sub-123');

        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertSame('different-sub', $user->fresh()->google_sub);
    }

    public function test_unverified_email_and_invalid_token_are_rejected(): void
    {
        $this->configureGoogleLogin();
        $user = User::factory()->create(['email' => 'petugas@example.test']);
        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, $user->email, 'google-sub-123', ['email_verified' => false]);

        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_sub);

        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, $user->email, 'google-sub-123', ['aud' => 'wrong-client']);
        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_token_with_invalid_signature_or_nonce_is_rejected(): void
    {
        $this->configureGoogleLogin();
        $user = User::factory()->create(['email' => 'petugas@example.test']);
        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, $user->email, 'google-sub-123', [], true);

        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_sub);

        $oauth = $this->startGoogleLogin();
        $this->fakeGoogleIdentity($oauth, $user->email, 'google-sub-123', ['nonce' => 'wrong-nonce']);
        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_invalid_or_replayed_oauth_state_is_rejected_before_token_exchange(): void
    {
        $this->configureGoogleLogin();
        $oauth = $this->startGoogleLogin();
        Http::preventStrayRequests();

        $this->get(route('login.google.callback', ['state' => 'wrong-state', 'code' => 'valid-code']))
            ->assertForbidden();
        $this->get(route('login.google.callback', ['state' => $oauth['state'], 'code' => 'valid-code']))
            ->assertForbidden();

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_changing_email_removes_google_account_binding(): void
    {
        $user = User::factory()->create(['email' => 'lama@example.test']);
        $user->google_sub = 'old-sub';
        $user->save();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'baru@example.test',
        ])->assertRedirect(route('profile.edit'));

        $this->assertNull($user->fresh()->google_sub);
    }

    public function test_admin_email_change_removes_google_account_binding(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $petugas = User::factory()->create(['email' => 'lama@example.test']);
        $petugas->assignRole('user');
        $petugas->google_sub = 'old-sub';
        $petugas->save();

        $this->actingAs($admin)->patch(route('admin.users.update', $petugas), [
            'name' => $petugas->name,
            'email' => 'baru@example.test',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertNull($petugas->fresh()->google_sub);
    }

    private function configureGoogleLogin(): void
    {
        config()->set('services.google_login.client_id', 'google-client-id');
        config()->set('services.google_login.client_secret', 'google-client-secret');
    }

    /** @return array{state: string, nonce: string, code_verifier: string, started_at: int} */
    private function startGoogleLogin(): array
    {
        $response = $this->get(route('login.google.redirect'));
        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $authorizationParameters);
        $this->assertSame('google-client-id', $authorizationParameters['client_id']);

        return session('google_login_oauth');
    }

    /** @param array<string, mixed> $claimOverrides */
    private function fakeGoogleIdentity(array $oauth, string $email, string $subject, array $claimOverrides = [], bool $tamperSignature = false): void
    {
        Cache::forget('google-login-signing-certificates');
        $keyOptions = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $opensslConfigs = [
            PHP_BINDIR.'/extras/ssl/openssl.cnf',
            ...glob(dirname(base_path(), 2).'/bin/php/*/extras/ssl/openssl.cnf'),
        ];
        foreach ($opensslConfigs as $opensslConfig) {
            if (is_file($opensslConfig)) {
                $keyOptions['config'] = $opensslConfig;
                break;
            }
        }

        $key = openssl_pkey_new($keyOptions);
        $this->assertNotFalse($key);
        $publicKey = openssl_pkey_get_details($key)['key'];
        $claims = array_replace([
            'iss' => 'https://accounts.google.com',
            'aud' => 'google-client-id',
            'sub' => $subject,
            'email' => $email,
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 3600,
            'nonce' => $oauth['nonce'],
        ], $claimOverrides);
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'kid' => 'test-key']));
        $payload = $this->base64Url(json_encode($claims));
        openssl_sign($header.'.'.$payload, $signature, $key, OPENSSL_ALGO_SHA256);
        if ($tamperSignature) {
            $signature[0] = chr(ord($signature[0]) ^ 1);
        }
        $idToken = $header.'.'.$payload.'.'.$this->base64Url($signature);
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['id_token' => $idToken]),
            'https://www.googleapis.com/oauth2/v1/certs' => Http::response(['test-key' => $publicKey]),
        ]);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
