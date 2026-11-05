<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Kuis terkunci bagi siswa (§8.5). Dirender sebagai 409 { message, reason, pending[] } (API-32).
 */
class GateLockedException extends RuntimeException
{
    /**
     * @param  array{status: string, reason: ?string, pending: array<int, mixed>}  $gate
     */
    public function __construct(public readonly array $gate)
    {
        parent::__construct($gate['reason'] ?? 'Kuis terkunci.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->gate['reason'] ?? 'Kuis terkunci.',
            'reason' => $this->gate['reason'],
            'pending' => $this->gate['pending'],
        ], 409);
    }
}
