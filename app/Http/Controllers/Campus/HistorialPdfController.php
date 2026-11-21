<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use App\Queries\HistorialAcademico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * HU17 (#126): el historial académico en PDF (A4), con barryvdh/laravel-dompdf.
 * Contrato (#124):
 *   alumno.historial.pdf    → GET /alumno/historial/{inscripcion}/pdf  (solo la propia; si no, 403)
 *   admin.alumnos.historial → GET /admin/alumnos/{inscripcion}/historial (cualquier inscripción)
 *   Nombre del archivo: historial-{dni}-{plan}.pdf
 * Los datos salen de HistorialAcademico::de() (#125); acá no se calcula nada.
 */
class HistorialPdfController extends Controller
{
    public function alumno(Inscripcion $inscripcion): Response
    {
        abort_unless($inscripcion->alumno_id === Auth::id(), 403);

        return $this->descargar($inscripcion);
    }

    public function admin(Inscripcion $inscripcion): Response
    {
        return $this->descargar($inscripcion);
    }

    private function descargar(Inscripcion $inscripcion): Response
    {
        $h = HistorialAcademico::de($inscripcion);

        return Pdf::loadView('pdf.historial', ['h' => $h])
            ->setPaper('a4')
            ->download('historial-'.$h['alumno']['dni'].'-'.Str::slug($h['plan']).'.pdf');
    }
}