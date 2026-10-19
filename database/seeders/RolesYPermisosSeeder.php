<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Roles y permisos del modelo v4.4 (03_datos_base.sql, punto 1). */
class RolesYPermisosSeeder extends Seeder
{
    /** Permisos de cada rol. */
    public const ROLES = [
        'administrador' => [
            'gestionar sedes', 'gestionar cursos', 'gestionar profesores', 'gestionar alumnos',
            'gestionar preinscripciones',   // incluye la lista de espera
            'gestionar contenidos', 'gestionar calendario',
            'ver alumnos', 'ver preinscripciones', 'ver notas', 'ver asistencia', 'ver auditoria',
            'exportar planillas', 'descargar historiales',
        ],
        'profesor' => [
            'cargar notas', 'gestionar tareas', 'registrar asistencia', 'publicar avisos',
            'gestionar calendario curso', 'exportar planillas curso',
        ],
        'alumno' => [
            'ver mis notas', 'entregar tareas', 'ver mi asistencia', 'ver mi calendario', 'ver mi historial',
        ],
        // Solo consulta y exportación (HU18, RF35, RF36). La base también lo controla.
        'auditor' => [
            'ver alumnos', 'ver preinscripciones', 'ver notas', 'ver asistencia', 'ver auditoria',
            'exportar planillas', 'descargar historiales',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $rol => $permisos) {
            foreach ($permisos as $permiso) {
                Permission::findOrCreate($permiso, 'web');
            }
            Role::findOrCreate($rol, 'web')->syncPermissions($permisos);
        }
    }
}
