<?php
// =============================================================================
// FILE: app/Http/Middleware/RoleMiddleware.php
// PURPOSE: Middleware untuk membatasi akses berdasarkan role user.
//          Dipasang di route api.php dan web.php.
// =============================================================================
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Penggunaan: ->middleware('role:admin,purchasing')
     * Artinya: hanya admin DAN purchasing yang boleh akses.
     *
     * @param  string  $roles  Roles yang diizinkan, dipisah koma
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 401);
            }
            return redirect()->route('login');
        }

        if (!$user->is_active) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akun Anda dinonaktifkan.'], 403);
            }
            abort(403, 'Akun Anda dinonaktifkan.');
        }

        if (!in_array($user->role, $roles)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akses ditolak. Role yang dibutuhkan: ' . implode(' atau ', $roles),
                ], 403);
            }
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}


// =============================================================================
// FILE: app/Http/Middleware/SupplierActiveMiddleware.php
// PURPOSE: Pastikan supplier tidak dalam status blacklist saat mengakses portal
// =============================================================================
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierActiveMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSupplier()) {
            $supplier = $user->supplier;

            if (!$supplier || $supplier->status === 'blacklist') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Akun supplier Anda diblacklist. Hubungi administrator.',
                        'reason'  => $supplier?->blacklist_reason,
                    ], 403);
                }
                abort(403, 'Akun supplier Anda diblacklist.');
            }

            if ($supplier->status === 'nonaktif') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Akun supplier Anda dinonaktifkan sementara.',
                    ], 403);
                }
                abort(403, 'Akun supplier Anda dinonaktifkan.');
            }
        }

        return $next($request);
    }
}
