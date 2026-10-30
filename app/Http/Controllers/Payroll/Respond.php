<?php

namespace App\Http\Controllers\Payroll;

use App\Payroll\Loans\LoanException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/** Refused payroll actions answer {message} with the status the engine or service gave (400, 404, 409 or 422). */
trait Respond
{
    protected function attempt(callable $action, int $status = 200): mixed
    {
        try {
            $out = $action();
            return $out instanceof JsonResponse || $out instanceof \Symfony\Component\HttpFoundation\Response ? $out : response()->json($out, $status);
        } catch (InvalidArgumentException|LoanException $e) {
            $code = in_array($e->getCode(), [400, 404, 409, 422], true) ? $e->getCode() : 422;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }
}
