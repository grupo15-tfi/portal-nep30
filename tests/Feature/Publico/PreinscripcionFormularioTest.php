<?php

namespace Tests\Feature\Publico;

use App\Events\PreinscripcionRecibida;
use App\Livewire\Publico\Preinscripcion;
use App\Models\Curso;
use App\Models\Preinscripcion as Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU04 (#52): formulario de preinscripción y confirmación. La lógica la prueba #51 (PreinscripcionAccionTest);
 * acá, que la página use la acción y muestre bien cada resultado y cada error.
 * Datos de DatosDePruebaSeeder: Informática con cupo (queda pendiente); Peluquería sin cupo y con
 * 2 aspirantes en lista de espera (la próxima queda N.º 3); el DNI 30000005 ya está pendiente en Informática.
 */
class PreinscripcionFormularioTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([PreinscripcionRecibida::class]); // el correo de acuse es de #53
    }

    private function oferta(string $plan): object
    {
        return DB::table('v_oferta_publica')->where('plan', 'like', "%{$plan}%")->first();
    }

    /** Componente con datos válidos cargados; cada prueba cambia lo que necesita */
    private function formulario(string $plan = 'Informática', array $cambios = [])
    {
        $componente = Livewire::test(Preinscripcion::class, ['curso' => $this->oferta($plan)]);
        $datos = [
            'apellido' => 'Gómez', 'nombre' => 'María Laura', 'dni' => '40.123.456',
            'fecha_nacimiento' => '2000-04-15', 'telefono' => '370 4123456', 'email' => 'maria.gomez@gmail.com',
            'acepta_privacidad' => true, ...$cambios,
        ];
        foreach ($datos as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        return $componente;
    }

    public function test_la_pagina_responde_para_un_curso_de_la_oferta_y_da_404_si_no_esta(): void
    {
        $curso = $this->oferta('Informática');

        $this->get(route('preinscripcion.crear', $curso->curso_id))->assertOk()
            ->assertSee('Preinscripción')
            ->assertSee($curso->plan)
            ->assertSee('Curso elegido')
            ->assertSee('política de privacidad')
            ->assertSeeLivewire(Preinscripcion::class);

        $this->get(route('preinscripcion.crear', 99999))->assertNotFound();

        Curso::whereKey($curso->curso_id)->update(['estado' => 'inactivo']);
        $this->get(route('preinscripcion.crear', $curso->curso_id))->assertNotFound();
    }

    public function test_la_oferta_y_el_detalle_del_curso_llevan_al_formulario(): void
    {
        $curso = $this->oferta('Informática');
        $url = route('preinscripcion.crear', $curso->curso_id);

        $this->get(route('oferta'))->assertSee($url, false);
        $this->get(route('oferta.curso', $curso->curso_id))->assertSee($url, false);
    }

    public function test_un_envio_correcto_muestra_la_confirmacion_y_no_crea_usuario(): void
    {
        $usuarios = User::count();

        $this->formulario()->call('enviar')
            ->assertHasNoErrors()
            ->assertSee('Recibimos tu preinscripción')
            ->assertSee('PRE-'.now()->year.'-')
            ->assertSee('m***@gmail.com')
            ->assertDontSee('maria.gomez@gmail.com')
            ->assertDontSee('40123456');

        $this->assertDatabaseHas('preinscripciones', ['dni' => '40123456', 'estado' => 'pendiente', 'email' => 'maria.gomez@gmail.com']);
        $this->assertSame($usuarios, User::count());
        Event::assertDispatched(PreinscripcionRecibida::class);
    }

    public function test_sin_cupo_queda_en_lista_de_espera_y_se_le_dice_su_lugar(): void
    {
        $this->get(route('preinscripcion.crear', $this->oferta('Peluquería')->curso_id))
            ->assertSee('Este curso no tiene cupo')
            ->assertSee('Anotarme en la lista de espera');

        $this->formulario('Peluquería')->call('enviar')
            ->assertHasNoErrors()
            ->assertSee('Quedaste en la lista de espera')
            ->assertSee('N.º 3');

        $this->assertSame('en_espera', Solicitud::where('dni', '40123456')->value('estado'));
    }

    public function test_sin_aceptar_la_privacidad_no_se_envia(): void
    {
        $this->formulario('Informática', ['acepta_privacidad' => false])->call('enviar')
            ->assertHasErrors('acepta_privacidad')
            ->assertSee('Tenés que aceptar la política de privacidad')
            ->assertSee('Revisá 1 campo')
            ->assertDontSee('Recibimos tu preinscripción');

        $this->assertDatabaseMissing('preinscripciones', ['dni' => '40123456']);
    }

    public function test_los_errores_salen_debajo_de_cada_campo(): void
    {
        $this->formulario('Informática', ['dni' => '123', 'email' => 'no-es-un-correo', 'telefono' => 'abc'])->call('enviar')
            ->assertHasErrors(['dni', 'email', 'telefono'])
            ->assertSee('El DNI tiene que tener 7 u 8 números.')
            ->assertSee('El correo no es válido.')
            ->assertSee('Revisá 3 campos')
            ->assertSeeHtml('id="error-dni"')
            ->assertSeeHtml('aria-invalid="true"');
    }

    public function test_menor_de_la_edad_minima_y_solicitud_duplicada(): void
    {
        $this->formulario('Informática', ['fecha_nacimiento' => now()->subYears(15)->toDateString()])->call('enviar')
            ->assertHasErrors('fecha_nacimiento')
            ->assertSee('tenés que tener al menos 18 años');

        $this->formulario('Informática', ['dni' => '30000005'])->call('enviar')
            ->assertHasErrors('dni')
            ->assertSee('Ya tenés una preinscripción pendiente para este curso.');
    }
}
