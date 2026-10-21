<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * HU06 (#15): correo con el enlace para restablecer la contraseña.
 * Sale por la cola (ShouldQueue): en la VPS lo procesa el worker de Supervisor.
 */
class RestablecerContrasena extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject('Restablecé tu contraseña · Campus N.E.P. y F.P. N.º 30')
            ->greeting("Hola, {$notifiable->nombre}:")
            ->line('Recibimos un pedido para restablecer la contraseña de tu cuenta del campus.')
            ->action('Restablecer contraseña', $url)
            ->line('El enlace vence en 60 minutos y sirve una sola vez.')
            ->line('Si no lo pediste, ignorá este correo: tu contraseña no cambia.')
            ->salutation('Saludos, N.E.P. y F.P. N.º 30');
    }
}