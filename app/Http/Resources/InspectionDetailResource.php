<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inspection_code' => $this->inspection_code,

            'vehicle' => [
                'vehicle_id' => $this->vehicle_id,
                'vehicle_name' => $this->vehicle_name,
                'license_plate' => $this->license_plate,
                'mileage' => $this->mileage,
                'color' => $this->color,
                'chassis_number' => $this->chassis_number,
                'engine_number' => $this->engine_number,
                'brand' => $this->vehicle->brand->name,
                'model' => $this->vehicle->model->name,
                'type' => $this->vehicle->type->name,
                'transmission' => [
                    'name' => $this->vehicle->transmission->name,
                    'description' => $this->vehicle->transmission->description,
                    ],
                'fuel_type' => $this->vehicle->fuel_type,
                'generation' => $this->vehicle->generation,
                'market_period' => $this->vehicle->market_period,
                'origin' => $this->vehicle->origin->name,
                'cc' => $this->vehicle->cc,
                'year' => $this->vehicle->year,
                // 'display' => $this->vehicle_display,
            ],

            'inspection_date' => $this->inspection_date?->format('Y-m-d H:i:s'),

            'status' => [
                'value' => $this->status,
                'label' => $this->status_label,
                'color' => $this->status_color,
                'icon' => $this->status_icon,
            ],

            'progress_percentage' => $this->progress_percentage,

            'notes' => $this->notes,
            // 'settings' => $this->settings,

            // 'document_url' => $this->document_url,

            'template' => [
                'id' => $this->template?->id,
                'name' => $this->template?->name,
            ],

            'results' => $this->results->map(function ($result) {
                return [
                    'id' => $result->id,
                    'section_item_id' => $result->section_item_id,
                    'value' => $result->value,
                    'notes' => $result->notes,
                    'extra_data' => $result->extra_data,
                ];
            }),

            'images' => $this->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'type' => $image->type ?? null,
                    'url' => asset('storage/' . $image->path),
                ];
            }),

            'repair_estimations' => $this->repairEstimations->map(function ($repair) {
                return [
                    'id' => $repair->id,
                    'description' => $repair->description,
                    'estimated_cost' => $repair->estimated_cost,
                ];
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
