<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function render($request)
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'insufficient_stock'], 422);
    }
}
