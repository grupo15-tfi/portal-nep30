<?php

namespace Tests\Feature\Admin;

use App\Actions\Profesores\AsignarMateria;
use App\Actions\Profesores\CambiarEstadoProfesor;
use App\Actions\Profesores\GuardarProfesor;
use App\Actions\Profesores\QuitarMateria;
use App\Models\Curso;
use App\Models\Materia;
use App\Models\PlanEstudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * HU08 (#13): acciones de profesores (contrato back ↔ front con #14).
 * Datos de DatosDePruebaSeeder: profesora «profesor@nep30.test» (DNI 20000001) con 3 asignaciones;
 * la de «Módulo 1: Corte y peinado» (Peluquería) ya tiene una clase y una tarea cargadas.
 */
class ProfesoresAccionesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'dni'      => '25123456',
            'apellido' => 'Gómez',
            'nombre'   => 'Laura',
            'email'    => 'laura.gomez@nep30.test',
            'sede_id'  => null,
        ], $cambios);
    }

    /** Ejecuta y devuelve los errores de validación (falla si no hubo error) */
    private function errores(callable $accion): array
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Se esperaba una ValidationException');
    }

    private function profesoraDePrueba(): User
    {
        return User::where('email', 'profesor@nep30.test')->firstOrFail();
    }

    private function cursoPeluqueria(): Curso
    {
        return Curso::where('plan_id', PlanEstudio::where('nombre', 'Peluquería')->value('id'))->firstOrFail();
    }

    public function test_alta_crea_un_profesor_activo_con_contrasena_aleatoria_y_sin_correo(): void
    {
        Notification::fake();

        $profesor = GuardarProfesor::ejecutar($this->datos());

        $this->assertTrue($profesor->hasRole('profesor'));
        $this->assertTrue($profesor->activo);
        $this->assertGreaterThanOrEqual(60, strlen($profesor->getAuthPassword())); // guardada con hash
        Notification::assertNothingSent(); // el correo lo manda el front (#14) con #15
    }

    public function test_dni_o_correo_repetido_dan_error_en_espanol(): void
    {
        $errores = $this->errores(fn () => GuardarProfesor::ejecutar($this->datos(['dni' => '20000001'])));
        $this->assertSame('Ya existe un usuario con ese DNI', $errores['dni'][0]);

        $errores = $this->errores(fn () => GuardarProfesor::ejecutar($this->datos(['email' => 'profesor@nep30.test'])));
        $this->assertSame('Ya existe un usuario con ese correo', $errores['email'][0]);
    }

    public function test_dni_con_formato_invalido_y_campos_obligatorios(): void
    {
        $this->assertArrayHasKey('dni', $this->errores(fn () => GuardarProfesor::ejecutar($this->datos(['dni' => '123456']))));
        $this->assertArrayHasKey('dni', $this->errores(fn () => GuardarProfesor::ejecutar($this->datos(['dni' => '12.345.678']))));

        $errores = $this->errores(fn () => GuardarProfesor::ejecutar($this->datos(['apellido' => '', 'nombre' => '', 'email' => 'no-es-correo'])));
        $this->assertArrayHasKey('apellido', $errores);
        $this->assertArrayHasKey('nombre', $errores);
        $this->assertArrayHasKey('email', $errores);
    }

    public function test_editar_conserva_su_dni_y_correo_sin_error(): void
    {
        $profesora = $this->profesoraDePrueba();

        GuardarProfesor::ejecutar($this->datos([
            'dni' => $profesora->dni, 'email' => $profesora->email, 'apellido' => 'Apellido Editado',
        ]), $profesora);

        $this->assertSame('Apellido Editado', $profesora->fresh()->apellido);
        $this->assertTrue($profesora->fresh()->hasRole('profesor'));
    }

    public function test_baja_y_alta_logica_sin_borrar(): void
    {
        $profesora = $this->profesoraDePrueba();

        CambiarEstadoProfesor::ejecutar($profesora, false);
        $this->assertFalse($profesora->fresh()->activo);
        $this->assertNull($profesora->fresh()->deleted_at); // no se borró

        CambiarEstadoProfesor::ejecutar($profesora, true);
        $this->assertTrue($profesora->fresh()->activo);
    }

    public function test_asignar_materia_del_plan_y_errores(): void
    {
        $profesor = GuardarProfesor::ejecutar($this->datos());
        $curso = $this->cursoPeluqueria();
        $materiaDelPlan = $curso->materiasDelPlan()->firstOrFail();
        $materiaDeOtroPlan = Materia::where('plan_id', '<>', $curso->plan_id)->firstOrFail();

        AsignarMateria::ejecutar($profesor, $curso->id, $materiaDelPlan->id);
        $this->assertCount(1, $profesor->fresh()->asignaciones);

        // Repetida
        $errores = $this->errores(fn () => AsignarMateria::ejecutar($profesor, $curso->id, $materiaDelPlan->id));
        $this->assertArrayHasKey('materia_id', $errores);

        // De otro plan
        $errores = $this->errores(fn () => AsignarMateria::ejecutar($profesor, $curso->id, $materiaDeOtroPlan->id));
        $this->assertSame('La materia no pertenece al plan del curso.', $errores['materia_id'][0]);
    }

    public function test_no_se_asigna_a_quien_no_es_profesor(): void
    {
        $alumno = User::factory()->conRol('alumno')->create();
        $curso = $this->cursoPeluqueria();

        $errores = $this->errores(fn () => AsignarMateria::ejecutar($alumno, $curso->id, $curso->materiasDelPlan()->value('id')));
        $this->assertArrayHasKey('materia_id', $errores);
    }

    public function test_quitar_materia_y_no_quitar_si_tiene_clases_o_tareas(): void
    {
        $profesora = $this->profesoraDePrueba();
        $curso = $this->cursoPeluqueria();
        $corte = Materia::where('nombre', 'Módulo 1: Corte y peinado')->firstOrFail();      // con clase y tarea
        $color = Materia::where('nombre', 'Módulo 2: Coloración')->firstOrFail();           // sin nada cargado

        QuitarMateria::ejecutar($profesora, $curso->id, $color->id);
        $this->assertDatabaseMissing('curso_profesores', ['profesor_id' => $profesora->id, 'materia_id' => $color->id]);

        $errores = $this->errores(fn () => QuitarMateria::ejecutar($profesora, $curso->id, $corte->id));
        $this->assertArrayHasKey('materia_id', $errores);
        $this->assertDatabaseHas('curso_profesores', ['profesor_id' => $profesora->id, 'materia_id' => $corte->id]);
    }

    public function test_consultas_del_contrato(): void
    {
        $profesores = User::profesores()->pluck('email');
        $this->assertContains('profesor@nep30.test', $profesores);
        $this->assertNotContains('alumno@nep30.test', $profesores);

        $asignaciones = $this->profesoraDePrueba()->asignaciones;
        $this->assertCount(3, $asignaciones);
        $this->assertTrue($asignaciones->first()->relationLoaded('materia'));
        $this->assertNotNull($asignaciones->first()->curso->sede->nombre);

        $curso = $this->cursoPeluqueria();
        $this->assertSame(
            ['Módulo 1: Corte y peinado', 'Módulo 2: Coloración'],
            $curso->materiasDelPlan->pluck('nombre')->all()
        );
    }
}