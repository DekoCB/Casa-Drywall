<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    public $timestamps = false;

    protected $fillable = ['username', 'password', 'email', 'rol', 'foto'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
        ];
    }

    /** URL pública de la foto de perfil, o null si no tiene una. */
    public function fotoUrl(): ?string
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    public function esSecretaria(): bool
    {
        return $this->rol === 'secretaria';
    }

    public function esContador(): bool
    {
        return $this->rol === 'contador';
    }

    /** Ruta de inicio según el rol, igual que el redirect del login original. */
    public function rutaInicio(): string
    {
        return match ($this->rol) {
            'secretaria' => route('secretaria.index'),
            'contador' => route('contador.index'),
            default => route('admin.index'),
        };
    }
}
