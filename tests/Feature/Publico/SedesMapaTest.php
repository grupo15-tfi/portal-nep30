<?php

namespace Tests\Feature\Publico;

use App\Http\Controllers\SedesController;
use App\Models\Sede;
use App\Services\Sedes\Distancia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * HU03 (#48): datos de sedes, distancias y geocodificación (RF04, RF05).
 * Datos de DatosDePruebaSeeder:
 *   - Sede Central (prueba): -26.1775, -58.1781 · Peluquería e Informática
 *   - Anexo Barrio Norte (prueba): -26.1600, -58.1900 · Alfabetización
 *   - Entre las dos hay unos 2,3 km en línea recta.
 * Nominatim nunca se llama de verdad: Http::fake() simula sus respuestas.
 */
class SedesMapaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const CENTRAL = ['lat' => -26.1775, 'lng' => -58.1781];
    private const ANEXO = ['lat' => -26.16, 'lng' => -58.19];

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();       // la espera de 1 segundo entre pedidos a Nominatim no demora las pruebas
        Http::preventStrayRequests(); // si algo intenta salir a internet, la prueba falla
    }

    /** Simula la respuesta de Nominatim: un resultado con esas coordenadas, o ninguno */
    private function nominatimResponde(?array $punto): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(
                $punto ? [['lat' => (string) $punto['lat'], 'lon' => (string) $punto['lng']]] : []
            ),
        ]);
    }

    // ---------- Distancia (Haversine) ----------

    public function test_la_distancia_entre_dos_puntos_conocidos_es_correcta(): void
    {
        // Un grado de latitud sobre un meridiano mide unos 111,2 km
        $this->assertEqualsWithDelta(111.2, Distancia::km(0, 0, 1, 0), 0.1);
        // Obelisco (Buenos Aires) a la plaza San Martín (Córdoba): unos 647 km en línea recta
        $this->assertEqualsWithDelta(646.7, Distancia::km(-34.6037, -58.3816, -31.4201, -64.1888), 0.5);
        // El mismo punto: 0
        $this->assertSame(0.0, Distancia::km(-26.1775, -58.1781, -26.1775, -58.1781));
    }

    // ---------- sedes.datos ----------

    public function test_los_datos_traen_las_sedes_activas_con_sus_cursos(): void
    {
        $this->getJson(route('sedes.datos'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.nombre', 'Sede Central (prueba)') // la central va primero
            ->assertJsonPath('0.latitud', -26.1775)
            ->assertJsonCount(2, '0.cursos')
            ->assertJsonPath('1.nombre', 'Anexo Barrio Norte (prueba)')
            ->assertJsonCount(1, '1.cursos')
            ->assertJsonStructure([['id', 'nombre', 'tipo', 'direccion', 'localidad', 'telefono', 'latitud', 'longitud',
                'cursos' => [['curso_id', 'plan', 'turno', 'disponibles']]]]);
    }

    public function test_una_sede_inactiva_no_aparece_en_los_datos(): void
    {
        Sede::where('nombre', 'Anexo Barrio Norte (prueba)')->update(['activo' => false]);

        $this->getJson(route('sedes.datos'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonMissing(['nombre' => 'Anexo Barrio Norte (prueba)']);
    }

    // ---------- sedes.cercanas con la ubicación del celular ----------

    public function test_las_sedes_cercanas_se_ordenan_por_distancia_con_un_decimal(): void
    {
        // Parado en el anexo: el anexo queda primero (0 km) y la central a unos 2,3 km
        $this->getJson(route('sedes.cercanas', self::ANEXO))
            ->assertOk()
            ->assertJsonPath('origen', self::ANEXO)
            ->assertJsonPath('sedes.0.nombre', 'Anexo Barrio Norte (prueba)')
            ->assertJsonPath('sedes.0.distancia_km', 0)
            ->assertJsonPath('sedes.1.nombre', 'Sede Central (prueba)')
            ->assertJsonPath('sedes.1.distancia_km', 2.3);
    }

    public function test_una_sede_inactiva_no_aparece_entre_las_cercanas(): void
    {
        Sede::where('nombre', 'Anexo Barrio Norte (prueba)')->update(['activo' => false]);

        $this->getJson(route('sedes.cercanas', self::ANEXO))
            ->assertOk()
            ->assertJsonCount(1, 'sedes')
            ->assertJsonPath('sedes.0.nombre', 'Sede Central (prueba)');
    }

    public function test_coordenadas_fuera_de_rango_o_sin_datos_dan_error(): void
    {
        $this->getJson(route('sedes.cercanas', ['lat' => 100, 'lng' => -58]))
            ->assertStatus(422)->assertJsonValidationErrors('lat');

        $this->getJson(route('sedes.cercanas', ['lat' => -26]))
            ->assertStatus(422)->assertJsonValidationErrors('lng');

        $this->getJson(route('sedes.cercanas'))
            ->assertStatus(422)->assertJsonValidationErrors(['lat', 'domicilio']);
    }

    // ---------- sedes.cercanas con un domicilio (Nominatim) ----------

    public function test_un_domicilio_encontrado_devuelve_las_sedes_mas_cercanas(): void
    {
        $this->nominatimResponde(self::CENTRAL);

        $this->getJson(route('sedes.cercanas', ['domicilio' => 'Av. 25 de Mayo 1200']))
            ->assertOk()
            ->assertJsonPath('sedes.0.nombre', 'Sede Central (prueba)');

        // Se pidió con la localidad agregada y con nuestro User-Agent
        Http::assertSent(fn (PedidoHttp $pedido) =>
            $pedido['q'] === 'av. 25 de mayo 1200, Formosa, Argentina'
            && $pedido->hasHeader('User-Agent', config('services.nominatim.user_agent')));
    }

    public function test_un_domicilio_no_encontrado_da_422_con_el_mensaje_del_contrato(): void
    {
        $this->nominatimResponde(null);

        $this->getJson(route('sedes.cercanas', ['domicilio' => 'Calle que no existe 99999']))
            ->assertStatus(422)
            ->assertExactJson(['message' => SedesController::NO_ENCONTRADO]);
    }

    public function test_el_mismo_domicilio_se_busca_una_sola_vez_gracias_a_la_cache(): void
    {
        $this->nominatimResponde(self::CENTRAL);

        $this->getJson(route('sedes.cercanas', ['domicilio' => 'Av. 25 de Mayo 1200']))->assertOk();
        $this->getJson(route('sedes.cercanas', ['domicilio' => '  av. 25 de mayo   1200 ']))->assertOk();

        Http::assertSentCount(1);
    }

    // ---------- Límite de pedidos ----------

    public function test_las_rutas_tienen_limite_de_pedidos(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson(route('sedes.datos'))->assertOk();
        }

        $this->getJson(route('sedes.datos'))->assertStatus(429);
    }
}