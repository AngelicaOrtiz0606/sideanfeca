<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/*
 * Importa municipios y colonias desde el catálogo oficial de Correos
 * (CPdescarga.txt), que debe estar en storage/app/catalogos/.
 *
 * - Formato: separado por "|", codificación ISO-8859-1, primera línea
 *   es un aviso legal y la segunda es el encabezado.
 * - Es repetible: actualiza en lugar de duplicar.
 * - Si una fila no cuadra con el encabezado o la entidad no existe, se
 *   detiene sin guardar lo incorrecto (corre antes el seeder de entidades).
 *
 * Uso:
 *   php artisan catalogos:importar-correos --limite=500   (prueba)
 *   php artisan catalogos:importar-correos                (completo)
 */
class ImportarCatalogoCorreos extends Command
{
    protected $signature = 'catalogos:importar-correos {--limite=0 : Importar solo las primeras N filas (prueba)}';

    protected $description = 'Importa municipios y colonias desde CPdescarga.txt de Correos de México';

    private const ARCHIVO = 'catalogos/CPdescarga.txt';
    private const TAMANO_LOTE = 1000;

    public function handle(): int
    {
        $ruta = storage_path('app/' . self::ARCHIVO);

        if (! is_file($ruta)) {
            $this->error("No encuentro el archivo: {$ruta}");
            return self::FAILURE;
        }

        $limite = (int) $this->option('limite');

        try {
            // Mapa: clave INEGI de entidad => id interno
            $entidades = DB::table('entidad_federativa')
                ->pluck('enfe_id_entidad_federativa', 'enfe_clave');

            // Paso 1: municipios
            $this->info('Paso 1 de 2: municipios');
            $municipios = [];
            foreach ($this->filas($ruta, $limite) as $fila) {
                $idEntidad = $entidades[$fila['c_estado']] ?? null;
                if ($idEntidad === null) {
                    throw new RuntimeException("Clave de entidad desconocida: {$fila['c_estado']}");
                }

                $clave = $idEntidad . '|' . $fila['c_mnpio'];
                $municipios[$clave] = [
                    'almu_id_entidad_federativa' => $idEntidad,
                    'almu_clave' => $fila['c_mnpio'],
                    'almu_nombre' => $fila['D_mnpio'],
                ];
            }

            foreach (array_chunk(array_values($municipios), self::TAMANO_LOTE) as $lote) {
                DB::table('alcaldia_municipio')->upsert(
                    $lote,
                    ['almu_id_entidad_federativa', 'almu_clave'],
                    ['almu_nombre']
                );
            }
            $this->line('  municipios procesados: ' . count($municipios));

            // Mapa: entidad|clave => id del municipio (ya guardados)
            $mapaMunicipios = DB::table('alcaldia_municipio')
                ->get(['almu_id_alcaldia_municipio', 'almu_id_entidad_federativa', 'almu_clave'])
                ->mapWithKeys(fn ($m) => [
                    $m->almu_id_entidad_federativa . '|' . trim($m->almu_clave) => $m->almu_id_alcaldia_municipio,
                ]);

            // Paso 2: colonias
            $this->info('Paso 2 de 2: colonias');
            $lote = [];
            $total = 0;
            foreach ($this->filas($ruta, $limite) as $fila) {
                $idEntidad = $entidades[$fila['c_estado']];
                $idMunicipio = $mapaMunicipios[$idEntidad . '|' . $fila['c_mnpio']];

                $lote[] = [
                    'colo_id_alcaldia_municipio' => $idMunicipio,
                    'colo_codigo_postal' => $fila['d_codigo'],
                    'colo_id_asenta_cpcons' => $fila['id_asenta_cpcons'],
                    'colo_nombre' => $fila['d_asenta'],
                ];

                if (count($lote) >= self::TAMANO_LOTE) {
                    $this->guardarColonias($lote);
                    $total += count($lote);
                    $lote = [];
                    $this->output->write('.');
                }
            }

            if ($lote !== []) {
                $this->guardarColonias($lote);
                $total += count($lote);
            }

            $this->newLine();
            $this->info("Listo. Colonias procesadas: {$total}");
            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error('Importación detenida: ' . $e->getMessage());
            $this->warn('No se guardó la fila incorrecta. Puedes volver a correr el comando.');
            return self::FAILURE;
        }
    }

    private function guardarColonias(array $lote): void
    {
        DB::table('colonia')->upsert(
            $lote,
            ['colo_codigo_postal', 'colo_id_asenta_cpcons'],
            ['colo_id_alcaldia_municipio', 'colo_nombre']
        );
    }

    /**
     * Lee el archivo línea por línea, convierte de ISO-8859-1 a UTF-8
     * y entrega cada fila como arreglo asociativo (columna => valor).
     */
    private function filas(string $ruta, int $limite): \Generator
    {
        $archivo = fopen($ruta, 'r');
        if ($archivo === false) {
            throw new RuntimeException('No se pudo abrir el archivo.');
        }

        fgets($archivo); // línea 1: aviso legal de Correos

        $encabezado = fgets($archivo);
        if ($encabezado === false) {
            fclose($archivo);
            throw new RuntimeException('El archivo no tiene encabezado.');
        }

        $columnas = array_map('trim', explode('|', $this->aUtf8($encabezado)));
        $numero = 2;
        $entregadas = 0;

        while (($linea = fgets($archivo)) !== false) {
            $numero++;
            $linea = rtrim($this->aUtf8($linea), "\r\n");

            if ($linea === '') {
                continue;
            }

            $valores = explode('|', $linea);
            if (count($valores) !== count($columnas)) {
                fclose($archivo);
                throw new RuntimeException("Línea {$numero} con " . count($valores) . ' columnas; se esperaban ' . count($columnas) . '.');
            }

            yield array_combine($columnas, array_map('trim', $valores));

            $entregadas++;
            if ($limite > 0 && $entregadas >= $limite) {
                break;
            }
        }

        fclose($archivo);
    }

    private function aUtf8(string $texto): string
    {
        return mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    }
}
