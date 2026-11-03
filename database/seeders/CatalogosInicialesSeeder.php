<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 * Catálogos iniciales de SIDEANFECA:
 *   1. Zonas regionales (1 a 7).
 *   2. Entidades federativas (32), con su zona regional.
 *   3. Coordinaciones nacionales (14), en el orden oficial.
 *
 * Es seguro correrlo varias veces: actualiza en lugar de duplicar.
 * Las zonas de cada entidad se pueden cambiar después desde la pantalla.
 * Los cargos NO se cargan aquí: se capturan desde la pantalla.
 */
class CatalogosInicialesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->cargarZonas();
            $this->cargarEntidades();
            $this->cargarCoordinaciones();
        });
    }

    private function cargarZonas(): void
    {
        $zonas = [
            1 => 'Noroeste',
            2 => 'Norte',
            3 => 'Centro',
            4 => 'Centro Occidente',
            5 => 'Centro Sur',
            6 => 'Sur',
            7 => 'Ciudad de México',
        ];

        foreach ($zonas as $numero => $nombre) {
            DB::table('zona_regional')->updateOrInsert(
                ['zore_numero' => $numero],
                ['zore_nombre' => $nombre]
            );
        }
    }

    private function cargarEntidades(): void
    {
        // [clave INEGI, nombre, número de zona regional]
        $entidades = [
            ['02', 'Baja California', 1],
            ['03', 'Baja California Sur', 1],
            ['08', 'Chihuahua', 1],
            ['25', 'Sinaloa', 1],
            ['26', 'Sonora', 1],

            ['05', 'Coahuila', 2],
            ['19', 'Nuevo León', 2],
            ['28', 'Tamaulipas', 2],

            ['01', 'Aguascalientes', 3],
            ['10', 'Durango', 3],
            ['22', 'Querétaro', 3],
            ['24', 'San Luis Potosí', 3],
            ['32', 'Zacatecas', 3],

            ['06', 'Colima', 4],
            ['11', 'Guanajuato', 4],
            ['14', 'Jalisco', 4],
            ['16', 'Michoacán', 4],
            ['18', 'Nayarit', 4],

            ['12', 'Guerrero', 5],
            ['13', 'Hidalgo', 5],
            ['15', 'Estado de México', 5],
            ['17', 'Morelos', 5],
            ['21', 'Puebla', 5],
            ['29', 'Tlaxcala', 5],

            ['04', 'Campeche', 6],
            ['07', 'Chiapas', 6],
            ['20', 'Oaxaca', 6],
            ['23', 'Quintana Roo', 6],
            ['27', 'Tabasco', 6],
            ['30', 'Veracruz', 6],
            ['31', 'Yucatán', 6],

            ['09', 'Ciudad de México', 7],
        ];

        // Mapa: número de zona => id interno de la zona
        $zonaIds = DB::table('zona_regional')->pluck('zore_id_zona_regional', 'zore_numero');

        foreach ($entidades as [$clave, $nombre, $zona]) {
            DB::table('entidad_federativa')->updateOrInsert(
                ['enfe_clave' => $clave],
                [
                    'enfe_nombre' => $nombre,
                    'enfe_id_zona_regional' => $zonaIds[$zona],
                ]
            );
        }
    }

    private function cargarCoordinaciones(): void
    {
        // El orden de esta lista es el orden de visualización oficial
        $coordinaciones = [
            'Certificación Académica',
            'Academia ANFECA',
            'Emprendimiento Social',
            'Planes y Programas de Estudio',
            'Investigación',
            'Posgrado',
            'Maratones',
            'Historia',
            'Vinculación Nacional e Internacional',
            'Universidad-Empresa',
            'Formación Profesional Académica',
            'Responsabilidad Social Universitaria',
            'Igualdad de Género',
            'Desarrollo Académico Estudiantil',
        ];

        foreach ($coordinaciones as $indice => $nombre) {
            DB::table('coordinacion_nacional')->updateOrInsert(
                ['cona_nombre' => $nombre],
                [
                    'cona_orden' => $indice + 1,
                    'cona_activo' => true,
                ]
            );
        }
    }
}
