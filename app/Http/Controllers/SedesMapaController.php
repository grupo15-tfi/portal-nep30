<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;

/**
 * HU03 (#49): página pública del mapa de sedes (GET /sedes → sedes.mapa).
 *
 * La lista de sedes se arma con los mismos datos que devuelve sedes.datos (#48), así se ve
 * aunque el navegador no cargue JavaScript. El mapa, la ubicación y la búsqueda por domicilio
 * los maneja resources/js/mapa-sedes.js con fetch() contra sedes.datos y sedes.cercanas.
 * La ubicación y el domicilio no se guardan (Ley 25.326): solo viajan en el pedido.
 */
class SedesMapaController extends Controller
{
    public function __invoke(SedesController $datos)
    {
        return view('publico.sedes.mapa', [
            // Misma forma que el JSON del contrato (#45): id, nombre, tipo, direccion, localidad, telefono, latitud, longitud, cursos
            'sedes'          => json_decode($datos->datos()->getContent()),
            // sedes.localidades es de Castillo (#50): mientras no exista, la búsqueda funciona solo por domicilio
            'urlLocalidades' => Route::has('sedes.localidades') ? route('sedes.localidades') : null,
        ]);
    }

    /** «Sede central», «Sede» o «Anexo» */
    public static function tipo(string $tipo): string
    {
        return match ($tipo) {
            'central' => 'Sede central',
            'anexo'   => 'Anexo',
            default   => 'Sede',
        };
    }
}
