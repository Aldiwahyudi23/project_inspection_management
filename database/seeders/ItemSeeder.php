<?php

namespace Database\Seeders;

use App\Models\MasterData\Component;
use App\Models\MasterData\InspectionItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
       public function run()
    {
        DB::transaction(function () {
            // Create default components
            $components = [
                ['name' => 'Dokumen', 'description' => 'Bagian dokumen kendaraan'],
                ['name' => 'Foto Kendaraan', 'description' => 'Dokumentasi foto kendaraan'],
                ['name' => 'Eksterior', 'description' => 'Bagian luar kendaraan'],
                ['name' => 'Interior', 'description' => 'Bagian dalam kendaraan'],
                ['name' => 'Mesin', 'description' => 'Komponen mesin kendaraan'],
                ['name' => 'Transmisi', 'description' => 'Sistem transmisi kendaraan'],
                ['name' => 'Kelistrikan', 'description' => 'Sistem kelistrikan kendaraan'],
                ['name' => 'AC', 'description' => 'Sistem pendingin udara'],
                ['name' => 'Fitur', 'description' => 'Fitur tambahan kendaraan'],
                ['name' => 'Rangka (Validasi Tabrak)', 'description' => 'Struktur rangka kendaraan'],
                ['name' => 'Interior (Validasi Banjir)', 'description' => 'Cek interior terkait banjir'],
                ['name' => 'Kaki Kaki', 'description' => 'Suspensi, roda, rem'],
                ['name' => 'Chassis', 'description' => 'Struktur dasar kendaraan'],
            ];

            $componentMap = [];
            foreach ($components as $component) {
                $comp = Component::firstOrCreate(
                    ['name' => $component['name']],
                    array_merge($component, ['sort_order' => count($componentMap) + 1])
                );
                $componentMap[$component['name']] = $comp->id;
            }

            // =========================
            // Create Inspection Items
            // =========================
            $inspectionItems = [
                // Dokumen - Lengkap dengan validasi BPKB
                'Dokumen' => [
                    ['name' => 'STNK', 'description' => 'Surat Tanda Nomor Kendaraan', 'check_notes' => 'Cek masa berlaku, kecocokan data, kondisi fisik', 'sort_order' => 1],
                    ['name' => 'BPKB Asli', 'description' => 'Keaslian buku pemilik kendaraan bermotor', 'check_notes' => 'Verifikasi keaslian dokumen', 'sort_order' => 2],
                    ['name' => 'Hologram BPKB', 'description' => 'Keaslian hologram pada BPKB', 'check_notes' => 'Cek hologram resmi dan sulit dipalsukan', 'sort_order' => 3],
                    ['name' => 'Benang Merah BPKB', 'description' => 'Benang pengaman merah pada halaman BPKB', 'check_notes' => 'Benang merah harus menyatu dengan kertas', 'sort_order' => 4],
                    ['name' => 'Cover BPKB Bertekstur', 'description' => 'Tekstur cover BPKB asli', 'check_notes' => 'Tekstur khusus yang sulit ditiru', 'sort_order' => 5],
                    ['name' => 'BPKB Halaman 1', 'description' => 'Data identitas kendaraan', 'check_notes' => 'Cocokan data dengan fisik kendaraan', 'sort_order' => 6],
                    ['name' => 'BPKB Halaman 2', 'description' => 'Data pemilik kendaraan', 'check_notes' => 'Verifikasi identitas pemilik', 'sort_order' => 7],
                    ['name' => 'BPKB Halaman 3', 'description' => 'Riwayat kepemilikan', 'check_notes' => 'Cek mutasi dan balik nama', 'sort_order' => 8],
                    ['name' => 'BPKB Halaman 4', 'description' => 'Catatan khusus dan blanko', 'check_notes' => 'Cek stempel dan tanda tangan', 'sort_order' => 9],
                    ['name' => 'No Rangka', 'description' => 'Nomor rangka kendaraan', 'check_notes' => 'Cocokan dengan BPKB dan fisik kendaraan', 'sort_order' => 10],
                    ['name' => 'No Mesin', 'description' => 'Nomor mesin kendaraan', 'check_notes' => 'Cocokan dengan BPKB dan fisik mesin', 'sort_order' => 11],
                    ['name' => 'Pajak Tahunan', 'description' => 'Bukti pembayaran pajak tahunan', 'check_notes' => 'Cek masa berlaku dan keterlambatan', 'sort_order' => 12],
                    ['name' => 'Pajak 5 Tahunan', 'description' => 'Bukti pembayaran pajak 5 tahunan', 'check_notes' => 'Cek masa berlaku untuk kendaraan >5 tahun', 'sort_order' => 13],
                    ['name' => 'Faktur', 'description' => 'Faktur pembelian kendaraan', 'check_notes' => 'Keaslian dan kesesuaian data', 'sort_order' => 14],
                    ['name' => 'NIK Pemilik', 'description' => 'Nomor Induk Kependudukan pemilik', 'check_notes' => 'Cocokan dengan KTP dan dokumen lain', 'sort_order' => 15],
                    ['name' => 'Form A', 'description' => 'Formulir A (jika ada)', 'check_notes' => 'Untuk kendaraan import/balik nama', 'sort_order' => 16],
                    ['name' => 'SPh Perusahaan', 'description' => 'Surat Pengesahan perusahaan', 'check_notes' => 'Untuk kendaraan atas nama PT/CV', 'sort_order' => 17],
                    ['name' => 'Buku Service', 'description' => 'Buku service dan riwayat perawatan', 'check_notes' => 'Cek kelengkapan dan keaslian stempel bengkel', 'sort_order' => 18],
                    ['name' => 'Jarak Tempuh (KM)', 'description' => 'Odometer kendaraan', 'check_notes' => 'Cocokan dengan kondisi fisik dan riwayat service', 'sort_order' => 19],
                    ['name' => 'Warna', 'description' => 'Warna kendaraan', 'check_notes' => 'Cocokan dengan dokumen dan fisik', 'sort_order' => 20],
                    ['name' => 'PKB', 'description' => 'Pajak Kendaraan Bermotor', 'check_notes' => 'Nominal dan masa berlaku', 'sort_order' => 21],
                    ['name' => 'Nama Pemilik', 'description' => 'Nama pemilik terdaftar', 'check_notes' => 'Cocokan dengan KTP dan dokumen lain', 'sort_order' => 22],
                    ['name' => 'Kepemilikan', 'description' => 'Status kepemilikan kendaraan', 'check_notes' => 'Perorangan/perusahaan/leasing', 'sort_order' => 23],
                    ['name' => 'KIR (Komersil)', 'description' => 'Kartu Uji Berkala untuk kendaraan komersil', 'check_notes' => 'Cek masa berlaku untuk kendaraan niaga', 'sort_order' => 24],
                    ['name' => 'BS/BM', 'description' => 'Bea Masuk untuk kendaraan import', 'check_notes' => 'Dokumen kepabeanan', 'sort_order' => 25],
                ],

                // Foto Kendaraan
                'Foto Kendaraan' => [
                    ['name' => 'Depan Kanan', 'description' => 'Tampak depan kanan kendaraan', 'check_notes' => '45 derajat dari depan kanan', 'sort_order' => 1],
                    ['name' => 'Depan', 'description' => 'Tampak depan kendaraan', 'check_notes' => 'Lurus dari depan', 'sort_order' => 2],
                    ['name' => 'Depan Kiri', 'description' => 'Tampak depan kiri kendaraan', 'check_notes' => '45 derajat dari depan kiri', 'sort_order' => 3],
                    ['name' => 'Belakang Kanan', 'description' => 'Tampak belakang kanan kendaraan', 'check_notes' => '45 derajat dari belakang kanan', 'sort_order' => 4],
                    ['name' => 'Belakang', 'description' => 'Tampak belakang kendaraan', 'check_notes' => 'Lurus dari belakang', 'sort_order' => 5],
                    ['name' => 'Belakang Kiri', 'description' => 'Tampak belakang kiri kendaraan', 'check_notes' => '45 derajat dari belakang kiri', 'sort_order' => 6],
                    ['name' => 'Bagasi Terbuka', 'description' => 'Tampak bagasi terbuka', 'check_notes' => 'Kondisi dalam bagasi dan peralatan', 'sort_order' => 7],
                    ['name' => 'Dashboard', 'description' => 'Interior dashboard', 'check_notes' => 'Kondisi panel instrumen dan kelengkapan', 'sort_order' => 8],
                    ['name' => 'Interior', 'description' => 'Keseluruhan interior kendaraan', 'check_notes' => 'Kondisi jok, karpet, dan plafon', 'sort_order' => 9],
                    ['name' => 'Kap Mesin Terbuka', 'description' => 'Ruang mesin dengan kap terbuka', 'check_notes' => 'Kondisi mesin dan komponennya', 'sort_order' => 10],
                    ['name' => 'No Fisik Mesin', 'description' => 'Nomor fisik mesin kendaraan', 'check_notes' => 'Close-up nomor mesin', 'sort_order' => 11],
                    ['name' => 'No Fisik Rangka', 'description' => 'Nomor fisik rangka kendaraan', 'check_notes' => 'Close-up nomor rangka', 'sort_order' => 12],
                ],

                // Eksterior
                'Eksterior' => [
                    ['name' => 'Kaca Depan', 'description' => 'Kondisi kaca depan/windshield', 'check_notes' => 'Cek retak, baret, dan fungsi wiper', 'sort_order' => 1],
                    ['name' => 'Grill', 'description' => 'Kondisi grill depan', 'check_notes' => 'Cek kerusakan, kehilangan part, warna', 'sort_order' => 2],
                    ['name' => 'Bemper Depan', 'description' => 'Kondisi bemper depan', 'check_notes' => 'Cek benturan, retak, dan cat', 'sort_order' => 3],
                    ['name' => 'Lampu Depan', 'description' => 'Lampu depan kiri & kanan', 'check_notes' => 'Fungsi, kondensasi, dan lensa', 'sort_order' => 4],
                    ['name' => 'Kap Mesin', 'description' => 'Kondisi kap mesin', 'check_notes' => 'Pembukaan, engsel, dan cat', 'sort_order' => 5],
                    ['name' => 'Fender Kiri', 'description' => 'Kondisi fender kiri', 'check_notes' => 'Dempul, cat, dan keselarasan', 'sort_order' => 6],
                    ['name' => 'Spion Kiri', 'description' => 'Kondisi spion kiri', 'check_notes' => 'Fungsi, kaca, dan housing', 'sort_order' => 7],
                    ['name' => 'Pintu Depan Kiri', 'description' => 'Kondisi pintu depan kiri', 'check_notes' => 'Pembukaan, engsel, dan cat', 'sort_order' => 8],
                    ['name' => 'Pintu Belakang Kiri', 'description' => 'Kondisi pintu belakang kiri', 'check_notes' => 'Pembukaan, engsel, dan cat', 'sort_order' => 9],
                    ['name' => 'Quarter Kiri', 'description' => 'Kondisi quarter panel kiri', 'check_notes' => 'Dempul, cat, dan keselarasan', 'sort_order' => 10],
                    ['name' => 'Lisplang Kiri', 'description' => 'Kondisi lisplang/skirt kiri', 'check_notes' => 'Kerusakan dan pemasangan', 'sort_order' => 11],
                    ['name' => 'Bemper Belakang', 'description' => 'Kondisi bemper belakang', 'check_notes' => 'Cek benturan, retak, dan cat', 'sort_order' => 12],
                    ['name' => 'Lampu Belakang', 'description' => 'Lampu belakang kiri & kanan', 'check_notes' => 'Fungsi, kondensasi, dan lensa', 'sort_order' => 13],
                    ['name' => 'Bagasi', 'description' => 'Kondisi bagasi', 'check_notes' => 'Pembukaan, engsel, dan lantai', 'sort_order' => 14],
                    ['name' => 'Spoiler', 'description' => 'Kondisi spoiler', 'check_notes' => 'Pemasangan, retak, dan cat', 'sort_order' => 15],
                    ['name' => 'Lisplang Kanan', 'description' => 'Kondisi lisplang/skirt kanan', 'check_notes' => 'Kerusakan dan pemasangan', 'sort_order' => 16],
                    ['name' => 'Quarter Kanan', 'description' => 'Kondisi quarter panel kanan', 'check_notes' => 'Dempul, cat, dan keselarasan', 'sort_order' => 17],
                    ['name' => 'Pintu Belakang Kanan', 'description' => 'Kondisi pintu belakang kanan', 'check_notes' => 'Pembukaan, engsel, dan cat', 'sort_order' => 18],
                    ['name' => 'Pintu Depan Kanan', 'description' => 'Kondisi pintu depan kanan', 'check_notes' => 'Pembukaan, engsel, dan cat', 'sort_order' => 19],
                    ['name' => 'Spion Kanan', 'description' => 'Kondisi spion kanan', 'check_notes' => 'Fungsi, kaca, dan housing', 'sort_order' => 20],
                    ['name' => 'Fender Kanan', 'description' => 'Kondisi fender kanan', 'check_notes' => 'Dempul, cat, dan keselarasan', 'sort_order' => 21],
                    ['name' => 'Atap', 'description' => 'Kondisi atap mobil', 'check_notes' => 'Denting, cat, dan moldings', 'sort_order' => 22],
                    ['name' => 'Lantai Bagasi', 'description' => 'Kondisi lantai bagasi', 'check_notes' => 'Karpet, spare tire, dan peralatan', 'sort_order' => 23],
                    ['name' => 'Kaca Samping', 'description' => 'Kaca pintu samping', 'check_notes' => 'Fungsi, retak, dan tint', 'sort_order' => 24],
                    ['name' => 'Kaca Belakang', 'description' => 'Kondisi kaca belakang', 'check_notes' => 'Retak, heater lines, dan wiper', 'sort_order' => 25],
                ],

                // Interior
                'Interior' => [
                    ['name' => 'Kondisi Dashboard', 'description' => 'Kondisi dashboard', 'check_notes' => 'Retak, warna, dan panel instrumen', 'sort_order' => 1],
                    ['name' => 'Setir', 'description' => 'Kondisi setir & tombol', 'check_notes' => 'Kulit, jahitan, dan fungsi tombol', 'sort_order' => 2],
                    ['name' => 'Kursi Depan', 'description' => 'Kondisi kursi depan', 'check_notes' => 'Kulit/kain, jahitan, dan adjuster', 'sort_order' => 3],
                    ['name' => 'Kursi Belakang', 'description' => 'Kondisi kursi belakang', 'check_notes' => 'Kulit/kain, jahitan, dan lipatan', 'sort_order' => 4],
                    ['name' => 'Headrest', 'description' => 'Kondisi headrest', 'check_notes' => 'Ketinggian, kondisi, dan fungsi', 'sort_order' => 5],
                    ['name' => 'Plafon', 'description' => 'Kondisi plafon interior', 'check_notes' => 'Noda, kendur, dan lampu dome', 'sort_order' => 6],
                    ['name' => 'Door Trim', 'description' => 'Kondisi door trim', 'check_notes' => 'Material, tombol, dan speaker', 'sort_order' => 7],
                    ['name' => 'Karpet', 'description' => 'Kondisi karpet interior', 'check_notes' => 'Kotoran, noda, dan keausan', 'sort_order' => 8],
                    ['name' => 'Sabuk Pengaman', 'description' => 'Kondisi seatbelt', 'check_notes' => 'Fungsi, retraksi, dan kotor', 'sort_order' => 9],
                    ['name' => 'Konsol Tengah', 'description' => 'Kondisi console box', 'check_notes' => 'Fungsi, engsel, dan penyimpanan', 'sort_order' => 10],
                    ['name' => 'Pedal', 'description' => 'Kondisi pedal gas, rem, kopling', 'check_notes' => 'Karet, keausan, dan fungsi', 'sort_order' => 11],
                    ['name' => 'Kaca Spion Interior', 'description' => 'Kondisi kaca spion dalam', 'check_notes' => 'Glass, adjustment, dan auto-dimming', 'sort_order' => 12],
                    ['name' => 'Sun Visor', 'description' => 'Kondisi sun visor', 'check_notes' => 'Fungsi, cermin, dan lampu', 'sort_order' => 13],
                ],

                // Mesin - Diperbaiki dengan pengelompokan sistem
                'Mesin' => [
                    // ============================================
                    // 🔹 SISTEM STARTING & IDLE
                    // ============================================
                    ['name' => 'Starting Mesin', 'description' => 'Proses start mesin', 'check_notes' => 'Mudah start, tidak ngadat, langsung hidup', 'sort_order' => 1],
                    ['name' => 'Putaran Idle', 'description' => 'Kecepatan idle mesin', 'check_notes' => 'Stabil (600-900 RPM), tidak naik turun', 'sort_order' => 2],
                    
                    // ============================================
                    // 🔹 SISTEM SUARA & GETARAN
                    // ============================================
                    ['name' => 'Suara Mesin', 'description' => 'Bunyi abnormal mesin', 'check_notes' => 'Cek knocking, tapping, whining, grinding', 'sort_order' => 3],
                    ['name' => 'Getaran Mesin', 'description' => 'Getaran berlebihan', 'check_notes' => 'Di idle, saat akselerasi, dan berjalan', 'sort_order' => 4],
                    
                    // ============================================
                    // 🔹 SISTEM PELUMASAN (OIL SYSTEM)
                    // ============================================
                    ['name' => 'Oli Mesin', 'description' => 'Level dan kondisi oli', 'check_notes' => 'Warna, kekentalan, bau terbakar, kontaminasi', 'sort_order' => 5],
                    // ['name' => 'Oil Leak', 'description' => 'Kebocoran oli mesin', 'check_notes' => 'Cek gasket, seal, oil pan, timing cover', 'sort_order' => 6],
                    // ['name' => 'Oil Filter', 'description' => 'Kondisi filter oli', 'check_notes' => 'Kebocoran, usia pakai, kualitas', 'sort_order' => 7],
                    
                    // ============================================
                    // 🔹 SISTEM PENDINGIN (COOLING SYSTEM)
                    // ============================================
                    ['name' => 'Radiator', 'description' => 'Kondisi radiator', 'check_notes' => 'Kebersihan fins, kebocoran, tekanan', 'sort_order' => 8],
                    ['name' => 'Coolant Level', 'description' => 'Level cairan pendingin', 'check_notes' => 'Di reservoir dan radiator', 'sort_order' => 9],
                    ['name' => 'Coolant Condition', 'description' => 'Kondisi coolant', 'check_notes' => 'Warna, kontaminasi, usia', 'sort_order' => 10],
                    // ['name' => 'Coolant Leak', 'description' => 'Kebocoran coolant', 'check_notes' => 'Cek selang, radiator, water pump, heater core', 'sort_order' => 11],
                    ['name' => 'Water Pump', 'description' => 'Pompa air', 'check_notes' => 'Kebocoran, suara bearing, performa', 'sort_order' => 12],
                    ['name' => 'Thermostat', 'description' => 'Pengatur suhu coolant', 'check_notes' => 'Fungsi buka-tutup pada suhu tertentu', 'sort_order' => 13],
                    ['name' => 'Kipas Radiator', 'description' => 'Fan radiator', 'check_notes' => 'Fungsi motor, sensor suhu, clutch (jika ada)', 'sort_order' => 14],
                    ['name' => 'Selang Radiator', 'description' => 'Hose radiator', 'check_notes' => 'Atas, bawah, heater - retak, kembang, bocor', 'sort_order' => 15],
                    ['name' => 'Coolant Reservoir', 'description' => 'Tandon coolant', 'check_notes' => 'Kondisi, retak, skala level', 'sort_order' => 16],
                    ['name' => 'Tutup Radiator', 'description' => 'Radiator cap', 'check_notes' => 'Pressure rating, seal, fungsi valve', 'sort_order' => 17],
                    
                    // ============================================
                    // 🔹 SISTEM BAHAN BAKAR (FUEL SYSTEM)
                    // ============================================
                    ['name' => 'Filter Bahan Bakar', 'description' => 'Fuel filter', 'check_notes' => 'Kebocoran, usia pakai, tekanan', 'sort_order' => 18],
                    ['name' => 'Fuel Pump', 'description' => 'Pompa bahan bakar', 'check_notes' => 'Suara, tekanan, performa', 'sort_order' => 19],
                    ['name' => 'Fuel Injector', 'description' => 'Injektor (EFI)', 'check_notes' => 'Pattern spray, kebocoran, clogging', 'sort_order' => 20],
                    ['name' => 'Karburator', 'description' => 'Carburetor (karburator)', 'check_notes' => 'Tuning, kebersihan, accelerator pump', 'sort_order' => 21],
                    ['name' => 'Fuel Pressure Regulator', 'description' => 'Regulator tekanan', 'check_notes' => 'Untuk sistem EFI', 'sort_order' => 22],
                    // ['name' => 'Fuel Leak', 'description' => 'Kebocoran bahan bakar', 'check_notes' => 'Bahaya kebakaran, cek semua sambungan', 'sort_order' => 23],
                    ['name' => 'Tangki Bensin', 'description' => 'Fuel tank', 'check_notes' => 'Kebocoran, karat, mounting', 'sort_order' => 24],
                    
                    // ============================================
                    // 🔹 SISTEM INTAKE & EXHAUST
                    // ============================================
                    ['name' => 'Air Filter', 'description' => 'Filter udara', 'check_notes' => 'Kotor, penyok, basah, usia', 'sort_order' => 25],
                    ['name' => 'Intake Manifold', 'description' => 'Manifold masuk', 'check_notes' => 'Kebocoran vacuum, gasket', 'sort_order' => 26],
                    ['name' => 'Exhaust Manifold', 'description' => 'Manifold buang', 'check_notes' => 'Retak, gasket, kebocoran gas', 'sort_order' => 27],
                    ['name' => 'Knalpot', 'description' => 'Sistem pembuangan', 'check_notes' => 'Kebocoran, karat, mounting, catalytic converter', 'sort_order' => 28],
                    ['name' => 'Asap Knalpot', 'description' => 'Warna asap buang', 'check_notes' => 'Putih (coolant), biru (oli), hitam (bahan bakar)', 'sort_order' => 29],
                    ['name' => 'Muffler', 'description' => 'Peredam suara', 'check_notes' => 'Karat, lubang, suara berisik', 'sort_order' => 30],
                    ['name' => 'Catalytic Converter', 'description' => 'Konverter katalitik', 'check_notes' => 'Fungsi, suara internal, restricted flow', 'sort_order' => 31],
                    
                    // ============================================
                    // 🔹 SISTEM MEKANIS INTERNAL
                    // ============================================
                    ['name' => 'Timing Belt/Chain', 'description' => 'Timing system', 'check_notes' => 'Kondisi visual, ketegangan, usia pakai', 'sort_order' => 32],
                    ['name' => 'Tensioner & Idler', 'description' => 'Tensioner timing', 'check_notes' => 'Bearing, spring tension', 'sort_order' => 33],
                    ['name' => 'Serpentine Belt', 'description' => 'Accessory belt', 'check_notes' => 'Retak, keausan, ketegangan, pulley alignment', 'sort_order' => 34],
                    ['name' => 'Mounting Mesin', 'description' => 'Engine mount', 'check_notes' => 'Retak, getaran berlebihan, hydraulic leak', 'sort_order' => 35],
                    ['name' => 'Crankshaft Pulley', 'description' => 'Pulley poros engkol', 'check_notes' => 'Keyway, balancer (harmonic balancer)', 'sort_order' => 36],
                    ['name' => 'Valve Cover Gasket', 'description' => 'Gasket tutup klep', 'check_notes' => 'Kebocoran oli, kondisi gasket', 'sort_order' => 37],
                    ['name' => 'Oil Pan Gasket', 'description' => 'Gasket karter oli', 'check_notes' => 'Kebocoran oli, kondisi gasket', 'sort_order' => 38],
                    
                    // ============================================
                    // 🔹 MESIN BENSIN (PETROL ENGINE) - SPECIFIC
                    // ============================================
                    ['name' => 'Busi', 'description' => 'Spark plug', 'check_notes' => 'Gap, warna elektroda, fouling', 'sort_order' => 39],
                    ['name' => 'Ignition Coil', 'description' => 'Koil pengapian', 'check_notes' => 'Output, crack, usia', 'sort_order' => 40],
                    ['name' => 'Distributor', 'description' => 'Distributor', 'check_notes' => 'Cap, rotor, timing advance', 'sort_order' => 41],
                    ['name' => 'Knocking Sensor', 'description' => 'Sensor knocking', 'check_notes' => 'Fungsi (untuk mesin modern)', 'sort_order' => 42],
                    
                    // ============================================
                    // 🔹 MESIN DIESEL (DIESEL ENGINE) - SPECIFIC
                    // ============================================
                    ['name' => 'Turbocharger', 'description' => 'Turbo diesel', 'check_notes' => 'Boost, shaft play, oli bocor, wastegate', 'sort_order' => 43],
                    ['name' => 'Intercooler', 'description' => 'Pendingin udara masuk', 'check_notes' => 'Kebocoran, fins bersih, mounting', 'sort_order' => 44],
                    ['name' => 'Glow Plug', 'description' => 'Pemanas awal diesel', 'check_notes' => 'Fungsi, resistance, starting dingin', 'sort_order' => 45],
                    ['name' => 'Injector Pump Diesel', 'description' => 'Pompa injeksi', 'check_notes' => 'Kebocoran, timing, delivery', 'sort_order' => 46],
                    ['name' => 'Blow-by Engine (Ngobos)', 'description' => 'Kebocoran tekanan crankcase', 'check_notes' => 'Asap dari oil filler/dipstick', 'sort_order' => 47],
                    ['name' => 'Fuel Sedimentor/Water Separator', 'description' => 'Pemisah air solar', 'check_notes' => 'Kondisi, drain valve', 'sort_order' => 48],
                    
                    // ============================================
                    // 🔹 MESIN HYBRID & ELECTRIC - SPECIFIC
                    // ============================================
                    ['name' => 'Motor Generator (Hybrid)', 'description' => 'MG1/MG2 hybrid', 'check_notes' => 'Fungsi, suara, pendinginan', 'sort_order' => 49],
                    ['name' => 'Inverter (Hybrid/EV)', 'description' => 'Power inverter', 'check_notes' => 'Fungsi, pendinginan, error code', 'sort_order' => 50],
                    ['name' => 'High Voltage Battery Cooling', 'description' => 'Pendingin baterai', 'check_notes' => 'Sirkulasi, suhu, pump/fan', 'sort_order' => 51],
                    
                    // ============================================
                    // 🔹 PERFORMANCE & RESPON
                    // ============================================
                    ['name' => 'Respon Gas', 'description' => 'Throttle response', 'check_notes' => 'Instant saat pedal ditekan, tidak delay', 'sort_order' => 52],
                    ['name' => 'Akselerasi', 'description' => 'Percepatan mesin', 'check_notes' => 'Halus, tidak tersendat, tenaga penuh', 'sort_order' => 53],
                    ['name' => 'Engine Brake', 'description' => 'Pengereman mesin', 'check_notes' => 'Effektif saat turun gigi', 'sort_order' => 54],
                    ['name' => 'Overheating Symptoms', 'description' => 'Gejala overheating', 'check_notes' => 'Suhu naik, coolant boil over', 'sort_order' => 55],
                ],

                // Transmisi - Diperbaiki dengan pengelompokan sistem dan tipe
                'Transmisi' => [
                    // ============================================
                    // 🔹 SISTEM UMUM TRANSMISI
                    // ============================================
                    ['name' => 'Performa Transmisi', 'description' => 'Performa keseluruhan', 'check_notes' => 'Kelancaran operasi, suara abnormal', 'sort_order' => 1],
                    ['name' => 'Transmisi Mount', 'description' => 'Engine/transmission mount', 'check_notes' => 'Retak, getaran berlebihan, hydraulic leak', 'sort_order' => 2],
                    ['name' => 'Kebocoran Oli Transmisi', 'description' => 'Transmission fluid leak', 'check_notes' => 'Cek seal, gasket, pan, output shaft', 'sort_order' => 3],
                    // ['name' => 'Linkage & Cable', 'description' => 'Sambungan transmisi', 'check_notes' => 'Adjustment, bushing, binding', 'sort_order' => 4],
                    
                    // ============================================
                    // 🔹 TRANSMISI MANUAL (MANUAL TRANSMISSION)
                    // ============================================
                    ['name' => 'Kopling', 'description' => 'Clutch system', 'check_notes' => 'Slip, chatter, engagement point, berat pedal', 'sort_order' => 5],
                    ['name' => 'Master Silinder Kopling', 'description' => 'Clutch master cylinder', 'check_notes' => 'Kebocoran, fungsi, level fluid', 'sort_order' => 6],
                    // ['name' => 'Slave Silinder Kopling', 'description' => 'Clutch slave cylinder', 'check_notes' => 'Kebocoran, fungsi, throw-out bearing', 'sort_order' => 7],
                    ['name' => 'Clutch Cable', 'description' => 'Kabel kopling', 'check_notes' => 'Adjustment, binding, fraying', 'sort_order' => 8],
                    // ['name' => 'Flywheel', 'description' => 'Flywheel/pressure plate', 'check_notes' => 'Warpage, hot spots, wear', 'sort_order' => 9],
                    ['name' => 'Tuas Persneling', 'description' => 'Gear shifter', 'check_notes' => 'Play, presisi, bushing, knuckle joint', 'sort_order' => 10],
                    ['name' => 'Perpindahan Gigi Manual', 'description' => 'Manual shifting', 'check_notes' => 'Halus, tidak bunyi grinding, mudah masuk gigi', 'sort_order' => 11],
                    // ['name' => 'Synchro Mesh', 'description' => 'Sinkronisasi gigi', 'check_notes' => 'Double clutching test, grinding saat downshift', 'sort_order' => 12],
                    ['name' => 'Clutch Release Bearing', 'description' => 'Release bearing', 'check_notes' => 'Suara saat pedal kopling ditekan', 'sort_order' => 13],
                    
                    // ============================================
                    // 🔹 TRANSMISI OTOMATIS (AUTOMATIC TRANSMISSION)
                    // ============================================
                    ['name' => 'Oli Transmisi Otomatis (ATF)', 'description' => 'Automatic transmission fluid', 'check_notes' => 'Level, warna (merah jernih), bau terbakar', 'sort_order' => 14],
                    // ['name' => 'ATF Filter', 'description' => 'Transmission filter', 'check_notes' => 'Kondisi, clogging, usia', 'sort_order' => 15],
                    ['name' => 'Perpindahan Gigi Otomatis', 'description' => 'Automatic shifting', 'check_notes' => 'Hentakan, slip, delay, hunting (naik-turun)', 'sort_order' => 16],
                    // ['name' => 'Torque Converter', 'description' => 'Torque converter', 'check_notes' => 'Stall test, shudder, lock-up function', 'sort_order' => 17],
                    ['name' => 'Kickdown Response', 'description' => 'Kickdown function', 'check_notes' => 'Respon saat pedal gas diinjak penuh', 'sort_order' => 18],
                    ['name' => 'Valve Body', 'description' => 'Valve body assembly', 'check_notes' => 'Fungsi solenoid, shifting quality', 'sort_order' => 19],
                    ['name' => 'Transmission Range Sensor', 'description' => 'Neutral safety switch', 'check_notes' => 'Posisi P-R-N-D, start hanya di P/N', 'sort_order' => 20],
                    ['name' => 'Mode Selector', 'description' => 'Mode driving', 'check_notes' => 'Sport, economy, manual mode (jika ada)', 'sort_order' => 21],
                    
                    // ============================================
                    // 🔹 TRANSMISI CVT (CONTINUOUSLY VARIABLE)
                    // ============================================
                    ['name' => 'CVT Fluid', 'description' => 'CVT fluid khusus', 'check_notes' => 'Level, kondisi, warna, bau', 'sort_order' => 22],
                    ['name' => 'CVT Performance', 'description' => 'CVT operation', 'check_notes' => 'Rubber band effect, shudder, slip, whining noise', 'sort_order' => 23],
                    // ['name' => 'CVT Pulley & Belt', 'description' => 'Primary/secondary pulley', 'check_notes' => 'Wear, belt tension, pulley function', 'sort_order' => 24],
                    // ['name' => 'CVT Start Clutch', 'description' => 'Start clutch (jika ada)', 'check_notes' => 'Shudder saat start dari berhenti', 'sort_order' => 25],
                    
                    // ============================================
                    // 🔹 TRANSMISI DCT/DSG (DUAL CLUTCH)
                    // ============================================
                    ['name' => 'DCT Fluid', 'description' => 'Dual clutch fluid', 'check_notes' => 'Level, kondisi, spesifikasi khusus', 'sort_order' => 26],
                    ['name' => 'DCT Performance', 'description' => 'Dual clutch operation', 'check_notes' => 'Hesitation, jerkiness, clutch slip, overheating', 'sort_order' => 27],
                    ['name' => 'Clutch Pack DCT', 'description' => 'Dual clutch pack', 'check_notes' => 'Wear, engagement, adaptive learning', 'sort_order' => 28],
                    ['name' => 'Mechatronic Unit', 'description' => 'Mechatronic module', 'check_notes' => 'Fungsi, error codes, hydraulic pressure', 'sort_order' => 29],
                    
                    // ============================================
                    // 🔹 DRIVETRAIN (GARDA, POROS, DLL)
                    // ============================================
                    ['name' => 'Gardan/Differential', 'description' => 'Rear/front differential', 'check_notes' => 'Suara whining/growling, kebocoran oli, backlash', 'sort_order' => 30],
                    ['name' => 'Oli Gardan', 'description' => 'Differential fluid', 'check_notes' => 'Level, kondisi, metal particles', 'sort_order' => 31],
                    ['name' => 'CV Joint & Boot', 'description' => 'Constant velocity joint', 'check_notes' => 'Boot bocor/robek, bunyi klik saat belok penuh', 'sort_order' => 32],
                    ['name' => 'Drive Shaft', 'description' => 'Propeller shaft', 'check_notes' => 'Balance, U-joint wear, center bearing', 'sort_order' => 33],
                    ['name' => 'Half Shaft', 'description' => 'Axle shaft', 'check_notes' => 'Bending, spline wear, bearing', 'sort_order' => 34],
                    ['name' => 'Transfer Case (4WD/AWD)', 'description' => 'Transfer case', 'check_notes' => 'Fungsi 2WD/4WD, kebocoran, suara', 'sort_order' => 35],
                    
                    // ============================================
                    // 🔹 OLI & FLUID
                    // ============================================
                    ['name' => 'Oli Manual Transmission', 'description' => 'Manual gear oil', 'check_notes' => 'Level, kondisi, viscosity', 'sort_order' => 36],
                    // ['name' => 'Transmission Cooler', 'description' => 'Oil cooler (AT)', 'check_notes' => 'Kebocoran, flow restriction, contamination', 'sort_order' => 37],
                    // ['name' => 'Transmission Pan', 'description' => 'Oil pan', 'check_notes' => 'Kebocoran, gasket, magnet (untuk debris)', 'sort_order' => 38],
                    
                    // ============================================
                    // 🔹 PERFORMANCE & OPERATIONAL TESTS
                    // ============================================
                    ['name' => 'Park Test', 'description' => 'Park position hold', 'check_notes' => 'Tahan di tanjakan, tidak roll', 'sort_order' => 39],
                    ['name' => 'Reverse Test', 'description' => 'Reverse operation', 'check_notes' => 'Halus masuk R, tidak bunyi/slip', 'sort_order' => 40],
                    ['name' => 'Neutral Test', 'description' => 'Neutral operation', 'check_notes' => 'Roda bebas di N, tidak drag', 'sort_order' => 41],
                    // ['name' => 'Hill Start Test', 'description' => 'Hill start ability', 'check_notes' => 'Manual: roll back minimal; Auto: hill hold', 'sort_order' => 42],
                    // ['name' => 'Load Test', 'description' => 'Heavy load performance', 'check_notes' => 'Saat akselerasi/naik tanjakan dengan beban', 'sort_order' => 43],
                    ['name' => 'Temperature Operation', 'description' => 'Suhu operasi', 'check_notes' => 'Overheat, delayed shifting saat panas/dingin', 'sort_order' => 44],
                ],

                // Kelistrikan
                'Kelistrikan' => [
                    ['name' => 'Aki (Battery)', 'description' => 'Kondisi aki', 'check_notes' => 'Tegangan, terminal, usia', 'sort_order' => 1],
                    ['name' => 'Alternator', 'description' => 'Sistem pengisian', 'check_notes' => 'Output voltage 13.8-14.4V', 'sort_order' => 2],
                    ['name' => 'Starter', 'description' => 'Motor starter', 'check_notes' => 'Suara, kecepatan start', 'sort_order' => 3],
                    ['name' => 'Lampu Utama', 'description' => 'Headlamp', 'check_notes' => 'High beam, low beam, fog lamp', 'sort_order' => 4],
                    ['name' => 'Lampu Sein & Hazard', 'description' => 'Turn signals', 'check_notes' => 'Depan, belakang, side mirror', 'sort_order' => 5],
                    ['name' => 'Lampu Rem & Mundur', 'description' => 'Brake & reverse lights', 'check_notes' => 'Intensitas dan fungsi', 'sort_order' => 6],
                    // ['name' => 'Power Window', 'description' => 'Jendela listrik', 'check_notes' => 'Semua pintu, auto up/down', 'sort_order' => 7],
                    ['name' => 'Power Door Lock', 'description' => 'Central locking', 'check_notes' => 'Remote, keyless entry', 'sort_order' => 8],
                    ['name' => 'Power Mirror', 'description' => 'Kaca spion listrik', 'check_notes' => 'Adjustment, fold, heating', 'sort_order' => 9],
                    ['name' => 'Wiper & Washer', 'description' => 'Sistem penyapu kaca', 'check_notes' => 'Speed, streaking, spray', 'sort_order' => 10],
                    ['name' => 'Audio System', 'description' => 'Head unit & speaker', 'check_notes' => 'Suara, fungsi input', 'sort_order' => 11],
                    ['name' => 'Klakson', 'description' => 'Horn', 'check_notes' => 'Volume dan fungsi', 'sort_order' => 12],
                    ['name' => 'Panel Instrumen', 'description' => 'Dashboard cluster', 'check_notes' => 'Indikator, warning light', 'sort_order' => 13],
                    ['name' => 'Fuse Box', 'description' => 'Kotak sekring', 'check_notes' => 'Kekencangan, korosi', 'sort_order' => 14],
                    ['name' => 'Wiring Harness', 'description' => 'Kabel body', 'check_notes' => 'Kerapihan, isolasi', 'sort_order' => 15],
                    ['name' => 'USB Port & Charger', 'description' => 'Port pengisian', 'check_notes' => 'Fungsi charging', 'sort_order' => 16],
                    ['name' => 'ECU Error Codes', 'description' => 'Check engine light', 'check_notes' => 'Scan dengan OBD2 scanner', 'sort_order' => 17],
                ],

                // AC
                'AC' => [
                    ['name' => 'AC - Cooling', 'description' => 'Dinginnya AC', 'check_notes' => 'Suhu udara keluar 5-12°C', 'sort_order' => 1],
                    ['name' => 'Kompresor AC', 'description' => 'AC compressor', 'check_notes' => 'Engagement, suara, clutch', 'sort_order' => 2],
                    ['name' => 'Freon Level', 'description' => 'Refrigerant charge', 'check_notes' => 'Pressure low & high side', 'sort_order' => 3],
                    ['name' => 'Kondensor', 'description' => 'AC condenser', 'check_notes' => 'Kebersihan, kebocoran', 'sort_order' => 4],
                    ['name' => 'Evaporator', 'description' => 'AC evaporator', 'check_notes' => 'Bau, drain clog', 'sort_order' => 5],
                    ['name' => 'Blower Motor', 'description' => 'Fan speed', 'check_notes' => 'Semua kecepatan, suara', 'sort_order' => 6],
                    ['name' => 'AC Control Panel', 'description' => 'Panel kontrol', 'check_notes' => 'Semua tombol fungsi', 'sort_order' => 7],
                    ['name' => 'AC Ventilation', 'description' => 'Arah angin', 'check_notes' => 'Face, foot, defogger', 'sort_order' => 8],
                    // ['name' => 'AC Leak', 'description' => 'Kebocoran freon', 'check_notes' => 'Cek dengan UV dye', 'sort_order' => 9],
                    // ['name' => 'Receiver Dryer', 'description' => 'Filter dryer', 'check_notes' => 'Untuk sistem AC', 'sort_order' => 10],
                ],

                // Fitur - Diperbaiki dan dilengkapi sesuai data yang diberikan
'Fitur' => [
    // ============================================
    // 🔹 FITUR DASAR & KENYAMANAN
    // ============================================
    ['name' => 'AC', 'description' => 'Air Conditioner', 'check_notes' => 'Fungsi cooling, semua kecepatan blower', 'sort_order' => 1],
    ['name' => 'Power Steering', 'description' => 'Power Steering System', 'check_notes' => 'Berat ringan sesuai kecepatan, tidak bunyi', 'sort_order' => 2],
    ['name' => 'Power Window', 'description' => 'Electric Power Windows', 'check_notes' => 'Semua jendela naik-turun, auto up/down', 'sort_order' => 3],
    ['name' => 'Central Lock', 'description' => 'Central Locking System', 'check_notes' => 'Remote, keyless, fungsi semua pintu', 'sort_order' => 4],
    ['name' => 'Keyless Entry', 'description' => 'Keyless Entry System', 'check_notes' => 'Touch sensor, proximity unlock', 'sort_order' => 5],
    ['name' => 'Start Stop Button', 'description' => 'Push Start Button', 'check_notes' => 'Engine start/stop dengan smart key', 'sort_order' => 6],
    ['name' => 'Leather Seat', 'description' => 'Leather Upholstery Seats', 'check_notes' => 'Kondisi kulit, jahitan, warna seragam', 'sort_order' => 7],
    ['name' => 'Sunroof', 'description' => 'Sunroof/Moonroof', 'check_notes' => 'Buka/tutup, tilt, kebocoran', 'sort_order' => 8],
    ['name' => 'Cruise Control', 'description' => 'Cruise Control System', 'check_notes' => 'Set, resume, cancel, adaptive (jika ada)', 'sort_order' => 9],
    ['name' => 'Touchscreen', 'description' => 'Touchscreen Display', 'check_notes' => 'Responsif, tidak delay, semua fungsi', 'sort_order' => 10],
    ['name' => 'Alloy Wheel', 'description' => 'Alloy Wheels', 'check_notes' => 'Kondisi velg, tidak bent/kerusakan', 'sort_order' => 11],
    
    // ============================================
    // 🔹 FITUR KESELAMATAN
    // ============================================
    ['name' => 'ABS', 'description' => 'Anti-lock Braking System', 'check_notes' => 'Fungsi, tidak ada pulsasi abnormal', 'sort_order' => 12],
    ['name' => 'Airbag', 'description' => 'Safety Airbag System', 'check_notes' => 'Indikator dashboard, sistem siap', 'sort_order' => 13],
    ['name' => 'Dual Airbag', 'description' => 'Dual Front Airbags', 'check_notes' => 'Pengemudi dan penumpang depan', 'sort_order' => 14],
    ['name' => 'EBD', 'description' => 'Electronic Brakeforce Distribution', 'check_notes' => 'Bekerja bersama ABS', 'sort_order' => 15],
    ['name' => 'BA', 'description' => 'Brake Assist System', 'check_notes' => 'Emergency brake assist', 'sort_order' => 16],
    ['name' => 'VSC', 'description' => 'Vehicle Stability Control', 'check_notes' => 'Stability dan traction control', 'sort_order' => 17],
    ['name' => 'HSA', 'description' => 'Hill Start Assist', 'check_notes' => 'Hold brake di tanjakan 2-3 detik', 'sort_order' => 18],
    ['name' => 'ISOFIX', 'description' => 'Child Seat Anchor System', 'check_notes' => 'Anchor point untuk car seat anak', 'sort_order' => 19],
    ['name' => 'Blind Spot', 'description' => 'Blind Spot Monitoring', 'check_notes' => 'Indikator di spion, warning sound', 'sort_order' => 20],
    
    // ============================================
    // 🔹 FITUR PENERANGAN & EKSTERIOR
    // ============================================
    ['name' => 'LED Headlight', 'description' => 'LED Headlights', 'check_notes' => 'High/low beam, DRL function', 'sort_order' => 21],
    ['name' => 'DRL', 'description' => 'Daytime Running Lights', 'check_notes' => 'Auto on saat mesin hidup', 'sort_order' => 22],
    ['name' => 'Fog Lamp', 'description' => 'Front Fog Lights', 'check_notes' => 'Fungsi, kondisi lensa', 'sort_order' => 23],
    ['name' => 'Retractable', 'description' => 'Automatic Retractable Mirrors', 'check_notes' => 'Auto fold/unfold dengan lock/unlock', 'sort_order' => 24],
    ['name' => 'Defogger', 'description' => 'Rear Glass Defogger', 'check_notes' => 'Pemanas kaca belakang, garis heating', 'sort_order' => 25],
    
    // ============================================
    // 🔹 FITUR INTERIOR & TEKNOLOGI
    // ============================================
    ['name' => 'Digital AC', 'description' => 'Digital Automatic Climate Control', 'check_notes' => 'Auto mode, dual zone (jika ada)', 'sort_order' => 26],
    ['name' => 'Audio Steering', 'description' => 'Audio Steering Switch', 'check_notes' => 'Tombol volume, track, telpon di setir', 'sort_order' => 27],
    ['name' => 'Tilt Steering', 'description' => 'Tilt & Telescopic Steering Wheel', 'check_notes' => 'Adjustment lock, posisi tidak slip', 'sort_order' => 28],
    ['name' => 'Wireless Charge', 'description' => 'Wireless Smartphone Charging', 'check_notes' => 'Charging pad fungsi, overheating check', 'sort_order' => 29],
    ['name' => 'Apple CarPlay', 'description' => 'Apple CarPlay & Android Auto', 'check_notes' => 'Koneksi USB/wireless, semua fungsi', 'sort_order' => 30],
    ['name' => 'Camera Belakang', 'description' => 'Rear View Camera', 'check_notes' => 'Gambar jernih, guide line dynamic/static', 'sort_order' => 31],
    ['name' => 'Sensor Parkir', 'description' => 'Parking Sensor System', 'check_notes' => 'Depan/belakang, visual display, beep sound', 'sort_order' => 32],
    ['name' => 'Camera 360', 'description' => '360 Degree Surround View Camera', 'check_notes' => 'Semua kamera fungsi, stitching image baik', 'sort_order' => 33],
    
    // ============================================
    // 🔹 FITUR PREMIUM & TAMBAHAN
    // ============================================
    ['name' => 'Heated Seats', 'description' => 'Heated Seats', 'check_notes' => 'Fungsi pemanas semua level', 'sort_order' => 34],
    ['name' => 'Memory Seats', 'description' => 'Memory Seats', 'check_notes' => 'Preset posisi, fungsi dengan remote key', 'sort_order' => 35],
    ['name' => 'Navigation System', 'description' => 'GPS Navigation System', 'check_notes' => 'GPS signal, map update, route guidance', 'sort_order' => 36],
    ['name' => 'Ambient Lighting', 'description' => 'Ambient Interior Lighting', 'check_notes' => 'Warna adjustable, intensity, lokasi', 'sort_order' => 37],
    ['name' => 'Premium Audio', 'description' => 'Premium Audio System', 'check_notes' => 'Brand (Bose, JBL, dll), speaker count', 'sort_order' => 38],
    ['name' => 'Dual Zone AC', 'description' => 'Dual Zone Climate Control', 'check_notes' => 'Suhu terpisah pengemudi dan penumpang', 'sort_order' => 39],
    ['name' => 'Bluetooth Connectivity', 'description' => 'Bluetooth Hands-free', 'check_notes' => 'Pairing, audio streaming, call quality', 'sort_order' => 40],
],

                // Rangka (Validasi Tabrak)
                'Rangka (Validasi Tabrak)' => [
                    ['name' => 'Bulkhead', 'description' => 'Struktur bulkhead', 'check_notes' => 'Keselarasan, perbaikan', 'sort_order' => 1],
                    ['name' => 'Pilar A Kiri', 'description' => 'Pilar A sisi kiri', 'check_notes' => 'Welding, cat asli', 'sort_order' => 2],
                    ['name' => 'Pilar A Kanan', 'description' => 'Pilar A sisi kanan', 'check_notes' => 'Welding, cat asli', 'sort_order' => 3],
                    ['name' => 'Pilar B Kiri', 'description' => 'Pilar B sisi kiri', 'check_notes' => 'Untuk pintu belakang', 'sort_order' => 4],
                    ['name' => 'Pilar B Kanan', 'description' => 'Pilar B sisi kanan', 'check_notes' => 'Untuk pintu belakang', 'sort_order' => 5],
                    ['name' => 'Pilar C Kiri', 'description' => 'Pilar C sisi kiri', 'check_notes' => 'Untuk bagasi/hatch', 'sort_order' => 6],
                    ['name' => 'Pilar C Kanan', 'description' => 'Pilar C sisi kanan', 'check_notes' => 'Untuk bagasi/hatch', 'sort_order' => 7],
                    ['name' => 'Long Member Kiri', 'description' => 'Frame rail kiri', 'check_notes' => 'Keselarasan, kerusakan', 'sort_order' => 8],
                    ['name' => 'Long Member Kanan', 'description' => 'Frame rail kanan', 'check_notes' => 'Keselarasan, kerusakan', 'sort_order' => 9],
                    ['name' => 'Cross Member', 'description' => 'Cross member', 'check_notes' => 'Bentuk asli, welding', 'sort_order' => 10],
                    ['name' => 'Apron Kiri', 'description' => 'Apron sisi kiri', 'check_notes' => 'Dekat shock tower', 'sort_order' => 11],
                    ['name' => 'Apron Kanan', 'description' => 'Apron sisi kanan', 'check_notes' => 'Dekat shock tower', 'sort_order' => 12],
                    ['name' => 'Shock Tower Depan', 'description' => 'Tower suspensi depan', 'check_notes' => 'Bentuk, welding', 'sort_order' => 13],
                    ['name' => 'Firewall', 'description' => 'Dinding pemisah mesin', 'check_notes' => 'Kerusakan, perbaikan', 'sort_order' => 14],
                    ['name' => 'Suport Kanan', 'description' => 'Dinding pemisah mesin', 'check_notes' => 'Kerusakan, perbaikan', 'sort_order' => 15],
                    ['name' => 'Suport Kiri', 'description' => 'Dinding pemisah mesin', 'check_notes' => 'Kerusakan, perbaikan', 'sort_order' => 16],
                ],

                // Interior (Validasi Banjir)
                'Interior (Validasi Banjir)' => [
                    ['name' => 'Kolom Setir', 'description' => 'Steering column', 'check_notes' => 'Karat, kotoran air', 'sort_order' => 1],
                    ['name' => 'Kolong Jok', 'description' => 'Under seat', 'check_notes' => 'Lumpur, karat, jamur', 'sort_order' => 2],
                    ['name' => 'Under Dashboard', 'description' => 'Kolong dashboard', 'check_notes' => 'Kabel, karat, jamur', 'sort_order' => 3],
                    ['name' => 'Karpet Dasar', 'description' => 'Under carpet', 'check_notes' => 'Lembab, jamur, bau', 'sort_order' => 4],
                    ['name' => 'Spare Tire Well', 'description' => 'Lubang ban serep', 'check_notes' => 'Air, karat, kotoran', 'sort_order' => 5],
                    ['name' => 'Fuse Box Interior', 'description' => 'Interior fuse box', 'check_notes' => 'Korosi terminal', 'sort_order' => 6],
                    ['name' => 'Seatbelt Lower Anchor', 'description' => 'Anchor sabuk pengaman', 'check_notes' => 'Karat dan fungsi', 'sort_order' => 7],
                    ['name' => 'Electronic Module', 'description' => 'Module under seat', 'check_notes' => 'Korosi, fungsi', 'sort_order' => 8],
                    // ['name' => 'Musty Smell', 'description' => 'Bau apek/jamur', 'check_notes' => 'Indikasi banjir', 'sort_order' => 9],
                    // ['name' => 'Water Line Mark', 'description' => 'Garis air', 'check_notes' => 'Pada karpet/door panel', 'sort_order' => 10],
                ],

                // Kaki Kaki
                'Kaki Kaki' => [
                    ['name' => 'Ban Depan', 'description' => 'Front tires', 'check_notes' => 'Tread depth, kondisi', 'sort_order' => 1],
                    ['name' => 'Ban Belakang', 'description' => 'Rear tires', 'check_notes' => 'Tread depth, kondisi', 'sort_order' => 2],
                    ['name' => 'Velg', 'description' => 'Wheels/rims', 'check_notes' => 'Bent, curb rash', 'sort_order' => 3],
                    ['name' => 'Shock Absorber Depan', 'description' => 'Front shocks', 'check_notes' => 'Bocor, bounce test', 'sort_order' => 4],
                    ['name' => 'Shock Absorber Belakang', 'description' => 'Rear shocks', 'check_notes' => 'Bocor, bounce test', 'sort_order' => 5],
                    ['name' => 'Spring Depan', 'description' => 'Front springs', 'check_notes' => 'Retak, ketinggian', 'sort_order' => 6],
                    ['name' => 'Spring Belakang', 'description' => 'Rear springs', 'check_notes' => 'Retak, ketinggian', 'sort_order' => 7],
                    ['name' => 'Ball Joint', 'description' => 'Ball joint', 'check_notes' => 'Play, boot rusak', 'sort_order' => 8],
                    ['name' => 'Control Arm Bushing', 'description' => 'Control arm bushing', 'check_notes' => 'Crack, wear', 'sort_order' => 9],
                    ['name' => 'Tie Rod End', 'description' => 'Tie rod end', 'check_notes' => 'Play, boot rusak', 'sort_order' => 10],
                    ['name' => 'Stabilizer Link', 'description' => 'Sway bar link', 'check_notes' => 'Play, bush rusak', 'sort_order' => 11],
                    ['name' => 'Strut Mount', 'description' => 'Strut mount', 'check_notes' => 'Suara, bearing', 'sort_order' => 12],
                    ['name' => 'Wheel Bearing', 'description' => 'Roda bearing', 'check_notes' => 'Suara, play', 'sort_order' => 13],
                    ['name' => 'Brake Pad Depan', 'description' => 'Front brake pads', 'check_notes' => 'Ketebalan, uneven wear', 'sort_order' => 14],
                    ['name' => 'Brake Pad Belakang', 'description' => 'Rear brake pads', 'check_notes' => 'Ketebalan, uneven wear', 'sort_order' => 15],
                    ['name' => 'Brake Disc/Rotor', 'description' => 'Brake discs', 'check_notes' => 'Groove, warp, thickness', 'sort_order' => 16],
                    ['name' => 'Brake Drum', 'description' => 'Brake drums', 'check_notes' => 'Untuk rem tromol', 'sort_order' => 17],
                    ['name' => 'Brake Caliper', 'description' => 'Caliper condition', 'check_notes' => 'Piston, slide pin', 'sort_order' => 18],
                    ['name' => 'Brake Hose', 'description' => 'Brake hose', 'check_notes' => 'Crack, bulge, leak', 'sort_order' => 19],
                    ['name' => 'Brake Fluid', 'description' => 'Cairan rem', 'check_notes' => 'Level, warna, usia', 'sort_order' => 20],
                    ['name' => 'Parking Brake', 'description' => 'Rem tangan', 'check_notes' => 'Adjustment, hold test', 'sort_order' => 21],
                    ['name' => 'Power Steering System', 'description' => 'Sistem power steering', 'check_notes' => 'Fluid, pump, hose', 'sort_order' => 22],
                    ['name' => 'Rack & Pinion', 'description' => 'Steering rack', 'check_notes' => 'Leak, play, bushing', 'sort_order' => 23],
                    ['name' => 'CV Joint Boot', 'description' => 'Boot CV joint', 'check_notes' => 'Tear, leak grease', 'sort_order' => 24],
                    ['name' => 'Spare Tire', 'description' => 'Ban serep', 'check_notes' => 'Kondisi, tekanan', 'sort_order' => 25],
                ],

                // Chassis
                'Chassis' => [
                    ['name' => 'Underbody Rust', 'description' => 'Karat bawah kendaraan', 'check_notes' => 'Skala karat 1-10', 'sort_order' => 1],
                    ['name' => 'Frame Condition', 'description' => 'Kondisi rangka', 'check_notes' => 'Bent, crack, repair', 'sort_order' => 2],
                    ['name' => 'Exhaust Hanger', 'description' => 'Gantungan knalpot', 'check_notes' => 'Rust, broken', 'sort_order' => 3],
                    ['name' => 'Fuel Tank', 'description' => 'Tangki bensin', 'check_notes' => 'Leak, rust, mounting', 'sort_order' => 4],
                    ['name' => 'Heat Shield', 'description' => 'Pelindung panas', 'check_notes' => 'Loose, missing', 'sort_order' => 5],
                    ['name' => 'Suspension Mount Points', 'description' => 'Titik mounting suspensi', 'check_notes' => 'Crack, weld quality', 'sort_order' => 6],
                    ['name' => 'Body Mount Bushings', 'description' => 'Body mount', 'check_notes' => 'Crack, deterioration', 'sort_order' => 7],
                ],
            ];

            // Insert inspection items
            foreach ($inspectionItems as $componentName => $items) {
                $componentId = $componentMap[$componentName] ?? null;
                if ($componentId) {
                    foreach ($items as $item) {
                        InspectionItem::firstOrCreate(
                            [
                                'component_id' => $componentId,
                                'name' => $item['name']
                            ],
                            [
                                'description' => $item['description'] ?? null,
                                'check_notes' => $item['check_notes'] ?? null,
                                'sort_order' => $item['sort_order'],
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }

            $this->command->info('Components and inspection items seeded successfully!');
            $this->command->info('Total Components: ' . count($components));
            $this->command->info('Total Inspection Items: ' . array_sum(array_map('count', $inspectionItems)));
        });
    }
}
