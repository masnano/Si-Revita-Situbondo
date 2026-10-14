<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiRpdService
{
    /**
     * Memvariasikan dan memperkaya draf item pecahan bahan RPD menggunakan Google Gemini API.
     * Jika API Key tidak ada atau koneksi gagal, otomatis beralih ke Smart Heuristic Variance Engine (100% lokal).
     */
    public function enhanceItems(array $items, array $options = []): array
    {
        $apiKey = $options['api_key'] ?? config('services.gemini.api_key');
        $model = $options['model'] ?? config('services.gemini.model', 'gemini-2.5-flash');
        $baseDate = $options['base_date'] ?? now()->format('Y-m-d');
        $projectTitle = $options['project_title'] ?? 'Revitalisasi Sekolah';
        $schoolName = $options['school_name'] ?? 'Satuan Pendidikan Situbondo';
        $termStage = $options['term_stage'] ?? 'Tahap 1';

        if (empty($apiKey)) {
            Log::info("GeminiRpdService: GEMINI_API_KEY tidak ditemukan, menggunakan Smart Heuristic Engine lokal.");
            return [
                'success' => true,
                'used_ai' => false,
                'provider' => 'smart_heuristic',
                'message' => 'Variasi disusun menggunakan Mesin Heuristik Cerdas Lokal (Jadwal berfase & narasi bervariasi).',
                'items' => $this->applySmartHeuristicVariance($items, $baseDate, $projectTitle),
            ];
        }

        try {
            $simplifiedItems = [];
            foreach ($items as $item) {
                $simplifiedItems[] = [
                    'row_number' => $item['row_number'] ?? null,
                    'category' => $item['category'] ?? 'bahan',
                    'item_name' => $item['item_name'] ?? '',
                    'specification' => $item['specification'] ?? '',
                    'volume' => $item['volume'] ?? 1,
                    'unit' => $item['unit'] ?? 'Unit',
                    'total_price' => (float)($item['total_price'] ?? 0),
                    'current_supplier' => $item['supplier_name'] ?? '',
                ];
            }

            $prompt = $this->buildPrompt($simplifiedItems, [
                'base_date' => $baseDate,
                'project_title' => $projectTitle,
                'school_name' => $schoolName,
                'term_stage' => $termStage,
            ]);

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(35)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'responseMimeType' => 'application/json',
                ]
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $candidates = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($candidates) {
                    $aiData = json_decode($candidates, true);
                    if (is_array($aiData)) {
                        $enhanced = $this->mergeAiOutputWithItems($items, $aiData, $baseDate, $projectTitle);
                        return [
                            'success' => true,
                            'used_ai' => true,
                            'provider' => 'gemini_api',
                            'model' => $model,
                            'message' => "Alhamdulillah! Jadwal belanja dan narasi BKU berhasil disempurnakan secara cerdas oleh Google Gemini AI ({$model}).",
                            'items' => $enhanced,
                        ];
                    }
                }
            }

            Log::warning("GeminiRpdService API HTTP error: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("GeminiRpdService error: " . $e->getMessage());
        }

        // Fallback anggun (Graceful fallback) ke Smart Heuristic lokal
        return [
            'success' => true,
            'used_ai' => false,
            'provider' => 'smart_heuristic',
            'message' => 'Koneksi ke Gemini API tidak tersedia atau kuota habis. Variasi jadwal & narasi disusun menggunakan Algoritma Cerdas Lokal.',
            'items' => $this->applySmartHeuristicVariance($items, $baseDate, $projectTitle),
        ];
    }

    /**
     * Susun instruksi prompt terstruktur untuk Gemini.
     */
    protected function buildPrompt(array $items, array $context): string
    {
        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Anda adalah Asisten AI Ahli Administrasi Keuangan Daerah & Manajemen Konstruksi Fisik untuk Program Revitalisasi Satuan Pendidikan di Kabupaten Situbondo, Jawa Timur.

Tugas Anda:
Analisis rincian item belanja RPD (Rencana Penarikan Dana) berikut, lalu variasikan tanggal rencana belanja BKU (planned_date), narasi pembukuan transaksi BKU (bku_description), dan nama toko/rekanan lokal di Kabupaten Situbondo (supplier_name).

KONTEKS PROYEK:
- Sekolah: {$context['school_name']}
- Paket Proyek: {$context['project_title']}
- Tahap Pencairan: {$context['term_stage']}
- Tanggal Mulai Pencairan (Baseline): {$context['base_date']}

ATURAN WAJIB (STRICT RULES):
1. PENANGGALAN JADWAL BELANJA BKU (planned_date):
   - Tanggal paling awal dimulai pada atau setelah {$context['base_date']}.
   - Susun jadwal bertahap selama rentang 3 s.d. 5 minggu secara logis sesuai urutan pekerjaan konstruksi nyata di lapangan:
     * Minggu 1: Belanja persiapan, galian, pondasi, pasir pasang, semen dasar, besi beton, sewa molen/alat.
     * Minggu 2: Belanja bata merah, semen dinding, kusen kayu/pintu, upah tukang minggu 1.
     * Minggu 3: Belanja rangka baja ringan, genteng/spandek, pipa sanitasi, upah tukang minggu 2.
     * Minggu 4: Belanja keramik lantai, kloset/sanitasi, plafon, upah tukang minggu 3.
     * Minggu 5: Belanja cat tembok, pelitur kayu, kunci & handle, operasional administrasi & ATK SPJ, upah tukang akhir.
   - PENTING: Pembayaran upah tukang/pekerja (kategori 'upah') HARUS SELALU dijadwalkan pada hari SABTU (akhir pekan).
   - DILARANG KERAS menjadwalkan transaksi apapun pada HARI MINGGU (kantor & toko libur).
   - Belanja bahan/alat dijadwalkan pada hari kerja (Senin s.d. Jumat/Sabtu). Format tanggal: YYYY-MM-DD.

2. VARIASI NARASI BKU (bku_description):
   - Susun kalimat pembukuan transaksi BKU yang alami, kaya variasi, dan sesuai kaidah tata naskah dinas resmi SPJ Revitalisasi/BOS/DAK Kemendikbudristek & BPK.
   - Jangan gunakan kalimat seragam yang monoton. Gunakan kombinasi frasa resmi seperti:
     * "Pembayaran belanja bahan material [uraian barang] ([volume] [satuan]) pada [toko] untuk pekerjaan [proyek]"
     * "Belanja pengadaan material konstruksi [uraian barang] sebanyak [volume] [satuan] dari [toko] keperluan [proyek]"
     * "Pembayaran upah tenaga kerja/tukang swakelola [uraian pekerjaan] ([volume] [satuan]) pada [mandor/pekerja]"
     * "Pembayaran sewa/pengadaan peralatan operasional kerja [uraian alat] ([volume] [satuan]) pada [rekanan]"
     * "Belanja operasional ATK, penggandaan, dan penyusunan SPJ revitalisasi pada [toko ATK]"

3. TOKO / REKANAN LOKAL SITUBONDO (supplier_name):
   - Jika current_supplier kosong atau berupa titik-titik, rekomendasikan penyedia/toko bangunan riil yang ada di wilayah Kabupaten Situbondo (Kecamatan Situbondo Kota, Panji, Besuki, Mangaran, Kapongan, dll.). Contoh:
     * Pasir/Batu: Pemasok Pasir & Batu Kali Sampean Situbondo
     * Semen/Bata/Material umum: Toko Bangunan Berkah Situbondo / UD Sinar Terang Panji / Toko Bangunan Sumber Rejeki Besuki
     * Besi & Atap Baja: Toko Besi & Baja Abadi Situbondo / Toko Baja Ringan Situbondo
     * Cat/Finishing: Toko Cat Citra Warna Situbondo / Toko Bangunan Utama
     * Sanitasi & Keramik: Toko Keramik Sentosa Situbondo
     * Upah: Kelompok Pekerja Swakelola Sekolah / Kelompok Tukang Panji
     * Operasional & ATK: Toko ATK Grafika Situbondo / Percetakan Media Situbondo

DATA ITEM PECAHAN BAHAN:
{$itemsJson}

OUTPUT WAJIB:
Hasilkan HANYA JSON array murni tanpa markdown pembungkus tambahan, dengan format persis:
[
  {
    "row_number": 1,
    "planned_date": "YYYY-MM-DD",
    "bku_description": "...",
    "supplier_name": "..."
  }
]
PROMPT;
    }

    /**
     * Menggabungkan hasil AI dengan item yang ada dengan validasi ketat.
     */
    protected function mergeAiOutputWithItems(array $originalItems, array $aiData, string $baseDate, string $projectTitle): array
    {
        $aiMap = [];
        foreach ($aiData as $row) {
            if (isset($row['row_number'])) {
                $aiMap[$row['row_number']] = $row;
            }
        }

        $result = [];
        foreach ($originalItems as $idx => $item) {
            $rowNum = $item['row_number'] ?? ($idx + 1);
            $aiRow = $aiMap[$rowNum] ?? null;

            if ($aiRow) {
                // Validasi tanggal: tidak boleh hari Minggu, upah harus Sabtu
                $date = $aiRow['planned_date'] ?? $item['planned_date'];
                try {
                    $cDate = Carbon::parse($date);
                    if ($cDate->isSunday()) {
                        $date = ($item['category'] ?? '') === 'upah' ? $cDate->subDay()->format('Y-m-d') : $cDate->addDay()->format('Y-m-d');
                    }
                    if (($item['category'] ?? '') === 'upah' && !$cDate->isSaturday()) {
                        $date = $cDate->next(Carbon::SATURDAY)->format('Y-m-d');
                    }
                } catch (\Throwable $e) {
                    $date = $item['planned_date'] ?? $baseDate;
                }

                $item['planned_date'] = $date;

                if (!empty($aiRow['bku_description'])) {
                    $item['bku_description'] = trim($aiRow['bku_description']);
                }

                if (!empty($aiRow['supplier_name']) && (empty($item['supplier_name']) || $item['supplier_name'] === '....................')) {
                    $item['supplier_name'] = trim($aiRow['supplier_name']);
                }
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Mesin Heuristik Cerdas Lokal (100% Offline & Tanpa Kuota).
     * Memvariasikan jadwal berfase konstruksi, mengacak narasi BKU resmi, dan memilih toko lokal Situbondo.
     */
    public function applySmartHeuristicVariance(array $items, string $baseDate, string $projectTitle): array
    {
        try {
            $base = Carbon::parse($baseDate);
        } catch (\Throwable $e) {
            $base = now();
        }

        if ($base->isSunday()) {
            $base->addDay();
        }

        $situbondoSuppliers = [
            'pasir_batu' => [
                'Pemasok Pasir Pasang Kali Sampean Situbondo',
                'Pemasok Pasir & Koral Panji Situbondo',
                'Pemasok Batu Belah Kali Sampean',
            ],
            'semen_bata' => [
                'Toko Bangunan Berkah Situbondo',
                'Pengrajin Bata Merah Panji Situbondo',
                'UD Sinar Terang Bangunan Panji',
                'Toko Material Sumber Rejeki Besuki',
            ],
            'besi_atap' => [
                'Toko Besi Abadi Jl. Pemuda Situbondo',
                'UD Baja Ringan Situbondo Jaya',
                'Toko Rangka Galvalum Besuki',
            ],
            'keramik_sanitasi' => [
                'Toko Keramik & Sanitasi Sentosa Situbondo',
                'Toko Bangunan Mitra Karya Situbondo',
                'Depo Bangunan Situbondo Indah',
            ],
            'cat_finishing' => [
                'Toko Cat Citra Warna Situbondo',
                'Toko Cat & Finishing Pelangi Situbondo',
                'UD Warna Abadi Besuki Situbondo',
            ],
            'kayu_kusen' => [
                'Pengrajin Kusen Kayu Subur Kapongan',
                'UD Kayu Jati Murni Panji',
            ],
            'upah' => [
                'Kelompok Tukang Swakelola Sekolah',
                'Kelompok Pekerja Swakelola Komite',
                'Kelompok Tenaga Kerja Lokal Panji',
                'Kelompok Tukang Pak Slamet',
            ],
            'alat' => [
                'Sewa Alat Proyek Jaya Situbondo',
                'Persewaan Mesin Molen Barokah',
            ],
            'operasional' => [
                'Toko ATK Grafika Situbondo',
                'Percetakan & ATK Media Mandiri Situbondo',
                'Toko Alat Tulis Pelajar Situbondo',
            ],
        ];

        $narrativeTemplates = [
            'bahan' => [
                "Pembayaran belanja bahan material {item} ({vol} {unit}) pada {supplier} untuk pekerjaan {project}",
                "Belanja pengadaan material konstruksi {item} sebanyak {vol} {unit} dari {supplier} keperluan {project}",
                "Pembelian bahan bangunan {item} sejumlah {vol} {unit} pada {supplier} kegiatan {project}",
                "Pembayaran belanja material {item} ({vol} {unit}) guna percepatan {project} pada {supplier}",
            ],
            'upah' => [
                "Pembayaran upah tenaga kerja/tukang {item} ({vol} {unit}) untuk pekerjaan {project} kepada {supplier}",
                "Pencairan honorarium pekerja/tukang swakelola {item} sebanyak {vol} {unit} pada {supplier} pekerjaan {project}",
                "Pembayaran upah mingguan tenaga kerja {item} ({vol} {unit}) kegiatan {project} kepada {supplier}",
            ],
            'alat' => [
                "Pembayaran sewa/pengadaan peralatan operasional {item} ({vol} {unit}) pada {supplier} untuk pekerjaan {project}",
                "Belanja sewa alat kerja pendukung {item} ({vol} {unit}) pada {supplier} kegiatan {project}",
            ],
            'operasional' => [
                "Pembayaran belanja operasional/administrasi {item} pada {supplier} untuk pekerjaan {project}",
                "Belanja kebutuhan ATK, penggandaan, dan pelaporan SPJ {item} pada {supplier} kegiatan {project}",
                "Pembayaran biaya administrasi dan operasional tim {item} pada {supplier} kegiatan {project}",
            ],
        ];

        $result = [];
        $totalItems = count($items);

        foreach ($items as $idx => $item) {
            $cat = $item['category'] ?? 'bahan';
            $itemName = strtolower($item['item_name'] ?? '');
            $vol = number_format((float)($item['volume'] ?? 1), ($item['volume'] ?? 1) == (int)($item['volume'] ?? 1) ? 0 : 2, ',', '.');
            $unit = $item['unit'] ?? 'Unit';

            // 1. Tentukan Fase Konstruksi (1 - 5)
            $phase = 1;
            if (str_contains($itemName, 'galian') || str_contains($itemName, 'pondasi') || str_contains($itemName, 'pasir') || str_contains($itemName, 'batu') || str_contains($itemName, 'molen') || str_contains($itemName, 'persiapan') || str_contains($itemName, 'rk3k')) {
                $phase = 1;
            } elseif (str_contains($itemName, 'bata') || str_contains($itemName, 'dinding') || str_contains($itemName, 'plesteran') || str_contains($itemName, 'besi') || str_contains($itemName, 'semen')) {
                $phase = 2;
            } elseif (str_contains($itemName, 'atap') || str_contains($itemName, 'baja') || str_contains($itemName, 'galvalum') || str_contains($itemName, 'genteng') || str_contains($itemName, 'spandek') || str_contains($itemName, 'plafon') || str_contains($itemName, 'kusen') || str_contains($itemName, 'kayu')) {
                $phase = 3;
            } elseif (str_contains($itemName, 'keramik') || str_contains($itemName, 'lantai') || str_contains($itemName, 'kloset') || str_contains($itemName, 'sanitasi') || str_contains($itemName, 'pipa') || str_contains($itemName, 'toilet')) {
                $phase = 4;
            } elseif (str_contains($itemName, 'cat') || str_contains($itemName, 'pelitur') || str_contains($itemName, 'plamir') || str_contains($itemName, 'kunci') || str_contains($itemName, 'lampu') || str_contains($itemName, 'atk') || str_contains($itemName, 'spj') || str_contains($itemName, 'pengelolaan') || str_contains($itemName, 'perencanaan')) {
                $phase = 5;
            } else {
                // Sebar merata berdasar indeks
                $phase = min(5, max(1, (int)floor(($idx / max(1, $totalItems)) * 5) + 1));
            }

            // 2. Hitung Tanggal Progresif
            $targetDate = $base->copy();
            if ($cat === 'upah') {
                // Upah tukang jatuh pada hari Sabtu di minggu fase terkait
                $weeksToAdd = $phase - 1;
                $targetDate->addWeeks($weeksToAdd);
                if (!$targetDate->isSaturday()) {
                    $targetDate->next(Carbon::SATURDAY);
                }
            } else {
                // Belanja bahan / alat: hari kerja (Senin s.d. Jumat)
                $dayOffset = (($phase - 1) * 7) + (($idx % 4) * 2);
                $targetDate->addDays($dayOffset);
                if ($targetDate->isSunday()) {
                    $targetDate->addDay();
                }
            }
            $item['planned_date'] = $targetDate->format('Y-m-d');

            // 3. Rekomendasi Toko Lokal Situbondo
            $currSupplier = trim((string)($item['supplier_name'] ?? ''));
            if ($currSupplier === '' || $currSupplier === '....................') {
                if ($cat === 'upah') {
                    $item['supplier_name'] = $situbondoSuppliers['upah'][$idx % count($situbondoSuppliers['upah'])];
                } elseif ($cat === 'alat') {
                    $item['supplier_name'] = $situbondoSuppliers['alat'][$idx % count($situbondoSuppliers['alat'])];
                } elseif ($cat === 'operasional') {
                    $item['supplier_name'] = $situbondoSuppliers['operasional'][$idx % count($situbondoSuppliers['operasional'])];
                } else {
                    if (str_contains($itemName, 'pasir') || str_contains($itemName, 'batu') || str_contains($itemName, 'koral')) {
                        $item['supplier_name'] = $situbondoSuppliers['pasir_batu'][$idx % count($situbondoSuppliers['pasir_batu'])];
                    } elseif (str_contains($itemName, 'besi') || str_contains($itemName, 'baja') || str_contains($itemName, 'atap')) {
                        $item['supplier_name'] = $situbondoSuppliers['besi_atap'][$idx % count($situbondoSuppliers['besi_atap'])];
                    } elseif (str_contains($itemName, 'keramik') || str_contains($itemName, 'kloset') || str_contains($itemName, 'sanitasi')) {
                        $item['supplier_name'] = $situbondoSuppliers['keramik_sanitasi'][$idx % count($situbondoSuppliers['keramik_sanitasi'])];
                    } elseif (str_contains($itemName, 'cat') || str_contains($itemName, 'kuas') || str_contains($itemName, 'pelitur')) {
                        $item['supplier_name'] = $situbondoSuppliers['cat_finishing'][$idx % count($situbondoSuppliers['cat_finishing'])];
                    } elseif (str_contains($itemName, 'kayu') || str_contains($itemName, 'kusen') || str_contains($itemName, 'pintu')) {
                        $item['supplier_name'] = $situbondoSuppliers['kayu_kusen'][$idx % count($situbondoSuppliers['kayu_kusen'])];
                    } else {
                        $item['supplier_name'] = $situbondoSuppliers['semen_bata'][$idx % count($situbondoSuppliers['semen_bata'])];
                    }
                }
            }

            // 4. Variasikan Narasi BKU
            $templates = $narrativeTemplates[$cat] ?? $narrativeTemplates['bahan'];
            $selectedTemplate = $templates[$idx % count($templates)];
            $item['bku_description'] = str_replace(
                ['{item}', '{vol}', '{unit}', '{supplier}', '{project}'],
                [$item['item_name'] ?? 'Material', $vol, $unit, $item['supplier_name'], $projectTitle],
                $selectedTemplate
            );

            $result[] = $item;
        }

        return $result;
    }
}

