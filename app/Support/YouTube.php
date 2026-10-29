<?php

namespace App\Support;

/**
 * HU10 (#74): enlaces de YouTube. Solo se aceptan videos de YouTube (pedido de la institución:
 * el video queda alojado en YouTube y no usa espacio del hosting).
 * Se muestra con youtube-nocookie.com: YouTube no guarda cookies hasta que se reproduce.
 */
class YouTube
{
    /** El id de 11 caracteres del video, o null si el enlace no es de YouTube */
    public static function id(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $patron = '~^(?:https?://)?(?:www\.|m\.)?'
            .'(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)'
            .'([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~';

        return preg_match($patron, trim($url), $partes) ? $partes[1] : null;
    }

    /** ¿Es un enlace válido de un video de YouTube? */
    public static function esValido(?string $url): bool
    {
        return self::id($url) !== null;
    }

    /** Dirección para el reproductor (iframe), o null si no hay video */
    public static function embed(?string $url): ?string
    {
        $id = self::id($url);

        return $id ? "https://www.youtube-nocookie.com/embed/{$id}" : null;
    }
}