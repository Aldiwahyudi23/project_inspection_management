<?php

namespace Database\Seeders;

use App\Models\MasterData\Damage\DamageCategory;
use App\Models\MasterData\Damage\DamagePrice;
use App\Models\MasterData\Damage\Damages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DamageSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            // Create Damage Categories
            $categories = [
                ['name' => 'Body & Eksterior', 'code' => 'BODY'],
                ['name' => 'Mesin & Kelistrikan', 'code' => 'ENGINE'],
                ['name' => 'Rangka & Chassis', 'code' => 'CHASSIS'],
                ['name' => 'Pilar & Struktur', 'code' => 'PILLAR'],
                ['name' => 'Transmisi & Drivetrain', 'code' => 'TRANSMISSION'],
                ['name' => 'Kaki-Kaki & Suspensi', 'code' => 'SUSPENSION'],
                ['name' => 'Interior', 'code' => 'INTERIOR'],
                ['name' => 'Kaca & Kaca Film', 'code' => 'GLASS'],
                ['name' => 'Ban & Velg', 'code' => 'WHEEL'],
                ['name' => 'Lampu & Penerangan', 'code' => 'LIGHT'],
            ];

            $categoryMap = [];
            foreach ($categories as $category) {
                $cat = DamageCategory::firstOrCreate(
                    ['code' => $category['code']],
                    [
                        'name' => $category['name'],
                        'is_active' => true
                    ]
                );
                $categoryMap[$category['code']] = $cat->id;
            }

            // Create Damages with their details
            $damages = [
                // ============================================
                // 🔹 BODY & EKSTERIOR
                // ============================================
                'BODY' => [
                    [
                        'label' => 'Penyok Kecil',
                        'value' => 'Ada penyok kecil diameter < 3cm',
                    ],
                    [
                        'label' => 'Penyok Sedang',
                        'value' => 'Ada penyok sedang diameter 3-5cm',
                    ],
                    [
                        'label' => 'Penyok Besar',
                        'value' => 'Ada penyok besar diameter > 5cm',
                        'handling' => 'Perlu perbaikan ketok dan cat',
                        'prices' => [
                            ['unit' => 'point', 'price' => 300000, 'applies_to' => 'body_panel'],
                            ['unit' => 'panel', 'price' => 600000, 'applies_to' => 'full_panel'],
                        ]
                    ],
                    [
                        'label' => 'Baret Halus',
                        'value' => 'Ada baret halus di permukaan cat',
                    ],
                    [
                        'label' => 'Baret Dalam',
                        'value' => 'Ada baret cukup dalam sampai lapisan primer',
                        'handling' => 'Perlu repainting pada area tersebut',
                        'prices' => [
                            ['unit' => 'panel', 'price' => 500000, 'applies_to' => 'full_panel'],
                        ]
                    ],
                    [
                        'label' => 'Cat Mengelupas',
                        'value' => 'Cat mulai mengelupas di beberapa bagian',
                        'handling' => 'Perlu pengecatan ulang',
                        'prices' => [
                            ['unit' => 'panel', 'price' => 800000, 'applies_to' => 'standard_panel'],
                        ]
                    ],
                    [
                        'label' => 'Renggang Panel',
                        'value' => 'Celah antar panel tidak rata',
                        'handling' => 'Perlu adjustment alignment panel',
                        'prices' => [
                            ['unit' => 'adjustment', 'price' => 200000, 'applies_to' => 'panel_alignment'],
                        ]
                    ],
                    [
                        'label' => 'Karat Spot',
                        'value' => 'Ada spot karat kecil',
                    ],
                    [
                        'label' => 'Karat Parah',
                        'value' => 'Ada karat cukup parah dan meluas',
                        'handling' => 'Perlu cut & weld dan cat ulang',
                        'prices' => [
                            ['unit' => 'area', 'price' => 750000, 'applies_to' => 'rust_repair'],
                        ]
                    ],
                    [
                        'label' => 'Bergelombang',
                        'value' => 'Permukaan panel tidak rata bergelombang',
                        'handling' => 'Perlu panel beating dan cat',
                        'prices' => [
                            ['unit' => 'panel', 'price' => 400000, 'applies_to' => 'body_panel'],
                        ]
                    ],
                    [
                        'label' => 'Bekas Tabrakan',
                        'value' => 'Ada bekas tabrakan sebelumnya',
                        'handling' => 'Perlu perbaikan body repair',
                        'prices' => [
                            ['unit' => 'panel', 'price' => 700000, 'applies_to' => 'collision_repair'],
                        ]
                    ],
                ],

                // ============================================
                // 🔹 MESIN & KELISTRIKAN
                // ============================================
                'ENGINE' => [
                    [
                        'label' => 'Rembes Oli Ringan',
                        'value' => 'Ada rembesan oli ringan di gasket',
                    ],
                    [
                        'label' => 'Rembes Oli Parah',
                        'value' => 'Ada kebocoran oli cukup parah',
                        'handling' => 'Perlu ganti gasket/seal',
                        'prices' => [
                            ['unit' => 'seal', 'price' => 350000, 'applies_to' => 'gasket_replacement'],
                        ]
                    ],
                    [
                        'label' => 'Rembes Coolant',
                        'value' => 'Ada rembesan cairan pendingin',
                        'handling' => 'Perlu ganti selang/radiator seal',
                        'prices' => [
                            ['unit' => 'hose', 'price' => 250000, 'applies_to' => 'hose_replacement'],
                        ]
                    ],
                    [
                        'label' => 'Suara Kasar',
                        'value' => 'Mesin berbunyi kasar saat idle',
                    ],
                    [
                        'label' => 'Knocking',
                        'value' => 'Ada suara knocking dari mesin',
                    ],
                    [
                        'label' => 'Riwayat Overheat',
                        'value' => 'Pernah mengalami overheat sebelumnya',
                    ],
                    [
                        'label' => 'Asap Putih',
                        'value' => 'Keluarkan asap putih dari knalpot',
                    ],
                    [
                        'label' => 'Asap Biru',
                        'value' => 'Keluarkan asap biru dari knalpot',
                    ],
                    [
                        'label' => 'Getaran Keras',
                        'value' => 'Mesin bergetar keras saat idle',
                    ],
                    [
                        'label' => 'Susah Start',
                        'value' => 'Mesin sulit dinyalakan',
                    ],
                    [
                        'label' => 'Idle Tidak Stabil',
                        'value' => 'RPM naik turun tidak stabil',
                    ],
                ],

                // ============================================
                // 🔹 RANGKA & CHASSIS
                // ============================================
                'CHASSIS' => [
                    [
                        'label' => 'Rangka Tidak Lurus',
                        'value' => 'Rangka terlihat tidak lurus',
                        'handling' => 'Perlu frame straightening',
                        'prices' => [
                            ['unit' => 'pull', 'price' => 2500000, 'applies_to' => 'frame_straightening'],
                        ]
                    ],
                    [
                        'label' => 'Welding Bekas',
                        'value' => 'Ada tanda welding bekas perbaikan',
                    ],
                    [
                        'label' => 'Karat Rangka',
                        'value' => 'Ada karat pada rangka',
                        'handling' => 'Perlu anti rust treatment',
                        'prices' => [
                            ['unit' => 'area', 'price' => 500000, 'applies_to' => 'rust_treatment'],
                        ]
                    ],
                    [
                        'label' => 'Retak Rangka',
                        'value' => 'Ada retakan pada rangka',
                        'handling' => 'Perlu welding reinforcement',
                        'prices' => [
                            ['unit' => 'weld', 'price' => 1000000, 'applies_to' => 'frame_welding'],
                        ]
                    ],
                ],

                // ============================================
                // 🔹 PILAR & STRUKTUR
                // ============================================
                'PILLAR' => [
                    [
                        'label' => 'Pilar A Penyok',
                        'value' => 'Ada penyok di pilar A',
                        'handling' => 'Perlu perbaikan pilar',
                        'prices' => [
                            ['unit' => 'pillar', 'price' => 1200000, 'applies_to' => 'pillar_a'],
                        ]
                    ],
                    [
                        'label' => 'Pilar B Penyok',
                        'value' => 'Ada penyok di pilar B',
                        'handling' => 'Perlu perbaikan pilar',
                        'prices' => [
                            ['unit' => 'pillar', 'price' => 1000000, 'applies_to' => 'pillar_b'],
                        ]
                    ],
                    [
                        'label' => 'Pilar C Penyok',
                        'value' => 'Ada penyok di pilar C',
                        'handling' => 'Perlu perbaikan pilar',
                        'prices' => [
                            ['unit' => 'pillar', 'price' => 900000, 'applies_to' => 'pillar_c'],
                        ]
                    ],
                    [
                        'label' => 'Karat Pilar',
                        'value' => 'Ada karat pada pilar',
                        'handling' => 'Perlu rust repair dan cat',
                        'prices' => [
                            ['unit' => 'pillar', 'price' => 800000, 'applies_to' => 'pillar_rust'],
                        ]
                    ],
                ],

                // ============================================
                // 🔹 TRANSMISI & DRIVETRAIN
                // ============================================
                'TRANSMISSION' => [
                    [
                        'label' => 'Slip Ringan',
                        'value' => 'Transmisi terkadang slip ringan',
                    ],
                    [
                        'label' => 'Slip Parah',
                        'value' => 'Transmisi sering slip parah',
                        'handling' => 'Perlu overhaul transmisi',
                        'prices' => [
                            ['unit' => 'overhaul', 'price' => 3500000, 'applies_to' => 'transmission_overhaul'],
                        ]
                    ],
                    [
                        'label' => 'Bunyi Whining',
                        'value' => 'Ada bunyi whining dari transmisi',
                    ],
                    [
                        'label' => 'Hentakan Keras',
                        'value' => 'Perpindahan gigi terasa hentakan keras',
                    ],
                    [
                        'label' => 'Kopling Slip',
                        'value' => 'Kopling mulai slip',
                        'handling' => 'Perlu ganti kopling set',
                        'prices' => [
                            ['unit' => 'set', 'price' => 1500000, 'applies_to' => 'clutch_kit'],
                        ]
                    ],
                    [
                        'label' => 'CV Joint Bermasalah',
                        'value' => 'CV joint berbunyi klik saat belok',
                        'handling' => 'Perlu ganti CV joint',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 850000, 'applies_to' => 'cv_joint'],
                        ]
                    ],
                ],

                // ============================================
                // 🔹 KAKI-KAKI & SUSPENSI
                // ============================================
                'SUSPENSION' => [
                    [
                        'label' => 'Shock Lembek',
                        'value' => 'Shock absorber sudah lembek',
                    ],
                    [
                        'label' => 'Shock Bocor',
                        'value' => 'Shock absorber bocor oli',
                        'handling' => 'Perlu ganti shock absorber',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 650000, 'applies_to' => 'shock_replacement'],
                        ]
                    ],
                    [
                        'label' => 'Bunyi Bunyi',
                        'value' => 'Ada bunyi saat melewati jalan tidak rata',
                    ],
                    [
                        'label' => 'Ball Joint Longgar',
                        'value' => 'Ball joint mulai longgar',
                        'handling' => 'Perlu ganti ball joint',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 350000, 'applies_to' => 'ball_joint'],
                        ]
                    ],
                    [
                        'label' => 'Spring Patah',
                        'value' => 'Pegas suspensi patah',
                        'handling' => 'Perlu ganti spring',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 750000, 'applies_to' => 'spring_replacement'],
                        ]
                    ],
                ],

                // ============================================
                // 🔹 INTERIOR
                // ============================================
                'INTERIOR' => [
                    [
                        'label' => 'Jok Kotor',
                        'value' => 'Jok agak kotor butuh cleaning',
                    ],
                    [
                        'label' => 'Jok Robek',
                        'value' => 'Jok ada sobekan/robek',
                        'handling' => 'Perlu jok reupholstery',
                        'prices' => [
                            ['unit' => 'seat', 'price' => 850000, 'applies_to' => 'seat_reupholster'],
                        ]
                    ],
                    [
                        'label' => 'Dashboard Retak',
                        'value' => 'Dashboard ada retakan',
                        'handling' => 'Perlu perbaikan dashboard',
                        'prices' => [
                            ['unit' => 'dashboard', 'price' => 1500000, 'applies_to' => 'dashboard_repair'],
                        ]
                    ],
                    [
                        'label' => 'Karpet Rusak',
                        'value' => 'Karpet sobek/rusak',
                        'handling' => 'Perlu ganti karpet',
                        'prices' => [
                            ['unit' => 'set', 'price' => 1200000, 'applies_to' => 'carpet_replacement'],
                        ]
                    ],
                    [
                        'label' => 'Plafon Kendur',
                        'value' => 'Plafon mulai kendur',
                        'handling' => 'Perlu ganti headliner',
                        'prices' => [
                            ['unit' => 'headliner', 'price' => 950000, 'applies_to' => 'headliner_replacement'],
                        ]
                    ],
                    [
                        'label' => 'Bau Apek',
                        'value' => 'Ada bau apek dalam kabin',
                    ],
                ],

                // ============================================
                // 🔹 KACA & KACA FILM
                // ============================================
                'GLASS' => [
                    [
                        'label' => 'Chip Kecil',
                        'value' => 'Ada chip kecil di kaca',
                    ],
                    [
                        'label' => 'Retak Kaca',
                        'value' => 'Kaca ada retakan',
                        'handling' => 'Perlu ganti kaca',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 2500000, 'applies_to' => 'windshield'],
                        ]
                    ],
                    [
                        'label' => 'Kaca Film Rusak',
                        'value' => 'Kaca film mengelupas/bubble',
                        'handling' => 'Perlu ganti kaca film',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 500000, 'applies_to' => 'tint_replacement'],
                        ]
                    ],
                ],

                // ============================================
                // 🔹 BAN & VELG
                // ============================================
                'WHEEL' => [
                    [
                        'label' => 'Velg Baretan Ringan',
                        'value' => 'Velg ada baretan ringan',
                    ],
                    [
                        'label' => 'Velg Penyok',
                        'value' => 'Velag ada penyok',
                        'handling' => 'Perlu velg repair',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 400000, 'applies_to' => 'rim_repair'],
                        ]
                    ],
                    [
                        'label' => 'Ban Mulai Botak',
                        'value' => 'Ban mulai botak tapi masih layak',
                    ],
                    [
                        'label' => 'Ban Botak Parah',
                        'value' => 'Ban sudah botak parah',
                        'handling' => 'Perlu ganti ban',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 1200000, 'applies_to' => 'tire_replacement'],
                        ]
                    ],
                    [
                        'label' => 'Ban Benjol',
                        'value' => 'Ban ada benjolan di sidewall',
                        'handling' => 'Perlu ganti ban',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 1200000, 'applies_to' => 'tire_replacement'],
                        ]
                    ],
                ],

                // ============================================
                // 🔹 LAMPU & PENERANGAN
                // ============================================
                'LIGHT' => [
                    [
                        'label' => 'Lampu Kuning',
                        'value' => 'Lensa lampu mulai menguning',
                    ],
                    [
                        'label' => 'Lampu Pecah',
                        'value' => 'Lampu pecah/retak',
                        'handling' => 'Perlu ganti lampu assembly',
                        'prices' => [
                            ['unit' => 'piece', 'price' => 850000, 'applies_to' => 'headlight_assembly'],
                        ]
                    ],
                    [
                        'label' => 'Kondensasi Lampu',
                        'value' => 'Ada kabut dalam housing lampu',
                        'handling' => 'Perlu seal repair',
                        'prices' => [
                            ['unit' => 'repair', 'price' => 200000, 'applies_to' => 'light_seal_repair'],
                        ]
                    ],
                    [
                        'label' => 'Lampu Mati',
                        'value' => 'Salah satu lampu mati',
                        'handling' => 'Perlu ganti bohlam',
                        'prices' => [
                            ['unit' => 'bulb', 'price' => 150000, 'applies_to' => 'bulb_replacement'],
                        ]
                    ],
                ],
            ];

            // Insert damages and their prices
            $damageCount = 0;
            $priceCount = 0;

            foreach ($damages as $categoryCode => $damageList) {
                $categoryId = $categoryMap[$categoryCode] ?? null;
                
                if ($categoryId) {
                    foreach ($damageList as $damageData) {
                        // Create damage
                        $damage = Damages::firstOrCreate(
                            ['value' => $damageData['value']],
                            [
                                'damage_category_id' => $categoryId,
                                'label' => $damageData['label'],
                                'handling' => $damageData['handling'] ?? null,
                                'is_active' => true,
                            ]
                        );

                        $damageCount++;

                        // Create prices only if handling exists
                        if (isset($damageData['handling']) && isset($damageData['prices'])) {
                            foreach ($damageData['prices'] as $priceData) {
                                DamagePrice::firstOrCreate(
                                    [
                                        'damage_id' => $damage->id,
                                        'unit' => $priceData['unit'],
                                        'applies_to' => $priceData['applies_to'] ?? null,
                                    ],
                                    [
                                        'price' => $priceData['price'],
                                        'currency' => 'IDR',
                                    ]
                                );
                                $priceCount++;
                            }
                        }
                    }
                }
            }

            $this->command->info('Damage data seeded successfully!');
            $this->command->info("Total Categories: " . count($categories));
            $this->command->info("Total Damages: $damageCount");
            $this->command->info("Total Damage with Prices: $priceCount");
            $this->command->info("Note: Only damages with 'handling' have price entries");
        });
    }
}