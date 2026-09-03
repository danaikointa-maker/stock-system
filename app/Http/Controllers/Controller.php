<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * ไม่ extends Illuminate\Routing\Controller เพราะคลาสนั้นมีเมธอด middleware() แบบ non-static
 * ซึ่งชนกับ HasMiddleware::middleware() ที่ต้องเป็น static (Laravel 11+)
 */
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
