<?php

namespace App\Models;

use App\Models\MasterData\Damage\Damages;
use App\Models\MasterData\InspectionItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InspectionRepairEstimation extends Model
{
    use HasFactory;

    protected $table = 'inspection_repair_estimations';

    protected $fillable = [
        'inspection_id',
        'related_sources',
        'part_name',
        'repair_description',
        'urgency',
        'status',
        'estimated_cost',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'related_sources' => 'array',
        'estimated_cost'  => 'decimal:2',
    ];

    /* =====================
     * RELATIONS
     * ===================== */

    public function inspection()
    {
        return $this->belongsTo(Inspection::class);
    }

    /* =====================
     * HELPERS (OPTIONAL BUT POWERFUL)
     * ===================== */

    public function relatedDamageIds(): array
    {
        return $this->related_sources['damages'] ?? [];
    }

    public function relatedInspectionItemIds(): array
    {
        return $this->related_sources['inspection_items'] ?? [];
    }


    public function resolvedDamages()
    {
        if (empty($this->relatedDamageIds())) {
            return collect();
        }

        return Damages::with('prices')
            ->whereIn('id', $this->relatedDamageIds())
            ->get();
    }

    public function resolvedInspectionItems()
    {
        if (empty($this->relatedInspectionItemIds())) {
            return collect();
        }

        return InspectionItem::whereIn(
            'id',
            $this->relatedInspectionItemIds()
        )->get();
    }
}
