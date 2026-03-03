<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\FormBuilder\SectionItem;
use App\Models\MasterData\InspectionItem;
class InspectionResult extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inspection_id',
        'inspection_item_id',
        'status',
        'note',
        'extra_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'extra_data' => 'array',
    ];

    /**
     * Get the inspection that owns this result.
     */
    public function inspection()
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * Get the inspection item for this result.
     */
    public function inspectionItem()
    {
        return $this->belongsTo(InspectionItem::class);
    }

    /**
     * Get the section item related to this result.
     */
    public function sectionItem()
    {
        return $this->hasOneThrough(
            SectionItem::class,
            InspectionItem::class,
            'id', // Foreign key on inspection_items table
            'inspection_item_id', // Foreign key on section_items table
            'inspection_item_id', // Local key on inspection_results table
            'id' // Local key on inspection_items table
        );
    }

    /**
     * Get the inspection images for this result.
     */
    public function inspectionImages()
    {
        return $this->hasMany(InspectionImage::class, 'inspection_item_id', 'inspection_item_id')
                    ->where('inspection_id', $this->inspection_id);
    }

    /**
     * Scope a query to filter by inspection ID.
     */
    public function scopeByInspection($query, $inspectionId)
    {
        return $query->where('inspection_id', $inspectionId);
    }

    /**
     * Scope a query to filter by inspection item ID.
     */
    public function scopeByItem($query, $itemId)
    {
        return $query->where('inspection_item_id', $itemId);
    }

    /**
     * Scope a query to only include results with status.
     */
    public function scopeHasStatus($query)
    {
        return $query->whereNotNull('status');
    }

    /**
     * Scope a query to only include results with notes.
     */
    public function scopeHasNotes($query)
    {
        return $query->whereNotNull('note');
    }

    /**
     * Check if result has status value.
     */
    public function hasStatus()
    {
        return !empty($this->status);
    }

    /**
     * Check if result has note value.
     */
    public function hasNote()
    {
        return !empty($this->note);
    }

    /**
     * Check if result has extra data.
     */
    public function hasExtraData()
    {
        return !empty($this->extra_data);
    }

    /**
     * Get a specific extra data value.
     */
    public function getExtraData($key, $default = null)
    {
        $extraData = $this->extra_data ?? [];
        return $extraData[$key] ?? $default;
    }

    /**
     * Get the input type from related section item.
     */
    public function getInputTypeAttribute()
    {
        if ($this->sectionItem) {
            return $this->sectionItem->input_type;
        }
        return null;
    }

    /**
     * Check if this result is for an image input type.
     */
    public function isImageResult()
    {
        return $this->input_type === 'image';
    }

    /**
     * Check if this result is for a text input type.
     */
    public function isTextResult()
    {
        return $this->input_type === 'text';
    }

    /**
     * Check if this result is for a radio/select input type.
     */
    public function isOptionResult()
    {
        return in_array($this->input_type, ['radio', 'select', 'checkbox']);
    }

    /**
     * Get the display value based on input type.
     */
    public function getDisplayValueAttribute()
    {
        if ($this->hasStatus()) {
            return $this->status;
        }
        
        if ($this->hasNote()) {
            return $this->note;
        }
        
        return 'N/A';
    }
}