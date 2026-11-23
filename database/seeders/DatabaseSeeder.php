<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/*
 * Datos de instalación de SIDEANFECA.
 *
 * Uso (después de php artisan migrate):
 *   php artisan db:seed
 *
 * Orden:
 *   1. SeguridadSeeder: privilegios, roles y sus permisos.
 *   2. CatalogosInicialesSeeder: zonas, entidades y coordinaciones.
 *   3. GradosAcademicosSeeder: Licenciatura, Maestría y Doctorado.
 *   4. CargosBaseSeeder: los cargos base de ANFECA (nacionales, regionales e institucionales).
 *   5. PuestosDirectoriosSeeder: los puestos de los tres directorios y su orden.
 *   6. OrganizacionesSeeder: CACECA con su domicilio (necesita el catálogo de Correos).
 *
 * Municipios y colonias no van aquí: se cargan con
 *   php artisan catalogos:importar-correos
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SeguridadSeeder::class,
            CatalogosInicialesSeeder::class,
            GradosAcademicosSeeder::class,
            CargosBaseSeeder::class,
            PuestosDirectoriosSeeder::class,
            OrganizacionesSeeder::class,
        ]);
    }
}
