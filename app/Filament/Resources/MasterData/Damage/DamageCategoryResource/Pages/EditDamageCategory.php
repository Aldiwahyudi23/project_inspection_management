<?php

namespace App\Filament\Resources\MasterData\Damage\DamageCategoryResource\Pages;

use App\Filament\Resources\MasterData\Damage\DamageCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDamageCategory extends EditRecord
{
    protected static string $resource = DamageCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
