<?php

namespace App\Livewire\Auditoria;

use App\Queries\RegistroDeAuditoria;
use App\Support\NombresDeAuditoria;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * HU16 (#123): pantalla «Auditoría». Muestra el registro de la tabla `auditoria` con filtros y,
 * en una ventana emergente, el detalle de cada cambio (campo, antes y después).
 * La usan el Administrador (admin.auditoria) y el Auditor (auditor.auditoria, HU18): es la misma
 * pantalla, solo cambia la ruta. Solo lectura: no hay botones para crear, editar ni borrar.
 * Los datos salen de RegistroDeAuditoria::buscar() (#122); acá no se consulta la base por otro lado.
 */
class Registro extends Component
{
    use WithPagination;

    /** Filtros (mismos nombres que los de RegistroDeAuditoria::buscar) */
    public string $tabla = '';

    public string $accion = '';

    public string $usuario = '';

    public string $desde = '';

    public string $hasta = '';

    /** Renglón abierto en la ventana «Ver detalle» */
    public ?int $detalleId = null;

    /** Al cambiar un filtro, volver a la primera página */
    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['tabla', 'accion', 'usuario', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    public function limpiar(): void
    {
        $this->reset('tabla', 'accion', 'usuario', 'desde', 'hasta');
        $this->resetPage();
    }

    public function verDetalle(int $id): void
    {
        $this->detalleId = $id;
    }

    public function cerrar(): void
    {
        $this->detalleId = null;
    }

    public function hayFiltros(): bool
    {
        return $this->tabla !== '' || $this->accion !== '' || trim($this->usuario) !== ''
            || $this->desde !== '' || $this->hasta !== '';
    }

    /** Nombre legible de cada campo que guardan los triggers (el que no está, se muestra tal cual) */
    public const CAMPOS = [
        'dni'                    => 'DNI',
        'apellido'               => 'Apellido',
        'nombre'                 => 'Nombre',
        'email'                  => 'Correo',
        'activo'                 => 'Activo',
        'deleted_at'             => 'Eliminado el',
        'password_cambiada'      => 'Cambió la contraseña',
        'rol'                    => 'Rol',
        'curso_id'               => 'Curso',
        'alumno_id'              => 'Alumno',
        'estado'                 => 'Estado',
        'observacion'            => 'Observación',
        'revisado_por'           => 'Revisado por',
        'avisado_en'             => 'Último aviso',
        'fecha_baja'             => 'Fecha de baja',
        'motivo_baja'            => 'Motivo de la baja',
        'inscripcion_materia_id' => 'Alumno y módulo',
        'inscripcion_id'         => 'Inscripción',
        'clase_id'               => 'Clase',
        'tipo'                   => 'Tipo de evaluación',
        'nota'                   => 'Nota',
        'nota_final'             => 'Nota final',
        'fecha'                  => 'Fecha',
        'fecha_acreditacion'     => 'Fecha de acreditación',
        'documentacion_en'       => 'Documentación registrada el',
        'documentacion_obs'      => 'Observación de la documentación',
    ];

    /** Campos que son sí/no aunque la base los guarde como 1/0 */
    private const SI_NO = ['activo', 'password_cambiada'];

    public static function campo(string $campo): string
    {
        return self::CAMPOS[$campo] ?? $campo;
    }

    /** Valor para mostrar: null → «—», sí/no, fechas en dd/mm/aaaa y estados con mayúscula */
    public static function valor(mixed $valor, string $campo = ''): string
    {
        if ($valor === null) {
            return '—';
        }
        if (is_bool($valor) || in_array($campo, self::SI_NO, true)) {
            return $valor ? 'Sí' : 'No';
        }
        if (is_array($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?(\.\d+)?)?$/', $valor)) {
            $fecha = Carbon::parse($valor);

            return strlen($valor) > 10 ? $fecha->format('d/m/Y H:i') : $fecha->format('d/m/Y');
        }
        if (in_array($campo, ['estado', 'tipo', 'rol'], true)) {
            return ucfirst(str_replace('_', ' ', (string) $valor));
        }

        return (string) $valor;
    }

    public function render()
    {
        $registros = RegistroDeAuditoria::buscar([
            'tabla'   => array_key_exists($this->tabla, RegistroDeAuditoria::TABLAS) ? $this->tabla : null,
            'accion'  => array_key_exists($this->accion, RegistroDeAuditoria::ACCIONES) ? $this->accion : null,
            'usuario' => $this->usuario,
            'desde'   => $this->desde ?: null,
            'hasta'   => $this->hasta ?: null,
        ]);

        // El detalle es de un renglón de la página que se está viendo
        $detalle = $this->detalleId ? collect($registros->items())->firstWhere('id', $this->detalleId) : null;
        if ($detalle) {
            $detalle['cambios'] = NombresDeAuditoria::deCambios($detalle['cambios']);   // números → nombres
        }

        return view('livewire.auditoria.registro', [
            'registros' => $registros,
            // «Sobre quién» es cada renglón, con nombres (una consulta por tabla, no por renglón)
            'sobre'     => NombresDeAuditoria::deRegistros($registros->items()),
            'detalle'   => $detalle,
        ]);
    }
}