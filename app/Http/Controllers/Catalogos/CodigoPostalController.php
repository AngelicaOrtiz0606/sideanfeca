<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Services\Catalogos\CodigoPostalService;
use Illuminate\Http\JsonResponse;

/**
 * Consulta por código postal (JSON), para llenar domicilios en los formularios.
 * No tiene pantalla: el catálogo de SEPOMEX no se consulta ni se edita aparte.
 */
class CodigoPostalController extends Controller
{
    public function __invoke(string $codigo, CodigoPostalService $codigosPostales): JsonResponse
    {
        return response()->json($codigosPostales->buscar($codigo));
    }
}
