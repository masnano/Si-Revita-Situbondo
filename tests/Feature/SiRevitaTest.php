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
}

