<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Textos de la oferta pública (HU02) a partir de una fila de v_oferta_publica.
 * Los usan la tarjeta del curso y la página de detalle.
 */
class OfertaTexto
{
    public static function tipo(object $curso): string
    {
        return $curso->tipo === 'terminalidad' ? 'Terminalidad primaria' : 'Formación profesional';
    }

    /** «Turno tarde · Lunes a viernes · 14:00 a 18:00» (omite lo que no esté cargado) */
    public static function cursada(object $curso): string
    {
        $partes = ['Turno '.$curso->turno];

        if ($curso->dias) {
            $partes[] = $curso->dias;
        }
        if ($curso->hora_inicio && $curso->hora_fin) {
            $partes[] = substr($curso->hora_inicio, 0, 5).' a '.substr($curso->hora_fin, 0, 5);
        }

        return implode(' · ', $partes);
    }

    public static function cupos(object $curso): string
    {
        if ($curso->con_lista_espera) {
            return 'Sin cupo: Lista de espera';
        }

        return $curso->disponibles == 1 ? '1 cupo disponible' : "{$curso->disponibles} cupos disponibles";
    }

    /** Enlace a la preinscripción de HU04 (#52), o null mientras esa ruta no exista */
    public static function urlPreinscripcion(object $curso): ?string
    {
        return Route::has('preinscripcion.crear') ? route('preinscripcion.crear', $curso->curso_id) : null;
    }
}
