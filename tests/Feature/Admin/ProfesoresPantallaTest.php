<?php

namespace Tests\Feature\Admin;

use App\Actions\Profesores\GuardarProfesor;
use App\Livewire\Admin\Profesores\Asignaciones;
use App\Livewire\Admin\Profesores\Formulario;
use App\Livewire\Admin\Profesores\Index;
use App\Models\Curso;
use App\Models\Materia;
use App\Models\PlanEstudio;
use App\Models\User;
use App\Notifications\CrearContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU08 (#14): pantallas de gestión de profesores.
 * Datos de DatosDePruebaSeeder: profesora «profesor@nep30.test» (DNI 20000001, apellido Prueba)
 * con 3 asignaciones; la de «Módulo 1: Corte y peinado» ya tiene una clase y una tarea.
 */
class ProfesoresPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    private function profesoraDePrueba(): User
    {
        return User::where('email', 'profesor@nep30.test')->firstOrFail();
    }

    private function cursoPeluqueria(): Curso
    {
        return Curso::where('plan_id', PlanEstudio::where('nombre', 'Peluquería')->value('id'))->firstOrFail();
    }

    public function test_solo_el_administrador_entra_y_el_menu_muestra_profesores(): void
    {
        $this->actingAs($this->admin())->get('/admin/profesores')->assertOk()->assertSee('Profesores');
        $this->actingAs($this->admin())->get('/admin/inicio')->assertSee(route('admin.profesores.index'), false);

        foreach (['profesor', 'alumno', 'auditor'] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())->get('/admin/profesores')->assertForbidden();
        }
    }

    public function test_busqueda_por_dni_apellido_o_correo(): void
    {
        GuardarProfesor::ejecutar(['dni' => '30111222', 'apellido' => 'Zárate', 'nombre' => 'Inés', 'email' => 'ines.zarate@nep30.test', 'sede_id' => null]);

        Livewire::actingAs($this->admin())->test(Index::class)
            ->set('buscar', 'Zárate')->assertSee('30111222')->assertDontSee('20000001')
            ->set('buscar', '20000001')->assertSee('profesor@nep30.test')->assertDontSee('30111222')
            ->set('buscar', 'ines.zarate')->assertSee('Zárate');
    }

    // ---------- #31: buscar por nombre y por nombre completo ----------

    /**
     * Juana Pérez (DNI 31222333) y otro profesor que no debe aparecer (DNI 32444555).
     * Los correos no incluyen el nombre: así la búsqueda no coincide por el email.
     */
    private function buscarJuana(string $texto)
    {
        GuardarProfesor::ejecutar(['dni' => '31222333', 'apellido' => 'Pérez', 'nombre' => 'Juana', 'email' => 'docente.a@nep30.test', 'sede_id' => null]);
        GuardarProfesor::ejecutar(['dni' => '32444555', 'apellido' => 'Gómez', 'nombre' => 'Carlos', 'email' => 'docente.b@nep30.test', 'sede_id' => null]);

        return Livewire::actingAs($this->admin())->test(Index::class)->set('buscar', $texto);
    }

    public function test_busqueda_por_nombre(): void
    {
        $this->buscarJuana('Juana')->assertSee('31222333')->assertDontSee('32444555');
    }

    public function test_busqueda_por_nombre_y_apellido(): void
    {
        $this->buscarJuana('Juana Pérez')->assertSee('31222333')->assertDontSee('32444555');
    }

    public function test_busqueda_por_apellido_y_nombre(): void
    {
        $this->buscarJuana('Pérez Juana')->assertSee('31222333')->assertDontSee('32444555');
    }

    public function test_busqueda_sin_distinguir_mayusculas(): void
    {
        $this->buscarJuana('juana pérez')->assertSee('31222333')->assertDontSee('32444555');
    }

    public function test_listado_paginado_de_15(): void
    {
        User::factory()->count(16)->conRol('profesor')->create(); // + la profesora de prueba = 17

        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        $this->assertCount(15, $componente->viewData('profesores')->items());
        $componente->call('nextPage');
        $this->assertCount(2, $componente->viewData('profesores')->items());
    }

    public function test_alta_desde_el_modal_envia_el_correo_crear_contrasena(): void
    {
        Notification::fake();

        Livewire::actingAs($this->admin())->test(Formulario::class)
            ->call('abrir')
            ->set('dni', '25123456')->set('apellido', 'Gómez')->set('nombre', 'Laura')
            ->set('email', 'laura.gomez@nep30.test')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('abierto', false)
            ->assertDispatched('profesor-guardado');

        $nuevo = User::where('email', 'laura.gomez@nep30.test')->firstOrFail();
        $this->assertTrue($nuevo->hasRole('profesor'));
        Notification::assertSentTo($nuevo, CrearContrasena::class);
    }

    public function test_errores_debajo_de_cada_campo(): void
    {
        Livewire::actingAs($this->admin())->test(Formulario::class)
            ->call('abrir')
            ->set('dni', '20000001')->set('apellido', '')->set('nombre', 'Laura')->set('email', 'profesor@nep30.test')
            ->call('guardar')
            ->assertHasErrors(['dni', 'apellido', 'email'])
            ->assertSee('Ya existe un usuario con ese DNI')
            ->assertSee('Ya existe un usuario con ese correo')
            ->assertSet('abierto', true); // el modal sigue abierto
    }

    public function test_editar_no_envia_correo(): void
    {
        Notification::fake();
        $profesora = $this->profesoraDePrueba();

        Livewire::actingAs($this->admin())->test(Formulario::class)
            ->call('abrir', $profesora->id)
            ->assertSet('dni', $profesora->dni)
            ->set('apellido', 'Apellido Editado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Apellido Editado', $profesora->fresh()->apellido);
        Notification::assertNothingSent();
    }

    public function test_baja_y_alta_con_el_boton(): void
    {
        $profesora = $this->profesoraDePrueba();
        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        $componente->call('cambiarEstado', $profesora->id)->assertSee('quedó dado de baja');
        $this->assertFalse($profesora->fresh()->activo);

        $componente->call('cambiarEstado', $profesora->id)->assertSee('quedó activo');
        $this->assertTrue($profesora->fresh()->activo);
    }

    public function test_asignaciones_muestra_solo_materias_del_plan_y_asigna(): void
    {
        $profesor = GuardarProfesor::ejecutar(['dni' => '30111222', 'apellido' => 'Zárate', 'nombre' => 'Inés', 'email' => 'ines.zarate@nep30.test', 'sede_id' => null]);
        $curso = $this->cursoPeluqueria();
        $color = Materia::where('nombre', 'Módulo 2: Coloración')->firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.profesores.asignaciones', $profesor))->assertOk();

        Livewire::actingAs($this->admin())->test(Asignaciones::class, ['profesor' => $profesor])
            ->set('curso_id', (string) $curso->id)
            ->assertSee('Módulo 2: Coloración')
            ->assertDontSee('Módulo 1: Ofimática')        // es de otro plan
            ->set('materia_id', (string) $color->id)
            ->call('asignar')
            ->assertHasNoErrors()
            ->assertSee('Materia asignada.');

        $this->assertDatabaseHas('curso_profesores', ['profesor_id' => $profesor->id, 'materia_id' => $color->id]);
    }

    public function test_errores_de_asignacion_se_muestran(): void
    {
        $profesora = $this->profesoraDePrueba();
        $curso = $this->cursoPeluqueria();
        $ofimatica = Materia::where('nombre', 'Módulo 1: Ofimática')->firstOrFail();
        $corte = Materia::where('nombre', 'Módulo 1: Corte y peinado')->firstOrFail();

        // Materia de otro plan (no debería pasar por el combo, pero la acción lo frena igual)
        Livewire::actingAs($this->admin())->test(Asignaciones::class, ['profesor' => $profesora])
            ->set('curso_id', (string) $curso->id)
            ->set('materia_id', (string) $ofimatica->id)
            ->call('asignar')
            ->assertHasErrors('materia_id');

        // Quitar una asignación que ya tiene clases y tareas
        Livewire::actingAs($this->admin())->test(Asignaciones::class, ['profesor' => $profesora])
            ->call('quitar', $curso->id, $corte->id)
            ->assertHasErrors('materia_id')
            ->assertSee('No se puede quitar');
    }

    public function test_en_el_celular_las_tablas_se_ven_como_tarjetas_con_la_etiqueta_de_cada_dato(): void
    {
        // Listado: cada dato lleva el nombre de su columna, así en 360 px se lee como tarjeta y las acciones no quedan fuera de la vista
        $this->actingAs($this->admin())->get('/admin/profesores')
            ->assertOk()
            ->assertSee('class="tabla-apilada"', false)
            ->assertSee('data-etiqueta="DNI"', false)
            ->assertSee('data-etiqueta="Acciones"', false);

        // Materias asignadas
        $this->actingAs($this->admin())->get(route('admin.profesores.asignaciones', $this->profesoraDePrueba()))
            ->assertOk()
            ->assertSee('class="tabla-apilada"', false)
            ->assertSee('data-etiqueta="Curso"', false)
            ->assertSee('data-etiqueta="Acción"', false);
    }

    public function test_el_enlace_materias_se_ve_como_boton_igual_que_editar_y_dar_de_baja(): void
    {
        // Sugerencia de José: las tres acciones de la fila con el mismo aspecto (borde, relleno y texto primario)
        $html = $this->actingAs($this->admin())->get('/admin/profesores')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/asignaciones"[^>]*class="[^"]*rounded border border-primario px-3 py-1\.5[^"]*"[^>]*>Materias<\/a>/',
            $html
        );
    }

    public function test_asignaciones_de_un_usuario_que_no_es_profesor_da_404(): void
    {
        $alumno = User::factory()->conRol('alumno')->create();

        $this->actingAs($this->admin())->get(route('admin.profesores.asignaciones', $alumno))->assertNotFound();
    }
}
