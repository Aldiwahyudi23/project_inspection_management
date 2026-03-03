<?php

namespace App\Models\FormBuilder;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuSection extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'template_id',
        'name',
        'section_type',
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
    ];

    /**
     * Get the template that owns this menu section.
     */
    public function template()
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    /**
     * Get the section items for this menu section.
     */
    public function sectionItems()
    {
        return $this->hasMany(SectionItem::class, 'section_id');
    }

    /**
     * Get only active section items.
     */
    public function activeSectionItems()
    {
        return $this->sectionItems()->where('is_active', true);
    }

    /**
     * Get section items ordered by sort_order.
     */
    public function orderedSectionItems()
    {
        return $this->sectionItems()->orderBy('sort_order');
    }

    /**
     * Scope a query to only include active sections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include menu type sections.
     */
    public function scopeMenuType($query)
    {
        return $query->where('section_type', 'menu');
    }

    /**
     * Scope a query to only include damage type sections.
     */
    public function scopeDamageType($query)
    {
        return $query->where('section_type', 'damage');
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Check if this is a damage section.
     */
    public function isDamageSection()
    {
        return $this->section_type === 'damage';
    }

    /**
     * Check if this is a regular menu section.
     */
    public function isMenuSection()
    {
        return $this->section_type === 'menu';
    }
}