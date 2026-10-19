<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de PRUEBA para la PC y la VPS de demos. Todos los nombres, DNI y correos
 * son inventados (Ley 25.326): nunca cargar datos reales de alumnos fuera del
 * hosting de la institución.
 *
 * Deja armado un recorrido completo para mostrar en las demos:
 *   - 2 sedes con coordenadas (para el mapa), período 2026 y 3 cursos.
 *   - Un usuario de cada rol (clave: password).
 *   - Peluquería con cupo 2 completo y 2 aspirantes en LISTA DE ESPERA.
 *   - Notas, asistencia, una tarea, un aviso, un evento y una noticia.
 */
class DatosDePruebaSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('sedes')->where('nombre', 'Sede Central (prueba)')->exists()) {
            $this->command?->info('Los datos de prueba ya estaban cargados.');

            return;
        }

        $ahora = now();
        $ts = ['created_at' => $ahora, 'updated_at' => $ahora];
        $admin = User::role('administrador')->firstOrFail();

        // Sedes (coordenadas aproximadas de la ciudad de Formosa).
        $central = DB::table('sedes')->insertGetId([
            'nombre' => 'Sede Central (prueba)', 'tipo' => 'central', 'direccion' => 'Calle de prueba 123',
            'localidad' => 'Formosa', 'telefono' => '3704 000000', 'email' => 'sede@nep30.test',
            'latitud' => -26.1775000, 'longitud' => -58.1781000, ...$ts,
        ]);
        $anexo = DB::table('sedes')->insertGetId([
            'nombre' => 'Anexo Barrio Norte (prueba)', 'tipo' => 'anexo', 'direccion' => 'Mz. 10 C. 5',
            'localidad' => 'Formosa', 'latitud' => -26.1600000, 'longitud' => -58.1900000,
            'observaciones' => 'Funciona en una escuela primaria (dato de prueba)', ...$ts,
        ]);

        $periodo = DB::table('periodos_lectivos')->insertGetId([
            'anio' => 2026, 'cuatrimestre' => null, 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-12-11', ...$ts,
        ]);

        $plan = fn (string $nombre) => DB::table('planes_estudio')->where('nombre', $nombre)->value('id');
        $peluqueria = $plan('Peluquería');
        $informatica = $plan('Operador de Informática para la Administración y Gestión');
        $alfabetizacion = $plan('Primaria para adultos: Alfabetización');

        $materia = fn (int $planId, string $nombre, ?int $ciclo = null) => DB::table('materias')->insertGetId(
            ['plan_id' => $planId, 'nombre' => $nombre, 'ciclo' => $ciclo, 'carga_horaria' => 64, ...$ts]
        );
        $corte = $materia($peluqueria, 'Módulo 1: Corte y peinado');
        $color = $materia($peluqueria, 'Módulo 2: Coloración');
        $ofimatica = $materia($informatica, 'Módulo 1: Ofimática');
        $materia($informatica, 'Módulo 2: Gestión administrativa');
        $materia($alfabetizacion, 'Lengua', 1);
        $materia($alfabetizacion, 'Matemática', 1);

        $curso = fn (int $planId, int $sedeId, string $turno, string $desde, string $hasta, int $cupo) => DB::table('cursos')->insertGetId([
            'plan_id' => $planId, 'sede_id' => $sedeId, 'periodo_id' => $periodo, 'turno' => $turno,
            'dias' => 'Lunes a viernes', 'hora_inicio' => $desde, 'hora_fin' => $hasta, 'cupo' => $cupo, ...$ts,
        ]);
        $cursoPelu = $curso($peluqueria, $central, 'tarde', '14:00', '18:00', 2);
        $cursoInfo = $curso($informatica, $central, 'mañana', '08:00', '12:00', 20);
        $curso($alfabetizacion, $anexo, 'noche', '19:00', '22:00', 25);

        // Un usuario de cada rol (clave: password).
        $usuario = function (string $dni, string $nombre, string $email, string $rol) use ($central) {
            $u = User::create([
                'dni' => $dni, 'apellido' => 'Prueba', 'nombre' => $nombre, 'email' => $email,
                'password' => Hash::make('password'), 'sede_id' => $central, 'fecha_nacimiento' => '1990-05-10',
            ]);
            $u->forceFill(['email_verified_at' => now()])->save();
            $u->assignRole($rol);

            return $u;
        };
        $profesor = $usuario('20000001', 'Profesora', 'profesor@nep30.test', 'profesor');
        $alumna = $usuario('30000001', 'Alumna Uno', 'alumno@nep30.test', 'alumno');
        $alumno2 = $usuario('30000002', 'Alumno Dos', 'alumno2@nep30.test', 'alumno');
        $usuario('40000001', 'Auditor', 'auditor@nep30.test', 'auditor');

        $cpCorte = DB::table('curso_profesores')->insertGetId(['curso_id' => $cursoPelu, 'materia_id' => $corte, 'profesor_id' => $profesor->id, ...$ts]);
        DB::table('curso_profesores')->insert(['curso_id' => $cursoPelu, 'materia_id' => $color, 'profesor_id' => $profesor->id, ...$ts]);
        DB::table('curso_profesores')->insert(['curso_id' => $cursoInfo, 'materia_id' => $ofimatica, 'profesor_id' => $profesor->id, ...$ts]);

        // Preinscripción -> aprobada -> alta del alumno (HU04, HU09).
        $preinscribir = fn (User|array $p, int $cursoId) => DB::table('preinscripciones')->insertGetId([
            'curso_id' => $cursoId,
            'dni' => $p instanceof User ? $p->dni : $p['dni'],
            'apellido' => 'Prueba',
            'nombre' => $p instanceof User ? $p->nombre : $p['nombre'],
            'fecha_nacimiento' => '1995-08-20',
            'email' => $p instanceof User ? $p->email : $p['email'],
            'telefono' => '3704 111111',
            'nivel_educativo' => 'secundaria_incompleta',
            'acepta_privacidad' => true,
            ...$ts,
        ]);
        foreach ([$alumna, $alumno2] as $a) {
            $pre = $preinscribir($a, $cursoPelu);
            DB::table('preinscripciones')->where('id', $pre)->update([
                'estado' => 'aprobada', 'revisado_por' => $admin->id, 'revisado_en' => $ahora,
            ]);
            $insc = DB::table('inscripciones')->insertGetId([
                'alumno_id' => $a->id, 'curso_id' => $cursoPelu, 'preinscripcion_id' => $pre,
                'fecha_inscripcion' => '2026-03-02', ...$ts,
            ]);
            foreach ([$corte, $color] as $m) {
                DB::table('inscripcion_materias')->insert(['inscripcion_id' => $insc, 'materia_id' => $m, ...$ts]);
            }
        }

        // El curso quedó completo: estas dos quedan EN ESPERA solas (trigger de la base).
        $preinscribir(['dni' => '30000003', 'nombre' => 'Aspirante Uno', 'email' => 'aspirante1@nep30.test'], $cursoPelu);
        $preinscribir(['dni' => '30000004', 'nombre' => 'Aspirante Dos', 'email' => 'aspirante2@nep30.test'], $cursoPelu);
        // Una pendiente en un curso con cupo, para revisar desde el panel.
        $preinscribir(['dni' => '30000005', 'nombre' => 'Aspirante Tres', 'email' => 'aspirante3@nep30.test'], $cursoInfo);

        // Campus: clase con asistencia, nota de un parcial, tarea, aviso, evento y noticia.
        $inscAlumna = DB::table('inscripciones')->where('alumno_id', $alumna->id)->value('id');
        $inscAlumno2 = DB::table('inscripciones')->where('alumno_id', $alumno2->id)->value('id');
        $clase = DB::table('clases')->insertGetId([
            'curso_profesor_id' => $cpCorte, 'fecha' => '2026-03-09', 'hora_inicio' => '14:00', 'hora_fin' => '18:00',
            'tema' => 'Herramientas y técnicas básicas de corte', ...$ts,
        ]);
        DB::table('asistencias')->insert([
            ['clase_id' => $clase, 'inscripcion_id' => $inscAlumna, 'estado' => 'presente', ...$ts],
            ['clase_id' => $clase, 'inscripcion_id' => $inscAlumno2, 'estado' => 'justificado', ...$ts],
        ]);
        $imAlumna = DB::table('inscripcion_materias')->where(['inscripcion_id' => $inscAlumna, 'materia_id' => $corte])->value('id');
        DB::table('evaluaciones')->insert([
            'inscripcion_materia_id' => $imAlumna, 'tipo' => 'parcial', 'nota' => 8, 'fecha' => '2026-04-20',
            'cargada_por' => $profesor->id, ...$ts,
        ]);
        DB::table('tareas')->insert([
            'curso_profesor_id' => $cpCorte, 'titulo' => 'Trabajo práctico 1: tipos de corte',
            'consigna' => 'Describir tres tipos de corte con fotos o dibujos.', 'fecha_entrega' => '2026-12-01 23:59:00', ...$ts,
        ]);
        DB::table('avisos')->insert([
            'curso_id' => $cursoPelu, 'creado_por' => $profesor->id, 'titulo' => 'Materiales para la próxima clase',
            'mensaje' => 'Traer peine, tijera y capa.', ...$ts,
        ]);
        DB::table('eventos')->insert([
            'titulo' => 'Inicio de clases 2026', 'tipo' => 'institucional', 'fecha_inicio' => '2026-03-02 08:00:00',
            'publico' => true, 'creado_por' => $admin->id, ...$ts,
        ]);
        DB::table('noticias')->insert([
            'titulo' => 'Abrió la preinscripción 2026', 'slug' => 'abrio-la-preinscripcion-2026',
            'resumen' => 'Ya podés preinscribirte en línea a los cursos de Formación Profesional.',
            'cuerpo' => 'Noticia de prueba para el sitio público.', 'estado' => 'publicada', 'publicada_en' => $ahora,
            'autor_id' => $admin->id, ...$ts,
        ]);
    }
}
