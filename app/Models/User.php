<?php

namespace App\Models;

use App\Models\Funcionario\Funcionario;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use \App\Models\Concerns\Auditable;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLES = [
        'admin'                => 'Administrador',
        'gestor_secretaria'    => 'Gestor Secretaria',
        'professor'            => 'Professor',
        'professor_aee'        => 'Prof AEE',
        'coordenador'          => 'Coordenador',
        'coordenador_interno'  => 'Coord. Interno',
        'diretor'              => 'Diretor/Vice',
        'secretaria_escola'    => 'Secretaria',
        'pedagogico'           => 'Pedagógico',
        'monitor'              => 'Monitor',
        'matricula_censo'      => 'Matrícula/Censo',
    ];

    protected $fillable = [
        'name',
        'login',
        'email',
        'password',
        'phone',
        'active',
        'fun_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['roles'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'active'            => 'boolean',
            'fun_id'            => 'integer',
        ];
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function getRolesAttribute(): array
    {
        return $this->userRoles->pluck('role')->all();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasAnyRole(array $roles): bool
    {
        return count(array_intersect($this->roles, $roles)) > 0;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Gestor equiparado ao administrador (acesso total nas telas de diário
     * AEE / Atividade). Admin ou gestor_secretaria.
     */
    public function isGestorGlobal(): bool
    {
        return $this->hasAnyRole(['admin', 'gestor_secretaria']);
    }

    public function syncRoles(array $roles): void
    {
        $this->userRoles()->delete();

        $valid = array_intersect($roles, array_keys(self::ROLES));
        foreach ($valid as $role) {
            $this->userRoles()->create(['role' => $role]);
        }

        $this->unsetRelation('userRoles');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'fun_id', 'fun_id');
    }

    /**
     * Envia a notificação de redefinição de senha (e-mail em pt_BR).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
