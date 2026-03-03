<?php

namespace App\Filament\Resources\FormBuilder\InspectionTemplateResource\Pages;

use App\Filament\Resources\FormBuilder\InspectionTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInspectionTemplates extends ListRecords
{
    protected static string $resource = InspectionTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
