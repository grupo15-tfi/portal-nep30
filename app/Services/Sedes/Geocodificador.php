<?php

namespace App\Services\Sedes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * HU03 (#48): convierte un domicilio escrito en coordenadas, con Nominatim (OpenStreetMap).
 *
 * Reglas del servicio gratuito de Nominatim que respetamos:
 *   - un User-Agent propio que identifica al portal;
 *   - como máximo 1 pedido por segundo;
 *   - guardar las respuestas (caché) para no repetir la misma búsqueda.
 *
 * Qué se guarda en la caché:
 *   - Nominatim encontró el domicilio: las coordenadas, por 30 días.
 *   - Nominatim respondió bien pero sin resultados: false («no encontrado»), por 1 día.
 *   - Nominatim no respondió o respondió con error: nada. Es un problema temporal (sin internet,
 *     certificados SSL, servicio caído) y el próximo pedido vuelve a preguntar. Queda un aviso en el log.
 *
 * Privacidad (Ley 25.326): el domicilio no se guarda en la base ni en los logs. En la caché la
 * clave es un hash del texto (no se puede leer el domicilio) y el valor son solo las coordenadas.
 */
class Geocodificador
{
    private const DIAS_ENCONTRADO = 30;
    private const DIAS_NO_ENCONTRADO = 1;

    /** @return array{lat: float, lng: float}|null  null = no se encontró el domicilio */
    public function buscar(string $domicilio): ?array
    {
        $texto = $this->normalizar($domicilio);
        $clave = 'geo:'.sha1($texto);

        // En la caché: coordenadas, o false = «buscado y no encontrado». null = no está en la caché.
        $guardado = Cache::get($clave);

        if ($guardado !== null) {
            return $guardado ?: null;
        }

        $resultado = $this->consultarNominatim($texto);

        if ($resultado === null) {
            return null; // error de Nominatim: no se guarda, se vuelve a preguntar la próxima vez
        }

        $dias = $resultado === false ? self::DIAS_NO_ENCONTRADO : self::DIAS_ENCONTRADO;
        Cache::put($clave, $resultado, now()->addDays($dias));

        return $resultado ?: null;
    }

    /** Minúsculas y espacios simples: «Av. 25 de Mayo  1200» y «av. 25 de mayo 1200» usan la misma caché */
    private function normalizar(string $domicilio): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $domicilio)));
    }

    /**
     * @return array{lat: float, lng: float}|false|null
     *         coordenadas · false = respondió sin resultados · null = no respondió o respondió con error
     */
    private function consultarNominatim(string $texto): array|false|null
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
        } catch (ConnectionException $e) {
            // El mensaje de cURL termina con « for <url>», y la URL lleva el domicilio: se corta ahí
            Log::warning('Nominatim no respondió', ['error' => Str::before($e->getMessage(), ' for ')]);

            return null;
        }

        if (! $respuesta->successful()) {
            Log::warning('Nominatim no respondió correctamente', ['estado' => $respuesta->status()]);

            return null;
        }

        $primero = $respuesta->json()[0] ?? null;

        if (! isset($primero['lat'], $primero['lon'])) {
            return false;
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
