<?php

namespace App\Models\Disciplina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AreaConhecimento extends Model
{
    protected $table = 'edu_area_conhecimento';
    protected $primaryKey = 'arc_id';

    protected $fillable = ['arc_nome'];
}
