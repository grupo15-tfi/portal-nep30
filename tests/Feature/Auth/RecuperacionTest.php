<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\OlvideContrasena;
use App\Livewire\Auth\RestablecerContrasena;
use App\Models\User;
use App\Notifications\CrearContrasena;
use App\Notifications\RestablecerContrasena as NotificacionRestablecer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU06 (#15): recuperación de contraseña, correo de alta y reglas de contraseña (RF12).
 * Ninguna prueba manda correos de verdad: Notification::fake().
 */
class RecuperacionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(array $datos = []): User
    {
        return User::factory()->conRol('alumno')->create(array_merge(['password' => 'Clave1234'], $datos));
    }

    private function restablecer(User $usuario, string $token, string $nueva = 'NuevaClave99')
    {
        return Livewire::test(RestablecerContrasena::class, ['token' => $token])
            ->set('email', $usuario->email)
            ->set('password', $nueva)
            ->set('password_confirmation', $nueva)
            ->call('guardar');
    }

    public function test_las_pantallas_abren_sin_sesion(): void
    {
        $this->get('/olvide-mi-contrasena')->assertOk();
        $this->get(route('password.reset', ['token' => 'abc', 'email' => 'alguien@ejemplo.com']))->assertOk();
    }

    public function test_un_correo_registrado_recibe_el_enlace(): void
    {
        Notification::fake();
        $usuario = $this->usuario();

        Livewire::test(OlvideContrasena::class)->set('email', $usuario->email)->call('enviar')
            ->assertSee(OlvideContrasena::MENSAJE);

        Notification::assertSentTo($usuario, NotificacionRestablecer::class);
    }

    public function test_un_correo_no_registrado_ve_el_mismo_mensaje_y_no_se_envia_nada(): void
    {
        Notification::fake();

        Livewire::test(OlvideContrasena::class)->set('email', 'nadie@ejemplo.com')->call('enviar')
            ->assertHasNoErrors()
            ->assertSee(OlvideContrasena::MENSAJE);

        Notification::assertNothingSent();
    }

    public function test_un_usuario_inactivo_no_recibe_enlace(): void
    {
        Notification::fake();
        $usuario = $this->usuario(['activo' => false]);

        Livewire::test(OlvideContrasena::class)->set('email', $usuario->email)->call('enviar')
            ->assertSee(OlvideContrasena::MENSAJE);

        Notification::assertNothingSent();
    }

    public function test_con_el_enlace_valido_se_cambia_la_contrasena(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $this->restablecer($usuario, $token)->assertHasNoErrors()->assertSee('Tu contraseña se cambió');

        $this->assertTrue(Hash::check('NuevaClave99', $usuario->fresh()->password));
    }

    public function test_el_enlace_vence_a_los_60_minutos(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $this->travel(61)->minutes();

        $this->restablecer($usuario, $token)->assertHasErrors('email');
        $this->assertTrue(Hash::check('Clave1234', $usuario->fresh()->password)); // no cambió
    }

    public function test_el_enlace_sirve_una_sola_vez(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $this->restablecer($usuario, $token, 'PrimeraClave1')->assertHasNoErrors();
        $this->restablecer($usuario, $token, 'SegundaClave2')->assertHasErrors('email');

        $this->assertTrue(Hash::check('PrimeraClave1', $usuario->fresh()->password));
    }

    public function test_la_nueva_contrasena_cumple_las_reglas(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        $this->restablecer($usuario, $token, 'corta1')->assertHasErrors('password');        // menos de 8
        $this->restablecer($usuario, $token, 'sololetras')->assertHasErrors('password');    // sin números
        $this->restablecer($usuario, $token, '12345678')->assertHasErrors('password');      // sin letras
    }

    public function test_enviar_enlace_crear_contrasena_para_altas_de_hu08(): void
    {
        Notification::fake();
        $usuario = $this->usuario();

        $usuario->enviarEnlaceCrearContrasena();

        Notification::assertSentTo($usuario, CrearContrasena::class);
    }

    public function test_los_correos_van_por_la_cola_y_en_espanol(): void
    {
        $usuario = $this->usuario();

        $this->assertContains(ShouldQueue::class, class_implements(NotificacionRestablecer::class));
        $this->assertContains(ShouldQueue::class, class_implements(CrearContrasena::class));

        $this->assertStringContainsString('Restablecé tu contraseña', (new NotificacionRestablecer('t'))->toMail($usuario)->subject);
        $this->assertStringContainsString('Creá tu contraseña', (new CrearContrasena('t'))->toMail($usuario)->subject);
    }
}