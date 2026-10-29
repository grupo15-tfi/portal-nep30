<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Services\Sedes\Distancia;
use App\Services\Sedes\Geocodificador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HU03 (#48): datos de las sedes para el mapa y sedes más cercanas a un punto.
 * Responde JSON: lo usa la página del mapa de Arce (#49) con fetch().
 * Contrato completo en la issue madre #45.
 */
class SedesController extends Controller
{
    public const NO_ENCONTRADO = 'No encontramos ese domicilio. Probá con otra dirección o elegí tu localidad.';

    /** GET /sedes/datos → sedes.datos: sedes activas con sus cursos vigentes */
    public function datos(): JsonResponse
    {
        // Cursos vigentes agrupados por sede. La vista ya filtra cursos, planes, sedes y período activos.
        $cursos = DB::table('v_oferta_publica')
            ->select('curso_id', 'plan', 'turno', 'disponibles', 'sede_id')
            ->orderBy('plan')
            ->get()
            ->groupBy('sede_id');

        $sedes = Sede::where('activo', true)
            ->orderByRaw("FIELD(tipo, 'central', 'sede', 'anexo')")
            ->orderBy('nombre')
            ->get()
            ->map(fn (Sede $sede) => [
                'id'        => $sede->id,
                'nombre'    => $sede->nombre,
                'tipo'      => $sede->tipo,
                'direccion' => $sede->direccion,
                'localidad' => $sede->localidad,
                'telefono'  => $sede->telefono,
                'latitud'   => (float) $sede->latitud,
                'longitud'  => (float) $sede->longitud,
                'cursos'    => ($cursos[$sede->id] ?? collect())->map(fn ($c) => [
                    'curso_id'    => $c->curso_id,
                    'plan'        => $c->plan,
                    'turno'       => $c->turno,
                    'disponibles' => (int) $c->disponibles,
                ])->values(),
            ]);

        return response()->json($sedes);
    }

    /**
     * GET /sedes/localidades → sedes.localidades (HU03, #50)
     * Alternativa cuando no se encuentra el domicilio: el aspirante elige su localidad.
     * Una fila por localidad con sedes activas; el punto es el promedio de las coordenadas de sus sedes.
     */
    public function localidades(): JsonResponse
    {
        $localidades = DB::table('sedes')
            ->where('activo', true)
            ->selectRaw('localidad, AVG(latitud) AS lat, AVG(longitud) AS lng')
            ->groupBy('localidad')
            ->orderBy('localidad')
            ->get()
            ->map(fn ($fila) => [
                'localidad' => $fila->localidad,
                'lat'       => round((float) $fila->lat, 7),
                'lng'       => round((float) $fila->lng, 7),
            ]);

        return response()->json($localidades);
    }
    
    /** GET /sedes/cercanas?lat=..&lng=.. o ?domicilio=.. → sedes.cercanas */
    public function cercanas(Request $request, Geocodificador $geocodificador): JsonResponse
    {
        $datos = $request->validate([
            'lat'       => ['required_without:domicilio', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng'       => ['required_with:lat', 'numeric', 'between:-180,180'],
            'domicilio' => ['required_without:lat', 'string', 'max:200'],
        ]);

        // Punto de partida: la ubicación del celular o el domicilio convertido en coordenadas
        if (isset($datos['lat'])) {
            $origen = ['lat' => (float) $datos['lat'], 'lng' => (float) $datos['lng']];
        } else {
            $origen = $geocodificador->buscar($datos['domicilio']);

            if ($origen === null) {
                return response()->json(['message' => self::NO_ENCONTRADO], 422);
            }
        }

        $sedes = Sede::where('activo', true)
            ->get(['id', 'nombre', 'latitud', 'longitud'])
            ->map(fn (Sede $sede) => [
                'id'           => $sede->id,
                'nombre'       => $sede->nombre,
                'distancia_km' => round(Distancia::km(
                    $origen['lat'], $origen['lng'], (float) $sede->latitud, (float) $sede->longitud
                ), 1),
            ])
            ->sortBy('distancia_km')
            ->values();

        // No se guarda nada: el origen solo vuelve a quien lo pidió, para dibujar «Vos estás acá»
        return response()->json(['origen' => $origen, 'sedes' => $sedes]);
    }
}