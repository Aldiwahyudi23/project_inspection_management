<?php

namespace App\Models\MasterData\Damage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'damage_id',
        'unit',
        'price',
        'currency',
        'applies_to',
    ];

    public function damages()
    {
        return $this->belongsTo(Damages::class, 'damage_id');
    }
}
