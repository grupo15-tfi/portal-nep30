<?php

namespace Tests\Feature\Publico;

use App\Actions\Preinscripciones\Preinscribir;
use App\Livewire\Publico\Preinscripcion as Formulario;
use App\Models\Preinscripcion;
use App\Notifications\AcusePreinscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU04 (#53): correo de acuse de recibo y protección del formulario.
 * Datos de DatosDePruebaSeeder: Informática con cupo (pendiente) y Peluquería sin cupo (en espera).
 */
class PreinscripcionAcuseTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function cursoId(string $plan): int
    {
        return DB::table('v_oferta_publica')->where('plan', 'like', "%{$plan}%")->value('curso_id');
    }

    private function datos(array $cambios = []): array
    {
        return [
            'curso_id' => $this->cursoId('Informática'), 'dni' => '40123456', 'apellido' => 'Gómez',
            'nombre' => 'María', 'fecha_nacimiento' => '2000-04-15', 'email' => 'maria@nep30.test',
            'telefono' => '3704 123456', 'acepta_privacidad' => true, ...$cambios,
        ];
    }

    /** El HTML del correo, tal como lo recibiría el aspirante */
    private function htmlDelCorreo(Preinscripcion $pre): string
    {
        return (string) (new AcusePreinscripcion($pre))
            ->toMail(new AnonymousNotifiable)
            ->render();
    }

    /** El formulario de Angel (#52) con datos válidos */
    private function formulario(array $cambios = [])
    {
        $componente = Livewire::test(Formulario::class, [
            'curso' => DB::table('v_oferta_publica')->where('plan', 'like', '%Informática%')->first(),
        ]);
        $datos = [
            'apellido' => 'Gómez', 'nombre' => 'María', 'dni' => '40123456', 'fecha_nacimiento' => '2000-04-15',
            'telefono' => '3704 123456', 'email' => 'maria@nep30.test', 'acepta_privacidad' => true, ...$cambios,
        ];
        foreach ($datos as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        return $componente;
    }

    // ---------- Correo de acuse ----------

    public function test_al_preinscribirse_se_envia_el_acuse_al_correo_del_aspirante(): void
    {
        Notification::fake();

        $pre = Preinscribir::ejecutar($this->datos());

        Notification::assertSentOnDemand(AcusePreinscripcion::class,
            fn ($notificacion, $canales, $destino) => $destino->routes['mail'] === 'maria@nep30.test'
                && $notificacion->preinscripcion->is($pre));
    }

    public function test_el_acuse_va_por_la_cola(): void
    {
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class,
            new AcusePreinscripcion(new Preinscripcion));
    }

    public function test_el_correo_dice_curso_sede_turno_y_que_quedo_pendiente(): void
    {
        Notification::fake();
        $pre = Preinscribir::ejecutar($this->datos());

        $html = $this->htmlDelCorreo($pre);

        $this->assertStringContainsString('Hola, María', $html);
        $this->assertStringContainsString('Operador de Informática', $html);
        $this->assertStringContainsString('Sede Central (prueba)', $html);
        $this->assertStringContainsString('Mañana', $html);
        $this->assertStringContainsString('pendiente de revisión', $html);
        $this->assertStringContainsString(Formulario::codigo($pre), $html);
    }

    public function test_sin_cupo_el_correo_avisa_la_lista_de_espera(): void
    {
        Notification::fake();
        $pre = Preinscribir::ejecutar($this->datos(['curso_id' => $this->cursoId('Peluquería')]));

        $this->assertStringContainsString('lista de espera', $this->htmlDelCorreo($pre));
    }

    public function test_el_correo_no_incluye_datos_sensibles(): void
    {
        Notification::fake();
        $pre = Preinscribir::ejecutar($this->datos(['domicilio' => 'Calle Falsa 123']));

        $html = $this->htmlDelCorreo($pre);

        $this->assertStringNotContainsString('40123456', $html);   // DNI
        $this->assertStringNotContainsString('123456', $html);     // teléfono
        $this->assertStringNotContainsString('Calle Falsa', $html); // domicilio
        $this->assertStringNotContainsString('2000', $html);       // fecha de nacimiento
    }

    public function test_si_el_correo_falla_la_preinscripcion_igual_queda_guardada(): void
    {
        Notification::shouldReceive('route')->andThrow(new \RuntimeException('El servidor de correo no responde'));

        $pre = Preinscribir::ejecutar($this->datos());

        $this->assertDatabaseHas('preinscripciones', ['id' => $pre->id, 'estado' => 'pendiente']);
    }

    // ---------- Protección del formulario ----------

    public function test_si_un_bot_completa_el_campo_trampa_no_se_guarda_nada(): void
    {
        Notification::fake();

        $this->formulario(['sitio_web' => 'https://spam.example'])->call('enviar')
            ->assertSet('resultado', null);

        $this->assertDatabaseMissing('preinscripciones', ['dni' => '40123456']);
        Notification::assertNothingSent();
    }

    public function test_despues_de_5_envios_desde_la_misma_ip_pide_esperar(): void
    {
        Notification::fake();

        // 5 intentos (aunque fallen, cuentan): DNI inválido para que no se guarde nada
        for ($i = 0; $i < Formulario::MAX_ENVIOS; $i++) {
            $this->formulario(['dni' => '1'])->call('enviar')->assertHasErrors('dni');
        }

        // El sexto, aunque sea válido, se frena
        $this->formulario()->call('enviar')->assertHasErrors('envio');
        $this->assertDatabaseMissing('preinscripciones', ['dni' => '40123456']);
    }

    public function test_la_pagina_del_formulario_tiene_limite_de_pedidos(): void
    {
        $url = route('preinscripcion.crear', $this->cursoId('Informática'));

        for ($i = 0; $i < 30; $i++) {
            $this->get($url)->assertOk();
        }

        $this->get($url)->assertStatus(429);
    }
}