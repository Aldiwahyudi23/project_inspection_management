<?php

namespace App\Models\MasterData\Damage;

use Illuminate\Database\Eloquent\Model;

class Damages extends Model
{
    
    protected $fillable = [
        'damage_category_id',
        'label',
        'value',
        'handling',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function category()
    {
        return $this->belongsTo(DamageCategory::class, 'damage_category_id');
    }

    public function prices()
    {
        return $this->hasMany(DamagePrice::class,'damage_id');
    }
}
