<?php

namespace App\Notifications;

use App\Livewire\Publico\Preinscripcion as Formulario;
use App\Models\Preinscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * HU04 (#53): correo de acuse de recibo al aspirante.
 * No hay usuario: se envía a la dirección de la preinscripción con Notification::route('mail', ...).
 * Ley 25.326: solo nombre, curso, sede, turno, estado y código. Nunca DNI, teléfono, domicilio ni fecha de nacimiento.
 */
class AcusePreinscripcion extends Notification implements ShouldQueue
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
        $curso = $pre->curso;

        $correo = (new MailMessage)
            ->subject('Recibimos tu preinscripción · N.E.P. y F.P. N.º 30')
            ->greeting("Hola, {$pre->nombre}:")
            ->line("Recibimos tu preinscripción a **{$curso->planEstudio->nombre}**.")
            ->line("Sede: {$curso->sede->nombre} · Turno: ".ucfirst($curso->turno).'.')
            ->line('Tu código de seguimiento es **'.Formulario::codigo($pre).'**.');

        if ($pre->enEspera()) {
            $correo->line('Por ahora el curso no tiene cupo, así que **quedaste en la lista de espera**, por orden de llegada. '
                .'Si se libera un lugar, la escuela te va a avisar por teléfono.');
        } else {
            $correo->line('Tu preinscripción quedó **pendiente de revisión**. La escuela se va a comunicar con vos para completar la inscripción.');
        }

        return $correo
            ->line('Si no hiciste esta preinscripción, ignorá este correo.')
            ->salutation('Saludos, N.E.P. y F.P. N.º 30');
    }
}