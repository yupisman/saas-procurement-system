<?php
// =============================================================================
// FILE: app/Http/Controllers/Api/AuthController.php
// PURPOSE: Autentikasi via Sanctum untuk supplier portal (Vue) dan mobile (Capacitor)
// =============================================================================
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login - mendapatkan token Sanctum
     * Digunakan oleh supplier portal (Vue) dan mobile (Capacitor)
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'nullable|string|max:255', // Untuk mobile
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ], 403);
        }

        // Hapus token lama jika dari device yang sama (mobile)
        $deviceName = $request->device_name ?? 'browser';
        $user->tokens()->where('name', $deviceName)->delete();

        // Buat token baru dengan ability sesuai role
        $abilities = match($user->role) {
            'admin'      => ['*'],
            'purchasing' => ['pr:read', 'pr:write', 'quotation:read', 'quotation:write', 'po:write'],
            'supplier'   => ['pr:read', 'quotation:write', 'delivery:write', 'invoice:write'],
            default      => ['pr:read'],
        };

        $token = $user->createToken($deviceName, $abilities)->plainTextToken;

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Catat login di audit
        AuditLog::record('login', 'Auth', $user, "User {$user->name} login dari {$request->ip()}");

        return response()->json([
            'token'    => $token,
            'user'     => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'role'     => $user->role,
                'supplier' => $user->supplier ? [
                    'id'           => $user->supplier->id,
                    'company_name' => $user->supplier->company_name,
                    'status'       => $user->supplier->status,
                ] : null,
            ],
            'abilities' => $abilities,
        ]);
    }

    /**
     * Logout - revoke token aktif
     */
    public function logout(Request $request): JsonResponse
    {
        AuditLog::record('logout', 'Auth', $request->user(), "User {$request->user()->name} logout");
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * Get profil user yang sedang login
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('supplier.categories');

        return response()->json([
            'user' => $user,
        ]);
    }
}
