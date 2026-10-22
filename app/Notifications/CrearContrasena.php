<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * HU06 (#15): correo de alta. Lo usa HU08 (#14) al crear un profesor:
 * $profesor->enviarEnlaceCrearContrasena(). El usuario elige su propia contraseña;
 * nadie más la conoce (la que se generó al crearlo es aleatoria).
 */
class CrearContrasena extends Notification implements ShouldQueue
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
            ->subject('Creá tu contraseña · Campus N.E.P. y F.P. N.º 30')
            ->greeting("Hola, {$notifiable->nombre}:")
            ->line('Te dimos de alta en el campus del N.E.P. y F.P. N.º 30. Para entrar, primero creá tu contraseña.')
            ->action('Crear mi contraseña', $url)
            ->line('Tu usuario es este correo. El enlace vence en 60 minutos.')
            ->line('Si se vence, entrá a la pantalla de ingreso, tocá «¿Olvidaste tu contraseña?» y pedí un enlace nuevo con este mismo correo.')
            ->salutation('Saludos, N.E.P. y F.P. N.º 30');
    }
}