<?php

namespace App\Models\MasterData\Damage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DamageCategory extends Model
{
     use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include active components.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    public function damages()
    {
        return $this->hasMany(Damages::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->code)) {
                $lastCode = static::where('code', 'like', 'DM-%')
                    ->orderBy('code', 'desc')
                    ->first();
                
                if ($lastCode) {
                    // Ambil angka dari kode terakhir
                    $lastNumber = (int) substr($lastCode->code, 3);
                    $newNumber = $lastNumber + 1;
                    $model->code = 'DM-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
                } else {
                    $model->code = 'DM-001';
                }
            }
        });
    }
}
