<?php

namespace App\Filament\Resources\FormBuilder\MenuSectionResource\RelationManagers;

use App\Models\DirectDB\VehicleData\Transmission;
use App\Models\FormBuilder\MenuSection;
use App\Models\FormBuilder\SectionItem;
use App\Models\MasterData\Damage\DamageCategory;
use App\Models\MasterData\Damage\Damages;
use App\Models\MasterData\InspectionItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SectionItemRelationManager extends RelationManager
{
    protected static string $relationship = 'sectionItems';

    protected static ?string $title = 'Section Items';

    public function form(Form $form): Form
    {
        return $form->schema([
            // === Inspection Item (Full Width) ===
            Forms\Components\Select::make('inspection_item_id')
                ->relationship('inspectionItem', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->label('Inspection Item')
                ->helperText('Pilih item inspeksi yang akan digunakan')
                ->columnSpanFull(),

            Forms\Components\Hidden::make('sort_order')
                ->default(0),

            // === Reusable Main Config ===
                ...$this->mainConfigSchema(),

            // 🔥 TRIGGER SETTINGS – SELALU MUNCUL
                ...$this->triggerSettingsSchema(),
            // === Dynamic Settings ===
            Forms\Components\Group::make()
                ->schema(function (callable $get) {
                    $inputType = $get('input_type');
                    return $inputType ? $this->getSettingsSchema($inputType) : [];
                })
                ->columnSpanFull()
                ->statePath('settings'),

             // === Pengaturan Khusus ===
                ...$this->getSettingsKhusus(),

        ]);
    }

    private function mainConfigSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('input_type')
                        ->required()
                        ->options([
                            'text' => 'Text',
                            'textarea' => 'Textarea',
                            'number' => 'Number',
                            'currency' => 'Currency',
                            'percentage' => 'Percentage',
                            'select' => 'Select / Dropdown',
                            'radio' => 'Radio Button',
                            'checkbox' => 'Checkbox Group',
                            'boolean' => 'Checkbox (Boolean)',
                            'date' => 'Date',
                            'datetime' => 'Datetime',
                            'time' => 'Time',
                            'image' => 'Image Upload',
                            'file' => 'File Upload',
                            'color' => 'Color Picker',
                            'rating' => 'Rating',
                            'slider' => 'Slider',
                            'switch' => 'Switch',
                        ])
                        ->reactive()
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $this->resetSettings($set))
                        ->label('Tipe Input'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->inline(false)
                        ->label('Aktif'),

                    Forms\Components\Toggle::make('is_visible')
                        ->default(true)
                        ->label('Ditampilkan'),

                    Forms\Components\Toggle::make('is_required')
                        ->default(true)
                        ->label('Wajib Diisi'),
                ]),
        ];
    }

    private function resetSettings(callable $set): void
    {
        $set('settings', []);
    }

    private function triggerSettingsSchema(): array
    {
        return [
            Forms\Components\Section::make('Pengaturan Trigger')
                ->schema([
                    Forms\Components\Toggle::make('settings.is_triggered')
                        ->label('Point ini Dipicu oleh Point Lain')
                        ->default(false)
                        ->reactive()
                        ->helperText('Aktifkan jika point ini muncul hanya ketika dipicu oleh point lain')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('settings.parent_item_id')
                        ->label('Parent Point (Pemicu)')
                        ->options(function ($get, $state, $record) {
                            if (!$record) {
                                return [];
                            }

                            $recordId = $record->id;
                            
                            $points = SectionItem::query()
                                ->with(['inspectionItem'])
                                ->where(function ($query) use ($recordId) {
                                    // 1. Cari di level pertama settings.target_item_id
                                    $query->where(function ($subQuery) use ($recordId) {
                                        $subQuery->whereJsonContains('settings->target_item_id', (string) $recordId)
                                                ->orWhereJsonContains('settings->target_item_id', (int) $recordId);
                                    })
                                    // 2. Cari di dalam settings.options[].settings.target_item_id
                                    ->orWhere(function ($subQuery) use ($recordId) {
                                        // Gunakan JSON_SEARCH untuk mencari nilai dalam array JSON
                                        $subQuery->whereRaw("EXISTS (
                                                        SELECT 1 
                                                        FROM JSON_TABLE(
                                                            JSON_EXTRACT(settings, '$.settings.options'),
                                                            '$[*]' COLUMNS(
                                                                item_settings JSON PATH '$.settings'
                                                            )
                                                        ) AS opt
                                                        WHERE JSON_EXTRACT(opt.item_settings, '$.target_item_id') LIKE ?
                                                        OR JSON_CONTAINS(JSON_EXTRACT(opt.item_settings, '$.target_item_id'), ?)
                                                    )", ["%\"$recordId\"%", "\"$recordId\""]);
                                                
                                    });
                                })
                                ->where('id', '!=', $recordId)
                                ->get()
                                ->mapWithKeys(function ($sectionItem) {
                                    $pointName = $sectionItem->inspectionItem?->name ?? 'Tanpa Nama';
                                    $inputType = $sectionItem->input_type;
                                    return [$sectionItem->id => "{$pointName} ({$inputType})"];
                                })
                                ->toArray();

                            return $points;
                        })
                        ->searchable()
                        ->multiple()
                        ->columnSpanFull()
                        ->helperText('Point-point yang memicu point ini muncul')
                        ->visible(fn (callable $get) => $get('settings.is_triggered') === true)
                        ->reactive()
                        ->disabled(fn ($get) => !$get('settings.is_triggered')),
                ])
                ->collapsible()
                ->collapsed(false),
        ];
    }
    
    private function getSettingsKhusus()
    {
        return [
             Forms\Components\Fieldset::make('Pengaturan Khusus')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([

                                Forms\Components\Select::make('settings.transmission')
                                    ->label('Tipe Transmisi')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->allowHtml()

                                    ->options(fn () =>
                                        Transmission::orderBy('name')
                                            ->get()
                                            ->mapWithKeys(function ($item) {

                                                $badge = $item->is_active
                                                    ? ''
                                                    : "<span class='text-xs text-red-500'>(Nonaktif)</span>";

                                                return [
                                                    $item->name => "
                                                        <div>
                                                            <div class='font-medium'>
                                                                {$item->name} {$badge}
                                                            </div>
                                                            <div class='text-xs text-gray-500'>
                                                                {$item->description}
                                                            </div>
                                                        </div>
                                                    ",
                                                ];
                                            })
                                            ->toArray()
                                    )

                                    ->disableOptionWhen(function (string $value): bool {
                                        return Transmission::where('name', $value)
                                            ->where('is_active', false)
                                            ->exists();
                                    })

                                    ->helperText('Pilih tipe transmisi sesuai kendaraan')
                                    ->columnSpan(1),

                                // Fuel Type (Select)
                                Forms\Components\Select::make('settings.fuel_type')
                                    ->label('Tipe Bahan Bakar')
                                    ->options([
                                        'Bensin' => 'Bensin',
                                        'Diesel' => 'Diesel',
                                        'Listrik' => 'Listrik',
                                        'Hybrid' => 'Hybrid',
                                        'Plug-in Hybrid' => 'Plug-in Hybrid',
                                    ])
                                    ->helperText('Pilih bahan bakar')
                                    ->columnSpan(1),
                                
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('settings.doors')
                                            ->numeric()
                                            ->label('Number of Doors')
                                            ->helperText('Contoh: 2 atau 4'),

                                        Forms\Components\Select::make('settings.drive')
                                            ->label('Drive Type')
                                            ->options([
                                                'FWD' => 'FWD',
                                                'RWD' => 'RWD',
                                                'AWD' => 'AWD',
                                                '4WD' => '4WD',
                                            ]),
                                    ]),

                                Forms\Components\Toggle::make('settings.pickup')
                                    ->label('Pickup')
                                    ->inline(false),

                                Forms\Components\Toggle::make('settings.box')
                                    ->label('Box / Cargo')
                                    ->inline(false),

                            ]),

                    ])
                    ->columnSpanFull()
        ];
    }

    /**
     * Mendapatkan skema pengaturan dinamis berdasarkan tipe input
     */
    private function getSettingsSchema(string $inputType): array
    {
        $schemas = [

            'text' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('settings.min_length')
                            ->numeric()
                            ->minValue(0)
                            ->label('Panjang Minimal')
                            ->helperText('Batas minimum karakter yang harus diisi.'),
                        
                        Forms\Components\TextInput::make('settings.max_length')
                            ->numeric()
                            ->default(255)
                            ->label('Panjang Maksimal')
                            ->helperText('Batas maksimum karakter yang diperbolehkan.'),
                        
                        Forms\Components\TextInput::make('settings.placeholder')
                            ->label('Teks Placeholder')
                            ->helperText('Contoh teks yang muncul di dalam kotak sebelum diisi.'),
                        
                        Forms\Components\Select::make('settings.capitalization')
                            ->options([
                                'none' => 'Tidak Ada',
                                'words' => 'Kapitalisasi Kata',
                                'sentences' => 'Kapitalisasi Kalimat',
                                'characters' => 'Huruf Besar Semua',
                            ])
                            ->label('Kapitalisasi Teks')
                            ->helperText('Atur perubahan huruf besar/kecil otomatis.'),
                        
                        Forms\Components\TextInput::make('settings.regex_pattern')
                            ->label('Pola Regex')
                            ->placeholder('/^[a-zA-Z0-9\s]+$/')
                            ->columnSpanFull()
                            ->helperText('Aturan validasi khusus menggunakan kode Regex.'),
                        
                        Forms\Components\Toggle::make('settings.allow_spaces')
                            ->default(true)
                            ->label('Izinkan Spasi')
                            ->helperText('Bolehkan penggunaan spasi dalam teks.'),
                        
                        Forms\Components\Toggle::make('settings.allow_special_chars')
                            ->default(true)
                            ->label('Izinkan Karakter Khusus')
                            ->helperText('Bolehkan simbol seperti @, #, $, dll.'),
                        
                        Forms\Components\Toggle::make('settings.trim_spaces')
                            ->default(true)
                            ->label('Hapus Spasi Tambahan (Trim)')
                            ->helperText('Otomatis hapus spasi di awal dan akhir teks.'),
                    ]),
            ],


            'textarea' => [
                Forms\Components\Grid::make(2)
                    ->schema($this->textareaSettings('settings.')),
            ],

            'number' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('settings.min')
                            ->numeric()
                            ->label('Nilai Minimal')
                            ->helperText('Batas angka terkecil yang boleh diinput.'),
                        
                        Forms\Components\TextInput::make('settings.max')
                            ->numeric()
                            ->label('Nilai Maksimal')
                            ->helperText('Batas angka terbesar yang diperbolehkan.'),
                        
                        Forms\Components\TextInput::make('settings.step')
                            ->numeric()
                            ->default(1)
                            ->label('Nilai Kelipatan (Step)')
                            ->helperText('Interval kenaikan angka (contoh: 5, 10, 100).'),
                        
                        Forms\Components\TextInput::make('settings.decimal_places')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(4)
                            ->label('Jumlah Desimal')
                            ->helperText('Berapa banyak angka di belakang koma.'),
                        
                        Forms\Components\TextInput::make('settings.prefix')
                            ->label('Awalan (Contoh: KM)')
                            ->helperText('Simbol atau teks di depan angka.'),
                        
                        Forms\Components\TextInput::make('settings.suffix')
                            ->label('Akhiran (Contoh: kg)')
                            ->helperText('Simbol atau teks di belakang angka.'),

                        Forms\Components\Toggle::make('settings.thousand_separator')
                            ->default(true)
                            ->label('Gunakan Pemisah Ribuan')
                            ->helperText('Gunakan titik atau koma sebagai pemisah ribuan.'),
                        
                        Forms\Components\Toggle::make('settings.allow_negative')
                            ->default(false)
                            ->label('Izinkan Nilai Negatif')
                            ->helperText('Bolehkan pengguna menginput angka di bawah nol.'),
                    ]),
            ],


            'currency' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('settings.currency_code')
                            ->options([
                                'IDR' => 'IDR - Rupiah Indonesia',
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                                'JPY' => 'JPY - Japanese Yen',
                                'SGD' => 'SGD - Singapore Dollar',
                            ])
                            ->default('IDR')
                            ->label('Kode Mata Uang')
                            ->helperText('Standar internasional kode mata uang.'),

                        Forms\Components\TextInput::make('settings.currency_symbol')
                            ->default('Rp')
                            ->label('Simbol Mata Uang')
                            ->helperText('Tanda mata uang yang akan muncul di kolom.'),
                        
                        Forms\Components\TextInput::make('settings.min_amount')
                            ->numeric()
                            ->default(0)
                            ->label('Jumlah Minimal')
                            ->helperText('Nominal uang paling rendah yang diperbolehkan.'),
                        
                        Forms\Components\TextInput::make('settings.max_amount')
                            ->numeric()
                            ->label('Jumlah Maksimal')
                            ->helperText('Nominal uang paling tinggi yang diperbolehkan.'),
                        
                        Forms\Components\TextInput::make('settings.decimal_places')
                            ->numeric()
                            ->default(0)
                            ->label('Jumlah Desimal')
                            ->helperText('Format sen (0 untuk Rupiah, 2 untuk Dollar).'),
                        
                        Forms\Components\Toggle::make('settings.allow_negative')
                            ->default(false)
                            ->label('Izinkan Saldo Negatif')
                            ->helperText('Bolehkan nilai uang di bawah nol (hutang/minus).'),
                        
                        Forms\Components\TextInput::make('settings.help_text')
                            ->label('Teks Bantuan')
                            ->columnSpanFull()
                            ->helperText('Pesan panduan tambahan untuk pengisi form.'),
                    ]),
            ],


            'percentage' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('settings.min')
                            ->numeric()
                            ->default(0)
                            ->label('Persentase Minimal')
                            ->helperText('Batas paling rendah (umumnya 0%).'),
                        
                        Forms\Components\TextInput::make('settings.max')
                            ->numeric()
                            ->default(100)
                            ->label('Persentase Maksimal')
                            ->helperText('Batas paling tinggi (umumnya 100%).'),
                        
                        Forms\Components\TextInput::make('settings.step')
                            ->numeric()
                            ->default(0.1)
                            ->label('Nilai Kelipatan')
                            ->helperText('Contoh: 0.1 agar bisa input 10.5%.'),
                        
                        Forms\Components\TextInput::make('settings.decimal_places')
                            ->numeric()
                            ->default(1)
                            ->label('Jumlah Desimal')
                            ->helperText('Jumlah angka di belakang koma.'),
                        
                        Forms\Components\Toggle::make('settings.show_percent_sign')
                            ->default(true)
                            ->label('Tampilkan Tanda %')
                            ->helperText('Munculkan simbol persen di dalam kotak input.'),
                        
                        Forms\Components\Toggle::make('settings.allow_over_100')
                            ->default(false)
                            ->label('Izinkan Lebih dari 100%')
                            ->helperText('Aktifkan jika ingin input nilai seperti 110% atau 200%.'),
                    ]),
            ],


            'select' => $this->optionSettings('select'),
            'radio' => $this->optionSettings('radio'),
            'checkbox' => $this->optionSettings('checkbox'),

            'boolean' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('settings.checked_value')
                            ->default('true')
                            ->label('Nilai Saat Dicentang'),
                        
                        Forms\Components\TextInput::make('settings.unchecked_value')
                            ->default('false')
                            ->label('Nilai Saat Tidak Dicentang'),
                        
                        Forms\Components\TextInput::make('settings.label')
                            ->label('Label Kotak Centang'),
                        
                        Forms\Components\Toggle::make('settings.default')
                            ->default(false)
                            ->label('Bawaan Dicentang'),
                    ]),
            ],

            'date' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DatePicker::make('settings.min_date')
                            ->label('Tanggal Minimal')
                            ->helperText('Batas tanggal paling awal yang bisa dipilih.'),
                        
                        Forms\Components\DatePicker::make('settings.max_date')
                            ->label('Tanggal Maksimal')
                            ->helperText('Batas tanggal paling akhir yang diperbolehkan.'),
                        
                        Forms\Components\TextInput::make('settings.format')
                            ->default('Y-m-d')
                            ->label('Format Simpan (Database)')
                            ->helperText('Contoh: Y-m-d (2023-12-31).'),
                        
                        Forms\Components\TextInput::make('settings.display_format')
                            ->default('d/m/Y')
                            ->label('Format Tampilan')
                            ->helperText('Contoh: d/m/Y (31/12/2023).'),
                        
                        Forms\Components\TextInput::make('settings.placeholder')
                            ->label('Teks Placeholder')
                            ->helperText('Pesan contoh di dalam kolom (misal: Pilih Tanggal).'),

                        Forms\Components\Toggle::make('settings.show_time')
                            ->default(false)
                            ->label('Tampilkan Waktu')
                            ->helperText('Aktifkan jika ingin menyertakan jam dan menit.'),
                    ]),
            ],

            'datetime' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('settings.min_date') // Gunakan DateTimePicker
                            ->label('Tanggal & Waktu Minimal')
                            ->helperText('Batas awal tanggal dan jam.'),
                        
                        Forms\Components\DateTimePicker::make('settings.max_date')
                            ->label('Tanggal & Waktu Maksimal')
                            ->helperText('Batas akhir tanggal dan jam.'),
                        
                        Forms\Components\TextInput::make('settings.format')
                            ->default('Y-m-d H:i:s')
                            ->label('Format Simpan (Database)')
                            ->helperText('Contoh: Y-m-d H:i:s (2023-12-31 23:59:59).'),
                        
                        Forms\Components\TextInput::make('settings.display_format')
                            ->default('d/m/Y H:i')
                            ->label('Format Tampilan')
                            ->helperText('Contoh: d/m/Y H:i (31/12/2023 23:59).'),
                        
                        Forms\Components\TextInput::make('settings.placeholder')
                            ->label('Teks Placeholder')
                            ->helperText('Contoh: Pilih tanggal dan waktu.'),
                    ]),
            ],

            'time' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TimePicker::make('settings.min_time')
                            ->label('Waktu Minimal')
                            ->helperText('Batas jam paling awal.'),
                        
                        Forms\Components\TimePicker::make('settings.max_time')
                            ->label('Waktu Maksimal')
                            ->helperText('Batas jam paling akhir.'),
                        
                        Forms\Components\TextInput::make('settings.format')
                            ->default('H:i')
                            ->label('Format Waktu')
                            ->helperText('H:i (24 jam) atau h:i A (12 jam PM/AM).'),
                        
                        Forms\Components\TextInput::make('settings.step_minutes')
                            ->numeric()
                            ->default(15)
                            ->label('Kelipatan Menit')
                            ->helperText('Interval menit pada pilihan (misal: tiap 15 menit).'),
                    ]),
            ],


            'image' => [
                Forms\Components\Grid::make(2)
                    ->schema($this->fileImageSettings('settings.', 'image')),
            ],

            'file' => [
                Forms\Components\Grid::make(2)
                    ->schema($this->fileImageSettings('settings.', 'file')),
            ],

            'color' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\ColorPicker::make('settings.default')
                            ->default('#3490dc')
                            ->label('Warna Bawaan'),
                        
                        Forms\Components\Toggle::make('settings.show_palette')
                            ->default(true)
                            ->label('Tampilkan Palet Warna'),
                        
                        Forms\Components\TagsInput::make('settings.preset_colors')
                            ->separator(',')
                            ->label('Pilihan Warna Preset')
                            ->columnSpanFull(),
                    ]),
            ],

            'rating' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('settings.max_rating')
                            ->numeric()
                            ->default(5)
                            ->label('Rating Maksimal'),
                        
                        Forms\Components\TextInput::make('settings.step')
                            ->numeric()
                            ->default(1)
                            ->label('Kelipatan Rating'),
                        
                        Forms\Components\Select::make('settings.icon')
                            ->options([
                                'star' => 'Bintang',
                                'heart' => 'Hati',
                                'thumb-up' => 'Jempol',
                                'flag' => 'Bendera',
                            ])
                            ->default('star')
                            ->label('Tipe Ikon'),

                        Forms\Components\Toggle::make('settings.show_labels')
                            ->default(true)
                            ->label('Tampilkan Label'),
                        
                        Forms\Components\TagsInput::make('settings.labels')
                            ->separator(',')
                            ->label('Label Kustom Rating')
                            ->columnSpanFull(),
                    ]),
            ],

            'slider' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('settings.min')
                            ->numeric()
                            ->default(0)
                            ->label('Nilai Minimal'),
                        
                        Forms\Components\TextInput::make('settings.max')
                            ->numeric()
                            ->default(100)
                            ->label('Nilai Maksimal'),
                        
                        Forms\Components\TextInput::make('settings.step')
                            ->numeric()
                            ->default(1)
                            ->label('Kelipatan'),
                        
                        Forms\Components\Select::make('settings.orientation')
                            ->options([
                                'horizontal' => 'Horizontal',
                                'vertical' => 'Vertikal',
                            ])
                            ->default('horizontal')
                            ->label('Orientasi'),
                        
                        Forms\Components\Toggle::make('settings.show_ticks')
                            ->default(true)
                            ->label('Tampilkan Garis Penanda'),
                        
                        Forms\Components\Toggle::make('settings.show_value')
                            ->default(true)
                            ->label('Tampilkan Nilai Saat Ini'),
                    ]),
            ],

            'switch' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('settings.on_color')
                            ->options([
                                'primary' => 'Utama (Primary)',
                                'success' => 'Sukses (Hijau)',
                                'danger' => 'Bahaya (Merah)',
                                'warning' => 'Peringatan (Kuning)',
                                'info' => 'Info (Biru)',
                            ])
                            ->default('primary')
                            ->label('Warna Aktif'),
                        
                        Forms\Components\Select::make('settings.off_color')
                            ->options([
                                'secondary' => 'Sekunder',
                                'gray' => 'Abu-abu',
                                'slate' => 'Slate',
                            ])
                            ->default('secondary')
                            ->label('Warna Nonaktif'),
                        
                        Forms\Components\TextInput::make('settings.on_label')
                            ->default('Aktif')
                            ->label('Label Aktif'),
                        
                        Forms\Components\TextInput::make('settings.off_label')
                            ->default('Nonaktif')
                            ->label('Label Nonaktif'),
                        
                        Forms\Components\Toggle::make('settings.default')
                            ->default(false)
                            ->label('Status Bawaan'),
                    ]),
            ],

            'signature' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('settings.width')
                            ->numeric()
                            ->default(400)
                            ->label('Lebar Kanvas'),
                        
                        Forms\Components\TextInput::make('settings.height')
                            ->numeric()
                            ->default(200)
                            ->label('Tinggi Kanvas'),
                        
                        Forms\Components\ColorPicker::make('settings.pen_color')
                            ->default('#000000')
                            ->label('Warna Pena'),
                        
                        Forms\Components\ColorPicker::make('settings.background_color')
                            ->default('#ffffff')
                            ->label('Warna Latar'),
                        
                        Forms\Components\Toggle::make('settings.required')
                            ->default(true)
                            ->label('Wajib Tanda Tangan'),
                    ]),
            ],

            'location' => [
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('settings.default_lat')
                            ->numeric()
                            ->default(-6.2088)
                            ->label('Latitude Bawaan'),
                        
                        Forms\Components\TextInput::make('settings.default_lng')
                            ->numeric()
                            ->default(106.8456)
                            ->label('Longitude Bawaan'),
                        
                        Forms\Components\TextInput::make('settings.zoom')
                            ->numeric()
                            ->default(15)
                            ->label('Level Zoom Peta'),
                        
                        Forms\Components\TextInput::make('settings.required_accuracy')
                            ->numeric()
                            ->default(50)
                            ->label('Akurasi yang Dibutuhkan (meter)'),
                        
                        Forms\Components\Toggle::make('settings.show_map')
                            ->default(true)
                            ->label('Tampilkan Pratinjau Peta'),
                    ]),
            ],
        ];

        return $schemas[$inputType] ?? [];
    }

    private function textareaSettings(string $prefix = 'settings.'): array
    {
        return [
            Forms\Components\TextInput::make("{$prefix}rows")
                ->numeric()
                ->default(4)
                ->label('Jumlah Baris'),

            Forms\Components\TextInput::make("{$prefix}placeholder")
                ->label('Teks Placeholder'),

            Forms\Components\TextInput::make("{$prefix}min_length")
                ->numeric()
                ->label('Panjang Minimal'),

            Forms\Components\TextInput::make("{$prefix}max_length")
                ->numeric()
                ->default(2000)
                ->label('Panjang Maksimal'),

            Forms\Components\Toggle::make("{$prefix}rich_text")
                ->default(false)
                ->label('Gunakan Rich Text Editor'),

            Forms\Components\Toggle::make("{$prefix}allow_html")
                ->default(false)
                ->label('Izinkan HTML'),

            Forms\Components\Section::make('Pengaturan Damage')
                ->schema([
                    Forms\Components\Toggle::make("{$prefix}show_damage")
                        ->label('Tampilkan Damage')
                        ->helperText('Jika aktif, akan menampilkan pilihan kerusakan')
                        ->default(false)
                        ->reactive(),

                    Forms\Components\Select::make("{$prefix}damage_category_id")
                        ->label('Kategori Kerusakan')
                        ->options(
                            DamageCategory::where('is_active', true)
                                ->pluck('name', 'id')
                        )
                        ->reactive()
                        ->afterStateUpdated(fn (callable $set) =>
                            $set("{$prefix}damage_ids", [])
                        )
                        ->live(),

                    Forms\Components\CheckboxList::make("{$prefix}damage_ids")
                        ->label('Jenis Kerusakan')
                        ->options(function (callable $get) use ($prefix) {
                            $categoryId = $get("{$prefix}damage_category_id");

                            if (!$categoryId) {
                                return [];
                            }

                            return Damages::query()
                                ->where('damage_category_id', $categoryId)
                                ->where('is_active', true)
                                ->orderBy('label')
                                ->get()
                                ->mapWithKeys(fn ($damage) => [
                                    $damage->id => "{$damage->label} ({$damage->value})",
                                ]);
                        })
                        ->columns(1)
                        ->live(),
                ])
                ->collapsible()
                ->collapsed(fn (callable $get) => !$get("{$prefix}show_damage")),
        ];
    }

    private function optionSettings(string $type, bool $allowImageSetting = true): array
    {
        $isSelect = $type === 'select';
        $isRadio = $type === 'radio';
        $isCheckbox = $type === 'checkbox';

        return [
            Forms\Components\Repeater::make('settings.options')
                ->label('Opsi Pilihan')
                ->schema([
                    // ================= BASIC =================
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('value')
                                ->required()
                                ->label('Nilai Opsi'),

                            Forms\Components\TextInput::make('label')
                                ->required()
                                ->label('Label Tampilan'),
                        ]),

                    Forms\Components\Toggle::make('multi')
                        ->label('Multi Selection')
                        ->default(false)
                        ->visible($isRadio),

                    // ================= TEXTAREA =================
                    Forms\Components\Section::make('Pengaturan Textarea')
                        ->schema([
                            Forms\Components\Toggle::make('show_textarea')
                                ->label('Tampilkan Textarea')
                                ->reactive(),

                            Forms\Components\Toggle::make('textarea_is_required')
                                ->label('Textarea Wajib Diisi')
                                ->default(true)
                                ->visible(fn ($get) => $get('show_textarea')),

                            Forms\Components\Grid::make(2)
                                ->visible(fn ($get) => $get('show_textarea'))
                                ->schema($this->textareaSettings()),
                        ])
                        ->collapsible()
                        ->collapsed(fn ($get) => !$get('show_textarea')),
                    
                                        
                    // =====================TRIGGER=======================
                    Forms\Components\Section::make('Triggers / Pemicu')
                        ->schema([

                            // ================= TOGGLE TRIGGER =================
                            Forms\Components\Toggle::make('settings.show_trigger')
                                ->label('Aktifkan Trigger')
                                ->default(false)
                                ->reactive()
                                ->helperText('Jika aktif, opsi ini dapat memicu point lain'),

                            // ================= TARGET POINT =================
                            Forms\Components\Select::make('settings.target_item_id')
                                ->label('Point yang Dipicu')
                                ->options(function ($get, $state, $record) {

                                    /**
                                    * record DI SINI ADALAH:
                                    * - SectionItem (induk dari options)
                                    * - atau null saat create
                                    */

                                    if (!$record instanceof SectionItem) {
                                        return [];
                                    }

                                    $currentSectionItemId = $record->id;

                                    return SectionItem::query()
                                        ->with('inspectionItem')
                                        ->where('id', '!=', $currentSectionItemId) // ⛔ tidak boleh memicu diri sendiri
                                        ->orderBy('sort_order')
                                        ->get()
                                        ->mapWithKeys(function (SectionItem $item) {
                                            $name = $item->inspectionItem?->name ?? 'Tanpa Nama';
                                            return [
                                                $item->id => "{$name} ({$item->input_type})"
                                            ];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->multiple() // ✅ boleh lebih dari satu target
                                ->columnSpanFull()
                                ->helperText('Pilih point yang akan muncul ketika opsi ini dipilih')
                                ->visible(fn (callable $get) => $get('settings.show_trigger') === true)
                                ->disabled(fn (callable $get) => !$get('settings.show_trigger'))
                                ->reactive(),

                        ])
                        ->collapsible()
                        ->collapsed(fn (callable $get) => !$get('settings.show_trigger')),

                    // ================= IMAGE =================
                    Forms\Components\Section::make('Pengaturan Gambar')
                        ->visible($allowImageSetting)
                        ->schema([
                            Forms\Components\Toggle::make('show_image')
                                ->label('Tampilkan Gambar')
                                ->reactive(),

                            Forms\Components\Toggle::make('image_is_required')
                                ->label('Gambar Wajib Diisi')
                                ->default(true)
                                ->visible(fn ($get) => $get('show_image')),

                            Forms\Components\Grid::make(2)
                                ->visible(fn ($get) => $get('show_image'))
                                ->schema(
                                    $this->fileImageSettings('settings.', 'image', false)
                                ),
                        ])
                        ->collapsible()
                        ->collapsed(fn ($get) => !$get('show_image')),
                ])
                ->columnSpanFull(),

            // ================= SELECT ONLY =================
            Forms\Components\TextInput::make('settings.placeholder')
                ->visible($isSelect),

            Forms\Components\Toggle::make('settings.searchable')
                ->default(true)
                ->visible($isSelect),

            // ================= RADIO & CHECKBOX =================
            Forms\Components\Select::make('settings.layout')
                ->options([
                    'vertical' => 'Vertikal',
                    'horizontal' => 'Horizontal',
                ])
                ->default('vertical')
                ->visible($isRadio || $isCheckbox),

            // ================= CHECKBOX ONLY =================
            Forms\Components\TextInput::make('settings.min_selected')
                ->numeric()
                ->default(0)
                ->visible($isCheckbox),

            Forms\Components\TextInput::make('settings.max_selected')
                ->numeric()
                ->visible($isCheckbox),
        ];
    }

    private function fileImageSettings(
        string $prefix = 'settings.',
        string $type = 'image',
        bool $allowOptionSetting = true
    ): array {
        $isImage = $type === 'image';

        $schema = [
            Forms\Components\TextInput::make("{$prefix}max_size")
                ->numeric()
                ->default(2048)
                ->label('Ukuran Maksimal (KB)'),

            // ✅ ARRAY MIME
            Forms\Components\Select::make("{$prefix}allowed_mimes")
                ->multiple()
                ->options([
                    'jpg' => 'JPG',
                    'jpeg' => 'JPEG',
                    'png' => 'PNG',
                    'webp' => 'WEBP',
                    'pdf' => 'PDF',
                ])
                ->label('Tipe File yang Diizinkan'),

            // ✅ JUMLAH FILE LANGSUNG ANGKA
            Forms\Components\TextInput::make("{$prefix}max_files")
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->label('Jumlah File Maksimal'),
        ];

        if ($isImage) {
            $schema = array_merge($schema, [
                Forms\Components\TextInput::make("{$prefix}max_width")
                    ->numeric()
                    ->label('Lebar Maksimal (px)'),

                Forms\Components\TextInput::make("{$prefix}max_height")
                    ->numeric()
                    ->label('Tinggi Maksimal (px)'),

                Forms\Components\TextInput::make("{$prefix}aspect_ratio")
                    ->placeholder('16:9'),

                Forms\Components\TextInput::make("{$prefix}compression_quality")
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(80)
                    ->label('Kualitas Kompresi (%)'),
            ]);

            // ================= IMAGE OPTION (ROOT IMAGE ONLY) =================
            if ($allowOptionSetting) {
                $schema[] =
                    Forms\Components\Section::make('Pengaturan Opsi untuk Gambar')
                        ->schema([
                            Forms\Components\Toggle::make("{$prefix}show_option")
                                ->label('Tampilkan Opsi Pilihan')
                                ->reactive()
                                ->afterStateUpdated(fn ($state, callable $set) =>
                                    !$state ? $set("settings.options", []) : null
                                ),

                            Forms\Components\Toggle::make("{$prefix}option_is_required")
                                ->label('Opsi Wajib Dipilih')
                                ->default(false),

                            Forms\Components\Grid::make(2)
                                ->schema(
                                    $this->optionSettings('radio', false)
                                ),
                        ])
                        ->collapsible()
                        ->collapsed(fn ($get) => !$get("{$prefix}show_option"));
            }
        }

        return $schema;
    }



    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('inspection_item_id')
            ->columns([
                Tables\Columns\TextColumn::make('inspectionItem.name')
                    ->label('Item Name')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('input_type')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'text', 'textarea' => 'info',
                        'number', 'currency', 'percentage' => 'warning',
                        'select', 'radio', 'checkbox' => 'success',
                        'date', 'datetime', 'time' => 'primary',
                        'image', 'file' => 'danger',
                        default => 'secondary',
                    })
                    ->label('Input Type'),
                
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                
                Tables\Columns\IconColumn::make('is_visible')
                    ->boolean()
                    ->label('Visible'),
                
                Tables\Columns\IconColumn::make('is_required')
                    ->boolean()
                    ->label('Required'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('input_type')
                    ->options([
                        'text' => 'Text',
                        'textarea' => 'Textarea',
                        'number' => 'Number',
                        'currency' => 'Currency',
                        'percentage' => 'Percentage',
                        'select' => 'Select/Dropdown',
                        'radio' => 'Radio Button',
                        'checkbox' => 'Checkbox Group',
                        'boolean' => 'Single Checkbox',
                        'date' => 'Date',
                        'datetime' => 'Datetime',
                        'time' => 'Time',
                        'image' => 'Image',
                        'file' => 'File',
                        'color' => 'Color',
                        'rating' => 'Rating',
                        'slider' => 'Slider',
                        'switch' => 'Switch',
                        'signature' => 'Signature',
                        'location' => 'Location',
                    ]),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visibility'),
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make()
                //     ->label('Tambah Section Item'),
            
                Tables\Actions\Action::make('createMultiple')
                    ->label('Tambah Multiple Points')
                    ->icon('heroicon-o-plus')
                    ->form(function () {
                        $ownerRecord = $this->getOwnerRecord();
                        
                        // Dapatkan template_id dari menu section
                        $templateID = $ownerRecord->template_id;
                        
                        // Cari semua inspection_item_id yang sudah digunakan di template ini
                        $usedInspectionItemIds = SectionItem::whereHas('menuSection', function ($query) use ($templateID) {
                                $query->where('template_id', $templateID);
                            })
                            ->pluck('inspection_item_id');
                        
                        // Ambil inspection items yang belum digunakan di template ini
                        $items = InspectionItem::whereNotIn('id', $usedInspectionItemIds)
                            ->with('component')
                            ->get()
                            ->groupBy('component.name');
                        
                        $sections = [];
                        foreach ($items as $componentName => $componentItems) {
                            $sections[] = Forms\Components\Section::make($componentName ?: 'No Component')
                                ->schema([
                                    Forms\Components\CheckboxList::make('inspection_item_ids')
                                        ->options($componentItems->pluck('name', 'id'))
                                        ->searchable()
                                        ->bulkToggleable()
                                        ->gridDirection('column')
                                        ->columns(1)
                                        ->label(false)
                                ])
                                ->collapsible()
                                ->collapsed(true)
                                ->compact();
                        }
                        
                        return [
                            Forms\Components\Group::make()
                                ->schema($sections)
                                ->columnSpanFull(),
                            
                            Forms\Components\Hidden::make('sort_order')
                                ->default(0),
                            
                            //  Main
                                ...$this->mainConfigSchema(),

                            // 🔥 TRIGGER SETTINGS – SELALU MUNCUL
                                ...$this->triggerSettingsSchema(),
                            
                            Forms\Components\Group::make()
                                ->schema(function (callable $get) {
                                    $inputType = $get('input_type');
                                    return $inputType ? $this->getSettingsSchema($inputType) : [];
                                })
                                ->columnSpanFull()
                                ->statePath('settings'),

                            // === Pengaturan Khusus ===
                                ...$this->getSettingsKhusus(),
                        ];
                    })
                    ->action(function (array $data, $livewire): void {
                        $ownerRecord = $livewire->getOwnerRecord();
                        $inspectionItemIds = $data['inspection_item_ids'] ?? [];
                        
                        if (empty($inspectionItemIds)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Tidak ada item yang dipilih')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        $maxSortOrder = SectionItem::where('section_id', $ownerRecord->id)
                            ->max('sort_order') ?? 0;
                        
                        $createdCount = 0;
                        
                        foreach ($inspectionItemIds as $index => $inspectionItemId) {
                            // Cek duplikasi - tidak perlu jika checkboxlist hanya menampilkan yang belum digunakan
                            // Tapi lebih aman tetap dicek
                            $exists = SectionItem::where('section_id', $ownerRecord->id)
                                ->where('inspection_item_id', $inspectionItemId)
                                ->exists();
                                
                            if (!$exists) {
                                SectionItem::create([
                                    'section_id' => $ownerRecord->id,
                                    'inspection_item_id' => $inspectionItemId,
                                    'input_type' => $data['input_type'],
                                    'is_active' => $data['is_active'],
                                    'is_visible' => $data['is_visible'],
                                    'is_required' => $data['is_required'],
                                    'sort_order' => $maxSortOrder + $index + 1,
                                    'settings' => $data['settings'] ?? [],
                                ]);
                                $createdCount++;
                            }
                        }
                        
                        // NOTIFIKASI SUKSES
                        \Filament\Notifications\Notification::make()
                            ->title('Berhasil menambahkan multiple points')
                            ->body($createdCount . ' points berhasil ditambahkan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Tambah Multiple Points')
                    ->modalSubmitActionLabel('Simpan Semua')
                    ->closeModalByClickingAway(false)
                    ->modalWidth('7xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit'),
                
                Tables\Actions\Action::make('move_point')
                    ->label('Pindah Point')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->form([
                        Forms\Components\Select::make('target_menu_section_id')
                            ->label('Pindah ke Menu')
                            ->options(function (Model $record) {
                                $currentMenuSection = $record->menuSection;

                                if (!$currentMenuSection) {
                                    return [];
                                }

                                return MenuSection::query()
                                    ->where('template_id', $currentMenuSection->template_id)
                                    ->where('id', '!=', $currentMenuSection->id)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih menu tujuan dalam template yang sama'),
                    ])
                    ->action(function (Model $record, array $data): void {
                        $targetMenuSectionId = $data['target_menu_section_id'];

                        // Jika target sama dengan menu sekarang
                        if ($record->section_id == $targetMenuSectionId) {
                            \Filament\Notifications\Notification::make()
                                ->title('Tidak dapat memindahkan point')
                                ->body('Tidak bisa memindahkan point ke menu yang sama')
                                ->danger()
                                ->send();
                            return;
                        }

                        $targetMenu = MenuSection::find($targetMenuSectionId);

                        // Update section_id saja
                        $record->update([
                            'section_id' => $targetMenuSectionId,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Point berhasil dipindahkan')
                            ->body("Point '{$record->inspectionItem->name}' telah dipindahkan ke '{$targetMenu->name}'")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Pindah Point')
                    ->modalSubheading(fn (Model $record) => 'Pilih menu tujuan untuk point: ' . $record->inspectionItem->name)
                    ->modalButton('Pindah Point'),
                
                Tables\Actions\Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->label('Preview')
                    ->url(fn ($record) => '#')
                    ->extraAttributes(['target' => '_blank']),
                    
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check-circle')
                        ->label('Activate Selected')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->label('Deactivate Selected')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\BulkAction::make('move_multiple')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->label('Pindah Multiple Points')
                        ->form([
                            Forms\Components\Select::make('target_menu_section_id')
                                ->label('Pindah ke Menu')
                                ->options(function (Collection $records) {
                                    $firstRecord = $records->first();
                                    if (!$firstRecord || !$firstRecord->menuSection) {
                                        return [];
                                    }

                                    return MenuSection::query()
                                        ->where('template_id', $firstRecord->menuSection->template_id)
                                        ->where('id', '!=', $firstRecord->section_id)
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Pilih menu tujuan dalam template yang sama'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $targetMenuSectionId = $data['target_menu_section_id'];
                            
                            $records->each(function ($record) use ($targetMenuSectionId) {
                                $record->update(['section_id' => $targetMenuSectionId]);
                            });
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Points berhasil dipindahkan')
                                ->body($records->count() . ' points berhasil dipindahkan')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Pindah Multiple Points')
                        ->modalButton('Pindah Points'),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->paginated([10, 25, 50, 100]);
    }
}