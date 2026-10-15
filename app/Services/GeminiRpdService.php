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
        $schoolKecamatan = $options['school_kecamatan'] ?? ($options['kecamatan'] ?? 'Situbondo');
        $schoolAddress = $options['school_address'] ?? ($options['address'] ?? 'Kabupaten Situbondo, Jawa Timur');
        $termStage = $options['term_stage'] ?? 'Tahap 1';

        $runtimeOptions = array_merge($options, [
            'school_name' => $schoolName,
            'school_kecamatan' => $schoolKecamatan,
            'school_address' => $schoolAddress,
            'project_title' => $projectTitle,
            'term_stage' => $termStage,
            'base_date' => $baseDate,
        ]);

        if (empty($apiKey)) {
            Log::info("GeminiRpdService: GEMINI_API_KEY tidak ditemukan, menggunakan Smart Heuristic Engine lokal Situbondo.");
            return [
                'success' => true,
                'used_ai' => false,
                'provider' => 'smart_heuristic',
                'message' => "Variasi disusun menggunakan Mesin Heuristik Cerdas Lokal Situbondo (Standar SHS/HSPK harga & toko terdekat Kec. {$schoolKecamatan}).",
                'items' => $this->applySmartHeuristicVariance($items, $baseDate, $projectTitle, $runtimeOptions),
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
                    'unit_price' => (float)($item['unit_price'] ?? 0),
                    'total_price' => (float)($item['total_price'] ?? 0),
                    'current_supplier' => $item['supplier_name'] ?? '',
                ];
            }

            $prompt = $this->buildPrompt($simplifiedItems, $runtimeOptions);

            $rawJson = $this->callGeminiApi($prompt, array_merge($options, [
                'timeout' => 35,
                'json_mode' => true,
                'temperature' => 0.4,
            ]));

            if (!empty($rawJson)) {
                $aiData = json_decode($rawJson, true);
                if (is_array($aiData)) {
                    $enhanced = $this->mergeAiOutputWithItems($items, $aiData, $baseDate, $projectTitle, $runtimeOptions);
                    return [
                        'success' => true,
                        'used_ai' => true,
                        'provider' => 'gemini_api',
                        'model' => $model,
                        'message' => "Alhamdulillah! Jadwal, narasi BKU, harga satuan Situbondo, dan toko terdekat di Kec. {$schoolKecamatan} berhasil disempurnakan oleh Google Gemini AI ({$model}).",
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
            'message' => "Koneksi ke Gemini API tidak tersedia atau kuota habis. Variasi jadwal, harga standar Situbondo, dan toko terdekat disusun menggunakan Algoritma Cerdas Lokal (Kec. {$schoolKecamatan}).",
            'items' => $this->applySmartHeuristicVariance($items, $baseDate, $projectTitle, $runtimeOptions),
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
     * Standar Harga Satuan (SHSB / HSPK) resmi Kabupaten Situbondo, Jawa Timur
     * untuk upah tenaga kerja konstruksi (HOK) dan bahan bangunan utama.
     */
    public static function getSitubondoPriceBenchmarks(): array
    {
        return [
            // UPAH TENAGA KERJA (Standar HSPK / SHS Kab. Situbondo TA 2024-2026)
            'upah_tukang' => [
                'role' => 'Tukang (Batu / Kayu / Besi / Cat / Keramik / Plafon / Plumbing)',
                'price' => 125000,
                'min' => 120000,
                'max' => 125000,
                'unit' => 'OH',
                'description' => 'Standar HOK Tukang Terampil Swakelola Revitalisasi Kabupaten Situbondo',
            ],
            'upah_pekerja' => [
                'role' => 'Pekerja / Pembantu Tukang / Kenek / Laden',
                'price' => 90000,
                'min' => 85000,
                'max' => 95000,
                'unit' => 'OH',
                'description' => 'Standar HOK Pekerja / Pembantu Tukang Swakelola Revitalisasi Kabupaten Situbondo',
            ],
            'upah_kepala_tukang' => [
                'role' => 'Kepala Tukang',
                'price' => 135000,
                'min' => 130000,
                'max' => 140000,
                'unit' => 'OH',
                'description' => 'Standar HOK Kepala Tukang Swakelola Kabupaten Situbondo',
            ],
            'upah_mandor' => [
                'role' => 'Mandor / Pengawas Lapangan Swakelola',
                'price' => 140000,
                'min' => 135000,
                'max' => 150000,
                'unit' => 'OH',
                'description' => 'Standar HOK Mandor Lapangan Kabupaten Situbondo',
            ],

            // BAHAN MATERIAL (Standar Harga Satuan Bahan Bangunan Kab. Situbondo)
            'semen_pc_50kg' => ['keywords' => ['semen portland', 'semen gresik 50', 'semen dynamix', 'semen 50', 'pcc 50'], 'price' => 68000, 'unit' => 'Zak'],
            'semen_pc_40kg' => ['keywords' => ['semen 40', 'semen gresik 40'], 'price' => 54000, 'unit' => 'Zak'],
            'pasir_pasang' => ['keywords' => ['pasir pasang', 'pasir sampean', 'pasir kali sampean'], 'price' => 185000, 'unit' => 'M³'],
            'pasir_cor' => ['keywords' => ['pasir cor', 'pasir beton'], 'price' => 200000, 'unit' => 'M³'],
            'pasir_urug' => ['keywords' => ['pasir urug', 'urugan pasir'], 'price' => 140000, 'unit' => 'M³'],
            'batu_belah' => ['keywords' => ['batu belah', 'batu kali', 'pondasi batu'], 'price' => 210000, 'unit' => 'M³'],
            'batu_split' => ['keywords' => ['split', 'kerikil cor', 'batu pecah'], 'price' => 240000, 'unit' => 'M³'],
            'bata_merah' => ['keywords' => ['bata merah', 'bata lokal'], 'price' => 800, 'unit' => 'Buah'],
            'batako' => ['keywords' => ['batako'], 'price' => 3500, 'unit' => 'Buah'],
            'besi_8' => ['keywords' => ['besi 8', 'besi beton 8', 'dia 8'], 'price' => 48000, 'unit' => 'Batang'],
            'besi_10' => ['keywords' => ['besi 10', 'besi beton 10', 'dia 10'], 'price' => 74000, 'unit' => 'Batang'],
            'besi_12' => ['keywords' => ['besi 12', 'besi beton 12', 'dia 12'], 'price' => 110000, 'unit' => 'Batang'],
            'kawat_bendrat' => ['keywords' => ['bendrat', 'kawat beton'], 'price' => 25000, 'unit' => 'Kg'],
            'baja_ringan_c75' => ['keywords' => ['baja ringan', 'truss', 'c75', 'profil c'], 'price' => 120000, 'unit' => 'Batang'],
            'reng_baja_ringan' => ['keywords' => ['reng baja', 'reng galvalum'], 'price' => 45000, 'unit' => 'Batang'],
            'genteng_karang_pilang' => ['keywords' => ['genteng karang pilang', 'genteng keramik', 'genteng nok'], 'price' => 5500, 'unit' => 'Buah'],
            'spandek_atap' => ['keywords' => ['spandek', 'atap galvalum'], 'price' => 65000, 'unit' => 'M'],
            'plafon_kalsiboard' => ['keywords' => ['kalsiboard', 'grc', 'plafon 4mm', 'langit-langit'], 'price' => 68000, 'unit' => 'Lembar'],
            'hollow_plafon' => ['keywords' => ['hollow', 'rangka hollow', 'hollow 4x4', 'hollow 2x4'], 'price' => 34000, 'unit' => 'Batang'],
            'keramik_lantai' => ['keywords' => ['keramik lantai', 'keramik 40x40', 'keramik 50x50', 'ubin lantai'], 'price' => 72000, 'unit' => 'Dus'],
            'keramik_dinding' => ['keywords' => ['keramik dinding', 'keramik 20x25', 'keramik 25x40'], 'price' => 65000, 'unit' => 'Dus'],
            'semen_nat' => ['keywords' => ['semen warna', 'oker', 'nat keramik'], 'price' => 18000, 'unit' => 'Kg'],
            'kloset_jongkok' => ['keywords' => ['kloset jongkok', 'closet jongkok', 'toto', 'ina'], 'price' => 320000, 'unit' => 'Unit'],
            'pintu_pvc' => ['keywords' => ['pintu pvc', 'pintu toilet', 'pintu kamar mandi'], 'price' => 350000, 'unit' => 'Unit'],
            'pipa_pvc_3' => ['keywords' => ['pipa 3', 'pipa aw 3'], 'price' => 115000, 'unit' => 'Batang'],
            'pipa_pvc_4' => ['keywords' => ['pipa 4', 'pipa aw 4'], 'price' => 165000, 'unit' => 'Batang'],
            'pipa_pvc_setengah' => ['keywords' => ['pipa 1/2', 'pipa aw 1/2'], 'price' => 32000, 'unit' => 'Batang'],
            'kran_air' => ['keywords' => ['kran air', 'keran'], 'price' => 75000, 'unit' => 'Buah'],
            'floor_drain' => ['keywords' => ['floor drain', 'saringan air'], 'price' => 65000, 'unit' => 'Buah'],
            'cat_tembok_interior' => ['keywords' => ['cat tembok', 'catylac', 'avitex', 'paragon'], 'price' => 145000, 'unit' => 'Galon'],
            'cat_tembok_eksterior' => ['keywords' => ['cat eksterior', 'mowilex', 'dulux weathershield'], 'price' => 215000, 'unit' => 'Galon'],
            'cat_kayu_besi' => ['keywords' => ['cat kayu', 'cat besi', 'pelitur'], 'price' => 70000, 'unit' => 'Kaleng'],
            'plamir_tembok' => ['keywords' => ['plamir', 'plamur'], 'price' => 55000, 'unit' => 'Sak'],
            'kusen_pintu_kayu' => ['keywords' => ['kusen pintu', 'pintu kayu'], 'price' => 650000, 'unit' => 'Unit'],
            'kusen_jendela' => ['keywords' => ['kusen jendela', 'jendela kaca'], 'price' => 350000, 'unit' => 'Unit'],
            'kunci_pintu' => ['keywords' => ['kunci pintu', 'handle pintu', 'gagang pintu'], 'price' => 135000, 'unit' => 'Set'],
        ];
    }

    /**
     * Direktori toko bangunan, pangkalan material, dan penyedia rekanan
     * terdekat pada 17 Kecamatan di seluruh Kabupaten Situbondo, Jawa Timur.
     */
    public static function getSitubondoDistrictStores(?string $kecamatan = null): array
    {
        $stores = [
            'Situbondo' => [
                'general' => [
                    'Toko Bangunan Berkah Jl. Basuki Rahmat Situbondo',
                    'Toko Besi & Baja Abadi Jl. Pemuda Situbondo',
                    'Depo Bangunan Situbondo Indah',
                    'Toko Bangunan Mitra Karya Situbondo',
                    'TB Jaya Abadi Jl. Irian Jaya Situbondo',
                ],
                'cat' => ['Toko Cat Citra Warna Jl. PB. Sudirman Situbondo', 'Toko Cat & Finishing Pelangi Situbondo'],
                'keramik' => ['Toko Keramik & Sanitasi Sentosa Jl. Argopuro Situbondo'],
                'kayu' => ['UD Kayu Jati Murni Situbondo'],
                'pasir' => ['Pemasok Pasir Pasang Kali Sampean Situbondo', 'Pemasok Batu Belah Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Panji-Situbondo'],
                'tukang' => ['Kelompok Tukang Swakelola Situbondo Kota', 'Kelompok Pekerja Swakelola Patokan'],
            ],
            'Panji' => [
                'general' => [
                    'UD Karunia Mega Bangunan Jl. Argopuro Mimbaan Panji',
                    'UD Panji Putra Jl. Argopuro Mimbaan Panji',
                    'Toko Bangunan Pojok Agung Jl. Jawa Krajan Mimbaan Panji',
                    'Toko Sumber Sari Jl. Argopuro Panji',
                    'UD Sinar Terang Bangunan Panji',
                    'Toko Bangunan Berkah Panji Jl. Raya Banyuwangi',
                ],
                'cat' => ['Toko Cat Cahaya Baru Mimbaan Panji', 'Toko Cat Citra Warna Panji'],
                'keramik' => ['UD Karunia Mega Bangunan Mimbaan Panji'],
                'kayu' => ['UD Kayu Jati Murni Panji'],
                'pasir' => ['Pemasok Pasir & Koral Panji Situbondo', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Sentra Pengrajin Bata Merah Panji Lor', 'Pengrajin Bata Merah Curah Jeru Panji'],
                'tukang' => ['Kelompok Tukang Swakelola Panji', 'Kelompok Pekerja Swakelola Mimbaan'],
            ],
            'Besuki' => [
                'general' => [
                    'Toko Bangunan Setia Bakti Besuki',
                    'Toko Material Sumber Rejeki Besuki',
                    'TB Bintang Terang Jl. Raya Besuki',
                    'Toko Bangunan Utama Besuki',
                ],
                'cat' => ['Panama Jaya Spesialis Cat & Pelapis Besuki', 'UD Warna Abadi Besuki'],
                'keramik' => ['Toko Bangunan Setia Bakti Besuki'],
                'kayu' => ['Toko Kayu Murah Barokah Jl. Semiring Demung Besuki', 'Pangkalan Kayu Barokah Besuki'],
                'pasir' => ['Pangkalan Pasir & Batu Kali Besuki', 'Pemasok Pasir Pasang Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Kalimas Besuki', 'UD Bata Merah Pesisir Besuki'],
                'tukang' => ['Kelompok Tukang Swakelola Besuki', 'Kelompok Pekerja Swakelola Demung'],
            ],
            'Kapongan' => [
                'general' => [
                    'Toko Bangunan Sumber Hidup Landangan Kapongan',
                    'Toko SKJ Barokah Bahan Bangunan Wonokoyo Kapongan',
                    'Toko Meubel & Bangunan Bang Hasyim Jalur Pantura Kapongan',
                    'TB Berkah Bersama Kapongan',
                ],
                'cat' => ['Toko Winda Jaya / Samurai Paint Wonokoyo Kapongan'],
                'keramik' => ['Toko Bangunan Sumber Hidup Landangan Kapongan'],
                'kayu' => ['Pengrajin Kusen Kayu Subur Kapongan', 'Pangkalan Kayu Wonokoyo Kapongan'],
                'pasir' => ['Pemasok Pasir Pasang Kali Sampean', 'Pemasok Pasir & Batu Kapongan'],
                'bata' => ['Pengrajin Bata Merah Landangan Kapongan', 'Pengrajin Bata Merah Kesambirampak'],
                'tukang' => ['Kelompok Tukang Swakelola Kapongan', 'Kelompok Pekerja Swakelola Landangan'],
            ],
            'Asembagus' => [
                'general' => [
                    'Toko Bumi Bangunan Krajan Tengah Asembagus',
                    'Subur Jaya Home / CV. Surya Kencana Jl. Raya Asembagus',
                    'Toko Sumber Berkat Jl. Raya Asembagus Trigonco',
                    'Toko Besi & Material Sinar Asembagus',
                ],
                'cat' => ['Subur Jaya Home Asembagus', 'Toko Cat Citra Warna Asembagus'],
                'keramik' => ['Toko Sumber Berkat Trigonco Asembagus'],
                'kayu' => ['Pangkalan Kayu Subur Jaya Asembagus'],
                'pasir' => ['Pemasok Pasir & Batu Kali Bantal Asembagus', 'Pemasok Pasir Sampean'],
                'bata' => ['Pengrajin Bata Merah Mojosari Asembagus', 'Sentra Bata Merah Trigonco'],
                'tukang' => ['Kelompok Tukang Swakelola Asembagus', 'Kelompok Pekerja Swakelola Kertosari'],
            ],
            'Banyuputih' => [
                'general' => [
                    'Toko Bangunan Sumber Rejeki Banyuputih (Pantura Baluran)',
                    'Toko Material Berkah Wonorejo Banyuputih',
                    'TB Timur Raya Jalur Pantura Banyuputih',
                    'TB Sumberwaru Indah Banyuputih',
                ],
                'cat' => ['Toko Bangunan Sumber Rejeki Banyuputih'],
                'keramik' => ['TB Timur Raya Banyuputih'],
                'kayu' => ['Pangkalan Kayu Jati & Sengon Banyuputih'],
                'pasir' => ['Pangkalan Pasir & Batu Kali Banyuputih', 'Pemasok Pasir Sampean'],
                'bata' => ['Pengrajin Bata Merah Sumberanyar Banyuputih'],
                'tukang' => ['Kelompok Tukang Swakelola Banyuputih', 'Kelompok Pekerja Swakelola Wonorejo'],
            ],
            'Panarukan' => [
                'general' => [
                    'Toko Bangunan Panarukan Indah Jl. Raya Panarukan',
                    'Toko Material Pelabuhan Panarukan',
                    'Toko Bangunan Barokah Paowan Panarukan',
                    'TB Kilensari Makmur Panarukan',
                ],
                'cat' => ['Toko Bangunan Panarukan Indah'],
                'keramik' => ['TB Barokah Paowan Panarukan'],
                'kayu' => ['UD Kayu Wringinanom Panarukan'],
                'pasir' => ['Pemasok Pasir & Sirtu Urug Panarukan', 'Pemasok Pasir Pasang Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Paowan Panarukan'],
                'tukang' => ['Kelompok Tukang Swakelola Panarukan', 'Kelompok Pekerja Swakelola Paowan'],
            ],
            'Mangaran' => [
                'general' => [
                    'Toko Bangunan Pantai Utara Mangaran (Tanjung Kamal)',
                    'Toko Material Barokah Tanjung Kamal Mangaran',
                    'TB Pesisir Makmur Mangaran',
                ],
                'cat' => ['Toko Bangunan Pantai Utara Mangaran'],
                'keramik' => ['Toko Material Barokah Tanjung Kamal'],
                'kayu' => ['Pangkalan Kayu Tanjung Pecinan Mangaran'],
                'pasir' => ['Pemasok Pasir & Sirtu Pesisir Mangaran', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Trebungan Mangaran'],
                'tukang' => ['Kelompok Tukang Swakelola Mangaran', 'Kelompok Pekerja Swakelola Tanjung Kamal'],
            ],
            'Kendit' => [
                'general' => [
                    'Toko Bangunan Kendit Makmur',
                    'Toko Material Pasir Putih Klatakan Kendit',
                    'TB Berkah Balung Kendit',
                ],
                'cat' => ['Toko Bangunan Kendit Makmur'],
                'keramik' => ['TB Pasir Putih Klatakan Kendit'],
                'kayu' => ['Pangkalan Kayu Balung Kendit'],
                'pasir' => ['Pemasok Pasir & Sirtu Kendit', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Rajekwesi Kendit'],
                'tukang' => ['Kelompok Tukang Swakelola Kendit', 'Kelompok Pekerja Swakelola Klatakan'],
            ],
            'Arjasa' => [
                'general' => [
                    'Toko Bangunan Arjasa Mandiri',
                    'Toko Besi & Bangunan Berkah Abadi Arjasa',
                    'TB Sumber Makmur Lamongan Arjasa',
                ],
                'cat' => ['Toko Bangunan Arjasa Mandiri'],
                'keramik' => ['TB Berkah Abadi Arjasa'],
                'kayu' => ['Pemasok Kayu & Bambu Konstruksi Arjasa', 'UD Kayu Jatisari Arjasa'],
                'pasir' => ['Pemasok Pasir Kali Deluwang Arjasa', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Kedungdowo Arjasa'],
                'tukang' => ['Kelompok Tukang Swakelola Arjasa', 'Kelompok Pekerja Swakelola Lamongan'],
            ],
            'Jangkar' => [
                'general' => [
                    'Toko Bangunan Pelabuhan Jangkar',
                    'Toko Material Sejahtera Jangkar',
                    'TB Berkah Agel Jangkar',
                ],
                'cat' => ['Toko Bangunan Pelabuhan Jangkar'],
                'keramik' => ['Toko Material Sejahtera Jangkar'],
                'kayu' => ['Pangkalan Kayu Palangan Jangkar'],
                'pasir' => ['Pemasok Pasir & Batu Jangkar', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Pesanggrahan Jangkar'],
                'tukang' => ['Kelompok Tukang Swakelola Jangkar', 'Kelompok Pekerja Swakelola Pesanggrahan'],
            ],
            'Suboh' => [
                'general' => [
                    'Toko Bangunan Suboh Jaya Jl. Raya Buduan Suboh',
                    'Toko Material Subur Buduan Suboh',
                    'TB Berkah Ketah Suboh',
                ],
                'cat' => ['Toko Bangunan Suboh Jaya'],
                'keramik' => ['Toko Material Subur Buduan Suboh'],
                'kayu' => ['Pangkalan Kayu Buduan Suboh'],
                'pasir' => ['Pemasok Pasir & Batu Suboh', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Gunung Putri Suboh'],
                'tukang' => ['Kelompok Tukang Swakelola Suboh', 'Kelompok Pekerja Swakelola Buduan'],
            ],
            'Mlandingan' => [
                'general' => [
                    'Toko Bangunan Mlandingan Raya',
                    'Toko Material Pesisir Mlandingan',
                    'TB Berkah Selomukti Mlandingan',
                ],
                'cat' => ['Toko Bangunan Mlandingan Raya'],
                'keramik' => ['Toko Material Pesisir Mlandingan'],
                'kayu' => ['Pangkalan Kayu Mlandingan Kulon'],
                'pasir' => ['Pemasok Pasir & Batu Mlandingan', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Tribungan Mlandingan'],
                'tukang' => ['Kelompok Tukang Swakelola Mlandingan', 'Kelompok Pekerja Swakelola Selomukti'],
            ],
            'Banyuglugur' => [
                'general' => [
                    'Toko Bangunan Perbatasan Banyuglugur',
                    'Toko Material Utama Kalianget Banyuglugur',
                    'TB Barokah Banyuglugur',
                ],
                'cat' => ['Toko Bangunan Perbatasan Banyuglugur'],
                'keramik' => ['Toko Material Utama Kalianget Banyuglugur'],
                'kayu' => ['Pangkalan Kayu Selobanteng Banyuglugur'],
                'pasir' => ['Pemasok Pasir & Batu Banyuglugur', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Telempong Banyuglugur'],
                'tukang' => ['Kelompok Tukang Swakelola Banyuglugur', 'Kelompok Pekerja Swakelola Kalianget'],
            ],
            'Bungatan' => [
                'general' => [
                    'Toko Bangunan Bahari Bungatan',
                    'Toko Material Pantai Bletok Bungatan',
                    'TB Berkah Selowogo Bungatan',
                ],
                'cat' => ['Toko Bangunan Bahari Bungatan'],
                'keramik' => ['Toko Material Pantai Bletok Bungatan'],
                'kayu' => ['Pangkalan Kayu Pasir Putih Bungatan'],
                'pasir' => ['Pemasok Pasir & Batu Bungatan', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Bletok Bungatan'],
                'tukang' => ['Kelompok Tukang Swakelola Bungatan', 'Kelompok Pekerja Swakelola Bletok'],
            ],
            'Jatibanteng' => [
                'general' => [
                    'Toko Bangunan Jatibanteng Makmur',
                    'Toko Material Barokah Curahsuri Jatibanteng',
                    'TB Wringinanom Jatibanteng',
                ],
                'cat' => ['Toko Bangunan Jatibanteng Makmur'],
                'keramik' => ['Toko Material Barokah Curahsuri'],
                'kayu' => ['Pangkalan Kayu Rimba Jatibanteng'],
                'pasir' => ['Pemasok Pasir Kali Jatibanteng', 'Pemasok Pasir Kali Sampean'],
                'bata' => ['Pengrajin Bata Merah Pategalan Jatibanteng'],
                'tukang' => ['Kelompok Tukang Swakelola Jatibanteng', 'Kelompok Pekerja Swakelola Curahsuri'],
            ],
            'Sumbermalang' => [
                'general' => [
                    'Toko Bangunan Lereng Argopuro Sumbermalang',
                    'Toko Material Sumber Rejeki Tlogosari Sumbermalang',
                    'TB Barokah Baderan Sumbermalang',
                ],
                'cat' => ['Toko Bangunan Lereng Argopuro Sumbermalang'],
                'keramik' => ['Toko Material Sumber Rejeki Tlogosari'],
                'kayu' => ['Pangkalan Kayu Argopuro Tlogosari'],
                'pasir' => ['Pemasok Pasir & Batu Kali Argopuro Sumbermalang', 'Pemasok Pasir Sampean'],
                'bata' => ['Pengrajin Bata Merah Alastengah Sumbermalang'],
                'tukang' => ['Kelompok Tukang Swakelola Sumbermalang', 'Kelompok Pekerja Swakelola Tlogosari'],
            ],
        ];

        if ($kecamatan) {
            foreach ($stores as $kName => $dStores) {
                if (strcasecmp($kName, trim($kecamatan)) === 0 || str_contains(strtolower($kecamatan), strtolower($kName))) {
                    return $dStores;
                }
            }
        }

        return $stores;
    }

    /**
     * Memilih toko bangunan atau rekanan terdekat dari lokasi sekolah di Kabupaten Situbondo.
     */
    public static function getNearestStoresForSchool(?string $kecamatan, ?string $address = null, ?string $category = 'bahan', ?string $itemName = null): array
    {
        $allStores = self::getSitubondoDistrictStores();

        $targetKecamatan = $kecamatan;
        if (empty($targetKecamatan) && !empty($address)) {
            foreach (array_keys($allStores) as $kName) {
                if (stripos($address, $kName) !== false) {
                    $targetKecamatan = $kName;
                    break;
                }
            }
        }

        $districtData = self::getSitubondoDistrictStores($targetKecamatan);
        if (empty($districtData) || !isset($districtData['general'])) {
            $districtData = $allStores['Situbondo'];
            $targetKecamatan = 'Situbondo';
        }

        $iLower = strtolower($itemName ?? '');
        $cat = strtolower($category ?? 'bahan');

        if ($cat === 'upah') {
            return $districtData['tukang'] ?? [
                "Kelompok Tukang Swakelola {$targetKecamatan}",
                "Kelompok Pekerja Swakelola Komite Sekolah",
            ];
        }

        if (str_contains($iLower, 'pasir') || str_contains($iLower, 'batu') || str_contains($iLower, 'koral') || str_contains($iLower, 'split')) {
            return $districtData['pasir'] ?? ['Pemasok Pasir Pasang Kali Sampean Situbondo', 'Pemasok Batu Belah Kali Sampean'];
        }

        if (str_contains($iLower, 'bata')) {
            return $districtData['bata'] ?? ['Pengrajin Bata Merah Panji-Situbondo'];
        }

        if (str_contains($iLower, 'cat') || str_contains($iLower, 'pelitur') || str_contains($iLower, 'plamir')) {
            return $districtData['cat'] ?? ['Toko Cat Citra Warna Situbondo'];
        }

        if (str_contains($iLower, 'kayu') || str_contains($iLower, 'kusen') || str_contains($iLower, 'pintu')) {
            return $districtData['kayu'] ?? ['UD Kayu Jati Murni Situbondo'];
        }

        if (str_contains($iLower, 'keramik') || str_contains($iLower, 'kloset') || str_contains($iLower, 'sanitair') || str_contains($iLower, 'pipa')) {
            return $districtData['keramik'] ?? ['Toko Keramik & Sanitasi Sentosa Situbondo'];
        }

        return $districtData['general'] ?? ['Toko Bangunan Berkah Situbondo'];
    }

    /**
     * Menentukan harga satuan (unit_price) dan volume yang realistis sesuai standar harga Situbondo.
     * Nilai total_price dijamin 100% tepat sama tanpa selisih anggaran (zero variance).
     */
    public static function determineSitubondoPrice(string $itemName, string $category, float $totalPrice = 0, ?string $unit = null): array
    {
        $iLower = strtolower($itemName);
        $benchmarks = self::getSitubondoPriceBenchmarks();

        $refPrice = 0;
        $matchedUnit = $unit ?: 'Unit';
        $matchedRole = null;

        if ($category === 'upah') {
            if (str_contains($iLower, 'pembantu') || str_contains($iLower, 'pekerja') || str_contains($iLower, 'laden') || str_contains($iLower, 'kenek') || str_contains($iLower, 'galian') || str_contains($iLower, 'pembersihan') || str_contains($iLower, 'adukan')) {
                $refPrice = $benchmarks['upah_pekerja']['price']; // Rp 90.000 / OH
                $matchedRole = 'Pekerja / Pembantu Tukang (HOK Situbondo)';
                $matchedUnit = 'OH';
            } elseif (str_contains($iLower, 'mandor')) {
                $refPrice = $benchmarks['upah_mandor']['price']; // Rp 140.000 / OH
                $matchedRole = 'Mandor Lapangan (HOK Situbondo)';
                $matchedUnit = 'OH';
            } elseif (str_contains($iLower, 'kepala')) {
                $refPrice = $benchmarks['upah_kepala_tukang']['price']; // Rp 135.000 / OH
                $matchedRole = 'Kepala Tukang (HOK Situbondo)';
                $matchedUnit = 'OH';
            } else {
                $refPrice = $benchmarks['upah_tukang']['price']; // Rp 125.000 / OH
                $matchedRole = 'Tukang Konstruksi (HOK Situbondo)';
                $matchedUnit = 'OH';
            }
        } else {
            foreach ($benchmarks as $key => $def) {
                if (str_starts_with($key, 'upah_')) continue;
                foreach ($def['keywords'] as $kw) {
                    if (str_contains($iLower, $kw)) {
                        $refPrice = $def['price'];
                        $matchedUnit = $def['unit'];
                        $matchedRole = $kw;
                        break 2;
                    }
                }
            }
        }

        if ($refPrice <= 0) {
            $refPrice = $totalPrice > 0 ? $totalPrice : 100000;
        }

        $calibratedVol = 1.0;
        $calibratedUnitPrice = $refPrice;

        if ($totalPrice > 0) {
            $uUpper = strtoupper($matchedUnit);
            if (in_array($uUpper, ['OH', 'HOK', 'HARI', 'ZAK', 'BATANG', 'BUAH', 'LEMBAR', 'DUS', 'UNIT', 'RIT', 'PAKET', 'BIJI'])) {
                $calibratedVol = max(1, round($totalPrice / $refPrice));
                $calibratedUnitPrice = round($totalPrice / $calibratedVol, 2);
            } else {
                $calibratedVol = max(0.5, round($totalPrice / $refPrice, 2));
                $calibratedUnitPrice = round($totalPrice / $calibratedVol, 2);
            }
        }

        return [
            'benchmark_price' => $refPrice,
            'unit_price' => $calibratedUnitPrice,
            'volume' => $calibratedVol,
            'unit' => $matchedUnit,
            'role_or_material' => $matchedRole,
        ];
    }

    /**
     * Susun instruksi prompt terstruktur untuk Gemini.
     */
    protected function buildPrompt(array $items, array $context): string
    {
        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $kecamatan = $context['school_kecamatan'] ?? 'Situbondo';
        $schoolAddress = $context['school_address'] ?? 'Kabupaten Situbondo, Jawa Timur';

        $nearestStoresList = self::getNearestStoresForSchool($kecamatan, $schoolAddress);
        $storesSummary = implode(', ', array_slice($nearestStoresList, 0, 5));

        return <<<PROMPT
Anda adalah Asisten AI Ahli Administrasi Keuangan Daerah & Manajemen Konstruksi Fisik untuk Program Revitalisasi Satuan Pendidikan di Kabupaten Situbondo, Jawa Timur.

Tugas Anda:
Analisis rincian item belanja RPD (Rencana Penarikan Dana) berikut. Tentukan:
1. Harga satuan (unit_price) dan volume (volume) yang realistis berdasarkan Standar Satuan Harga Biaya (SHSB / HSPK) Kabupaten Situbondo.
2. Nama toko bangunan / rekanan lokal terdekat dari alamat sekolah di Kecamatan {$kecamatan}, Kabupaten Situbondo (supplier_name).
3. Tanggal rencana belanja BKU yang berfase konstruksi logis tanpa hari Minggu (planned_date).
4. Narasi resmi pembukuan transaksi Buku Kas Umum (bku_description).

KONTEKS PROYEK & LOKASI SEKOLAH:
- Sekolah: {$context['school_name']}
- Alamat Sekolah: {$schoolAddress}
- Kecamatan Sekolah: {$kecamatan} (Kabupaten Situbondo, Jawa Timur)
- Rekomendasi Toko Bangunan Terdekat di Wilayah Ini: {$storesSummary}
- Paket Proyek: {$context['project_title']}
- Tahap Pencairan: {$context['term_stage']}
- Tanggal Mulai Pencairan (Baseline): {$context['base_date']}

ATURAN WAJIB (STRICT RULES):
1. PENENTUAN HARGA TUKANG, PEMBANTU TUKANG & HARGA BAHAN (STANDAR HARGA SATUAN SITUBONDO):
   Gunakan rujukan resmi Standar Satuan Harga (HSPK / SHSB) Pemerintah Kabupaten Situbondo terkini:
   * UPAH TUKANG (Batu, Kayu, Besi, Cat, Keramik, Plafon, Plumbing): Rp 120.000 s.d. Rp 125.000 / OH (HOK)
   * UPAH PEMBANTU TUKANG / PEKERJA (Kenek, Laden adukan spesi, Galian, Lansir, Bersih): Rp 90.000 s.d. Rp 95.000 / OH (HOK)
   * KEPALA TUKANG: Rp 130.000 s.d. Rp 135.000 / OH
   * MANDOR: Rp 140.000 s.d. Rp 150.000 / OH
   * HARGA BAHAN BANGUNAN UTAMA SITUBONDO:
     - Semen Portland 50 kg (Gresik/Dynamix): Rp 68.000 / Zak
     - Pasir Pasang Kali Sampean: Rp 185.000 / M³ (Rp 750.000 / Rit)
     - Pasir Cor Kali Sampean: Rp 200.000 / M³
     - Batu Belah Kali Sampean: Rp 210.000 / M³
     - Bata Merah Lokal Situbondo (Panji/Kapongan): Rp 800 / Buah
     - Besi Beton 8mm: Rp 48.000 / btg; 10mm: Rp 74.000 / btg; 12mm: Rp 110.000 / btg
     - Rangka Baja Ringan C75 0.75mm: Rp 120.000 / btg; Reng: Rp 45.000 / btg
     - Genteng Karang Pilang: Rp 5.500 / buah; Spandek 0.3mm: Rp 65.000 / m
     - Plafon Kalsiboard / GRC 4mm: Rp 68.000 / lbr; Rangka Hollow: Rp 34.000 / btg
     - Keramik Lantai 40x40 SNI: Rp 72.000 / dus; Dinding 20x25: Rp 65.000 / dus
     - Kloset Jongkok Porselen SNI (INA/TOTO): Rp 320.000 / unit
     - Pintu PVC Kamar Mandi: Rp 350.000 / unit
     - Cat Tembok Interior 5 kg: Rp 145.000 / galon; Eksterior: Rp 215.000 / galon
     - Pipa PVC AW 3": Rp 115.000 / btg; 4": Rp 165.000 / btg
   ATURAN MUTLAK NILAI ANGGARAN:
   Total pengeluaran (total_price) per item HARUS TETAP SAMA DAN BALANCE (zero variance). Rumus wajib: volume * unit_price = total_price.

2. REKOMENDASI TOKO BANGUNAN TERDEKAT DI KECAMATAN {$kecamatan} (supplier_name):
   - Prioritaskan toko bangunan riil yang terdekat dari lokasi sekolah di Kecamatan {$kecamatan}, Situbondo.
   - Untuk material pasir dan batu, gunakan Pemasok Pasir & Batu Kali Sampean Situbondo.
   - Untuk upah pekerja, gunakan Kelompok Tukang Swakelola {$kecamatan} / Kelompok Pekerja Swakelola Komite Sekolah.

3. PENANGGALAN JADWAL BELANJA BKU (planned_date):
   - Tanggal paling awal dimulai pada atau setelah {$context['base_date']}.
   - Susun jadwal bertahap selama rentang 3 s.d. 5 minggu secara logis sesuai urutan pekerjaan konstruksi:
     * Minggu 1: Belanja persiapan, galian, pondasi, pasir pasang, semen dasar, besi beton, sewa molen/alat, spanduk transparansi publik & ATK BKU P2SP.
     * Minggu 2: Belanja bata merah, semen dinding, kusen kayu/pintu, ongkos angkut bahan dinding & atap, upah tukang minggu 1.
     * Minggu 3: Belanja rangka baja ringan, genteng/spandek, pipa sanitasi, upah tukang minggu 2.
     * Minggu 4: Belanja keramik lantai, kloset/sanitasi, plafon, upah tukang minggu 3.
     * Minggu 5: Belanja cat tembok, pelitur kayu, kunci & handle, bea materai 10.000 SPJ, penggandaan laporan SPJ, upah tukang akhir.
   - PENTING: Pembayaran upah tukang/pekerja (kategori 'upah') HARUS SELALU dijadwalkan pada hari SABTU (akhir pekan).
   - DILARANG KERAS menjadwalkan transaksi apapun pada HARI MINGGU (kantor & toko libur).
   - Belanja bahan/alat/operasional dijadwalkan pada hari kerja (Senin s.d. Jumat/Sabtu). Format tanggal: YYYY-MM-DD.

4. VARIASI NARASI BKU (bku_description):
   - Susun kalimat pembukuan transaksi BKU yang alami, kaya variasi, dan sesuai kaidah tata naskah dinas resmi SPJ Revitalisasi/BOS/DAK Kemendikbudristek & BPK RI.

5. KETENTUAN KHUSUS BIAYA PERENCANAAN & PENGAWASAN (STRICT):
   - "Biaya Perencanaan": JANGAN DI-BREAKDOWN, tetap gunakan judul yang sama persis tanpa dirubah. Letakkan di AWAL (Minggu 1, tanggal pencairan 70% {$context['base_date']}). Rekanan: Tim Teknis Perencana Swakelola. Narasi BKU: "Pembayaran Biaya Perencanaan untuk pekerjaan [proyek] kepada [rekanan]".
   - "Biaya Pengawasan": JANGAN DI-BREAKDOWN, tetap gunakan judul yang sama persis tanpa dirubah. Letakkan di AKHIR (Minggu 5 / tanggal paling akhir setelah seluruh pekerjaan fisik selesai dan pencairan 30%). Rekanan: Tim Teknis Pengawas Swakelola. Narasi BKU: "Pembayaran Biaya Pengawasan untuk pekerjaan [proyek] setelah seluruh pekerjaan selesai kepada [rekanan]".

DATA ITEM PECAHAN BAHAN:
{$itemsJson}

OUTPUT WAJIB:
Hasilkan HANYA JSON array murni tanpa markdown pembungkus tambahan, dengan format persis:
[
  {
    "row_number": 1,
    "unit_price": 125000,
    "volume": 20.0,
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
    protected function mergeAiOutputWithItems(array $originalItems, array $aiData, string $baseDate, string $projectTitle, array $context = []): array
    {
        $aiMap = [];
        foreach ($aiData as $row) {
            if (isset($row['row_number'])) {
                $aiMap[$row['row_number']] = $row;
            }
        }

        $schoolKecamatan = $context['school_kecamatan'] ?? 'Situbondo';
        $schoolAddress = $context['school_address'] ?? 'Situbondo, Jawa Timur';

        $result = [];
        foreach ($originalItems as $idx => $item) {
            $rowNum = $item['row_number'] ?? ($idx + 1);
            $aiRow = $aiMap[$rowNum] ?? null;
            $itemName = strtolower($item['item_name'] ?? '');
            $cat = $item['category'] ?? 'bahan';

            if ($aiRow) {
                // 1. Validasi tanggal: tidak boleh hari Minggu, upah harus Sabtu
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

                // 2. Kalibrasi harga satuan & volume jika diberikan AI
                if (!empty($aiRow['unit_price']) && (float)$aiRow['unit_price'] > 0) {
                    $suggestedPrice = (float)$aiRow['unit_price'];
                    $tot = (float)($item['total_price'] ?? 0);
                    if ($tot > 0) {
                        $unit = strtoupper(trim($item['unit'] ?? ''));
                        if (in_array($unit, ['OH', 'HOK', 'HARI', 'ZAK', 'BATANG', 'BUAH', 'LEMBAR', 'DUS', 'UNIT', 'RIT', 'PAKET', 'BIJI', 'METER', 'M'])) {
                            $newVol = max(1, round($tot / $suggestedPrice));
                            $newUnitPrice = round($tot / $newVol, 2);
                        } else {
                            $newVol = max(0.5, round($tot / $suggestedPrice, 2));
                            $newUnitPrice = round($tot / $newVol, 2);
                        }
                        $item['volume'] = $newVol;
                        $item['unit_price'] = $newUnitPrice;
                    }
                }
            }

            // Jika supplier masih kosong, gunakan toko terdekat di kecamatan sekolah
            if (empty($item['supplier_name']) || $item['supplier_name'] === '....................') {
                $nearest = self::getNearestStoresForSchool($schoolKecamatan, $schoolAddress, $cat, $itemName);
                $item['supplier_name'] = $nearest[$idx % count($nearest)];
            }

            // Aturan Khusus: Biaya Perencanaan diawal dan Biaya Pengawasan diakhir
            if (str_contains($itemName, 'perencanaan')) {
                $item['planned_date'] = $baseDate;
                $item['supplier_name'] = 'Tim Teknis Perencana Swakelola';
            } elseif (str_contains($itemName, 'pengawasan')) {
                $item['supplier_name'] = 'Tim Teknis Pengawas Swakelola';
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Mesin Heuristik Cerdas Lokal (100% Offline & Tanpa Kuota).
     * Memvariasikan jadwal berfase konstruksi, mengkalibrasi harga tukang & bahan standar Situbondo,
     * mengacak narasi BKU resmi, dan memilih toko bangunan terdekat dari lokasi sekolah.
     */
    public function applySmartHeuristicVariance(array $items, string $baseDate, string $projectTitle, array $options = []): array
    {
        try {
            $base = Carbon::parse($baseDate);
        } catch (\Throwable $e) {
            $base = now();
        }

        if ($base->isSunday()) {
            $base->addDay();
        }

        $schoolKecamatan = $options['school_kecamatan'] ?? ($options['kecamatan'] ?? 'Situbondo');
        $schoolAddress = $options['school_address'] ?? ($options['address'] ?? 'Kabupaten Situbondo, Jawa Timur');

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
            $tot = (float)($item['total_price'] ?? 0);

            // 1. Tentukan Fase Konstruksi (1 - 5)
            $phase = 1;
            if (str_contains($itemName, 'perencanaan')) {
                // Biaya Perencanaan: DIAWAL setelah pencairan 70% (Fase 1, Hari Pertama)
                $phase = 1;
            } elseif (str_contains($itemName, 'pengawasan')) {
                // Biaya Pengawasan: DIAKHIR setelah seluruh pekerjaan selesai (Fase 5 / Akhir)
                $phase = 5;
            } elseif (str_contains($itemName, 'galian') || str_contains($itemName, 'pondasi') || str_contains($itemName, 'pasir') || str_contains($itemName, 'batu') || str_contains($itemName, 'molen') || str_contains($itemName, 'persiapan') || str_contains($itemName, 'rk3k') || str_contains($itemName, 'spanduk') || (str_contains($itemName, 'angkut') && str_contains($itemName, 'pondasi'))) {
                $phase = 1;
            } elseif (str_contains($itemName, 'bata') || str_contains($itemName, 'dinding') || str_contains($itemName, 'plesteran') || str_contains($itemName, 'besi') || str_contains($itemName, 'semen') || str_contains($itemName, 'konsumsi') || (str_contains($itemName, 'angkut') && str_contains($itemName, 'dinding'))) {
                $phase = 2;
            } elseif (str_contains($itemName, 'atap') || str_contains($itemName, 'baja') || str_contains($itemName, 'galvalum') || str_contains($itemName, 'genteng') || str_contains($itemName, 'spandek') || str_contains($itemName, 'plafon') || str_contains($itemName, 'kusen') || str_contains($itemName, 'kayu') || str_contains($itemName, 'transport') || str_contains($itemName, 'sppd')) {
                $phase = 3;
            } elseif (str_contains($itemName, 'keramik') || str_contains($itemName, 'lantai') || str_contains($itemName, 'kloset') || str_contains($itemName, 'sanitasi') || str_contains($itemName, 'pipa') || str_contains($itemName, 'toilet') || (str_contains($itemName, 'angkut') && str_contains($itemName, 'lantai'))) {
                $phase = 4;
            } elseif (str_contains($itemName, 'cat') || str_contains($itemName, 'pelitur') || str_contains($itemName, 'plamir') || str_contains($itemName, 'kunci') || str_contains($itemName, 'lampu') || str_contains($itemName, 'atk') || str_contains($itemName, 'spj') || str_contains($itemName, 'materai') || str_contains($itemName, 'pengelolaan')) {
                $phase = 5;
            } else {
                $phase = min(5, max(1, (int)floor(($idx / max(1, $totalItems)) * 5) + 1));
            }

            // 2. Hitung Tanggal Progresif
            $targetDate = $base->copy();
            if (str_contains($itemName, 'perencanaan')) {
                $targetDate = $base->copy();
            } elseif (str_contains($itemName, 'pengawasan')) {
                $targetDate = $base->copy()->addDays(32);
                if ($targetDate->isSunday()) {
                    $targetDate->addDay();
                }
            } elseif ($cat === 'upah') {
                $weeksToAdd = $phase - 1;
                $targetDate->addWeeks($weeksToAdd);
                if (!$targetDate->isSaturday()) {
                    $targetDate->next(Carbon::SATURDAY);
                }
            } else {
                $dayOffset = (($phase - 1) * 7) + (($idx % 4) * 2);
                $targetDate->addDays($dayOffset);
                if ($targetDate->isSunday()) {
                    $targetDate->addDay();
                }
            }
            $item['planned_date'] = $targetDate->format('Y-m-d');

            // 3. Kalibrasi Harga Satuan & Volume Mengikuti Standar HSPK / SHS Situbondo
            $priceBenchmark = self::determineSitubondoPrice(
                $item['item_name'] ?? '',
                $cat,
                $tot,
                $item['unit'] ?? null
            );

            // Jika belum ada harga satuan atau harga satuan 0, gunakan acuan Situbondo
            if ($tot > 0 && (!isset($item['unit_price']) || (float)$item['unit_price'] <= 0 || !empty($options['calibrate_prices']))) {
                $item['unit_price'] = $priceBenchmark['unit_price'];
                $item['volume'] = $priceBenchmark['volume'];
            }
            if (empty($item['unit']) || $item['unit'] === 'Unit') {
                $item['unit'] = $priceBenchmark['unit'];
            }

            $volFormatted = number_format((float)($item['volume'] ?? 1), ($item['volume'] ?? 1) == (int)($item['volume'] ?? 1) ? 0 : 2, ',', '.');
            $unit = $item['unit'] ?? 'Unit';

            // 4. Rekomendasi Toko Bangunan Terdekat Sesuai Kecamatan Sekolah di Situbondo
            $currSupplier = trim((string)($item['supplier_name'] ?? ''));
            if ($currSupplier === '' || $currSupplier === '....................' || !empty($options['overwrite_supplier'])) {
                if (str_contains($itemName, 'perencanaan')) {
                    $item['supplier_name'] = 'Tim Teknis Perencana Swakelola';
                } elseif (str_contains($itemName, 'pengawasan')) {
                    $item['supplier_name'] = 'Tim Teknis Pengawas Swakelola';
                } elseif ($cat === 'operasional') {
                    if (str_contains($itemName, 'konsumsi') || str_contains($itemName, 'makan') || str_contains($itemName, 'snack')) {
                        $item['supplier_name'] = "Katering / Warung Makan Lokal Kec. {$schoolKecamatan}";
                    } elseif (str_contains($itemName, 'transport') || str_contains($itemName, 'sppd')) {
                        $item['supplier_name'] = 'Tim Pelaksana P2SP Satuan Pendidikan';
                    } elseif (str_contains($itemName, 'materai')) {
                        $item['supplier_name'] = "Kantor Pos Situbondo / Agen Pos Kec. {$schoolKecamatan}";
                    } elseif (str_contains($itemName, 'spanduk') || str_contains($itemName, 'banner') || str_contains($itemName, 'publikasi') || str_contains($itemName, 'transparansi')) {
                        $item['supplier_name'] = 'Percetakan & Banner Reklame Situbondo';
                    } else {
                        $item['supplier_name'] = "Toko ATK & Penggandaan Kec. {$schoolKecamatan}";
                    }
                } elseif ($cat === 'alat' && (str_contains($itemName, 'angkut') || str_contains($itemName, 'kirim') || str_contains($itemName, 'mobilisasi'))) {
                    $item['supplier_name'] = "Penyedia Jasa Angkutan & Ekspedisi Material Kec. {$schoolKecamatan} Situbondo";
                } elseif ($cat === 'alat') {
                    $item['supplier_name'] = "Sewa Alat Proyek Jaya Situbondo";
                } else {
                    $nearestStores = self::getNearestStoresForSchool($schoolKecamatan, $schoolAddress, $cat, $itemName);
                    $item['supplier_name'] = $nearestStores[$idx % count($nearestStores)];
                }
            }

            // 5. Variasikan Narasi BKU
            if (str_contains($itemName, 'perencanaan')) {
                $item['bku_description'] = "Pembayaran " . ($item['item_name'] ?? 'Biaya Perencanaan') . " untuk pekerjaan " . $projectTitle . " kepada " . $item['supplier_name'];
            } elseif (str_contains($itemName, 'pengawasan')) {
                $item['bku_description'] = "Pembayaran " . ($item['item_name'] ?? 'Biaya Pengawasan') . " untuk pekerjaan " . $projectTitle . " setelah seluruh pekerjaan selesai kepada " . $item['supplier_name'];
            } elseif (str_contains($itemName, 'angkut') || str_contains($itemName, 'kirim') || str_contains($itemName, 'mobilisasi')) {
                $item['bku_description'] = "Pembayaran " . ($item['item_name'] ?? 'Ongkos Angkut Bahan') . " (" . $volFormatted . " " . $unit . ") pada " . $item['supplier_name'] . " untuk pekerjaan " . $projectTitle;
            } elseif ($cat === 'operasional') {
                if (str_contains($itemName, 'konsumsi') || str_contains($itemName, 'makan')) {
                    $item['bku_description'] = "Pembayaran penyediaan " . ($item['item_name'] ?? 'konsumsi rapat') . " (" . $volFormatted . " " . $unit . ") pada " . $item['supplier_name'] . " untuk kelancaran " . $projectTitle;
                } elseif (str_contains($itemName, 'transport') || str_contains($itemName, 'sppd')) {
                    $item['bku_description'] = "Pembayaran " . ($item['item_name'] ?? 'biaya transportasi') . " (" . $volFormatted . " " . $unit . ") untuk koordinasi dinas dan perbankan kegiatan " . $projectTitle;
                } elseif (str_contains($itemName, 'materai')) {
                    $item['bku_description'] = "Pembayaran pengadaan " . ($item['item_name'] ?? 'bea materai') . " (" . $volFormatted . " " . $unit . ") pada " . $item['supplier_name'] . " kelengkapan berkas kuitansi " . $projectTitle;
                } elseif (str_contains($itemName, 'spanduk') || str_contains($itemName, 'transparansi')) {
                    $item['bku_description'] = "Pembayaran pembuatan " . ($item['item_name'] ?? 'spanduk transparansi') . " (" . $volFormatted . " " . $unit . ") pada " . $item['supplier_name'] . " publikasi swakelola " . $projectTitle;
                } else {
                    $templates = $narrativeTemplates['operasional'];
                    $selectedTemplate = $templates[$idx % count($templates)];
                    $item['bku_description'] = str_replace(
                        ['{item}', '{vol}', '{unit}', '{supplier}', '{project}'],
                        [$item['item_name'] ?? 'Operasional', $volFormatted, $unit, $item['supplier_name'], $projectTitle],
                        $selectedTemplate
                    );
                }
            } else {
                $templates = $narrativeTemplates[$cat] ?? $narrativeTemplates['bahan'];
                $selectedTemplate = $templates[$idx % count($templates)];
                $item['bku_description'] = str_replace(
                    ['{item}', '{vol}', '{unit}', '{supplier}', '{project}'],
                    [$item['item_name'] ?? 'Material', $volFormatted, $unit, $item['supplier_name'], $projectTitle],
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

    /**
     * Memetakan kolom berkas RPD menggunakan AI Google Gemini (dengan fallback Heuristik Semantik Cerdas Situbondo).
     * Mendeteksi 8 atribut esensial yang dibutuhkan sistem:
     * 1. Nama/Uraian
     * 2. VOL / volume
     * 3. Sat / satuan
     * 4. Harga satuan
     * 5. Termin 1 (70%)
     * 6. Termin 2 (30%)
     * 7. Jumlah (100%)
     * 8. Keterangan
     */
    public function mapRpdColumnsWithAi(array $matrix, array $options = []): array
    {
        $heuristic = $this->detectRpdColumnsHeuristic($matrix);

        // Jika API Key tidak ada atau use_ai bernilai false, gunakan hasil heuristik cerdas lokal
        $apiKey = $options['api_key'] ?? config('services.gemini.api_key');
        if (empty($apiKey) || (isset($options['use_ai']) && !$options['use_ai'])) {
            return array_merge($heuristic, [
                'used_ai' => false,
                'source' => 'smart_heuristic',
            ]);
        }

        try {
            // Ambil sampel maksimal 20 baris pertama untuk analisis AI
            $sampleRows = [];
            $maxSample = min(20, count($matrix));
            for ($i = 0; $i < $maxSample; $i++) {
                $r = $matrix[$i] ?? [];
                if (is_array($r) && !empty(array_filter($r, fn($v) => !is_null($v) && trim((string)$v) !== ''))) {
                    // Batasi maksimal 15 kolom agar prompt tetap ringkas
                    $sampleRows[] = array_slice($r, 0, 15);
                }
            }

            if (empty($sampleRows)) {
                return array_merge($heuristic, ['used_ai' => false, 'source' => 'smart_heuristic']);
            }

            $sampleJson = json_encode($sampleRows, JSON_UNESCAPED_UNICODE);

            $prompt = <<<PROMPT
Anda adalah AI Spesialis Parsing dan Pemetaan Dokumen RPD (Rencana Penggunaan Dana) Revitalisasi Satuan Pendidikan.
Berikut adalah cuplikan baris-baris awal tabel Excel/CSV yang diunggah pengguna (format JSON array 2 dimensi):
{$sampleJson}

TUGAS ANDA:
Identifikasi baris header (header_row_index, 0-indexed) dan petakan indeks kolom (0-indexed) untuk 8 atribut data wajib berikut:
1. "uraian": Nama / Uraian Pekerjaan / Nama Barang / Nama Kegiatan / Bahan
2. "volume": VOL / Volume / Qty / Kuantitas / Banyak
3. "satuan": Sat / Satuan / Unit
4. "harga_satuan": Harga Satuan / Tarif / Biaya Satuan
5. "termin_1": Termin 1 (70%) / Tahap 1 / 70%
6. "termin_2": Termin 2 (30%) / Tahap 2 / 30%
7. "jumlah": Jumlah (100%) / Total / Total Biaya / 100%
8. "keterangan": Keterangan / Catatan / Spesifikasi / Lokasi

FORMAT OUTPUT WAJIB JSON MURNI (tanpa markdown pembuka/penutup):
{
  "header_row_index": 0,
  "columns": {
    "uraian": 1,
    "volume": 2,
    "satuan": 3,
    "harga_satuan": 4,
    "termin_1": 5,
    "termin_2": 6,
    "jumlah": 7,
    "keterangan": 8
  }
}
PROMPT;

            $rawJson = $this->callGeminiApi($prompt, array_merge($options, [
                'timeout' => 20,
                'json_mode' => true,
                'temperature' => 0.2,
            ]));

            if (!empty($rawJson)) {
                $decoded = json_decode($rawJson, true);
                if (is_array($decoded) && isset($decoded['columns']) && is_array($decoded['columns'])) {
                    $aiCols = $decoded['columns'];
                    $mergedCols = $heuristic['columns'] ?? [];

                    foreach (['uraian', 'volume', 'satuan', 'harga_satuan', 'termin_1', 'termin_2', 'jumlah', 'keterangan'] as $k) {
                        if (isset($aiCols[$k]) && is_numeric($aiCols[$k])) {
                            $mergedCols[$k] = (int)$aiCols[$k];
                        }
                    }

                    $headerRow = isset($decoded['header_row_index']) && is_numeric($decoded['header_row_index'])
                        ? (int)$decoded['header_row_index']
                        : ($heuristic['header_row_index'] ?? 0);

                    return [
                        'header_row_index' => $headerRow,
                        'columns' => $mergedCols,
                        'used_ai' => true,
                        'source' => 'gemini_api',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Gemini mapRpdColumnsWithAi error: " . $e->getMessage());
        }

        return array_merge($heuristic, [
            'used_ai' => false,
            'source' => 'smart_heuristic',
        ]);
    }

    /**
     * Deteksi heuristik cerdas untuk baris header dan pemetaan kolom tabel RPD.
     */
    public function detectRpdColumnsHeuristic(array $matrix): array
    {
        $bestScore = -1;
        $bestHeaderRow = 0;
        $bestMapping = [];

        $maxRows = min(25, count($matrix));
        for ($r = 0; $r < $maxRows; $r++) {
            $row = $matrix[$r] ?? [];
            if (!is_array($row) || empty(array_filter($row, fn($v) => !is_null($v) && trim((string)$v) !== ''))) {
                continue;
            }

            // 1. Cek baris tunggal $r
            $mapping = $this->matchRowColumns($row);
            $score = $this->scoreColumnMapping($mapping);

            // 2. Cek baris ganda (gabungan $r dan $r + 1 untuk header bertingkat)
            if ($r + 1 < $maxRows) {
                $nextRow = $matrix[$r + 1] ?? [];
                if (is_array($nextRow)) {
                    $combinedRow = [];
                    $colCount = max(count($row), count($nextRow));
                    for ($c = 0; $c < $colCount; $c++) {
                        $v1 = trim((string)($row[$c] ?? ''));
                        $v2 = trim((string)($nextRow[$c] ?? ''));
                        $combinedRow[$c] = trim("{$v1} {$v2}");
                    }
                    $combinedMapping = $this->matchRowColumns($combinedRow);
                    $combinedScore = $this->scoreColumnMapping($combinedMapping);
                    if ($combinedScore > $score) {
                        $mapping = $combinedMapping;
                        $score = $combinedScore;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestHeaderRow = $r;
                $bestMapping = $mapping;
            }
        }

        // Default fallback mapping jika tidak terdeteksi
        if (empty($bestMapping['uraian'])) {
            $bestMapping = array_merge([
                'uraian' => 1,
                'volume' => 2,
                'satuan' => 3,
                'harga_satuan' => 4,
                'termin_1' => 5,
                'termin_2' => 6,
                'jumlah' => 7,
                'keterangan' => 8,
            ], $bestMapping);
        }

        return [
            'header_row_index' => $bestHeaderRow,
            'columns' => $bestMapping,
            'score' => $bestScore,
        ];
    }

    /**
     * Mencocokkan nilai sel pada satu baris dengan 8 atribut data RPD yang esensial.
     */
    public function matchRowColumns(array $row): array
    {
        $mapping = [];
        $cellCount = count($row);

        for ($c = 0; $c < $cellCount; $c++) {
            $val = trim((string)($row[$c] ?? ''));
            if ($val === '') continue;

            $v = strtolower(preg_replace('/\s+/', ' ', $val));
            $cleanV = preg_replace('/[^\w\s\%\(\)\.\/]/', '', $v);

            // 1. Uraian / Nama
            if (!isset($mapping['uraian'])) {
                if (
                    preg_match('/^(nama|uraian|nama[\s_\/\-]+uraian|uraian[\s_\/\-]+nama|item|item[\s_\/\-]+pekerjaan|item[\s_\/\-]+belanja|uraian[\s_\/\-]+pekerjaan|nama[\s_\/\-]+pekerjaan|uraian[\s_\/\-]+kegiatan|nama[\s_\/\-]+kegiatan|uraian[\s_\/\-]+belanja|nama[\s_\/\-]+barang|uraian[\s_\/\-]+barang|pekerjaan|barang|bahan|deskripsi|description|rincian|rincian[\s_\/\-]+pekerjaan|rincian[\s_\/\-]+belanja|komponen)$/i', $cleanV) ||
                    ((str_contains($cleanV, 'uraian') || str_contains($cleanV, 'nama') || str_contains($cleanV, 'pekerjaan') || str_contains($cleanV, 'item') || str_contains($cleanV, 'deskripsi') || str_contains($cleanV, 'rincian')) &&
                     !str_contains($cleanV, 'sekolah') && !str_contains($cleanV, 'harga') && !str_contains($cleanV, 'satuan') && !str_contains($cleanV, 'rekening') && !str_contains($cleanV, 'koef') && !str_contains($cleanV, 'total') && !str_contains($cleanV, 'jumlah') && !str_contains($cleanV, 'termin') && !str_contains($cleanV, 'tahap') && !str_contains($cleanV, 'vol'))
                ) {
                    $mapping['uraian'] = $c;
                    continue;
                }
            }

            // 2. Volume
            if (!isset($mapping['volume'])) {
                if (
                    preg_match('/^(vol|volume|vol\.|volume\s*\(vol\)|vol\s*\(volume\)|qty|kuantitas|banyak|banyaknya|jumlah\s*satuan)$/i', $cleanV) ||
                    (str_starts_with($cleanV, 'vol') && !str_contains($cleanV, 'harga'))
                ) {
                    $mapping['volume'] = $c;
                    continue;
                }
            }

            // 3. Satuan
            if (!isset($mapping['satuan'])) {
                if (
                    preg_match('/^(sat|satuan|unit|sat\.|sat\s*\(satuan\))$/i', $cleanV) &&
                    !str_contains($cleanV, 'harga')
                ) {
                    $mapping['satuan'] = $c;
                    continue;
                }
            }

            // 4. Harga Satuan
            if (!isset($mapping['harga_satuan'])) {
                if (
                    preg_match('/(harga\s*satuan|hrg\s*satuan|tarif|biaya\s*satuan|harga\s*per\s*satuan|harga\s*sat\b|^harga$)/i', $cleanV) &&
                    !str_contains($cleanV, 'total') && !str_contains($cleanV, 'jumlah')
                ) {
                    $mapping['harga_satuan'] = $c;
                    continue;
                }
            }

            // 5. Termin 1 (70%)
            if (!isset($mapping['termin_1'])) {
                if (
                    preg_match('/(termin\s*1|termin\s*i\b|tahap\s*1|tahap\s*i\b|70\s*%|\bt1\b|pencairan\s*1)/i', $cleanV)
                ) {
                    $mapping['termin_1'] = $c;
                    continue;
                }
            }

            // 6. Termin 2 (30%)
            if (!isset($mapping['termin_2'])) {
                if (
                    preg_match('/(termin\s*2|termin\s*ii\b|tahap\s*2|tahap\s*ii\b|30\s*%|\bt2\b|pencairan\s*2)/i', $cleanV)
                ) {
                    $mapping['termin_2'] = $c;
                    continue;
                }
            }

            // 7. Jumlah (100%)
            if (
                preg_match('/(100\s*%|jumlah\s*\(?100%?\)?|total\s*\(?100%?\)?|jumlah\s*total|total\s*biaya|total\s*anggaran|pagu\s*anggaran|^jumlah$|^total$|^pagu$)/i', $cleanV) ||
                (str_contains($cleanV, 'jumlah') && !str_contains($cleanV, 'satuan'))
            ) {
                if (!isset($mapping['jumlah']) || (isset($mapping['termin_2']) && $c > $mapping['termin_2'])) {
                    $mapping['jumlah'] = $c;
                    continue;
                }
            }

            // 8. Keterangan
            if (!isset($mapping['keterangan'])) {
                if (
                    preg_match('/(keterangan|ket\b|catatan|spesifikasi|spek|remarks|notes)/i', $cleanV)
                ) {
                    $mapping['keterangan'] = $c;
                    continue;
                }
            }

            // Kolom pendukung lainnya jika ada
            if (!isset($mapping['no']) && in_array($cleanV, ['no', 'no.', 'nomor', 'no urut', '#'])) {
                $mapping['no'] = $c;
            } elseif (!isset($mapping['kode']) && (str_contains($cleanV, 'kode') || str_contains($cleanV, 'kd rekening'))) {
                $mapping['kode'] = $c;
            } elseif (!isset($mapping['kategori']) && (str_contains($cleanV, 'kategori') || str_contains($cleanV, 'jenis belanja'))) {
                $mapping['kategori'] = $c;
            } elseif (!isset($mapping['toko']) && (str_contains($cleanV, 'toko') || str_contains($cleanV, 'rekanan') || str_contains($cleanV, 'supplier'))) {
                $mapping['toko'] = $c;
            } elseif (!isset($mapping['metode']) && (str_contains($cleanV, 'metode') || str_contains($cleanV, 'cara bayar'))) {
                $mapping['metode'] = $c;
            }
        }

        return $mapping;
    }

    /**
     * Memberi skor relevansi pemetaan kolom untuk memilih baris header terbaik.
     */
    public function scoreColumnMapping(array $mapping): int
    {
        $score = 0;
        if (isset($mapping['uraian'])) $score += 10;
        if (isset($mapping['volume'])) $score += 5;
        if (isset($mapping['satuan'])) $score += 5;
        if (isset($mapping['harga_satuan'])) $score += 5;
        if (isset($mapping['termin_1'])) $score += 8;
        if (isset($mapping['termin_2'])) $score += 8;
        if (isset($mapping['jumlah'])) $score += 8;
        if (isset($mapping['keterangan'])) $score += 4;
        return $score;
    }

    /**
     * Memecah paket pekerjaan fisik revitalisasi (misal: "Rehabilitasi Ruang Kelas", "Perpustakaan", "Toilet")
     * menjadi rincian Belanja Bahan (~68%) dan Belanja Upah (~32%) yang variatif, masuk akal, dan aman dari temuan BPK.
     * PENTING: DILARANG MEMBREAKDOWN BIAYA PERENCANAAN DAN BIAYA PENGAWASAN!
     */
    public function decomposePackageWithAi(string $packageName, float $totalBudget, ?string $roomName = null, array $options = []): array
    {
        $totalBudget = round($totalBudget, 0);
        if ($totalBudget <= 0) {
            return [];
        }

        $pLower = strtolower($packageName);

        // ATURAN MUTLAK: BIAYA PERENCANAAN DAN BIAYA PENGAWASAN TIDAK BOLEH DIBREAKDOWN
        if (str_contains($pLower, 'perencanaan') || str_contains($pLower, 'pengawasan')) {
            $isPlan = str_contains($pLower, 'perencanaan');
            $cleanTitle = $isPlan ? 'Biaya Perencanaan' : 'Biaya Pengawasan';
            return [[
                'uraian' => $cleanTitle,
                'kode' => '5.1.02.04',
                'kategori' => 'operasional',
                'spesifikasi' => $packageName,
                'volume' => 1.0,
                'satuan' => 'Paket',
                'harga_satuan' => $totalBudget,
                'total' => $totalBudget,
                'tanggal' => $isPlan ? ($options['default_date'] ?? now()->format('Y-m-d')) : null,
                'toko' => $isPlan ? 'Tim Teknis Perencana Swakelola' : 'Tim Teknis Pengawas Swakelola',
                'npwp' => null,
                'metode' => $totalBudget > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                'room_name' => null,
            ]];
        }

        // Porsi belanja fisik swakelola: ~68% bahan dan ~32% upah (nol selisih anggaran)
        $bahanBudget = round($totalBudget * 0.68, 0);
        $upahBudget = max(0, $totalBudget - $bahanBudget);

        $breakdownService = app(RpdBreakdownService::class);
        $targetRoom = $roomName ?: $packageName;

        $bahanItems = $breakdownService->decomposeLumpSumToDetailedItems($bahanBudget, 'bahan', $targetRoom, $options);
        $upahItems = $breakdownService->decomposeLumpSumToDetailedItems($upahBudget, 'upah', $targetRoom, $options);

        return array_merge($bahanItems, $upahItems);
    }
}


