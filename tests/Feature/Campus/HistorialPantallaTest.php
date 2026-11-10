<?php

namespace Tests\Feature\Campus;

use App\Livewire\Admin\Alumnos\Index as AlumnosAdmin;
use App\Livewire\Alumno\Historial;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU17 (#127): pantalla «Historial académico» del alumno y botón «Historial (PDF)» en Alumnos.
 * Los datos salen de HistorialAcademico (#125) y el PDF lo genera #126: acá se simulan sus rutas
 * para probar que los botones aparecen y apuntan bien.
 */
class HistorialPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function alumna(): User
    {
        return User::where('email', 'alumno@nep30.test')->firstOrFail();
    }

    /** Simula las rutas del PDF de #126 (mismos nombres y direcciones del contrato) */
    private function rutasDelPdf(): void
    {
        Route::middleware(['web', 'auth', 'role:alumno'])->get('/alumno/historial/{inscripcion}/pdf', fn () => 'pdf')->name('alumno.historial.pdf');
        Route::middleware(['web', 'auth', 'role:administrador'])->get('/admin/alumnos/{inscripcion}/historial', fn () => 'pdf')->name('admin.alumnos.historial');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_solo_el_alumno_entra_y_tiene_el_menu(): void
    {
        $this->get(route('alumno.historial'))->assertRedirect(route('login'));

        $this->actingAs($this->alumna())->get(route('alumno.historial'))
            ->assertOk()->assertSee('Historial académico')
            ->assertSee('href="'.route('alumno.historial').'"', false);

        foreach (['profesor@nep30.test', 'auditor@nep30.test', 'admin@nep30.test'] as $email) {
            $this->actingAs(User::where('email', $email)->firstOrFail())->get(route('alumno.historial'))->assertForbidden();
        }
    }

    public function test_muestra_cada_inscripcion_con_sus_modulos_y_el_resumen(): void
    {
        $insc = Inscripcion::where('alumno_id', $this->alumna()->id)->with('curso.planEstudio', 'curso.sede', 'materias.materia')->firstOrFail();

        $pantalla = Livewire::actingAs($this->alumna())->test(Historial::class)
            ->assertSee($insc->curso->planEstudio->nombre)
            ->assertSee($insc->curso->sede->nombre)
            ->assertSee(['Acreditados', 'Promedio', 'Pendientes'])
            ->assertSee('no reemplaza al certificado analítico oficial');

        foreach ($insc->materias as $im) {
            $pantalla->assertSee($im->materia->nombre);
        }
    }

    public function test_un_modulo_acreditado_se_ve_en_verde_con_su_nota(): void
    {
        $insc = Inscripcion::where('alumno_id', $this->alumna()->id)->firstOrFail();
        $im = $insc->materias()->with('materia')->firstOrFail();
        \Illuminate\Support\Facades\DB::table('inscripcion_materias')->where('id', $im->id)
            ->update(['estado' => 'acreditada', 'nota_final' => 8.5, 'fecha_acreditacion' => '2026-11-06']);

        Livewire::actingAs($this->alumna())->test(Historial::class)
            ->assertSeeInOrder([$im->materia->nombre, 'bg-exito-claro text-exito', 'Acreditada', '8,5', '06/11/2026'], false);
    }

    public function test_alumno_sin_inscripciones_ve_un_mensaje_claro(): void
    {
        $nuevo = User::factory()->conRol('alumno')->create();

        Livewire::actingAs($nuevo)->test(Historial::class)
            ->assertSee('Todavía no tenés inscripciones')
            ->assertDontSee('Descargar PDF');
    }

    public function test_no_muestra_historiales_de_otro_alumno(): void
    {
        $otro = Inscripcion::where('alumno_id', '!=', $this->alumna()->id)->with('alumno')->firstOrFail();

        Livewire::actingAs($this->alumna())->test(Historial::class)
            ->assertDontSeeHtml('wire:key="historial-'.$otro->id.'"');
    }

    public function test_el_boton_descargar_pdf_apunta_a_la_ruta_de_cada_inscripcion(): void
    {
        // Sin las rutas de #126 todavía, no hay botón (nada se rompe)
        Livewire::actingAs($this->alumna())->test(Historial::class)->assertDontSee('Descargar PDF');

        $this->rutasDelPdf();
        $insc = Inscripcion::where('alumno_id', $this->alumna()->id)->firstOrFail();

        Livewire::actingAs($this->alumna())->test(Historial::class)
            ->assertSee('Descargar PDF')
            ->assertSeeHtml('href="'.url('/alumno/historial/'.$insc->id.'/pdf').'"');
    }

    public function test_el_administrador_tiene_historial_pdf_en_cada_fila_de_alumnos(): void
    {
        $admin = User::where('email', 'admin@nep30.test')->firstOrFail();
        Livewire::actingAs($admin)->test(AlumnosAdmin::class)->assertDontSee('Historial (PDF)');

        $this->rutasDelPdf();
        $insc = Inscripcion::firstOrFail();

        Livewire::actingAs($admin)->test(AlumnosAdmin::class)
            ->assertSee('Historial (PDF)')
            ->assertSeeHtml('href="'.url('/admin/alumnos/'.$insc->id.'/historial').'"');
    }
}
