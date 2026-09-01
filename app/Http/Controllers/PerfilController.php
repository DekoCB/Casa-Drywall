<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Datos de la cuenta del usuario autenticado: nombre de usuario, foto y
 * contraseña. Accesible desde cualquier rol — no vive bajo /admin.
 */
class PerfilController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'username' => ['required', 'string', 'max:100', Rule::unique('usuarios', 'username')->ignore($usuario->id)],
            'foto' => ['nullable', 'image', 'max:8192'], // 8192 KB = 8 MB
            'password_actual' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (filled($datos['password'] ?? null)) {
            if (! Hash::check($datos['password_actual'], $usuario->password)) {
                throw ValidationException::withMessages([
                    'password_actual' => 'La contraseña actual no es correcta.',
                ]);
            }

            $usuario->password = $datos['password'];
        }

        $usuario->username = $datos['username'];

        if ($request->hasFile('foto')) {
            if ($usuario->foto) {
                Storage::disk('public')->delete($usuario->foto);
            }

            $usuario->foto = $request->file('foto')->store('perfiles', 'public');
        }

        $usuario->save();

        return back()->with('mensaje', 'Perfil actualizado correctamente');
    }
}
