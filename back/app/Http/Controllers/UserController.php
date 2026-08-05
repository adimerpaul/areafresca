<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    // ── Auth ─────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)
            ->with('permissions:id,name')
            ->first();

        if (! $user || ! password_verify($request->password, $user->password)) {
            return response()->json(['message' => 'Usuario o contraseña incorrectos'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'must_change_password' => password_verify('123456', $user->password),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        $user = User::where('id', $request->user()->id)
            ->with('permissions:id,name')
            ->first();

        return response()->json($user);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password_actual' => 'required|string',
            'password_nuevo' => 'required|string|min:6',
            'password_nuevo_confirmation' => 'required|same:password_nuevo',
        ]);

        $user = $request->user();

        if (! password_verify($request->password_actual, $user->getAuthPassword())) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], 422);
        }

        $user->update(['password' => bcrypt($request->password_nuevo)]);

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    // ── CRUD usuarios ────────────────────────────────────────

    public function index(Request $request)
    {
        $this->requirePermission($request, 'Ver Usuarios');

        return User::with('permissions:id,name')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $this->requirePermission($request, 'Crear Usuarios');

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username,NULL,id,deleted_at,NULL',
            'password' => 'required|string|min:6',
            'email' => 'nullable|email|max:255',
            'celular' => 'nullable|string|max:50',
            'ci' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name' => mb_strtoupper($request->name),
            'username' => $request->username,
            'email' => $request->email,
            'celular' => $request->celular,
            'ci' => $request->ci,
            'password' => bcrypt($request->password),
        ]);

        return response()->json($user->load('permissions:id,name'), 201);
    }

    public function update(Request $request, $id)
    {
        $this->requirePermission($request, 'Editar Usuarios');

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'celular' => 'nullable|string|max:50',
            'ci' => 'nullable|string|max:50',
        ]);

        $data = $request->only(['name', 'email', 'celular', 'ci']);
        if (isset($data['name'])) {
            $data['name'] = mb_strtoupper($data['name']);
        }

        $user->update($data);

        return response()->json($user->load('permissions:id,name'));
    }

    public function destroy(Request $request, $id)
    {
        $this->requirePermission($request, 'Eliminar Usuarios');

        $user = User::findOrFail($id);

        if ($user->avatar) {
            $path = public_path('images/'.$user->avatar);
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $user->delete();

        return response()->json(['message' => 'Usuario eliminado']);
    }

    public function resetPassword(Request $request, $id)
    {
        $this->requirePermission($request, 'Editar Usuarios');

        User::findOrFail($id)->update(['password' => bcrypt('123456')]);

        return response()->json(['message' => 'Contraseña restablecida a 123456']);
    }

    // ── Avatar ───────────────────────────────────────────────

    public function uploadAvatar(Request $request, $id)
    {
        $this->requirePermission($request, ['Crear Usuarios', 'Editar Usuarios']);

        $request->validate(['avatar' => 'required|image|max:8192']);

        $user = User::findOrFail($id);

        if ($user->avatar) {
            $old = public_path('images/'.$user->avatar);
            if (file_exists($old)) {
                @unlink($old);
            }
        }

        $file = $request->file('avatar');
        $src = $this->gdLoad($file);

        $ow = imagesx($src);
        $oh = imagesy($src);
        $max = 300;
        $scale = ($ow > $max || $oh > $max) ? min($max / $ow, $max / $oh) : 1;
        $nw = (int) round($ow * $scale);
        $nh = (int) round($oh * $scale);

        $dst = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
        imagedestroy($src);

        $dir = public_path('images');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'avatar_'.$id.'_'.time().'.webp';
        imagewebp($dst, $dir.'/'.$filename, 85);
        imagedestroy($dst);

        $user->update(['avatar' => $filename]);

        return response()->json(['avatar' => $filename]);
    }

    private function gdLoad($file): \GdImage
    {
        $mime = $file->getMimeType();
        $path = $file->getPathname();

        return match (true) {
            str_contains($mime, 'jpeg') => imagecreatefromjpeg($path),
            str_contains($mime, 'png') => imagecreatefrompng($path),
            str_contains($mime, 'webp') => imagecreatefromwebp($path),
            str_contains($mime, 'gif') => imagecreatefromgif($path),
            default => imagecreatefromstring(file_get_contents($path)),
        };
    }

    // ── Permisos ─────────────────────────────────────────────

    public function permissions(Request $request)
    {
        $this->requirePermission($request, ['Crear Usuarios', 'Editar Usuarios', 'Gestionar Permisos']);

        return response()->json(Permission::orderBy('name')->get());
    }

    public function userPermissions(Request $request, $id)
    {
        $this->requirePermission($request, ['Editar Usuarios', 'Gestionar Permisos']);

        $user = User::findOrFail($id);

        return response()->json($user->permissions()->pluck('id'));
    }

    public function updateUserPermissions(Request $request, $id)
    {
        $this->requirePermission($request, 'Gestionar Permisos');

        $user = User::findOrFail($id);
        $perms = Permission::whereIn('id', $request->permissions ?? [])->get();
        $user->syncPermissions($perms);

        return response()->json($user->permissions()->pluck('name'));
    }

    // ── Helper ───────────────────────────────────────────────

    private function requirePermission(Request $request, string|array $permission): void
    {
        $user = $request->user();
        $perms = is_array($permission) ? $permission : [$permission];

        foreach ($perms as $p) {
            if ($user->hasPermissionTo($p)) {
                return;
            }
        }

        abort(403, 'No tiene permiso para realizar esta acción');
    }
}
