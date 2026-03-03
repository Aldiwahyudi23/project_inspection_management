<?php

namespace App\Filament\Resources\FormBuilder\InspectionTemplateResource\Pages;

use App\Filament\Resources\FormBuilder\InspectionTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInspectionTemplate extends ViewRecord
{
    protected static string $resource = InspectionTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
