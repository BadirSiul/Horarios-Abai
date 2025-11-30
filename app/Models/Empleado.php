<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    use HasFactory;

    protected $fillable = [
        'cedula',
        'nombre',
        'login',
        'team_leader',
        'coordinador', // si lo usas
    ];

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }
}
