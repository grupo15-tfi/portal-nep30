<?php

namespace App\Notifications;

use App\Models\Preinscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * HU09 (#86): aviso al aspirante de que su preinscripción no fue aceptada, con el motivo.
 */
class PreinscripcionRechazada extends Notification implements ShouldQueue
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
        $pre = $this->preinscripcion->loadMissing('curso.planEstudio');

        return (new MailMessage)
            ->subject('Sobre tu preinscripción · N.E.P. y F.P. N.º 30')
            ->greeting("Hola, {$pre->nombre}:")
            ->line("Revisamos tu preinscripción a **{$pre->curso->planEstudio->nombre}** y esta vez no pudo ser aceptada.")
            ->line("Motivo: {$pre->observacion}")
            ->line('Si tenés dudas, acercate a la escuela o volvé a preinscribirte en otro curso desde el sitio.')
            ->salutation('Saludos, N.E.P. y F.P. N.º 30');
    }
}