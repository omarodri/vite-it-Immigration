<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StageBlockedException extends HttpException
{
    public function __construct(string $message = 'Stage cannot be advanced')
    {
        parent::__construct(422, $message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code'    => 'STAGE_BLOCKED',
        ], 422);
    }
}
