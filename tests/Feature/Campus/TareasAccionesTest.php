<?php

namespace Tests\Feature\Campus;

use App\Actions\Tareas\DevolverEntrega;
use App\Actions\Tareas\EntregarTarea;
use App\Actions\Tareas\GuardarTarea;
use App\Models\CursoProfesor;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
* HU12 (#118): publicar tareas, entregar y devolver.
* Datos de DatosDePruebaSeeder:
*- Profesora (profesor@nep30.test) con «Módulo 1: Corte y peinado» en Peluquería.
*- Alumna Uno (alumno@nep30.test) y Alumno Dos (alumno2@nep30.test) inscriptos en Peluquería.
*- La tarea «Trabajo práctico 1: tipos de corte», con fecha límite 01/12/2026 23:59.
*/

class TareasAccionesTest extends TestCase
{
    use RefreshDatabase;
    
    protected $seed = true;
    private User $profesora;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->profesora = User::where('email', 'profesor@nep30.test')->firstOrFail();
        $this->actingAs($this->profesora);
    }
    
    private function corte(): CursoProfesor
    {
        return CursoProfesor::where('profesor_id', $this->profesora->id)
        ->where('materia_id', Materia::where('nombre', 'Módulo 1: Corte y peinado')->value('id'))
        ->firstOrFail();
    }

    private function alumno(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function inscripcion(string $email): Inscripcion
    {
        return Inscripcion::where('alumno_id', $this->alumno($email)->id)->firstOrFail();
    }

    /** Una tarea con la fecha límite que se pida (sin pasar por la acción, para poder ponerla en el pasado) */

    private function tarea(string $limite = '+7 days'): Tarea
    {
        return Tarea::create([
            'curso_profesor_id' => $this->corte()->id,
            'titulo'            => 'Tarea de prueba',
            'fecha_entrega'     => now()->modify($limite),
        ]);
    }

    private function datos(array $cambios = []): array
    {
        return [
            'curso_profesor_id' => $this->corte()->id,
            'titulo'            => 'Trabajo práctico 2: color',
            'consigna'          => 'Armar una carta de colores.',
            'fecha_entrega'     => now()->addWeek()->format('Y-m-d\TH:i'),
            'archivo'           => null,
            ...$cambios,
        ];
    }

    private function errorEn(string $clave, callable $accion): void
    {
        try {
            $accion();
            $this->fail("Se esperaba un error en «{$clave}».");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($clave, $e->errors());
        }
    }

    // ── GuardarTarea ───────────────────────────────────────

    public function test_la_profesora_publica_una_tarea_con_archivo_de_consigna(): void
    {
        $tarea = GuardarTarea::ejecutar($this->datos(['archivo' => UploadedFile::fake()->create('consigna.pdf',
        200, 'application/pdf')]));
        $this->assertSame('Trabajo práctico 2: color', $tarea->titulo);
        $this->assertSame("tareas/{$tarea->id}/consigna.pdf", $tarea->archivo_ruta);
        Storage::disk('local')->assertExists($tarea->archivo_ruta);
    }

    public function test_al_editar_se_reemplaza_el_archivo_y_se_puede_dejar_una_fecha_pasada(): void
    {
        $tarea = GuardarTarea::ejecutar($this->datos(['archivo' => UploadedFile::fake()->create('consigna.pdf',
        200, 'application/pdf')]));
        $viejo = $tarea->archivo_ruta;
        $tarea = GuardarTarea::ejecutar($this->datos([
            'titulo'        => 'Trabajo práctico 2: color (corregido)',
            'fecha_entrega' => now()->subDay()->format('Y-m-d H:i'),
            'archivo'       => UploadedFile::fake()->image('consigna.png'),
        ]), $tarea);

        $this->assertSame('Trabajo práctico 2: color (corregido)', $tarea->fresh()->titulo);
        Storage::disk('local')->assertMissing($viejo);
        Storage::disk('local')->assertExists("tareas/{$tarea->id}/consigna.png");
    }

    public function test_no_se_publica_sin_titulo_con_fecha_pasada_ni_con_archivo_no_permitido(): void
    {
        $this->errorEn('titulo', fn () => GuardarTarea::ejecutar($this->datos(['titulo' => ''])));
        $this->errorEn('titulo', fn () => GuardarTarea::ejecutar($this->datos(['titulo' => str_repeat('a',
        151)])));
        $this->errorEn('fecha_entrega', fn () => GuardarTarea::ejecutar($this->datos(['fecha_entrega' => now()->subDay()->format('Y-m-d H:i')])));
        $this->errorEn('archivo', fn () => GuardarTarea::ejecutar($this->datos(['archivo' => UploadedFile::fake()->create('virus.exe', 10)])));
        $this->errorEn('archivo', fn () => GuardarTarea::ejecutar($this->datos(['archivo' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')])));
        $this->assertSame(1, Tarea::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_otro_profesor_no_publica_ni_edita_en_una_materia_ajena(): void
    {
        $tarea = Tarea::firstOrFail();
        $this->actingAs(User::factory()->conRol('profesor')->create());
        $this->errorEn('curso_profesor_id', fn () => GuardarTarea::ejecutar($this->datos()));
        $this->errorEn('curso_profesor_id', fn () => GuardarTarea::ejecutar($this->datos(['titulo' => 'Cambiado']),
        $tarea));
        $this->assertSame('Trabajo práctico 1: tipos de corte', $tarea->fresh()->titulo);
    }

    // ── EntregarTarea ──────────────────────────────────────

    public function test_la_alumna_entrega_a_tiempo_y_reemplaza_su_entrega(): void
    {
        $tarea = $this->tarea();
        $this->actingAs($this->alumno('alumno@nep30.test'));
        $insc = $this->inscripcion('alumno@nep30.test');
        $entrega = EntregarTarea::ejecutar($tarea, $insc, UploadedFile::fake()->create('tp.pdf', 300,
        'application/pdf'));
        $this->assertFalse($entrega->fuera_de_termino);
        $this->assertSame('pdf', $entrega->archivo_tipo);
        $this->assertSame(300 * 1024, $entrega->archivo_tamano);
        Storage::disk('local')->assertExists("entregas/{$tarea->id}/{$insc->id}.pdf");

        // Reemplazo con otro tipo: queda una sola entrega y el archivo viejo se borra

        $entrega = EntregarTarea::ejecutar($tarea, $insc, UploadedFile::fake()->create('tp.zip', 50,
        'application/zip'));
        $this->assertSame(1, Entrega::where('tarea_id', $tarea->id)->count());
        $this->assertSame('zip', $entrega->archivo_tipo);
        Storage::disk('local')->assertMissing("entregas/{$tarea->id}/{$insc->id}.pdf");
        Storage::disk('local')->assertExists("entregas/{$tarea->id}/{$insc->id}.zip");
    }

    public function test_la_primera_entrega_tarde_se_acepta_y_queda_fuera_de_termino(): void
    {
        $tarea = $this->tarea('-1 day');
        $this->actingAs($this->alumno('alumno@nep30.test'));
        $entrega = EntregarTarea::ejecutar($tarea, $this->inscripcion('alumno@nep30.test'), UploadedFile::fake()->image('foto.jpg'));
        $this->assertTrue($entrega->fuera_de_termino);
        $this->assertSame('jpg', $entrega->archivo_tipo);
    }

    public function test_vencida_la_fecha_no_se_reemplaza_la_entrega(): void
    {
        $tarea = $this->tarea('+1 hour');
        $this->actingAs($this->alumno('alumno@nep30.test'));
        $insc = $this->inscripcion('alumno@nep30.test');
        EntregarTarea::ejecutar($tarea, $insc, UploadedFile::fake()->create('tp.pdf', 100, 'application/pdf'));
        $this->travel(2)->hours();
        $this->errorEn('archivo', fn () => EntregarTarea::ejecutar($tarea, $insc, UploadedFile::fake()->create('tp.docx', 100)));
        Storage::disk('local')->assertExists("entregas/{$tarea->id}/{$insc->id}.pdf");
        Storage::disk('local')->assertMissing("entregas/{$tarea->id}/{$insc->id}.docx");
    }

    public function test_un_archivo_no_permitido_o_muy_grande_no_se_guarda(): void
    {
        $tarea = $this->tarea();
        $this->actingAs($this->alumno('alumno@nep30.test'));
        $insc = $this->inscripcion('alumno@nep30.test');
        $this->errorEn('archivo', fn () => EntregarTarea::ejecutar($tarea, $insc, UploadedFile::fake()->create('virus.exe', 10)));
        $this->errorEn('archivo', fn () => EntregarTarea::ejecutar($tarea, $insc, UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')));
        $this->assertSame(0, Entrega::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_no_entrega_un_alumno_de_baja_ni_de_otro_curso(): void
    {
        $tarea = $this->tarea();
        $alumna = $this->inscripcion('alumno@nep30.test');
        $pdf = fn () => UploadedFile::fake()->create('tp.pdf', 10, 'application/pdf');
        $this->actingAs($this->alumno('alumno@nep30.test'));

        // De baja

        $alumna->update(['estado' => 'baja', 'fecha_baja' => now()->toDateString()]);
        $this->errorEn('archivo', fn () => EntregarTarea::ejecutar($tarea, $alumna->fresh(), $pdf()));

        // Inscripción en otro curso (Informática)

        $alumna->update(['estado' => 'activa', 'fecha_baja' => null]);
        $otroCurso = CursoProfesor::where('profesor_id', $this->profesora->id)->where('curso_id', '<>', $this->corte()->curso_id)->firstOrFail();
        $tareaAjena = Tarea::create(['curso_profesor_id' => $otroCurso->id, 'titulo' => 'De Informática',
        'fecha_entrega' => now()->addWeek()]);
        $this->errorEn('archivo', fn () => EntregarTarea::ejecutar($tareaAjena, $alumna->fresh(), $pdf()));
        $this->assertSame(0, Entrega::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    // ── DevolverEntrega ────────────────────────────────────

    public function test_la_profesora_devuelve_una_entrega_y_otro_no_puede(): void
    {
        $tarea = $this->tarea();
        $this->actingAs($this->alumno('alumno@nep30.test'));
        $entrega = EntregarTarea::ejecutar($tarea, $this->inscripcion('alumno@nep30.test'), UploadedFile::fake()->create('tp.pdf', 10, 'application/pdf'));
        $this->actingAs(User::factory()->conRol('profesor')->create());
        $this->errorEn('devolucion', fn () => DevolverEntrega::ejecutar($entrega, 'Muy bien'));
        $this->actingAs($this->profesora);
        $this->errorEn('devolucion', fn () => DevolverEntrega::ejecutar($entrega, '
        '));
        $this->assertNull($entrega->fresh()->devuelta_en);
        $entrega = DevolverEntrega::ejecutar($entrega, '
        Muy bien, faltó el corte en capas.
        ');
        $this->assertSame('Muy bien, faltó el corte en capas.', $entrega->fresh()->devolucion);
        $this->assertNotNull($entrega->fresh()->devuelta_en);
    }
}