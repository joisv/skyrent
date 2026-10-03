<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    /**
     * Mengambil daftar pengguna sistem dengan filter dan relasi roles.
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles.permissions', 'permissions', 'affiliate']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role') && $request->role !== 'all' && $request->role !== 'semua') {
            $query->role($request->role);
        }

        $sortField = $request->get('sortField', 'created_at');
        $sortDirection = $request->get('sortDirection', 'desc');
        if (in_array($sortField, ['created_at', 'updated_at', 'name', 'email'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = (int) $request->get('per_page', 50);
        $users = $query->paginate($perPage);

        $formatted = collect($users->items())->map(function ($u) {
            $roleNames = $u->roles->pluck('name')->toArray();
            $rolePermissions = $u->roles->flatMap->permissions->pluck('name')->unique()->values()->toArray();
            $directPermissions = $u->permissions->pluck('name')->values()->toArray();
            $allPermissions = $u->getAllPermissions()->pluck('name')->values()->toArray();

            return [
                'id' => (string) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $roleNames,
                'role' => !empty($roleNames) ? $roleNames[0] : '-',
                'permissions' => $allPermissions,
                'direct_permissions' => $directPermissions,
                'role_permissions' => $rolePermissions,
                'affiliate_id' => $u->affiliate_id,
                'affiliate_name' => $u->affiliate?->name,
                'created_at' => $u->created_at?->toIso8601String(),
                'created_at_formatted' => $u->created_at ? $u->created_at->format('d M Y, H:i') : null,
                'updated_at' => $u->updated_at?->toIso8601String(),
                'updated_at_formatted' => $u->updated_at ? $u->updated_at->format('d M Y') : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
            'total' => $users->total(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
        ]);
    }

    /**
     * Mengambil daftar semua role beserta jumlah dan daftar permissions.
     * GET /api/v1/roles
     */
    public function roles(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => ucfirst(str_replace('-', ' ', $role->name)),
                'permissions_count' => (int) $role->permissions->count(),
                'permissions' => $role->permissions->pluck('name')->values()->toArray(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $roles,
        ]);
    }

    /**
     * Mengambil daftar semua permissions.
     * GET /api/v1/permissions
     */
    public function permissions(Request $request): JsonResponse
    {
        $permissions = Permission::all()->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'display_name' => ucfirst(str_replace(['.', '-', '_'], ' ', $p->name)),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $permissions,
        ]);
    }

    /**
     * Menetapkan (assign / sync) role dan/atau direct permissions untuk pengguna tertentu.
     * POST /api/v1/users/{id}/assign-role
     */
    public function assignRole(Request $request, $id): JsonResponse
    {
        $request->validate([
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::exists('permissions', 'name')],
            'direct_permissions' => ['nullable', 'array'],
            'direct_permissions.*' => [Rule::exists('permissions', 'name')],
        ], [
            'role.exists' => 'Role tidak ditemukan dalam sistem.',
            'permissions.*.exists' => 'Terdapat izin hak akses yang tidak valid.',
        ]);

        $user = User::findOrFail($id);

        if ($request->filled('role')) {
            $user->syncRoles([$request->role]);
        }

        if ($request->has('permissions') || $request->has('direct_permissions')) {
            $perms = $request->input('permissions', $request->input('direct_permissions', []));
            $user->syncPermissions($perms ?? []);
        }

        $roleNames = $user->roles->pluck('name')->toArray();
        $allPermissions = $user->getAllPermissions()->pluck('name')->values()->toArray();
        $directPermissions = $user->getDirectPermissions()->pluck('name')->values()->toArray();
        $primaryRole = !empty($roleNames) ? $roleNames[0] : '-';

        return response()->json([
            'status' => 'success',
            'message' => "Role dan permissions pengguna {$user->name} berhasil diperbarui",
            'data' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roleNames,
                'role' => $primaryRole,
                'permissions' => $allPermissions,
                'direct_permissions' => $directPermissions,
            ],
        ]);
    }

    /**
     * Menambahkan user baru dari aplikasi.
     * POST /api/v1/users
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
        ], [
            'name.required' => 'Nama pengguna wajib diisi.',
            'email.required' => 'Email pengguna wajib diisi.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.required' => 'Kata sandi wajib diisi minimal 6 karakter.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->filled('role')) {
            $user->syncRoles([$request->role]);
        }

        $roles = $user->roles->pluck('name')->toArray();

        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna baru berhasil dibuat.',
            'data' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'role' => !empty($roles) ? $roles[0] : '-',
            ],
        ], 201);
    }

    /**
     * Menghapus akun pengguna dari sistem.
     * DELETE /api/v1/users/{id}
     */
    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Jangan izinkan user menghapus akunnya sendiri
        if (auth()->check() && auth()->id() === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Pengguna {$user->name} berhasil dihapus.",
        ]);
    }
}
