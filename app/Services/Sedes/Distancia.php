<?php

namespace App\Services\Sedes;

/**
 * HU03 (#48): distancia en línea recta entre dos puntos del planeta (fórmula de Haversine).
 * Es aproximada: no sigue calles. Por eso en la página se aclara «en línea recta».
 */
class Distancia
{
    /** Radio medio de la Tierra en kilómetros */
    private const RADIO_KM = 6371.0;

    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Diferencias pasadas a radianes (las funciones trigonométricas de PHP trabajan en radianes)
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        // Haversine: cuánto «se abre» el ángulo entre los dos puntos visto desde el centro de la Tierra
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        // min(1, ...) evita un error de redondeo cuando los puntos son casi opuestos
        return 2 * self::RADIO_KM * asin(min(1.0, sqrt($a)));
    }
}