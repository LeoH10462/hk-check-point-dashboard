<?php
namespace App;

use Closure;
use Illuminate\Http\Request;

final class OperatorOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->session()->get('operator'), 401, '請先登入');
        return $next($request)->header('Cache-Control','no-store');
    }
}
