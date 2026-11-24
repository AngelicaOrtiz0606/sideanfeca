<?php

namespace Database\Seeders;

use App\Support\TextoCatalogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 * Organizaciones base (tabla ORGANIZACION): CACECA, con su domicilio.
 *
 * Es repetible: si la organización ya existe (sin importar mayúsculas,
 * acentos ni espacios), no la duplica ni la cambia.
 *
 * El domicilio usa la colonia del catálogo de Correos. Si ese catálogo
 * todavía no se cargó, avisa y no registra la organización (se puede correr
 * otra vez después de: php artisan catalogos:importar-correos).
 *
 * Uso: php artisan db:seed --class=OrganizacionesSeeder
 */
class OrganizacionesSeeder extends Seeder
{
    private const ORGANIZACIONES = [
        [
            'nombre' => 'CACECA',
            'calle' => 'Blvd. Manuel Ávila Camacho',
            'num_ext' => '5',
            'num_int' => 'Torre B, Piso 10, Suite 1000',
            'colonia' => 'Lomas de Sotelo',
            'codigo_postal' => '53390',
            'municipio' => 'Naucalpan de Juárez',
        ],
    ];

    public function run(): void
    {
        $existentes = DB::table('organizacion')->pluck('orga_nombre')->map(fn (string $n) => TextoCatalogo::clave($n))->flip();

        foreach (self::ORGANIZACIONES as $organizacion) {
            if ($existentes->has(TextoCatalogo::clave($organizacion['nombre']))) {
                continue;
            }

            $colonia = DB::table('colonia as c')
                ->join('alcaldia_municipio as m', 'm.almu_id_alcaldia_municipio', '=', 'c.colo_id_alcaldia_municipio')
                ->where('c.colo_codigo_postal', $organizacion['codigo_postal'])
                ->get(['c.colo_id_colonia as id', 'c.colo_nombre as colonia', 'm.almu_nombre as municipio'])
                ->first(fn (object $c) => TextoCatalogo::clave($c->colonia) === TextoCatalogo::clave($organizacion['colonia'])
                    && TextoCatalogo::clave($c->municipio) === TextoCatalogo::clave($organizacion['municipio']))
                ?->id;

            if ($colonia === null) {
                $this->command?->warn("No se registró {$organizacion['nombre']}: falta la colonia {$organizacion['colonia']} (C.P. {$organizacion['codigo_postal']}). Carga primero el catálogo de Correos (php artisan catalogos:importar-correos) y vuelve a correr este seeder.");

                continue;
            }

            DB::transaction(function () use ($organizacion, $colonia): void {
                $domicilio = DB::table('domicilio')->insertGetId([
                    'domi_id_colonia' => $colonia,
                    'domi_calle' => $organizacion['calle'],
                    'domi_num_ext' => $organizacion['num_ext'],
                    'domi_num_int' => $organizacion['num_int'],
                ], 'domi_id_domicilio');

                DB::table('organizacion')->insert([
                    'orga_nombre' => $organizacion['nombre'],
                    'orga_id_domicilio' => $domicilio,
                    'orga_activo' => true,
                ]);
            });
        }
    }
}
