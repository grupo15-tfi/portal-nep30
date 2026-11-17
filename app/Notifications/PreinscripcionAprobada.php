<?php

namespace App\Notifications;

use App\Models\Preinscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * HU09 (#86): aviso al aspirante de que su preinscripción fue aprobada.
 * Ley 25.326: solo nombre, curso, sede y turno. Nunca DNI, teléfono ni domicilio.
 */
class PreinscripcionAprobada extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Preinscripcion $preinscripcion)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pre = $this->preinscripcion->loadMissing('curso.planEstudio', 'curso.sede');

        return (new MailMessage)
            ->subject('Tu preinscripción fue aprobada · N.E.P. y F.P. N.º 30')
            ->greeting("Hola, {$pre->nombre}:")
            ->line("Tu preinscripción a **{$pre->curso->planEstudio->nombre}** fue **aprobada**.")
            ->line("Sede: {$pre->curso->sede->nombre} · Turno: ".ucfirst($pre->curso->turno).'.')
            ->line('La escuela va a completar tu inscripción. Cuando esté lista, te va a llegar otro correo para crear tu contraseña del Campus Virtual.')
            ->salutation('Saludos, N.E.P. y F.P. N.º 30');
    }
}