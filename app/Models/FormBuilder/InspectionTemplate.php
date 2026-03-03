<?php

namespace App\Models\FormBuilder;

use App\Models\Inspection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionTemplate extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'settings',
        'sort_order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Get the menu sections for this template.
     */
    public function menuSections()
    {
        return $this->hasMany(MenuSection::class, 'template_id');
    }

    /**
     * Get the inspections using this template.
     */
    public function inspections()
    {
        return $this->hasMany(Inspection::class, 'template_id');
    }

    /**
     * Get only active menu sections.
     */
    public function activeMenuSections()
    {
        return $this->menuSections()->where('is_active', true);
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting($key, $default = null)
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? $default;
    }

    /**
     * Check if template has active inspections.
     */
    public function hasActiveInspections()
    {
        return $this->inspections()->whereIn('status', ['draft', 'in_progress', 'pending'])->exists();
    }
}