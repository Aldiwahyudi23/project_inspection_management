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

            $vehicleDetail = $this->vehicleApi->getVehicleDetailForInspectionForm($inspection->vehicle_id);

            if (! $vehicleDetail['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $vehicleDetail['message'],
                    'debug'   => $vehicleDetail['error'] ?? null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'inspection' => [
                        'id' => $inspection->id,
                        'template_id' => $inspection->template_id,
                        'vehicle_id' => $inspection->vehicle_id,
                        'vehicle_detail' => $vehicleDetail['data'] ?? null,
                        'license_plate' => $inspection->license_plate,
                        'atribute_vehicle' => $result['data'] ?? null,
                        'vehicle_name' => $inspection->vehicle_name,
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
            'current_result' => $inspectionId 
                ? $this->getItemResult($inspectionId, $item->inspection_item_id, $item->input_type) 
                : null,
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
/**
     * Ambil current_result untuk satu item inspeksi.
     *
     * Format return (flat) sesuai formValues frontend:
     *
     * [A] text/textarea/number/currency/percentage/date/datetime/time
     *     → string | number | null
     *     Contoh: "Baret halus" | 12345
     *
     * [B] image (tanpa show_option)
     *     → [{id, image_url, caption}] | null
     *
     * [C] radio / select / checkbox
     *     → { status, note, image, damage_ids }
     *       status: string (radio/select) | string[] (checkbox)
     *
     * [D] image + show_option
     *     → { image, status, note, damage_ids }
     *       image: [{id, image_url, caption}]
     *       status: string | null (opsi yang dipilih)
     */
    private function getItemResult($inspectionId, $itemId, $inputType = null)
    {
        // ── Helper: decode status yang mungkin tersimpan sebagai JSON string ──
        $decodeStatus = function ($raw) {
            if ($raw === null) return null;
            if (is_array($raw)) return $raw;
            if (is_string($raw) && str_starts_with(trim($raw), '[')) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) return $decoded;
            }
            return $raw === '' ? null : (string) $raw;
        };

        // ── Helper: ambil gambar milik item ──
        $getImages = fn() => InspectionImage::where('inspection_id', $inspectionId)
            ->where('inspection_item_id', $itemId)
            ->get()
            ->map(fn($img) => [
                'id'        => $img->id,
                'image_url' => asset('storage/' . $img->image_path),
                'caption'   => $img->caption,
            ])
            ->values()
            ->toArray();

        // ── [B] input_type = image ────────────────────────────────
        if ($inputType === 'image') {
            $images = $getImages();
            $result = InspectionResult::where('inspection_id', $inspectionId)
                ->where('inspection_item_id', $itemId)
                ->first();

            // Tidak ada result dan tidak ada gambar → null
            if (!$result && empty($images)) return null;

            // Tidak ada result tapi ada gambar → image biasa (tanpa show_option)
            if (!$result) return $images;

            // Ada result → berarti punya show_option
            // Format flat [D]: { image, status, note, damage_ids }
            $extraData = is_array($result->extra_data)
                ? $result->extra_data
                : (json_decode($result->extra_data, true) ?? []);

            return [
                'image'      => $images,
                'status'     => $decodeStatus($result->status),
                'note'       => $result->note,
                'damage_ids' => $extraData['damage_ids'] ?? [],
            ];
        }

        // ── Input type lain: result wajib ada ────────────────────
        $result = InspectionResult::where('inspection_id', $inspectionId)
            ->where('inspection_item_id', $itemId)
            ->first();

        if (!$result) return null;

        // ── [A] Nilai langsung (text/number/dll) ──────────────────
        if (in_array($inputType, ['text', 'textarea', 'number', 'currency', 'percentage', 'date', 'datetime', 'time'])) {
            return $result->note;
        }

        // ── [C] radio / select / checkbox ─────────────────────────
        // Format flat: { status, note, image, damage_ids }
        if (in_array($inputType, ['radio', 'select', 'checkbox'])) {
            $images    = $getImages();
            $extraData = is_array($result->extra_data)
                ? $result->extra_data
                : (json_decode($result->extra_data, true) ?? []);

            return [
                'status'     => $decodeStatus($result->status),
                'note'       => $result->note,
                'image'      => !empty($images) ? $images : null,
                'damage_ids' => $extraData['damage_ids'] ?? [],
            ];
        }

        return null;
    }

    // ========================= Untuk menghandle Imga di Form Inspection=====================================
    public function uploadImages(Request $request)
    {
        Log::info('=== UPLOAD IMAGE START ===');
        Log::info('Request Data:', $request->all());

        try {

                // ✅ Harus di sini — SEBELUM validate
        $request->merge([
            'inspection_item_id' => ($request->inspection_item_id == 0 || $request->inspection_item_id === '')
                ? null : $request->inspection_item_id,
            'item_id' => ($request->item_id == 0 || $request->item_id === '')
                ? null : $request->item_id,
        ]);

            $request->validate([
                'inspection_id' => 'required|exists:inspections,id',
                'inspection_item_id' => 'nullable|exists:inspection_items,id',
                'item_id' => 'nullable|exists:section_items,id',
                'images' => 'required',
                'selected_option' => 'nullable|array'
            ]);

            Log::info('Validation passed');

            // $sectionItem = SectionItem::findOrFail($request->item_id);
        $sectionItem = $request->item_id
    ? SectionItem::find($request->item_id)
    : null;

Log::info('SectionItem:', ['found' => $sectionItem ? $sectionItem->id : 'null (foto bebas)']);
            // Resolve settings berdasarkan input_type dan selected_option
            $selectedOptions = $request->input('selected_option_value', []);

            if (is_string($selectedOptions)) {
                $selectedOptions = array_map('trim', explode(',', $selectedOptions));
            }

            // $settings = $this->resolveImageSettings(
            //     $sectionItem,
            //     $selectedOptions
            // );

            $settings = $sectionItem
                ? $this->resolveImageSettings($sectionItem, $selectedOptions)
                : [
                        'max_size'            => 5120, // default 5MB
                        'max_files'           => 10,   // foto bebas boleh banyak
                        'allowed_mimes'       => ['jpg', 'jpeg', 'png'],
                        'compression_quality' => 60,
                        'max_width'           => null,
                        'max_height'          => null,
                    ];

            Log::info('Resolved settings:', $settings);

            // Validasi awal sebelum kompresi
            $files = is_array($request->file('images'))
                ? $request->file('images')
                : [$request->file('images')];

            Log::info('Total files:', ['count' => count($files)]);

            // Validasi jumlah file
            if (count($files) > $settings['max_files']) {
                Log::warning('File count exceeded');
                return response()->json([
                    'success' => false,
                    'message' => "Maximum {$settings['max_files']} file(s) allowed"
                ], 400);
            }

            $uploadedImages = [];
            $errors = [];

            foreach ($files as $index => $file) {
                Log::info("Processing file index {$index}");

                try {
                    // Validasi format file
                    $extension = strtolower($file->getClientOriginalExtension());
                    Log::info("Extension: {$extension}");

                    if (!empty($settings['allowed_mimes']) && 
                        !in_array($extension, $settings['allowed_mimes'])) {
                        throw new \Exception("Format file tidak diizinkan. Gunakan: " . implode(', ', $settings['allowed_mimes']));
                    }

                    // Validasi ukuran file SEBELUM kompresi
                    $fileSizeKB = round($file->getSize() / 1024, 2);
                    Log::info('Original file size (KB):', ['size_kb' => $fileSizeKB]);

                    if ($file->getSize() > ($settings['max_size'] * 1024)) {
                        throw new \Exception("Ukuran file terlalu besar. Maksimal {$settings['max_size']}KB");
                    }

                    // Proses kompresi jika > 1.2MB
                    $processedFile = $file;
                    if ($file->getSize() > (1.2 * 1024 * 1024)) {
                        Log::info('File > 1.2MB, compressing...');
                        
                        $processedFile = $this->compressImage(
                            $file,
                            $settings
                        );

                        Log::info('After compress size (KB):', [
                            'size_kb' => round($processedFile->getSize() / 1024, 2)
                        ]);
                    }

                    // Simpan file
                    $fileName = Str::uuid() . '.' . $extension;
                    $path = $processedFile->storeAs(
                        "inspection/images",
                        $fileName,
                        'public'
                    );

                    Log::info('Stored at:', ['path' => $path]);

                    // Simpan ke database
                    $image = InspectionImage::create([
                        'inspection_id' => $request->inspection_id,
                        'inspection_item_id' => $request->inspection_item_id ?: null,
                        'image_path' => $path,
                        'caption' => null,
                    ]);

                    Log::info('DB Inserted:', ['image_id' => $image->id]);

                    $uploadedImages[] = [
                        'id' => $image->id,
                        'inspection_item_id' => $image->inspection_item_id,
                        'image_url' => $image->image_url
                    ];

                } catch (\Exception $e) {
                    Log::error("Error file index {$index}: " . $e->getMessage());
                    $errors[] = "File " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            Log::info('=== UPLOAD IMAGE END ===');

            if (empty($uploadedImages)) {
                return response()->json([
                    'success' => false,
                    'errors' => $errors
                ], 400);
            }

            return response()->json([
                'success' => true,
                'errors' => $errors ?: null,
                'data' => $uploadedImages
            ]);

        } catch (\Exception $e) {
            Log::error('FATAL ERROR: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function resolveImageSettings(SectionItem $sectionItem, array $selectedOptions): array
    {
        // Default settings
        $default = [
            'max_size' => 2048,
            'max_files' => 1,
            'allowed_mimes' => ['jpg', 'jpeg', 'png'],
            'compression_quality' => 80,
            'max_width' => null,
            'max_height' => null
        ];

        // Ambil settings dari model
        $settingsJson = $sectionItem->settings ?? [];
        
        // Log untuk debug
        Log::info('Raw settings from DB:', $settingsJson);
        Log::info('Input type:', ['type' => $sectionItem->input_type]);
        Log::info('Selected options (raw):', $selectedOptions);

        // Process selected options - karena bisa berupa string "NOT OK,Repaint" atau array
        $processedSelectedOptions = [];
        foreach ($selectedOptions as $key => $value) {
            // Jika value mengandung koma, pecah jadi array
            if (is_string($value) && strpos($value, ',') !== false) {
                $parts = explode(',', $value);
                foreach ($parts as $part) {
                    $processedSelectedOptions[] = trim($part);
                }
            } else {
                $processedSelectedOptions[] = $value;
            }
        }
        
        Log::info('Processed selected options:', $processedSelectedOptions);

        /*
        |--------------------------------------------------------------------------
        | CASE 1: input_type = image / file
        |--------------------------------------------------------------------------
        */
        if (in_array($sectionItem->input_type, ['image', 'file'])) {
            // Untuk tipe image/file, settings langsung di root atau di dalam key 'settings'
            $imageSettings = $settingsJson['settings'] ?? $settingsJson;
            
            return [
                'max_size' => (int)($imageSettings['max_size'] ?? $default['max_size']),
                'max_files' => (int)($imageSettings['max_files'] ?? $default['max_files']),
                'allowed_mimes' => !empty($imageSettings['allowed_mimes']) 
                    ? (is_array($imageSettings['allowed_mimes']) ? $imageSettings['allowed_mimes'] : explode(',', $imageSettings['allowed_mimes']))
                    : $default['allowed_mimes'],
                'compression_quality' => (int)($imageSettings['compression_quality'] ?? $default['compression_quality']),
                'max_width' => $imageSettings['max_width'] ?? null,
                'max_height' => $imageSettings['max_height'] ?? null
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | CASE 2: radio / select / checkbox
        |--------------------------------------------------------------------------
        */
        // Ambil options dari settings (perhatikan struktur JSON)
        $options = $settingsJson['settings']['options'] ?? [];
        
        if (empty($options)) {
            // Log::warning('No options found in settings');
            return $default;
        }

        // Cari settings yang cocok dengan selected options
        $matchedSettings = [];
        
        foreach ($processedSelectedOptions as $selectedValue) {
            foreach ($options as $option) {
                // Cek apakah value cocok
                $optionValue = $option['value'] ?? null;
                
                // Log untuk trace
                Log::info('Comparing:', [
                    'selected' => $selectedValue,
                    'option_value' => $optionValue,
                    'match' => ($optionValue == $selectedValue) ? 'YES' : 'NO'
                ]);
                
                if ($optionValue == $selectedValue) {
                    
                    // Ambil settings dari option
                    $optionSettings = $option['settings'] ?? [];
                    
                    Log::info('Found matching option:', [
                        'selected' => $selectedValue,
                        'option_value' => $optionValue,
                        'settings' => $optionSettings
                    ]);
                    
                    $matchedSettings[] = $optionSettings;
                    break; // Keluar dari loop options setelah menemukan yang cocok
                }
            }
        }

        Log::info('Matched settings count:', ['count' => count($matchedSettings)]);

        if (empty($matchedSettings)) {
            Log::warning('No matching settings found, using default');
            return $default;
        }

        // Ambil nilai TERTINGGI dari setiap setting
        $maxSize = 0;
        $maxFiles = 0;
        $compressionQuality = 0;
        $maxWidth = null;
        $maxHeight = null;
        $allowedMimes = [];

        foreach ($matchedSettings as $setting) {
            // Max Size - ambil yang terbesar (perhatikan tipe data string)
            if (isset($setting['max_size']) && $setting['max_size'] !== '' && $setting['max_size'] !== null) {
                $settingMaxSize = (int)$setting['max_size'];
                if ($settingMaxSize > $maxSize) {
                    $maxSize = $settingMaxSize;
                    Log::info("Max size from setting: {$settingMaxSize}");
                }
            }
            
            // Max Files - ambil yang terbesar
            if (isset($setting['max_files']) && $setting['max_files'] !== '' && $setting['max_files'] !== null) {
                $settingMaxFiles = (int)$setting['max_files'];
                if ($settingMaxFiles > $maxFiles) {
                    $maxFiles = $settingMaxFiles;
                    Log::info("Max files from setting: {$settingMaxFiles}");
                }
            }
            
            // Compression Quality - ambil yang terbesar (kualitas terbaik)
            if (isset($setting['compression_quality']) && $setting['compression_quality'] !== '' && $setting['compression_quality'] !== null) {
                $settingQuality = (int)$setting['compression_quality'];
                if ($settingQuality > $compressionQuality) {
                    $compressionQuality = $settingQuality;
                    Log::info("Compression quality from setting: {$settingQuality}");
                }
            }
            
            // Max Width - ambil yang terbesar
            if (isset($setting['max_width']) && $setting['max_width'] !== '' && $setting['max_width'] !== null) {
                $settingWidth = (int)$setting['max_width'];
                if ($maxWidth === null || $settingWidth > $maxWidth) {
                    $maxWidth = $settingWidth;
                    Log::info("Max width from setting: {$settingWidth}");
                }
            }
            
            // Max Height - ambil yang terbesar
            if (isset($setting['max_height']) && $setting['max_height'] !== '' && $setting['max_height'] !== null) {
                $settingHeight = (int)$setting['max_height'];
                if ($maxHeight === null || $settingHeight > $maxHeight) {
                    $maxHeight = $settingHeight;
                    Log::info("Max height from setting: {$settingHeight}");
                }
            }
            
            // Allowed Mimes - gabungkan semua
            if (isset($setting['allowed_mimes']) && !empty($setting['allowed_mimes'])) {
                $mimes = is_array($setting['allowed_mimes']) 
                    ? $setting['allowed_mimes'] 
                    : (is_string($setting['allowed_mimes']) ? explode(',', $setting['allowed_mimes']) : []);
                
                $allowedMimes = array_merge($allowedMimes, $mimes);
                Log::info("Allowed mimes from setting:", $mimes);
            }
        }

        // Jika tidak ada nilai yang ditemukan, gunakan default
        if ($maxSize == 0) $maxSize = $default['max_size'];
        if ($maxFiles == 0) $maxFiles = $default['max_files'];
        if ($compressionQuality == 0) $compressionQuality = $default['compression_quality'];
        if (empty($allowedMimes)) $allowedMimes = $default['allowed_mimes'];
        
        // Unique allowed mimes
        $allowedMimes = array_unique($allowedMimes);

        // Log hasil akhir
        // Log::info('Final resolved settings:', [
        //     'max_size' => $maxSize,
        //     'max_files' => $maxFiles,
        //     'compression_quality' => $compressionQuality,
        //     'max_width' => $maxWidth,
        //     'max_height' => $maxHeight,
        //     'allowed_mimes' => $allowedMimes
        // ]);

        return [
            'max_size' => $maxSize,
            'max_files' => $maxFiles,
            'allowed_mimes' => $allowedMimes,
            'compression_quality' => $compressionQuality,
            'max_width' => $maxWidth,
            'max_height' => $maxHeight
        ];
    }
    private function compressImage($file, array $settings)
    {
        $quality = $settings['compression_quality'] ?? 80;
        
        $sourcePath = $file->getPathname();
        $extension = strtolower($file->getClientOriginalExtension());
        
        try {
            // Baca EXIF data untuk orientasi
            $exif = null;
            if (in_array($extension, ['jpg', 'jpeg']) && function_exists('exif_read_data')) {
                $exif = @exif_read_data($sourcePath);
            }
            
            // Buat image resource
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($sourcePath);
                    break;
                case 'png':
                    $image = imagecreatefrompng($sourcePath);
                    // Preserve alpha channel untuk PNG
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    break;
                default:
                    return $file;
            }
            
            if (!$image) {
                throw new \Exception("Gagal memproses gambar");
            }
            
            // Fix orientasi berdasarkan EXIF
            if ($exif && isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
                
                switch ($orientation) {
                    case 3: // 180 derajat
                        $image = imagerotate($image, 180, 0);
                        break;
                    case 6: // 90 derajat searah jarum jam
                        $image = imagerotate($image, -90, 0);
                        break;
                    case 8: // 90 derajat berlawanan jarum jam
                        $image = imagerotate($image, 90, 0);
                        break;
                }
            }
            
            // Resize jika diperlukan
            $width = imagesx($image);
            $height = imagesy($image);
            
            $newWidth = $settings['max_width'] ?? $width;
            $newHeight = $settings['max_height'] ?? $height;
            
            // Hitung rasio aspek jika hanya satu dimensi yang ditentukan
            if ($settings['max_width'] && !$settings['max_height']) {
                $ratio = $width / $height;
                $newHeight = round($newWidth / $ratio);
            } elseif (!$settings['max_width'] && $settings['max_height']) {
                $ratio = $height / $width;
                $newWidth = round($newHeight / $ratio);
            }
            
            // Resize jika ukuran berbeda
            if ($newWidth != $width || $newHeight != $height) {
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                
                // Preserve transparency untuk PNG
                if ($extension === 'png') {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                    imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
                }
                
                imagecopyresampled(
                    $resized, $image,
                    0, 0, 0, 0,
                    $newWidth, $newHeight,
                    $width, $height
                );
                
                imagedestroy($image);
                $image = $resized;
            }
            
            // Simpan ke temporary file
            $tempPath = tempnam(sys_get_temp_dir(), 'img_');
            
            if ($extension === 'png') {
                // Untuk PNG, quality diubah ke compression level (0-9)
                $pngCompression = min(9, max(0, 9 - round($quality / 11.11))); // 80% quality ≈ level 2
                imagepng($image, $tempPath, $pngCompression);
            } else {
                $targetMinSize = 1024 * 1024; // 1MB
                $currentQuality = $quality;
                $tempPath = tempnam(sys_get_temp_dir(), 'img_');

                do {

                    if ($extension === 'png') {
                        $pngCompression = min(9, max(0, 9 - round($currentQuality / 11.11)));
                        imagepng($image, $tempPath, $pngCompression);
                    } else {
                        imagejpeg($image, $tempPath, $currentQuality);
                    }

                    clearstatcache(true, $tempPath);
                    $fileSize = filesize($tempPath);

                    // jika size terlalu kecil, naikkan quality
                    if ($fileSize < $targetMinSize && $currentQuality < 95) {
                        $currentQuality += 5;
                    } else {
                        break;
                    }

                } while ($currentQuality <= 95);
            }
            
            imagedestroy($image);
            
            return new \Illuminate\Http\UploadedFile(
                $tempPath,
                $file->getClientOriginalName(),
                $file->getClientMimeType(),
                null,
                true
            );
            
        } catch (\Exception $e) {
            // Log::error('Compression error: ' . $e->getMessage());
            // Jika gagal kompres, return file asli
            return $file;
        }
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


    /**
     * Ambil semua gambar yang belum diassign ke item manapun (foto bebas).
     * Gambar bebas = inspection_item_id IS NULL di tabel inspection_images.
     */
    public function getUnassignedImages(int $inspectionId)
    {
        // Pastikan inspection ada dan milik user yang sedang login
        $inspection = Inspection::where('id', $inspectionId)
            ->firstOrFail();

        // Ambil gambar yang inspection_item_id = NULL
        $images = InspectionImage::where('inspection_id', $inspectionId)
            ->whereNull('inspection_item_id')   // ← harus whereNull, bukan where(..., null)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($img) => [
                'id'         => $img->id,
                'image_url'  => asset('storage/' . $img->image_path),
                'caption'    => $img->caption,
                'created_at' => $img->created_at?->toISOString(),
            ])
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'data'    => $images,   // selalu array, tidak pernah null
        ]);
    }

        /**
     * Assign inspection_item_id ke foto bebas.
     *
     * PATCH /api/inspection-images/{image}/assign
     * body: { inspection_item_id: number }
     */
    public function assignImages(Request $request)
    {
        $request->validate([
            'inspection_item_id' => ['required','integer','exists:inspection_items,id'],
            'image_ids' => ['required','array'],
            'image_ids.*' => ['integer','exists:inspection_images,id']
        ]);

        $inspectionItemId = $request->inspection_item_id;
        $imageIds = $request->image_ids;

        $updated = InspectionImage::whereIn('id', $imageIds)
            ->update([
                'inspection_item_id' => $inspectionItemId
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Images assigned successfully',
            'updated_count' => $updated
        ]);
    }

     /**
     * Update vehicle detail on inspection
     */
    public function updateVehicle(Request $request, $inspectionId)
    {
        try {

            $inspection = Inspection::findOrFail($inspectionId);

            $validated = $request->validate([
                'license_plate' => 'required|string|max:20',
                'vehicle_name'  => 'required|string|max:255',
                'vehicle_id'    => 'required|integer',
            ]);

            $inspection->update([
                'license_plate' => $validated['license_plate'] ?? $inspection->license_plate,
                'vehicle_name'  => $validated['vehicle_name'] ?? $inspection->vehicle_name,
                'vehicle_id'    => $validated['vehicle_id'] ?? $inspection->vehicle_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle data updated successfully',
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle data',
                'error' => $e->getMessage()
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

                    // ── Hanya punya image_ids tanpa status dan note → bersihkan orphan, skip simpan result ──
                    $hasStatus = !empty($cleanedResult['status']);
                    $hasNote   = !empty($cleanedResult['note']);

                    if (!$hasStatus && !$hasNote) {
                        $this->cleanOrphanImages(
                            $inspection->id,
                            $inspectionItemId,
                            $cleanedResult['image_ids'] ?? []
                        );
                        continue;
                    }

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

                  // Hapus file fisik di storage
            if ($orphan->image_path && Storage::disk('public')->exists($orphan->image_path)) {
                Storage::disk('public')->delete($orphan->image_path);
            }
            // Uncomment jika perlu hapus file fisik:
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