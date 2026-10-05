<?php

namespace Database\Seeders;

use App\Models\BudgetItem;
use App\Models\Permission;
use App\Models\RevitalizationProject;
use App\Models\Role;
use App\Models\School;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $rootRole = Role::create([
            'name' => 'root',
            'display_name' => 'Root (Super Administrator)',
            'description' => 'Akses penuh ke semua modul, kelola user, role, dan konfigurasi permission granular.',
        ]);

        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Admin Revitalisasi (Pengelola / Bendahara)',
            'description' => 'Akses ke modul teknis pelaporan revitalisasi: data sekolah, proyek, RAB, transaksi, BKU, BPK, BB, BP, dan bukti pengeluaran.',
        ]);

        $userRole = Role::create([
            'name' => 'user',
            'display_name' => 'User (Viewer / Pengawas / Kepala Sekolah)',
            'description' => 'Akses view laporan, monitoring realisasi revitalisasi, dan cetak/export output laporan BKU, BPK, BB, BP.',
        ]);

        // 2. Permissions per module
        $modules = [
            'dashboard' => ['view'],
            'sekolah' => ['view', 'create', 'update', 'delete', 'export'],
            'proyek' => ['view', 'create', 'update', 'delete', 'export'],
            'rab' => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'transaksi' => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'bku' => ['view', 'export', 'print'],
            'bpk' => ['view', 'export', 'print'],
            'bb' => ['view', 'export', 'print'],
            'bp' => ['view', 'export', 'print'],
            'laporan' => ['view', 'export', 'print'],
            'kuitansi' => ['view', 'create', 'print'],
            'users' => ['view', 'create', 'update', 'delete'],
            'roles' => ['view', 'manage'],
        ];

        $allPermissionIds = [];
        $adminPermissionIds = [];
        $userPermissionIds = [];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $display = ucfirst($action) . ' ' . ucfirst($module);
                $perm = Permission::create([
                    'name' => $name,
                    'module' => $module,
                    'action' => $action,
                    'display_name' => $display,
                    'description' => "Izin untuk {$action} pada modul {$module}",
                ]);

                $allPermissionIds[] = $perm->id;

                // Admin gets revitalization modules
                if (!in_array($module, ['users', 'roles'])) {
                    $adminPermissionIds[] = $perm->id;
                }

                // User gets viewing & printing/exporting
                if (in_array($action, ['view', 'export', 'print'])) {
                    if (!in_array($module, ['users', 'roles'])) {
                        $userPermissionIds[] = $perm->id;
                    }
                }
            }
        }

        $rootRole->permissions()->sync($allPermissionIds);
        $adminRole->permissions()->sync($adminPermissionIds);
        $userRole->permissions()->sync($userPermissionIds);

        // 3. Schools in Situbondo
        $smp1 = School::create([
            'npsn' => '20522134',
            'name' => 'SMP Negeri 1 Situbondo',
            'jenjang' => 'SMP',
            'status' => 'Negeri',
            'address' => 'Jl. PB. Sudirman No. 5, Kel. Patokan',
            'kecamatan' => 'Situbondo',
            'kabupaten' => 'Situbondo',
            'provinsi' => 'Jawa Timur',
            'postal_code' => '68312',
            'principal_name' => 'Drs. H. Bambang Supratman, M.Pd.',
            'principal_nip' => '19680512 199403 1 008',
            'treasurer_name' => 'Siti Nur Aisyah, S.E.',
            'treasurer_nip' => '19840915 200801 2 011',
            'bank_name' => 'Bank Jatim Cabang Situbondo',
            'bank_account_number' => '0311-0024-9182',
            'bank_account_holder' => 'BANTUAN REVITALISASI SMPN 1 SITUBONDO',
            'phone' => '(0338) 671234',
            'email' => 'smpn1situbondo@disdik.situbondokab.go.id',
        ]);

        $sd1 = School::create([
            'npsn' => '20521980',
            'name' => 'SD Negeri 1 Patokan',
            'jenjang' => 'SD',
            'status' => 'Negeri',
            'address' => 'Jl. Kartini No. 12, Kel. Patokan',
            'kecamatan' => 'Situbondo',
            'kabupaten' => 'Situbondo',
            'provinsi' => 'Jawa Timur',
            'postal_code' => '68311',
            'principal_name' => 'Endang Sri Wahyuni, S.Pd., M.M.',
            'principal_nip' => '19720310 199702 2 004',
            'treasurer_name' => 'Achmad Rifa\'i, S.Pd.',
            'treasurer_nip' => '19881120 201101 1 005',
            'bank_name' => 'Bank Jatim Cabang Situbondo',
            'bank_account_number' => '0311-0088-7711',
            'bank_account_holder' => 'REVITALISASI SDN 1 PATOKAN SITUBONDO',
            'phone' => '(0338) 672100',
            'email' => 'sdn1patokan@disdik.situbondokab.go.id',
        ]);

        $smk1 = School::create([
            'npsn' => '20522150',
            'name' => 'SMK Negeri 1 Panji Situbondo',
            'jenjang' => 'SMK',
            'status' => 'Negeri',
            'address' => 'Jl. Argopuro No. 45, Kec. Panji',
            'kecamatan' => 'Panji',
            'kabupaten' => 'Situbondo',
            'provinsi' => 'Jawa Timur',
            'postal_code' => '68321',
            'principal_name' => 'Ir. Hendro Wibowo, M.T.',
            'principal_nip' => '19650401 199103 1 009',
            'treasurer_name' => 'Farida Kusumaningrum, S.Ak.',
            'treasurer_nip' => '19860614 200902 2 007',
            'bank_name' => 'Bank Jatim Cabang Situbondo',
            'bank_account_number' => '0311-0077-3322',
            'bank_account_holder' => 'DANA REVITALISASI SMKN 1 PANJI',
            'phone' => '(0338) 674555',
            'email' => 'smkn1panji@disdik.situbondokab.go.id',
        ]);

        // 4. Default Users
        User::create([
            'name' => 'Super Administrator (Root)',
            'username' => 'root',
            'email' => 'root@sirevita.situbondokab.go.id',
            'password' => Hash::make('password123'),
            'role_id' => $rootRole->id,
            'school_id' => null,
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $adminUser = User::create([
            'name' => 'Bendahara & Admin SMPN 1 Situbondo',
            'username' => 'admin',
            'email' => 'admin@sirevita.situbondokab.go.id',
            'password' => Hash::make('password123'),
            'role_id' => $adminRole->id,
            'school_id' => $smp1->id,
            'phone' => '081298765432',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Tim Pengawas / Pengguna Laporan',
            'username' => 'user',
            'email' => 'pengawas@sirevita.situbondokab.go.id',
            'password' => Hash::make('password123'),
            'role_id' => $userRole->id,
            'school_id' => $smp1->id,
            'phone' => '081333444555',
            'is_active' => true,
        ]);

        // 5. Revitalization Projects
        $proj1 = RevitalizationProject::create([
            'school_id' => $smp1->id,
            'fiscal_year' => 2026,
            'title' => 'Revitalisasi Gedung Ruang Kelas, Perpustakaan, dan Lab Komputer',
            'funding_source' => 'DAK Fisik Bidang Pendidikan SMP TA 2026',
            'spk_number' => '027/451/431.201.2/2026',
            'spk_date' => '2026-03-01',
            'contract_amount' => 850000000,
            'start_date' => '2026-03-05',
            'end_date' => '2026-09-30',
            'status' => 'pelaksanaan',
            'physical_progress' => 68.50,
            'description' => 'Paket revitalisasi sarana prasarana fisik pendidikan meliputi rehabilitasi sedang 4 ruang kelas belajar, penataan ruang perpustakaan digital, dan peremajaan laboratorium komputer SMPN 1 Situbondo.',
        ]);

        $proj2 = RevitalizationProject::create([
            'school_id' => $sd1->id,
            'fiscal_year' => 2026,
            'title' => 'Rehabilitasi Ruang Kelas dan Pembangunan Sanitasi Sekolah Sehat',
            'funding_source' => 'Bantuan Keuangan Revitalisasi Sekolah APBD Kab. Situbondo TA 2026',
            'spk_number' => '027/212/431.201.1/2026',
            'spk_date' => '2026-04-10',
            'contract_amount' => 420000000,
            'start_date' => '2026-04-15',
            'end_date' => '2026-08-15',
            'status' => 'pelaksanaan',
            'physical_progress' => 45.00,
            'description' => 'Perbaikan atap dan plafon ruang kelas 1-3 serta pembangunan fasilitas toilet dan tempat cuci tangan higienis bagi siswa SDN 1 Patokan.',
        ]);

        // 6. RAB (Budget Items) for Project 1
        $item1 = BudgetItem::create([
            'project_id' => $proj1->id,
            'code' => '5.2.2.01.01',
            'category' => 'Material/Bahan',
            'name' => 'Semen Portland Gresik 40 kg & Pasir Pasang Lumajang',
            'volume' => 800,
            'unit' => 'sak/m3',
            'unit_price' => 75000,
            'total_price' => 60000000,
            'notes' => 'Pekerjaan pasangan bata dan plesteran dinding ruang kelas',
        ]);

        $item2 = BudgetItem::create([
            'project_id' => $proj1->id,
            'code' => '5.2.2.01.02',
            'category' => 'Material/Bahan',
            'name' => 'Rangka Atap Baja Ringan C75 & Genteng Metal Pasir',
            'volume' => 520,
            'unit' => 'm2',
            'unit_price' => 250000,
            'total_price' => 130000000,
            'notes' => 'Penggantian seluruh atap ruang kelas yang lapuk',
        ]);

        $item3 = BudgetItem::create([
            'project_id' => $proj1->id,
            'code' => '5.2.2.01.03',
            'category' => 'Material/Bahan',
            'name' => 'Keramik Granit 60x60 Polished & Cat Tembok Eksterior/Interior',
            'volume' => 480,
            'unit' => 'm2/pail',
            'unit_price' => 195000,
            'total_price' => 93600000,
            'notes' => 'Finishing lantai dan pengecatan ruang perpustakaan & lab',
        ]);

        $item4 = BudgetItem::create([
            'project_id' => $proj1->id,
            'code' => '5.2.2.02.01',
            'category' => 'Upah Tenaga Kerja',
            'name' => 'Upah Tukang Bangunan & Pekerja Konstruksi Swakelola',
            'volume' => 450,
            'unit' => 'OH',
            'unit_price' => 135000,
            'total_price' => 60750000,
            'notes' => 'Upah tenaga kerja harian dengan sistem mingguan',
        ]);

        $item5 = BudgetItem::create([
            'project_id' => $proj1->id,
            'code' => '5.2.2.03.01',
            'category' => 'Peralatan/Sewa',
            'name' => 'Sewa Scaffolding Perancah & Molen Mixer Beton',
            'volume' => 3,
            'unit' => 'bulan',
            'unit_price' => 5000000,
            'total_price' => 15000000,
            'notes' => 'Alat bantu pekerjaan struktur dan atap',
        ]);

        $item6 = BudgetItem::create([
            'project_id' => $proj1->id,
            'code' => '5.2.2.04.01',
            'category' => 'Honor & Operasional',
            'name' => 'Honor Tim Pelaksana Revitalisasi Swakelola Sekolah & Konsultasi Teknis',
            'volume' => 1,
            'unit' => 'paket',
            'unit_price' => 25000000,
            'total_price' => 25000000,
            'notes' => 'Manajemen pengelolaan, pelaporan, dan gambar kerja',
        ]);

        // 7. Transactions (Double-entry Cash Flow for BKU, BPK, BB, BP)
        // TRX 1: Penerimaan Termin 1 DAK Fisik Rp 340.000.000 masuk ke Rekening Bank Jatim
        Transaction::create([
            'school_id' => $smp1->id,
            'project_id' => $proj1->id,
            'budget_item_id' => null,
            'transaction_number' => 'BKU-2026/001',
            'transaction_date' => '2026-03-10',
            'type' => 'penerimaan_dana',
            'payment_method' => 'bank_transfer',
            'description' => 'Penerimaan Penyaluran Dana Alokasi Khusus (DAK) Fisik Bidang Pendidikan Termin I (40%) dari Kasda Pemkab Situbondo ke Rekening Sekolah',
            'recipient_name' => 'Pemerintah Kabupaten Situbondo / BPPKAD',
            'recipient_address' => 'Jl. Kartini No. 1 Situbondo',
            'amount' => 340000000,
            'has_tax' => false,
            'tax_total' => 0,
            'net_amount' => 340000000,
            'tax_status' => 'none',
            'created_by' => $adminUser->id,
        ]);

        // TRX 2: Tarik Tunai dari Bank Rp 60.000.000 ke Kas Tunai Bendahara
        Transaction::create([
            'school_id' => $smp1->id,
            'project_id' => $proj1->id,
            'budget_item_id' => null,
            'transaction_number' => 'BKU-2026/002',
            'transaction_date' => '2026-03-12',
            'type' => 'tarik_tunai',
            'payment_method' => 'kas_tunai',
            'description' => 'Penarikan tunai dari Bank Jatim Cabang Situbondo untuk pengisian Kas Tunai Bendahara kegiatan revitalisasi',
            'recipient_name' => 'Bendahara Revitalisasi (Siti Nur Aisyah, S.E.)',
            'recipient_address' => 'SMPN 1 Situbondo',
            'amount' => 60000000,
            'has_tax' => false,
            'tax_total' => 0,
            'net_amount' => 60000000,
            'tax_status' => 'none',
            'created_by' => $adminUser->id,
        ]);

        // TRX 3: Belanja Bahan Material Semen & Pasir tunai Rp 18.500.000 (PPN 11% Rp 1.833.333, PPh 22 1.5% Rp 250.000)
        Transaction::create([
            'school_id' => $smp1->id,
            'project_id' => $proj1->id,
            'budget_item_id' => $item1->id,
            'transaction_number' => 'BKU-2026/003',
            'transaction_date' => '2026-03-15',
            'type' => 'belanja_tunai',
            'payment_method' => 'kas_tunai',
            'description' => 'Pembayaran belanja material Semen Gresik 150 sak dan Pasir Lumajang 4 truk ke UD. Berkah Jaya Bahan Bangunan Situbondo',
            'recipient_name' => 'UD. Berkah Jaya Bangunan (H. Subagyo)',
            'recipient_address' => 'Jl. Basuki Rahmat No. 88, Panji, Situbondo',
            'amount' => 18500000,
            'has_tax' => true,
            'tax_type' => 'PPN & PPh 22',
            'tax_ppn' => 1833333,
            'tax_pph22' => 250000,
            'tax_total' => 2083333,
            'net_amount' => 16416667,
            'tax_ntpn' => 'NTPN-893184918231',
            'tax_payment_date' => '2026-03-20',
            'tax_status' => 'disetor',
            'created_by' => $adminUser->id,
        ]);

        // TRX 4: Belanja Rangka Baja Ringan via Transfer Bank Rp 65.000.000 (PPN Rp 6.441.441, PPh 22 Rp 878.378)
        Transaction::create([
            'school_id' => $smp1->id,
            'project_id' => $proj1->id,
            'budget_item_id' => $item2->id,
            'transaction_number' => 'BKU-2026/004',
            'transaction_date' => '2026-03-18',
            'type' => 'belanja_transfer',
            'payment_method' => 'bank_transfer',
            'description' => 'Pembayaran material rangka baja ringan zincalume dan genteng metal pasir untuk atap 4 ruang kelas ke CV. Situbondo Baja Presisi',
            'recipient_name' => 'CV. Situbondo Baja Presisi',
            'recipient_address' => 'Jl. Argopuro No. 12, Mimbaan, Panji, Situbondo',
            'amount' => 65000000,
            'has_tax' => true,
            'tax_type' => 'PPN & PPh 22',
            'tax_ppn' => 6441441,
            'tax_pph22' => 878378,
            'tax_total' => 7319819,
            'net_amount' => 57680181,
            'tax_ntpn' => 'NTPN-781928374910',
            'tax_payment_date' => '2026-03-22',
            'tax_status' => 'disetor',
            'created_by' => $adminUser->id,
        ]);

        // TRX 5: Pembayaran Upah Tenaga Kerja Mingguan Rp 15.000.000 Kas Tunai (PPh 21 Rp 375.000)
        Transaction::create([
            'school_id' => $smp1->id,
            'project_id' => $proj1->id,
            'budget_item_id' => $item4->id,
            'transaction_number' => 'BKU-2026/005',
            'transaction_date' => '2026-03-24',
            'type' => 'belanja_tunai',
            'payment_method' => 'kas_tunai',
            'description' => 'Pembayaran upah tukang dan pekerja konstruksi revitalisasi Tahap I Minggu 1 & 2',
            'recipient_name' => 'Mandor Slamet Riyadi & Rekan',
            'recipient_address' => 'Desa Talkandang, Situbondo',
            'amount' => 15000000,
            'has_tax' => true,
            'tax_type' => 'PPh 21',
            'tax_pph21' => 375000,
            'tax_total' => 375000,
            'net_amount' => 14625000,
            'tax_ntpn' => 'NTPN-981203948571',
            'tax_payment_date' => '2026-03-26',
            'tax_status' => 'disetor',
            'created_by' => $adminUser->id,
        ]);

        // TRX 6: Sewa Molen dan Scaffolding tunai Rp 5.000.000 (PPh 23 Rp 100.000)
        Transaction::create([
            'school_id' => $smp1->id,
            'project_id' => $proj1->id,
            'budget_item_id' => $item5->id,
            'transaction_number' => 'BKU-2026/006',
            'transaction_date' => '2026-03-27',
            'type' => 'belanja_tunai',
            'payment_method' => 'kas_tunai',
            'description' => 'Pembayaran sewa molen mixer beton dan 40 set scaffolding perancah selama 1 bulan',
            'recipient_name' => 'Rental Alat Teknik Situbondo Mandiri',
            'recipient_address' => 'Jl. Madura No. 15, Situbondo',
            'amount' => 5000000,
            'has_tax' => true,
            'tax_type' => 'PPh 23',
            'tax_pph23' => 100000,
            'tax_total' => 100000,
            'net_amount' => 4900000,
            'tax_ntpn' => 'NTPN-662910394821',
            'tax_payment_date' => '2026-03-30',
            'tax_status' => 'disetor',
            'created_by' => $adminUser->id,
        ]);

        // TRX 7: Belanja Granit & Cat Ruang Kelas transfer Bank Rp 32.000.000 (PPN Rp 3.168.000, PPh 22 Rp 432.000) - Pajak belum disetor (dipungut)
        Transaction::create([
            'school_id' => $smp1->id,
            'project_id' => $proj1->id,
            'budget_item_id' => $item3->id,
            'transaction_number' => 'BKU-2026/007',
            'transaction_date' => '2026-04-02',
            'type' => 'belanja_transfer',
            'payment_method' => 'bank_transfer',
            'description' => 'Pembayaran pembelian granit lantai 60x60 Roman dan cat tembok Dulux Weathershield ke Toko Mega Bangunan Situbondo',
            'recipient_name' => 'Toko Mega Bangunan Situbondo',
            'recipient_address' => 'Jl. Ahmad Yani No. 50, Situbondo',
            'amount' => 32000000,
            'has_tax' => true,
            'tax_type' => 'PPN & PPh 22',
            'tax_ppn' => 3168000,
            'tax_pph22' => 432000,
            'tax_total' => 3600000,
            'net_amount' => 28400000,
            'tax_ntpn' => null,
            'tax_payment_date' => null,
            'tax_status' => 'dipungut',
            'created_by' => $adminUser->id,
        ]);

        // TRX 8: Bunga Rekening Bank Jatim Rp 425.000
        Transaction::create([
            'school_id' => $smp1->id,
            'project_id' => $proj1->id,
            'budget_item_id' => null,
            'transaction_number' => 'BKU-2026/008',
            'transaction_date' => '2026-04-05',
            'type' => 'bunga_bank',
            'payment_method' => 'bank_transfer',
            'description' => 'Penerimaan Jasa Giro / Bunga Rekening Bank Jatim Cabang Situbondo bulan Maret 2026',
            'recipient_name' => 'Bank Jatim Cabang Situbondo',
            'recipient_address' => 'Jl. Basuki Rahmat No. 1, Situbondo',
            'amount' => 425000,
            'has_tax' => false,
            'tax_total' => 0,
            'net_amount' => 425000,
            'tax_status' => 'none',
            'created_by' => $adminUser->id,
        ]);

        // TRX 9: Biaya Pajak Bunga & Administrasi Bank Rp 85.000
        Transaction::create([
            'school_id' => $smp1->id,
            'project_id' => $proj1->id,
            'budget_item_id' => null,
            'transaction_number' => 'BKU-2026/009',
            'transaction_date' => '2026-04-05',
            'type' => 'biaya_bank',
            'payment_method' => 'bank_transfer',
            'description' => 'Pemotongan Pajak Bunga Jasa Giro dan Biaya Administrasi Pengelolaan Rekening Koran Bank Jatim',
            'recipient_name' => 'Bank Jatim Cabang Situbondo',
            'recipient_address' => 'Jl. Basuki Rahmat No. 1, Situbondo',
            'amount' => 85000,
            'has_tax' => false,
            'tax_total' => 0,
            'net_amount' => 85000,
            'tax_status' => 'none',
            'created_by' => $adminUser->id,
        ]);
    }
}

