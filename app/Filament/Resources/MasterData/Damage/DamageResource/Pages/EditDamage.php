<?php

namespace App\Filament\Resources\MasterData\Damage\DamageResource\Pages;

use App\Filament\Resources\MasterData\Damage\DamageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDamage extends EditRecord
{
    protected static string $resource = DamageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
