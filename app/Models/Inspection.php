<?php

namespace App\Models;

use App\Models\DirectDB\Vehicle;
use App\Models\FormBuilder\InspectionTemplate;
use App\Models\FormBuilder\MenuSection;
use App\Models\FormBuilder\SectionItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inspection extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'template_id',
        'vehicle_id',
        'vehicle_name',
        'license_plate',
        'mileage',
        'color',
        'chassis_number',
        'engine_number',
        'inspection_date',
        'status',
        'settings',
        'notes',
        'document_path',
        'inspection_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'inspection_date' => 'datetime',
        'mileage' => 'integer',
        'settings' => 'array',
        'vehicle_id' => 'integer',
    ];

    /**
     * Get the template used for this inspection.
     */
    public function template()
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    /**
     * Get the inspection results for this inspection.
     */
    public function results()
    {
        return $this->hasMany(InspectionResult::class);
    }

    /**
     * Get the inspection images for this inspection.
     */
    public function images()
    {
        return $this->hasMany(InspectionImage::class);
    }

    public function repairEstimations()
    {
        return $this->hasMany(InspectionRepairEstimation::class);
    }
    
    /**
     * Get the vehicle associated with this inspection.
     */     
    // Data Vehicle dari DirectDB eksternal connection

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * Get the menu sections from the template.
     */
    public function menuSections()
    {
        return $this->hasManyThrough(
            MenuSection::class,
            InspectionTemplate::class,
            'id', // Foreign key on inspection_templates table
            'template_id', // Foreign key on menu_sections table
            'template_id', // Local key on inspections table
            'id' // Local key on inspection_templates table
        );
    }

    /**
     * Get all section items through menu sections and template.
     */
    public function sectionItems()
    {
        return $this->hasManyThrough(
            SectionItem::class,
            MenuSection::class,
            'template_id', // Foreign key on menu_sections table
            'section_id', // Foreign key on section_items table
            'template_id', // Local key on inspections table
            'id' // Local key on menu_sections table
        )->where('menu_sections.is_active', true)
         ->where('section_items.is_active', true);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include active inspections.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled', 'rejected']);
    }

    /**
     * Scope a query to only include completed inspections.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to filter by vehicle ID.
     */
    public function scopeByVehicle($query, $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Scope a query to filter by license plate.
     */
    public function scopeByLicensePlate($query, $licensePlate)
    {
        return $query->where('license_plate', 'LIKE', "%{$licensePlate}%");
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        $endDate = $endDate ?: $startDate;
        return $query->whereBetween('inspection_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to order by inspection date (newest first).
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('inspection_date', 'desc');
    }

    /**
     * Check if inspection is in draft status.
     */
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    /**
     * Check if inspection is in progress.
     */
    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if inspection is under review.
     */
    public function isUnderReview()
    {
        return $this->status === 'under_review';
    }

    /**
     * Check if inspection is approved.
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if inspection is completed.
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if inspection can be edited.
     */
    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'in_progress', 'paused', 'revision']);
    }

    /**
     * Check if inspection can be reviewed.
     */
    public function canBeReviewed()
    {
        return in_array($this->status, ['under_review', 'paused']);
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
     * Get the document URL if exists.
     */
    public function getDocumentUrlAttribute()
    {
        if (!$this->document_path) {
            return null;
        }

        return asset('storage/' . $this->document_path);
    }

    /**
     * Get the vehicle display name.
     */
    public function getVehicleDisplayAttribute()
    {
        if ($this->vehicle_name && $this->license_plate) {
            return $this->vehicle_name . ' (' . $this->license_plate . ')';
        }
        
        return $this->vehicle_name ?: $this->license_plate ?: 'Vehicle #' . $this->vehicle_id;
    }

    /**
     * Get progress percentage based on completed items.
     */
    public function getProgressPercentageAttribute()
    {
        $totalItems = $this->sectionItems()->count();
        $completedItems = $this->results()->count();
        
        if ($totalItems === 0) {
            return 0;
        }
        
        return round(($completedItems / $totalItems) * 100);
    }

    /**
     * Get damage section results.
     */
    public function damageResults()
    {
        return $this->results()->whereHas('sectionItem', function ($query) {
            $query->whereHas('menuSection', function ($q) {
                $q->where('section_type', 'damage');
            });
        });
    }

        public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'in_progress' => 'Sedang Berjalan',
            'paused' => 'Tertunda',
            'under_review' => 'Dalam Review',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'revision' => 'Perlu Revisi',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get status color attribute for badges.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'in_progress' => 'primary',
            'paused' => 'warning',
            'under_review' => 'info',
            'approved' => 'success',
            'rejected' => 'danger',
            'revision' => 'warning',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get status icon attribute.
     */
    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'heroicon-o-pencil',
            'in_progress' => 'heroicon-o-cog',
            'paused' => 'heroicon-o-clock',
            'under_review' => 'heroicon-o-eye',
            'approved' => 'heroicon-o-check-badge',
            'rejected' => 'heroicon-o-x-circle',
            'revision' => 'heroicon-o-arrow-path',
            'completed' => 'heroicon-o-check',
            'cancelled' => 'heroicon-o-ban',
            default => 'heroicon-o-document',
        };
    }
}