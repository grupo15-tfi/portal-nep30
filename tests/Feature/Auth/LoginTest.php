<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU05 (#8): inicio de sesión, acceso por rol y bloqueo por intentos (RNF12).
 * Los usuarios de la factory tienen la contraseña "password".
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    // Misma siembra que el resto de las pruebas (DatabaseSeeder completo)
    protected $seed = true;

    private function intentar(string $email, string $password)
    {
        return Livewire::test(Login::class)
            ->set('email', $email)
            ->set('password', $password)
            ->call('ingresar');
    }

    public function test_cada_rol_entra_a_su_panel(): void
    {
        $paneles = [
            'administrador' => '/admin/inicio',
            'profesor'      => '/profesor/inicio',
            'alumno'        => '/alumno/inicio',
            'auditor'       => '/auditor/inicio',
        ];

        foreach ($paneles as $rol => $url) {
            $user = User::factory()->conRol($rol)->create();
            $this->intentar($user->email, 'password')->assertRedirect($url);
            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_clave_incorrecta_muestra_mensaje_generico(): void
    {
        $user = User::factory()->conRol('alumno')->create();

        $this->intentar($user->email, 'otra-clave')
            ->assertHasErrors('email')
            ->assertSee('Correo o contraseña incorrectos');

        $this->assertGuest();
    }

    public function test_correo_inexistente_muestra_el_mismo_mensaje(): void
    {
        $this->intentar('nadie@ejemplo.com', 'password')
            ->assertSee('Correo o contraseña incorrectos');

        $this->assertGuest();
    }

    public function test_cinco_fallos_bloquean_quince_minutos(): void
    {
        $user = User::factory()->conRol('alumno')->create();

        for ($i = 0; $i < 5; $i++) {
            $this->intentar($user->email, 'otra-clave');
        }

        $user->refresh();
        $this->assertTrue($user->bloqueado_hasta->isFuture());

        // El 6.º intento con la clave correcta también falla
        $this->intentar($user->email, 'password');
        $this->assertGuest();
    }

    public function test_ingreso_correcto_reinicia_el_contador(): void
    {
        $user = User::factory()->conRol('alumno')->create();

        $this->intentar($user->email, 'otra-clave');
        $this->intentar($user->email, 'password');

        $user->refresh();
        $this->assertSame(0, $user->intentos_fallidos);
        $this->assertNotNull($user->ultimo_acceso);
    }

    public function test_usuario_inactivo_no_entra(): void
    {
        $user = User::factory()->conRol('profesor')->create(['activo' => false]);

        $this->intentar($user->email, 'password');
        $this->assertGuest();
    }

    public function test_usuario_dado_de_baja_no_entra(): void
    {
        $user = User::factory()->conRol('profesor')->create();
        $user->delete(); // baja lógica (deleted_at)

        $this->intentar($user->email, 'password');
        $this->assertGuest();
    }

    public function test_alumno_no_puede_entrar_al_panel_de_admin(): void
    {
        $alumno = User::factory()->conRol('alumno')->create();

        $this->actingAs($alumno)->get('/admin/inicio')->assertForbidden();
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/admin/inicio')->assertRedirect('/login');
    }

    public function test_cerrar_sesion_vuelve_al_login(): void
    {
        $alumno = User::factory()->conRol('alumno')->create();

        $this->actingAs($alumno)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}