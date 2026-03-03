<?php

namespace Database\Seeders;

use App\Models\FormBuilder\InspectionTemplate;
use App\Models\FormBuilder\MenuSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InspectionTemplateSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            // Create inspection template
            $template = InspectionTemplate::firstOrCreate(
                ['name' => 'Aldi Wahyudi'],
                [
                    'description' => 'Template inspeksi kendaraan standar Aldi Wahyudi',
                    'settings' => json_encode([
                        'allow_photos' => true,
                        'max_photos_per_section' => 10,
                        'require_damage_notes' => true,
                        'default_currency' => 'IDR',
                        'enable_gps_location' => true,
                        'auto_save_interval' => 30, // seconds
                        'signature_required' => true,
                        'show_pricing' => true,
                    ]),
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

            $this->command->info("Template created: {$template->name}");

            // Create menu sections
            $menuSections = [
                // Menu type sections
                ['name' => 'Dokumen', 'section_type' => 'menu', 'sort_order' => 1],
                ['name' => 'Foto', 'section_type' => 'menu', 'sort_order' => 2],
                ['name' => 'Depan', 'section_type' => 'menu', 'sort_order' => 3],
                ['name' => 'Kiri', 'section_type' => 'menu', 'sort_order' => 4],
                ['name' => 'Belakang', 'section_type' => 'menu', 'sort_order' => 5],
                ['name' => 'Kanan', 'section_type' => 'menu', 'sort_order' => 6],
                ['name' => 'Interior', 'section_type' => 'menu', 'sort_order' => 7],
                
                // Damage type section
                ['name' => 'Lainnya', 'section_type' => 'damage', 'sort_order' => 8],
            ];

            $sectionCount = 0;
            foreach ($menuSections as $sectionData) {
                $section = MenuSection::firstOrCreate(
                    [
                        'template_id' => $template->id,
                        'name' => $sectionData['name']
                    ],
                    [
                        'section_type' => $sectionData['section_type'],
                        'sort_order' => $sectionData['sort_order'],
                        'is_active' => true,
                    ]
                );
                $sectionCount++;
                
                $this->command->info("  Section created: {$section->name} ({$section->section_type})");
            }

            $this->command->info('Inspection template seeded successfully!');
            $this->command->info("Template: {$template->name}");
            $this->command->info("Total Sections: {$sectionCount}");
            $this->command->info("Menu Sections: 7");
            $this->command->info("Damage Section: 1 (Lainnya)");
        });
    }
}