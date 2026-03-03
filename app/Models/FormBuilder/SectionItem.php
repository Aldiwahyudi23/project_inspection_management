<?php

namespace App\Models\FormBuilder;

use App\Models\InspectionResult;
use App\Models\MasterData\InspectionItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SectionItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'section_id',
        'inspection_item_id',
        'input_type',
        'settings',
        'sort_order',
        'is_active',
        'is_visible',
        'is_required',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Get the menu section that owns this section item.
     */
    public function menuSection()
    {
        return $this->belongsTo(MenuSection::class, 'section_id');
    }

    /**
     * Get the inspection item referenced by this section item.
     */
    public function inspectionItem()
    {
        return $this->belongsTo(InspectionItem::class, 'inspection_item_id','id');
    }

    /**
     * Get the inspection results for this section item.
     */
    public function inspectionResults()
    {
        return $this->hasManyThrough(
            InspectionResult::class,
            InspectionItem::class,
            'id', // Foreign key on inspection_items table
            'inspection_item_id', // Foreign key on inspection_results table
            'inspection_item_id', // Local key on section_items table
            'id' // Local key on inspection_items table
        );
    }

    /**
     * Scope a query to only include active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include visible items.
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope a query to only include required items.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get items by input type.
     */
    public function scopeByInputType($query, $type)
    {
        return $query->where('input_type', $type);
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
     * Check if this item should be displayed.
     * Combines both active and visible flags.
     */
    public function shouldDisplay()
    {
        return $this->is_active && $this->is_visible;
    }

    /**
     * Check if this item accepts image input.
     */
    public function acceptsImages()
    {
        return $this->input_type === 'image' || $this->input_type === 'file';
    }

    /**
     * Get available options from settings (for radio/select inputs).
     */
    public function getOptionsAttribute()
    {
        return $this->getSetting('options', []);
    }

    /**
     * Get validation rules from settings.
     */
    public function getValidationRulesAttribute()
    {
        return $this->getSetting('validation_rules', []);
    }

    /**
     * Check if item is in a damage section.
     */
    public function isInDamageSection()
    {
        return $this->menuSection && $this->menuSection->isDamageSection();
    }
}