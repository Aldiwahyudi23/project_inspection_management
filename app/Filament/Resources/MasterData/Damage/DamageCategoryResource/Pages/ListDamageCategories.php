<?php

namespace App\Filament\Resources\MasterData\Damage\DamageCategoryResource\Pages;

use App\Filament\Resources\MasterData\Damage\DamageCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDamageCategories extends ListRecords
{
    protected static string $resource = DamageCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
