<?php

namespace App\Filament\Resources\MasterData\Damage\DamageCategoryResource\Pages;

use App\Filament\Resources\MasterData\Damage\DamageCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDamageCategory extends CreateRecord
{
    protected static string $resource = DamageCategoryResource::class;
}
