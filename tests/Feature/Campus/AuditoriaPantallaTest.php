<?php

namespace Tests\Feature\Campus;

use App\Livewire\Auditoria\Registro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU16 (#123): pantalla «Auditoría» del Administrador y del Auditor (HU18).
 * Los datos salen de RegistroDeAuditoria::buscar() (#122). Los renglones de prueba se cargan
 * en marzo de 2026 y se filtran por esas fechas, para no mezclarse con los del seeder.
 */
class AuditoriaPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Un renglón de auditoría hecho a mano (insertar sí se puede; editar o borrar, no) */
    private function registro(array $datos): int
    {
        return DB::table('auditoria')->insertGetId([
            'usuario_id' => null, 'accion' => 'modificacion', 'tabla' => 'users', 'registro_id' => 1,
            'valores_anteriores' => null, 'valores_nuevos' => null, 'ip' => '127.0.0.1',
            'created_at' => '2026-03-02 10:00:00', ...$datos,
        ]);
    }

    /** Una fila del detalle: campo, antes y después, en ese orden */
    private function assertFila(string $html, string $campo, string $antes, string $despues): void
    {
        $this->assertMatchesRegularExpression(
            '#>'.preg_quote($campo, '#').'</td>\s*<td[^>]*>'.preg_quote($antes, '#').'</td>\s*<td[^>]*>'.preg_quote($despues, '#').'</td>#u',
            $html,
            "No está la fila «{$campo}: {$antes} → {$despues}»"
        );
    }

    private function enMarzo(User $usuario)
    {
        return Livewire::actingAs($usuario)->test(Registro::class)
            ->set('desde', '2026-03-01')->set('hasta', '2026-03-31');
    }

    // ── Acceso ─────────────────────────────────────────────

    public function test_entran_el_administrador_y_el_auditor_y_nadie_mas(): void
    {
        $admin = $this->usuario('admin@nep30.test');
        $auditor = $this->usuario('auditor@nep30.test');

        $this->get(route('admin.auditoria'))->assertRedirect(route('login'));
        $this->get(route('auditor.auditoria'))->assertRedirect(route('login'));

        $this->actingAs($admin)->get(route('admin.auditoria'))
            ->assertOk()->assertSee('Auditoría')->assertSee(route('admin.auditoria'));   // y está en el menú
        $this->actingAs($auditor)->get(route('auditor.auditoria'))
            ->assertOk()->assertSee(route('auditor.auditoria'));

        foreach (['profesor@nep30.test', 'alumno@nep30.test'] as $email) {
            $this->actingAs($this->usuario($email));
            $this->get(route('admin.auditoria'))->assertForbidden();
            $this->get(route('auditor.auditoria'))->assertForbidden();
        }
    }

    // ── Tabla ──────────────────────────────────────────────

    public function test_muestra_fecha_usuario_accion_en_color_tabla_y_registro(): void
    {
        $admin = $this->usuario('admin@nep30.test');
        $id = $this->registro(['usuario_id' => $admin->id, 'accion' => 'baja', 'tabla' => 'users',
            'registro_id' => $this->usuario('alumno2@nep30.test')->id, 'created_at' => '2026-03-05 18:30:00']);

        $this->enMarzo($admin)
            ->assertSeeHtml('wire:key="auditoria-'.$id.'"')
            ->assertSee(['05/03/2026 18:30', 'Administrador, Prueba', 'Usuarios', 'Prueba, Alumno Dos'])
            ->assertSeeHtml('accion-baja')
            ->assertSee('Baja');
    }

    public function test_esta_paginada_de_a_25(): void
    {
        foreach (range(1, 30) as $i) {
            $this->registro(['registro_id' => $i, 'created_at' => '2026-03-02 10:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00']);
        }

        $pantalla = $this->enMarzo($this->usuario('admin@nep30.test'))
            ->assertSee('30 registros')->assertSee('Página 1 de 2')
            ->assertSee('02/03/2026 10:30');                       // lo más reciente primero
        $this->assertCount(25, $pantalla->viewData('registros')->items());

        $pantalla->call('nextPage')->assertSee('Página 2 de 2')->assertSee('02/03/2026 10:01');
    }

    // ── Filtros ────────────────────────────────────────────

    public function test_filtra_por_tabla_accion_usuario_y_fechas_y_se_puede_limpiar(): void
    {
        $admin = $this->usuario('admin@nep30.test');
        $nota = $this->registro(['usuario_id' => $admin->id, 'tabla' => 'evaluaciones', 'accion' => 'alta', 'created_at' => '2026-03-02 09:00:00']);
        $baja = $this->registro(['usuario_id' => null, 'tabla' => 'users', 'accion' => 'baja', 'created_at' => '2026-03-10 09:00:00']);

        $pantalla = $this->enMarzo($admin);
        $ve = fn (int $id) => $pantalla->assertSeeHtml('wire:key="auditoria-'.$id.'"');
        $noVe = fn (int $id) => $pantalla->assertDontSeeHtml('wire:key="auditoria-'.$id.'"');

        $pantalla->set('tabla', 'evaluaciones');
        $ve($nota); $noVe($baja);

        $pantalla->set('tabla', '')->set('accion', 'baja');
        $ve($baja); $noVe($nota);

        $pantalla->set('accion', '')->set('usuario', 'Administrador');
        $ve($nota); $noVe($baja);

        $pantalla->set('usuario', '')->set('desde', '2026-03-05');
        $ve($baja); $noVe($nota);

        $pantalla->call('limpiar')
            ->assertSet('tabla', '')->assertSet('accion', '')->assertSet('usuario', '')
            ->assertSet('desde', '')->assertSet('hasta', '');
        $this->assertSame(DB::table('auditoria')->count(), $pantalla->viewData('registros')->total());
    }

    public function test_sin_resultados_lo_dice(): void
    {
        $this->enMarzo($this->usuario('admin@nep30.test'))
            ->set('usuario', 'Inexistente')
            ->assertSee('No hay registros con esos filtros');
    }

    // ── Detalle ────────────────────────────────────────────

    public function test_ver_detalle_muestra_campo_antes_y_despues(): void
    {
        $id = $this->registro([
            'tabla' => 'evaluaciones', 'accion' => 'modificacion', 'registro_id' => 999,
            'valores_anteriores' => json_encode(['nota' => 5, 'tipo' => 'parcial', 'fecha' => '2026-04-20']),
            'valores_nuevos' => json_encode(['nota' => 6.5, 'tipo' => 'parcial', 'fecha' => '2026-04-27']),
        ]);

        $pantalla = $this->enMarzo($this->usuario('admin@nep30.test'))
            ->assertDontSee('Detalle del cambio')
            ->call('verDetalle', $id)
            ->assertSee(['Detalle del cambio', 'Notas: n.º 999 (ya no existe)', 'Sistema'])
            ->assertDontSee('Tipo de evaluación');                   // solo lo que cambió

        $this->assertFila($pantalla->html(), 'Nota', '5', '6.5');                  // nombre legible del campo
        $this->assertFila($pantalla->html(), 'Fecha', '20/04/2026', '27/04/2026');  // fechas en dd/mm/aaaa

        $pantalla->call('cerrar')->assertDontSee('Detalle del cambio');
    }

    public function test_el_detalle_es_legible_para_quien_no_programa(): void
    {
        $id = $this->registro([
            'tabla' => 'users', 'accion' => 'modificacion',
            'valores_anteriores' => json_encode(['activo' => 1, 'email' => 'a@nep30.test']),
            'valores_nuevos' => json_encode(['activo' => 0, 'email' => 'b@nep30.test', 'password_cambiada' => true]),
        ]);

        $html = $this->enMarzo($this->usuario('auditor@nep30.test'))->call('verDetalle', $id)->html();

        $this->assertFila($html, 'Activo', 'Sí', 'No');
        $this->assertFila($html, 'Correo', 'a@nep30.test', 'b@nep30.test');
        $this->assertFila($html, 'Cambió la contraseña', '—', 'Sí');
        $this->assertStringNotContainsString('>password_cambiada<', $html);
    }

    public function test_muestra_nombres_en_vez_de_numeros_internos(): void
    {
        $nota = DB::table('evaluaciones')->first();       // la del seeder: parcial de la Alumna Uno en Corte
        $curso = DB::table('inscripciones')->value('curso_id');
        $id = $this->registro([
            'tabla' => 'evaluaciones', 'accion' => 'alta', 'registro_id' => $nota->id,
            'valores_nuevos' => json_encode(['inscripcion_materia_id' => $nota->inscripcion_materia_id, 'nota' => 8]),
        ]);
        $id2 = $this->registro([
            'tabla' => 'inscripciones', 'accion' => 'alta', 'registro_id' => DB::table('inscripciones')->value('id'),
            'valores_nuevos' => json_encode(['curso_id' => $curso]),
        ]);

        $pantalla = $this->enMarzo($this->usuario('auditor@nep30.test'))
            ->assertSee('Prueba, Alumna Uno · Módulo 1: Corte y peinado')       // columna «Sobre» de la nota
            ->assertSee('Prueba, Alumna Uno · Peluquería');                     // y de la inscripción

        $html = $pantalla->call('verDetalle', $id)->html();
        $this->assertStringContainsString('en Notas: Prueba, Alumna Uno · Módulo 1: Corte y peinado', $html);
        $this->assertFila($html, 'Alumno y módulo', '—', 'Prueba, Alumna Uno · Módulo 1: Corte y peinado');

        $html = $pantalla->call('verDetalle', $id2)->html();
        $this->assertMatchesRegularExpression('#>Curso</td>\s*<td[^>]*>—</td>\s*<td[^>]*>Peluquería · [^<]+ · turno [^<]+</td>#u', $html);
    }

    // ── Solo lectura ───────────────────────────────────────

    public function test_no_hay_opciones_para_crear_editar_ni_borrar(): void
    {
        $this->registro(['valores_nuevos' => json_encode(['apellido' => 'Gómez'])]);

        foreach (['admin@nep30.test', 'auditor@nep30.test'] as $email) {
            $html = $this->enMarzo($this->usuario($email))->html();

            foreach (['<form', 'wire:submit', 'Editar', 'Eliminar', 'Borrar', 'Nuevo', 'Crear'] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $html, "No debería haber «{$prohibido}» para {$email}");
            }
            $this->assertStringContainsString('Ver detalle', $html);
        }
    }
}