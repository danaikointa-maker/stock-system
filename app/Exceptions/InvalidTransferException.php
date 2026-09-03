<?php

namespace App\Exceptions;

use Exception;

class InvalidTransferException extends Exception
{
    public function render($request)
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'invalid_transfer'], 422);
    }
}
