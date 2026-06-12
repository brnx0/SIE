<?php

namespace App\Models;

use App\Models\Funcionario\Funcionario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'professor' => 'Professor',
        'professor_aee' => 'Professor AEE',
        'coordenador' => 'Coordenador Pedagógico',
        'coordenador_interno' => 'Coordenador Pedagógico Interno',
        'diretor' => 'Diretor',
        'secretaria_escola' => 'Secretaria Escolar',
        'admin' => 'Administrador',
    ];


    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'active',
        'fun_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'active'            => 'boolean',
            'fun_id'            => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'fun_id', 'fun_id');
    }
}
