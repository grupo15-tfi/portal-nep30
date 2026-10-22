<?php

namespace Tests\Feature\Perfil;

use App\Livewire\Perfil\CambiarContrasena;
use App\Livewire\Perfil\Datos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU06 (#16): pantalla de perfil y cambio de contraseña.
 */
class PerfilTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $rol = 'alumno'): User
    {
        return User::factory()->conRol($rol)->create(['password' => 'Clave1234']);
    }

    public function test_los_cuatro_roles_abren_su_perfil_y_el_invitado_va_al_login(): void
    {
        foreach (['administrador', 'profesor', 'alumno', 'auditor'] as $rol) {
            $this->actingAs($this->usuario($rol))->get(route('perfil'))->assertOk()->assertSee('Mi perfil');
        }
        auth()->logout();
        $this->get(route('perfil'))->assertRedirect(route('login'));
    }

    public function test_dni_y_correo_se_ven_pero_no_se_editan(): void
    {
        $usuario = $this->usuario();

        Livewire::actingAs($usuario)->test(Datos::class)
            ->assertSee($usuario->dni)
            ->assertSee($usuario->email)
            ->assertSet('nombre', $usuario->nombre);

        // El componente no tiene propiedades para DNI ni correo: no hay forma de editarlos

        $this->assertFalse(property_exists(Datos::class, 'email'));
        $this->assertFalse(property_exists(Datos::class, 'dni'));
    }

    public function test_guardar_nombre_apellido_y_telefonos_se_ve_de_inmediato(): void
    {
        $usuario = $this->usuario();

        Livewire::actingAs($usuario)->test(Datos::class)
            ->set('nombre', 'Lucía')
            ->set('apellido', 'Gómez')
            ->call('agregarTelefono')
            ->set('telefonos.0.numero', '3704 123456')
            ->set('telefonos.0.tipo', 'whatsapp')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Tus datos se guardaron.');

        $usuario->refresh();
        $this->assertSame('Lucía', $usuario->nombre);
        $this->assertSame('Gómez', $usuario->apellido);
        $this->assertSame('3704 123456', $usuario->telefonos->first()->numero);
        $this->assertTrue($usuario->telefonos->first()->es_principal);

        $this->actingAs($usuario)->get(route('perfil'))->assertSee('3704 123456');
    }

    public function test_telefono_con_formato_invalido_da_error_en_el_campo(): void
    {
        Livewire::actingAs($this->usuario())->test(Datos::class)
            ->call('agregarTelefono')
            ->set('telefonos.0.numero', 'llamame')
            ->call('guardar')
            ->assertHasErrors(['telefonos.0.numero' => 'regex']);
    }

    public function test_telefono_de_emergencia_sin_contacto_da_error(): void
    {
        Livewire::actingAs($this->usuario())->test(Datos::class)
            ->call('agregarTelefono')
            ->set('telefonos.0.numero', '3704 555555')
            ->set('telefonos.0.tipo', 'emergencia')
            ->call('guardar')
            ->assertHasErrors(['telefonos.0.contacto_nombre' => 'required_if']);
    }

    public function test_cambiar_la_contrasena_exige_la_actual(): void
    {
        $usuario = $this->usuario();

        Livewire::actingAs($usuario)->test(CambiarContrasena::class)
            ->set('actual', 'otra-cosa')
            ->set('nueva', 'NuevaClave99')
            ->set('nueva_confirmation', 'NuevaClave99')
            ->call('guardar')
            ->assertHasErrors(['actual' => 'current_password']);

        $this->assertTrue(Hash::check('Clave1234', $usuario->fresh()->password));
    }

    public function test_la_nueva_contrasena_debe_coincidir_y_cumplir_las_reglas(): void
    {
        $componente = Livewire::actingAs($this->usuario())->test(CambiarContrasena::class)->set('actual', 'Clave1234');

        $componente->set('nueva', 'NuevaClave99')->set('nueva_confirmation', 'distinta')->call('guardar')
            ->assertHasErrors(['nueva' => 'confirmed']);

        $componente->set('nueva', 'corta')->set('nueva_confirmation', 'corta')->call('guardar')
            ->assertHasErrors('nueva');
    }

    public function test_cambiar_la_contrasena_correctamente(): void
    {
        $usuario = $this->usuario();

        Livewire::actingAs($usuario)->test(CambiarContrasena::class)
            ->set('actual', 'Clave1234')
            ->set('nueva', 'NuevaClave99')
            ->set('nueva_confirmation', 'NuevaClave99')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('actual', '');

        $this->assertTrue(Hash::check('NuevaClave99', $usuario->fresh()->password));
    }
}
