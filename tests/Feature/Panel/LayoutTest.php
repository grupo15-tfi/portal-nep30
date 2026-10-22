<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU05 (#8): layout de los paneles con menú por rol, «Mi perfil» y «Salir».
 */
class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $rol): User
    {
        return User::factory()->conRol($rol)->create();
    }

    public function test_cada_rol_ve_su_panel_con_mi_perfil_y_salir(): void
    {
        $paneles = ['administrador' => '/admin/inicio', 'profesor' => '/profesor/inicio',
                    'alumno' => '/alumno/inicio', 'auditor' => '/auditor/inicio'];

        foreach ($paneles as $rol => $url) {
            $this->actingAs($this->usuario($rol))->get($url)
                ->assertOk()
                ->assertSee('<html lang="es">', false)
                ->assertSee(route('perfil'), false)
                ->assertSee(route('logout'), false)
                ->assertSee('Salir');
        }
    }

    public function test_el_administrador_ve_sus_opciones(): void
    {
        $this->actingAs($this->usuario('administrador'))->get('/admin/inicio')
            ->assertSee('Administrador')
            ->assertSee(route('admin.sedes'), false)
            ->assertSee(route('admin.planes'), false)
            ->assertSee(route('admin.cursos'), false);
    }

    public function test_el_alumno_no_ve_opciones_del_administrador(): void
    {
        $this->actingAs($this->usuario('alumno'))->get('/alumno/inicio')
            ->assertSee('Alumno')
            ->assertDontSee(route('admin.sedes'), false)
            ->assertDontSee(route('admin.cursos'), false);
    }

    public function test_la_opcion_actual_queda_marcada(): void
    {
        $this->actingAs($this->usuario('administrador'))->get('/admin/sedes')
            ->assertSee('aria-current="page"', false)
            ->assertSee('<title>Sedes · ', false);
    }

    public function test_las_pantallas_de_administracion_usan_el_layout(): void
    {
        $admin = $this->usuario('administrador');

        foreach (['/admin/sedes', '/admin/cursos', '/admin/planes'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee(route('logout'), false);
        }
    }

    public function test_el_perfil_usa_el_layout(): void
    {
        $this->actingAs($this->usuario('profesor'))->get(route('perfil'))
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee(route('logout'), false);
    }
}