<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Ticket;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public const ROLE_USER = 'user';
    public const ROLE_AGENT = 'agent';
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function tickets(){
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets(){
        return $this->hasMany(Ticket::class, 'assigned_agent_id');
    }

    public function isAgent(): bool
    {
        return $this->role === self::ROLE_AGENT;
    }
    public function scopeAgents($query)
    {
        return $query->where('role', self::ROLE_AGENT);
    }
}
