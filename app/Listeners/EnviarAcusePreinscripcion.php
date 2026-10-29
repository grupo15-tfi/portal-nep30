<?php

namespace App\Listeners;

use App\Events\PreinscripcionRecibida;
use App\Notifications\AcusePreinscripcion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * HU04 (#53): cuando llega una preinscripción (evento de #51), le manda el acuse al aspirante.
 * Laravel lo conecta solo con el evento por el tipo del parámetro de handle().
 *
 * Si el correo falla, la preinscripción ya está guardada: el error queda en el log y el
 * aspirante igual ve su confirmación. En el log no va el correo ni ningún dato personal.
 */
class EnviarAcusePreinscripcion
{
    public function handle(PreinscripcionRecibida $evento): void
    {
        $pre = $evento->preinscripcion;

        try {
            Notification::route('mail', $pre->email)->notify(new AcusePreinscripcion($pre));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el acuse de preinscripción', [
                'preinscripcion_id' => $pre->id,
                'error'             => $e::class,
            ]);
        }
    }
}