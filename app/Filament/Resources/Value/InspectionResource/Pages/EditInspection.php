<?php

namespace App\Filament\Resources\Value\InspectionResource\Pages;

use App\Filament\Resources\Value\InspectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInspection extends EditRecord
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
