<?php

namespace Tests\Feature\Directorios;

use Database\Seeders\CatalogosInicialesSeeder;
use Database\Seeders\PuestosDirectoriosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seeder de los puestos de los directorios: crea los puestos de los tres
 * directorios en el orden oficial, sin el Secretario Técnico Regional, y se
 * puede correr otra vez sin duplicar nada.
 */
class PuestosDirectoriosSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
    }

    public function test_crea_los_puestos_de_los_tres_directorios_en_el_orden_oficial(): void
    {
        $this->seed(PuestosDirectoriosSeeder::class);

        // 4 del consejo + 7 directores + 7 secretarios regionales + 14 coordinadores
        // nacionales + secretario técnico general + ALAFEC + 14 × 7 coordinadores regionales
        $this->assertSame(132, DB::table('puesto')->count());

        $nacional = $this->directorio('Consejo Nacional Directivo');
        $this->assertCount(27, $nacional);
        $this->assertSame(['Presidente', 'Vicepresidente', 'Secretario General', 'Director Ejecutivo', 'Director Regional · Zona 1'], array_slice($nacional, 0, 5));
        $this->assertSame('Coordinador Nacional · Certificación Académica', $nacional[11]);
        $this->assertSame(['Secretario Técnico General · Maratones', 'Representante de ANFECA ante ALAFEC'], array_slice($nacional, -2));

        $regionales = $this->directorio('Consejos Regionales');
        $this->assertCount(112, $regionales);
        $this->assertSame(['Director Regional · Zona 1', 'Secretario Regional · Zona 1', 'Coordinador Regional · Zona 1 · Certificación Académica'], array_slice($regionales, 0, 3));
        $this->assertSame('Director Regional · Zona 2', $regionales[16]);

        $coordinaciones = $this->directorio('Coordinaciones Nacionales');
        $this->assertCount(113, $coordinaciones);
        $this->assertSame(['Coordinador Nacional · Certificación Académica', 'Coordinador Regional · Zona 1 · Certificación Académica'], array_slice($coordinaciones, 0, 2));
        // El Secretario Técnico General va después del Coordinador Nacional de Maratones (es su secretario)
        $maratones = array_search('Coordinador Nacional · Maratones', $coordinaciones, true);
        $this->assertSame('Secretario Técnico General · Maratones', $coordinaciones[$maratones + 1]);
        $this->assertSame('Coordinador Regional · Zona 7 · Desarrollo Académico Estudiantil', end($coordinaciones));

        // El Secretario Técnico Regional ya no existe: ni cargo ni puestos
        $this->assertDatabaseMissing('cargo', ['carg_nombre_m' => 'Secretario Técnico']);
    }

    public function test_es_repetible_y_respeta_los_puestos_que_agrego_el_usuario(): void
    {
        $this->seed(PuestosDirectoriosSeeder::class);

        // El usuario agregó un puesto al final del Consejo Nacional y movió el Presidente
        $cnd = (int) DB::table('tipo_directorio')->where('tidi_nombre', 'Consejo Nacional Directivo')->value('tidi_id_tipo_directorio');
        $cargo = (int) DB::table('cargo')->where('carg_nombre_m', 'Director General CACECA')->value('carg_id_cargo');
        $propio = (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $cargo], 'pues_id_puesto');
        DB::table('puesto_directorio')->insert(['pudi_id_puesto' => $propio, 'pudi_id_tipo_directorio' => $cnd, 'pudi_orden' => 28]);

        $this->seed(PuestosDirectoriosSeeder::class);

        $this->assertSame(133, DB::table('puesto')->count());
        $nacional = $this->directorio('Consejo Nacional Directivo');
        $this->assertCount(28, $nacional);
        $this->assertSame('Presidente', $nacional[0]);
        $this->assertSame('Director General CACECA', end($nacional));
        $this->assertSame(range(1, 28), DB::table('puesto_directorio')->where('pudi_id_tipo_directorio', $cnd)->orderBy('pudi_orden')->pluck('pudi_orden')->map(fn ($o) => (int) $o)->all());
    }

    /**
     * Los puestos del directorio, en orden, como «Cargo · Zona N · Coordinación».
     *
     * @return array<int, string>
     */
    private function directorio(string $nombre): array
    {
        return DB::table('puesto_directorio')
            ->join('tipo_directorio', 'tidi_id_tipo_directorio', '=', 'pudi_id_tipo_directorio')
            ->join('puesto', 'pues_id_puesto', '=', 'pudi_id_puesto')
            ->join('cargo', 'carg_id_cargo', '=', 'pues_id_cargo')
            ->leftJoin('zona_regional', 'zore_id_zona_regional', '=', 'pues_id_zona_regional')
            ->leftJoin('coordinacion_nacional', 'cona_id_coordinacion_nacional', '=', 'pues_id_coordinacion_nacional')
            ->where('tidi_nombre', $nombre)
            ->orderBy('pudi_orden')
            ->get(['carg_nombre_m', 'zore_numero', 'cona_nombre'])
            ->map(fn (object $p) => implode(' · ', array_filter([$p->carg_nombre_m, $p->zore_numero ? "Zona {$p->zore_numero}" : null, $p->cona_nombre])))
            ->all();
    }
}
