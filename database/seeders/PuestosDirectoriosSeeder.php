<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/*
 * Puestos de los directorios de ANFECA (tabla PUESTO) y su lugar en cada
 * directorio (tabla PUESTO_DIRECTORIO), tomados de los directorios que hoy
 * publica el sitio de ANFECA:
 *
 *   Consejo Nacional Directivo: Presidente, Vicepresidente, Secretario
 *     General, Director Ejecutivo, los 7 directores regionales, los
 *     coordinadores nacionales (uno por coordinación), el Secretario Técnico
 *     General de Maratones y el Representante de ANFECA ante ALAFEC.
 *   Consejos Regionales (por zona): Director Regional, Secretario Regional y
 *     un Coordinador Regional por coordinación.
 *   Coordinaciones Nacionales (por coordinación): Coordinador Nacional y los
 *     7 coordinadores regionales de esa coordinación. En Maratones, después
 *     del Coordinador Nacional va el Secretario Técnico General (su secretario).
 *
 * También crea el puesto Director General CACECA (nacional), que no va en
 * ningún directorio: quien lo ocupa pertenece a la organización CACECA.
 *
 * El Secretario Técnico Regional de cada coordinación ya no existe: no se crea.
 * Solo crea puestos (vacantes); las personas se designan desde la pantalla.
 *
 * Es repetible: no duplica puestos que ya existen. En cada directorio deja
 * primero estos puestos en el orden oficial y después, en el orden que ya
 * tenían, los que el usuario haya agregado.
 *
 * Requiere zonas y coordinaciones (CatalogosInicialesSeeder). Los cargos que
 * falten los agrega CargosBaseSeeder.
 *
 * Uso: php artisan db:seed --class=PuestosDirectoriosSeeder
 */
class PuestosDirectoriosSeeder extends Seeder
{
    /** Coordinaciones, en el orden oficial (como en el catálogo). */
    private const COORDINACIONES = [
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
        'Formación Profesional y Académica',
        'Responsabilidad Social Universitaria',
        'Igualdad de Género',
        'Desarrollo Académico Estudiantil',
    ];

    /** La coordinación del Secretario Técnico General. */
    private const COORDINACION_DEL_SECRETARIO_TECNICO = 'Maratones';

    /** @var array<string, int> nombre en masculino => id del cargo */
    private array $cargos = [];

    /** @var Collection<int, int> número de zona => id de la zona */
    private Collection $zonas;

    /** @var Collection<string, int> nombre => id de la coordinación */
    private Collection $coordinaciones;

    public function run(): void
    {
        $this->call(CargosBaseSeeder::class);

        DB::transaction(function () {
            $this->cargarCatalogos();

            $this->ordenar('Consejo Nacional Directivo', $this->consejoNacional());
            $this->ordenar('Consejos Regionales', $this->consejosRegionales());
            $this->ordenar('Coordinaciones Nacionales', $this->coordinacionesNacionales());

            // Puestos que no van en ningún directorio publicado
            $this->puesto('Director General CACECA');
        });
    }

    /** @return array<int, int> ids de puesto en el orden oficial */
    private function consejoNacional(): array
    {
        $puestos = [
            $this->puesto('Presidente'),
            $this->puesto('Vicepresidente'),
            $this->puesto('Secretario General'),
            $this->puesto('Director Ejecutivo'),
        ];

        foreach ($this->zonas as $zona) {
            $puestos[] = $this->puesto('Director Regional', zona: $zona);
        }

        foreach (self::COORDINACIONES as $coordinacion) {
            $puestos[] = $this->puesto('Coordinador Nacional', coordinacion: $this->coordinaciones[$coordinacion]);
        }

        $puestos[] = $this->secretarioTecnicoGeneral();
        $puestos[] = $this->puesto('Representante de ANFECA ante ALAFEC');

        return $puestos;
    }

    /** @return array<int, int> por zona: director, secretario y coordinadores regionales */
    private function consejosRegionales(): array
    {
        $puestos = [];

        foreach ($this->zonas as $zona) {
            $puestos[] = $this->puesto('Director Regional', zona: $zona);
            $puestos[] = $this->puesto('Secretario Regional', zona: $zona);

            foreach (self::COORDINACIONES as $coordinacion) {
                $puestos[] = $this->puesto('Coordinador Regional', zona: $zona, coordinacion: $this->coordinaciones[$coordinacion]);
            }
        }

        return $puestos;
    }

    /** @return array<int, int> por coordinación: el nacional y luego los regionales de cada zona */
    private function coordinacionesNacionales(): array
    {
        $puestos = [];

        foreach (self::COORDINACIONES as $coordinacion) {
            $id = $this->coordinaciones[$coordinacion];
            $puestos[] = $this->puesto('Coordinador Nacional', coordinacion: $id);

            if ($coordinacion === self::COORDINACION_DEL_SECRETARIO_TECNICO) {
                $puestos[] = $this->secretarioTecnicoGeneral();
            }

            foreach ($this->zonas as $zona) {
                $puestos[] = $this->puesto('Coordinador Regional', zona: $zona, coordinacion: $id);
            }
        }

        return $puestos;
    }

    private function secretarioTecnicoGeneral(): int
    {
        return $this->puesto('Secretario Técnico General', coordinacion: $this->coordinaciones[self::COORDINACION_DEL_SECRETARIO_TECNICO]);
    }

    /** Busca el puesto (cargo + ámbito) y, si no existe, lo crea. */
    private function puesto(string $cargo, ?int $zona = null, ?int $coordinacion = null): int
    {
        $ambito = [
            'pues_id_cargo' => $this->cargos[$cargo],
            'pues_id_zona_regional' => $zona,
            'pues_id_coordinacion_nacional' => $coordinacion,
            'pues_id_institucion_educativa' => null,
        ];

        $consulta = DB::table('puesto');
        foreach ($ambito as $columna => $valor) {
            $valor === null ? $consulta->whereNull($columna) : $consulta->where($columna, $valor);
        }

        return (int) ($consulta->value('pues_id_puesto') ?? DB::table('puesto')->insertGetId($ambito, 'pues_id_puesto'));
    }

    /**
     * Deja los puestos del directorio en este orden (agregando los que
     * falten) y, después, los demás que ya estaban, en su orden anterior.
     *
     * @param  array<int, int>  $oficiales
     */
    private function ordenar(string $directorio, array $oficiales): void
    {
        $tipo = DB::table('tipo_directorio')->where('tidi_nombre', $directorio)->value('tidi_id_tipo_directorio')
            ?? throw new RuntimeException("Falta el tipo de directorio «{$directorio}». Corre primero las migraciones.");

        $oficiales = array_values(array_unique($oficiales));
        $demas = DB::table('puesto_directorio')
            ->where('pudi_id_tipo_directorio', $tipo)
            ->whereNotIn('pudi_id_puesto', $oficiales)
            ->orderBy('pudi_orden')
            ->pluck('pudi_id_puesto')
            ->all();

        // El orden es único por directorio (se revisa al final de la transacción)
        foreach ([...$oficiales, ...$demas] as $indice => $puesto) {
            DB::table('puesto_directorio')->updateOrInsert(
                ['pudi_id_puesto' => $puesto, 'pudi_id_tipo_directorio' => $tipo],
                ['pudi_orden' => $indice + 1],
            );
        }
    }

    private function cargarCatalogos(): void
    {
        $nombres = ['Presidente', 'Vicepresidente', 'Secretario General', 'Director Ejecutivo', 'Director Regional',
            'Secretario Regional', 'Coordinador Nacional', 'Coordinador Regional', 'Secretario Técnico General',
            'Representante de ANFECA ante ALAFEC', 'Director General CACECA'];

        foreach ($nombres as $nombre) {
            $this->cargos[$nombre] = (int) (DB::table('cargo')->where('carg_nombre_m', $nombre)->value('carg_id_cargo')
                ?? throw new RuntimeException("Falta el cargo «{$nombre}» en el catálogo de cargos."));
        }

        $this->zonas = DB::table('zona_regional')->orderBy('zore_numero')->pluck('zore_id_zona_regional', 'zore_numero')
            ->map(fn ($id) => (int) $id);
        if ($this->zonas->count() !== 7) {
            throw new RuntimeException('Faltan las zonas regionales. Corre primero: php artisan db:seed --class=CatalogosInicialesSeeder');
        }

        $this->coordinaciones = DB::table('coordinacion_nacional')->whereIn('cona_nombre', self::COORDINACIONES)
            ->pluck('cona_id_coordinacion_nacional', 'cona_nombre')->map(fn ($id) => (int) $id);
        $faltan = array_diff(self::COORDINACIONES, $this->coordinaciones->keys()->all());
        if ($faltan !== []) {
            throw new RuntimeException('Faltan coordinaciones: '.implode(', ', $faltan).'. Corre primero: php artisan db:seed --class=CatalogosInicialesSeeder');
        }
    }
}
