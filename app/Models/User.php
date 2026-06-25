<?php

namespace App\Models;

use App\Models\Funcionario\Funcionario;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'professor'            => 'Professor',
        'professor_aee'        => 'Professor AEE',
        'coordenador'          => 'Coordenador Pedagógico',
        'coordenador_interno'  => 'Coordenador Pedagógico Interno',
        'diretor'              => 'Diretor',
        'secretaria_escola'    => 'Secretaria Escolar',
        'admin'                => 'Administrador',
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
