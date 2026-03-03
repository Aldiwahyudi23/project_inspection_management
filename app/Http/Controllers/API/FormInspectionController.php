<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectDB\VehicleData\Transmission;
use App\Models\Inspection;
use App\Models\FormBuilder\InspectionTemplate;
use App\Models\FormBuilder\MenuSection;
use App\Models\FormBuilder\SectionItem;
use App\Models\InspectionResult;
use App\Models\InspectionImage;
use App\Models\MasterData\InspectionItem;
use App\Models\MasterData\Damage\DamageCategory;
use App\Models\MasterData\Damage\Damages;
use App\Services\VehicleApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class FormInspectionController extends Controller
{
    public function __construct(
        protected VehicleApiService $vehicleApi
    ) {
        //
    }

    // ========================================= Untuk Menghandle Form Inspection (Filament)=====================================   
    private $damageMapCache = null;

    /**
     * Get inspection with full template structure (optimized for Filament form)
     */
    public function getInspectionTemplate($inspectionId)
    {
        try {
            $inspection = Inspection::with(['template', 'vehicle'])->findOrFail($inspectionId);
            
            // Get template structure with all settings
            $templateStructure = $this->getFullTemplateStructure(
                $inspection->template_id, 
                $inspectionId
            );

            $result = $this->vehicleApi->getVehicleForInspectionForm($inspection->vehicle_id);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'debug'   => $result['error'] ?? null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'inspection' => [
                        'id' => $inspection->id,
                        'template_id' => $inspection->template_id,
                        'vehicle_id' => $inspection->vehicle_id,
                        'atribute_vehicle' => $result['data'] ?? null,
                        'vehicle_name' => $inspection->vehicle_name,
                        'license_plate' => $inspection->license_plate,
                        'status' => $inspection->status,
                        'progress_percentage' => $inspection->progress_percentage,
                        'can_be_edited' => $inspection->canBeEdited(),
                    ],
                    'template' => $templateStructure,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inspection template',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get full template structure with all settings
     */
    private function getFullTemplateStructure($templateId, $inspectionId = null)
    {
        // Get template with sections
        $template = InspectionTemplate::with([
            'menuSections' => function ($query) {
                $query->where('is_active', true)
                      ->orderBy('sort_order');
            }
        ])->findOrFail($templateId);

        $structure = [];
        
        foreach ($template->menuSections as $section) {
            $sectionData = [
                'id' => $section->id,
                'name' => $section->name,
                'section_type' => $section->section_type,
                'sort_order' => $section->sort_order,
                'is_damage_section' => $section->section_type === 'damage',
                'items' => []
            ];

            // Get items for this section
            $items = SectionItem::with(['inspectionItem'])
                ->where('section_id', $section->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($items as $item) {
                $itemData = $this->transformSectionItem($item, $inspectionId);
                $sectionData['items'][] = $itemData;
            }

            $structure[] = $sectionData;
        }

        return [
            'id' => $template->id,
            'name' => $template->name,
            'settings' => $template->settings ?? [],
            'sections' => $structure
        ];
    }

    /**
     * Transform section item data for frontend consumption
     */
    private function transformSectionItem(SectionItem $item, $inspectionId = null)
    {
        // Ambil settings asli dari database
        $dbSettings = $item->settings ?? [];
        
        // Flatten settings jika ada nested 'settings'
        $flattenedSettings = $this->flattenSettings($dbSettings);
        
        // Transform damage_ids dalam settings menjadi data lengkap
        $transformedSettings = $this->transformDamageIdsInSettings($flattenedSettings);
        
        // Base item data
        $itemData = [
            'id' => $item->id,
            'section_id' => $item->section_id,
            'inspection_item_id' => $item->inspection_item_id,
            'input_type' => $item->input_type,
            'sort_order' => $item->sort_order,
            'is_active' => $item->is_active,
            'is_visible' => $item->is_visible,
            'is_required' => $item->is_required,
            
            // Inspection item details
            'inspection_item' => $item->inspectionItem ? [
                'id' => $item->inspectionItem->id,
                'name' => $item->inspectionItem->name,
                'description' => $item->inspectionItem->description,
            ] : null,
            
            // Settings yang sudah diratakan dan ditransform
            'settings' => $transformedSettings,
            
            // Current result if exists
            'current_result' => $inspectionId ? $this->getItemResult($inspectionId, $item->inspection_item_id) : null,
        ];

        return $itemData;
    }

    /**
     * Flatten settings dengan menggabungkan nested 'settings' ke level atas
     */
    private function flattenSettings($settings)
    {
        if (!is_array($settings)) {
            return $settings;
        }

        $flattened = [];
        
        // Jika ada key 'settings' di dalam settings, gabungkan dengan level atas
        foreach ($settings as $key => $value) {
            if ($key === 'settings' && is_array($value)) {
                // Rekursif untuk nested settings
                $nestedFlattened = $this->flattenSettings($value);
                foreach ($nestedFlattened as $nestedKey => $nestedValue) {
                    $flattened[$nestedKey] = $nestedValue;
                }
            } elseif (is_array($value)) {
                // Untuk array biasa, proses rekursif tapi tetap dengan key yang sama
                $flattened[$key] = $this->flattenSettings($value);
            } else {
                $flattened[$key] = $value;
            }
        }
        
        return $flattened;
    }

    /**
     * Transform semua damage_ids dalam settings (rekursif) menjadi data lengkap
     */
    private function transformDamageIdsInSettings($settings)
    {
        if (!is_array($settings)) {
            return $settings;
        }
        
        $result = [];
        
        foreach ($settings as $key => $value) {
            if ($key === 'damage_ids' && is_array($value)) {
                // Transform damage_ids menjadi data lengkap
                $result[$key] = $this->transformDamageIds($value);
            } elseif ($key === 'options' && is_array($value)) {
                // Transform options yang mungkin memiliki damage_ids
                $result[$key] = array_map(function ($option) {
                    if (is_array($option)) {
                        if (isset($option['settings']) && is_array($option['settings'])) {
                            $option['settings'] = $this->transformDamageIdsInSettings($option['settings']);
                        }
                        if (isset($option['damage_ids']) && is_array($option['damage_ids'])) {
                            $option['damage_ids'] = $this->transformDamageIds($option['damage_ids']);
                        }
                    }
                    return $option;
                }, $value);
            } elseif (is_array($value)) {
                // Rekursif untuk nested arrays lainnya
                $result[$key] = $this->transformDamageIdsInSettings($value);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Transform damage IDs menjadi data lengkap
     */
    private function transformDamageIds($damageIds)
    {
        if (empty($damageIds) || !is_array($damageIds)) {
            return [];
        }
        
        $result = [];
        
        foreach ($damageIds as $damageId) {
            // Konversi ke string untuk konsistensi
            $damageId = (string) $damageId;
            
            // Cari di cache
            if (isset($this->damageMapCache[$damageId])) {
                $result[] = $this->damageMapCache[$damageId];
            } else {
                // Fallback: cari langsung di database
                $damage = Damages::find($damageId);
                if ($damage) {
                    $result[] = [
                        'id' => (string) $damage->id,
                        'label' => $damage->label,
                        'value' => $damage->value,
                        'description' => $damage->description,
                    ];
                }
            }
        }
        
        return $result;
    }

    /**
     * Get item result
     */
    private function getItemResult($inspectionId, $itemId)
    {
        $result = InspectionResult::where('inspection_id', $inspectionId)
            ->where('inspection_item_id', $itemId)
            ->with('inspectionImages')
            ->first();
            
        if (!$result) {
            return null;
        }
        
        return [
            'id' => $result->id,
            'status' => $result->status,
            'note' => $result->note,
            'extra_data' => $result->extra_data ?? [],
            'images' => $result->inspectionImages->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->image_url,
                    'caption' => $image->caption,
                ];
            }),
        ];
    }

    // ========================= Untuk menghandle Imga di Form Inspection=====================================
    /**
     * Upload Single / Multiple Images
     */
    // public function uploadImages(Request $request)
    // {
    //     $request->validate([
    //         'inspection_id' => 'required|exists:inspections,id',
    //         'inspection_item_id' => 'required|exists:inspection_items,id',
    //         'images' => 'required',
    //         'images.*' => 'image|mimes:jpg,jpeg,png|max:5120', // 5MB
    //         'item_id' => 'nullable|required', // untuk keperluan nested required validation
    //     ]);

    //     $uploadedImages = [];

    //     $files = is_array($request->file('images'))
    //         ? $request->file('images')
    //         : [$request->file('images')];

    //     foreach ($files as $file) {

    //         $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

    //         $path = $file->storeAs(
    //             "inspection/images",
    //             $fileName,
    //             'public'
    //         );

    //         $image = InspectionImage::create([
    //             'inspection_id' => $request->inspection_id,
    //             'inspection_item_id' => $request->inspection_item_id,
    //             'image_path' => $path,
    //             'caption' => null,
    //         ]);

    //         $uploadedImages[] = [
    //             'id' => $image->id,
    //             'image_url' => $image->image_url,
    //             'caption' => $image->caption,
    //         ];
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Image uploaded successfully',
    //         'data' => $uploadedImages,
    //     ]);
    // }


    public function uploadImages(Request $request)
    {
        try {
            $request->validate([
                'inspection_id' => 'required|exists:inspections,id',
                'inspection_item_id' => 'required|exists:inspection_items,id',
                'item_id' => 'required|integer', // ID dari SectionItem
                'images' => 'required',
                'selected_option_value' => 'nullable|string' // untuk radio/select/checkbox
            ]);

            // Ambil data SectionItem untuk mendapatkan settings
            $sectionItem = SectionItem::find($request->item_id);
            
            if (!$sectionItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section item not found',
                    'data' => null
                ], 404);
            }

            // Dapatkan settings berdasarkan tipe input
            $settings = $this->getImageSettings($sectionItem, $request->selected_option_value);
            
            // Validasi file
            $files = is_array($request->file('images'))
                ? $request->file('images')
                : [$request->file('images')];

            // Cek max_files
            $maxFiles = (int)($settings['max_files'] ?? 1);
            if (count($files) > $maxFiles) {
                return response()->json([
                    'success' => false,
                    'message' => "Maximum {$maxFiles} file(s) allowed",
                    'data' => null
                ], 400);
            }

            $uploadedImages = [];
            $errors = [];

            foreach ($files as $index => $file) {
                try {
                    // Validasi tipe file (allowed_mimes)
                    $allowedMimes = $settings['allowed_mimes'] ?? ['jpg', 'jpeg', 'png'];
                    $extension = strtolower($file->getClientOriginalExtension());
                    
                    if (!in_array($extension, $allowedMimes)) {
                        $errors[] = "File " . ($index + 1) . ": Format tidak diizinkan. Harus: " . implode(', ', $allowedMimes);
                        continue;
                    }

                    // Validasi ukuran file sebelum kompresi
                    $maxSize = (int)($settings['max_size'] ?? 2048) * 1024; // Convert KB to bytes
                    if ($file->getSize() > $maxSize) {
                        $maxSizeMB = $maxSize / 1024 / 1024;
                        $errors[] = "File " . ($index + 1) . ": Ukuran terlalu besar (maks {$maxSizeMB}MB)";
                        continue;
                    }

                    // Proses kompresi gambar
                    $processedFile = $this->compressImage($file, $settings);
                    
                    // Simpan file
                    $fileName = Str::uuid() . '.' . $extension;
                    $path = $file->storeAs(
                        "inspection/images",
                        $fileName,
                        'public'
                    );

                    // Buat record di database
                    $image = InspectionImage::create([
                        'inspection_id' => $request->inspection_id,
                        'inspection_item_id' => $request->inspection_item_id,
                        'image_path' => $path,
                        'caption' => null,
                    ]);

                    $uploadedImages[] = [
                        'id' => $image->id,
                        'image_url' => $image->image_url,
                        'caption' => $image->caption,
                        'size' => Storage::disk('public')->size($path) / 1024, // size in KB
                    ];

                } catch (\Exception $e) {
                    Log::error('Error processing image: ' . $e->getMessage());
                    $errors[] = "File " . ($index + 1) . ": Gagal diproses - " . $e->getMessage();
                }
            }

            // Response dengan error jika ada
            if (!empty($errors) && empty($uploadedImages)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload images',
                    'errors' => $errors,
                    'data' => null
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => !empty($errors) ? 'Images uploaded with some errors' : 'Images uploaded successfully',
                'errors' => !empty($errors) ? $errors : null,
                'data' => $uploadedImages,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
                'data' => null
            ], 422);
        } catch (\Exception $e) {
            Log::error('Upload images error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Mendapatkan settings gambar berdasarkan tipe input dan option yang dipilih
     */
    private function getImageSettings(SectionItem $sectionItem, ?string $selectedOptionValue = null): array
    {
        $defaultSettings = [
            'max_size' => 2048,
            'max_files' => 1,
            'allowed_mimes' => ['jpg', 'jpeg', 'png'],
            'compression_quality' => 80
        ];

        // Jika input type bukan image (radio/select/checkbox) dan ada selected_option_value
        if ($sectionItem->input_type !== 'image' && $selectedOptionValue) {
            $options = $sectionItem->getSetting('options', []);
            
            // Cari option yang sesuai dengan selected_option_value
            foreach ($options as $option) {
                if (($option['value'] ?? '') == $selectedOptionValue) {
                    // Ambil settings dari option jika ada
                    $optionSettings = $option['settings'] ?? [];
                    
                    return [
                        'max_size' => $optionSettings['max_size'] ?? $defaultSettings['max_size'],
                        'max_files' => $optionSettings['max_files'] ?? $defaultSettings['max_files'],
                        'allowed_mimes' => $optionSettings['allowed_mimes'] ?? $defaultSettings['allowed_mimes'],
                        'compression_quality' => $optionSettings['compression_quality'] ?? $defaultSettings['compression_quality']
                    ];
                }
            }
        }

        // Untuk tipe image langsung atau fallback
        $settings = $sectionItem->settings ?? [];
        
        return [
            'max_size' => $settings['max_size'] ?? $defaultSettings['max_size'],
            'max_files' => $settings['max_files'] ?? $defaultSettings['max_files'],
            'allowed_mimes' => $settings['allowed_mimes'] ?? $defaultSettings['allowed_mimes'],
            'compression_quality' => $settings['compression_quality'] ?? $defaultSettings['compression_quality']
        ];
    }

    /**
     * Kompres gambar berdasarkan settings
     */
    private function compressImage($file, array $settings)
    {
        $quality = (int)($settings['compression_quality'] ?? 80);
        
        // Hanya kompres jika quality < 100
        if ($quality < 100) {
            try {
                $image = Image::read($file);
                
                // Resize jika max_width atau max_height ditentukan
                if (isset($settings['max_width']) && $settings['max_width'] > 0) {
                    $image->resize(width: $settings['max_width']);
                }
                
                if (isset($settings['max_height']) && $settings['max_height'] > 0) {
                    $image->resize(height: $settings['max_height']);
                }
                
                // Encode dengan quality yang ditentukan
                // Semakin tinggi quality → semakin besar ukuran
                // Semakin rendah quality → semakin kecil ukuran
                $encoded = $image->encodeByExtension(
                    $file->getClientOriginalExtension(),
                    quality: $quality
                );
                
                // Buat temporary file dengan hasil kompresi
                $tempPath = tempnam(sys_get_temp_dir(), 'img_');
                file_put_contents($tempPath, $encoded);
                
                // Buat UploadedFile baru
                return new \Illuminate\Http\UploadedFile(
                    $tempPath,
                    $file->getClientOriginalName(),
                    $file->getClientMimeType(),
                    null,
                    true // test mode
                );
                
            } catch (\Exception $e) {
                Log::error('Image compression failed: ' . $e->getMessage());
                // Jika kompresi gagal, return file original
                return $file;
            }
        }
        
        return $file;
    }


    /**
     * Delete Image (DB + File Storage)
     */
    public function deleteImage($id)
    {
        $image = InspectionImage::findOrFail($id);

        if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully'
        ]);
    }

    public function deleteItem(int $inspectionId, int $itemId)
    {
        DB::beginTransaction();

        try {

            // DELETE IMAGES
            $images = InspectionImage::where('inspection_id', $inspectionId)
                ->where('inspection_item_id', $itemId)
                ->get();

            foreach ($images as $image) {

                if (
                    $image->image_path &&
                    !str_starts_with($image->image_path, 'http') &&
                    Storage::disk('public')->exists($image->image_path)
                ) {
                    Storage::disk('public')->delete($image->image_path);
                }
            }

            InspectionImage::where('inspection_id', $inspectionId)
                ->where('inspection_item_id', $itemId)
                ->delete();

            // DELETE RESULT
            InspectionResult::where('inspection_id', $inspectionId)
                ->where('inspection_item_id', $itemId)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully',
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    // ─────────────────────────────────────────────────────────────
    // STATE: diisi saat proses, dipakai lintas method
    // ─────────────────────────────────────────────────────────────

    /** @var array<int, array> Map inspection_item_id → result dari payload */
    private array $payloadMap = [];

    /** @var array<int, SectionItem> Map section_item.id → SectionItem */
    private array $sectionItemMap = [];

    /** @var array<int, array> Map item_id (section_item.id) → result dari payload */
    private array $payloadByItemIdMap = [];

    /** @var array<int, bool> Map inspection_item_id → apakah final visible */
    private array $visibilityMap = [];

    /**
     * Set inspection_item_id yang namanya cocok dengan fitur kendaraan.
     * Item ini selalu visible + ikut validasi normal (required, format).
     *
     * @var array<int, bool> Map inspection_item_id → true
     */
    private array $featureItemIds = [];

    // ─────────────────────────────────────────────────────────────
    // ENTRY POINT
    // ─────────────────────────────────────────────────────────────

    public function saveForm(Request $request)
    {
        try {
            // ── 1. Validasi struktur payload ──────────────────────
            $validator = Validator::make($request->all(), [
                'inspection_id'                     => 'required|integer',
                'results'                           => 'required|array|min:1',
                'results.*.inspection_item_id'      => 'required|integer|min:1',
                'results.*.item_id'                 => 'nullable|integer',
                'results.*.status'                  => 'nullable',
                'results.*.note'                    => 'nullable|string',
                'results.*.extra_data'              => 'nullable|array',
                'results.*.extra_data.damage_ids'   => 'nullable|array',
                'results.*.extra_data.damage_ids.*' => 'integer',
                'results.*.image_ids'               => 'nullable|array',
                'results.*.image_ids.*'             => 'integer',
            ]);

            if ($validator->fails()) {
                return $this->error('Validasi payload gagal', 422, [
                    'errors' => $validator->errors()
                ]);
            }

            // ── 2. Cari Inspection ────────────────────────────────
            $inspection = Inspection::find($request->inspection_id);
            if (!$inspection) {
                return $this->error('Inspeksi tidak ditemukan', 404);
            }

            $allowedStatuses = ['in_progress', 'paused', 'revision'];
            if (!in_array($inspection->status, $allowedStatuses)) {
                return $this->error("Status inspeksi tidak valid: {$inspection->status}", 422);
            }

            // ── 2b. Load data vehicle dari Backend C via API ───────
            $vehicle  = null;
            $features = []; // fitur kendaraan untuk feature-item matching
            if ($inspection->vehicle_id) {
                $vehicleResult = $this->vehicleApi->getVehicleForInspectionForm($inspection->vehicle_id);
                if ($vehicleResult['success'] && !empty($vehicleResult['data'])) {
                    $vehicle = (object) $vehicleResult['data'];
                    $vehicle->pickup = (bool) ($vehicle->pickup ?? false);
                    $vehicle->box    = (bool) ($vehicle->box    ?? false);
                    // Normalisasi features → array of lowercase string untuk perbandingan
                    $features = array_map(
                        fn ($f) => mb_strtolower(trim((string) $f)),
                        (array) ($vehicleResult['data']['features'] ?? [])
                    );
                } else {
                    Log::warning('Vehicle data tidak tersedia, filter vehicle dilewati', [
                        'inspection_id' => $inspection->id,
                        'vehicle_id'    => $inspection->vehicle_id,
                    ]);
                }
            }

            // ── 3. Build payload map ──────────────────────────────
            // Map inspection_item_id → result (untuk required check & visibility)
            // Map item_id (section_item.id) → result (untuk trigger check)
            foreach ($request->results as $result) {
                $this->payloadMap[(int) $result['inspection_item_id']] = $result;
                if (!empty($result['item_id'])) {
                    $this->payloadByItemIdMap[(int) $result['item_id']] = $result;
                }
            }

            // ── 4. Load semua SectionItem dari template ───────────
            $sectionItems = SectionItem::with(['inspectionItem', 'menuSection'])
                ->whereHas('menuSection', fn ($q) =>
                    $q->where('template_id', $inspection->template_id)
                      ->where('is_active', true)
                )
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($sectionItems as $item) {
                $this->sectionItemMap[$item->id] = $item;
            }

            // ── 4b. Tandai item yang namanya cocok dengan fitur kendaraan ──
            // Perbandingan case-insensitive + trim
            if (!empty($features)) {
                foreach ($this->sectionItemMap as $sectionItem) {
                    $itemName = mb_strtolower(trim($sectionItem->inspectionItem->name ?? ''));
                    if ($itemName !== '' && in_array($itemName, $features, true)) {
                        $this->featureItemIds[$sectionItem->inspection_item_id] = true;
                    }
                }
            }

            // ── 5. Tentukan visibility tiap item ──────────────────
            $this->buildVisibilityMap($vehicle);

            // ── 6. Validasi required, filter vehicle, nested ──────
            $validationErrors = $this->validateAll($vehicle);
            if (!empty($validationErrors)) {
                return $this->error('Validasi form gagal', 422, [
                    'errors' => $validationErrors
                ]);
            }

            // ── 7. Simpan dalam transaksi ─────────────────────────
            DB::transaction(function () use ($request, $inspection) {
                foreach ($request->results as $result) {
                    $inspectionItemId = (int) $result['inspection_item_id'];

                    // Item tidak visible → hapus data lama di DB kalau ada, lalu skip
                    if (!($this->visibilityMap[$inspectionItemId] ?? false)) {
                        InspectionResult::where('inspection_id', $inspection->id)
                            ->where('inspection_item_id', $inspectionItemId)
                            ->delete();
                        $this->cleanOrphanImages($inspection->id, $inspectionItemId, []);
                        continue;
                    }

                    $sectionItem = $this->findSectionItemByInspectionItemId($inspectionItemId);

                    // Bersihkan stale note/image dari option yang tidak dipilih
                    $cleanedResult = $this->cleanStaleData($result, $sectionItem);

                    // Simpan result
                    $this->saveResult($inspection, $cleanedResult);

                    // Hapus orphan images (ada di DB tapi tidak di payload)
                    $this->cleanOrphanImages(
                        $inspection->id,
                        $inspectionItemId,
                        $cleanedResult['image_ids'] ?? []
                    );
                }

                $inspection->status = 'under_review';
                $inspection->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Hasil inspeksi berhasil disimpan',
                'data'    => [
                    'inspection_id' => $inspection->id,
                    'status'        => $inspection->status,
                    'result_count'  => count($request->results),
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('SaveResultController error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return $this->error(
                'Terjadi kesalahan saat menyimpan',
                500,
                config('app.debug') ? ['debug' => $e->getMessage()] : null
            );
        }
    }

    // ═════════════════════════════════════════════════════════════
    // VISIBILITY ENGINE
    // ═════════════════════════════════════════════════════════════

    /**
     * Tentukan visibility tiap item berdasarkan:
     * 1. is_visible (base)
     * 2. Vehicle filter
     * 3. Trigger dari parent item
     */
    private function buildVisibilityMap($vehicle): void
    {
        foreach ($this->sectionItemMap as $sectionItem) {
            $inspItemId = $sectionItem->inspection_item_id;
            $settings   = $sectionItem->settings ?? [];

            // ── Feature item: selalu visible terlepas dari is_visible, filter, trigger ──
            if ($this->featureItemIds[$inspItemId] ?? false) {
                $this->visibilityMap[$inspItemId] = true;
                continue;
            }

            // Base: is_visible false → langsung tidak visible
            if (!$sectionItem->is_visible) {
                $this->visibilityMap[$inspItemId] = false;
                continue;
            }

            // Vehicle filter
            if (!$this->passesVehicleFilter($settings, $vehicle)) {
                $this->visibilityMap[$inspItemId] = false;
                continue;
            }

            // Triggered item: hanya visible jika parent memilih option yang men-trigger ini
            $isTriggered   = (bool) ($settings['is_triggered'] ?? false);
            $parentItemIds = array_map('intval', (array) ($settings['parent_item_id'] ?? []));

            if ($isTriggered && !empty($parentItemIds)) {
                $visible = false;
                foreach ($parentItemIds as $parentSectionItemId) {
                    if ($this->isTriggeredByParent((int) $parentSectionItemId, $sectionItem->id)) {
                        $visible = true;
                        break;
                    }
                }
                $this->visibilityMap[$inspItemId] = $visible;
                continue;
            }

            $this->visibilityMap[$inspItemId] = true;
        }
    }

    /**
     * Cek apakah parent (section_item.id) memilih option yang men-trigger target.
     *
     * @param int $parentSectionItemId  section_item.id dari parent
     * @param int $targetInspectionItemId  inspection_item_id dari triggered item
     *                                     (sama dengan nilai di target_item_id settings)
     */
    /**
     * Cek apakah parent memilih option yang men-trigger target.
     *
     * @param int $parentSectionItemId  SectionItem.id dari parent
     * @param int $targetSectionItemId  SectionItem.id dari triggered item
     *                                  — nilai ini yang disimpan di target_item_id di Filament
     */
    private function isTriggeredByParent(int $parentSectionItemId, int $targetSectionItemId): bool
    {
        $parentSectionItem = $this->sectionItemMap[$parentSectionItemId] ?? null;
        if (!$parentSectionItem) return false;

        $parentResult = $this->payloadByItemIdMap[$parentSectionItemId]
            ?? $this->payloadMap[$parentSectionItem->inspection_item_id]
            ?? null;
        if (!$parentResult) return false;

        $selectedStatus = $parentResult['status'] ?? null;
        if ($selectedStatus === null) return false;

        $selectedValues = is_array($selectedStatus) ? $selectedStatus : [$selectedStatus];

        // Options ada di settings.settings.options (nested Filament structure)
        $options = $parentSectionItem->settings['settings']['options']
            ?? $parentSectionItem->settings['options']
            ?? [];

        foreach ($options as $option) {
            $optionValue = $option['value'] ?? null;

            // Skip option yang tidak dipilih
            if (!in_array($optionValue, $selectedValues, true)) continue;

            // show_trigger & target_item_id ada di option.settings
            $showTrigger = $option['settings']['show_trigger'] ?? false;
            if (!$showTrigger) continue;

            // target_item_id berisi SectionItem.id sebagai string → cast ke int
            $targetItemIds = array_map('intval', (array) ($option['settings']['target_item_id'] ?? []));
            if (empty($targetItemIds)) continue;

            // Bandingkan SectionItem.id dengan SectionItem.id
            if (in_array($targetSectionItemId, $targetItemIds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek apakah item lolos vehicle filter dari settings.
     * Jika settings tidak punya filter → lolos (tidak semua item punya filter).
     */
    private function passesVehicleFilter(array $settings, $vehicle): bool
    {
        if (!$vehicle) return true;

        // Doors
        if (!empty($settings['doors'])) {
            if ((int) $settings['doors'] !== (int) ($vehicle->doors ?? 0)) return false;
        }

        // Transmission (multiple select di Filament → array)
        if (!empty($settings['transmission'])) {
            $allowed = (array) $settings['transmission'];
            $vt      = $vehicle->transmission ?? null;
            if ($vt && !in_array($vt, $allowed, true)) return false;
        }

        // Fuel type
        if (!empty($settings['fuel_type'])) {
            $vf = $vehicle->fuel_type ?? null;
            if ($vf && $settings['fuel_type'] !== $vf) return false;
        }

        // Drive type
        if (!empty($settings['drive'])) {
            $vd = $vehicle->drive ?? null;
            if ($vd && $settings['drive'] !== $vd) return false;
        }

        // Pickup
        if (isset($settings['pickup']) && $settings['pickup'] === true) {
            if (!($vehicle->pickup ?? false)) return false;
        }

        // Box / Cargo
        if (isset($settings['box']) && $settings['box'] === true) {
            if (!($vehicle->box ?? false)) return false;
        }

        return true;
    }

    // ═════════════════════════════════════════════════════════════
    // VALIDATION
    // ═════════════════════════════════════════════════════════════

    private function validateAll($vehicle): array
    {
        $errors = [];

        foreach ($this->sectionItemMap as $sectionItem) {
            $inspItemId   = $sectionItem->inspection_item_id;
            $isVisible    = $this->visibilityMap[$inspItemId] ?? false;
            $settings     = $sectionItem->settings ?? [];
            $itemName     = $sectionItem->inspectionItem->name ?? "Item #{$inspItemId}";
            $result       = $this->payloadMap[$inspItemId] ?? null;

            // ── Damage section: skip required, hanya validasi format jika ada data ──
            // PENGECUALIAN: feature item di damage section tetap ikut validasi normal
            $isDamageSection = $sectionItem->menuSection?->section_type === 'damage';
            $isFeatureItem   = $this->featureItemIds[$inspItemId] ?? false;

            if ($isDamageSection && !$isFeatureItem) {
                if ($result && !$this->isResultEmpty($result)) {
                    $typeError = $this->validateValueByType($result, $sectionItem);
                    if ($typeError) {
                        $errors["item_{$inspItemId}_value"] = $typeError;
                    }
                }
                continue; // skip semua validasi required untuk damage section
            }

            // ── [A] Item visible & required → wajib ada ───────────
            if ($isVisible && $sectionItem->is_required) {
                if (!$result || $this->isResultEmpty($result)) {
                    $errors["item_{$inspItemId}"] = "{$itemName} wajib diisi";
                    continue;
                }

                // Validasi value sesuai type & settings
                $typeError = $this->validateValueByType($result, $sectionItem);
                if ($typeError) {
                    $errors["item_{$inspItemId}_value"] = $typeError;
                }
            }

            // ── [B] Item ada filter vehicle & visible → wajib ada ─
            if ($isVisible && $this->hasVehicleFilter($settings) && $sectionItem->is_required) {
                if (!$result || $this->isResultEmpty($result)) {
                    $errors["item_{$inspItemId}_vehicle"] = "{$itemName} wajib diisi (spesifikasi kendaraan)";
                }
            }

            // ── [C] Nested required (textarea/image dalam option) ─
            if ($isVisible && $result && in_array($sectionItem->input_type, ['radio', 'checkbox', 'select'])) {
                $nestedErrors = $this->validateNestedRequired($result, $sectionItem);
                foreach ($nestedErrors as $key => $msg) {
                    $errors["item_{$inspItemId}_nested_{$key}"] = $msg;
                }
            }

            // ── [D] Item tidak visible yang dikirim → abaikan, akan dibersihkan saat save ──
            // Tidak perlu error — frontend bisa kirim stale data dari localStorage
            // Backend akan skip + hapus data lama di DB (lihat step 7)
        }

        return $errors;
    }

    /**
     * Validasi value berdasarkan input_type dan settings.
     */
    private function validateValueByType(array $result, SectionItem $sectionItem): ?string
    {
        $settings  = $sectionItem->settings['settings'] ?? [];
        $inputType = $sectionItem->input_type;
        $itemName  = $sectionItem->inspectionItem->name ?? "Item #{$sectionItem->inspection_item_id}";
        $note      = $result['note']   ?? null;
        $status    = $result['status'] ?? null;

        switch ($inputType) {

            case 'text':
            case 'textarea':
                if ($note !== null && is_string($note)) {
                    $min = $settings['min_length'] ?? null;
                    $max = $settings['max_length'] ?? ($inputType === 'text' ? 255 : 2000);
                    if ($min && mb_strlen($note) < (int) $min) return "{$itemName} minimal {$min} karakter";
                    if ($max && mb_strlen($note) > (int) $max) return "{$itemName} maksimal {$max} karakter";
                }
                break;

            case 'number':
            case 'percentage':
                if ($note !== null && !is_array($note) && is_numeric($note)) {
                    $val = (float) $note;
                    $min = isset($settings['min']) ? (float) $settings['min'] : null;
                    $max = isset($settings['max']) ? (float) $settings['max'] : null;
                    if ($min !== null && $val < $min) return "{$itemName} minimal {$min}";
                    if ($max !== null && $val > $max) return "{$itemName} maksimal {$max}";
                }
                break;

            case 'currency':
                if ($note !== null && !is_array($note) && is_numeric($note)) {
                    $val = (float) $note;
                    $min = isset($settings['min_amount']) ? (float) $settings['min_amount'] : null;
                    $max = isset($settings['max_amount']) ? (float) $settings['max_amount'] : null;
                    if ($min !== null && $val < $min) return "{$itemName} minimal {$min}";
                    if ($max !== null && $val > $max) return "{$itemName} maksimal {$max}";
                }
                break;

            case 'radio':
            case 'select':
                if ($status !== null && !is_array($status)) {
                    $opts  = $settings['settings']['options'] ?? $settings['options'] ?? [];
                    $valid = collect($opts)->pluck('value')->toArray();
                    if (!empty($valid) && !in_array($status, $valid, true)) {
                        return "{$itemName} nilai tidak valid: '{$status}'";
                    }
                }
                break;

            case 'checkbox':
                if ($status !== null && is_array($status)) {
                    $opts        = $settings['settings']['options'] ?? $settings['options'] ?? [];
                    $valid       = collect($opts)->pluck('value')->toArray();
                    $minSelected = (int) ($settings['settings']['min_selected'] ?? $settings['min_selected'] ?? 0);
                    $maxSelected = $settings['settings']['max_selected'] ?? $settings['max_selected'] ?? null;
                    $maxSelected = $maxSelected !== null ? (int) $maxSelected : null;

                    foreach ($status as $val) {
                        if (!empty($valid) && !in_array($val, $valid, true)) {
                            return "{$itemName} nilai tidak valid: '{$val}'";
                        }
                    }
                    if ($minSelected > 0 && count($status) < $minSelected) {
                        return "{$itemName} minimal pilih {$minSelected} opsi";
                    }
                    if ($maxSelected && count($status) > $maxSelected) {
                        return "{$itemName} maksimal pilih {$maxSelected} opsi";
                    }
                }
                break;

            case 'image':
                $maxFiles = (int) ($settings['max_files'] ?? 1);
                $imageIds = $result['image_ids'] ?? [];
                if (count($imageIds) > $maxFiles) {
                    return "{$itemName} maksimal {$maxFiles} gambar";
                }
                break;
        }

        return null;
    }

    /**
     * Validasi nested required: textarea / image dalam option yang dipilih.
     */
    private function validateNestedRequired(array $result, SectionItem $sectionItem): array
    {
        $errors         = [];
        $options        = $sectionItem->settings['settings']['options']
            ?? $sectionItem->settings['options']
            ?? [];
        $status         = $result['status'] ?? null;
        $itemName       = $sectionItem->inspectionItem->name ?? "Item #{$sectionItem->inspection_item_id}";
        $selectedValues = is_array($status) ? $status : [$status];

        foreach ($options as $idx => $option) {
            $optionValue = $option['value'] ?? null;

            // Hanya validasi option yang dipilih
            if (!in_array($optionValue, $selectedValues, true)) continue;

            // Textarea required?
            if (($option['show_textarea'] ?? false) && ($option['textarea_is_required'] ?? false)) {
                if (empty($result['note'])) {
                    $errors[$idx . '_textarea'] = "{$itemName} → opsi '{$optionValue}': catatan wajib diisi";
                }
            }

            // Image required?
            if (($option['show_image'] ?? false) && ($option['image_is_required'] ?? false)) {
                if (empty($result['image_ids'])) {
                    $errors[$idx . '_image'] = "{$itemName} → opsi '{$optionValue}': gambar wajib diunggah";
                }
            }
        }

        return $errors;
    }

    // ═════════════════════════════════════════════════════════════
    // CLEAN STALE DATA
    // ═════════════════════════════════════════════════════════════

    /**
     * Hapus note/image_ids yang tidak relevan dengan option yang dipilih.
     *
     * Contoh: pilih option A (ada textarea) → ganti ke B (tidak ada textarea)
     * → note dari A masih ikut terbawa di frontend → hapus di sini
     */
    private function cleanStaleData(array $result, ?SectionItem $sectionItem): array
    {
        if (!$sectionItem) return $result;
        if (!in_array($sectionItem->input_type, ['radio', 'checkbox', 'select'])) return $result;

        $status = $result['status'] ?? null;
        if ($status === null) return $result;

        $selectedValues = is_array($status) ? $status : [$status];
        $options        = $sectionItem->settings['settings']['options']
            ?? $sectionItem->settings['options']
            ?? [];

        $anyHasTextarea = false;
        $anyHasImage    = false;

        foreach ($options as $option) {
            if (!in_array($option['value'] ?? null, $selectedValues, true)) continue;
            if ($option['show_textarea'] ?? false) $anyHasTextarea = true;
            if ($option['show_image']    ?? false) $anyHasImage    = true;
        }

        // Hapus note jika option yang dipilih tidak punya textarea
        if (!$anyHasTextarea) {
            $result['note'] = null;
        }

        // Hapus image_ids jika option yang dipilih tidak punya show_image
        if (!$anyHasImage) {
            $result['image_ids'] = [];
        }

        return $result;
    }

    // ═════════════════════════════════════════════════════════════
    // CLEAN ORPHAN IMAGES
    // ═════════════════════════════════════════════════════════════

    /**
     * Hapus InspectionImage yang ada di DB tapi tidak ada di payload.
     * Scope: inspection_id + inspection_item_id yang sama.
     */
    private function cleanOrphanImages(int $inspectionId, int $inspectionItemId, array $activeImageIds): void
    {
        $query = InspectionImage::where('inspection_id', $inspectionId)
            ->where('inspection_item_id', $inspectionItemId);

        if (!empty($activeImageIds)) {
            $query->whereNotIn('id', $activeImageIds);
        }

        $orphans = $query->get();

        foreach ($orphans as $orphan) {
            // Uncomment jika perlu hapus file fisik:
            // if ($orphan->image_path) Storage::disk('public')->delete($orphan->image_path);
            $orphan->delete();
        }
    }

    // ═════════════════════════════════════════════════════════════
    // SAVE RESULT
    // ═════════════════════════════════════════════════════════════

    private function saveResult(Inspection $inspection, array $result): void
    {
        $inspectionItemId = (int) $result['inspection_item_id'];

        $status = $result['status'] ?? null;
        if (is_array($status)) {
            $status = json_encode($status);
        } elseif ($status !== null) {
            $status = (string) $status;
        }

        $note = isset($result['note']) && is_string($result['note']) && $result['note'] !== ''
            ? $result['note']
            : null;
        $extraData = null;

        if (!empty($result['extra_data'])) {
            $extraData = $result['extra_data'];
            if (!empty($extraData['damage_ids'])) {
                $extraData['damage_ids'] = array_values(array_unique(array_map('intval', $extraData['damage_ids'])));
            }
        }

        if (!empty($result['image_ids'])) {
            $imageIds  = array_values(array_unique(array_map('intval', $result['image_ids'])));
            $extraData = array_merge($extraData ?? [], ['image_ids' => $imageIds]);
        }

        InspectionResult::updateOrCreate(
            [
                'inspection_id'      => $inspection->id,
                'inspection_item_id' => $inspectionItemId,
            ],
            [
                'status'     => $status,
                'note'       => $note,
                'extra_data' => $extraData ?: null,
            ]
        );
    }

    // ═════════════════════════════════════════════════════════════
    // HELPERS
    // ═════════════════════════════════════════════════════════════

    private function findSectionItemByInspectionItemId(int $inspectionItemId): ?SectionItem
    {
        foreach ($this->sectionItemMap as $sectionItem) {
            if ($sectionItem->inspection_item_id === $inspectionItemId) {
                return $sectionItem;
            }
        }
        return null;
    }

    private function isResultEmpty(array $result): bool
    {
        $status   = $result['status']    ?? null;
        $note     = $result['note']      ?? null;
        $imageIds = $result['image_ids'] ?? [];

        if ($status !== null && $status !== '' && $status !== []) return false;
        if ($note !== null && is_string($note) && $note !== '')   return false;
        if (!empty($imageIds))                                     return false;

        return true;
    }

    private function hasVehicleFilter(array $settings): bool
    {
        return !empty($settings['doors'])
            || !empty($settings['transmission'])
            || !empty($settings['fuel_type'])
            || !empty($settings['drive'])
            || isset($settings['pickup'])
            || isset($settings['box']);
    }

    private function error(string $message, int $status = 400, mixed $extra = null): \Illuminate\Http\JsonResponse
    {
        $body = ['success' => false, 'message' => $message];
        if ($extra !== null) $body = array_merge($body, (array) $extra);
        return response()->json($body, $status);
    }
}