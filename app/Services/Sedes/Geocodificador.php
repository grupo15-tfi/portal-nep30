<?php

namespace App\Services\Sedes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * HU03 (#48): convierte un domicilio escrito en coordenadas, con Nominatim (OpenStreetMap).
 *
 * Reglas del servicio gratuito de Nominatim que respetamos:
 *   - un User-Agent propio que identifica al portal;
 *   - como máximo 1 pedido por segundo;
 *   - guardar las respuestas (caché) para no repetir la misma búsqueda.
 *
 * Privacidad (Ley 25.326): el domicilio no se guarda en la base ni en los logs. En la caché la
 * clave es un hash del texto (no se puede leer el domicilio) y el valor son solo las coordenadas.
 */
class Geocodificador
{
    private const DIAS_CACHE = 30;

    /** @return array{lat: float, lng: float}|null  null = no se encontró el domicilio */
    public function buscar(string $domicilio): ?array
    {
        $texto = $this->normalizar($domicilio);
        $clave = 'geo:'.sha1($texto);

        // false = «buscado y no encontrado»: también se guarda, para no volver a preguntar lo mismo.
        // (null no sirve como marca: Cache::remember lo toma como «no está en caché»)
        $resultado = Cache::remember(
            $clave,
            now()->addDays(self::DIAS_CACHE),
            fn () => $this->consultarNominatim($texto) ?? false,
        );

        return $resultado ?: null;
    }

    /** Minúsculas y espacios simples: «Av. 25 de Mayo  1200» y «av. 25 de mayo 1200» usan la misma caché */
    private function normalizar(string $domicilio): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $domicilio)));
    }

    private function consultarNominatim(string $texto): ?array
    {
        $this->esperarTurno();

        try {
            $respuesta = Http::timeout(5)
                ->withHeaders(['User-Agent' => config('services.nominatim.user_agent')])
                ->get(config('services.nominatim.url'), [
                    'q'            => $texto.', Formosa, Argentina',
                    'format'       => 'jsonv2',
                    'limit'        => 1,
                    'countrycodes' => 'ar',
                ]);
        } catch (ConnectionException) {
            return null; // Nominatim no respondió: se trata como «no encontrado»
        }

        $primero = $respuesta->successful() ? ($respuesta->json()[0] ?? null) : null;

        if (! isset($primero['lat'], $primero['lon'])) {
            return null;
        }

        return ['lat' => (float) $primero['lat'], 'lng' => (float) $primero['lon']];
    }

    /** Máximo 1 pedido por segundo a Nominatim, para todo el portal */
    private function esperarTurno(): void
    {
        if (RateLimiter::tooManyAttempts('nominatim', 1)) {
            Sleep::for(RateLimiter::availableIn('nominatim'))->seconds();
        }

        RateLimiter::hit('nominatim', 1);
    }
}