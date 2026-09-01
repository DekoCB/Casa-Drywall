<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect(Auth::user()->rutaInicio());
        }

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // El registro está protegido por una contraseña maestra, igual que en el
        // register.php original.
        if ($request->input('master_password') !== config('rentaltech.master_password')) {
            throw ValidationException::withMessages([
                'master_password' => 'Contraseña maestra incorrecta. No tienes permiso para crear usuarios.',
            ]);
        }

        $datos = $request->validate([
            'username' => ['required', 'string', 'max:100', Rule::unique('usuarios', 'username')],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'email')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'required' => 'Por favor, complete todos los campos',
            'username.unique' => 'El nombre de usuario ya existe',
            'email.unique' => 'El email ya está registrado',
            'email.email' => 'El email no es válido',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
        ]);

        Usuario::create([
            'username' => $datos['username'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'rol' => $request->input('rol', 'admin'),
        ]);

        return redirect()->route('register')
            ->with('success', 'Usuario creado exitosamente. Redirigiendo al login...');
    }
}
