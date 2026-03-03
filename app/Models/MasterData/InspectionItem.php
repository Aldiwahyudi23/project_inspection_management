<?php

namespace App\Models\MasterData;

use App\Models\FormBuilder\SectionItem;
use App\Models\InspectionImage;
use App\Models\InspectionResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'component_id',
        'name',
        'description',
        'check_notes',
        'sort_order',
        'is_active',
        'image_path',
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
     * Get the component that owns this inspection item.
     */
    public function component()
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get the section items that reference this inspection item.
     */
    public function sectionItems()
    {
        return $this->hasMany(SectionItem::class, 'inspection_item_id');
    }

    /**
     * Get the inspection results for this item.
     */
    public function inspectionResults()
    {
        return $this->hasMany(InspectionResult::class, 'inspection_item_id');
    }

    /**
     * Get the inspection images for this item.
     */
    public function inspectionImages()
    {
        return $this->hasMany(InspectionImage::class, 'inspection_item_id');
    }

    /**
     * Scope a query to only include active items.
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
     * Get the inspection item's image URL (if exists).
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }

        return asset('storage/' . $this->image_path);
    }

    /**
     * Get the full name including component name.
     */
    public function getFullNameAttribute()
    {
        if ($this->component) {
            return $this->component->name . ' - ' . $this->name;
        }

        return $this->name;
    }
}