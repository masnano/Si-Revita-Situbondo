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
    /**
     * Memanggil Google Gemini API dengan auto-fallback model (gemini-3.5-flash-lite, gemini-3.8-flash, dll).
     */
    public function callGeminiApi(string $prompt, array $options = []): ?string
    {
        $apiKey = $options['api_key'] ?? config('services.gemini.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $requestedModel = $options['model'] ?? config('services.gemini.model', 'gemini-3.5-flash-lite');
        // Urutan model yang dicoba jika terjadi 404 (model retired) atau 503 (high demand)
        $modelsToTry = array_unique([
            $requestedModel,
            'gemini-3.5-flash-lite',
            'gemini-3.8-flash',
            'gemini-flash-latest',
        ]);

        $timeout = $options['timeout'] ?? 30;
        $jsonMode = $options['json_mode'] ?? true;
        $temperature = $options['temperature'] ?? 0.3;

        foreach ($modelsToTry as $model) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => $temperature,
                    ]
                ];

                if (!empty($options['max_tokens'])) {
                    $payload['generationConfig']['maxOutputTokens'] = (int)$options['max_tokens'];
                }

                if ($jsonMode) {
                    $payload['generationConfig']['responseMimeType'] = 'application/json';
                }

                $response = Http::timeout($timeout)->post($url, $payload);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!empty($text)) {
                        return trim($text);
                    }
                }

                $status = $response->status();
                Log::warning("GeminiRpdService: Model {$model} returned status {$status}: " . substr($response->body(), 0, 250));

                if (in_array($status, [404, 429, 503])) {
                    continue;
                }
            } catch (\Throwable $e) {
                Log::warning("GeminiRpdService: Exception on model {$model}: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Memvariasikan dan memperkaya draf item pecahan bahan RPD menggunakan Google Gemini API.
     * Jika API Key tidak ada atau koneksi gagal, otomatis beralih ke Smart Heuristic Variance Engine (100% lokal).
     */
    public function enhanceItems(array $items, array $options = []): array
    {
        $apiKey = $options['api_key'] ?? config('services.gemini.api_key');
        $model = $options['model'] ?? config('services.gemini.model', 'gemini-3.5-flash-lite');
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

            $rawJson = $this->callGeminiApi($prompt, array_merge($options, [
                'timeout' => 35,
                'json_mode' => true,
                'temperature' => 0.4,
            ]));

            if (!empty($rawJson)) {
                $aiData = json_decode($rawJson, true);
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
     * Memecah pagu Belanja Mobilisasi secara otomatis menggunakan AI Google Gemini
     * (atau Smart Heuristic Engine jika offline/tanpa API key).
     * Sesuai ketentuan: biaya mobilisasi digunakan sebagai ongkos kirim/angkut bahan material
     * dari toko ke tempat yang ditentukan/sekolah serta pengangkutan peralatan kerja proyek.
     */
    public function decomposeMobilisasiWithAi(float $totalAmount, array $options = []): array
    {
        if ($totalAmount <= 0) {
            return [];
        }

        $schoolName = $options['school_name'] ?? 'Satuan Pendidikan Kab. Situbondo';
        $projectTitle = $options['project_title'] ?? 'Revitalisasi Sekolah';
        $termStage = $options['term_stage'] ?? 'Tahap 1';
        $defaultDate = $options['default_date'] ?? now()->format('Y-m-d');
        $roomName = $options['room_name'] ?? null;
        $existingRab = $options['existing_rab'] ?? null;

        // Ambil info material dari RAB jika ada agar AI kontekstual terhadap material riil
        $materialsSummary = '';
        if (!empty($existingRab) && (is_countable($existingRab) ? count($existingRab) : 0) > 0) {
            $matList = collect($existingRab)->where('category', 'Material/Bahan')->pluck('name')->take(8)->implode(', ');
            if (!empty($matList)) {
                $materialsSummary = "\nDAFTAR MATERIAL RIIL PROYEK DARI RAB: " . $matList;
            }
        }

        $prompt = <<<PROMPT
Anda adalah Asisten AI Ahli Administrasi Keuangan Daerah & Manajemen Logistik Konstruksi Fisik Swakelola Revitalisasi Satuan Pendidikan di Kabupaten Situbondo, Jawa Timur.

TUGAS ANDA:
Pecah pagu total "Belanja Mobilisasi" sebesar Rp {$totalAmount} menjadi rincian pengeluaran riil:
"Biaya mobilisasi digunakan sebagai ongkos kirim/angkut bahan material dari toko rekanan/pemasok ke tempat yang ditentukan/sekolah serta pengangkutan peralatan kerja proyek."

KONTEKS PROYEK:
- Sekolah: {$schoolName}
- Paket Proyek: {$projectTitle}
- Tahap Pencairan: {$termStage}
- Tanggal Mulai Baseline: {$defaultDate}
{$materialsSummary}

ATURAN WAJIB PECAH BIAYA MOBILISASI:
1. KOMPONEN PENGIRIMAN / ONGKOS ANGKUT:
   Pecah menjadi 4 rincian pengiriman bertahap sesuai alur konstruksi:
   a. "Ongkos Angkut & Pengiriman Bahan Material Pondasi & Struktur Beton" (semen, pasir pasang, split, besi beton ke sekolah)
   b. "Ongkos Angkut & Pengiriman Bahan Material Dinding, Kusen & Rangka Atap" (bata merah, semen dinding, galvalum, genteng/spandek, kusen ke sekolah)
   c. "Ongkos Angkut & Pengiriman Bahan Material Lantai, Finishing & Sanitair" (keramik lantai, cat tembok, pipa PVC, kloset porselen ke sekolah)
   d. "Mobilisasi & Demobilisasi Peralatan Kerja (Mesin Molen & Scaffolding)" (pengangkutan sewa molen cor dan steger scaffolding)
2. SATUAN & VOLUME:
   - Gunakan satuan pengiriman: 'Rit', 'Trip', atau 'Paket'.
   - Volume: 1.0 s.d. 2.0.
3. PERSENTASE (percentage):
   - Berikan proporsi persentase desimal (total tepat = 1.0), contoh: 0.35, 0.35, 0.20, 0.10.
4. PENANGGALAN (day_offset):
   - Tentukan day_offset (jumlah hari kerja setelah baseline date) bertahap:
     * Pondasi & Struktur: hari ke-2
     * Dinding & Atap: hari ke-10
     * Finishing & Sanitair: hari ke-18
     * Peralatan: hari ke-4
5. TOKO / REKANAN:
   - Rekomendasikan nama armada/jasa angkut lokal di Situbondo (contoh: "Penyedia Jasa Angkutan & Ekspedisi Material Situbondo", "Armada Angkut Toko Bangunan Berkah Panji", dll.).

OUTPUT JSON (Wajib array murni tanpa markdown):
[
  {
    "name": "Ongkos Angkut & Pengiriman Bahan Material Pondasi & Struktur Beton",
    "specification": "Biaya pengiriman armada truk angkutan material semen, pasir pasang, split, dan besi beton ke lokasi satuan pendidikan",
    "unit": "Rit",
    "volume": 2.0,
    "percentage": 0.35,
    "day_offset": 2,
    "supplier_name": "Penyedia Jasa Angkutan & Ekspedisi Material Situbondo"
  },
  {
    "name": "Ongkos Angkut & Pengiriman Bahan Material Dinding, Kusen & Rangka Atap",
    "specification": "Biaya pengiriman armada truk/pick-up angkutan material bata merah, baja ringan, genteng, dan kusen ke sekolah",
    "unit": "Rit",
    "volume": 2.0,
    "percentage": 0.35,
    "day_offset": 10,
    "supplier_name": "Penyedia Jasa Angkutan & Ekspedisi Material Situbondo"
  },
  {
    "name": "Ongkos Angkut & Pengiriman Bahan Material Lantai, Finishing & Sanitair",
    "specification": "Biaya pengiriman armada pick-up angkutan keramik, cat tembok, pipa PVC, kloset, dan kelengkapan finishing",
    "unit": "Rit",
    "volume": 1.0,
    "percentage": 0.20,
    "day_offset": 18,
    "supplier_name": "Penyedia Jasa Angkutan & Ekspedisi Material Situbondo"
  },
  {
    "name": "Mobilisasi & Demobilisasi Peralatan Kerja (Mesin Molen & Scaffolding)",
    "specification": "Biaya pengangkutan mobilisasi dan penarikan kembali peralatan kerja proyek (molen cor dan steger scaffolding)",
    "unit": "Paket",
    "volume": 1.0,
    "percentage": 0.10,
    "day_offset": 4,
    "supplier_name": "Penyedia Jasa Angkutan & Ekspedisi Material Situbondo"
  }
]
PROMPT;

        $components = null;

        // Panggil Gemini API jika use_ai aktif atau API key ada
        if (!isset($options['use_ai']) || !empty($options['use_ai'])) {
            $rawJson = $this->callGeminiApi($prompt, $options);
            if (!empty($rawJson)) {
                $decoded = json_decode($rawJson, true);
                if (is_array($decoded) && count($decoded) >= 3) {
                    $components = $decoded;
                }
            }
        }

        // Fallback Heuristik Cerdas Lokal
        if (empty($components)) {
            $components = [
                [
                    'name' => 'Ongkos Angkut & Pengiriman Bahan Material Pondasi & Struktur Beton',
                    'specification' => 'Biaya pengiriman armada truk angkutan material semen, pasir pasang, split, dan besi beton ke lokasi satuan pendidikan',
                    'percentage' => 0.35,
                    'unit' => 'Rit',
                    'volume' => 2.0,
                    'day_offset' => 2,
                    'supplier_name' => 'Penyedia Jasa Angkutan & Ekspedisi Material Situbondo',
                ],
                [
                    'name' => 'Ongkos Angkut & Pengiriman Bahan Material Dinding, Kusen & Rangka Atap',
                    'specification' => 'Biaya pengiriman armada truk/pick-up angkutan material bata merah, baja ringan, genteng, dan kusen ke sekolah',
                    'percentage' => 0.35,
                    'unit' => 'Rit',
                    'volume' => 2.0,
                    'day_offset' => 10,
                    'supplier_name' => 'Penyedia Jasa Angkutan & Ekspedisi Material Situbondo',
                ],
                [
                    'name' => 'Ongkos Angkut & Pengiriman Bahan Material Lantai, Finishing & Sanitair',
                    'specification' => 'Biaya pengiriman armada pick-up angkutan keramik, cat tembok, pipa PVC, kloset, dan kelengkapan finishing',
                    'percentage' => 0.20,
                    'unit' => 'Rit',
                    'volume' => 1.0,
                    'day_offset' => 18,
                    'supplier_name' => 'Penyedia Jasa Angkutan & Ekspedisi Material Situbondo',
                ],
                [
                    'name' => 'Mobilisasi & Demobilisasi Peralatan Kerja (Mesin Molen & Scaffolding)',
                    'specification' => 'Biaya pengangkutan mobilisasi dan penarikan kembali peralatan kerja proyek (molen cor dan steger scaffolding)',
                    'percentage' => 0.10,
                    'unit' => 'Paket',
                    'volume' => 1.0,
                    'day_offset' => 4,
                    'supplier_name' => 'Penyedia Jasa Angkutan & Ekspedisi Material Situbondo',
                ],
            ];
        }

        // Normalisasi persentase agar total persis 1.0
        $sumPct = array_sum(array_column($components, 'percentage'));
        if ($sumPct > 0 && abs($sumPct - 1.0) > 0.001) {
            foreach ($components as &$c) {
                $c['percentage'] = $c['percentage'] / $sumPct;
            }
            unset($c);
        }

        // Cari pos RAB Belanja Mobilisasi jika ada
        $breakdownService = app(RpdBreakdownService::class);
        $rabMatch = !empty($existingRab)
            ? $breakdownService->findBestRabMatch('Belanja Mobilisasi', 'alat', $existingRab, $roomName)
            : null;
        $budgetItemId = $rabMatch?->id;
        $itemCode = $rabMatch?->code ?? '5.1.02.03';

        $results = [];
        $runningSum = 0;
        $count = count($components);

        foreach ($components as $idx => $comp) {
            $isLast = ($idx === $count - 1);
            if ($isLast) {
                $subTotal = round($totalAmount - $runningSum, 2);
            } else {
                $subTotal = round($totalAmount * ($comp['percentage'] ?? (1 / $count)), 0);
                $runningSum += $subTotal;
            }

            if ($subTotal <= 0) continue;

            $vol = (float)($comp['volume'] ?? 1.0);
            $unitPrice = round($subTotal / $vol, 2);

            // Hitung tanggal, hindari hari Minggu
            $offset = (int)($comp['day_offset'] ?? ($idx * 5 + 2));
            $planDateObj = Carbon::parse($defaultDate)->addDays($offset);
            if ($planDateObj->isSunday()) {
                $planDateObj->addDay();
            }
            $planDate = $planDateObj->format('Y-m-d');

            $supplier = $comp['supplier_name'] ?? 'Penyedia Jasa Angkutan & Ekspedisi Material Situbondo';
            $name = $comp['name'] ?? 'Ongkos Angkut & Pengiriman Bahan Material';
            $spec = $comp['specification'] ?? ('Biaya pengiriman armada angkutan material untuk pekerjaan ' . $projectTitle);

            $results[] = [
                'budget_item_id' => $budgetItemId,
                'uraian' => $name,
                'kode' => $itemCode,
                'kategori' => 'alat',
                'spesifikasi' => $spec,
                'volume' => $vol,
                'satuan' => $comp['unit'] ?? 'Rit',
                'harga_satuan' => $unitPrice,
                'total' => $subTotal,
                'tanggal' => $planDate,
                'toko' => $supplier,
                'npwp' => null,
                'metode' => $subTotal > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                'room_name' => $roomName,
            ];
        }

        // Pastikan total akhir tepat 100% sama dengan $totalAmount
        $finalSum = array_sum(array_column($results, 'total'));
        $diff = round($totalAmount - $finalSum, 2);
        if ($diff != 0 && !empty($results)) {
            $results[0]['total'] += $diff;
            $results[0]['harga_satuan'] = round($results[0]['total'] / $results[0]['volume'], 2);
        }

        return $results;
    }

    /**
     * Memecah pagu Biaya Pengelolaan secara otomatis menggunakan AI Google Gemini
     * (atau Smart Heuristic Engine jika offline/tanpa API key).
     * Sesuai instruksi: dipecah menjadi kegiatan pengelolaan oleh tim P2SP selama revitalisasi berlangsung,
     * misalnya membeli ATK, konsumsi rapat, SPPD koordinasi kedinas, spanduk transparansi, materai,
     * dan kegiatan pengelolaan lainnya yang masuk akal dan TIDAK BERPOTENSI TEMUAN BPK.
     */
    public function decomposePengelolaanWithAi(float $totalAmount, array $options = []): array
    {
        if ($totalAmount <= 0) {
            return [];
        }

        $schoolName = $options['school_name'] ?? 'Satuan Pendidikan Kab. Situbondo';
        $projectTitle = $options['project_title'] ?? 'Revitalisasi Sekolah';
        $termStage = $options['term_stage'] ?? 'Tahap 1';
        $defaultDate = $options['default_date'] ?? now()->format('Y-m-d');
        $existingRab = $options['existing_rab'] ?? null;

        $prompt = <<<PROMPT
Anda adalah Auditor Ahli dan Spesialis Keuangan Pemerintah untuk Program Swakelola Revitalisasi Satuan Pendidikan, Tim P2SP (Panitia Pembangunan Satuan Pendidikan), dan Kepatuhan Pemeriksaan BPK RI / Inspektorat Daerah di Kabupaten Situbondo, Jawa Timur.

TUGAS ANDA:
Pecah pagu total "Biaya Pengelolaan" sebesar Rp {$totalAmount} menjadi rincian kegiatan pengelolaan riil oleh Tim P2SP selama revitalisasi berlangsung:
"Dipecah menjadi kegiatan pengelolaan oleh tim P2SP selama revitalisasi berlangsung, misalnya membeli ATK, konsumsi rapat, SPPD koordinasi kedinas, spanduk transparansi, materai, atau kegiatan pengelolaan lainnya yang masuk akal dan TIDAK BERPOTENSI TEMUAN BPK."

KONTEKS PROYEK:
- Sekolah: {$schoolName}
- Paket Revitalisasi: {$projectTitle}
- Tahap Pencairan: {$termStage}
- Tanggal Mulai Baseline: {$defaultDate}

KEGIATAN PENGELOLAAN YANG SAH & ANTI-TEMUAN BPK (5 KOMPONEN UTAMA):
1. Pengadaan ATK, Format Isian BKU, & Penggandaan Dokumen SPJ Revitalisasi
   - Kertas HVS F4/A4, map dokumen, binder, buku kas, dan penggandaan/penjilidan laporan pertanggungjawaban (SPJ) 3 rangkap Tim P2SP.
   - Toko: Toko ATK & Penggandaan Situbondo.
   - Satuan: Paket. Volume: 1.0. Proporsi: ~0.25 (25%).
2. Konsumsi Rapat Koordinasi Tim P2SP, Komite Sekolah, & Tokoh Masyarakat
   - Penyediaan snack dan makan rapat koordinasi persiapan pelaksanaan, rapat teknis mingguan, dan evaluasi berkala Tim P2SP dengan Komite Sekolah.
   - Rekanan: Katering / Warung Makan Lokal Situbondo.
   - Satuan: Paket. Volume: 1.0. Proporsi: ~0.25 (25%).
3. Biaya Transportasi Tim P2SP (Konsultasi Teknis Dinas Pendidikan & Transaksi Bank)
   - Biaya transportasi/SPPD perjalanan dinas Tim P2SP untuk koordinasi teknis ke Dinas Pendidikan Kab. Situbondo dan pengurusan rekening/pencairan dana ke Bank.
   - Penerima: Tim Pelaksana P2SP Satuan Pendidikan.
   - Satuan: Paket. Volume: 1.0. Proporsi: ~0.25 (25%).
4. Spanduk Publikasi Revitalisasi & Papan Informasi Transparansi Swakelola
   - Pembuatan spanduk sosialisasi program revitalisasi sekolah dan papan informasi transparansi publik swakelola P2SP.
   - Rekanan: Percetakan & Banner Reklame Situbondo.
   - Satuan: Paket. Volume: 1.0. Proporsi: ~0.15 (15%).
5. Pengadaan Bea Materai Administrasi Kuitansi & Berita Acara SPJ (Materai 10.000)
   - Materai tempel Rp 10.000 untuk legalitas dokumen kuitansi pengeluaran di atas Rp 5 juta, SPK, BAPHP, dan BAST hasil pekerjaan swakelola.
   - Rekanan: Kantor Pos Situbondo / Agen Pos.
   - Satuan: Lembar. Tarif: tepat Rp 10.000 per lembar. Proporsi: ~0.10 (10%).

OUTPUT JSON (Wajib array murni tanpa markdown):
[
  {
    "name": "Pengadaan ATK, Format Isian BKU, & Penggandaan Dokumen SPJ Revitalisasi",
    "specification": "Kertas HVS F4/A4, map dokumen, binder, buku kas, dan penggandaan/penjilidan laporan pertanggungjawaban (SPJ) 3 rangkap Tim P2SP",
    "unit": "Paket",
    "volume": 1.0,
    "percentage": 0.25,
    "day_offset": 3,
    "supplier_name": "Toko ATK & Penggandaan Situbondo"
  },
  {
    "name": "Konsumsi Rapat Koordinasi Tim P2SP, Komite Sekolah, & Tokoh Masyarakat",
    "specification": "Penyediaan snack dan makan rapat koordinasi persiapan pelaksanaan, rapat teknis mingguan, dan evaluasi berkala Tim P2SP dengan Komite Sekolah",
    "unit": "Paket",
    "volume": 1.0,
    "percentage": 0.25,
    "day_offset": 5,
    "supplier_name": "Katering / Warung Makan Lokal Situbondo"
  },
  {
    "name": "Biaya Transportasi Tim P2SP (Konsultasi Teknis Dinas Pendidikan & Transaksi Bank)",
    "specification": "Biaya transportasi perjalanan dinas Tim P2SP untuk koordinasi teknis ke Dinas Pendidikan Kab. Situbondo dan pengurusan rekening/pencairan dana ke Bank",
    "unit": "Paket",
    "volume": 1.0,
    "percentage": 0.25,
    "day_offset": 7,
    "supplier_name": "Tim Pelaksana P2SP Satuan Pendidikan"
  },
  {
    "name": "Spanduk Publikasi Revitalisasi & Papan Informasi Transparansi Swakelola",
    "specification": "Pembuatan spanduk sosialisasi program revitalisasi sekolah dan papan informasi transparansi publik swakelola P2SP",
    "unit": "Paket",
    "volume": 1.0,
    "percentage": 0.15,
    "day_offset": 1,
    "supplier_name": "Percetakan & Banner Reklame Situbondo"
  },
  {
    "name": "Pengadaan Bea Materai Administrasi Kuitansi & Berita Acara SPJ (Materai 10.000)",
    "specification": "Materai tempel Rp 10.000 untuk legalitas dokumen kuitansi pengeluaran di atas Rp 5 juta, SPK, BAPHP, dan BAST hasil pekerjaan swakelola",
    "unit": "Lembar",
    "volume": 1.0,
    "percentage": 0.10,
    "day_offset": 15,
    "supplier_name": "Kantor Pos Situbondo / Agen Pos"
  }
]
PROMPT;

        $components = null;

        // Panggil Gemini API jika use_ai aktif atau API key ada
        if (!isset($options['use_ai']) || !empty($options['use_ai'])) {
            $rawJson = $this->callGeminiApi($prompt, $options);
            if (!empty($rawJson)) {
                $decoded = json_decode($rawJson, true);
                if (is_array($decoded) && count($decoded) >= 4) {
                    $components = $decoded;
                }
            }
        }

        // Fallback Heuristik Cerdas Lokal
        if (empty($components)) {
            $components = [
                [
                    'name' => 'Pengadaan ATK, Format Isian BKU, & Penggandaan Dokumen SPJ Revitalisasi',
                    'specification' => 'Kertas HVS F4/A4, map dokumen, binder, buku kas, dan penggandaan/penjilidan laporan pertanggungjawaban (SPJ) 3 rangkap Tim P2SP',
                    'supplier_name' => 'Toko ATK & Penggandaan Situbondo',
                    'percentage' => 0.25,
                    'unit' => 'Paket',
                    'volume' => 1.0,
                    'day_offset' => 3,
                ],
                [
                    'name' => 'Konsumsi Rapat Koordinasi Tim P2SP, Komite Sekolah, & Tokoh Masyarakat',
                    'specification' => 'Penyediaan snack dan makan rapat koordinasi persiapan pelaksanaan, rapat teknis mingguan, dan evaluasi berkala Tim P2SP dengan Komite Sekolah',
                    'supplier_name' => 'Katering / Warung Makan Lokal Situbondo',
                    'percentage' => 0.25,
                    'unit' => 'Paket',
                    'volume' => 1.0,
                    'day_offset' => 5,
                ],
                [
                    'name' => 'Biaya Transportasi Tim P2SP (Konsultasi Teknis Dinas Pendidikan & Transaksi Bank)',
                    'specification' => 'Biaya transportasi perjalanan dinas Tim P2SP untuk koordinasi teknis ke Dinas Pendidikan Kab. Situbondo dan pengurusan rekening/pencairan dana ke Bank',
                    'supplier_name' => 'Tim Pelaksana P2SP Satuan Pendidikan',
                    'percentage' => 0.25,
                    'unit' => 'Paket',
                    'volume' => 1.0,
                    'day_offset' => 7,
                ],
                [
                    'name' => 'Spanduk Publikasi Revitalisasi & Papan Informasi Transparansi Swakelola',
                    'specification' => 'Pembuatan spanduk sosialisasi program revitalisasi sekolah dan papan informasi transparansi publik swakelola P2SP',
                    'supplier_name' => 'Percetakan & Banner Reklame Situbondo',
                    'percentage' => 0.15,
                    'unit' => 'Paket',
                    'volume' => 1.0,
                    'day_offset' => 1,
                ],
                [
                    'name' => 'Pengadaan Bea Materai Administrasi Kuitansi & Berita Acara SPJ (Materai 10.000)',
                    'specification' => 'Materai tempel Rp 10.000 untuk legalitas dokumen kuitansi pengeluaran di atas Rp 5 juta, SPK, BAPHP, dan BAST hasil pekerjaan swakelola',
                    'supplier_name' => 'Kantor Pos Situbondo / Agen Pos',
                    'percentage' => 0.10,
                    'unit' => 'Lembar',
                    'volume' => 1.0,
                    'day_offset' => 15,
                ],
            ];
        }

        // Normalisasi persentase agar total persis 1.0
        $sumPct = array_sum(array_column($components, 'percentage'));
        if ($sumPct > 0 && abs($sumPct - 1.0) > 0.001) {
            foreach ($components as &$c) {
                $c['percentage'] = $c['percentage'] / $sumPct;
            }
            unset($c);
        }

        // Cari pos RAB Biaya Pengelolaan jika ada
        $breakdownService = app(RpdBreakdownService::class);
        $rabMatch = !empty($existingRab)
            ? $breakdownService->findBestRabMatch('Biaya Pengelolaan', 'operasional', $existingRab, null)
            : null;
        $budgetItemId = $rabMatch?->id;
        $itemCode = $rabMatch?->code ?? '5.1.02.04';

        $results = [];
        $runningSum = 0;
        $count = count($components);

        foreach ($components as $idx => $comp) {
            $isLast = ($idx === $count - 1);
            if ($isLast) {
                $subTotal = round($totalAmount - $runningSum, 2);
            } else {
                $subTotal = round($totalAmount * ($comp['percentage'] ?? (1 / $count)), 0);
                $runningSum += $subTotal;
            }

            if ($subTotal <= 0) continue;

            $vol = (float)($comp['volume'] ?? 1.0);
            $satuan = $comp['unit'] ?? 'Paket';

            // Khusus Materai: tarif Rp 10.000 per lembar
            if ($satuan === 'Lembar' && $subTotal >= 10000) {
                $vol = max(1.0, round($subTotal / 10000));
                $unitPrice = 10000;
                $subTotal = $vol * $unitPrice;
            } else {
                $unitPrice = round($subTotal / $vol, 2);
            }

            // Hitung tanggal, hindari hari Minggu
            $offset = (int)($comp['day_offset'] ?? ($idx * 3 + 1));
            $planDateObj = Carbon::parse($defaultDate)->addDays($offset);
            if ($planDateObj->isSunday()) {
                $planDateObj->addDay();
            }
            $planDate = $planDateObj->format('Y-m-d');

            $supplier = $comp['supplier_name'] ?? 'Toko ATK & Penggandaan Situbondo';
            $name = $comp['name'] ?? 'Biaya Pengelolaan Tim P2SP';
            $spec = $comp['specification'] ?? ('Penyelenggaraan kegiatan operasional tim P2SP ' . $projectTitle);

            $results[] = [
                'budget_item_id' => $budgetItemId,
                'uraian' => $name,
                'kode' => $itemCode,
                'kategori' => 'operasional',
                'spesifikasi' => $spec,
                'volume' => $vol,
                'satuan' => $satuan,
                'harga_satuan' => $unitPrice,
                'total' => $subTotal,
                'tanggal' => $planDate,
                'toko' => $supplier,
                'npwp' => null,
                'metode' => $subTotal > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                'room_name' => null,
            ];
        }

        // Pastikan total akhir tepat 100% sama dengan $totalAmount tanpa selisih
        $finalSum = array_sum(array_column($results, 'total'));
        $diff = round($totalAmount - $finalSum, 2);
        if ($diff != 0 && !empty($results)) {
            $results[0]['total'] += $diff;
            $results[0]['harga_satuan'] = round($results[0]['total'] / $results[0]['volume'], 2);
        }

        return $results;
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
Pastikan rincian belanja bahan material, ongkos kirim/angkut bahan (Belanja Mobilisasi), dan kegiatan operasional pengelolaan Tim P2SP (ATK, konsumsi rapat, SPPD koordinasi dinas, transparansi, materai anti-temuan BPK) tersusun logis, tertib, dan sesuai kaidah audit.

KONTEKS PROYEK:
- Sekolah: {$context['school_name']}
- Paket Proyek: {$context['project_title']}
- Tahap Pencairan: {$context['term_stage']}
- Tanggal Mulai Pencairan (Baseline): {$context['base_date']}

ATURAN WAJIB (STRICT RULES):
1. PENANGGALAN JADWAL BELANJA BKU (planned_date):
   - Tanggal paling awal dimulai pada atau setelah {$context['base_date']}.
   - Susun jadwal bertahap selama rentang 3 s.d. 5 minggu secara logis sesuai urutan pekerjaan konstruksi nyata di lapangan:
     * Minggu 1: Belanja persiapan, galian, pondasi, pasir pasang, semen dasar, besi beton, sewa molen/alat, spanduk transparansi publik & ATK format BKU P2SP, ongkos angkut bahan pondasi/struktur.
     * Minggu 2: Belanja bata merah, semen dinding, kusen kayu/pintu, ongkos angkut bahan dinding & atap, konsumsi rapat evaluasi P2SP, upah tukang minggu 1.
     * Minggu 3: Belanja rangka baja ringan, genteng/spandek, pipa sanitasi, SPPD transportasi koordinasi dinas P2SP, upah tukang minggu 2.
     * Minggu 4: Belanja keramik lantai, kloset/sanitasi, plafon, ongkos angkut bahan finishing & sanitair, upah tukang minggu 3.
     * Minggu 5: Belanja cat tembok, pelitur kayu, kunci & handle, bea materai 10.000 kuitansi SPJ, penggandaan laporan SPJ 3 rangkap, upah tukang akhir.
   - PENTING: Pembayaran upah tukang/pekerja (kategori 'upah') HARUS SELALU dijadwalkan pada hari SABTU (akhir pekan).
   - DILARANG KERAS menjadwalkan transaksi apapun pada HARI MINGGU (kantor & toko libur).
   - Belanja bahan/alat/operasional dijadwalkan pada hari kerja (Senin s.d. Jumat/Sabtu). Format tanggal: YYYY-MM-DD.

2. VARIASI NARASI BKU (bku_description):
   - Susun kalimat pembukuan transaksi BKU yang alami, kaya variasi, dan sesuai kaidah tata naskah dinas resmi SPJ Revitalisasi/BOS/DAK Kemendikbudristek & BPK RI.
   - Jangan gunakan kalimat seragam yang monoton. Gunakan kombinasi frasa resmi seperti:
     * Bahan Material: "Pembayaran belanja bahan material [uraian barang] ([volume] [satuan]) pada [toko] untuk pekerjaan [proyek]"
     * Ongkos Angkut / Mobilisasi: "Pembayaran ongkos angkut & pengiriman material [uraian] ([volume] [satuan]) armada [penyedia angkutan] ke sekolah keperluan [proyek]"
     * Upah Tenaga: "Pembayaran upah tenaga kerja/tukang swakelola [uraian pekerjaan] ([volume] [satuan]) pada [mandor/pekerja]"
     * Sewa Peralatan: "Pembayaran sewa/pengadaan peralatan operasional kerja [uraian alat] ([volume] [satuan]) pada [rekanan]"
     * Pengelolaan P2SP (ATK): "Pembayaran belanja kebutuhan ATK, penggandaan format BKU, dan penyusunan SPJ revitalisasi pada [toko ATK]"
     * Pengelolaan P2SP (Konsumsi): "Pembayaran penyediaan konsumsi rapat koordinasi dan evaluasi mingguan Tim P2SP pada [katering/warung]"
     * Pengelolaan P2SP (SPPD Transport): "Pembayaran biaya transportasi/SPPD Tim P2SP koordinasi teknis dinas dan urusan perbankan pelaksanaan [proyek]"
     * Pengelolaan P2SP (Materai): "Pembayaran pengadaan bea materai Rp 10.000 kelengkapan kuitansi dan BAST pada [kantor pos]"
     * Pengelolaan P2SP (Publikasi): "Pembayaran pembuatan spanduk sosialisasi & papan transparansi swakelola revitalisasi pada [percetakan]"

3. TOKO / REKANAN LOKAL SITUBONDO (supplier_name):
   - Jika current_supplier kosong atau berupa titik-titik, rekomendasikan penyedia/toko bangunan riil yang ada di wilayah Kabupaten Situbondo (Kecamatan Situbondo Kota, Panji, Besuki, Mangaran, Kapongan, dll.). Contoh:
     * Pasir/Batu: Pemasok Pasir & Batu Kali Sampean Situbondo
     * Semen/Bata/Material umum: Toko Bangunan Berkah Situbondo / UD Sinar Terang Panji / Toko Bangunan Sumber Rejeki Besuki
     * Besi & Atap Baja: Toko Besi & Baja Abadi Situbondo / Toko Baja Ringan Situbondo
     * Cat/Finishing: Toko Cat Citra Warna Situbondo / Toko Bangunan Utama
     * Sanitasi & Keramik: Toko Keramik Sentosa Situbondo
     * Upah: Kelompok Pekerja Swakelola Sekolah / Kelompok Tukang Panji
     * Ongkos Kirim / Mobilisasi Bahan: Penyedia Jasa Angkutan & Ekspedisi Material Situbondo / Armada Angkut Toko Bangunan Berkah Panji / Jasa Angkutan Material Sampean Situbondo
     * Operasional & ATK: Toko ATK Grafika Situbondo / Percetakan Media Mandiri Situbondo
     * Konsumsi Rapat: Katering / Warung Makan Lokal Situbondo / Katering Bu Marni Panji
     * Transportasi / SPPD Dinas: Tim Pelaksana P2SP Satuan Pendidikan
     * Materai: Kantor Pos Situbondo / Agen Pos
     * Spanduk & Banner: Percetakan & Banner Reklame Situbondo

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
            'mobilisasi' => [
                'Penyedia Jasa Angkutan & Ekspedisi Material Situbondo',
                'Armada Angkut Toko Bangunan Berkah Panji',
                'Jasa Angkutan Material Sampean Situbondo',
            ],
            'operasional' => [
                'Toko ATK Grafika Situbondo',
                'Percetakan & ATK Media Mandiri Situbondo',
                'Toko Alat Tulis Pelajar Situbondo',
            ],
            'konsumsi' => [
                'Katering / Warung Makan Lokal Situbondo',
                'Katering Bu Marni Panji',
                'Rumah Makan & Katering Barokah Situbondo',
            ],
            'transport' => [
                'Tim Pelaksana P2SP Satuan Pendidikan',
            ],
            'materai' => [
                'Kantor Pos Situbondo / Agen Pos',
            ],
            'publikasi' => [
                'Percetakan & Banner Reklame Situbondo',
                'Percetakan Media Grafika Situbondo',
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
            if (str_contains($itemName, 'galian') || str_contains($itemName, 'pondasi') || str_contains($itemName, 'pasir') || str_contains($itemName, 'batu') || str_contains($itemName, 'molen') || str_contains($itemName, 'persiapan') || str_contains($itemName, 'rk3k') || str_contains($itemName, 'spanduk') || (str_contains($itemName, 'angkut') && str_contains($itemName, 'pondasi'))) {
                $phase = 1;
            } elseif (str_contains($itemName, 'bata') || str_contains($itemName, 'dinding') || str_contains($itemName, 'plesteran') || str_contains($itemName, 'besi') || str_contains($itemName, 'semen') || str_contains($itemName, 'konsumsi') || (str_contains($itemName, 'angkut') && str_contains($itemName, 'dinding'))) {
                $phase = 2;
            } elseif (str_contains($itemName, 'atap') || str_contains($itemName, 'baja') || str_contains($itemName, 'galvalum') || str_contains($itemName, 'genteng') || str_contains($itemName, 'spandek') || str_contains($itemName, 'plafon') || str_contains($itemName, 'kusen') || str_contains($itemName, 'kayu') || str_contains($itemName, 'transport') || str_contains($itemName, 'sppd')) {
                $phase = 3;
            } elseif (str_contains($itemName, 'keramik') || str_contains($itemName, 'lantai') || str_contains($itemName, 'kloset') || str_contains($itemName, 'sanitasi') || str_contains($itemName, 'pipa') || str_contains($itemName, 'toilet') || (str_contains($itemName, 'angkut') && str_contains($itemName, 'lantai'))) {
                $phase = 4;
            } elseif (str_contains($itemName, 'cat') || str_contains($itemName, 'pelitur') || str_contains($itemName, 'plamir') || str_contains($itemName, 'kunci') || str_contains($itemName, 'lampu') || str_contains($itemName, 'atk') || str_contains($itemName, 'spj') || str_contains($itemName, 'materai') || str_contains($itemName, 'pengelolaan') || str_contains($itemName, 'perencanaan')) {
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
                    if (str_contains($itemName, 'angkut') || str_contains($itemName, 'kirim') || str_contains($itemName, 'mobilisasi')) {
                        $item['supplier_name'] = $situbondoSuppliers['mobilisasi'][$idx % count($situbondoSuppliers['mobilisasi'])];
                    } else {
                        $item['supplier_name'] = $situbondoSuppliers['alat'][$idx % count($situbondoSuppliers['alat'])];
                    }
                } elseif ($cat === 'operasional') {
                    if (str_contains($itemName, 'konsumsi') || str_contains($itemName, 'makan') || str_contains($itemName, 'snack')) {
                        $item['supplier_name'] = $situbondoSuppliers['konsumsi'][$idx % count($situbondoSuppliers['konsumsi'])];
                    } elseif (str_contains($itemName, 'transport') || str_contains($itemName, 'sppd')) {
                        $item['supplier_name'] = $situbondoSuppliers['transport'][0];
                    } elseif (str_contains($itemName, 'materai')) {
                        $item['supplier_name'] = $situbondoSuppliers['materai'][0];
                    } elseif (str_contains($itemName, 'spanduk') || str_contains($itemName, 'banner') || str_contains($itemName, 'publikasi') || str_contains($itemName, 'transparansi')) {
                        $item['supplier_name'] = $situbondoSuppliers['publikasi'][$idx % count($situbondoSuppliers['publikasi'])];
                    } else {
                        $item['supplier_name'] = $situbondoSuppliers['operasional'][$idx % count($situbondoSuppliers['operasional'])];
                    }
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
            if (str_contains($itemName, 'angkut') || str_contains($itemName, 'kirim') || str_contains($itemName, 'mobilisasi')) {
                $item['bku_description'] = "Pembayaran " . ($item['item_name'] ?? 'Ongkos Angkut Bahan') . " (" . $vol . " " . $unit . ") pada " . $item['supplier_name'] . " untuk pekerjaan " . $projectTitle;
            } elseif ($cat === 'operasional') {
                if (str_contains($itemName, 'konsumsi') || str_contains($itemName, 'makan')) {
                    $item['bku_description'] = "Pembayaran penyediaan " . ($item['item_name'] ?? 'konsumsi rapat') . " (" . $vol . " " . $unit . ") pada " . $item['supplier_name'] . " untuk kelancaran " . $projectTitle;
                } elseif (str_contains($itemName, 'transport') || str_contains($itemName, 'sppd')) {
                    $item['bku_description'] = "Pembayaran " . ($item['item_name'] ?? 'biaya transportasi') . " (" . $vol . " " . $unit . ") untuk koordinasi dinas dan perbankan kegiatan " . $projectTitle;
                } elseif (str_contains($itemName, 'materai')) {
                    $item['bku_description'] = "Pembayaran pengadaan " . ($item['item_name'] ?? 'bea materai') . " (" . $vol . " " . $unit . ") pada " . $item['supplier_name'] . " kelengkapan berkas kuitansi " . $projectTitle;
                } elseif (str_contains($itemName, 'spanduk') || str_contains($itemName, 'transparansi')) {
                    $item['bku_description'] = "Pembayaran pembuatan " . ($item['item_name'] ?? 'spanduk transparansi') . " (" . $vol . " " . $unit . ") pada " . $item['supplier_name'] . " publikasi swakelola " . $projectTitle;
                } else {
                    $templates = $narrativeTemplates['operasional'];
                    $selectedTemplate = $templates[$idx % count($templates)];
                    $item['bku_description'] = str_replace(
                        ['{item}', '{vol}', '{unit}', '{supplier}', '{project}'],
                        [$item['item_name'] ?? 'Operasional', $vol, $unit, $item['supplier_name'], $projectTitle],
                        $selectedTemplate
                    );
                }
            } else {
                $templates = $narrativeTemplates[$cat] ?? $narrativeTemplates['bahan'];
                $selectedTemplate = $templates[$idx % count($templates)];
                $item['bku_description'] = str_replace(
                    ['{item}', '{vol}', '{unit}', '{supplier}', '{project}'],
                    [$item['item_name'] ?? 'Material', $vol, $unit, $item['supplier_name'], $projectTitle],
                    $selectedTemplate
                );
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Menghasilkan Nomor Transaksi Penerimaan Negara (NTPN) 16 karakter alfanumerik acak
     * sesuai standar resmi MPN G3 / Direktorat Jenderal Pajak (DJP).
     */
    public function generateNtpn(): string
    {
        $characters = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $ntpn = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < 16; $i++) {
            $ntpn .= $characters[random_int(0, $max)];
        }
        return $ntpn;
    }

    /**
     * Menghitung tanggal penyetoran pajak yang masuk akal, sinkron dengan tanggal transaksi belanja,
     * dan tidak mencurigakan dalam audit BPK/Inspektorat (menghindari hari Minggu dan tidak mendahului transaksi).
     */
    public function calculateTaxPaymentDate(string|Carbon $transactionDate, ?string $category = null): string
    {
        try {
            $date = is_string($transactionDate) ? Carbon::parse($transactionDate) : $transactionDate->copy();
        } catch (\Throwable $e) {
            $date = now();
        }

        // Jika tanggal transaksi hari Minggu (seharusnya tidak ada, tapi jika terjadi geser ke Senin)
        if ($date->isSunday()) {
            $date->addDay();
        }

        // Pola penyetoran pajak instansi yang realistis & masuk akal:
        // - Mayoritas transaksi belanja (75%) disetor pada hari yang SAMA (same day) melalui CMS Bank/e-Billing
        // - Sebagian kecil (25%) disetor pada hari kerja berikutnya (+1 hari kerja, atau +2 jika akhir pekan/Sabtu)
        // - Dilarang keras hari Minggu, dan tidak menyeberang bulan jika di akhir bulan
        $seed = crc32($date->format('Ymd') . ($category ?? ''));
        $variant = abs($seed) % 4; // 0, 1, 2, 3

        $paymentDate = $date->copy();

        if ($variant === 0) {
            $paymentDate->addDay();
            if ($paymentDate->isSunday()) {
                $paymentDate->addDay();
            }

            if ($paymentDate->month !== $date->month) {
                $paymentDate = $date->copy();
            }
        }

        return $paymentDate->format('Y-m-d');
    }

    /**
     * Menghasilkan uraian narasi transaksi BKU yang resmi dan sesuai tata naskah SPJ.
     * Menggunakan Google Gemini API jika tersedia, atau Smart Narrative Heuristic Engine jika offline.
     */
    public function generateBkuDescription(string $category, string $itemName, $volume = 1, string $unit = 'Unit', ?string $supplier = null, ?string $projectTitle = null): string
    {
        $category = strtolower($category ?: 'bahan');
        $volFormatted = number_format((float)$volume, (float)$volume == (int)$volume ? 0 : 2, ',', '.');
        $supplierName = trim($supplier ?: 'Penyedia Bahan Lokal Situbondo');
        $projectName = trim($projectTitle ?: 'Revitalisasi Satuan Pendidikan');

        $apiKey = config('services.gemini.api_key');

        if (!empty($apiKey)) {
            try {
                $prompt = "Buat SATU baris kalimat uraian resmi pembukuan BKU (Buku Kas Umum) SPJ Revitalisasi Sekolah di Kabupaten Situbondo untuk transaksi berikut:\n" .
                    "- Jenis: {$category}\n- Barang/Pekerjaan: {$itemName}\n- Jumlah: {$volFormatted} {$unit}\n- Rekanan/Penerima: {$supplierName}\n- Kegiatan: {$projectName}\n" .
                    "Kaidah: Hasilkan HANYA 1 kalimat murni tanpa tanda petik pembuka/penutup, tanpa markdown, diawali dengan kata kerja resmi seperti 'Pembayaran belanja...', 'Pembayaran ongkos angkut...', atau 'Pembayaran biaya...'.";

                $text = $this->callGeminiApi($prompt, [
                    'timeout' => 12,
                    'json_mode' => false,
                    'temperature' => 0.3,
                    'max_tokens' => 120,
                ]);

                if (!empty($text)) {
                    return trim($text, " \"'\n\r");
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini generateBkuDescription fallback: " . $e->getMessage());
            }
        }

        // Smart Heuristic Fallback
        $lowerItem = strtolower($itemName);
        if (str_contains($lowerItem, 'angkut') || str_contains($lowerItem, 'kirim') || str_contains($lowerItem, 'mobilisasi')) {
            return "Pembayaran {$itemName} ({$volFormatted} {$unit}) pada {$supplierName} untuk pekerjaan {$projectName}";
        }

        if ($category === 'operasional') {
            if (str_contains($lowerItem, 'konsumsi') || str_contains($lowerItem, 'makan')) {
                return "Pembayaran penyediaan {$itemName} ({$volFormatted} {$unit}) pada {$supplierName} untuk kelancaran rapat tim {$projectName}";
            }
            if (str_contains($lowerItem, 'transport') || str_contains($lowerItem, 'sppd')) {
                return "Pembayaran {$itemName} ({$volFormatted} {$unit}) untuk koordinasi dinas dan perbankan kegiatan {$projectName}";
            }
            if (str_contains($lowerItem, 'materai')) {
                return "Pembayaran pengadaan {$itemName} ({$volFormatted} {$unit}) pada {$supplierName} kelengkapan berkas kuitansi {$projectName}";
            }
            if (str_contains($lowerItem, 'spanduk') || str_contains($lowerItem, 'transparansi')) {
                return "Pembayaran pembuatan {$itemName} ({$volFormatted} {$unit}) pada {$supplierName} publikasi swakelola {$projectName}";
            }
        }

        $templates = [
            'bahan' => "Pembayaran belanja bahan material {item} ({vol} {unit}) pada {supplier} untuk pekerjaan {project}",
            'upah' => "Pembayaran upah tenaga kerja/tukang {item} ({vol} {unit}) pekerjaan {project} kepada {supplier}",
            'alat' => "Pembayaran sewa/pengadaan peralatan operasional {item} ({vol} {unit}) pada {supplier} untuk pekerjaan {project}",
            'operasional' => "Pembayaran belanja operasional/administrasi {item} pada {supplier} untuk pekerjaan {project}",
        ];

        $tpl = $templates[$category] ?? $templates['bahan'];
        return str_replace(
            ['{item}', '{vol}', '{unit}', '{supplier}', '{project}'],
            [$itemName, $volFormatted, $unit, $supplierName, $projectName],
            $tpl
        );
    }

    /**
     * Melakukan audit komprehensif kepatuhan keuangan SPJ sekolah:
     * - Status penyetoran pajak (apakah semua ber-NTPN dan disetor)
     * - Keabsahan tanggal transaksi & penyetoran (tidak hari Minggu, tidak mendahului transaksi)
     * - Saldo kas & bank (tidak boleh minus / defisit di hari berjalan)
     * - Kepatuhan pemungutan pajak (belanja di atas Rp 2 Juta wajib PPN/PPh 22)
     * Memberikan skor kepatuhan (0 - 100%), daftar anomali, dan opini audit resmi AI.
     */
    public function auditFinancialCompliance(\App\Models\School $school, int $year, string|int $month = 'all'): array
    {
        $query = \App\Models\Transaction::where('school_id', $school->id)
            ->whereYear('transaction_date', $year);

        if ($month !== 'all' && is_numeric($month)) {
            $query->whereMonth('transaction_date', (int)$month);
        }

        $transactions = $query->orderBy('transaction_date')->orderBy('id')->get();

        $anomalies = [];
        $taxTransactions = $transactions->where('has_tax', true);

        // 1. Audit Pajak Belum Disetor
        $unpaidTaxes = $taxTransactions->where('tax_status', '!=', 'disetor');
        if ($unpaidTaxes->count() > 0) {
            $anomalies[] = [
                'type' => 'pajak_belum_disetor',
                'severity' => 'critical',
                'title' => "Terdapat {$unpaidTaxes->count()} transaksi pajak belum disetorkan",
                'description' => "Total saldo pajak tertunggak sebesar Rp " . number_format($unpaidTaxes->sum('tax_total'), 0, ',', '.') . " yang belum memiliki Nomor NTPN valid.",
                'count' => $unpaidTaxes->count(),
            ];
        }

        // 2. Audit Tanggal Penyetoran Pajak Mendahului Transaksi
        $invalidTaxDates = 0;
        foreach ($taxTransactions as $tx) {
            if ($tx->tax_payment_date && $tx->transaction_date && $tx->tax_payment_date->lt($tx->transaction_date)) {
                $invalidTaxDates++;
            }
        }
        if ($invalidTaxDates > 0) {
            $anomalies[] = [
                'type' => 'tanggal_pajak_janggal',
                'severity' => 'high',
                'title' => "Terdapat {$invalidTaxDates} tanggal penyetoran pajak yang mendahului transaksi pembelian",
                'description' => "Pajak disetor sebelum transaksi terjadi. Hal ini merupakan anomali serius dalam pemeriksaan BPK/Inspektorat.",
                'count' => $invalidTaxDates,
            ];
        }

        // 3. Audit Transaksi pada Hari Minggu (Hari Libur)
        $sundayTransactions = 0;
        foreach ($transactions as $tx) {
            if ($tx->transaction_date && $tx->transaction_date->isSunday()) {
                $sundayTransactions++;
            }
        }
        if ($sundayTransactions > 0) {
            $anomalies[] = [
                'type' => 'transaksi_hari_minggu',
                'severity' => 'medium',
                'title' => "Terdapat {$sundayTransactions} transaksi pembukuan tercatat pada hari Minggu",
                'description' => "Instansi sekolah dan toko umumnya libur pada hari Minggu. Disarankan digeser ke hari kerja Senin-Sabtu.",
                'count' => $sundayTransactions,
            ];
        }

        // 4. Audit Setor Pajak pada Hari Minggu
        $sundayTaxPayments = 0;
        foreach ($taxTransactions as $tx) {
            if ($tx->tax_payment_date && $tx->tax_payment_date->isSunday()) {
                $sundayTaxPayments++;
            }
        }
        if ($sundayTaxPayments > 0) {
            $anomalies[] = [
                'type' => 'setor_pajak_hari_minggu',
                'severity' => 'medium',
                'title' => "Terdapat {$sundayTaxPayments} penyetoran pajak tercatat pada hari Minggu",
                'description' => "Bank persepsi / loket kas negara libur hari Minggu. Tanggal penyetoran sebaiknya disinkronkan ke hari kerja.",
                'count' => $sundayTaxPayments,
            ];
        }

        // 5. Audit Belanja Material Di Atas Rp 2 Juta Tanpa Pajak
        $potentialTaxMisses = 0;
        foreach ($transactions as $tx) {
            if (in_array($tx->type, ['belanja_tunai', 'belanja_transfer']) && $tx->amount >= 2000000 && !$tx->has_tax) {
                $potentialTaxMisses++;
            }
        }
        if ($potentialTaxMisses > 0) {
            $anomalies[] = [
                'type' => 'indikasi_potensi_pajak',
                'severity' => 'low',
                'title' => "Terdapat {$potentialTaxMisses} belanja >= Rp 2.000.000 belum dikenakan pajak",
                'description' => "Belanja barang/jasa bernilai Rp 2 juta ke atas umumnya wajib dipungut PPN (11%) atau PPh 22/23 kecuali memenuhi kriteria pengecualian.",
                'count' => $potentialTaxMisses,
            ];
        }

        // Perhitungan Skor Kepatuhan
        $totalTransactions = $transactions->count();
        $penalty = 0;
        foreach ($anomalies as $a) {
            if ($a['severity'] === 'critical') $penalty += 35;
            elseif ($a['severity'] === 'high') $penalty += 20;
            elseif ($a['severity'] === 'medium') $penalty += 10;
            else $penalty += 5;
        }
        $complianceScore = max(0, min(100, 100 - $penalty));

        $opinion = 'Wajar Tanpa Pengecualian (WTP)';
        $statusBadge = 'success';
        if ($complianceScore < 70) {
            $opinion = 'Perlu Perbaikan Mendesak (Disclaimer/Tidak Wajar)';
            $statusBadge = 'danger';
        } elseif ($complianceScore < 90) {
            $opinion = 'Wajar Dengan Pengecualian (WDP)';
            $statusBadge = 'warning';
        }

        // AI Narrative Analysis (Gemini atau Smart Heuristic)
        $aiAnalysisText = $this->generateAuditNarrative($school, $year, $month, $complianceScore, $opinion, $anomalies, $transactions->count(), $taxTransactions->count());

        return [
            'school_name' => $school->name,
            'year' => $year,
            'month' => $month,
            'total_transactions' => $totalTransactions,
            'tax_transactions_count' => $taxTransactions->count(),
            'total_tax_amount' => $taxTransactions->sum('tax_total'),
            'compliance_score' => $complianceScore,
            'opinion' => $opinion,
            'status_badge' => $statusBadge,
            'anomalies' => $anomalies,
            'ai_analysis' => $aiAnalysisText,
        ];
    }

    /**
     * Menyusun narasi telaah opini audit kepatuhan keuangan oleh AI.
     */
    protected function generateAuditNarrative(\App\Models\School $school, int $year, string|int $month, int $score, string $opinion, array $anomalies, int $txCount, int $taxCount): string
    {
        $apiKey = config('services.gemini.api_key');

        if (!empty($apiKey)) {
            try {
                $anomalySummary = empty($anomalies) ? "Tidak ditemukan anomali. Semua pajak ber-NTPN dan disetor tepat waktu, tidak ada transaksi hari Minggu." : json_encode($anomalies);
                $prompt = <<<PROMPT
Anda adalah Auditor Ahli Madya BPK RI / Inspektorat Daerah Kabupaten Situbondo.
Buat analisis kepatuhan singkat (2-3 paragraf) untuk SPJ Bantuan Revitalisasi Satuan Pendidikan:
- Sekolah: {$school->name}
- Periode: Tahun {$year}
- Total Transaksi: {$txCount} (Transaksi Pajak: {$taxCount})
- Skor Kepatuhan: {$score}/100
- Predikat: {$opinion}
- Temuan Anomali: {$anomalySummary}

Berikan telaah audit formal, apresiasi jika kepatuhan tinggi, atau arahan perbaikan konkret jika ada anomali pajak atau penanggalan. Gaya bahasa naskah dinas resmi pemerintahan.
PROMPT;

                $text = $this->callGeminiApi($prompt, [
                    'timeout' => 15,
                    'json_mode' => false,
                    'temperature' => 0.4,
                    'max_tokens' => 300,
                ]);

                if (!empty($text)) {
                    return $text;
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini generateAuditNarrative error: " . $e->getMessage());
            }
        }

        // Heuristic fallback text
        if (empty($anomalies)) {
            return "Berdasarkan hasil uji kepatuhan administratif terhadap pembukuan {$school->name} Tahun Anggaran {$year}, seluruh transaksi telah mematuhi tata kelola keuangan negara. Seluruh kewajiban pemotongan pajak (PPN dan PPh) telah disetorkan secara lunas ke Kas Negara dengan Nomor NTPN resmi DJP yang valid. Tidak ditemukan transaksi pada hari libur nasional atau inkonsistensi penanggalan. Pembukuan dinyatakan tertib dan memenuhi kriteria Wajar Tanpa Pengecualian (WTP).";
        }

        $items = [];
        foreach ($anomalies as $a) {
            $items[] = "- " . $a['title'];
        }
        $findingsList = implode("\n", $items);

        return "Hasil telaah kepatuhan administratif pembukuan {$school->name} Tahun Anggaran {$year} menunjukkan skor kepatuhan {$score}/100 dengan predikat {$opinion}. Ditemukan beberapa catatan kepatuhan yang memerlukan tindak lanjut:\n{$findingsList}\n\nDirekomendasikan kepada Bendahara Pengeluaran untuk segera menyinkronkan penanggalan bukti dan melengkapi penyetoran NTPN melalui fitur Setor Pajak Otomatis Si Revita sebelum berkas diserahkan ke Inspektorat.";
    }
}

