<?php

namespace App\Enums;

/**
 * Privilegios del sistema (tabla PRIVILEGIO, cargados por SeguridadSeeder).
 *
 * Cada uno es un Gate de Laravel con el mismo nombre. Úsalos así:
 *   - en rutas:        ->middleware('can:'.Privilegio::VerBitacora->value)
 *   - en vistas:       @can('ver_bitacora') ... @endcan
 *   - en controladores: Gate::authorize(Privilegio::VerBitacora->value)
 *
 * Si se agrega un privilegio en el seeder, se agrega también aquí.
 */
enum Privilegio: string
{
    case VerDirectorios = 'ver_directorios';
    case GestionarPersonas = 'gestionar_personas';
    case GestionarInstituciones = 'gestionar_instituciones';
    case GestionarPuestos = 'gestionar_puestos';
    case GestionarDesignaciones = 'gestionar_designaciones';
    case GestionarDirectorios = 'gestionar_directorios';
    case GestionarZonas = 'gestionar_zonas';
    case GestionarCargos = 'gestionar_cargos';
    case GestionarCoordinaciones = 'gestionar_coordinaciones';
    case GestionarGrados = 'gestionar_grados';
    case VerBitacora = 'ver_bitacora';
    case GestionarUsuarios = 'gestionar_usuarios';
    case EliminarDefinitivo = 'eliminar_definitivo';

    /**
     * Middleware de ruta que exige este privilegio (responde 403 si no lo tiene).
     */
    public function middleware(): string
    {
        return 'can:'.$this->value;
    }
}
