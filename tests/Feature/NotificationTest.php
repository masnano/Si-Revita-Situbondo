<?php

namespace Tests\Feature;

use App\Models\BudgetItem;
use App\Models\RevitalizationProject;
use App\Models\RpdDocument;
use App\Models\RpdItem;
use App\Models\School;
use App\Models\SystemNotification;
use App\Models\User;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_guest_cannot_access_notifications()
    {
        $response = $this->getJson('/notifications');
        $response->assertStatus(401);

        $response = $this->get('/notifications');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_notifications_and_unread_count()
    {
        $user = User::first();
        $this->actingAs($user);

        $response = $this->getJson('/notifications');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'unread_count',
            'notifications' => [
                '*' => [
                    'id',
                    'type',
                    'title',
                    'message',
                    'url',
                    'icon',
                    'color',
                    'time_ago',
                    'is_read'
                ]
            ]
        ]);

        $responseUnread = $this->getJson('/notifications/unread-count');
        $responseUnread->assertStatus(200);
        $responseUnread->assertJsonStructure([
            'success',
            'unread_count',
            'latest_id'
        ]);
    }

    public function test_mark_all_as_read_empties_notification_badge()
    {
        $user = User::first();
        $this->actingAs($user);

        // Ensure there is at least one notification
        SystemNotification::send(
            type: 'school',
            title: 'Test Notification',
            message: 'Testing mark all as read',
            url: '/test'
        );

        $beforeCount = SystemNotification::whereDoesntHave('reads', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->count();

        $this->assertGreaterThan(0, $beforeCount);

        // Mark all as read
        $response = $this->postJson('/notifications/read-all');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'unread_count' => 0
        ]);

        $afterCount = SystemNotification::whereDoesntHave('reads', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->count();

        $this->assertEquals(0, $afterCount);
    }

    public function test_school_creation_triggers_push_notification()
    {
        $user = User::where('username', 'root')->first();
        $this->actingAs($user);

        $npsn = '99' . rand(100000, 999999);
        $response = $this->post('/schools', [
            'npsn' => $npsn,
            'name' => 'SD Negeri Uji Coba Notifikasi',
            'jenjang' => 'SD',
            'status' => 'Negeri',
            'address' => 'Jl. Merdeka No. 12',
            'kecamatan' => 'Panji',
            'kabupaten' => 'Situbondo',
            'principal_name' => 'Bambang Sudarsono, S.Pd',
            'principal_nip' => '197501012000031001',
            'treasurer_name' => 'Sri Wahyuni, S.Pd',
            'treasurer_nip' => '198005122005012003',
            'bank_name' => 'Bank Jatim Cabang Situbondo',
            'bank_account_number' => '0293847291',
            'bank_account_holder' => 'SD Negeri Uji Coba Notifikasi',
        ]);

        $response->assertRedirect('/schools');
        $response->assertSessionHas('push_notification');

        $notif = SystemNotification::where('type', 'school')
            ->where('title', 'like', '%Sekolah%')
            ->latest('id')
            ->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('SD Negeri Uji Coba Notifikasi', $notif->message);
        $this->assertEquals('emerald', $notif->color);
    }

    public function test_project_creation_triggers_push_notification()
    {
        $user = User::where('username', 'root')->first();
        $this->actingAs($user);

        $school = School::first();

        $response = $this->post('/projects', [
            'school_id' => $school->id,
            'fiscal_year' => 2026,
            'title' => 'Pembangunan Laboratorium IPA Uji Coba',
            'funding_source' => 'DAK Fisik 2026',
            'spk_number' => 'SPK/NOTIF/01/' . rand(100, 999),
            'spk_date' => '2026-03-01',
            'contract_amount' => 175000000,
            'start_date' => '2026-03-01',
            'end_date' => '2026-06-30',
            'status' => 'pelaksanaan',
            'physical_progress' => 15,
            'description' => 'Uji coba notifikasi proyek',
        ]);

        $response->assertRedirect('/projects');
        $response->assertSessionHas('push_notification');

        $notif = SystemNotification::where('type', 'project')
            ->latest('id')
            ->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('Pembangunan Laboratorium IPA Uji Coba', $notif->message);
        $this->assertEquals('indigo', $notif->color);
    }

    public function test_rab_creation_triggers_push_notification()
    {
        $user = User::where('username', 'root')->first();
        $this->actingAs($user);

        $project = RevitalizationProject::first();

        $response = $this->post('/rab', [
            'project_id' => $project->id,
            'menu_pekerjaan' => 'Rehabilitasi Ruang Kelas',
            'category' => 'bahan',
            'name' => 'Besi Beton 12mm SNI',
            'volume' => 50,
            'unit' => 'Batang',
            'unit_price' => 110000,
            'notes' => 'Uji coba notifikasi RAB',
        ]);

        $response->assertSessionHas('push_notification');

        $notif = SystemNotification::where('type', 'rab')
            ->latest('id')
            ->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('Besi Beton 12mm SNI', $notif->message);
        $this->assertEquals('amber', $notif->color);
    }

    public function test_rpd_posting_to_bku_triggers_notification()
    {
        $user = User::where('username', 'root')->first();
        $this->actingAs($user);

        $rpd = RpdDocument::first();
        if (!$rpd) {
            $school = School::first();
            $project = RevitalizationProject::first();
            $rpd = RpdDocument::create([
                'school_id' => $school->id,
                'project_id' => $project->id,
                'term_stage' => 'Termin 1 (70%)',
                'title' => 'RPD Termin 1 Test',
                'status' => 'analyzed',
                'total_items_count' => 1,
                'total_budget' => 5000000,
            ]);
            RpdItem::create([
                'rpd_document_id' => $rpd->id,
                'project_id' => $project->id,
                'school_id' => $school->id,
                'item_name' => 'Semen Gresik',
                'category' => 'bahan',
                'volume' => 10,
                'unit' => 'Zak',
                'unit_price' => 70000,
                'total_price' => 700000,
                'planned_date' => now()->format('Y-m-d'),
                'is_posted' => false,
            ]);
        } else {
            RpdItem::where('rpd_document_id', $rpd->id)->update(['is_posted' => false]);
            $rpd->update(['status' => 'analyzed']);
        }

        $response = $this->post("/rpd/{$rpd->id}/post-bku");
        $response->assertSessionHas('push_notification');

        $notif = SystemNotification::where('type', 'bku')
            ->latest('id')
            ->first();

        $this->assertNotNull($notif);
        $this->assertEquals('Posting RPD ke BKU Berhasil', $notif->title);
        $this->assertEquals('sky', $notif->color);
    }

    public function test_layout_contains_notification_bell_between_print_and_logout()
    {
        $user = User::first();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('Cetak Layar', $content);
        $this->assertStringContainsString('notificationManager', $content);
        $this->assertStringContainsString('data-lucide="bell"', $content);
        $this->assertStringContainsString('Read All', $content);

        // Verify order in top header: Cetak Layar appears before notification bell, and notification bell appears before logout
        $printPos = strpos($content, 'Cetak Layar');
        $this->assertNotFalse($printPos, 'Tombol Cetak Layar harus ada di header');

        $bellPos = strpos($content, 'notificationManager', $printPos);
        $this->assertNotFalse($bellPos, 'Lonceng notifikasi harus ada setelah Cetak Layar');

        $logoutPos = strpos($content, 'action="' . route('logout') . '"', $bellPos);
        $this->assertNotFalse($logoutPos, 'Tombol Logout harus ada setelah Lonceng notifikasi');

        $this->assertLessThan($bellPos, $printPos, 'Tombol Cetak Layar harus muncul sebelum lonceng notifikasi');
        $this->assertLessThan($logoutPos, $bellPos, 'Lonceng notifikasi harus muncul sebelum tombol logout');
    }
}
