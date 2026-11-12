<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 * Grados académicos básicos (tabla NIVEL_ACADEMICO): Licenciatura, Maestría
 * y Doctorado. Los demás los agrega el usuario desde la pantalla, y estos
 * también se pueden editar, desactivar o borrar ahí.
 *
 * Es repetible: si un grado ya existe (sin importar mayúsculas), no lo
 * duplica ni lo cambia.
 *
 * Uso: php artisan db:seed --class=GradosAcademicosSeeder
 */
class GradosAcademicosSeeder extends Seeder
{
    private const GRADOS = [
        ['Licenciatura', 'Lic.', 'Lic.'],
        ['Maestría', 'Mtro.', 'Mtra.'],
        ['Doctorado', 'Dr.', 'Dra.'],
    ];

    public function run(): void
    {
        foreach (self::GRADOS as [$nombre, $masculina, $femenina]) {
            $existe = DB::table('nivel_academico')->whereRaw('lower(niac_nombre) = lower(?)', [$nombre])->exists();

            if (! $existe) {
                DB::table('nivel_academico')->insert([
                    'niac_nombre' => $nombre,
                    'niac_abr_m' => $masculina,
                    'niac_abr_f' => $femenina,
                    'niac_activo' => true,
                ]);
            }
        }
    }
}
