<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * HU05 (#30): un usuario dado de baja con la sesión abierta no puede seguir navegando.
 */
class UsuarioActivoTest extends TestCase
{
    use RefreshDatabase;

    // Misma siembra que el resto de las pruebas (DatabaseSeeder completo)
    protected $seed = true;

    private const AVISO = 'Tu cuenta está dada de baja. Consultá con la institución.';

    public function test_usuario_desactivado_con_sesion_abierta_vuelve_al_login(): void
    {
        $profesor = User::factory()->conRol('profesor')->create();
        $this->actingAs($profesor);

        $profesor->update(['activo' => false]);

        $this->get('/profesor/inicio')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_usuario_eliminado_con_sesion_abierta_vuelve_al_login(): void
    {
        $profesor = User::factory()->conRol('profesor')->create();
        $this->actingAs($profesor);

        $profesor->delete(); // baja lógica (deleted_at)

        $this->get('/profesor/inicio')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_el_login_muestra_el_aviso_de_cuenta_dada_de_baja(): void
    {
        $profesor = User::factory()->conRol('profesor')->create();
        $this->actingAs($profesor);

        $profesor->update(['activo' => false]);

        $this->followingRedirects()
            ->get('/profesor/inicio')
            ->assertOk()
            ->assertSee(self::AVISO);
    }

    public function test_usuario_eliminado_con_sesion_real_vuelve_al_login_con_aviso(): void
    {
        $profesor = User::factory()->conRol('profesor')->create();
        $profesor->delete(); // baja lógica (deleted_at)

        // Sin actingAs: como en un pedido real, la sesión guarda solo el id
        // y Laravel no puede cargar al usuario porque SoftDeletes lo excluye.
        $this->withSession([Auth::guard()->getName() => $profesor->id])
            ->get('/profesor/inicio')
            ->assertRedirect('/login');

        $this->assertGuest();

        $this->get('/login')->assertOk()->assertSee(self::AVISO);
    }

    public function test_usuario_activo_entra_normal_a_su_panel(): void
    {
        $profesor = User::factory()->conRol('profesor')->create();

        $this->actingAs($profesor)->get('/profesor/inicio')->assertOk();
        $this->assertAuthenticatedAs($profesor);
    }

    public function test_invitado_en_el_sitio_publico_no_se_ve_afectado(): void
    {
        $this->get('/')->assertOk();
        $this->assertGuest();
    }
}
