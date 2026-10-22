<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Sedes\Index;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU07 (#9): ABM de sedes con coordenadas (RF14).
 */
class SedesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    private function formulario(array $cambios = [])
    {
        $datos = array_merge([
            'nombre'    => 'Anexo Barrio Prueba',
            'tipo'      => 'anexo',
            'localidad' => 'Formosa',
            'latitud'   => '-26.1849000',
            'longitud'  => '-58.1731000',
        ], $cambios);

        $componente = Livewire::actingAs($this->admin())->test(Index::class);
        foreach ($datos as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        return $componente->call('guardar');
    }

    public function test_el_administrador_ve_la_pantalla_de_sedes(): void
    {
        $this->actingAs($this->admin())->get('/admin/sedes')->assertOk();
    }

    public function test_profesor_y_alumno_reciben_403(): void
    {
        foreach (['profesor', 'alumno'] as $rol) {
            $user = User::factory()->conRol($rol)->create();
            $this->actingAs($user)->get('/admin/sedes')->assertForbidden();
        }
    }

    public function test_alta_de_sede_valida(): void
    {
        $this->formulario()->assertHasNoErrors();

        $this->assertDatabaseHas('sedes', ['nombre' => 'Anexo Barrio Prueba', 'tipo' => 'anexo', 'activo' => true]);
    }

    public function test_nombre_repetido_da_error(): void
    {
        Sede::create(['nombre' => 'Anexo Barrio Prueba', 'tipo' => 'anexo', 'localidad' => 'Formosa',
                      'latitud' => -26.18, 'longitud' => -58.17]);

        $this->formulario()->assertHasErrors(['nombre' => 'unique']);
    }

    public function test_coordenadas_obligatorias_y_en_rango(): void
    {
        $this->formulario(['latitud' => ''])->assertHasErrors('latitud');
        $this->formulario(['latitud' => '91'])->assertHasErrors('latitud');
        $this->formulario(['longitud' => '-181'])->assertHasErrors('longitud');
        $this->formulario(['longitud' => 'abc'])->assertHasErrors('longitud');
    }

    public function test_tipo_invalido_da_error(): void
    {
        $this->formulario(['tipo' => 'oficina'])->assertHasErrors('tipo');
    }

    public function test_editar_sin_cambiar_el_nombre_no_da_error_de_repetido(): void
    {
        $sede = Sede::create(['nombre' => 'Sede Editar', 'tipo' => 'sede', 'localidad' => 'Formosa',
                              'latitud' => -26.18, 'longitud' => -58.17]);

        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('editar', $sede->id)
            ->set('localidad', 'Clorinda')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Clorinda', $sede->fresh()->localidad);
    }

    public function test_desactivar_sede_la_saca_de_la_oferta_publica(): void
    {
        $sede = Sede::where('nombre', 'Sede Central (prueba)')->firstOrFail(); // del DatosDePruebaSeeder
        $antes = DB::table('v_oferta_publica')->where('sede_id', $sede->id)->count();
        $this->assertGreaterThan(0, $antes, 'La sede de prueba debería tener cursos en la oferta');

        Livewire::actingAs($this->admin())->test(Index::class)->call('cambiarEstado', $sede->id);

        $this->assertFalse($sede->fresh()->activo);
        $this->assertSame(0, DB::table('v_oferta_publica')->where('sede_id', $sede->id)->count());
        $this->assertDatabaseHas('sedes', ['id' => $sede->id]); // no se borró
    }
}
