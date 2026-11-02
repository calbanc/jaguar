<?php

namespace Tests\Feature;

use App\Mail\VerifyHseEmail;
use App\Services\EmailVerification;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\HseDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use HseDatabase;

    private array $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installHseFixture();
        $this->tenant = $this->tenant('alpha');
        Mail::fake();
    }

    private function credentials(): array
    {
        return ['email' => 'alpha@example.test', 'password' => 'password-test-123', 'empresa_codigo' => 'alpha', 'plataforma' => 'web'];
    }

    private function pendingUser(): object
    {
        DB::table('usuarios')->where('id', $this->tenant['user'])->update(['email_verified_at' => null]);

        return DB::table('usuarios')->where('id', $this->tenant['user'])->first();
    }

    public function test_pending_accounts_cannot_login_or_use_existing_sessions(): void
    {
        $this->loginAsTenant($this->tenant);
        $this->pendingUser();
        $this->postJson('/api/v1/auth/login', $this->credentials())->assertForbidden()->assertJsonPath('code', 'EMAIL_NOT_VERIFIED')->assertJsonMissingPath('token');
        $this->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
        $this->assertSame(1, DB::table('sesiones')->count());
        $this->postJson('/api/v1/auth/login', array_replace($this->credentials(), ['password' => 'incorrecta']))->assertUnauthorized()->assertJsonMissingPath('code');
    }

    public function test_registration_sends_minimal_verification_mail_without_credentials(): void
    {
        $this->loginAsTenant($this->tenant);
        $this->postJson('/api/v1/administracion/usuarios', ['nombre' => 'Ana <Torres>', 'email' => 'ana@example.test',
            'password' => 'password-user-123', 'rol_id' => $this->tenant['role'], 'plataforma_id' => 1])
            ->assertCreated()->assertJsonPath('verification_email_sent', true);
        $this->assertDatabaseHas('usuarios', ['email' => 'ana@example.test', 'email_verified_at' => null]);
        Mail::assertSent(VerifyHseEmail::class, function ($mail) {
            $html = $mail->render();
            $this->assertStringContainsString('Verificar mi correo', $html);
            $this->assertStringContainsString('Ana &lt;Torres&gt;', $html);
            $this->assertStringNotContainsString('password-user-123', $html);

            return $mail->hasTo('ana@example.test') && str_contains($mail->verificationUrl, 'signature=');
        });
    }

    public function test_confirmation_requires_valid_unexpired_link_and_does_not_automatically_sign_in(): void
    {
        $url = app(EmailVerification::class)->url($this->pendingUser());
        $this->get($url)->assertOk()->assertSee('Confirma tu correo.');
        $this->assertDatabaseHas('usuarios', ['id' => $this->tenant['user'], 'email_verified_at' => null]);
        $this->post($url.'x')->assertForbidden();
        $this->post($url)->assertOk()->assertSee('Correo verificado.');
        $this->assertNotNull(DB::table('usuarios')->where('id', $this->tenant['user'])->value('email_verified_at'));
        $this->assertSame(0, DB::table('sesiones')->count());
        $this->post($url)->assertOk();
        $this->postJson('/api/v1/auth/login', $this->credentials())->assertOk();
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden()->assertSee('El enlace no es válido.');
    }

    public function test_resend_requires_credentials_and_tenant_access_and_limits_messages(): void
    {
        $this->pendingUser();
        $this->postJson('/api/v1/auth/email/resend', array_replace($this->credentials(), ['password' => 'incorrecta']))->assertUnauthorized();
        $this->postJson('/api/v1/auth/email/resend', array_replace($this->credentials(), ['empresa_codigo' => 'other']))->assertUnauthorized();
        Mail::assertNothingSent();
        $this->postJson('/api/v1/auth/email/resend', $this->credentials())->assertOk();
        $this->postJson('/api/v1/auth/email/resend', $this->credentials())->assertStatus(429);
        Mail::assertSentCount(1);
        $this->assertSame(0, DB::table('sesiones')->count());
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/email/resend', $this->credentials())->assertOk();
        Mail::assertSentCount(2);
    }

    public function test_changing_email_resets_verification_revokes_sessions_and_invalidates_old_links(): void
    {
        $this->loginAsTenant($this->tenant);
        $user = DB::table('usuarios')->where('id', $this->tenant['user'])->first();
        $url = app(EmailVerification::class)->url($user);
        $this->patchJson('/api/v1/administracion/usuarios/'.$this->tenant['member'], ['email' => 'new@example.test'])
            ->assertOk()->assertJsonPath('verification_email_sent', true);
        $this->assertDatabaseHas('usuarios', ['id' => $user->id, 'email' => 'new@example.test', 'email_verified_at' => null]);
        $this->assertSame(0, DB::table('sesiones')->whereNull('revocado_at')->count());
        $this->post($url)->assertForbidden();
        Mail::assertSent(VerifyHseEmail::class, fn ($mail) => $mail->hasTo('new@example.test'));
    }

    public function test_failed_delivery_preserves_created_account_and_can_be_retried(): void
    {
        $this->loginAsTenant($this->tenant);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->postJson('/api/v1/administracion/usuarios', ['nombre' => 'Ana', 'email' => 'ana@example.test', 'password' => 'password-user-123',
            'rol_id' => $this->tenant['role'], 'plataforma_id' => 1])->assertCreated()->assertJsonPath('verification_email_sent', false);
        $this->assertDatabaseHas('usuarios', ['email' => 'ana@example.test', 'email_verified_at' => null]);
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->postJson('/api/v1/auth/email/resend', array_replace($this->credentials(), ['email' => 'ana@example.test', 'password' => 'password-user-123']))->assertOk();
        Mail::assertSentCount(1);
    }

    public function test_inactive_accounts_and_already_verified_accounts_do_not_receive_messages(): void
    {
        $this->postJson('/api/v1/auth/email/resend', $this->credentials())->assertOk()->assertJsonPath('message', 'Tu correo ya está verificado. Puedes iniciar sesión.');
        $url = app(EmailVerification::class)->url($this->pendingUser());
        DB::table('usuarios')->where('id', $this->tenant['user'])->update(['activo' => false]);
        $this->postJson('/api/v1/auth/email/resend', $this->credentials())->assertUnauthorized();
        $this->post($url)->assertForbidden();
        Mail::assertNothingSent();
    }
}
