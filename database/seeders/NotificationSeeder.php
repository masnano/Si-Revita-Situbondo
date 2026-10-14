<?php

namespace Database\Seeders;

use App\Models\RevitalizationProject;
use App\Models\RpdDocument;
use App\Models\School;
use App\Models\SystemNotification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        $project = RevitalizationProject::with('school')->first();
        $rpd = RpdDocument::first();

        if ($school) {
            SystemNotification::create([
                'type' => 'school',
                'title' => 'Penambahan Data Sekolah Baru',
                'message' => "Sekolah {$school->name} (NPSN: {$school->npsn}) di Kec. {$school->kecamatan} telah berhasil didaftarkan.",
                'url' => route('schools.index', ['search' => $school->npsn]),
                'icon' => 'school',
                'color' => 'emerald',
                'created_at' => now()->subMinutes(45),
            ]);
        }

        if ($project) {
            $formattedAmount = number_format($project->contract_amount, 0, ',', '.');
            $schoolName = $project->school->name ?? 'Sekolah';

            SystemNotification::create([
                'type' => 'project',
                'title' => 'Penambahan Paket Revitalisasi Baru',
                'message' => "Paket '{$project->title}' untuk {$schoolName} (TA {$project->fiscal_year}, Pagu Rp {$formattedAmount}) berhasil didaftarkan.",
                'url' => route('projects.show', $project->id),
                'icon' => 'building-2',
                'color' => 'indigo',
                'created_at' => now()->subMinutes(30),
            ]);

            SystemNotification::create([
                'type' => 'rab',
                'title' => 'Penambahan Pos Belanja RAB',
                'message' => "Item 'Semen Gresik 50 Zak' (100 Zak - Rp 6.800.000) ditambahkan ke Menu Rehabilitasi Ruang Kelas pada {$project->title}.",
                'url' => route('rab.index', ['project_id' => $project->id]),
                'icon' => 'calculator',
                'color' => 'amber',
                'created_at' => now()->subMinutes(20),
            ]);
        }

        if ($rpd) {
            $formattedBudget = number_format($rpd->total_budget, 0, ',', '.');

            SystemNotification::create([
                'type' => 'rpd',
                'title' => 'Pecah Bahan RPD Berhasil Diproses',
                'message' => "Dokumen RPD '{$rpd->title}' ({$rpd->term_stage}) berhasil dipecah menjadi {$rpd->total_items_count} item draf isian BKU (Total Rp {$formattedBudget}).",
                'url' => route('rpd.show', $rpd->id),
                'icon' => 'file-spreadsheet',
                'color' => 'purple',
                'created_at' => now()->subMinutes(12),
            ]);

            SystemNotification::create([
                'type' => 'bku',
                'title' => 'Posting RPD ke BKU Berhasil',
                'message' => "Sebanyak " . ($rpd->total_items_count ?: 12) . " transaksi resmi dari dokumen RPD '{$rpd->title}' telah berhasil diposting ke Buku Kas Umum (BKU), BPK, BB, dan Buku Pembantu Pajak.",
                'url' => route('reports.bku', ['project_id' => $rpd->project_id]),
                'icon' => 'book-open',
                'color' => 'sky',
                'created_at' => now()->subMinutes(5),
            ]);
        }
    }
}

