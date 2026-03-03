<?php

namespace App\Filament\Resources\FormBuilder\MenuSectionResource\Pages;

use App\Filament\Resources\FormBuilder\MenuSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMenuSections extends ListRecords
{
    protected static string $resource = MenuSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
