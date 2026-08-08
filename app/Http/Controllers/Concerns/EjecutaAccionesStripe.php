<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Throwable;

trait EjecutaAccionesStripe
{
    protected function ejecutar(callable $accion, int $status = 200): JsonResponse
    {
        try {
            return response()->json($accion(), $status);
        } catch (RuntimeException|ApiErrorException $e) {
            return $this->errorStripe($e);
        }
    }

    protected function errorStripe(Throwable $e): JsonResponse
    {
        if ($e instanceof ApiErrorException) {
            report($e);

            return response()->json(['message' => 'Stripe rechazó la operación: '.$e->getMessage()], 422);
        }

        return response()->json(['message' => $e->getMessage()], 422);
    }
}
