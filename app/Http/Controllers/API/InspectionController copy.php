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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InspectionController extends Controller
{
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

            // Get existing results for prefilling
            $existingResults = $this->getExistingResults($inspectionId);

            return response()->json([
                'success' => true,
                'data' => [
                    'inspection' => [
                        'id' => $inspection->id,
                        'template_id' => $inspection->template_id,
                        'vehicle_id' => $inspection->vehicle_id,
                        'vehicle_name' => $inspection->vehicle_name,
                        'license_plate' => $inspection->license_plate,
                        'status' => $inspection->status,
                        'progress_percentage' => $inspection->progress_percentage,
                        'can_be_edited' => $inspection->canBeEdited(),
                        'vehicle_display' => $inspection->vehicle_display,
                    ],
                    'template' => $templateStructure,
                    'existing_results' => $existingResults,
                    'validation_config' => $this->getValidationConfig($templateStructure),
                    'metadata' => [
                        'damage_categories' => $this->getDamageCategories(),
                        'transmissions' => $this->getTransmissions(),
                    ]
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
                ->where('is_visible', true)
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
            'description' => $template->description,
            'settings' => $template->settings ?? [],
            'sections' => $structure
        ];
    }

    /**
     * Transform section item data for frontend consumption
     */
    private function transformSectionItem(SectionItem $item, $inspectionId = null)
    {
        $settings = $item->settings ?? [];
        
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
                'code' => $item->inspectionItem->code,
            ] : null,
            
            // Settings based on input type
            'settings' => $this->transformSettings($item->input_type, $settings),
            
            // Validation rules
            'validation_rules' => $this->extractValidationRules($item->input_type, $settings),
            
            // Current result if exists
            'current_result' => $inspectionId ? $this->getItemResult($inspectionId, $item->inspection_item_id) : null,
            
            // UI configuration
            'ui_config' => $this->getUIConfig($item->input_type, $settings),
        ];

        return $itemData;
    }

    /**
     * Transform settings based on input type
     */
    private function transformSettings($inputType, $settings)
    {
        $transformed = $settings;
        
        switch ($inputType) {
            case 'text':
                $transformed = array_merge([
                    'min_length' => 0,
                    'max_length' => 255,
                    'placeholder' => '',
                    'capitalization' => 'none',
                    'regex_pattern' => null,
                    'allow_spaces' => true,
                    'allow_special_chars' => true,
                    'trim_spaces' => true,
                ], $settings);
                break;
                
            case 'textarea':
                $transformed = array_merge([
                    'rows' => 4,
                    'placeholder' => '',
                    'min_length' => 0,
                    'max_length' => 2000,
                    'rich_text' => false,
                    'allow_html' => false,
                    'show_damage' => false,
                    'damage_category_id' => null,
                    'damage_ids' => [],
                ], $settings);
                break;
                
            case 'number':
                $transformed = array_merge([
                    'min' => null,
                    'max' => null,
                    'step' => 1,
                    'decimal_places' => 0,
                    'prefix' => '',
                    'suffix' => '',
                    'thousand_separator' => true,
                    'allow_negative' => false,
                ], $settings);
                break;
                
            case 'currency':
                $transformed = array_merge([
                    'currency_code' => 'IDR',
                    'currency_symbol' => 'Rp',
                    'min_amount' => 0,
                    'max_amount' => null,
                    'decimal_places' => 0,
                    'allow_negative' => false,
                    'help_text' => '',
                ], $settings);
                break;
                
            case 'percentage':
                $transformed = array_merge([
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.1,
                    'decimal_places' => 1,
                    'show_percent_sign' => true,
                    'allow_over_100' => false,
                ], $settings);
                break;
                
            case 'select':
            case 'radio':
            case 'checkbox':
                $transformed = array_merge([
                    'options' => [],
                    'placeholder' => $inputType === 'select' ? 'Pilih...' : null,
                    'searchable' => $inputType === 'select',
                    'allow_custom' => $inputType === 'select' ? false : null,
                    'layout' => $inputType !== 'select' ? 'vertical' : null,
                    'min_selected' => $inputType === 'checkbox' ? 0 : null,
                    'max_selected' => $inputType === 'checkbox' ? null : null,
                ], $settings);
                
                // Transform options with nested settings
                if (isset($transformed['options']) && is_array($transformed['options'])) {
                    foreach ($transformed['options'] as &$option) {
                        if (!isset($option['settings'])) {
                            $option['settings'] = [];
                        }
                        if (isset($option['settings']['textarea_settings'])) {
                            $option['settings']['textarea'] = $this->transformSettings('textarea', $option['settings']['textarea_settings']);
                            unset($option['settings']['textarea_settings']);
                        }
                        if (isset($option['settings']['image_settings'])) {
                            $option['settings']['image'] = $this->transformSettings('image', $option['settings']['image_settings']);
                            unset($option['settings']['image_settings']);
                        }
                    }
                }
                break;
                
            case 'boolean':
                $transformed = array_merge([
                    'checked_value' => 'true',
                    'unchecked_value' => 'false',
                    'label' => '',
                    'default' => false,
                ], $settings);
                break;
                
            case 'date':
                $transformed = array_merge([
                    'min_date' => null,
                    'max_date' => null,
                    'format' => 'Y-m-d',
                    'display_format' => 'd/m/Y',
                    'placeholder' => '',
                    'show_time' => false,
                ], $settings);
                break;
                
            case 'datetime':
                $transformed = array_merge([
                    'min_date' => null,
                    'max_date' => null,
                    'format' => 'Y-m-d H:i:s',
                    'display_format' => 'd/m/Y H:i',
                    'placeholder' => '',
                ], $settings);
                break;
                
            case 'time':
                $transformed = array_merge([
                    'min_time' => null,
                    'max_time' => null,
                    'format' => 'H:i',
                    'step_minutes' => 15,
                ], $settings);
                break;
                
            case 'image':
            case 'file':
                $transformed = array_merge([
                    'max_size' => 2048, // KB
                    'allowed_mimes' => $inputType === 'image' ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'doc', 'docx'],
                    'max_files' => 1,
                    'show_option' => $inputType === 'image',
                    'option_is_required' => false,
                ], $settings);
                
                if ($inputType === 'image') {
                    $transformed = array_merge([
                        'max_width' => null,
                        'max_height' => null,
                        'aspect_ratio' => null,
                        'compression_quality' => 80,
                    ], $transformed);
                    
                    if (isset($transformed['option_settings'])) {
                        $transformed['options'] = $this->transformSettings('radio', $transformed['option_settings']);
                        unset($transformed['option_settings']);
                    }
                }
                break;
                
            case 'color':
                $transformed = array_merge([
                    'default' => '#3490dc',
                    'show_palette' => true,
                    'preset_colors' => [],
                ], $settings);
                break;
                
            case 'rating':
                $transformed = array_merge([
                    'max_rating' => 5,
                    'step' => 1,
                    'icon' => 'star',
                    'show_labels' => true,
                    'labels' => [],
                ], $settings);
                break;
                
            case 'slider':
                $transformed = array_merge([
                    'min' => 0,
                    'max' => 100,
                    'step' => 1,
                    'orientation' => 'horizontal',
                    'show_ticks' => true,
                    'show_value' => true,
                ], $settings);
                break;
                
            case 'switch':
                $transformed = array_merge([
                    'on_color' => 'primary',
                    'off_color' => 'secondary',
                    'on_label' => 'Aktif',
                    'off_label' => 'Nonaktif',
                    'default' => false,
                ], $settings);
                break;
                
            case 'signature':
                $transformed = array_merge([
                    'width' => 400,
                    'height' => 200,
                    'pen_color' => '#000000',
                    'background_color' => '#ffffff',
                    'required' => true,
                ], $settings);
                break;
                
            case 'location':
                $transformed = array_merge([
                    'default_lat' => -6.2088,
                    'default_lng' => 106.8456,
                    'zoom' => 15,
                    'required_accuracy' => 50,
                    'show_map' => true,
                ], $settings);
                break;
        }
        
        // Special settings from Filament form
        $transformed = array_merge([
            'is_triggered' => false,
            'parent_item_id' => [],
            'transmission' => [],
            'fuel_type' => null,
            'doors' => null,
            'drive' => null,
            'pickup' => false,
            'box' => false,
        ], $transformed);
        
        return $transformed;
    }

    /**
     * Extract validation rules from settings
     */
    private function extractValidationRules($inputType, $settings)
    {
        $rules = [];
        
        // Required rule
        if (isset($settings['validation_rules']) && is_array($settings['validation_rules'])) {
            $rules = $settings['validation_rules'];
        }
        
        // Add input type specific rules
        switch ($inputType) {
            case 'text':
                if (isset($settings['min_length'])) {
                    $rules[] = 'min:' . $settings['min_length'];
                }
                if (isset($settings['max_length'])) {
                    $rules[] = 'max:' . $settings['max_length'];
                }
                if (isset($settings['regex_pattern'])) {
                    $rules[] = 'regex:' . $settings['regex_pattern'];
                }
                break;
                
            case 'textarea':
                if (isset($settings['min_length'])) {
                    $rules[] = 'min:' . $settings['min_length'];
                }
                if (isset($settings['max_length'])) {
                    $rules[] = 'max:' . $settings['max_length'];
                }
                break;
                
            case 'number':
            case 'currency':
            case 'percentage':
                if (isset($settings['min'])) {
                    $rules[] = 'min:' . $settings['min'];
                }
                if (isset($settings['max'])) {
                    $rules[] = 'max:' . $settings['max'];
                }
                if ($inputType === 'number' && isset($settings['decimal_places'])) {
                    $rules[] = 'numeric';
                    if ($settings['decimal_places'] > 0) {
                        $rules[] = 'regex:/^\d+(\.\d{1,' . $settings['decimal_places'] . '})?$/';
                    }
                }
                break;
                
            case 'select':
            case 'radio':
                if (isset($settings['options']) && is_array($settings['options'])) {
                    $validOptions = array_column($settings['options'], 'value');
                    $rules[] = 'in:' . implode(',', $validOptions);
                }
                break;
                
            case 'checkbox':
                if (isset($settings['options']) && is_array($settings['options'])) {
                    $validOptions = array_column($settings['options'], 'value');
                    $rules[] = 'array';
                    $rules[] = 'in:' . implode(',', $validOptions);
                    if (isset($settings['min_selected'])) {
                        $rules[] = 'min:' . $settings['min_selected'];
                    }
                    if (isset($settings['max_selected'])) {
                        $rules[] = 'max:' . $settings['max_selected'];
                    }
                }
                break;
                
            case 'date':
            case 'datetime':
                $rules[] = 'date';
                if (isset($settings['min_date'])) {
                    $rules[] = 'after_or_equal:' . $settings['min_date'];
                }
                if (isset($settings['max_date'])) {
                    $rules[] = 'before_or_equal:' . $settings['max_date'];
                }
                break;
                
            case 'time':
                $rules[] = 'date_format:H:i';
                break;
                
            case 'image':
            case 'file':
                $rules[] = 'array';
                if (isset($settings['max_files'])) {
                    $rules[] = 'max:' . $settings['max_files'];
                }
                if (isset($settings['max_size'])) {
                    $rules[] = 'max:' . $settings['max_size'];
                }
                if (isset($settings['allowed_mimes'])) {
                    $rules[] = 'mimes:' . implode(',', $settings['allowed_mimes']);
                }
                break;
        }
        
        return $rules;
    }

    /**
     * Get UI configuration for frontend
     */
    private function getUIConfig($inputType, $settings)
    {
        $config = [
            'component' => $this->getComponentType($inputType),
            'props' => []
        ];
        
        switch ($inputType) {
            case 'text':
                $config['props'] = [
                    'type' => 'text',
                    'placeholder' => $settings['placeholder'] ?? '',
                    'capitalization' => $settings['capitalization'] ?? 'none',
                ];
                break;
                
            case 'textarea':
                $config['props'] = [
                    'rows' => $settings['rows'] ?? 4,
                    'placeholder' => $settings['placeholder'] ?? '',
                    'richText' => $settings['rich_text'] ?? false,
                    'showDamage' => $settings['show_damage'] ?? false,
                    'damageCategoryId' => $settings['damage_category_id'] ?? null,
                    'damageIds' => $settings['damage_ids'] ?? [],
                ];
                break;
                
            case 'select':
                $config['props'] = [
                    'placeholder' => $settings['placeholder'] ?? 'Pilih...',
                    'searchable' => $settings['searchable'] ?? true,
                    'allowCustom' => $settings['allow_custom'] ?? false,
                    'options' => $this->formatOptions($settings['options'] ?? []),
                ];
                break;
                
            case 'radio':
            case 'checkbox':
                $config['props'] = [
                    'layout' => $settings['layout'] ?? 'vertical',
                    'options' => $this->formatOptionsWithSettings($settings['options'] ?? []),
                ];
                break;
                
            case 'image':
            case 'file':
                $config['props'] = [
                    'maxFiles' => $settings['max_files'] ?? 1,
                    'maxSize' => $settings['max_size'] ?? 2048,
                    'allowedMimes' => $settings['allowed_mimes'] ?? [],
                    'showOption' => $settings['show_option'] ?? false,
                    'options' => $settings['options'] ?? [],
                ];
                break;
        }
        
        // Add trigger configuration
        if (isset($settings['is_triggered']) && $settings['is_triggered']) {
            $config['trigger'] = [
                'enabled' => true,
                'parentItems' => $settings['parent_item_id'] ?? [],
            ];
        }
        
        return $config;
    }

    /**
     * Format options for select/radio/checkbox
     */
    private function formatOptions($options)
    {
        return array_map(function ($option) {
            return [
                'value' => $option['value'] ?? '',
                'label' => $option['label'] ?? '',
            ];
        }, $options);
    }

    /**
     * Format options with nested settings
     */
    private function formatOptionsWithSettings($options)
    {
        return array_map(function ($option) {
            $formatted = [
                'value' => $option['value'] ?? '',
                'label' => $option['label'] ?? '',
                'showTextarea' => $option['show_textarea'] ?? false,
                'textareaIsRequired' => $option['textarea_is_required'] ?? true,
                'showImage' => $option['show_image'] ?? false,
                'imageIsRequired' => $option['image_is_required'] ?? true,
                'showTrigger' => $option['settings']['show_trigger'] ?? false,
                'targetItemId' => $option['settings']['target_item_id'] ?? [],
            ];
            
            if ($formatted['showTextarea']) {
                $formatted['textareaSettings'] = $option['settings']['textarea'] ?? $this->transformSettings('textarea', []);
            }
            
            if ($formatted['showImage']) {
                $formatted['imageSettings'] = $option['settings']['image'] ?? $this->transformSettings('image', []);
            }
            
            return $formatted;
        }, $options);
    }

    /**
     * Get component type for frontend
     */
    private function getComponentType($inputType)
    {
        $map = [
            'text' => 'TextInput',
            'textarea' => 'Textarea',
            'number' => 'NumberInput',
            'currency' => 'CurrencyInput',
            'percentage' => 'PercentageInput',
            'select' => 'Select',
            'radio' => 'RadioGroup',
            'checkbox' => 'CheckboxGroup',
            'boolean' => 'Checkbox',
            'date' => 'DatePicker',
            'datetime' => 'DateTimePicker',
            'time' => 'TimePicker',
            'image' => 'ImageUpload',
            'file' => 'FileUpload',
            'color' => 'ColorPicker',
            'rating' => 'Rating',
            'slider' => 'Slider',
            'switch' => 'Switch',
            'signature' => 'SignaturePad',
            'location' => 'LocationPicker',
        ];
        
        return $map[$inputType] ?? 'TextInput';
    }

    /**
     * Get existing results for inspection
     */
    private function getExistingResults($inspectionId)
    {
        $results = InspectionResult::where('inspection_id', $inspectionId)
            ->with('inspectionImages')
            ->get()
            ->keyBy('inspection_item_id');
            
        $formatted = [];
        
        foreach ($results as $itemId => $result) {
            $formatted[$itemId] = [
                'id' => $result->id,
                'status' => $result->status,
                'note' => $result->note,
                'extra_data' => $result->extra_data ?? [],
                'images' => $result->inspectionImages->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->image_url,
                        'caption' => $image->caption,
                        'path' => $image->image_path,
                    ];
                }),
                'created_at' => $result->created_at,
                'updated_at' => $result->updated_at,
            ];
        }
        
        return $formatted;
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
            'created_at' => $result->created_at,
        ];
    }

    /**
     * Get validation configuration for frontend
     */
    private function getValidationConfig($templateStructure)
    {
        $config = [];
        
        foreach ($templateStructure['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $config[$item['inspection_item_id']] = [
                    'rules' => $item['validation_rules'],
                    'is_required' => $item['is_required'],
                    'input_type' => $item['input_type'],
                ];
            }
        }
        
        return $config;
    }

    /**
     * Get damage categories for frontend
     */
    private function getDamageCategories()
    {
        return DamageCategory::where('is_active', true)
            ->with(['damages' => function ($query) {
                $query->where('is_active', true)
                      ->orderBy('label');
            }])
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'damages' => $category->damages->map(function ($damage) {
                        return [
                            'id' => $damage->id,
                            'label' => $damage->label,
                            'value' => $damage->value,
                            'description' => $damage->description,
                        ];
                    }),
                ];
            });
    }

    /**
     * Get transmissions for frontend
     */
    private function getTransmissions()
    {
        return Transmission::orderBy('name')
            ->get()
            ->map(function ($transmission) {
                return [
                    'id' => $transmission->id,
                    'name' => $transmission->name,
                    'description' => $transmission->description,
                    'is_active' => $transmission->is_active,
                ];
            });
    }

    /**
     * Submit multiple items at once (for form submission)
     */
    public function submitInspectionForm(Request $request, $inspectionId)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.inspection_item_id' => 'required|integer|exists:inspection_items,id',
            'items.*.status' => 'nullable|string|max:255',
            'items.*.note' => 'nullable|string',
            'items.*.extra_data' => 'nullable|array',
            'items.*.images' => 'nullable|array',
            'items.*.images.*' => 'nullable|image|max:5120',
            'is_final' => 'boolean',
            'notes' => 'nullable|string',
            'document' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $inspection = Inspection::findOrFail($inspectionId);
            
            // Validate each item based on its settings
            $validationErrors = [];
            $submittedItems = [];
            
            foreach ($request->items as $index => $itemData) {
                // Get section item to validate based on settings
                $sectionItem = SectionItem::where('inspection_item_id', $itemData['inspection_item_id'])
                    ->whereHas('menuSection', function ($query) use ($inspection) {
                        $query->where('template_id', $inspection->template_id);
                    })
                    ->first();
                
                if (!$sectionItem) {
                    continue;
                }
                
                // Validate based on item settings
                $itemValidator = Validator::make($itemData, [
                    'inspection_item_id' => 'required|exists:inspection_items,id',
                    'status' => $this->getItemValidationRules($sectionItem, 'status'),
                    'note' => $this->getItemValidationRules($sectionItem, 'note'),
                    'extra_data' => 'nullable|array',
                ]);
                
                if ($itemValidator->fails()) {
                    $validationErrors[$itemData['inspection_item_id']] = $itemValidator->errors();
                    continue;
                }
                
                // Create or update result
                $result = InspectionResult::updateOrCreate(
                    [
                        'inspection_id' => $inspectionId,
                        'inspection_item_id' => $itemData['inspection_item_id']
                    ],
                    [
                        'status' => $itemData['status'] ?? null,
                        'note' => $itemData['note'] ?? null,
                        'extra_data' => $itemData['extra_data'] ?? [],
                    ]
                );
                
                $submittedItems[] = $result;
                
                // Handle image uploads
                if (isset($itemData['images']) && is_array($itemData['images'])) {
                    foreach ($itemData['images'] as $imageFile) {
                        $path = $imageFile->store("inspections/{$inspectionId}/images", 'public');
                        
                        InspectionImage::create([
                            'inspection_id' => $inspectionId,
                            'inspection_item_id' => $itemData['inspection_item_id'],
                            'image_path' => $path,
                            'caption' => $itemData['caption'] ?? null
                        ]);
                    }
                }
            }
            
            if (!empty($validationErrors)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Some items failed validation',
                    'errors' => $validationErrors
                ], 422);
            }
            
            // If this is final submission
            if ($request->is_final) {
                // Validate all required items are completed
                $missingRequired = $this->validateRequiredItems($inspection);
                if (!empty($missingRequired)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Some required items are missing',
                        'missing_items' => $missingRequired
                    ], 422);
                }
                
                // Handle document upload
                if ($request->hasFile('document')) {
                    $documentPath = $request->file('document')->store("inspections/{$inspectionId}/documents", 'public');
                    $inspection->document_path = $documentPath;
                }
                
                $inspection->update([
                    'status' => 'under_review',
                    'notes' => $request->notes ?? $inspection->notes,
                    'document_path' => $inspection->document_path
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => $request->is_final ? 'Inspection submitted successfully' : 'Items saved successfully',
                'data' => [
                    'submitted_count' => count($submittedItems),
                    'inspection' => $inspection->fresh(),
                    'progress_percentage' => $inspection->fresh()->progress_percentage,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save inspection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get validation rules for specific item field
     */
    private function getItemValidationRules(SectionItem $item, $field)
    {
        $rules = [];
        
        if ($field === 'status' && $item->is_required) {
            $rules[] = 'required';
        }
        
        if ($field === 'note' && $item->input_type === 'textarea' && $item->is_required) {
            $rules[] = 'required';
        }
        
        // Add rules from settings
        $validationRules = $item->getSetting('validation_rules', []);
        if (is_array($validationRules)) {
            foreach ($validationRules as $rule) {
                if (str_contains($rule, $field) || $rule === 'required') {
                    $rules[] = $rule;
                }
            }
        }
        
        return !empty($rules) ? implode('|', $rules) : 'nullable';
    }

    /**
     * Validate all required items are completed
     */
    private function validateRequiredItems(Inspection $inspection)
    {
        $requiredItems = SectionItem::whereHas('menuSection', function ($query) use ($inspection) {
                $query->where('template_id', $inspection->template_id);
            })
            ->where('is_required', true)
            ->where('is_active', true)
            ->pluck('inspection_item_id')
            ->toArray();

        $completedItems = InspectionResult::where('inspection_id', $inspection->id)
            ->whereIn('inspection_item_id', $requiredItems)
            ->where(function ($query) {
                $query->whereNotNull('status')
                      ->orWhereNotNull('note');
            })
            ->pluck('inspection_item_id')
            ->toArray();

        $missingItems = array_diff($requiredItems, $completedItems);

        if (empty($missingItems)) {
            return [];
        }

        // Get item details for error message
        return InspectionItem::whereIn('id', $missingItems)
            ->get()
            ->map(function ($item) {
                return [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                ];
            });
    }

    /**
     * Get damage results with optimized query
     */
    public function getDamageResultsOptimized($inspectionId)
    {
        try {
            $inspection = Inspection::findOrFail($inspectionId);
            
            // Get damage sections with items
            $damageSections = MenuSection::where('template_id', $inspection->template_id)
                ->where('section_type', 'damage')
                ->where('is_active', true)
                ->with(['sectionItems' => function ($query) {
                    $query->where('is_active', true)
                          ->where('is_visible', true)
                          ->with('inspectionItem');
                }])
                ->orderBy('sort_order')
                ->get();
            
            // Get results for damage items only
            $damageItemIds = collect();
            foreach ($damageSections as $section) {
                $damageItemIds = $damageItemIds->merge(
                    $section->sectionItems->pluck('inspection_item_id')
                );
            }
            
            $results = InspectionResult::where('inspection_id', $inspectionId)
                ->whereIn('inspection_item_id', $damageItemIds)
                ->with('inspectionImages')
                ->get()
                ->keyBy('inspection_item_id');
            
            // Format response
            $response = [];
            foreach ($damageSections as $section) {
                $sectionData = [
                    'section' => [
                        'id' => $section->id,
                        'name' => $section->name,
                        'section_type' => $section->section_type,
                    ],
                    'items' => []
                ];
                
                foreach ($section->sectionItems as $item) {
                    $itemData = $this->transformSectionItem($item, $inspectionId);
                    
                    // Add result if exists
                    if (isset($results[$item->inspection_item_id])) {
                        $result = $results[$item->inspection_item_id];
                        $itemData['result'] = [
                            'id' => $result->id,
                            'status' => $result->status,
                            'note' => $result->note,
                            'images' => $result->inspectionImages->map(function ($image) {
                                return [
                                    'id' => $image->id,
                                    'url' => $image->image_url,
                                ];
                            }),
                        ];
                        $itemData['has_result'] = true;
                    } else {
                        $itemData['has_result'] = false;
                    }
                    
                    $sectionData['items'][] = $itemData;
                }
                
                $response[] = $sectionData;
            }
            
            return response()->json([
                'success' => true,
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch damage results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trigger relationships for frontend
     */
    public function getTriggerRelationships($inspectionId)
    {
        try {
            $inspection = Inspection::findOrFail($inspectionId);
            
            // Get all items with trigger settings
            $triggerItems = SectionItem::whereHas('menuSection', function ($query) use ($inspection) {
                    $query->where('template_id', $inspection->template_id);
                })
                ->whereJsonLength('settings->parent_item_id', '>', 0)
                ->with(['inspectionItem'])
                ->get();
            
            // Get items that can trigger others
            $parentItems = SectionItem::whereHas('menuSection', function ($query) use ($inspection) {
                    $query->where('template_id', $inspection->template_id);
                })
                ->where(function ($query) {
                    $query->whereJsonContains('settings->options', function ($subQuery) {
                        $subQuery->whereJsonLength('settings->target_item_id', '>', 0);
                    });
                })
                ->with(['inspectionItem'])
                ->get();
            
            $relationships = [];
            
            // Map trigger relationships
            foreach ($triggerItems as $item) {
                $settings = $item->settings ?? [];
                $parentIds = $settings['parent_item_id'] ?? [];
                
                foreach ($parentIds as $parentId) {
                    $relationships[] = [
                        'parent_item_id' => $parentId,
                        'child_item_id' => $item->inspection_item_id,
                        'parent_name' => $item->inspectionItem->name ?? 'Unknown',
                        'child_name' => $item->inspectionItem->name ?? 'Unknown',
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'triggers' => $relationships,
                    'trigger_items' => $triggerItems->map(function ($item) {
                        return [
                            'id' => $item->inspection_item_id,
                            'name' => $item->inspectionItem->name ?? 'Unknown',
                            'input_type' => $item->input_type,
                            'parent_items' => $item->settings['parent_item_id'] ?? [],
                        ];
                    }),
                    'parent_items' => $parentItems->map(function ($item) {
                        return [
                            'id' => $item->inspection_item_id,
                            'name' => $item->inspectionItem->name ?? 'Unknown',
                            'input_type' => $item->input_type,
                            'options' => $item->settings['options'] ?? [],
                        ];
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch trigger relationships',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}