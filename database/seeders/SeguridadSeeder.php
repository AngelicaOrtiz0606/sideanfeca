<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeguridadSeeder extends Seeder
{
    public function run(): void
    {
        // Privilegios del sistema: clave => descripción
        $privilegios = [
            'ver_directorios'          => 'Consultar directorios',
            'gestionar_personas'       => 'Personas, contactos y grados asignados',
            'gestionar_instituciones'  => 'Instituciones y su participación',
            'gestionar_puestos'        => 'Crear y modificar puestos',
            'gestionar_designaciones'  => 'Asignar y quitar personas de puestos',
            'gestionar_directorios'    => 'Marcar puestos en directorios y ordenarlos',
            'gestionar_zonas'          => 'Zonas regionales y la zona de cada entidad',
            'gestionar_cargos'         => 'Catálogo de cargos',
            'gestionar_coordinaciones' => 'Coordinaciones nacionales',
            'gestionar_grados'         => 'Grados académicos',
            'ver_bitacora'             => 'Consultar la bitácora',
            'gestionar_usuarios'       => 'Crear usuarios, asignar rol y desactivarlos',
            'eliminar_definitivo'      => 'Borrado definitivo de datos de una persona (solo superadministrador)',
        ];

        foreach ($privilegios as $nombre => $descripcion) {
            DB::table('privilegio')->updateOrInsert(
                ['priv_nombre' => $nombre],
                ['priv_descripcion' => $descripcion]
            );
        }

        // Solo dos roles. El superadministrador tiene todo.
        // El administrador tiene todo menos bitácora, usuarios y borrado definitivo.
        $sinAdmin = array_values(array_diff(
            array_keys($privilegios),
            ['ver_bitacora', 'gestionar_usuarios', 'eliminar_definitivo']
        ));

        $roles = [
            'Superadministrador' => [
                'descripcion' => 'Acceso total: administra usuarios y consulta la bitácora',
                'privilegios' => array_keys($privilegios),
            ],
            'Administrador' => [
                'descripcion' => 'Gestiona todo el contenido, sin usuarios ni bitácora',
                'privilegios' => $sinAdmin,
            ],
        ];

        foreach ($roles as $nombre => $datos) {
            DB::table('rol')->updateOrInsert(
                ['rol_nombre' => $nombre],
                ['rol_descripcion' => $datos['descripcion'], 'rol_activo' => true]
            );

            $rolId = DB::table('rol')->where('rol_nombre', $nombre)->value('rol_id_rol');

            foreach ($datos['privilegios'] as $privNombre) {
                $privId = DB::table('privilegio')
                    ->where('priv_nombre', $privNombre)
                    ->value('priv_id_privilegio');

                DB::table('rol_privilegio')->updateOrInsert(
                    ['ropr_id_rol' => $rolId, 'ropr_id_privilegio' => $privId],
                    ['ropr_activo' => true]
                );
            }
        }
    }
}
