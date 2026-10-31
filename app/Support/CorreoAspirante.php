<?php

namespace App\Support;

use App\Models\Preinscripcion;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notificaciones;
use Throwable;

/**
 * Correos al aspirante (no tiene usuario: va a la dirección de la preinscripción).
 * Si el envío falla, la acción ya se guardó: queda en el log, sin datos personales (#53).
 */
class CorreoAspirante
{
    public static function enviar(Preinscripcion $preinscripcion, Notification $correo): void
    {
        try {
            Notificaciones::route('mail', $preinscripcion->email)->notify($correo);
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el correo al aspirante', [
                'preinscripcion_id' => $preinscripcion->id,
                'correo'            => class_basename($correo),
                'error'             => $e::class,
            ]);
        }
    }
}