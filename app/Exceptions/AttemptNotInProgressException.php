<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Percobaan sudah dikirim, sehingga tidak bisa diubah atau dikirim lagi (API-34, API-35).
 */
class AttemptNotInProgressException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Percobaan ini sudah dikirim.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
