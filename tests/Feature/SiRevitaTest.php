<?php

namespace Tests\Feature;

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

        $response = $this->post('/rpd', [
            'school_id' => $project->school_id,
            'project_id' => $project->id,
            'term_stage' => 'Tahap 1',
            'title' => 'RPD Pengujian Otomatis BKU',
            'rpd_file' => $uploadedFile,
        ]);

        $response->assertRedirect();
        
        $doc = \App\Models\RpdDocument::where('title', 'RPD Pengujian Otomatis BKU')->first();
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
}

