<?php

namespace App\Filament\Resources\FormBuilder\MenuSectionResource\Pages;

use App\Filament\Resources\FormBuilder\MenuSectionResource;
use App\Models\FormBuilder\MenuSection;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMenuSection extends EditRecord
{
    protected static string $resource = MenuSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),

            Actions\ActionGroup::make(
                $this->getOtherMenusActions()
            )
            ->label('Beralih ke Menu Lain')
            ->icon('heroicon-o-arrows-right-left')
            ->color('info')
            ->button()
            ->visible(fn () => $this->hasOtherMenus()),
        ];
    }

    // Method untuk mendapatkan actions menu lain
    private function getOtherMenusActions(): array
    {
        $currentMenu = $this->record;
        
        return MenuSection::where('template_id', $currentMenu->template_id)
            ->where('id', '!=', $currentMenu->id)
            ->get()
            ->map(function (MenuSection $menu) {
                return Actions\Action::make("switch_to_{$menu->id}")
                    ->label($menu->name)
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('gray')
                    ->url(MenuSectionResource::getUrl('edit', ['record' => $menu->id]))
                    ->openUrlInNewTab(false);
            })
            ->toArray();
    }

    // Method untuk cek apakah ada menu lain
    private function hasOtherMenus(): bool
    {
        return MenuSection::where('template_id', $this->record->template_id)
            ->where('id', '!=', $this->record->id)
            ->exists();
    }
}
