<?php

namespace Database\Seeders;

use App\Support\TextoCatalogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 * Cargos base de ANFECA (tabla CARGO), con su nombre en masculino y en
 * femenino y su nivel. Los demás los agrega el usuario desde Catálogos →
 * Cargos, y estos también se pueden editar, desactivar o borrar ahí.
 *
 * Es repetible: si un cargo ya existe (en masculino o en femenino, sin
 * importar mayúsculas, acentos ni espacios), no lo duplica ni lo cambia.
 *
 * Uso: php artisan db:seed --class=CargosBaseSeeder
 */
class CargosBaseSeeder extends Seeder
{
    private const CARGOS = [
        // Consejo Nacional Directivo
        ['Presidente', 'Presidenta', 'Nacional'],
        ['Vicepresidente', 'Vicepresidenta', 'Nacional'],
        ['Secretario General', 'Secretaria General', 'Nacional'],
        ['Director Ejecutivo', 'Directora Ejecutiva', 'Nacional'],

        // Otros cargos nacionales
        ['Coordinador Nacional', 'Coordinadora Nacional', 'Nacional'],
        ['Secretario Técnico General', 'Secretaria Técnica General', 'Nacional'],
        ['Representante de ANFECA ante ALAFEC', 'Representante de ANFECA ante ALAFEC', 'Nacional'],
        ['Presidente del Consejo de Acreditación en la Enseñanza de la Contaduría y Administración, A.C. (CACECA)', 'Presidenta del Consejo de Acreditación en la Enseñanza de la Contaduría y Administración, A.C. (CACECA)', 'Nacional'],

        // Regionales
        ['Director Regional', 'Directora Regional', 'Regional'],
        ['Secretario Regional', 'Secretaria Regional', 'Regional'],

        // Regionales con coordinaciones (el Secretario Técnico Regional ya no existe)
        ['Coordinador Regional', 'Coordinadora Regional', 'Regional'],

        // Institucionales
        ['Director', 'Directora', 'Institucional'],
        ['Director Académico', 'Directora Académica', 'Institucional'],
        ['Director de Área', 'Directora de Área', 'Institucional'],
        ['Director General', 'Directora General', 'Institucional'],
        ['Director de Contaduría', 'Directora de Contaduría', 'Institucional'],
        ['Director de División', 'Directora de División', 'Institucional'],
        ['Director de División Económico-Administrativa', 'Directora de División Económico-Administrativa', 'Institucional'],
        ['Director de Carrera de LIN', 'Directora de Carrera de LIN', 'Institucional'],
        ['Coordinador Académico', 'Coordinadora Académica', 'Institucional'],
        ['Coordinador de Licenciatura en Administración', 'Coordinadora de Licenciatura en Administración', 'Institucional'],
        ['Coordinador de Contaduría y Administración', 'Coordinadora de Contaduría y Administración', 'Institucional'],
        ['Coordinador de Licenciatura en Contaduría Pública', 'Coordinadora de Licenciatura en Contaduría Pública', 'Institucional'],
        ['Coordinador de la Facultad', 'Coordinadora de la Facultad', 'Institucional'],
        ['Coordinador General', 'Coordinadora General', 'Institucional'],
        ['Coordinador de Negocios', 'Coordinadora de Negocios', 'Institucional'],
        ['Coordinador de Proyectos y Vinculación Institucional', 'Coordinadora de Proyectos y Vinculación Institucional', 'Institucional'],
        ['Coordinador de Unidad Académica', 'Coordinadora de Unidad Académica', 'Institucional'],
        ['Coordinador de Área Económico-Administrativa', 'Coordinadora de Área Económico-Administrativa', 'Institucional'],
        ['Coordinador de Ciencias Económico Administrativas', 'Coordinadora de Ciencias Económico Administrativas', 'Institucional'],
        ['Coordinador de Contaduría', 'Coordinadora de Contaduría', 'Institucional'],
        ['Jefe de Departamento', 'Jefa de Departamento', 'Institucional'],
        ['Jefe de Departamento de Ciencias Administrativas', 'Jefa de Departamento de Ciencias Administrativas', 'Institucional'],
        ['Jefe de Departamento de Ciencias Económico Administrativas', 'Jefa de Departamento de Ciencias Económico Administrativas', 'Institucional'],
        ['Jefe del Departamento de Contabilidad', 'Jefa del Departamento de Contabilidad', 'Institucional'],
        ['Rector', 'Rectora', 'Institucional'],
        ['Encargado de la Dirección', 'Encargada de la Dirección', 'Institucional'],
        ['Secretario', 'Secretaria', 'Institucional'],
    ];

    public function run(): void
    {
        $existentes = DB::table('cargo')->get(['carg_nombre_m', 'carg_nombre_f'])
            ->flatMap(fn (object $c) => [TextoCatalogo::clave($c->carg_nombre_m), TextoCatalogo::clave($c->carg_nombre_f)])
            ->flip();

        foreach (self::CARGOS as [$masculino, $femenino, $nivel]) {
            if ($existentes->has(TextoCatalogo::clave($masculino)) || $existentes->has(TextoCatalogo::clave($femenino))) {
                continue;
            }

            DB::table('cargo')->insert([
                'carg_nombre_m' => $masculino,
                'carg_nombre_f' => $femenino,
                'carg_nivel' => $nivel,
                'carg_activo' => true,
            ]);
            $existentes->put(TextoCatalogo::clave($masculino), true)->put(TextoCatalogo::clave($femenino), true);
        }
    }
}
