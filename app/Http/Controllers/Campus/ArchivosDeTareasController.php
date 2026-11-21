<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Tarea;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HU12 (#119): descargas de la consigna de una tarea y de una entrega (disco privado «local»).
 * Contrato (#117):
 *   tareas.archivo   → el profesor de la asignación o un alumno activo del curso
 *   entregas.archivo → el profesor de la asignación o el alumno dueño (con su inscripción activa)
 * Cualquier otro: 403. Si no hay archivo: 404 (se controla después del permiso, para no revelar nada).
 */
class ArchivosDeTareasController extends Controller
{
    public function consigna(Tarea $tarea): StreamedResponse
    {
        $cp = $tarea->cursoProfesor;

        $puede = $cp->profesor_id === Auth::id()
            || Inscripcion::activas()->where('alumno_id', Auth::id())->where('curso_id', $cp->curso_id)->exists();

        abort_unless($puede, 403);

        return $this->descargar($tarea->archivo_ruta, "{$tarea->titulo} - consigna");
    }

    public function entrega(Entrega $entrega): StreamedResponse
    {
        $inscripcion = $entrega->inscripcion;

        $puede = $entrega->tarea->cursoProfesor->profesor_id === Auth::id()
            || ($inscripcion->alumno_id === Auth::id() && $inscripcion->estado === 'activa');

        abort_unless($puede, 403);

        $alumno = $inscripcion->alumno;

        return $this->descargar($entrega->archivo_ruta, "{$entrega->tarea->titulo} - {$alumno->apellido} {$alumno->nombre}");
    }

    /** «TP 1 - Prueba Alumna Uno.pdf», sin los caracteres que no puede llevar un nombre de archivo */
    private function descargar(?string $ruta, string $nombre): StreamedResponse
    {
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        $limpio = trim(preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $nombre));
        $limpio = preg_replace('/\s+/', ' ', $limpio);

        return Storage::disk('local')->download($ruta, $limpio.'.'.pathinfo($ruta, PATHINFO_EXTENSION));
    }
}