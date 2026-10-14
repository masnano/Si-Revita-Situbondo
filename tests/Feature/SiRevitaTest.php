<?php

namespace Tests\Feature;

use App\Models\RevitalizationProject;
use App\Models\RpdDocument;
use App\Models\RpdItem;
use App\Models\School;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class SiRevitaTest extends TestCase
{
    public function test_guest_is_redirected_to_login()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_login_page_renders_successfully()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Si Revita');
        $response->assertSee('Situbondo');
    }

    public function test_root_can_access_all_modules()
    {
        $root = User::where('username', 'root')->first();
        $this->actingAs($root);

        // Dashboard
        $this->get('/dashboard')->assertStatus(200);

        // 4 Core Reports
        $this->get('/laporan/bku')->assertStatus(200)->assertSee('Buku Kas Umum');
        $this->get('/laporan/bpk')->assertStatus(200)->assertSee('Buku Pembantu Kas');
        $this->get('/laporan/bb')->assertStatus(200)->assertSee('Buku Pembantu Bank');
        $this->get('/laporan/bp')->assertStatus(200)->assertSee('Buku Pembantu Pajak');
        $this->get('/laporan/realisasi')->assertStatus(200)->assertSee('Realisasi');
        $this->get('/laporan/kuitansi')->assertStatus(200);

        // Print Kuitansi
        $trx = Transaction::first();
        if ($trx) {
            $this->get("/laporan/kuitansi/{$trx->id}/print")->assertStatus(200)->assertSee('Kuitansi');
        }

        // Transactions CRUD
        $this->get('/transactions')->assertStatus(200);
        $this->get('/transactions/create')->assertStatus(200);

        // Master Data
        $this->get('/schools')->assertStatus(200);
        $this->get('/projects')->assertStatus(200);
        $this->get('/rab')->assertStatus(200);

        // Root Administration
        $this->get('/users')->assertStatus(200);
        $this->get('/roles')->assertStatus(200);
    }

    public function test_admin_can_access_revitalization_reports_but_not_user_management()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        // Reports
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/laporan/bku')->assertStatus(200);
        $this->get('/laporan/bpk')->assertStatus(200);
        $this->get('/laporan/bb')->assertStatus(200);
        $this->get('/laporan/bp')->assertStatus(200);
        $this->get('/transactions')->assertStatus(200);

        // Admin forbidden from Root modules
        $this->get('/users')->assertStatus(403);
        $this->get('/roles')->assertStatus(403);
    }

    public function test_user_can_view_reports_only()
    {
        $user = User::where('username', 'user')->first();
        $this->actingAs($user);

        // Can view reports
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/laporan/bku')->assertStatus(200);
        $this->get('/laporan/bpk')->assertStatus(200);
        $this->get('/laporan/bb')->assertStatus(200);
        $this->get('/laporan/bp')->assertStatus(200);

        // Forbidden from Root modules
        $this->get('/users')->assertStatus(403);
        $this->get('/roles')->assertStatus(403);
    }

    public function test_csv_export_works()
    {
        $root = User::where('username', 'root')->first();
        $this->actingAs($root);

        $school = School::first();
        $response = $this->get("/laporan/export/bku?school_id={$school->id}&month=3&year=2026");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $responseAllBku = $this->get("/laporan/export/bku?school_id={$school->id}&month=all&year=2026");
        $responseAllBku->assertStatus(200);
        $responseAllBku->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $responseAllBpk = $this->get("/laporan/export/bpk?school_id={$school->id}&month=all&year=2026");
        $responseAllBpk->assertStatus(200);
        $responseAllBpk->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $responseAllBb = $this->get("/laporan/export/bb?school_id={$school->id}&month=all&year=2026");
        $responseAllBb->assertStatus(200);
        $responseAllBb->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_reports_support_filter_all_months()
    {
        $root = User::where('username', 'root')->first();
        $this->actingAs($root);

        $this->get('/laporan/bku?month=all&year=2026')
            ->assertStatus(200)
            ->assertSee('Semua Bulan')
            ->assertSee('Saldo Awal Tahun');

        $this->get('/laporan/bpk?month=all&year=2026')
            ->assertStatus(200)
            ->assertSee('Semua Bulan')
            ->assertSee('Saldo Awal Kas Tunai (Awal Tahun');

        $this->get('/laporan/bb?month=all&year=2026')
            ->assertStatus(200)
            ->assertSee('Semua Bulan')
            ->assertSee('Saldo Awal Rekening Bank (Awal Tahun');
    }

    public function test_report_print_pdf_titles_start_with_month_name()
    {
        $root = User::where('username', 'root')->first();
        $this->actingAs($root);

        // BKU month 3 (Maret)
        $resBku = $this->get('/laporan/bku?month=3&year=2026');
        $resBku->assertStatus(200);
        $this->assertStringContainsString('<title>Maret 2026 - Buku Kas Umum (BKU)', $resBku->getContent());

        // BPK month 3 (Maret)
        $resBpk = $this->get('/laporan/bpk?month=3&year=2026');
        $resBpk->assertStatus(200);
        $this->assertStringContainsString('<title>Maret 2026 - Buku Pembantu Kas (BPK)', $resBpk->getContent());

        // BB month 3 (Maret)
        $resBb = $this->get('/laporan/bb?month=3&year=2026');
        $resBb->assertStatus(200);
        $this->assertStringContainsString('<title>Maret 2026 - Buku Pembantu Bank (BB)', $resBb->getContent());

        // BP month 3 (Maret)
        $resBp = $this->get('/laporan/bp?month=3&year=2026');
        $resBp->assertStatus(200);
        $this->assertStringContainsString('<title>Maret 2026 - Buku Pembantu Pajak (BP)', $resBp->getContent());

        // Realisasi month 3 (Maret)
        $resRealisasi = $this->get('/laporan/realisasi?month=3&year=2026');
        $resRealisasi->assertStatus(200);
        $this->assertStringContainsString('<title>Maret 2026 - Laporan Realisasi Anggaran', $resRealisasi->getContent());

        // Kuitansi print for existing transaction in March
        $tx = Transaction::whereMonth('transaction_date', 3)->first();
        if ($tx) {
            $resKuitansi = $this->get("/laporan/kuitansi/{$tx->id}/print");
            $resKuitansi->assertStatus(200);
            $this->assertStringContainsString('<title>Maret 2026 - Kuitansi', $resKuitansi->getContent());
        }
    }

    public function test_rpd_templates_can_be_downloaded()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        // Download Excel template
        $responseExcel = $this->get('/rpd/template?format=xlsx');
        $responseExcel->assertStatus(200);
        $responseExcel->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Download CSV template
        $responseCsv = $this->get('/rpd/template?format=csv');
        $responseCsv->assertStatus(200);
        $responseCsv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_upload_rpd_and_pecah_bahan_generates_bku_candidates()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $project = \App\Models\RevitalizationProject::first();

        // Create sample CSV data
        $csvContent = "No,Kode,Kategori,Uraian,Spesifikasi,Volume,Satuan,Harga Satuan,Total,Tanggal,Toko,Metode\n" .
                      "1,5.1.02.01,Bahan,Semen Gresik 50 Kg,PCC,100,Zak,68000,6800000,2026-03-05,Toko Bangunan Berkah,Transfer Bank\n" .
                      "2,5.1.02.02,Upah,Upah Tukang Batu,HOK,24,OH,125000,3000000,2026-03-12,Kelompok Tukang Pak Slamet,Kas Tunai\n" .
                      "3,5.1.02.01,Bahan,Paku Kayu 5cm,Campur,10,Kg,25000,250000,2026-03-08,Toko Besi Abadi,Kas Tunai\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'rpd_test_') . '.csv';
        file_put_contents($tmpFile, $csvContent);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tmpFile,
            'RPD_Uji_Coba_Situbondo.csv',
            'text/csv',
            null,
            true
        );

        \App\Models\RpdDocument::where('title', 'RPD Pengujian Otomatis BKU')->delete();

        $response = $this->post('/rpd', [
            'school_id' => $project->school_id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap 1',
            'title' => 'RPD Pengujian Otomatis BKU',
            'rpd_file' => $uploadedFile,
        ]);

        $response->assertRedirect();
        
        $doc = \App\Models\RpdDocument::where('title', 'RPD Pengujian Otomatis BKU')->latest('id')->first();
        $this->assertNotNull($doc);
        $this->assertEquals(3, $doc->items()->count());

        // Cek Bahan dengan nilai > 2jt kena PPN & PPh 22
        $itemSemen = $doc->items()->where('item_name', 'Semen Gresik 50 Kg')->first();
        $this->assertNotNull($itemSemen);
        $this->assertTrue($itemSemen->has_tax);
        $this->assertGreaterThan(0, $itemSemen->tax_ppn);
        $this->assertGreaterThan(0, $itemSemen->tax_pph22);

        // Cek Bahan < 2jt bebas pajak
        $itemPaku = $doc->items()->where('item_name', 'Paku Kayu 5cm')->first();
        $this->assertNotNull($itemPaku);
        $this->assertEquals(0, $itemPaku->tax_total);

        // Test Posting ke BKU Resmi
        $postResponse = $this->post("/rpd/{$doc->id}/post-bku");
        $postResponse->assertRedirect();

        $doc->refresh();
        $this->assertEquals('posted_to_bku', $doc->status);
        $this->assertEquals(3, $doc->items()->where('is_posted', true)->count());

        // Verifikasi transaksi tercatat di BKU periode Maret 2026
        $this->get('/laporan/bku?project_id=' . $project->id . '&month=3&year=2026')
            ->assertStatus(200)
            ->assertSee('Semen Gresik 50 Kg');
    }

    public function test_upload_rpd_official_format_breaks_down_into_detailed_materials_and_wages()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $project = \App\Models\RevitalizationProject::first();

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            base_path('storage/app/contoh_rpd_referensi.csv'),
            'RPD_Resmi_SDN3_Sumberargo.csv',
            'text/csv',
            null,
            true
        );

        \App\Models\RpdDocument::where('title', 'RPD Revitalisasi Resmi Detailed Test')->delete();

        $response = $this->post('/rpd', [
            'school_id' => $project->school_id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap 1',
            'target_column' => 'termin_1',
            'breakdown_mode' => 'detailed',
            'title' => 'RPD Revitalisasi Resmi Detailed Test',
            'rpd_file' => $uploadedFile,
            'default_date' => '2026-07-15',
        ]);

        $response->assertRedirect();

        $doc = \App\Models\RpdDocument::where('title', 'RPD Revitalisasi Resmi Detailed Test')->latest('id')->first();
        $this->assertNotNull($doc);

        // Verifikasi 48 item material & upah terurai secara realistis (atap 1 paket pembelian)
        $this->assertEquals(48, $doc->items()->count());
        $this->assertEquals(313422410, (int)$doc->total_budget);

        // Verifikasi item material spesifik per pekerjaan
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $doc->id,
            'category' => 'bahan',
            'item_name' => 'Semen Portland (PCC 50 Kg) - Ruang Kelas',
        ]);
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $doc->id,
            'category' => 'bahan',
            'item_name' => 'Pengadaan Bahan Atap (Rangka Baja Ringan Galvalum & Genteng/Spandek) - Ruang Kelas',
        ]);
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $doc->id,
            'category' => 'bahan',
            'item_name' => 'Kloset Jongkok Porselen SNI (INA / TOTO) - Toilet - 1',
        ]);
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $doc->id,
            'category' => 'bahan',
            'item_name' => 'Plafon Kalsiboard 4mm Kedap Akustik + Hollow - Perpustakaan',
        ]);
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $doc->id,
            'category' => 'upah',
            'item_name' => 'Upah Tukang Plumbing & Sanitasi (Minggu 2) - Toilet - 1',
        ]);

        // Verifikasi penanggalan BKU realistis & otomatis:
        // 1. Tidak ada transaksi di hari Minggu
        $sundayItems = $doc->items->filter(fn($i) => \Carbon\Carbon::parse($i->planned_date)->isSunday());
        $this->assertEquals(0, $sundayItems->count(), 'Tidak boleh ada transaksi BKU di hari Minggu');

        // 2. Upah pekerja dibayarkan pada hari Sabtu (akhir pekan)
        $upahItems = $doc->items->where('category', 'upah');
        foreach ($upahItems as $upah) {
            $this->assertTrue(\Carbon\Carbon::parse($upah->planned_date)->isSaturday(), 'Upah tukang harus dibayarkan pada hari Sabtu akhir pekan');
        }

        // 3. Rentang tanggal bertahap (progresif) selama rentang 3-4 minggu
        $earliest = $doc->items->min('planned_date');
        $latest = $doc->items->max('planned_date');
        $this->assertGreaterThan(20, \Carbon\Carbon::parse($earliest)->diffInDays(\Carbon\Carbon::parse($latest)));

        // 4. Verifikasi kepatuhan perpajakan PMK 168/2023:
        // Seluruh upah tukang swakelola (OH <= Rp 450.000) bebas pajak PPh 21 (0% / Rp 0)
        foreach ($upahItems as $upah) {
            $this->assertEquals(0, (float)$upah->tax_pph21, "Upah tukang {$upah->item_name} tidak boleh dipotong PPh 21 karena HOK di bawah ambang Rp 450.000/hari (PMK 168/2023)");
            $this->assertEquals((float)$upah->total_price, (float)$upah->net_amount, "Penerimaan bersih upah tukang {$upah->item_name} harus 100% utuh tanpa potongan");
        }

        // 5. Verifikasi Smart Semantic Matching ke Pos Belanja RAB:
        // Setiap item RPD terhubung secara otomatis ke item RAB proyek (budget_item_id tidak null)
        $itemsWithBudget = $doc->items()->whereNotNull('budget_item_id')->count();
        $this->assertEquals(48, $itemsWithBudget, 'Seluruh 48 item RPD harus terhubung ke pos belanja RAB melalui Smart Semantic Matching');
    }

    public function test_tax_threshold_for_daily_wages_follows_pmk_168_2023()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $service = app(\App\Services\RpdBreakdownService::class);
        $project = \App\Models\RevitalizationProject::first();

        // 1. Upah tukang standar swakelola (Rp 125.000/OH) -> Bebas Pajak (0%)
        $rowNormal = [
            'uraian' => 'Upah Tukang Batu',
            'spesifikasi' => 'Pasangan dinding',
            'volume' => 20,
            'satuan' => 'OH',
            'harga_satuan' => 125000,
            'total' => 2500000,
            'tanggal' => '2026-07-18',
            'kategori' => 'upah',
            'toko' => 'Kelompok Tukang',
            'npwp' => null,
            'metode' => 'Kas Tunai',
        ];
        $breakdownNormal = $service->analyzeAndBreakdownRow($rowNormal, 1, $project);
        $this->assertFalse($breakdownNormal['has_tax']);
        $this->assertEquals(0, $breakdownNormal['tax_pph21']);
        $this->assertEquals(2500000, $breakdownNormal['net_amount']);

        // 2. Upah tenaga harian spesialis > Rp 450.000/hari (misal Rp 600.000/OH) -> Kena TER Harian 0.5%
        $rowSpecialist = [
            'uraian' => 'Upah Tenaga Ahli Pengawas',
            'spesifikasi' => 'Pengawasan struktur',
            'volume' => 5,
            'satuan' => 'OH',
            'harga_satuan' => 600000,
            'total' => 3000000,
            'tanggal' => '2026-07-25',
            'kategori' => 'upah',
            'toko' => 'Konsultan Lepas',
            'npwp' => null,
            'metode' => 'Kas Tunai',
        ];
        $breakdownSpecialist = $service->analyzeAndBreakdownRow($rowSpecialist, 2, $project);
        $this->assertTrue($breakdownSpecialist['has_tax']);
        // TER 0.5% dari Rp 3.000.000 = Rp 15.000
        $this->assertEquals(15000, $breakdownSpecialist['tax_pph21']);
        $this->assertEquals(3000000 - 15000, $breakdownSpecialist['net_amount']);
    }

    public function test_post_rpd_to_bku_automatically_records_incoming_fund_and_cash_withdrawal_at_baseline_date()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $project = \App\Models\RevitalizationProject::first();
        $baselineDate = '2026-08-03';

        // Buat berkas RPD contoh
        $csvContent = "No,Uraian Pekerjaan,Spesifikasi,Volume,Satuan,Harga Satuan,Total,Tahap 1 (70%),Tahap 2 (30%),Jumlah (100%)\n";
        $csvContent .= "1,Semen Portland PCC 50 Kg,SNI Gresik,100,Zak,68000,6800000,6800000,0,6800000\n";
        $csvContent .= "2,Pasir Pasang Kali Sampean,Kualitas Kasar,10,M3,250000,2500000,2500000,0,2500000\n";
        $csvContent .= "3,Upah Tukang Batu,HOK Pasangan,20,OH,125000,2500000,2500000,0,2500000\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'rpd_auto_income_') . '.csv';
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempFile,
            'rpd_auto_income.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->post('/rpd', [
            'school_id' => $project->school_id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap 1',
            'target_column' => 'termin_1',
            'breakdown_mode' => 'detailed',
            'title' => 'RPD Uji Saldo Masuk Otomatis',
            'rpd_file' => $uploadedFile,
            'default_date' => $baselineDate,
        ]);

        $response->assertRedirect();
        @unlink($tempFile);

        $doc = \App\Models\RpdDocument::where('title', 'RPD Uji Saldo Masuk Otomatis')->latest('id')->first();
        $this->assertNotNull($doc);

        // Eksekusi Posting ke BKU
        $postResponse = $this->post("/rpd/{$doc->id}/post-bku");
        $postResponse->assertRedirect();

        // 1. Verifikasi transaksi Saldo Masuk (penerimaan_dana) otomatis dicatat pada tanggal inputan awal (2026-08-03)
        $this->assertDatabaseHas('transactions', [
            'project_id' => $project->id,
            'type' => 'penerimaan_dana',
            'transaction_date' => $baselineDate,
            'amount' => $doc->total_budget,
            'payment_method' => 'bank_transfer',
        ]);

        // 2. Verifikasi transaksi Penarikan Tunai Kas (tarik_tunai) otomatis dicatat pada tanggal inputan awal (2026-08-03)
        $totalCash = $doc->items()->where('payment_method', 'belanja_tunai')->sum('total_price');
        $this->assertDatabaseHas('transactions', [
            'project_id' => $project->id,
            'type' => 'tarik_tunai',
            'transaction_date' => $baselineDate,
            'amount' => $totalCash,
            'payment_method' => 'kas_tunai',
        ]);

        // 3. Verifikasi halaman BKU, BPK, dan BB dapat diakses dan saldonya positif
        $bkuRes = $this->get('/laporan/bku?school_id=' . $project->school_id . '&month=8&year=2026');
        $bkuRes->assertStatus(200);

        $bpkRes = $this->get('/laporan/bpk?school_id=' . $project->school_id . '&month=8&year=2026');
        $bpkRes->assertStatus(200);

        $bbRes = $this->get('/laporan/bb?school_id=' . $project->school_id . '&month=8&year=2026');
        $bbRes->assertStatus(200);
    }

    public function test_upload_rab_excel_auto_maps_budget_items_and_template_download()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        // 1. Verifikasi download template RAB Excel
        $templateRes = $this->get('/rab/template');
        $templateRes->assertStatus(200);
        $this->assertTrue(str_contains($templateRes->headers->get('Content-Disposition') ?? '', 'Template_RAB_Pos_Belanja'));

        // 2. Buat file contoh RAB sesuai format yang diupload user
        $csvContent = "KODE ID,KODE AHSP,URAIAN PEKERJAAN,KUANTITAS,SATUAN,HARGA SATUAN,JUMLAH\n";
        $csvContent .= "100,1,DIVISI 1 PERSIAPAN LAPANGAN/SITE WORK,,,,\n";
        $csvContent .= ",,K3,1,Paket,2000000,2000000\n";
        $csvContent .= ",,Pembuatan Prasasti,1,buah,250000,250000\n";
        $csvContent .= "106,1.6.9,Bongkar rangka atap kayu,260.92,m2,12690,3311074.8\n";
        $csvContent .= "105,1.1.4.2,Pasangan bouwplank,0,m',72188.9,0\n";
        $csvContent .= "200,2,DIVISI 2 PEKERJAAN STRUKTUR,,,,\n";
        $csvContent .= "203,2.1.1.1,Pemasangan Atap Pelana Rangka Atap Baja Ringan,260.92,m2,293133.03,76483270.19\n";
        $csvContent .= ",,,,,TOTAL BIAYA,82044344.99\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'rab_upload_') . '.csv';
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempFile,
            'contoh_rab_proyek.csv',
            'text/csv',
            null,
            true
        );

        $school = School::first();
        $project = \App\Models\RevitalizationProject::create([
            'school_id' => $school->id,
            'fiscal_year' => 2026,
            'funding_source' => 'DAK Fisik Bidang Pendidikan TA 2026',
            'title' => 'Proyek Uji Import RAB Terisolasi',
            'spk_number' => 'SPK/TEST/RAB/2026',
            'spk_date' => '2026-01-01',
            'contract_amount' => 100000000,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'pelaksanaan',
        ]);

        // 3. Upload & Import RAB dengan target menu
        $response = $this->post('/rab/import', [
            'project_id' => $project->id,
            'menu_pekerjaan' => 'Rehabilitasi Ruang Kelas',
            'rab_file' => $uploadedFile,
            'mode' => 'replace',
            'skip_zero_volume' => 1,
        ]);

        $response->assertRedirect(route('rab.index', [
            'project_id' => $project->id,
            'menu_pekerjaan' => 'Rehabilitasi Ruang Kelas',
        ]));
        @unlink($tempFile);

        // 4. Verifikasi item tersimpan dengan menu_pekerjaan dan baris volume 0 diabaikan
        $this->assertDatabaseHas('budget_items', [
            'project_id' => $project->id,
            'menu_pekerjaan' => 'Rehabilitasi Ruang Kelas',
            'name' => 'K3',
            'category' => 'Honor & Operasional',
            'volume' => 1,
            'total_price' => 2000000,
        ]);

        $this->assertDatabaseHas('budget_items', [
            'project_id' => $project->id,
            'menu_pekerjaan' => 'Rehabilitasi Ruang Kelas',
            'name' => 'Bongkar rangka atap kayu',
            'code' => '1.6.9',
            'category' => 'Upah Tenaga Kerja',
        ]);

        $this->assertDatabaseHas('budget_items', [
            'project_id' => $project->id,
            'menu_pekerjaan' => 'Rehabilitasi Ruang Kelas',
            'name' => 'Pemasangan Atap Pelana Rangka Atap Baja Ringan',
            'code' => '2.1.1.1',
            'category' => 'Material/Bahan',
        ]);

        // Volume 0 tidak diimpor
        $this->assertDatabaseMissing('budget_items', [
            'project_id' => $project->id,
            'name' => 'Pasangan bouwplank',
        ]);

        // 5. Verifikasi halaman RAB menampilkan grup Menu Pekerjaan dan item-item yang baru diimpor
        $rabIndexRes = $this->get('/rab?project_id=' . $project->id . '&menu_pekerjaan=all');
        $rabIndexRes->assertStatus(200);
        $rabIndexRes->assertSee('Rehabilitasi Ruang Kelas');
        $rabIndexRes->assertSee('Bongkar rangka atap kayu');
        $rabIndexRes->assertSee('Pemasangan Atap Pelana Rangka Atap Baja Ringan');

        // Bersihkan data uji coba agar tidak mengotori database
        $project->budgetItems()->delete();
        $project->delete();
    }

    public function test_update_rab_item()
    {
        $root = User::where('username', 'root')->first();
        $this->actingAs($root);

        $project = \App\Models\RevitalizationProject::first();
        $item = \App\Models\BudgetItem::create([
            'project_id' => $project->id,
            'menu_pekerjaan' => 'Rehabilitasi Ruang Kelas',
            'category' => 'Material/Bahan',
            'name' => 'Initial Name For Test',
            'volume' => 1,
            'unit' => 'ls',
            'unit_price' => 50000,
            'total_price' => 50000,
        ]);

        $newName = 'Updated Name By Test';

        $res = $this->put('/rab/' . $item->id, [
            'menu_pekerjaan' => $item->menu_pekerjaan,
            'code' => $item->code,
            'category' => $item->category,
            'name' => $newName,
            'volume' => 5,
            'unit' => $item->unit,
            'unit_price' => 100000,
            'notes' => 'Catatan test update',
        ]);

        $res->assertSessionHas('success');
        $item->refresh();

        $this->assertEquals($newName, $item->name);
        $this->assertEquals(5, (float)$item->volume);
        $this->assertEquals(500000, (float)$item->total_price);

        $item->delete();
    }

    public function test_delete_rab_item()
    {
        $root = User::where('username', 'root')->first();
        $this->actingAs($root);

        $project = \App\Models\RevitalizationProject::first();
        $item = \App\Models\BudgetItem::create([
            'project_id' => $project->id,
            'menu_pekerjaan' => 'Rehabilitasi Ruang Kelas',
            'category' => 'Material/Bahan',
            'name' => 'To Be Deleted',
            'volume' => 1,
            'unit' => 'ls',
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        $res = $this->delete('/rab/' . $item->id);
        $res->assertSessionHas('success');

        $this->assertDatabaseMissing('budget_items', ['id' => $item->id]);
    }



    public function test_upload_rpd_new_official_format_with_mobilisasi_and_non_fisik_maps_accurately()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $project = \App\Models\RevitalizationProject::first();

        $rawCsv = "NO,URAIAN,,,,,,,,,,,,,,,,VOL,SAT,HARGA SATUAN (Rp.),Koef Ruang,Koef Kerusakan Rehab,JUMLAH ANGGARAN (Rp.),,,KETERANGAN\n" .
                  ",,,,,,,,,,,,,,,,,,,,,,Termin 1,Termin 2,JUMLAH,\n" .
                  ",,,,,,,,,,,,,,,,,,,,,,0.7,0.3,1,\n" .
                  "I,PEKERJAAN FISIK,,,,,,,,,,,,,,,,,,,,,,,,\n" .
                  ",A,PEMBANGUNAN BARU ,,,,,,,,,,,,,,,,,,,,,,,\n" .
                  ",1,-,PEKERJAAN PERSIAPAN DAN RK3K,,,,(,8,m,x,7,m,),x,1,ruang,1,Ls,0,1,,,,0,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,,,,,,,\n" .
                  ",1,-,Toilet 1,,,,(,,m,x,,m,),x,1,ruang,28,m²,0,1,,,,0,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",2,-,TOILET,,,,(,,m,x,,m,),x,1,ruang,30,m²,3166666.6666666665,1,,,,,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",3,-,,,,,(,6,m,x,4,m,),x,1,ruang,30,m²,,1,,,,,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",4,-,,,,,,,,,,,,,,,30,m²,,,,,,,\n" .
                  ",,, - Belanja Bahan,,,,,,,,,,,,,,,,0,0.5,,0,0,0,\n" .
                  ",,, - Belanja Upah,,,,,,,,,,,,,,,,0,0.5,,0,0,0,\n" .
                  ",,,,,,,,,,,,,,,,,,,,,,,,,\n" .
                  ",A,Rehabilitasi,,,,,,,,,,,,,,,,,,,,,,,\n" .
                  ",,-,Ruang Kelas ,,,,(,8,m,x,7,m,),x,1,ruang,172.06515000000002,m²,2005889,1,0.39,,,,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,231000914,0.5,,161700639.79999998,69300274.2,231000914,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,94352486,0.5,,66046740.199999996,28305745.8,94352486,\n" .
                  ",,-,Perpustakaan,,,,(,,m,x,,m,),x,1,ruang,54.6,m²,2666666.6666666665,1,,,,,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,71903090,0.5,,50332163,21570927,71903090,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,30815610,0.5,,21570927,9244683,30815610,\n" .
                  ",,-,XXXXXX,,,,(,6,m,x,4,m,),x,1,ruang,24,m²,0,1,0.44,,,,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,,0.5,,,,,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,,0.5,,,,,\n" .
                  ",,-,XXXXXX,,,,(,,m,x,,m,),x,1,ruang,0,m²,,1,,,,,\n" .
                  ",,,,,,,(,,m,x,,m,),x,1,ruang,0,m²,,0.5,,,,,\n" .
                  ",,-,Rumah Dinas,,,,(,,m,x,,m,),x,1,ruang,60,m²,0,1,,,,,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",,-,Toilet - 1,,,,(,,m,x,,m,),x,1,ruang,7.65,m²,Sub Total,1,,,,,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,13771940,0.5,,9640358,4131582,13771940,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,5902260,0.5,,4131581.9999999995,1770678,5902260,\n" .
                  ",B,Bangun Baru,,,,,,,,,,,,,,,,,,,,,,,\n" .
                  ",,-,Toilet - 2,,,,(,,m,x,,m,),x,1,ruang,28,m²,0,1,,,,,\n" .
                  ",,, - Belanja Bahan,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",,, - Belanja Upah,,,,(,8,m,x,2,m,),x,1,ruang,,,0,0.5,,0,0,0,\n" .
                  ",,-,Belanja Mobilisasi,,,,,,,,,,,,,,,,44774631,,,44774631,,44774631,\n" .
                  ",,,,,,,,,,,,,,,,,,,Sub Total IB,,,358197041,134323890,492520931,\n" .
                  "II,PEKERJAAN INTERIOR DAN PERABOT,,,,,,,,,,,,,,,,,,,,,,,,\n" .
                  ",1,- ,,,,,,,,,,,,,,,0,Ls,0,,,0,0,0,\n" .
                  ",,- ,,,,,,,,,,,,,,,0,Ls,0,,,0,0,0,\n" .
                  ",,,,,,,,,,,,,,,,,,,Sub Total II,,,0,0,0,\n" .
                  "III,PERENCANAAN DAN PENGAWASAN,,,,,,,,,,,,,,,,,,,,,,,,\n" .
                  ",A,Biaya Perencanaan,,,,,,,,,,,,,,,1,Ls,9661408,,,9661408,,9661408,\n" .
                  ",B,Biaya Pengawasan,,,,,,,,,,,,,,,1,Ls,11479183,,,0,11479183,11479183,\n" .
                  ",C,Biaya Pengelolaan ,,,,,,,,,,,,,,,1,Ls,12551203,,,12551203,0,12551203,\n" .
                  ",,,,,,,,,,,,,,,,,,,Sub Total III,,,22212611,11479183,33691794,\n" .
                  "TOTAL  (I + II + III ),,,,,,,,,,,,,,,,,,,,,,380409652,145803073,526212725,\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'rpd_new_format_') . '.csv';
        file_put_contents($tempFile, $rawCsv);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempFile,
            'RPD_Format_Baru_Dinas.csv',
            'text/csv',
            null,
            true
        );

        \App\Models\RpdDocument::where('title', 'RPD Format Baru Dinas T1 Test')->delete();

        // 1. Uji Upload Termin 1 (70%)
        $response = $this->post('/rpd', [
            'school_id' => $project->school_id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap 1',
            'target_column' => 'termin_1',
            'breakdown_mode' => 'detailed',
            'title' => 'RPD Format Baru Dinas T1 Test',
            'rpd_file' => $uploadedFile,
            'default_date' => '2026-07-13',
        ]);

        $response->assertRedirect();

        $docT1 = \App\Models\RpdDocument::where('title', 'RPD Format Baru Dinas T1 Test')->latest('id')->first();
        $this->assertNotNull($docT1);

        // 58 item: 48 terurai (atap 1 paket sekaligus) + 4 Belanja Mobilisasi (Ongkos Angkut Pengiriman Material) + 1 Perencanaan + 5 Biaya Pengelolaan P2SP
        $this->assertEquals(58, $docT1->items()->count());
        $this->assertEquals(380409652, (int)$docT1->total_budget);

        // Seluruh 58 item terhubung ke pos belanja RAB
        $this->assertEquals(58, $docT1->items()->whereNotNull('budget_item_id')->count());
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $docT1->id,
            'category' => 'bahan',
            'item_name' => 'Pengadaan Bahan Atap (Rangka Baja Ringan Galvalum & Genteng/Spandek) - Ruang Kelas',
        ]);

        // Verifikasi dekomposisi Belanja Mobilisasi menjadi biaya angkut/pengiriman bahan material (Total: Rp 44.774.631)
        $mobilisasiItems = $docT1->items()->where('category', 'alat')->get();
        $this->assertCount(4, $mobilisasiItems);
        $this->assertEquals(44774631, (int)$mobilisasiItems->sum('total_price'));
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $docT1->id,
            'item_name' => 'Ongkos Angkut & Pengiriman Bahan Material Pondasi & Struktur Beton',
            'category' => 'alat',
        ]);

        // Verifikasi Biaya Perencanaan (Rp 9.661.408)
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $docT1->id,
            'item_name' => 'Biaya Perencanaan',
            'total_price' => 9661408,
            'category' => 'operasional',
        ]);

        // Verifikasi dekomposisi Biaya Pengelolaan P2SP menjadi pos riil operasional auditable (Total: Rp 12.551.203)
        $pengelolaanItems = $docT1->items()->where('category', 'operasional')->where('item_name', '!=', 'Biaya Perencanaan')->get();
        $this->assertCount(5, $pengelolaanItems);
        $this->assertEquals(12551203, (int)$pengelolaanItems->sum('total_price'));
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $docT1->id,
            'item_name' => 'Pengadaan ATK, Format Isian BKU, & Penggandaan Dokumen SPJ Revitalisasi',
            'category' => 'operasional',
        ]);
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $docT1->id,
            'item_name' => 'Konsumsi Rapat Koordinasi Tim P2SP, Komite Sekolah, & Tokoh Masyarakat',
            'category' => 'operasional',
        ]);
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $docT1->id,
            'item_name' => 'Biaya Transportasi Tim P2SP (Konsultasi Teknis Dinas Pendidikan & Transaksi Bank)',
            'category' => 'operasional',
        ]);
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $docT1->id,
            'item_name' => 'Spanduk Publikasi Revitalisasi & Papan Informasi Transparansi Swakelola',
            'category' => 'operasional',
        ]);
        $this->assertDatabaseHas('rpd_items', [
            'rpd_document_id' => $docT1->id,
            'item_name' => 'Pengadaan Bea Materai Administrasi Kuitansi & Berita Acara SPJ (Materai 10.000)',
            'category' => 'operasional',
        ]);

        // Bersihkan
        $docT1->items()->delete();
        $docT1->delete();
        @unlink($tempFile);
    }

    public function test_rpd_deduplication_updates_identical_items_and_posts_new_items_selectively()
    {
        $root = User::where('username', 'root')->first();
        $this->actingAs($root);

        $school = School::first();
        $project = \App\Models\RevitalizationProject::where('school_id', $school->id)->first();

        // 1. Unggah berkas RPD versi awal dengan 2 item
        $csvContent1 = "No,Kode Rekening,Kategori,Uraian Barang / Kegiatan / Bahan,Spesifikasi,Volume,Satuan,Harga Satuan,Total Biaya,Tanggal Rencana Belanja,Toko / Rekanan,Metode Bayar\n" .
                       "1,5.1.02.01,Bahan,Semen Gresik 50 Kg,SNI PCC Type 1,100,Zak,68000,6800000,2026-08-01,Toko Bangunan Berkah Situbondo,Transfer Bank\n" .
                       "2,5.1.02.02,Upah,Upah Tukang Batu,HOK Tukang,20,OH,120000,2400000,2026-08-02,Kelompok Tukang Pak Slamet,Kas Tunai\n";

        $tempFile1 = tempnam(sys_get_temp_dir(), 'rpd_test_v1_') . '.csv';
        file_put_contents($tempFile1, $csvContent1);
        $file1 = new \Illuminate\Http\UploadedFile($tempFile1, 'rpd_v1.csv', 'text/csv', null, true);

        // Hapus dokumen lama jika ada
        \App\Models\RpdDocument::where('project_id', $project->id)->where('term_stage', 'Tahap Deduplikasi Test')->delete();

        $this->post('/rpd', [
            'school_id' => $school->id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap Deduplikasi Test',
            'title' => 'RPD Deduplikasi Test',
            'rpd_file' => $file1,
            'default_date' => '2026-08-01',
        ]);

        $doc = \App\Models\RpdDocument::where('project_id', $project->id)->where('term_stage', 'Tahap Deduplikasi Test')->first();
        $this->assertNotNull($doc);
        $this->assertEquals(2, $doc->items()->count());
        $this->assertEquals(2, $doc->items()->where('is_posted', false)->count());

        // 2. Posting seluruh item awal ke BKU
        $this->post("/rpd/{$doc->id}/post-bku")->assertRedirect();

        $doc->refresh();
        $this->assertEquals(2, $doc->items()->where('is_posted', true)->count());
        $this->assertEquals(0, $doc->items()->where('is_posted', false)->count());
        $this->assertEquals('posted_to_bku', $doc->status);

        // Hitung transaksi sebelum re-upload
        $initialTrxCount = Transaction::where('project_id', $project->id)->count();

        // 3. Re-upload berkas RPD versi baru:
        // - Item Semen Gresik identik tapi ada perubahan volume (120 Zak) -> HARUS DIUPDATE, BUKAN DITAMBAHKAN!
        // - Item Upah Tukang identik -> HARUS DIUPDATE, BUKAN DITAMBAHKAN!
        // - Item BARU: Pasir Pasang Kali Sampean -> HARUS DITAMBAHKAN DENGAN is_posted = false!
        $csvContent2 = "No,Kode Rekening,Kategori,Uraian Barang / Kegiatan / Bahan,Spesifikasi,Volume,Satuan,Harga Satuan,Total Biaya,Tanggal Rencana Belanja,Toko / Rekanan,Metode Bayar\n" .
                       "1,5.1.02.01,Bahan,Semen Gresik 50 Kg,SNI PCC Type 1,120,Zak,68000,8160000,2026-08-01,Toko Bangunan Berkah Situbondo,Transfer Bank\n" .
                       "2,5.1.02.02,Upah,Upah Tukang Batu,HOK Tukang,20,OH,120000,2400000,2026-08-02,Kelompok Tukang Pak Slamet,Kas Tunai\n" .
                       "3,5.1.02.01,Bahan,Pasir Pasang Kali Sampean,Kualitas Kasar,10,M3,250000,2500000,2026-08-03,Pemasok Pasir Situbondo,Kas Tunai\n";

        $tempFile2 = tempnam(sys_get_temp_dir(), 'rpd_test_v2_') . '.csv';
        file_put_contents($tempFile2, $csvContent2);
        $file2 = new \Illuminate\Http\UploadedFile($tempFile2, 'rpd_v2.csv', 'text/csv', null, true);

        $this->post('/rpd', [
            'school_id' => $school->id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap Deduplikasi Test',
            'title' => 'RPD Deduplikasi Test',
            'rpd_file' => $file2,
            'default_date' => '2026-08-01',
        ]);

        $doc->refresh();

        // Total item harus 3 (bukan 5 karena item identik di-update!)
        $this->assertEquals(3, $doc->items()->count());

        // Semen Gresik terupdate volumenya menjadi 120 dan total_price menjadi 8.160.000
        $semenItem = $doc->items()->where('item_name', 'Semen Gresik 50 Kg')->first();
        $this->assertNotNull($semenItem);
        $this->assertEquals(120, (int)$semenItem->volume);
        $this->assertEquals(8160000, (int)$semenItem->total_price);
        $this->assertTrue($semenItem->is_posted); // Status posted lama dipertahankan!

        // Item baru (Pasir Pasang) berstatus belum diposting (is_posted = false)
        $pasirItem = $doc->items()->where('item_name', 'Pasir Pasang Kali Sampean')->first();
        $this->assertNotNull($pasirItem);
        $this->assertFalse($pasirItem->is_posted);

        // Status dokumen kembali menjadi 'analyzed' karena ada item baru yang belum diposting
        $this->assertEquals('analyzed', $doc->status);

        // 4. Verifikasi Halaman Tampilan Output RPD
        $viewResponse = $this->get("/rpd/{$doc->id}");
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Posting 1 Item Baru ke BKU');
        $viewResponse->assertSee('Ditemukan 1 Item Baru yang Belum Diposting ke BKU');

        // 5. Eksekusi Posting ke BKU untuk item baru tersebut
        $postResponse = $this->post("/rpd/{$doc->id}/post-bku");
        $postResponse->assertRedirect();

        $doc->refresh();
        $this->assertEquals(0, $doc->items()->where('is_posted', false)->count());
        $this->assertEquals(3, $doc->items()->where('is_posted', true)->count());
        $this->assertEquals('posted_to_bku', $doc->status);

        // Pastikan transaksi BKU bertambah tepat 1 untuk item Pasir Pasang (tidak menduplikasi item lama)
        $this->assertEquals($initialTrxCount + 1, Transaction::where('project_id', $project->id)->count());

        // Bersihkan data tes
        Transaction::where('project_id', $project->id)->where('description', 'like', '%Tahap Deduplikasi Test%')->delete();
        Transaction::where('project_id', $project->id)->where('notes', 'like', "%RPD #{$doc->id}%")->delete();
        $doc->items()->delete();
        $doc->delete();
        @unlink($tempFile1);
        @unlink($tempFile2);
    }

    public function test_upload_rpd_with_optional_store_defaults_to_dots_for_manual_filling()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $school = School::first();
        $project = RevitalizationProject::where('school_id', $school->id)->first();

        // 1. Tes upload RPD dengan default_store dikosongkan (empty string)
        $csvContentEmptyStore = "No,Kode Akun,Kategori,Uraian Pekerjaan,Spesifikasi,Volume,Satuan,Harga Satuan,Jumlah Total,Tanggal,Toko,Metode\n" .
            "1,5.1.02.01,Bahan,Semen Gresik Test Empty Store,SNI PCC 50kg,50,Zak,68000,3400000,2026-08-10,,Transfer Bank\n";

        $tempFileEmpty = tempnam(sys_get_temp_dir(), 'rpd_test_empty_store_') . '.csv';
        file_put_contents($tempFileEmpty, $csvContentEmptyStore);
        $fileEmpty = new \Illuminate\Http\UploadedFile($tempFileEmpty, 'rpd_empty_store.csv', 'text/csv', null, true);

        $titleEmpty = 'RPD Toko Kosong Titik Titik Test ' . uniqid();
        $response = $this->post('/rpd', [
            'school_id' => $school->id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap 1',
            'target_column' => 'total',
            'breakdown_mode' => 'summary',
            'title' => $titleEmpty,
            'default_date' => '2026-08-10',
            'default_store' => '', // Dikosongkan oleh pengguna
            'rpd_file' => $fileEmpty,
        ]);

        $response->assertRedirect();
        $doc = RpdDocument::where('title', $titleEmpty)->first();
        $this->assertNotNull($doc);

        $itemEmpty = $doc->items()->where('item_name', 'Semen Gresik Test Empty Store')->first();
        $this->assertNotNull($itemEmpty);
        // Memastikan supplier_name otomatis diisi titik-titik (....................)
        $this->assertEquals('....................', $itemEmpty->supplier_name);
        $this->assertStringContainsString('....................', $itemEmpty->bku_description);

        // 2. Tes update item via items.update dengan supplier_name dikosongkan
        $updateResp = $this->put("/rpd/items/{$itemEmpty->id}", [
            'category' => 'bahan',
            'item_name' => 'Semen Gresik Test Empty Store Edit',
            'volume' => 50,
            'unit' => 'Zak',
            'unit_price' => 68000,
            'planned_date' => '2026-08-10',
            'supplier_name' => '', // kosong
            'payment_method' => 'belanja_transfer',
        ]);
        $updateResp->assertRedirect();
        $itemEmpty->refresh();
        $this->assertEquals('....................', $itemEmpty->supplier_name);

        // 3. Tes update item dengan nama toko riil
        $updateResp2 = $this->put("/rpd/items/{$itemEmpty->id}", [
            'category' => 'bahan',
            'item_name' => 'Semen Gresik Test Empty Store Edit',
            'volume' => 50,
            'unit' => 'Zak',
            'unit_price' => 68000,
            'planned_date' => '2026-08-10',
            'supplier_name' => 'Toko Bangunan Sumber Urip',
            'payment_method' => 'belanja_transfer',
        ]);
        $updateResp2->assertRedirect();
        $itemEmpty->refresh();
        $this->assertEquals('Toko Bangunan Sumber Urip', $itemEmpty->supplier_name);

        // Bersihkan data tes
        $doc->items()->delete();
        $doc->delete();
        @unlink($tempFileEmpty);
    }

    public function test_posted_rpd_can_be_unposted_and_deleted_with_transactions()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $school = School::first();
        $project = RevitalizationProject::where('school_id', $school->id)->first();

        // 1. Buat Dokumen RPD baru dengan 2 item
        $csvData = "No,Kode Akun,Kategori,Uraian Pekerjaan,Spesifikasi,Volume,Satuan,Harga Satuan,Jumlah Total,Tanggal,Toko,Metode\n" .
            "1,5.1.02.01,Bahan,Semen Uji Hapus Posted,SNI PCC 50kg,10,Zak,68000,680000,2026-09-01,Toko Berkah,Transfer Bank\n" .
            "2,5.1.02.02,Upah,Upah Tukang Uji Hapus,HOK Tukang,5,OH,120000,600000,2026-09-05,Kelompok Tukang,Kas Tunai\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'rpd_test_unpost_') . '.csv';
        file_put_contents($tempFile, $csvData);
        $file = new \Illuminate\Http\UploadedFile($tempFile, 'rpd_test_unpost.csv', 'text/csv', null, true);

        $title = 'RPD Uji Hapus Posted ' . uniqid();
        $this->post('/rpd', [
            'school_id' => $school->id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap Uji Hapus',
            'title' => $title,
            'rpd_file' => $file,
            'default_date' => '2026-09-01',
        ])->assertRedirect();

        $doc = RpdDocument::where('title', $title)->first();
        $this->assertNotNull($doc);
        $this->assertEquals(2, $doc->items()->count());

        // 2. Post ke BKU
        $this->post("/rpd/{$doc->id}/post-bku")->assertRedirect();
        $doc->refresh();
        $this->assertEquals('posted_to_bku', $doc->status);
        $this->assertEquals(2, $doc->items()->where('is_posted', true)->count());

        // Verifikasi transaksi terbentuk di database BKU (2 item belanja + saldo masuk SP2D + tarik tunai kas = 4 transaksi)
        $txCount = Transaction::where('project_id', $project->id)
            ->where(function ($q) use ($doc) {
                $q->where('notes', 'like', "%RPD #{$doc->id}%")
                  ->orWhere('notes', 'like', "%RPD: {$doc->title}%")
                  ->orWhere('notes', 'like', "%Pecah Bahan RPD: {$doc->title}%");
            })->count();
        $this->assertGreaterThanOrEqual(2, $txCount);

        // 3. Tes Fitur UNPOST (Batalkan Posting BKU tanpa hapus dokumen)
        $this->post("/rpd/{$doc->id}/unpost")->assertRedirect();
        $doc->refresh();
        $this->assertEquals('analyzed', $doc->status);
        $this->assertNull($doc->posted_at);
        $this->assertEquals(0, $doc->items()->where('is_posted', true)->count());
        $this->assertEquals(2, $doc->items()->where('is_posted', false)->count());

        // Verifikasi seluruh transaksi di BKU yang terkait telah terhapus
        $txCountAfterUnpost = Transaction::where('project_id', $project->id)
            ->where(function ($q) use ($doc) {
                $q->where('notes', 'like', "%RPD #{$doc->id}%")
                  ->orWhere('notes', 'like', "%RPD: {$doc->title}%")
                  ->orWhere('notes', 'like', "%Pecah Bahan RPD: {$doc->title}%");
            })->count();
        $this->assertEquals(0, $txCountAfterUnpost);

        // 4. Posting kembali ke BKU untuk menguji fitur HAPUS RPD YANG SUDAH DIPOSTING
        $this->post("/rpd/{$doc->id}/post-bku")->assertRedirect();
        $doc->refresh();
        $this->assertEquals('posted_to_bku', $doc->status);

        // 5. Hapus RPD yang sedang berstatus 'posted_to_bku'
        $delResp = $this->delete("/rpd/{$doc->id}");
        $delResp->assertRedirect(route('rpd.index'));
        $delResp->assertSessionHas('success');

        // Verifikasi dokumen dan item RPD telah terhapus
        $this->assertDatabaseMissing('rpd_documents', ['id' => $doc->id]);
        $this->assertDatabaseMissing('rpd_items', ['rpd_document_id' => $doc->id]);

        // Verifikasi seluruh transaksi di BKU yang berkaitan juga otomatis ikut terhapus bersih
        $txCountAfterDelete = Transaction::where('project_id', $project->id)
            ->where(function ($q) use ($doc) {
                $q->where('notes', 'like', "%RPD #{$doc->id}%")
                  ->orWhere('notes', 'like', "%RPD: {$doc->title}%")
                  ->orWhere('notes', 'like', "%Pecah Bahan RPD: {$doc->title}%");
            })->count();
        $this->assertEquals(0, $txCountAfterDelete);

        @unlink($tempFile);
    }

    public function test_ai_variance_enhances_rpd_breakdown_items()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $school = School::first();
        $project = RevitalizationProject::where('school_id', $school->id)->first();

        // 1. Unggah RPD dengan opsi use_ai = 1
        $csvContent = "No,Kode Akun,Kategori,Uraian Pekerjaan,Spesifikasi,Volume,Satuan,Harga Satuan,Jumlah Total,Tanggal,Toko,Metode\n" .
            "1,5.1.02.01,Bahan,Semen Gresik 50 Kg,SNI PCC Type 1,50,Zak,68000,3400000,2026-08-03,,Transfer Bank\n" .
            "2,5.1.02.01,Bahan,Pasir Pasang Kali Sampean,Kasar Bersih,5,M3,250000,1250000,2026-08-03,,Kas Tunai\n" .
            "3,5.1.02.01,Bahan,Cat Tembok Weathershield,Putih 20L,2,Pail,1450000,2900000,2026-08-03,,Transfer Bank\n" .
            "4,5.1.02.02,Upah,Upah Tukang Batu,HOK Tukang,15,OH,120000,1800000,2026-08-03,,Kas Tunai\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'rpd_test_ai_') . '.csv';
        file_put_contents($tempFile, $csvContent);
        $file = new \Illuminate\Http\UploadedFile($tempFile, 'rpd_ai_test.csv', 'text/csv', null, true);

        $title = 'RPD Uji Variasi AI ' . uniqid();
        $response = $this->post('/rpd', [
            'school_id' => $school->id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap AI Test',
            'title' => $title,
            'rpd_file' => $file,
            'default_date' => '2026-08-03',
            'use_ai' => 1,
        ]);

        $response->assertRedirect();
        $doc = RpdDocument::where('title', $title)->first();
        $this->assertNotNull($doc);
        $this->assertEquals(4, $doc->items()->count());

        // Verifikasi tidak ada transaksi di hari Minggu
        foreach ($doc->items as $it) {
            $this->assertFalse(\Carbon\Carbon::parse($it->planned_date)->isSunday(), 'Tidak boleh ada transaksi BKU di hari Minggu');
        }

        // Verifikasi item upah jatuh pada hari Sabtu
        $upahItem = $doc->items->firstWhere('category', 'upah');
        $this->assertNotNull($upahItem);
        $this->assertTrue(\Carbon\Carbon::parse($upahItem->planned_date)->isSaturday(), 'Upah tukang harus dijadwalkan pada hari Sabtu');

        // Verifikasi toko otomatis disarankan di Situbondo (bukan kosong)
        $this->assertNotEmpty($upahItem->supplier_name);
        $this->assertNotEquals('....................', $upahItem->supplier_name);

        // 2. Uji endpoint manual AI enhance (/rpd/{id}/ai-enhance)
        $aiEnhanceResp = $this->post("/rpd/{$doc->id}/ai-enhance", [
            'overwrite_supplier' => 1,
        ]);
        $aiEnhanceResp->assertRedirect();
        $aiEnhanceResp->assertSessionHas('success');

        // Bersihkan data tes
        $doc->items()->delete();
        $doc->delete();
        @unlink($tempFile);
    }

    public function test_tax_is_default_deposited_with_ai_generated_ntpn_and_synchronized_date()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $gemini = app(\App\Services\GeminiRpdService::class);

        // 1. Uji Mesin Generator NTPN (Harus 16 karakter alfanumerik kapital resmi DJP)
        $ntpn = $gemini->generateNtpn();
        $this->assertEquals(16, strlen($ntpn));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{16}$/', $ntpn);

        // 2. Uji Penanggalan Pajak Cerdas (Sinkron dengan transaksi, tidak hari Minggu)
        $wednesday = '2026-08-05'; // Rabu
        $taxWed = $gemini->calculateTaxPaymentDate($wednesday);
        $this->assertTrue(in_array($taxWed, ['2026-08-05', '2026-08-06']));
        $this->assertFalse(\Carbon\Carbon::parse($taxWed)->isSunday());

        $saturday = '2026-08-08'; // Sabtu
        $taxSat = $gemini->calculateTaxPaymentDate($saturday, 'upah');
        $this->assertTrue(in_array($taxSat, ['2026-08-08', '2026-08-10']));
        $this->assertFalse(\Carbon\Carbon::parse($taxSat)->isSunday(), 'Tanggal penyetoran pajak dilarang hari Minggu');

        // 3. Uji Manual Transaction Store: Pajak Otomatis 'disetor' lengkap dengan NTPN & Tanggal Sinkron
        $school = School::first();
        $project = RevitalizationProject::where('school_id', $school->id)->first();
        $trxNumber = 'BKU-TEST-TAX/' . uniqid();

        $storeResp = $this->post('/transactions', [
            'school_id' => $school->id,
            'project_id' => $project->id,
            'transaction_number' => $trxNumber,
            'transaction_date' => '2026-08-10',
            'type' => 'belanja_tunai',
            'payment_method' => 'kas_tunai',
            'description' => 'Pembelian semen PCC untuk renovasi kelas',
            'amount' => 3000000,
            'has_tax' => 1,
            'tax_type' => 'PPN & PPh 22',
            'tax_ppn' => 300000,
            'tax_pph22' => 45000,
            // tax_ntpn dan tax_payment_date sengaja dikosongkan untuk menguji auto-generate default
        ]);
        $storeResp->assertRedirect();

        $savedTx = Transaction::where('transaction_number', $trxNumber)->first();
        $this->assertNotNull($savedTx);
        $this->assertTrue($savedTx->has_tax);
        $this->assertEquals('disetor', $savedTx->tax_status, 'Status pajak harus default disetor');
        $this->assertNotEmpty($savedTx->tax_ntpn, 'Nomor NTPN harus otomatis digenerate');
        $this->assertEquals(16, strlen($savedTx->tax_ntpn));
        $this->assertNotNull($savedTx->tax_payment_date);
        $this->assertTrue(in_array($savedTx->tax_payment_date->format('Y-m-d'), ['2026-08-10', '2026-08-11']));

        // 4. Uji Route Massal Auto Setor Pajak Buku Pembantu Pajak (/laporan/bp/auto-setor)
        $autoSetorResp = $this->post('/laporan/bp/auto-setor', [
            'school_id' => $school->id,
            'year' => 2026,
            'month' => 'all',
        ]);
        $autoSetorResp->assertRedirect();
        $autoSetorResp->assertSessionHas('success');

        // 5. Uji Akses Halaman Laporan Buku Pembantu Pajak (BP)
        $bpResp = $this->get('/laporan/bp?school_id=' . $school->id . '&year=2026');
        $bpResp->assertStatus(200);
        $bpResp->assertSee('Setor Otomatis Pajak AI');

        // 6. Uji Fitur Audit Kepatuhan Keuangan AI (/laporan/ai-audit)
        $auditResp = $this->get('/laporan/ai-audit?school_id=' . $school->id . '&year=2026');
        $auditResp->assertStatus(200);
        $auditResp->assertSee('Skor Kepatuhan SPJ');

        // 7. Uji AJAX Endpoint AI Description & AI NTPN
        $aiDescResp = $this->postJson('/transactions/ai/generate-description', [
            'category' => 'bahan',
            'item_name' => 'Semen Gresik 50 Kg',
            'volume' => 50,
            'unit' => 'Zak',
            'supplier' => 'UD Berkah Situbondo',
            'project_title' => 'Revitalisasi Sekolah',
        ]);
        $aiDescResp->assertStatus(200);
        $this->assertTrue($aiDescResp->json('success'));
        $this->assertNotEmpty($aiDescResp->json('description'));

        $aiNtpnResp = $this->getJson('/transactions/ai/generate-ntpn?transaction_date=2026-08-10');
        $aiNtpnResp->assertStatus(200);
        $this->assertTrue($aiNtpnResp->json('success'));
        $this->assertEquals(16, strlen($aiNtpnResp->json('ntpn')));

        // Bersihkan data tes
        $savedTx->delete();
    }

    /**
     * Uji pemecahan otomatis biaya mobilisasi (menjadi ongkos kirim bahan toko -> sekolah)
     * dan biaya pengelolaan (menjadi operasional tim P2SP anti-temuan BPK) menggunakan AI.
     */
    public function test_ai_breakdown_mobilisasi_and_pengelolaan_auto_decompose_with_zero_variance()
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin);

        $project = \App\Models\RevitalizationProject::first();
        $breakdownService = app(\App\Services\RpdBreakdownService::class);

        // 1. Uji dekomposisi Belanja Mobilisasi secara langsung (misal pagu Rp 30.000.000)
        $mobPagu = 30000000;
        $mobItems = $breakdownService->decomposeBelanjaMobilisasi($mobPagu, 'Ruang Kelas', [
            'project' => $project,
            'default_date' => '2026-08-01',
        ]);

        $this->assertCount(4, $mobItems);
        $this->assertEquals($mobPagu, (int)array_sum(array_column($mobItems, 'total')));
        $mobNames = array_column($mobItems, 'uraian');
        $this->assertContains('Ongkos Angkut & Pengiriman Bahan Material Pondasi & Struktur Beton', $mobNames);
        $this->assertContains('Ongkos Angkut & Pengiriman Bahan Material Dinding, Kusen & Rangka Atap', $mobNames);
        $this->assertContains('Ongkos Angkut & Pengiriman Bahan Material Lantai, Finishing & Sanitair', $mobNames);
        $this->assertContains('Mobilisasi & Demobilisasi Peralatan Kerja (Mesin Molen & Scaffolding)', $mobNames);

        // 2. Uji dekomposisi Biaya Pengelolaan secara langsung (misal pagu Rp 15.000.000)
        $pengPagu = 15000000;
        $pengItems = $breakdownService->decomposeBiayaPengelolaan($pengPagu, [
            'project' => $project,
            'default_date' => '2026-08-01',
        ]);

        $this->assertCount(5, $pengItems);
        $this->assertEquals($pengPagu, (int)array_sum(array_column($pengItems, 'total')));
        $pengNames = array_column($pengItems, 'uraian');
        $this->assertContains('Pengadaan ATK, Format Isian BKU, & Penggandaan Dokumen SPJ Revitalisasi', $pengNames);
        $this->assertContains('Konsumsi Rapat Koordinasi Tim P2SP, Komite Sekolah, & Tokoh Masyarakat', $pengNames);
        $this->assertContains('Biaya Transportasi Tim P2SP (Konsultasi Teknis Dinas Pendidikan & Transaksi Bank)', $pengNames);
        $this->assertContains('Spanduk Publikasi Revitalisasi & Papan Informasi Transparansi Swakelola', $pengNames);
        $this->assertContains('Pengadaan Bea Materai Administrasi Kuitansi & Berita Acara SPJ (Materai 10.000)', $pengNames);

        // 3. Uji Upload File RPD Generic CSV yang memuat pagu gelondongan Mobilisasi dan Biaya Pengelolaan
        $csvContent = "No,Kode Rekening,Kategori,Uraian Barang / Kegiatan / Bahan,Spesifikasi,Volume,Satuan,Harga Satuan,Total Biaya,Tanggal Rencana Belanja,Toko / Rekanan,Metode Bayar\n" .
                      "1,5.1.02.01,Bahan,Semen Gresik 50 Kg,SNI PCC Type 1,100,Zak,68000,6800000,2026-08-05,Toko Bangunan Berkah Situbondo,Transfer Bank\n" .
                      "2,5.1.02.03,Alat,Belanja Mobilisasi,Mobilisasi material dan alat proyek,1,Paket,25000000,25000000,2026-08-02,,Transfer Bank\n" .
                      "3,5.1.02.04,Operasional,Biaya Pengelolaan,Operasional Tim P2SP,1,Paket,12000000,12000000,2026-08-03,,Kas Tunai\n";

        $tempFile = tempnam(sys_get_temp_dir(), 'test_rpd_generic_') . '.csv';
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempFile,
            'RPD_Generic_Test.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->post(route('rpd.store'), [
            'school_id' => $project->school_id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap 1 Uji AI',
            'title' => 'RPD Generic Auto Breakdown Test',
            'default_date' => '2026-08-01',
            'breakdown_mode' => 'detailed',
            'rpd_file' => $uploadedFile,
            'use_ai' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $doc = \App\Models\RpdDocument::where('title', 'RPD Generic Auto Breakdown Test')->first();
        $this->assertNotNull($doc);

        // 1 item bahan + 4 item mobilisasi (ongkos kirim) + 5 item pengelolaan P2SP = 10 items
        $this->assertEquals(10, $doc->items()->count());
        $this->assertEquals(6800000 + 25000000 + 12000000, (int)$doc->total_budget);

        // Verifikasi item mobilisasi
        $mobInDoc = $doc->items()->where('category', 'alat')->get();
        $this->assertCount(4, $mobInDoc);
        $this->assertEquals(25000000, (int)$mobInDoc->sum('total_price'));

        // Verifikasi item pengelolaan
        $pengInDoc = $doc->items()->where('category', 'operasional')->get();
        $this->assertCount(5, $pengInDoc);
        $this->assertEquals(12000000, (int)$pengInDoc->sum('total_price'));

        // Bersihkan
        $doc->items()->delete();
        $doc->delete();
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }
}

