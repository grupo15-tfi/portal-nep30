<?php

namespace Tests\Feature\Campus;

use App\Actions\Notas\CerrarModulo;
use App\Models\Inscripcion;
use App\Models\InscripcionMateria;
use App\Models\Materia;
use App\Models\User;
use App\Queries\HistorialAcademico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU17 (#126): el historial académico en PDF.
 * Datos de DatosDePruebaSeeder: Alumna Uno (DNI 30000001) y Alumno Dos, inscriptos en Peluquería.
 */
class HistorialPdfTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function inscripcion(string $email): Inscripcion
    {
        return Inscripcion::where('alumno_id', $this->usuario($email)->id)->orderBy('id')->firstOrFail();
    }

    // ── Descarga del alumno ────────────────────────────────

    public function test_el_alumno_descarga_el_pdf_de_su_inscripcion(): void
    {
        $insc = $this->inscripcion('alumno@nep30.test');

        $r = $this->actingAs($this->usuario('alumno@nep30.test'))->get(route('alumno.historial.pdf', $insc));

        $r->assertOk();
        $r->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->assertMatchesRegularExpression('/filename="?historial-30000001-peluqueria[^"]*\.pdf"?/', $r->headers->get('content-disposition'));
    }

    public function test_el_alumno_no_descarga_el_historial_de_otro(): void
    {
        $ajena = $this->inscripcion('alumno2@nep30.test');

        $this->actingAs($this->usuario('alumno@nep30.test'))
            ->get(route('alumno.historial.pdf', $ajena))
            ->assertForbidden();
    }

    // ── Descarga del administrador ─────────────────────────

    public function test_el_administrador_descarga_el_pdf_de_cualquier_inscripcion(): void
    {
        $r = $this->actingAs($this->usuario('admin@nep30.test'))
            ->get(route('admin.alumnos.historial', $this->inscripcion('alumno2@nep30.test')));

        $r->assertOk();
        $r->assertHeader('content-type', 'application/pdf');
    }

    // ── Permisos ───────────────────────────────────────────

    public function test_profesor_y_auditor_reciben_403(): void
    {
        $insc = $this->inscripcion('alumno@nep30.test');

        foreach (['profesor@nep30.test', 'auditor@nep30.test'] as $email) {
            $this->actingAs($this->usuario($email));
            $this->get(route('alumno.historial.pdf', $insc))->assertForbidden();
            $this->get(route('admin.alumnos.historial', $insc))->assertForbidden();
        }
    }

    public function test_sin_sesion_va_al_login(): void
    {
        $insc = $this->inscripcion('alumno@nep30.test');

        $this->get(route('alumno.historial.pdf', $insc))->assertRedirect(route('login'));
        $this->get(route('admin.alumnos.historial', $insc))->assertRedirect(route('login'));
    }

    // ── Contenido (la vista que arma el PDF) ───────────────

    public function test_la_vista_trae_los_datos_los_modulos_y_la_leyenda(): void
    {
        $this->actingAs($this->usuario('profesor@nep30.test'));
        $insc = $this->inscripcion('alumno@nep30.test');
        $modulo = InscripcionMateria::where('inscripcion_id', $insc->id)
            ->where('materia_id', Materia::where('nombre', 'Módulo 1: Corte y peinado')->value('id'))->firstOrFail();
        CerrarModulo::ejecutar($modulo, 7);

        $html = view('pdf.historial', ['h' => HistorialAcademico::de($insc)])->render();

        $this->assertStringContainsString('Núcleo de Educación Permanente y Formación Profesional N.º 30', $html);
        $this->assertStringContainsString('Prueba, Alumna Uno', $html);
        $this->assertStringContainsString('30000001', $html);
        $this->assertStringContainsString('Módulo 1: Corte y peinado', $html);
        $this->assertStringContainsString('7,00', $html);
        $this->assertStringContainsString(now()->format('d/m/Y'), $html);
        $this->assertStringContainsString('No reemplaza al certificado analítico oficial', $html);
    }
}