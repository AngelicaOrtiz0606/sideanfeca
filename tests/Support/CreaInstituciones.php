<?php

namespace Tests\Support;

use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Ayuda para que las pruebas creen instituciones válidas según el esquema:
 * domicilio (colonia, municipio, entidad) y zona para afiliadas y observadoras.
 * Solo se usa con DatabaseTransactions: todo se deshace al terminar.
 */
trait CreaInstituciones
{
    private ?int $idDomicilioDePrueba = null;

    private int $siguienteNumeroAfiliacion = 1000001;

    protected function crearInstitucion(string $participacion, bool $activa = true): int
    {
        $tipoUniversidad = DB::table('tipo_institucion_educativa')
            ->where('tiie_nombre', 'Universidad')
            ->value('tiie_id_tipo_institucion');

        $requiereDomicilio = $participacion !== 'Matriz';

        return DB::table('institucion_educativa')->insertGetId([
            'ined_id_tipo_institucion' => $tipoUniversidad,
            'ined_participacion' => $participacion,
            'ined_num_afiliacion' => $participacion === 'Afiliada' ? (string) $this->siguienteNumeroAfiliacion++ : null,
            'ined_sector' => 'Publica',
            'ined_id_domicilio' => $requiereDomicilio ? $this->domicilioDePrueba() : null,
            'ined_id_zona_regional' => $requiereDomicilio ? DB::table('zona_regional')->value('zore_id_zona_regional') : null,
            'ined_activo' => $activa,
        ], 'ined_id_institucion_educativa');
    }

    private function domicilioDePrueba(): int
    {
        if ($this->idDomicilioDePrueba !== null) {
            return $this->idDomicilioDePrueba;
        }

        if (! DB::table('entidad_federativa')->exists()) {
            $this->seed(CatalogosInicialesSeeder::class);
        }

        $idEntidad = DB::table('entidad_federativa')->where('enfe_clave', '09')->value('enfe_id_entidad_federativa');

        $idMunicipio = DB::table('alcaldia_municipio')->insertGetId([
            'almu_id_entidad_federativa' => $idEntidad,
            'almu_clave' => '003',
            'almu_nombre' => 'Coyoacán',
        ], 'almu_id_alcaldia_municipio');

        $idColonia = DB::table('colonia')->insertGetId([
            'colo_id_alcaldia_municipio' => $idMunicipio,
            'colo_codigo_postal' => '04510',
            'colo_id_asenta_cpcons' => '0001',
            'colo_nombre' => 'Ciudad Universitaria',
        ], 'colo_id_colonia');

        return $this->idDomicilioDePrueba = DB::table('domicilio')->insertGetId([
            'domi_id_colonia' => $idColonia,
            'domi_calle' => 'Circuito Exterior',
            'domi_num_ext' => 's/n',
        ], 'domi_id_domicilio');
    }
}
