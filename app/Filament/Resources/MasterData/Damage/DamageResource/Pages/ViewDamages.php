<?php

namespace App\Filament\Resources\MasterData\Damage\DamageResource\Pages;

use App\Filament\Resources\MasterData\Damage\DamageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDamages extends ViewRecord
{
    protected static string $resource = DamageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
