<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InvalidStatusTransitionException extends Exception
{
    public function __construct(
        protected string $from,
        protected string $to,
        protected array $allowed = []
    ) {
        parent::__construct("Invalid status transition from '{$from}' to '{$to}'.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success'            => false,
            'message'            => $this->getMessage(),
            'current_status'     => $this->from,
            'allowed_transitions'=> $this->allowed,
        ], 422);
    }
}
