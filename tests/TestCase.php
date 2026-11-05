<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sufijo obligatorio del nombre de la base de pruebas.
     */
    private const SUFIJO_BASE_PRUEBAS = '_testing';

    /**
     * Antes de cualquier prueba (y antes de que RefreshDatabase o
     * DatabaseTransactions toquen la base), confirma que la conexión
     * apunta a una base de pruebas. Si no, detiene todo.
     */
    protected function setUpTraits(): array
    {
        $this->asegurarBaseDePruebas();

        return parent::setUpTraits();
    }

    private function asegurarBaseDePruebas(): void
    {
        $conexion = config('database.default');
        $base = (string) config("database.connections.{$conexion}.database");

        if ($conexion !== 'pgsql' || ! str_ends_with($base, self::SUFIJO_BASE_PRUEBAS)) {
            throw new RuntimeException(
                "Pruebas detenidas: la conexión es '{$conexion}' y la base es '{$base}'. "
                .'Las pruebas solo corren en PostgreSQL, en una base cuyo nombre termine en '
                .self::SUFIJO_BASE_PRUEBAS.'. Revisa phpunit.xml y .env.testing.'
            );
        }
    }
}
