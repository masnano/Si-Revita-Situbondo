<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\RevitalizationProject;
use App\Models\RpdDocument;
use App\Models\RpdItem;
use App\Models\Transaction;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Smalot\PdfParser\Parser;

class RpdBreakdownService
{
    /**
     * Memproses unggahan file RPD (Excel / PDF / CSV) dan melakukan analisis "Pecah Bahan" untuk BKU.
     */
    public function processUpload(UploadedFile $file, RevitalizationProject $project, array $options = []): RpdDocument
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = $file->getClientOriginalName();
        $termStage = $options['term_stage'] ?? 'Tahap 1';
        $title = $options['title'] ?? 'RPD ' . $termStage . ' - ' . $project->title;

        // Simpan file fisik ke storage publik
        $storedPath = $file->store('rpd_files', 'public');

        $existingRabItems = BudgetItem::where('project_id', $project->id)->get();
        $extractOptions = array_merge($options, [
            'project' => $project,
            'existing_rab' => $existingRabItems,
        ]);

        // Parse baris-baris data dari file (mendukung Excel, PDF, dan CSV)
        $rawRows = $this->extractRowsFromFile($file->getRealPath(), $extension, $extractOptions);

        if (empty($rawRows)) {
            throw new \Exception('File RPD tidak memiliki data baris yang valid atau format tabel tidak dikenali. Pastikan file berisi tabel RPD dengan uraian pekerjaan, volume, dan nominal anggaran.');
        }

        // Auto-breakdown jika ada baris pagu gelondongan Mobilisasi atau Biaya Pengelolaan yang belum dipecah
        $expandedRows = [];
        foreach ($rawRows as $r) {
            $uLower = strtolower(trim($r['uraian'] ?? ''));
            $tot = (float)($r['total'] ?? 0);

            $isLumpMobilisasi = (str_contains($uLower, 'mobilisasi') || str_contains($uLower, 'demobilisasi'))
                && !str_contains($uLower, 'ongkos angkut')
                && !str_contains($uLower, 'ongkos kirim')
                && !str_contains($uLower, 'pengiriman bahan')
                && !str_contains($uLower, 'mesin molen')
                && !str_contains($uLower, 'scaffolding')
                && $tot > 0;

            $isLumpPengelolaan = str_contains($uLower, 'pengelolaan')
                && !str_contains($uLower, 'atk')
                && !str_contains($uLower, 'konsumsi')
                && !str_contains($uLower, 'transport')
                && !str_contains($uLower, 'sppd')
                && !str_contains($uLower, 'spanduk')
                && !str_contains($uLower, 'materai')
                && $tot > 0;

            if ($isLumpMobilisasi) {
                $decomposed = $this->decomposeBelanjaMobilisasi($tot, $r['room_name'] ?? null, $extractOptions);
                foreach ($decomposed as $d) {
                    $expandedRows[] = $d;
                }
            } elseif ($isLumpPengelolaan) {
                $decomposed = $this->decomposeBiayaPengelolaan($tot, $extractOptions);
                foreach ($decomposed as $d) {
                    $expandedRows[] = $d;
                }
            } else {
                $expandedRows[] = $r;
            }
        }
        $rawRows = $expandedRows;

        return DB::transaction(function () use ($project, $title, $termStage, $storedPath, $originalName, $extension, $rawRows, $options, $existingRabItems) {
            // Cek apakah dokumen RPD untuk proyek ini sudah ada (berdasarkan ID atau Judul Dokumen)
            $document = null;
            if (!empty($options['document_id'])) {
                $document = RpdDocument::find($options['document_id']);
            }
            if (!$document) {
                $document = RpdDocument::where('project_id', $project->id)
                    ->where('title', $title)
                    ->first();
            }
            if (!$document && !empty($options['replace_existing'])) {
                $document = RpdDocument::where('project_id', $project->id)
                    ->where('term_stage', $termStage)
                    ->first();
            }

            if ($document) {
                $document->update([
                    'title' => $title,
                    'file_path' => $storedPath,
                    'file_name' => $originalName,
                    'file_type' => $extension,
                    'notes' => $options['notes'] ?? $document->notes,
                ]);
            } else {
                $document = RpdDocument::create([
                    'school_id' => $project->school_id,
                    'project_id' => $project->id,
                    'title' => $title,
                    'term_stage' => $termStage,
                    'file_path' => $storedPath,
                    'file_name' => $originalName,
                    'file_type' => $extension,
                    'status' => 'analyzed',
                    'notes' => $options['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }

            $existingItems = $document->items()->get();
            $usedExistingItemIds = [];

            $defaultDate = $options['default_date'] ?? now()->format('Y-m-d');
            $rawDefaultStore = trim((string)($options['default_store'] ?? ''));
            $defaultStore = $rawDefaultStore !== '' ? $rawDefaultStore : '....................';
            $processedCount = 0;

            foreach ($rawRows as $index => $row) {
                // Analisis Kategori & Pecah Bahan
                $parsed = $this->analyzeAndBreakdownRow($row, $index + 1, $project, [
                    'default_date' => $defaultDate,
                    'default_store' => $defaultStore,
                    'existing_rab' => $existingRabItems,
                    'term_stage' => $termStage,
                    'breakdown_mode' => $options['breakdown_mode'] ?? 'detailed',
                ]);

                if (!$parsed) {
                    continue;
                }

                $cleanParsedName = trim(preg_replace('/\s+/', ' ', mb_strtolower($parsed['item_name'])));
                $parsedCategory = $parsed['category'];
                $parsedUnit = strtolower(trim($parsed['unit'] ?? ''));

                // Cari apakah ada item yang sama atau identik di dokumen yang sudah ada
                $matchedItem = $existingItems->first(function ($ext) use ($cleanParsedName, $parsedCategory, $parsedUnit, $parsed, $usedExistingItemIds) {
                    if (in_array($ext->id, $usedExistingItemIds)) {
                        return false;
                    }
                    if ($ext->category !== $parsedCategory) {
                        return false;
                    }
                    $extName = trim(preg_replace('/\s+/', ' ', mb_strtolower($ext->item_name)));
                    if ($extName === $cleanParsedName) {
                        return true;
                    }
                    // Pembandingan spesifikasi dan satuan jika nama mirip
                    if (!empty($ext->specification) && !empty($parsed['specification']) &&
                        trim(mb_strtolower($ext->specification)) === trim(mb_strtolower($parsed['specification'])) &&
                        strtolower(trim($ext->unit)) === $parsedUnit) {
                        return true;
                    }
                    return false;
                });

                if ($matchedItem) {
                    // Item sama / identik: UPDATE, bukan ditambahkan
                    $usedExistingItemIds[] = $matchedItem->id;

                    $updatePayload = [
                        'item_name' => $parsed['item_name'],
                        'specification' => $parsed['specification'] ?? $matchedItem->specification,
                        'volume' => $parsed['volume'],
                        'unit' => $parsed['unit'],
                        'unit_price' => $parsed['unit_price'],
                        'total_price' => $parsed['total_price'],
                        'planned_date' => $parsed['planned_date'],
                        'supplier_name' => $parsed['supplier_name'] ?? $matchedItem->supplier_name,
                        'supplier_npwp' => $parsed['supplier_npwp'] ?? $matchedItem->supplier_npwp,
                        'budget_item_id' => $parsed['budget_item_id'] ?? $matchedItem->budget_item_id,
                        'has_tax' => $parsed['has_tax'],
                        'tax_ppn' => $parsed['tax_ppn'],
                        'tax_pph22' => $parsed['tax_pph22'],
                        'tax_pph21' => $parsed['tax_pph21'],
                        'tax_total' => $parsed['tax_total'],
                        'net_amount' => $parsed['net_amount'],
                        'bku_description' => $parsed['bku_description'] ?? $matchedItem->bku_description,
                    ];

                    // Jika belum diposting ke BKU, metode bayar boleh diperbarui
                    if (!$matchedItem->is_posted) {
                        $updatePayload['payment_method'] = $parsed['payment_method'];
                    }

                    $matchedItem->update($updatePayload);
                } else {
                    // Item baru: tambahkan dengan status belum diposting (is_posted = false)
                    $newItem = RpdItem::create(array_merge($parsed, [
                        'rpd_document_id' => $document->id,
                        'project_id' => $project->id,
                        'is_posted' => false,
                    ]));
                    $existingItems->push($newItem);
                    $usedExistingItemIds[] = $newItem->id;
                }

                $processedCount++;
            }

            if ($processedCount === 0 && $document->items()->count() === 0) {
                throw new \Exception('Gagal mengekstrak item pecahan bahan dari berkas RPD. Pastikan berkas memiliki nominal anggaran pada kolom termin/jumlah.');
            }

            // Rekalkulasi total dan statistik dokumen
            $allItems = $document->items()->get();
            $totalBudget = $allItems->sum('total_price');
            $totalMaterials = $allItems->where('category', 'bahan')->sum('total_price');
            $totalWages = $allItems->where('category', 'upah')->sum('total_price');
            $totalEquipment = $allItems->where('category', 'alat')->sum('total_price');
            $totalOperational = $allItems->where('category', 'operasional')->sum('total_price');
            $totalTaxEstimated = $allItems->sum('tax_total');
            $totalNetEstimated = $allItems->sum('net_amount');
            $itemCount = $allItems->count();

            $unpostedCount = $allItems->where('is_posted', false)->count();

            $document->update([
                'total_budget' => $totalBudget,
                'total_materials' => $totalMaterials,
                'total_wages' => $totalWages,
                'total_equipment' => $totalEquipment,
                'total_operational' => $totalOperational,
                'total_tax_estimated' => $totalTaxEstimated,
                'total_net_estimated' => $totalNetEstimated,
                'total_items_count' => $itemCount,
                // Jika masih ada item yang belum diposting, status dokumen adalah 'analyzed'
                'status' => $unpostedCount > 0 ? 'analyzed' : 'posted_to_bku',
            ]);

            // Jika opsi use_ai aktif, variasikan dan sempurnakan item menggunakan AI / Smart Engine
            if (!empty($options['use_ai'])) {
                $this->enhanceDocumentWithAi($document, $options);
            }

            return $document;
        });
    }

    /**
     * Memvariasikan dan menyempurnakan item-item pecahan bahan RPD yang belum diposting menggunakan Gemini AI.
     */
    public function enhanceDocumentWithAi(RpdDocument $document, array $options = []): array
    {
        $unpostedItems = $document->items()->where('is_posted', false)->get();
        if ($unpostedItems->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Tidak ada item draf/belum diposting yang dapat disempurnakan.',
                'items_count' => 0,
            ];
        }

        $itemsArray = [];
        foreach ($unpostedItems as $item) {
            $itemsArray[] = [
                'id' => $item->id,
                'row_number' => $item->row_number,
                'category' => $item->category,
                'item_name' => $item->item_name,
                'specification' => $item->specification,
                'volume' => (float)$item->volume,
                'unit' => $item->unit,
                'total_price' => (float)$item->total_price,
                'planned_date' => $item->planned_date ? $item->planned_date->format('Y-m-d') : null,
                'supplier_name' => $item->supplier_name,
                'bku_description' => $item->bku_description,
            ];
        }

        $geminiService = app(GeminiRpdService::class);
        $result = $geminiService->enhanceItems($itemsArray, array_merge($options, [
            'base_date' => $options['default_date'] ?? ($unpostedItems->min('planned_date') ? Carbon::parse($unpostedItems->min('planned_date'))->format('Y-m-d') : now()->format('Y-m-d')),
            'project_title' => $document->project?->title ?? 'Revitalisasi Sekolah',
            'school_name' => $document->school?->name ?? 'Sekolah Dasar Situbondo',
            'term_stage' => $document->term_stage ?? 'Tahap 1',
        ]));

        $updatedCount = 0;
        foreach ($result['items'] as $resItem) {
            $modelItem = $unpostedItems->firstWhere('id', $resItem['id'] ?? null) 
                      ?? $unpostedItems->firstWhere('row_number', $resItem['row_number'] ?? null);

            if ($modelItem) {
                $payload = [];
                if (!empty($resItem['planned_date'])) {
                    $payload['planned_date'] = $resItem['planned_date'];
                }
                if (!empty($resItem['bku_description'])) {
                    $payload['bku_description'] = $resItem['bku_description'];
                }
                if (!empty($resItem['supplier_name']) && ($modelItem->supplier_name === '....................' || empty($modelItem->supplier_name) || !empty($options['overwrite_supplier']))) {
                    $payload['supplier_name'] = $resItem['supplier_name'];
                }
                if (!empty($payload)) {
                    $modelItem->update($payload);
                    $updatedCount++;
                }
            }
        }

        return array_merge($result, [
            'items_count' => $updatedCount,
        ]);
    }

    /**
     * Ekstraksi baris dari file Excel (.xlsx, .xls), PDF (.pdf), atau CSV (.csv).
     */
    protected function extractRowsFromFile(string $filePath, string $extension, array $options = []): array
    {
        if ($extension === 'pdf') {
            return $this->extractFromPdf($filePath, $options);
        }

        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->extractFromSpreadsheet($filePath, $options);
        }

        return $this->extractFromCsv($filePath, $options);
    }

    /**
     * Membaca file PDF dan mengekstrak tabel RPD menggunakan parser teks & baris.
     */
    protected function extractFromPdf(string $filePath, array $options = []): array
    {
        $rawText = '';

        // Prioritas 1: Gunakan Smalot\PdfParser (PHP murni, universal)
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $rawText = $pdf->getText();
        } catch (\Throwable $e) {
            $rawText = '';
        }

        // Prioritas 2: Fallback pdftotext jika terpasang dan Smalot menghasilkan teks kosong
        if (empty(trim($rawText))) {
            $pdftotextPath = 'pdftotext.exe';
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open("{$pdftotextPath} -layout \"{$filePath}\" -", $descriptors, $pipes);
            if (is_resource($proc)) {
                $rawText = stream_get_contents($pipes[1]);
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
            }
        }

        if (empty(trim($rawText))) {
            throw new \Exception('Dokumen PDF tidak mengandung lapisan teks yang dapat dibaca (kemungkinan berupa scan gambar murni tanpa OCR). Silakan gunakan file Excel atau PDF yang dicetak dari dokumen digital.');
        }

        $lines = explode("\n", $rawText);

        // Deteksi apakah format PDF adalah Format Resmi RPD Revitalisasi (memuat Belanja Bahan, Belanja Upah, Termin)
        $isOfficialFormat = false;
        foreach ($lines as $line) {
            $l = strtolower($line);
            if (str_contains($l, 'belanja bahan') || str_contains($l, 'belanja upah') || str_contains($l, 'termin 1') || str_contains($l, 'termin 2')) {
                $isOfficialFormat = true;
                break;
            }
        }

        if ($isOfficialFormat) {
            return $this->parseOfficialRpdFromLines($lines, $options);
        }

        return $this->parseGenericTableFromLines($lines, $options);
    }

    /**
     * Parse Format Resmi RPD Revitalisasi dari baris teks PDF.
     */
    protected function parseOfficialRpdFromLines(array $lines, array $options = []): array
    {
        $targetTermin = $this->determineTargetTermin($options);
        $currentParent = null;
        $currentVol = 1.0;
        $currentSat = 'Paket';
        $results = [];

        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;

            $tLower = strtolower($t);

            // Lewati judul dan kop dokumen
            if (str_contains($tLower, 'rencana penggunaan dana') ||
                str_contains($tLower, 'program revitalisasi') ||
                str_contains($tLower, 'provinsi jawa timur') ||
                str_contains($tLower, 'sekolah dasar :') ||
                str_contains($tLower, 'npsn :') ||
                str_contains($tLower, 'kab/kota :') ||
                str_contains($tLower, 'jumlah anggaran') ||
                str_contains($tLower, 'harga satuan') ||
                $tLower === 'uraian' || $tLower === 'no') {
                continue;
            }

            // Lewati subtotal dan total akumulasi agar tidak double counting
            if (preg_match('/^(sub\s*total|total\s*\()/i', $t)) {
                continue;
            }

            // Deteksi nama pekerjaan induk (misal: - Ruang Kelas, - Perpustakaan, - Toilet - 1)
            if (preg_match('/^[-–—\s*]*(ruang\s+kelas|perpustakaan|toilet[^\d]*\d*|gedung[^\d]*\d*|ruang\s+lab|lab\s+komputer|sanitasi|rehabilitasi\s+[a-z0-9\s]+)/i', $t) &&
                !str_contains($tLower, 'belanja bahan') && !str_contains($tLower, 'belanja upah')) {
                $parts = explode("\t", $t);
                $parentName = trim($parts[0]);
                $parentName = preg_replace('/^[-–—\s*]+/', '', $parentName);
                $currentParent = $parentName;

                // Ambil volume & satuan bila ada pada baris pekerjaan induk
                if (preg_match('/([\d\.,]+)\s*(m²|m2|ls|unit|paket)/i', $t, $volMatch)) {
                    $currentVol = (float)str_replace(',', '.', str_replace('.', '', $volMatch[1]));
                    $currentSat = $volMatch[2];
                } else {
                    $currentVol = 1.0;
                    $currentSat = 'Paket';
                }
                continue;
            }

            // Deteksi baris Belanja Bahan atau Belanja Upah
            if (str_contains($tLower, 'belanja bahan') || str_contains($tLower, 'belanja upah')) {
                $isBahan = str_contains($tLower, 'belanja bahan');
                $category = $isBahan ? 'bahan' : 'upah';
                $name = ($isBahan ? 'Belanja Bahan' : 'Belanja Upah') . ($currentParent ? ' - ' . $currentParent : '');

                $numbers = $this->extractCurrencyNumbersFromText($t);
                $amount = $this->selectAmountByTargetTermin($numbers, $targetTermin);

                if ($amount > 0) {
                    $breakdownMode = $options['breakdown_mode'] ?? 'detailed';
                    if ($breakdownMode === 'detailed') {
                        $decomposed = $this->decomposeLumpSumToDetailedItems($amount, $category, $currentParent, $options);
                        foreach ($decomposed as $dItem) {
                            $results[] = $dItem;
                        }
                    } else {
                        $results[] = [
                            'uraian' => $name,
                            'kode' => $isBahan ? '5.1.02.01' : '5.1.02.02',
                            'kategori' => $category,
                            'spesifikasi' => ($isBahan ? 'Pengadaan Bahan/Material ' : 'Upah Tukang & Tenaga ') . ($currentParent ?: 'Rehabilitasi'),
                            'volume' => 1.0,
                            'satuan' => 'Paket',
                            'harga_satuan' => $amount,
                            'total' => $amount,
                            'tanggal' => $this->calculateProgressiveDate($options['default_date'] ?? now()->format('Y-m-d'), count($results) + 1, $category),
                            'toko' => null,
                            'npwp' => null,
                            'metode' => $amount > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                        ];
                    }
                }
                continue;
            }

            // Deteksi Belanja Mobilisasi
            if (str_contains($tLower, 'mobilisasi') || str_contains($tLower, 'demobilisasi')) {
                $numbers = $this->extractCurrencyNumbersFromText($t);
                $amount = $this->selectAmountByTargetTermin($numbers, $targetTermin);

                if ($amount > 0) {
                    $breakdownMode = $options['breakdown_mode'] ?? 'detailed';
                    if ($breakdownMode === 'detailed') {
                        $decomposedMob = $this->decomposeBelanjaMobilisasi($amount, $currentParent, $options);
                        foreach ($decomposedMob as $dItem) {
                            $results[] = $dItem;
                        }
                    } else {
                        $existingRab = $options['existing_rab'] ?? null;
                        $rabMatch = !empty($existingRab) ? $this->findBestRabMatch('Belanja Mobilisasi', 'alat', $existingRab, $currentParent) : null;

                        $results[] = [
                            'budget_item_id' => $rabMatch?->id,
                            'uraian' => 'Belanja Mobilisasi Peralatan & Bahan',
                            'kode' => $rabMatch?->code ?? '5.1.02.03',
                            'kategori' => 'alat',
                            'spesifikasi' => 'Mobilisasi dan demobilisasi peralatan, material, serta perlengkapan kerja revitalisasi',
                            'volume' => 1.0,
                            'satuan' => 'Ls',
                            'harga_satuan' => $amount,
                            'total' => $amount,
                            'tanggal' => $options['default_date'] ?? now()->format('Y-m-d'),
                            'toko' => 'Penyedia Sewa Peralatan Situbondo',
                            'npwp' => null,
                            'metode' => $amount > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                            'room_name' => $currentParent,
                        ];
                    }
                }
                continue;
            }

            // Deteksi Biaya Perencanaan, Pengawasan, dan Pengelolaan
            if (str_contains($tLower, 'biaya perencanaan') || str_contains($tLower, 'biaya pengawasan') || str_contains($tLower, 'biaya pengelolaan')) {
                $name = trim(preg_replace('/^[-–—\s\dA-Z\.]+/i', '', $t));
                $parts = preg_split('/[\t\s]{2,}/', $name);
                $cleanName = trim($parts[0]);

                $numbers = $this->extractCurrencyNumbersFromText($t);
                $amount = $this->selectAmountByTargetTermin($numbers, $targetTermin);

                if ($amount > 0) {
                    $isPengelolaan = str_contains($tLower, 'pengelolaan');
                    $breakdownMode = $options['breakdown_mode'] ?? 'detailed';

                    if ($isPengelolaan && $breakdownMode === 'detailed') {
                        $decomposedPengelolaan = $this->decomposeBiayaPengelolaan($amount, $options);
                        foreach ($decomposedPengelolaan as $dItem) {
                            $results[] = $dItem;
                        }
                    } else {
                        $existingRab = $options['existing_rab'] ?? null;
                        $rabMatch = !empty($existingRab) ? $this->findBestRabMatch($cleanName, 'operasional', $existingRab, null) : null;

                        $results[] = [
                            'budget_item_id' => $rabMatch?->id,
                            'uraian' => $cleanName,
                            'kode' => $rabMatch?->code ?? '5.1.02.04',
                            'kategori' => 'operasional',
                            'spesifikasi' => "Penyelenggaraan {$cleanName}",
                            'volume' => 1.0,
                            'satuan' => 'Ls',
                            'harga_satuan' => $amount,
                            'total' => $amount,
                            'tanggal' => $options['default_date'] ?? now()->format('Y-m-d'),
                            'toko' => 'Toko ATK & Administrasi Situbondo',
                            'npwp' => null,
                            'metode' => $amount > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                            'room_name' => null,
                        ];
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Parse tabel umum dari PDF jika format bukan Format Resmi RPD.
     */
    protected function parseGenericTableFromLines(array $lines, array $options = []): array
    {
        $results = [];

        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;

            $parts = preg_split('/[\t]{1,}|\s{2,}/', $t);
            if (count($parts) < 3) continue;

            $uraian = trim($parts[0]);
            $uLower = strtolower($uraian);
            if (str_contains($uLower, 'uraian') || str_contains($uLower, 'total') || str_contains($uLower, 'sub total')) {
                continue;
            }

            $numbers = $this->extractCurrencyNumbersFromText($t);
            if (empty($numbers)) continue;

            $total = end($numbers);
            if ($total <= 0) continue;

            $results[] = [
                'uraian' => $uraian,
                'kode' => '5.1.02.01',
                'kategori' => $this->determineCategory($uraian, null),
                'spesifikasi' => null,
                'volume' => 1.0,
                'satuan' => 'Unit',
                'harga_satuan' => $total,
                'total' => $total,
                'tanggal' => null,
                'toko' => null,
                'npwp' => null,
                'metode' => $total > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
            ];
        }

        return $results;
    }

    /**
     * Membaca file Excel menggunakan PhpOffice/PhpSpreadsheet.
     */
    protected function extractFromSpreadsheet(string $filePath, array $options = []): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $sheetData = $worksheet->toArray(null, true, true, false);

        if (empty($sheetData)) {
            return [];
        }

        // Deteksi apakah format sheet adalah Format Resmi RPD Revitalisasi Situbondo
        $isOfficial = false;
        foreach ($sheetData as $row) {
            $rowStr = strtolower(implode(' ', array_filter($row, 'is_scalar')));
            if (str_contains($rowStr, 'belanja bahan') || str_contains($rowStr, 'belanja upah') || str_contains($rowStr, 'termin 1') || str_contains($rowStr, 'termin 2')) {
                $isOfficial = true;
                break;
            }
        }

        if ($isOfficial) {
            return $this->parseOfficialRpdMatrix($sheetData, $options);
        }

        return $this->mapMatrixToStructuredRows($sheetData, $options);
    }

    /**
     * Parse Format Resmi RPD Revitalisasi dari matriks Excel / CSV.
     */
    protected function parseOfficialRpdMatrix(array $matrix, array $options = []): array
    {
        $termin1Col = null;
        $termin2Col = null;
        $termin3Col = null;
        $jumlahCol = null;
        $volCol = null;
        $satCol = null;
        $hargaSatuanCol = null;

        // Cari indeks kolom termin dan uraian dari baris header (20 baris pertama)
        foreach (array_slice($matrix, 0, 20) as $rIdx => $row) {
            foreach ($row as $cIdx => $val) {
                $v = strtolower(trim((string)$val));
                if (empty($v)) continue;

                if (str_contains($v, 'termin 1') || str_contains($v, 'tahap 1') || $v === '70%') {
                    if ($termin1Col === null) $termin1Col = $cIdx;
                } elseif (str_contains($v, 'termin 2') || str_contains($v, 'tahap 2') || $v === '30%') {
                    if ($termin2Col === null) $termin2Col = $cIdx;
                } elseif (str_contains($v, 'termin 3') || str_contains($v, 'tahap 3')) {
                    if ($termin3Col === null) $termin3Col = $cIdx;
                } elseif ($v === 'jumlah' || $v === 'total' || $v === '100%') {
                    if ($jumlahCol === null) $jumlahCol = $cIdx;
                } elseif ($v === 'vol' || $v === 'volume' || str_contains($v, 'volume')) {
                    if ($volCol === null) $volCol = $cIdx;
                } elseif ($v === 'sat' || $v === 'satuan') {
                    if ($satCol === null) $satCol = $cIdx;
                } elseif (str_contains($v, 'harga satuan')) {
                    if ($hargaSatuanCol === null) $hargaSatuanCol = $cIdx;
                }
            }
        }

        // Jika jumlahCol ada sebelum atau sama dengan termin2Col, cari jumlah setelah termin2Col
        if ($jumlahCol !== null && $termin2Col !== null && $jumlahCol <= $termin2Col) {
            foreach (array_slice($matrix, 0, 20) as $row) {
                foreach ($row as $cIdx => $val) {
                    $v = strtolower(trim((string)$val));
                    if ($cIdx > $termin2Col && ($v === 'jumlah' || $v === 'total' || $v === '100%')) {
                        $jumlahCol = $cIdx;
                        break 2;
                    }
                }
            }
        }

        $targetTermin = $this->determineTargetTermin($options);
        $currentParent = null;
        $currentSubSection = null;
        $results = [];

        foreach ($matrix as $rIdx => $row) {
            // Gabungkan teks dari kolom 0 sampai 6 untuk deteksi baris yang di-merge / diindentasi
            $textCells = [];
            for ($c = 0; $c <= min(6, count($row) - 1); $c++) {
                $val = trim((string)($row[$c] ?? ''));
                if ($val !== '' && $val !== '-' && !preg_match('/^[\d\.,\(\)]+$/', $val)) {
                    $textCells[] = $val;
                }
            }
            $cleanText = implode(' ', $textCells);
            $cLower = strtolower($cleanText);

            if (empty($cLower)) continue;

            // Abaikan judul dokumen dan baris total
            if (str_starts_with($cLower, 'sub total') || str_starts_with($cLower, 'total') || str_starts_with($cLower, 'jumlah total')) {
                continue;
            }
            if ($cLower === 'no uraian' || $cLower === 'uraian' || str_contains($cLower, 'rencana penggunaan dana') || str_contains($cLower, 'program revitalisasi')) {
                continue;
            }
            if (preg_match('/^0\.[0-9]+/', $cLower)) {
                continue;
            }

            // Deteksi Sub-section (Rehabilitasi, Pembangunan Baru, Bangun Baru)
            if (preg_match('/^(?:[a-z]\s*[-–—:]*\s*)?(rehabilitasi|pembangunan\s+baru|bangun\s+baru|interior)/i', $cleanText, $sm)) {
                $currentSubSection = trim($sm[1]);
                continue;
            }

            // Identifikasi nama pekerjaan induk / ruangan
            if (preg_match('/(ruang\s+kelas|perpustakaan|toilet[^\d]*\d*|rumah\s+dinas|gedung[^\d]*\d*|ruang\s+lab|lab\s+komputer|sanitasi|pekerjaan\s+persiapan)/i', $cleanText, $pm) &&
                !str_contains($cLower, 'belanja bahan') && !str_contains($cLower, 'belanja upah')) {
                $roomName = trim(preg_replace('/^[-–—\s\dA-Z\.]+/i', '', $cleanText));
                if (empty($roomName)) {
                    $roomName = trim($pm[0]);
                }
                $currentParent = $roomName;
                continue;
            }

            // Baris Belanja Bahan atau Belanja Upah
            if (str_contains($cLower, 'belanja bahan') || str_contains($cLower, 'belanja upah')) {
                $isBahan = str_contains($cLower, 'belanja bahan');
                $category = $isBahan ? 'bahan' : 'upah';
                $name = ($isBahan ? 'Belanja Bahan' : 'Belanja Upah') . ($currentParent ? ' - ' . $currentParent : '');

                $valT1 = $termin1Col !== null ? $this->parseNumeric($row[$termin1Col] ?? 0) : 0;
                $valT2 = $termin2Col !== null ? $this->parseNumeric($row[$termin2Col] ?? 0) : 0;
                $valT3 = $termin3Col !== null ? $this->parseNumeric($row[$termin3Col] ?? 0) : 0;
                $valTot = $jumlahCol !== null ? $this->parseNumeric($row[$jumlahCol] ?? 0) : 0;

                $amount = match ($targetTermin) {
                    1 => $termin1Col !== null ? $valT1 : ($valTot ?: $valT2),
                    2 => $termin2Col !== null ? $valT2 : ($valTot ?: $valT1),
                    3 => $termin3Col !== null ? $valT3 : ($valTot ?: $valT1),
                    default => $valTot > 0 ? $valTot : ($valT1 + $valT2 + $valT3),
                };

                if ($amount > 0) {
                    $breakdownMode = $options['breakdown_mode'] ?? 'detailed';
                    if ($breakdownMode === 'detailed') {
                        $decomposed = $this->decomposeLumpSumToDetailedItems($amount, $category, $currentParent, $options);
                        foreach ($decomposed as $dItem) {
                            $results[] = $dItem;
                        }
                    } else {
                        $results[] = [
                            'uraian' => $name,
                            'kode' => $isBahan ? '5.1.02.01' : '5.1.02.02',
                            'kategori' => $category,
                            'spesifikasi' => ($isBahan ? 'Pengadaan Bahan/Material ' : 'Upah Tukang & Tenaga ') . ($currentParent ?: 'Rehabilitasi'),
                            'volume' => 1.0,
                            'satuan' => 'Paket',
                            'harga_satuan' => $amount,
                            'total' => $amount,
                            'tanggal' => $this->calculateProgressiveDate($options['default_date'] ?? now()->format('Y-m-d'), count($results) + 1, $category),
                            'toko' => null,
                            'npwp' => null,
                            'metode' => $amount > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                            'room_name' => $currentParent,
                        ];
                    }
                }
                continue;
            }

            // Belanja Mobilisasi (Biaya Tambahan Ongkos Angkut / Pengiriman Pembelian Bahan)
            if (str_contains($cLower, 'mobilisasi') || str_contains($cLower, 'demobilisasi')) {
                $valT1 = $termin1Col !== null ? $this->parseNumeric($row[$termin1Col] ?? 0) : 0;
                $valT2 = $termin2Col !== null ? $this->parseNumeric($row[$termin2Col] ?? 0) : 0;
                $valTot = $jumlahCol !== null ? $this->parseNumeric($row[$jumlahCol] ?? 0) : 0;

                $amount = match ($targetTermin) {
                    1 => $termin1Col !== null ? $valT1 : $valTot,
                    2 => $termin2Col !== null ? $valT2 : 0,
                    default => $valTot > 0 ? $valTot : ($valT1 + $valT2),
                };

                if ($amount > 0) {
                    $breakdownMode = $options['breakdown_mode'] ?? 'detailed';
                    if ($breakdownMode === 'detailed') {
                        $decomposedMob = $this->decomposeBelanjaMobilisasi($amount, $currentParent, $options);
                        foreach ($decomposedMob as $dItem) {
                            $results[] = $dItem;
                        }
                    } else {
                        $existingRab = $options['existing_rab'] ?? null;
                        $rabMatch = !empty($existingRab) ? $this->findBestRabMatch('Belanja Mobilisasi', 'alat', $existingRab, $currentParent) : null;

                        $results[] = [
                            'budget_item_id' => $rabMatch?->id,
                            'uraian' => 'Belanja Mobilisasi Peralatan & Bahan',
                            'kode' => $rabMatch?->code ?? '5.1.02.03',
                            'kategori' => 'alat',
                            'spesifikasi' => 'Mobilisasi dan demobilisasi peralatan, material, serta perlengkapan kerja revitalisasi',
                            'volume' => 1.0,
                            'satuan' => 'Ls',
                            'harga_satuan' => $amount,
                            'total' => $amount,
                            'tanggal' => $options['default_date'] ?? now()->format('Y-m-d'),
                            'toko' => 'Penyedia Sewa Peralatan Situbondo',
                            'npwp' => null,
                            'metode' => $amount > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                            'room_name' => $currentParent,
                        ];
                    }
                }
                continue;
            }

            // Biaya Perencanaan, Pengawasan, Pengelolaan (Non-Fisik)
            if (str_contains($cLower, 'biaya perencanaan') || str_contains($cLower, 'biaya pengawasan') || str_contains($cLower, 'biaya pengelolaan')) {
                $cleanName = trim(preg_replace('/^[a-z\d\.\s–—-]+/i', '', $cleanText));
                if (empty($cleanName)) {
                    if (str_contains($cLower, 'perencanaan')) $cleanName = 'Biaya Perencanaan';
                    elseif (str_contains($cLower, 'pengawasan')) $cleanName = 'Biaya Pengawasan';
                    else $cleanName = 'Biaya Pengelolaan';
                }

                $valT1 = $termin1Col !== null ? $this->parseNumeric($row[$termin1Col] ?? 0) : 0;
                $valT2 = $termin2Col !== null ? $this->parseNumeric($row[$termin2Col] ?? 0) : 0;
                $valTot = $jumlahCol !== null ? $this->parseNumeric($row[$jumlahCol] ?? 0) : 0;

                $amount = match ($targetTermin) {
                    1 => $termin1Col !== null ? $valT1 : ($valTot > 0 && $valT2 == 0 ? $valTot : 0),
                    2 => $termin2Col !== null ? $valT2 : 0,
                    default => $valTot > 0 ? $valTot : ($valT1 + $valT2),
                };

                if ($amount > 0) {
                    $isPengelolaan = str_contains($cLower, 'pengelolaan');
                    $breakdownMode = $options['breakdown_mode'] ?? 'detailed';

                    if ($isPengelolaan && $breakdownMode === 'detailed') {
                        $decomposedPengelolaan = $this->decomposeBiayaPengelolaan($amount, $options);
                        foreach ($decomposedPengelolaan as $dItem) {
                            $results[] = $dItem;
                        }
                    } else {
                        $existingRab = $options['existing_rab'] ?? null;
                        $rabMatch = !empty($existingRab) ? $this->findBestRabMatch($cleanName, 'operasional', $existingRab, null) : null;

                        $results[] = [
                            'budget_item_id' => $rabMatch?->id,
                            'uraian' => $cleanName,
                            'kode' => $rabMatch?->code ?? '5.1.02.04',
                            'kategori' => 'operasional',
                            'spesifikasi' => "Penyelenggaraan {$cleanName}",
                            'volume' => 1.0,
                            'satuan' => 'Ls',
                            'harga_satuan' => $amount,
                            'total' => $amount,
                            'tanggal' => $options['default_date'] ?? now()->format('Y-m-d'),
                            'toko' => 'Toko ATK & Administrasi Situbondo',
                            'npwp' => null,
                            'metode' => $amount > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                            'room_name' => null,
                        ];
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Membaca file CSV dengan auto-detect pemisah (, atau ; atau tab).
     */
    protected function extractFromCsv(string $filePath, array $options = []): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return [];
        }

        $firstLine = $lines[0];
        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $maxCount = 0;
        foreach ($delimiters as $delim) {
            $count = substr_count($firstLine, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delim;
            }
        }

        $matrix = [];
        $handle = fopen($filePath, 'r');
        while (($data = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
            $matrix[] = $data;
        }
        fclose($handle);

        // Deteksi format resmi
        $isOfficial = false;
        foreach ($matrix as $row) {
            $rowStr = strtolower(implode(' ', array_filter($row, 'is_scalar')));
            if (str_contains($rowStr, 'belanja bahan') || str_contains($rowStr, 'belanja upah') || str_contains($rowStr, 'termin 1') || str_contains($rowStr, 'termin 2')) {
                $isOfficial = true;
                break;
            }
        }

        if ($isOfficial) {
            return $this->parseOfficialRpdMatrix($matrix, $options);
        }

        return $this->mapMatrixToStructuredRows($matrix, $options);
    }

    /**
     * Menemukan baris header dan memetakan baris data format rincian ke array asosiatif.
     */
    protected function mapMatrixToStructuredRows(array $matrix, array $options = []): array
    {
        $headerRowIndex = null;
        $headerMap = [];

        foreach ($matrix as $rowIndex => $row) {
            $rowString = strtolower(implode(' ', array_filter($row, 'is_scalar')));
            if (
                (str_contains($rowString, 'uraian') || str_contains($rowString, 'nama') || str_contains($rowString, 'bahan') || str_contains($rowString, 'item') || str_contains($rowString, 'pekerjaan')) &&
                (str_contains($rowString, 'vol') || str_contains($rowString, 'harga') || str_contains($rowString, 'total') || str_contains($rowString, 'jumlah'))
            ) {
                $headerRowIndex = $rowIndex;
                foreach ($row as $colIndex => $colValue) {
                    $val = strtolower(trim((string)$colValue));
                    if (empty($val)) continue;

                    if (in_array($val, ['no', 'nomor', 'no.', 'no urut', '#'])) {
                        $headerMap['no'] = $colIndex;
                    } elseif (str_contains($val, 'kode') || str_contains($val, 'kd')) {
                        $headerMap['kode'] = $colIndex;
                    } elseif (str_contains($val, 'kategori') || str_contains($val, 'jenis') || str_contains($val, 'kelompok')) {
                        $headerMap['kategori'] = $colIndex;
                    } elseif (str_contains($val, 'uraian') || str_contains($val, 'nama barang') || str_contains($val, 'nama bahan') || str_contains($val, 'nama item') || str_contains($val, 'deskripsi') || str_contains($val, 'pekerjaan')) {
                        $headerMap['uraian'] = $colIndex;
                    } elseif (str_contains($val, 'spek') || str_contains($val, 'spesifikasi') || str_contains($val, 'merk')) {
                        $headerMap['spesifikasi'] = $colIndex;
                    } elseif (str_contains($val, 'vol') || str_contains($val, 'qty') || str_contains($val, 'kuantitas') || str_contains($val, 'banyak')) {
                        $headerMap['volume'] = $colIndex;
                    } elseif (str_contains($val, 'harga satuan') || str_contains($val, 'tarif') || (str_contains($val, 'harga') && !str_contains($val, 'total'))) {
                        $headerMap['harga_satuan'] = $colIndex;
                    } elseif (in_array($val, ['satuan', 'unit', 'sat', 'sat.']) || (!str_contains($val, 'harga') && str_contains($val, 'satuan'))) {
                        $headerMap['satuan'] = $colIndex;
                    } elseif (str_contains($val, 'total') || str_contains($val, 'jumlah harga') || str_contains($val, 'subtotal') || str_contains($val, 'biaya') || str_contains($val, 'jumlah')) {
                        $headerMap['total'] = $colIndex;
                    } elseif (str_contains($val, 'tgl') || str_contains($val, 'tanggal') || str_contains($val, 'waktu')) {
                        $headerMap['tanggal'] = $colIndex;
                    } elseif (str_contains($val, 'toko') || str_contains($val, 'rekanan') || str_contains($val, 'supplier') || str_contains($val, 'penerima')) {
                        $headerMap['toko'] = $colIndex;
                    } elseif (str_contains($val, 'npwp') || str_contains($val, 'ktp')) {
                        $headerMap['npwp'] = $colIndex;
                    } elseif (str_contains($val, 'metode') || str_contains($val, 'kas') || str_contains($val, 'bank')) {
                        $headerMap['metode'] = $colIndex;
                    }
                }
                break;
            }
        }

        if ($headerRowIndex === null) {
            $headerRowIndex = 0;
            $headerMap = [
                'no' => 0,
                'uraian' => 1,
                'volume' => 2,
                'satuan' => 3,
                'harga_satuan' => 4,
                'total' => 5,
            ];
        }

        $results = [];
        for ($i = $headerRowIndex + 1; $i < count($matrix); $i++) {
            $row = $matrix[$i];
            if (empty(array_filter($row, fn($x) => !is_null($x) && trim((string)$x) !== ''))) {
                continue;
            }

            $uraianCol = $headerMap['uraian'] ?? 1;
            $uraian = isset($row[$uraianCol]) ? trim((string)$row[$uraianCol]) : '';

            if (empty($uraian) || str_starts_with(strtolower($uraian), 'total') || str_starts_with(strtolower($uraian), 'jumlah total') || str_starts_with(strtolower($uraian), 'sub total')) {
                continue;
            }

            $volRaw = isset($headerMap['volume']) && isset($row[$headerMap['volume']]) ? $row[$headerMap['volume']] : 1;
            $satuanRaw = isset($headerMap['satuan']) && isset($row[$headerMap['satuan']]) ? $row[$headerMap['satuan']] : 'Unit';
            $hargaRaw = isset($headerMap['harga_satuan']) && isset($row[$headerMap['harga_satuan']]) ? $row[$headerMap['harga_satuan']] : null;
            $totalRaw = isset($headerMap['total']) && isset($row[$headerMap['total']]) ? $row[$headerMap['total']] : null;

            $volume = $this->parseNumeric($volRaw, 1);
            $hargaSatuan = $this->parseNumeric($hargaRaw, 0);
            $total = $this->parseNumeric($totalRaw, 0);

            if ($total <= 0 && $hargaSatuan > 0) {
                $total = $volume * $hargaSatuan;
            } elseif ($hargaSatuan <= 0 && $total > 0 && $volume > 0) {
                $hargaSatuan = $total / $volume;
            }

            if ($total <= 0) {
                continue;
            }

            // Auto-breakdown untuk Mobilisasi & Biaya Pengelolaan
            $uLower = strtolower($uraian);
            $isLumpMob = (str_contains($uLower, 'mobilisasi') || str_contains($uLower, 'demobilisasi'))
                && !str_contains($uLower, 'ongkos angkut')
                && !str_contains($uLower, 'ongkos kirim')
                && !str_contains($uLower, 'pengiriman bahan');
            $isLumpPeng = str_contains($uLower, 'pengelolaan')
                && !str_contains($uLower, 'atk')
                && !str_contains($uLower, 'konsumsi')
                && !str_contains($uLower, 'sppd');

            if ($isLumpMob) {
                $decomposed = $this->decomposeBelanjaMobilisasi($total, null, $options);
                foreach ($decomposed as $d) {
                    $results[] = $d;
                }
                continue;
            }

            if ($isLumpPeng) {
                $decomposed = $this->decomposeBiayaPengelolaan($total, $options);
                foreach ($decomposed as $d) {
                    $results[] = $d;
                }
                continue;
            }

            $results[] = [
                'uraian' => $uraian,
                'kode' => isset($headerMap['kode']) && isset($row[$headerMap['kode']]) ? trim((string)$row[$headerMap['kode']]) : null,
                'kategori' => isset($headerMap['kategori']) && isset($row[$headerMap['kategori']]) ? trim((string)$row[$headerMap['kategori']]) : null,
                'spesifikasi' => isset($headerMap['spesifikasi']) && isset($row[$headerMap['spesifikasi']]) ? trim((string)$row[$headerMap['spesifikasi']]) : null,
                'volume' => $volume,
                'satuan' => trim((string)$satuanRaw) ?: 'Unit',
                'harga_satuan' => $hargaSatuan,
                'total' => $total,
                'tanggal' => isset($headerMap['tanggal']) && isset($row[$headerMap['tanggal']]) ? trim((string)$row[$headerMap['tanggal']]) : null,
                'toko' => isset($headerMap['toko']) && isset($row[$headerMap['toko']]) ? trim((string)$row[$headerMap['toko']]) : null,
                'npwp' => isset($headerMap['npwp']) && isset($row[$headerMap['npwp']]) ? trim((string)$row[$headerMap['npwp']]) : null,
                'metode' => isset($headerMap['metode']) && isset($row[$headerMap['metode']]) ? trim((string)$row[$headerMap['metode']]) : null,
            ];
        }

        return $results;
    }

    /**
     * Menentukan target termin dari opsi request.
     */
    protected function determineTargetTermin(array $options): int|string
    {
        $targetColumn = $options['target_column'] ?? 'auto';
        $termStage = $options['term_stage'] ?? 'Tahap 1';

        if ($targetColumn === 'termin_1') return 1;
        if ($targetColumn === 'termin_2') return 2;
        if ($targetColumn === 'termin_3') return 3;
        if ($targetColumn === 'total' || $targetColumn === 'jumlah') return 'total';

        // Auto mode
        $stageLower = strtolower($termStage);
        if (str_contains($stageLower, 'semua') || str_contains($stageLower, '100%') || str_contains($stageLower, 'total') || str_contains($stageLower, 'jumlah')) {
            return 'total';
        }
        if (str_contains($stageLower, 'tahap 2') || str_contains($stageLower, 'termin 2')) {
            return 2;
        }
        if (str_contains($stageLower, 'tahap 3') || str_contains($stageLower, 'termin 3')) {
            return 3;
        }

        return 1; // Default Termin 1
    }

    /**
     * Ekstrak seluruh token angka mata uang dari baris teks.
     */
    protected function extractCurrencyNumbersFromText(string $text): array
    {
        preg_match_all('/(?:\b|\s)([\d\.,]{4,})(?:\b|\s)/', $text, $matches);
        $numbers = [];
        if (!empty($matches[1])) {
            foreach ($matches[1] as $raw) {
                $clean = preg_replace('/[^\d]/', '', $raw);
                if (!empty($clean) && is_numeric($clean)) {
                    $numbers[] = (float)$clean;
                }
            }
        }
        return $numbers;
    }

    /**
     * Memilih nominal anggaran berdasarkan target termin dari deretan angka baris.
     */
    protected function selectAmountByTargetTermin(array $numbers, int|string $targetTermin): float
    {
        if (empty($numbers)) return 0;

        $amountT1 = 0;
        $amountT2 = 0;
        $amountTot = 0;

        if (count($numbers) >= 4) {
            // [pagu_harga_satuan, termin_1, termin_2, total_jumlah]
            $amountT1 = $numbers[1];
            $amountT2 = $numbers[2];
            $amountTot = $numbers[3];
        } elseif (count($numbers) === 3) {
            // [termin_1, termin_2, total_jumlah]
            $amountT1 = $numbers[0];
            $amountT2 = $numbers[1];
            $amountTot = $numbers[2];
        } elseif (count($numbers) === 2) {
            $amountT1 = $numbers[0];
            $amountTot = $numbers[1];
        } elseif (count($numbers) === 1) {
            $amountTot = $numbers[0];
            $amountT1 = $amountTot;
        }

        return match ($targetTermin) {
            1 => $amountT1 > 0 ? $amountT1 : ($amountTot ?: $amountT2),
            2 => $amountT2 > 0 ? $amountT2 : ($amountTot ?: $amountT1),
            default => $amountTot > 0 ? $amountTot : ($amountT1 + $amountT2),
        };
    }

    /**
     * Konversi nilai teks mata uang menjadi angka float murni.
     */
    protected function parseNumeric($val, float $default = 0): float
    {
        if ($val === null || $val === '') return $default;
        if (is_numeric($val)) return (float)$val;

        $str = (string)$val;
        $str = preg_replace('/[^\d,\.]/', '', $str);

        if (str_contains($str, '.') && str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } elseif (str_contains($str, ',') && !str_contains($str, '.')) {
            $str = str_replace(',', '.', $str);
        }

        return is_numeric($str) ? (float)$str : $default;
    }

    /**
     * Logika Inti "Pecah Bahan & Penentuan Atribut Isian BKU".
     */
    public function analyzeAndBreakdownRow(array $row, int $rowNumber, RevitalizationProject $project, array $context = []): ?array
    {
        $uraian = $row['uraian'];
        $vol = max(0.01, $row['volume']);
        $satuan = $row['satuan'];
        $hargaSatuan = $row['harga_satuan'];
        $totalPrice = $row['total'];

        // 1. Identifikasi Kategori
        $category = $this->determineCategory($uraian, $row['kategori']);

        // 2. Hubungkan ke RAB dengan Smart Semantic Matching
        $budgetItemId = $row['budget_item_id'] ?? null;
        if (!$budgetItemId && !empty($context['existing_rab'])) {
            $matchedRab = $this->findBestRabMatch(
                $uraian,
                $category,
                $context['existing_rab'],
                $row['room_name'] ?? $context['room_name'] ?? null
            );
            if ($matchedRab) {
                $budgetItemId = $matchedRab->id;
            }
        }

        // 3. Tanggal Rencana Penarikan / Belanja BKU
        $defaultDate = $context['default_date'] ?? now()->format('Y-m-d');
        $plannedDate = !empty($row['tanggal'])
            ? $this->parseDateOrDefault($row['tanggal'], $defaultDate)
            : $this->calculateProgressiveDate($defaultDate, $rowNumber, $category);

        // 4. Toko / Pihak Ketiga / Penerima
        $rawContextStore = trim((string)($context['default_store'] ?? ''));
        $defaultStore = $rawContextStore !== '' ? $rawContextStore : '....................';
        $rawRowStore = trim((string)($row['toko'] ?? ''));
        $supplierName = $rawRowStore !== '' ? $rawRowStore : $this->suggestSupplier($category, $uraian, $defaultStore);

        // 5. Metode Pembayaran BKU (Kas Tunai vs Transfer Bank)
        $paymentMethod = $row['metode'] ? (
            str_contains(strtolower($row['metode']), 'bank') || str_contains(strtolower($row['metode']), 'transfer') ? 'belanja_transfer' : 'belanja_tunai'
        ) : ($totalPrice > 5000000 ? 'belanja_transfer' : 'belanja_tunai');

        // 6. Penghitungan Pajak Sesuai Ketentuan Perpajakan (PMK 59/2022 & PMK 168/2023)
        // Belanja Barang/Bahan >= Rp 2.000.000 kena PPN 11% & PPh 22 1.5%
        // Upah Tenaga Kerja/Tukang Swakelola:
        // Berdasarkan PP No. 58/2023 & PMK No. 168/2023 (Berlaku 1 Januari 2024):
        // Pegawai Tidak Tetap / Tenaga Kerja Lepas (Upah Harian / Mingguan / Satuan / Borongan):
        // - Upah bruto sehari per orang s.d. Rp 450.000 : Tarif PPh 21 = 0% (Nihil / Bebas Pajak)
        // - Upah bruto sehari per orang > Rp 450.000 s.d. Rp 2.500.000 : Tarif TER Harian 0,5%
        // - Upah bruto sehari per orang > Rp 2.500.000 : Tarif Pasal 17 UU PPh (DPP 50% x Bruto)
        // Pada Swakelola Revitalisasi Sekolah, upah tukang & pekerja (Rp 80.000 - Rp 150.000 / OH)
        // berada di bawah ambang batas PTKP harian Rp 450.000, sehingga TIDAK DIPOTONG PAJAK (PPh 21 = 0).
        $taxPpn = 0;
        $taxPph22 = 0;
        $taxPph21 = 0;
        $hasTax = false;

        if ($category === 'bahan' || $category === 'alat' || $category === 'operasional') {
            if ($totalPrice >= 2000000) {
                $hasTax = true;
                $dpp = round($totalPrice / 1.11, 2);
                $taxPpn = round($totalPrice - $dpp, 2);
                $taxPph22 = round($dpp * 0.015, 2);
            }
        } elseif ($category === 'upah') {
            $isHok = in_array(strtoupper(trim($satuan)), ['OH', 'HOK', 'HARI']);
            $dailyRate = $isHok ? $hargaSatuan : 0;

            if ($dailyRate > 2500000) {
                $hasTax = true;
                // Tarif Pasal 17 UU PPh (5%) atas DPP 50% bruto harian
                $taxPph21 = round($totalPrice * 0.5 * 0.05, 2);
            } elseif ($dailyRate > 450000) {
                $hasTax = true;
                // Skema TER Harian 0,5% sesuai PMK 168/2023 Pasal 12 ayat (1) huruf b
                $taxPph21 = round($totalPrice * 0.005, 2);
            } else {
                // Di bawah ambang batas Rp 450.000/hari atau upah swakelola rombongan: Bebas Pajak (PPh 21 = 0)
                $hasTax = false;
                $taxPph21 = 0;
            }
        }

        $taxTotal = $taxPpn + $taxPph22 + $taxPph21;
        $netAmount = $totalPrice - ($taxPph22 + $taxPph21);

        // 7. Narasi Standar BKU
        $bkuDescription = $this->generateBkuDescription($category, $uraian, $vol, $satuan, $supplierName, $project->title);

        return [
            'budget_item_id' => $budgetItemId,
            'row_number' => $rowNumber,
            'item_code' => $row['kode'] ?? ($category === 'upah' ? '5.1.02.02' : ($category === 'operasional' ? '5.1.02.04' : '5.1.02.01')),
            'category' => $category,
            'item_name' => $uraian,
            'specification' => $row['spesifikasi'],
            'volume' => $vol,
            'unit' => $satuan,
            'unit_price' => $hargaSatuan,
            'total_price' => $totalPrice,
            'planned_date' => $plannedDate,
            'supplier_name' => $supplierName,
            'supplier_npwp' => $row['npwp'],
            'payment_method' => $paymentMethod,
            'bku_number' => 'DRAFT-BKU-' . str_pad($rowNumber, 3, '0', STR_PAD_LEFT),
            'bku_description' => $bkuDescription,
            'has_tax' => $hasTax,
            'tax_ppn' => $taxPpn,
            'tax_pph22' => $taxPph22,
            'tax_pph21' => $taxPph21,
            'tax_total' => $taxTotal,
            'net_amount' => $netAmount,
            'is_posted' => false,
        ];
    }

    /**
     * Tentukan kategori dari kata kunci uraian.
     */
    protected function determineCategory(string $uraian, ?string $explicit): string
    {
        if ($explicit) {
            $e = strtolower(trim($explicit));
            if (str_contains($e, 'upah') || str_contains($e, 'tenaga') || str_contains($e, 'tukang')) return 'upah';
            if (str_contains($e, 'alat') || str_contains($e, 'sewa')) return 'alat';
            if (str_contains($e, 'operasional') || str_contains($e, 'atk') || str_contains($e, 'admin')) return 'operasional';
            if (str_contains($e, 'bahan') || str_contains($e, 'material')) return 'bahan';
        }

        $text = strtolower($uraian);

        if (preg_match('/(upah|tukang|pekerja|mandor|ongkos|hok|tenaga kerja|kepala tukang)/i', $text)) {
            return 'upah';
        }

        if (preg_match('/(sewa|molen|vibrator|scaffolding|peralatan|gerobak dorong|sekop|cangkul)/i', $text)) {
            return 'alat';
        }

        if (preg_match('/(atk|kertas|materai|spanduk|banner|prasasti|dokumentasi|konsumsi|snack|jilid|fotocopy|perencanaan|pengawasan|pengelolaan)/i', $text)) {
            return 'operasional';
        }

        return 'bahan';
    }

    /**
     * Rekomendasi nama pihak ketiga / toko berdasarkan kategori.
     */
    protected function suggestSupplier(string $category, string $uraian, string $defaultStore): string
    {
        $store = trim($defaultStore) !== '' ? trim($defaultStore) : '....................';

        return match ($category) {
            'upah' => 'Kelompok Tukang & Pekerja Swakelola',
            'alat' => 'Penyedia Sewa Peralatan Situbondo',
            'operasional' => 'Toko ATK & Penggandaan Situbondo',
            default => $store,
        };
    }

    /**
     * Hasilkan uraian transaksi standar BKU.
     */
    protected function generateBkuDescription(string $category, string $uraian, float $vol, string $satuan, string $supplier, string $projectTitle): string
    {
        $volFormatted = number_format($vol, $vol == (int)$vol ? 0 : 2, ',', '.');
        $cleanUraian = trim(preg_replace('/^(belanja bahan|belanja upah|belanja|upah)\s*[-–—:]*\s*/i', '', $uraian));
        if (empty($cleanUraian)) {
            $cleanUraian = $uraian;
        }

        return match ($category) {
            'upah' => "Pembayaran upah tenaga kerja/tukang {$cleanUraian} ({$volFormatted} {$satuan}) untuk pekerjaan {$projectTitle} kepada {$supplier}",
            'alat' => "Pembayaran sewa/pengadaan alat {$cleanUraian} ({$volFormatted} {$satuan}) pada {$supplier} untuk pekerjaan {$projectTitle}",
            'operasional' => "Pembayaran belanja operasional/administrasi {$cleanUraian} pada {$supplier} untuk pekerjaan {$projectTitle}",
            default => "Pembayaran belanja bahan {$cleanUraian} ({$volFormatted} {$satuan}) pada {$supplier} untuk pekerjaan {$projectTitle}",
        };
    }

    /**
     * Memecah pagu anggaran gelondongan Belanja Bahan / Upah per ruang menjadi rincian material & upah HOK riil.
     * Jika proyek memiliki rincian RAB di database, sistem mengutamakan dekomposisi berbasis item riil RAB!
     */
    public function decomposeLumpSumToDetailedItems(float $totalBudget, string $category, ?string $roomName, array $options = []): array
    {
        $existingRab = $options['existing_rab'] ?? null;
        $targetMenu = $this->resolveMenuPekerjaanFromRoomName($roomName);

        // Opsi: Gunakan dekomposisi langsung item riil RAB jika diminta secara eksplisit
        if (($options['breakdown_mode'] ?? null) === 'direct_rab' || ($options['decompose_mode'] ?? null) === 'direct_rab') {
            $rabMatchingCategory = ($category === 'bahan') ? 'Material/Bahan' : (($category === 'upah') ? 'Upah Tenaga Kerja' : (($category === 'alat') ? 'Peralatan/Sewa' : 'Honor & Operasional'));
            if (!empty($existingRab) && $existingRab->isNotEmpty()) {
                $directRabItems = $existingRab->filter(function ($rab) use ($targetMenu, $rabMatchingCategory) {
                    return $rab->menu_pekerjaan === $targetMenu && $rab->category === $rabMatchingCategory;
                });

                if ($directRabItems->isNotEmpty() && $directRabItems->sum('total_price') > 0) {
                    return $this->decomposeFromRealBudgetItems($totalBudget, $category, $roomName, $directRabItems, $options);
                }
            }
        }

        // PRIORITAS 2: Gunakan katalog dekomposisi cerdas jika data RAB belum diinput
        $roomType = $this->detectRoomType($roomName);
        $rawDefaultStore = trim((string)($options['default_store'] ?? ''));
        $defaultStore = $rawDefaultStore !== '' ? $rawDefaultStore : '....................';
        $defaultDate = $options['default_date'] ?? null;
        $catalog = $this->getDetailedCatalog($roomType, $category, $defaultStore);

        if (empty($catalog)) {
            $singleRab = !empty($existingRab) ? $this->findBestRabMatch($category === 'bahan' ? 'Belanja Bahan' : 'Belanja Upah', $category, $existingRab, $roomName) : null;
            return [[
                'budget_item_id' => $singleRab?->id,
                'uraian' => ($category === 'bahan' ? 'Belanja Bahan' : 'Belanja Upah') . ($roomName ? " - {$roomName}" : ''),
                'kode' => $singleRab?->code ?? ($category === 'upah' ? '5.1.02.02' : '5.1.02.01'),
                'kategori' => $category,
                'spesifikasi' => ($category === 'bahan' ? 'Pengadaan Bahan/Material ' : 'Upah Tukang & Tenaga ') . ($roomName ?: 'Rehabilitasi'),
                'volume' => 1.0,
                'satuan' => 'Paket',
                'harga_satuan' => $totalBudget,
                'total' => $totalBudget,
                'tanggal' => $defaultDate,
                'toko' => $category === 'upah' ? 'Kelompok Tukang & Pekerja Swakelola' : $defaultStore,
                'npwp' => null,
                'metode' => $totalBudget > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                'room_name' => $roomName,
            ]];
        }

        $totalBudget = round($totalBudget, 0);
        $runningTotal = 0;
        $items = [];
        $catalogCount = count($catalog);

        foreach ($catalog as $idx => $itemDef) {
            $isLast = ($idx === $catalogCount - 1);
            if ($isLast) {
                $itemTotal = max(0, $totalBudget - $runningTotal);
            } else {
                $itemTotal = round($totalBudget * $itemDef['weight'], 0);
                $runningTotal += $itemTotal;
            }

            if ($itemTotal <= 0) {
                continue;
            }

            $refPrice = $itemDef['ref_price'];
            $unit = $itemDef['unit'];

            if (in_array($unit, ['M³', 'M²'])) {
                $vol = max(0.5, round($itemTotal / $refPrice, 1));
            } else {
                $vol = max(1, round($itemTotal / $refPrice));
            }

            $unitPrice = round($itemTotal / $vol, 2);

            $itemDate = $this->calculateRealisticItemDate(
                $defaultDate ?: now()->format('Y-m-d'),
                $itemDef['week'] ?? 1,
                $itemDef['day_in_week'] ?? 0,
                $category
            );

            $roomSuffix = $roomName ? " - {$roomName}" : '';
            $itemName = $itemDef['name'] . $roomSuffix;
            $spec = $itemDef['spec'] . ($roomName ? " pada {$roomName}" : '');
            $supplier = $itemDef['supplier'] ?? ($category === 'upah' ? 'Kelompok Tukang & Pekerja Swakelola' : $defaultStore);

            // Hubungkan dengan Smart Semantic Matching ke item RAB jika ada
            $rabMatch = null;
            if (!empty($existingRab)) {
                $rabMatch = $this->findBestRabMatch($itemName, $category, $existingRab, $roomName);
            }

            $items[] = [
                'budget_item_id' => $rabMatch?->id,
                'uraian' => $itemName,
                'kode' => $rabMatch?->code ?? $itemDef['account_code'],
                'kategori' => $category,
                'spesifikasi' => $spec,
                'volume' => $vol,
                'satuan' => $unit,
                'harga_satuan' => $unitPrice,
                'total' => $itemTotal,
                'tanggal' => $itemDate,
                'toko' => $supplier,
                'npwp' => null,
                'metode' => $itemTotal > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                'room_name' => $roomName,
            ];
        }

        return $items;
    }

    /**
     * Dekomposisi langsung berbasis item riil dari data RAB (BudgetItems).
     * Menghasilkan pecahan bahan yang 100% presisi dengan nama, kode, satuan, harga, dan ID dari RAB.
     */
    public function decomposeFromRealBudgetItems(float $totalBudget, string $category, ?string $roomName, $rabItems, array $options = []): array
    {
        $rawDefaultStore = trim((string)($options['default_store'] ?? ''));
        $defaultStore = $rawDefaultStore !== '' ? $rawDefaultStore : '....................';
        $defaultDate = $options['default_date'] ?? now()->format('Y-m-d');
        $rabTotalSum = $rabItems->sum('total_price');

        if ($rabTotalSum <= 0) {
            return [];
        }

        $ratio = $totalBudget / $rabTotalSum;
        $runningTotal = 0;
        $items = [];
        $count = $rabItems->count();

        foreach ($rabItems->values() as $idx => $rab) {
            $isLast = ($idx === $count - 1);
            if ($isLast) {
                $itemTotal = max(0, round($totalBudget - $runningTotal, 2));
            } else {
                $itemTotal = round($rab->total_price * $ratio, 2);
                $runningTotal += $itemTotal;
            }

            if ($itemTotal <= 0) {
                continue;
            }

            $vol = round($rab->volume * $ratio, 2);
            $unitPrice = ($vol > 0) ? round($itemTotal / $vol, 2) : $rab->unit_price;

            // Alokasikan minggu pelaksanaan bertahap (1 s.d. 4)
            $weekNum = min(4, max(1, (int)ceil(($idx + 1) / max(1, $count / 4))));
            $itemDate = $this->calculateRealisticItemDate($defaultDate, $weekNum, ($idx % 5) + 1, $category);

            $supplier = ($category === 'upah')
                ? 'Kelompok Tukang & Pekerja Swakelola'
                : $defaultStore;

            $items[] = [
                'budget_item_id' => $rab->id,
                'uraian' => $rab->name,
                'kode' => $rab->code ?: ($category === 'upah' ? '5.1.02.02' : '5.1.02.01'),
                'kategori' => $category,
                'spesifikasi' => $rab->notes ?: "Pekerjaan {$rab->name} pada " . ($roomName ?: 'Revitalisasi'),
                'volume' => $vol,
                'satuan' => $rab->unit,
                'harga_satuan' => $unitPrice,
                'total' => $itemTotal,
                'tanggal' => $itemDate,
                'toko' => $supplier,
                'npwp' => null,
                'metode' => $itemTotal > 5000000 ? 'Transfer Bank' : 'Kas Tunai',
                'room_name' => $roomName,
            ];
        }

        return $items;
    }

    /**
     * Memecah pagu gelondongan Belanja Mobilisasi secara otomatis menggunakan AI Google Gemini
     * (atau Smart Heuristic Engine Situbondo jika offline / tanpa API key) menjadi biaya tambahan
     * ongkos kirim/angkut bahan material dari toko ke sekolah/tempat yang ditentukan serta sewa peralatan kerja.
     */
    public function decomposeBelanjaMobilisasi(float $totalAmount, ?string $roomName = null, array $options = []): array
    {
        if ($totalAmount <= 0) {
            return [];
        }

        $project = $options['project'] ?? null;
        $aiOptions = array_merge($options, [
            'room_name' => $roomName,
            'default_date' => $options['default_date'] ?? now()->format('Y-m-d'),
            'existing_rab' => $options['existing_rab'] ?? null,
            'school_name' => $options['school_name'] ?? ($project?->school?->name ?? 'Satuan Pendidikan Kab. Situbondo'),
            'project_title' => $options['project_title'] ?? ($project?->title ?? 'Revitalisasi Sekolah'),
            'term_stage' => $options['term_stage'] ?? 'Tahap 1',
        ]);

        return app(GeminiRpdService::class)->decomposeMobilisasiWithAi($totalAmount, $aiOptions);
    }

    /**
     * Memecah pagu gelondongan Biaya Pengelolaan Non-Fisik secara otomatis menggunakan AI Google Gemini
     * (atau Smart Heuristic Engine Situbondo jika offline / tanpa API key) menjadi pos kegiatan operasional riil
     * Tim P2SP selama revitalisasi berlangsung (ATK/SPJ, konsumsi rapat, SPPD koordinasi dinas/bank,
     * spanduk transparansi, dan materai 10.000) yang rasional dan anti-temuan BPK.
     */
    public function decomposeBiayaPengelolaan(float $totalAmount, array $options = []): array
    {
        if ($totalAmount <= 0) {
            return [];
        }

        $project = $options['project'] ?? null;
        $aiOptions = array_merge($options, [
            'default_date' => $options['default_date'] ?? now()->format('Y-m-d'),
            'existing_rab' => $options['existing_rab'] ?? null,
            'school_name' => $options['school_name'] ?? ($project?->school?->name ?? 'Satuan Pendidikan Kab. Situbondo'),
            'project_title' => $options['project_title'] ?? ($project?->title ?? 'Revitalisasi Sekolah'),
            'term_stage' => $options['term_stage'] ?? 'Tahap 1',
        ]);

        return app(GeminiRpdService::class)->decomposePengelolaanWithAi($totalAmount, $aiOptions);
    }

    /**
     * Menentukan nama Menu Pekerjaan standar dari teks nama ruangan / lingkup pekerjaan.
     */
    public function resolveMenuPekerjaanFromRoomName(?string $roomName): string
    {
        if (empty($roomName)) {
            return 'Rehabilitasi Ruang Kelas';
        }

        $r = strtolower($roomName);
        if (preg_match('/(toilet|jamban|sanitasi|km|wc|kamar mandi)/i', $r)) {
            return 'Pembangunan / Rehabilitasi Toilet';
        }
        if (preg_match('/(perpustakaan|perpus|baca|literasi)/i', $r)) {
            return 'Rehabilitasi Ruang Perpustakaan';
        }
        if (preg_match('/(lab|laboratorium|komputer|ipa|bahasa)/i', $r)) {
            return 'Rehabilitasi Ruang Laboratorium';
        }
        if (preg_match('/(guru|kantor|kepala sekolah|tu|tata usaha)/i', $r)) {
            return 'Rehabilitasi Ruang Guru / Pimpinan';
        }
        if (preg_match('/(uks|kesehatan)/i', $r)) {
            return 'Pembangunan Ruang UKS';
        }
        if (preg_match('/(pembangunan|rkb)/i', $r)) {
            return 'Pembangunan Ruang Kelas Baru';
        }

        return 'Rehabilitasi Ruang Kelas';
    }

    /**
     * Smart Semantic Matcher: Mencocokkan item pecahan bahan RPD ke item RAB resmi (BudgetItem).
     * Mempertimbangkan afinitas Menu Pekerjaan, kesesuaian kategori, sinonim kata kunci topik, dan token nama.
     */
    public function findBestRabMatch(string $uraian, string $category, $existingRab, ?string $roomContext = null): ?BudgetItem
    {
        if (empty($existingRab) || $existingRab->isEmpty()) {
            return null;
        }

        $u = strtolower(trim($uraian));

        // 1. Deteksi Target Menu Pekerjaan dari konteks ruangan atau teks uraian
        $targetMenu = null;
        if (!empty($roomContext)) {
            $r = strtolower($roomContext);
            if (preg_match('/(kelas|ruang\s*kelas|rk)/i', $r)) $targetMenu = 'Rehabilitasi Ruang Kelas';
            elseif (preg_match('/(perpus|perpustakaan|baca)/i', $r)) $targetMenu = 'Rehabilitasi Ruang Perpustakaan';
            elseif (preg_match('/(toilet|jamban|sanitasi|km|wc)/i', $r)) $targetMenu = 'Pembangunan / Rehabilitasi Toilet';
            elseif (preg_match('/(uks)/i', $r)) $targetMenu = 'Pembangunan Ruang UKS';
            elseif (preg_match('/(lab|laboratorium)/i', $r)) $targetMenu = 'Rehabilitasi Ruang Laboratorium';
            elseif (preg_match('/(guru|kantor|tu)/i', $r)) $targetMenu = 'Rehabilitasi Ruang Guru / Pimpinan';
        }

        if (!$targetMenu) {
            if (str_contains($u, 'kelas')) $targetMenu = 'Rehabilitasi Ruang Kelas';
            elseif (str_contains($u, 'perpus')) $targetMenu = 'Rehabilitasi Ruang Perpustakaan';
            elseif (str_contains($u, 'toilet') || str_contains($u, 'jamban')) $targetMenu = 'Pembangunan / Rehabilitasi Toilet';
            elseif (str_contains($u, 'uks')) $targetMenu = 'Pembangunan Ruang UKS';
            elseif (str_contains($u, 'lab')) $targetMenu = 'Rehabilitasi Ruang Laboratorium';
        }

        // 2. Pemetaan Kategori RPD ke Kategori RAB
        $rabCategory = match ($category) {
            'bahan' => 'Material/Bahan',
            'upah' => 'Upah Tenaga Kerja',
            'alat' => 'Peralatan/Sewa',
            default => 'Honor & Operasional',
        };

        // 3. Kluster Kata Kunci Topik Pekerjaan
        $keywordClusters = [
            'baja_ringan' => ['baja ringan', 'kuda-kuda', 'profil c', 'c75', 'reng', 'truss', 'canai dingin', 'atap pelana'],
            'genteng' => ['genteng', 'karang pilang', 'nok', 'bubung', 'spandek', 'seng', 'atap genteng'],
            'semen_beton' => ['semen', 'beton', 'cor', 'f\'c', 'mutu', 'ready mix', 'adukan', 'spesi', 'slump'],
            'pasir_urukan' => ['pasir pasang', 'pasir cor', 'pasir uruk', 'urukan', 'urugan', 'sirtu', 'kerikil', 'agregat'],
            'bata' => ['bata merah', 'bata', 'batako', 'hebel', 'bata ringan', 'dinding bata'],
            'besi_tulangan' => ['besi beton', 'penulangan', 'bjtp', 'bjts', 'tulangan', 'beugel', 'sengkang', 'wiremesh'],
            'plesteran_acian' => ['plesteran', 'plester', 'skoning', 'acian', 'aci', 'trasram'],
            'keramik' => ['keramik', 'lantai keramik', 'granit', 'ubin', 'plin', 'homogenous'],
            'kusen_pintu' => ['kusen', 'pintu', 'jendela', 'spandrell', 'aluminium', 'pvc kamar mandi', 'engsel', 'kunci', 'slot', 'spring knip', 'kait angin'],
            'kaca' => ['kaca', 'kaca polos', 'jendela kaca', 'rayban'],
            'plafon' => ['plafon', 'langit-langit', 'grc', 'kalsiboard', 'gypsum', 'hollow', 'galvalum'],
            'lisplank' => ['lisplank', 'serat semen'],
            'cat' => ['cat tembok', 'cat plafond', 'cat dasar', 'plamir', 'plamuur', 'pengecatan', 'cat penutup', 'cat genteng'],
            'listrik' => ['kabel', 'lampu', 'fitting', 'saklar', 'stop kontak', 'mcb', 'box mcb', 'instalasi listrik', 'led'],
            'sanitasi' => ['closet', 'kloset', 'jongkok', 'duduk', 'floor drain', 'kran', 'afur', 'pipa pvc', 'saluran', 'septictank', 'plumbing', 'waterproofing'],
            'bongkar' => ['bongkar', 'pembongkaran', 'pembersihan lapangan', 'perataan'],
            'galian' => ['galian', 'penggalian', 'angkut', 'mengangkut tanah'],
            'bekisting' => ['bekisting', 'cetakan beton', 'triplek bekisting'],
            'sewa_alat' => ['molen', 'mixer', 'vibrator', 'stamper', 'genset', 'scaffolding', 'perancah', 'mobilisasi', 'demobilisasi', 'peralatan', 'alat', 'angkut', 'pengangkutan', 'ongkos kirim', 'pengiriman material', 'ekspedisi'],
            'operasional' => ['k3', 'prasasti', 'papan nama', 'papan', 'laporan', 'spj', 'administrasi', 'honor', 'konsumsi', 'fotocopy', 'perencanaan', 'pengawasan', 'pengelolaan', 'manajemen', 'rk3k', 'site work', 'persiapan', 'operasional', 'biaya', 'transport', 'transportasi', 'atk', 'materai', 'banner', 'spanduk', 'rapat', 'p2sp'],
        ];

        $bestMatch = null;
        $bestScore = 0;

        foreach ($existingRab as $rab) {
            $score = 0;
            $rName = strtolower($rab->name);
            $rNotes = strtolower($rab->notes ?? '');
            $rFull = $rName . ' ' . $rNotes;

            // Kriteria A: Kesesuaian Kategori Pos Belanja
            if ($rab->category === $rabCategory) {
                $score += 10;
            }

            // Kriteria B: Kesesuaian Menu Pekerjaan
            if ($targetMenu && $rab->menu_pekerjaan === $targetMenu) {
                $score += 15;
            } elseif ($targetMenu && str_contains(strtolower($rab->menu_pekerjaan), strtolower(substr($targetMenu, 0, 8)))) {
                $score += 8;
            }

            // Kriteria C: Kesesuaian Kluster Topik Kata Kunci
            foreach ($keywordClusters as $topic => $synonyms) {
                $matchedInUraian = false;
                foreach ($synonyms as $syn) {
                    if (str_contains($u, $syn)) {
                        $matchedInUraian = true;
                        break;
                    }
                }

                if ($matchedInUraian) {
                    foreach ($synonyms as $syn) {
                        if (str_contains($rFull, $syn)) {
                            $score += 12;
                            break;
                        }
                    }
                }
            }

            // Kriteria D: Token nama langsung
            $words = array_filter(explode(' ', preg_replace('/[^a-zA-Z0-9\s]/', '', $u)));
            foreach ($words as $w) {
                if (strlen($w) >= 4 && !in_array($w, ['ruang', 'kelas', 'belanja', 'bahan', 'upah', 'minggu', 'satuan', 'untuk', 'pada', 'dan'])) {
                    if (str_contains($rFull, $w)) {
                        $score += 3;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $rab;
            }
        }

        // Ambang batas minimal kecocokan
        if ($bestScore >= 15) {
            return $bestMatch;
        }

        // Fallback untuk belanja operasional / alat jika ada pos yang se-kategori
        if (in_array($category, ['operasional', 'alat']) && $bestScore >= 10) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Identifikasi jenis ruangan dari nama ruangan (Ruang Kelas, Perpustakaan, Toilet, dll.).
     */
    public function detectRoomType(?string $roomName): string
    {
        if (empty($roomName)) {
            return 'ruang_kelas';
        }

        $r = strtolower($roomName);

        if (preg_match('/(toilet|jamban|sanitasi|km|wc|kamar mandi)/i', $r)) {
            return 'toilet';
        }

        if (preg_match('/(perpustakaan|perpus|baca|literasi)/i', $r)) {
            return 'perpustakaan';
        }

        if (preg_match('/(lab|laboratorium|komputer|ipa|bahasa)/i', $r)) {
            return 'lab';
        }

        if (preg_match('/(guru|kantor|kepala sekolah|tu|tata usaha)/i', $r)) {
            return 'ruang_guru';
        }

        if (preg_match('/(uks|kesehatan)/i', $r)) {
            return 'uks';
        }

        if (preg_match('/(kelas|ruang kelas|rk|belajar)/i', $r)) {
            return 'ruang_kelas';
        }

        return 'ruang_kelas';
    }

    /**
     * Katalog komponen material dan HOK upah untuk dekomposisi pecah bahan per jenis ruangan.
     * Dilengkapi penanda minggu (week 1-4) dan hari kerja (day_in_week) agar penanggalan BKU realistis.
     */
    public function getDetailedCatalog(string $roomType, string $category, string $defaultStore): array
    {
        if ($category === 'upah') {
            return match ($roomType) {
                'toilet' => [
                    [
                        'name' => 'Upah Tukang Plumbing & Sanitasi (Minggu 2)',
                        'spec' => 'HOK Tukang pipa air bersih, saluran kotor & pasang kloset (Estimasi 1-2 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 125000,
                        'weight' => 0.22,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 2,
                        'day_in_week' => 5,
                        'day_offset' => 13,
                    ],
                    [
                        'name' => 'Upah Pekerja Galian & Saluran Sanitasi (Minggu 2)',
                        'spec' => 'HOK Pekerja galian pipa/septictank, buis beton & urugan pasir (Estimasi 1-2 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.15,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 2,
                        'day_in_week' => 5,
                        'day_offset' => 13,
                    ],
                    [
                        'name' => 'Upah Tukang Pasang Keramik & Plester Kedap Air (Minggu 3)',
                        'spec' => 'HOK Tukang keramik lantai, dinding porselen & plesteran trasram (Estimasi 1-2 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 125000,
                        'weight' => 0.25,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 3,
                        'day_in_week' => 5,
                        'day_offset' => 20,
                    ],
                    [
                        'name' => 'Upah Pekerja Adukan Spesi & Waterproofing (Minggu 3)',
                        'spec' => 'HOK Pekerja adukan spesi kedap air, aplikasi waterproofing & angkut material (Estimasi 1-2 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.15,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 3,
                        'day_in_week' => 5,
                        'day_offset' => 20,
                    ],
                    [
                        'name' => 'Upah Tukang Pasang Pintu PVC & Aksesoris Kran (Minggu 4)',
                        'spec' => 'HOK Tukang pasang pintu PVC pabrikasi, kran air stainless & floor drain (Estimasi 1 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 120000,
                        'weight' => 0.13,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 4,
                        'day_in_week' => 5,
                        'day_offset' => 27,
                    ],
                    [
                        'name' => 'Upah Pekerja Pembersihan & Uji Saluran (Minggu 4)',
                        'spec' => 'HOK Pekerja pembersihan nat, sisa adukan spesi & uji coba kelancaran air (Estimasi 1-2 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.10,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 4,
                        'day_in_week' => 5,
                        'day_offset' => 27,
                    ],
                ],
                'perpustakaan' => [
                    [
                        'name' => 'Upah Tukang Batu & Plesteran Halus (Minggu 1)',
                        'spec' => 'HOK Tukang pasangan bata, plesteran halus & acian dinding perpus (Estimasi 2-3 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 125000,
                        'weight' => 0.18,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 1,
                        'day_in_week' => 5,
                        'day_offset' => 6,
                    ],
                    [
                        'name' => 'Upah Pekerja / Pembantu Tukang Pasangan (Minggu 1)',
                        'spec' => 'HOK Pekerja adukan spesi, angkut bata/pasir & pembersihan bongkaran (Estimasi 3 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.12,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 1,
                        'day_in_week' => 5,
                        'day_offset' => 6,
                    ],
                    [
                        'name' => 'Upah Tukang Plafon Akustik & Rangka Hollow (Minggu 2)',
                        'spec' => 'HOK Tukang rangka hollow galvanis & pasang kalsiboard akustik (Estimasi 2-3 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 125000,
                        'weight' => 0.15,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 2,
                        'day_in_week' => 5,
                        'day_offset' => 13,
                    ],
                    [
                        'name' => 'Upah Pekerja Bantu Rangka Plafon (Minggu 2)',
                        'spec' => 'HOK Pekerja bantu perancah steger, lansir lembaran plafon & potong hollow (Estimasi 2-3 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.10,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 2,
                        'day_in_week' => 5,
                        'day_offset' => 13,
                    ],
                    [
                        'name' => 'Upah Tukang Keramik Lantai & Kusen Kaca (Minggu 3)',
                        'spec' => 'HOK Tukang keramik 40x40 heavy duty & pasang kusen jendela sirkulasi (Estimasi 2-3 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 125000,
                        'weight' => 0.16,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 3,
                        'day_in_week' => 5,
                        'day_offset' => 20,
                    ],
                    [
                        'name' => 'Upah Pekerja Adukan Keramik & Nat Lantai (Minggu 3)',
                        'spec' => 'HOK Pekerja adukan semen perekat, pengisian nat lantai & potong keramik (Estimasi 2-3 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.10,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 3,
                        'day_in_week' => 5,
                        'day_offset' => 20,
                    ],
                    [
                        'name' => 'Upah Tukang Cat & Finishing Interior Perpus (Minggu 4)',
                        'spec' => 'HOK Tukang plamir, cat tembok interior rendah bau & finishing kusen (Estimasi 2 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 120000,
                        'weight' => 0.11,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 4,
                        'day_in_week' => 5,
                        'day_offset' => 27,
                    ],
                    [
                        'name' => 'Upah Pekerja Pembersihan & Perapihan Perpus (Minggu 4)',
                        'spec' => 'HOK Pekerja pembersihan bercak cat kaca, pel lantai & pembersihan akhir (Estimasi 2 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.08,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 4,
                        'day_in_week' => 5,
                        'day_offset' => 27,
                    ],
                ],
                default => [
                    [
                        'name' => 'Upah Tukang Batu & Struktur Dinding (Minggu 1)',
                        'spec' => 'HOK Tukang pasangan pondasi, dinding bata, balok latai & plesteran (Estimasi 3-4 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 125000,
                        'weight' => 0.18,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 1,
                        'day_in_week' => 5,
                        'day_offset' => 6,
                    ],
                    [
                        'name' => 'Upah Pekerja / Pembantu Tukang Struktur (Minggu 1)',
                        'spec' => 'HOK Pekerja pengadukan spesi, angkut batu/pasir/semen & galian (Estimasi 4-5 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.12,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 1,
                        'day_in_week' => 5,
                        'day_offset' => 6,
                    ],
                    [
                        'name' => 'Upah Tukang Rangka Atap & Baja Ringan (Minggu 2)',
                        'spec' => 'HOK Tukang pasang kuda-kuda baja ringan C75, reng & atap genteng/spandek (Estimasi 3-4 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 130000,
                        'weight' => 0.16,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 2,
                        'day_in_week' => 5,
                        'day_offset' => 13,
                    ],
                    [
                        'name' => 'Upah Pekerja Bantu Pasang Rangka Atap (Minggu 2)',
                        'spec' => 'HOK Pekerja lansir material baja ringan, atap ke steger & perapihan (Estimasi 3-4 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.10,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 2,
                        'day_in_week' => 5,
                        'day_offset' => 13,
                    ],
                    [
                        'name' => 'Upah Tukang Plafon & Keramik Lantai (Minggu 3)',
                        'spec' => 'HOK Tukang rangka hollow, papan kalsiboard, keramik lantai & plin (Estimasi 3-4 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 125000,
                        'weight' => 0.15,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 3,
                        'day_in_week' => 5,
                        'day_offset' => 20,
                    ],
                    [
                        'name' => 'Upah Pekerja Adukan Keramik & Plafon (Minggu 3)',
                        'spec' => 'HOK Pekerja adukan spesi semen perekat keramik, angkut material & bantu steger (Estimasi 3-4 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.09,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 3,
                        'day_in_week' => 5,
                        'day_offset' => 20,
                    ],
                    [
                        'name' => 'Upah Tukang Cat & Finishing Interior/Eksterior (Minggu 4)',
                        'spec' => 'HOK Tukang plamir, cat tembok interior/eksterior, cat plafon & stel kusen pintu/jendela (Estimasi 2-3 tukang/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 120000,
                        'weight' => 0.12,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 4,
                        'day_in_week' => 5,
                        'day_offset' => 27,
                    ],
                    [
                        'name' => 'Upah Pekerja Pembersihan Akhir & Rapihan (Minggu 4)',
                        'spec' => 'HOK Pekerja amplas dinding, pembersihan sisa adukan, pel lantai & buang sisa puing (Estimasi 2-3 pekerja/hari x 6 hari)',
                        'unit' => 'OH',
                        'ref_price' => 90000,
                        'weight' => 0.08,
                        'account_code' => '5.1.02.02',
                        'supplier' => 'Kelompok Tukang & Pekerja Swakelola',
                        'week' => 4,
                        'day_in_week' => 5,
                        'day_offset' => 27,
                    ],
                ],
            };
        }

        // Category 'bahan'
        return match ($roomType) {
            'toilet' => [
                [
                    'name' => 'Semen Portland 50 Kg & Pasir Pasang Sanitasi',
                    'spec' => 'Semen Gresik/Dynamix 50kg & Pasir Pasang Kali Sampean',
                    'unit' => 'Zak',
                    'ref_price' => 68000,
                    'weight' => 0.18,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 1,
                    'day_in_week' => 1,
                    'day_offset' => 2,
                ],
                [
                    'name' => 'Pipa PVC AW 3", 4", 1/2" & Fitting Sambungan Rucika',
                    'spec' => 'Pipa saluran air bersih AW 1/2" & air kotor AW 3"/4" + fitting knee tee',
                    'unit' => 'Batang',
                    'ref_price' => 85000,
                    'weight' => 0.18,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 2,
                    'day_in_week' => 1,
                    'day_offset' => 8,
                ],
                [
                    'name' => 'Keramik Lantai Anti Selip 25x25 & Dinding 20x25',
                    'spec' => 'Keramik lantai kasar anti selip 25x25 & dinding glossy mulia 20x25',
                    'unit' => 'Dus',
                    'ref_price' => 62000,
                    'weight' => 0.22,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 3,
                    'day_in_week' => 1,
                    'day_offset' => 16,
                ],
                [
                    'name' => 'Pintu Toilet PVC Pabrikasi + Kunci Selot & Ventilasi',
                    'spec' => 'Pintu PVC tebal lengkap kusen, engsel, slot stainless & jalusi',
                    'unit' => 'Unit',
                    'ref_price' => 350000,
                    'weight' => 0.12,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 3,
                    'day_in_week' => 3,
                    'day_offset' => 18,
                ],
                [
                    'name' => 'Kran Air Stainless Steel 1/2" & Floor Drain Anti Bau',
                    'spec' => 'Kran air leher angsa sus 304 & saringan lantai kuningan anti serangga',
                    'unit' => 'Buah',
                    'ref_price' => 75000,
                    'weight' => 0.08,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 4,
                    'day_in_week' => 0,
                    'day_offset' => 22,
                ],
                [
                    'name' => 'Kloset Jongkok Porselen SNI (INA / TOTO)',
                    'spec' => 'Kloset jongkok porselen glasur putih anti gores + pipa flush',
                    'unit' => 'Unit',
                    'ref_price' => 350000,
                    'weight' => 0.15,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 4,
                    'day_in_week' => 1,
                    'day_offset' => 23,
                ],
                [
                    'name' => 'Cat Waterproofing Anti Bocor (Aquaproof / No Drop)',
                    'spec' => 'Pelapis waterproofing elastis lantai basah & dinding bawah toilet',
                    'unit' => 'Pail',
                    'ref_price' => 250000,
                    'weight' => 0.07,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 4,
                    'day_in_week' => 2,
                    'day_offset' => 24,
                ],
            ],
            'perpustakaan' => [
                [
                    'name' => 'Semen Portland (PCC 50 Kg) Perpustakaan',
                    'spec' => 'SNI PCC Type 1 Gresik/Dynamix perbaikan dinding & lantai perpus',
                    'unit' => 'Zak',
                    'ref_price' => 68000,
                    'weight' => 0.16,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 1,
                    'day_in_week' => 0,
                    'day_offset' => 1,
                ],
                [
                    'name' => 'Pasir Pasang Kali Sampean Situbondo',
                    'spec' => 'Pasir ayak bersih untuk plesteran dinding & pasangan lantai perpus',
                    'unit' => 'M³',
                    'ref_price' => 260000,
                    'weight' => 0.08,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 1,
                    'day_in_week' => 1,
                    'day_offset' => 2,
                ],
                [
                    'name' => 'Bata Merah Bakar Panji / Bata Ringan Perpus',
                    'spec' => 'Material penyekat dinding ruang baca perpus ukuran standar',
                    'unit' => 'Buah',
                    'ref_price' => 950,
                    'weight' => 0.08,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 1,
                    'day_in_week' => 3,
                    'day_offset' => 4,
                ],
                [
                    'name' => 'Besi Beton SNI Dia. 10mm & Kawat Bendrat',
                    'spec' => 'Besi beton tulangan kolom pengaku dinding & balok latai perpus',
                    'unit' => 'Batang',
                    'ref_price' => 88000,
                    'weight' => 0.08,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 2,
                    'day_in_week' => 1,
                    'day_offset' => 8,
                ],
                [
                    'name' => 'Plafon Kalsiboard 4mm Kedap Akustik + Hollow',
                    'spec' => 'Kalsiboard kedap suara ruang baca + rangka hollow galvanis 2x4 & 4x4',
                    'unit' => 'Lembar',
                    'ref_price' => 85000,
                    'weight' => 0.12,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 3,
                    'day_in_week' => 0,
                    'day_offset' => 15,
                ],
                [
                    'name' => 'Keramik Lantai 40x40 Glazed Heavy Duty',
                    'spec' => 'Keramik tahan gores lantai perpustakaan Platinum 40x40 Grade A',
                    'unit' => 'Dus',
                    'ref_price' => 68000,
                    'weight' => 0.14,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 3,
                    'day_in_week' => 2,
                    'day_offset' => 17,
                ],
                [
                    'name' => 'Kusen Aluminium & Kaca Sirkulasi Cahaya Perpus',
                    'spec' => 'Kusen aluminium brown + kaca bening 5mm pencahayaan alami ruang baca',
                    'unit' => 'Unit',
                    'ref_price' => 980000,
                    'weight' => 0.14,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 3,
                    'day_in_week' => 4,
                    'day_offset' => 19,
                ],
                [
                    'name' => 'Cat Tembok Ramah Anak Rendah Bau & Jamur',
                    'spec' => 'Cat tembok interior low VOC ramah anak & buku (Dulux Easy Clean)',
                    'unit' => 'Pail',
                    'ref_price' => 1450000,
                    'weight' => 0.12,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 4,
                    'day_in_week' => 1,
                    'day_offset' => 23,
                ],
                [
                    'name' => 'Instalasi Listrik, Stop Kontak Meja & LED Perpus',
                    'spec' => 'Stop kontak meja baca, kabel NYM Supreme & lampu Philips LED 18W',
                    'unit' => 'Titik',
                    'ref_price' => 135000,
                    'weight' => 0.08,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 4,
                    'day_in_week' => 3,
                    'day_offset' => 25,
                ],
            ],
            default => [
                [
                    'name' => 'Semen Portland (PCC 50 Kg)',
                    'spec' => 'SNI PCC Type 1 Gresik/Dynamix untuk pasangan bata, spesi & cor beton',
                    'unit' => 'Zak',
                    'ref_price' => 68000,
                    'weight' => 0.18,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 1,
                    'day_in_week' => 0,
                    'day_offset' => 1,
                ],
                [
                    'name' => 'Pasir Pasang & Pasir Cor Beton Kali Sampean',
                    'spec' => 'Pasir kali bersih Situbondo ayak bebas lumpur untuk spesi & beton',
                    'unit' => 'M³',
                    'ref_price' => 260000,
                    'weight' => 0.09,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 1,
                    'day_in_week' => 1,
                    'day_offset' => 2,
                ],
                [
                    'name' => 'Bata Merah Bakar Panji Situbondo',
                    'spec' => 'Bata merah cetak lokal Panji ukuran standar 5x11x22 cm',
                    'unit' => 'Buah',
                    'ref_price' => 950,
                    'weight' => 0.10,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 1,
                    'day_in_week' => 2,
                    'day_offset' => 3,
                ],
                [
                    'name' => 'Besi Beton Polos & Ulir Dia. 10mm & 8mm SNI',
                    'spec' => 'Besi tulangan struktur SNI panjang 12m + kawat bendrat pengikat',
                    'unit' => 'Batang',
                    'ref_price' => 88000,
                    'weight' => 0.12,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 1,
                    'day_in_week' => 3,
                    'day_offset' => 4,
                ],
                [
                    'name' => 'Pengadaan Bahan Atap (Rangka Baja Ringan Galvalum & Genteng/Spandek)',
                    'spec' => 'Rangka kuda-kuda baja ringan galvalum C75.75, reng 0.45, genteng pres/spandek SNI, nok bubung & sekrup roofing (1 paket pembelian lengkap)',
                    'unit' => 'Paket',
                    'ref_price' => 220000,
                    'weight' => 0.23,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 2,
                    'day_in_week' => 0,
                    'day_offset' => 8,
                ],
                [
                    'name' => 'Plafon Kalsiboard 4mm & Rangka Hollow Galvanis',
                    'spec' => 'Kalsiboard Linggi 4mm + Rangka Hollow 2x4 & 4x4 anti rayap',
                    'unit' => 'Lembar',
                    'ref_price' => 85000,
                    'weight' => 0.08,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 3,
                    'day_in_week' => 0,
                    'day_offset' => 15,
                ],
                [
                    'name' => 'Keramik Lantai 40x40 Putih Corak & Plin Lantai',
                    'spec' => 'Keramik Glazed Platinum/Asia Tile 40x40 Grade A standar ruang belajar',
                    'unit' => 'Dus',
                    'ref_price' => 65000,
                    'weight' => 0.07,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 3,
                    'day_in_week' => 1,
                    'day_offset' => 16,
                ],
                [
                    'name' => 'Kusen, Daun Pintu & Jendela Kaca 5mm',
                    'spec' => 'Kusen aluminium/kayu + daun jendela kaca 5mm, engsel & kunci',
                    'unit' => 'Unit',
                    'ref_price' => 950000,
                    'weight' => 0.07,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 3,
                    'day_in_week' => 3,
                    'day_offset' => 18,
                ],
                [
                    'name' => 'Cat Tembok Dinding Dalam/Luar & Plafon',
                    'spec' => 'Catylac / Dulux Weathershield + plamir tembok & roll kuas cat',
                    'unit' => 'Pail',
                    'ref_price' => 1450000,
                    'weight' => 0.04,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 4,
                    'day_in_week' => 1,
                    'day_offset' => 23,
                ],
                [
                    'name' => 'Instalasi Kelistrikan & Lampu LED Ruang Kelas',
                    'spec' => 'Kabel NYM 2x1.5 Supreme, Saklar Broco & Lampu Philips LED 18W',
                    'unit' => 'Titik',
                    'ref_price' => 125000,
                    'weight' => 0.02,
                    'account_code' => '5.1.02.01',
                    'supplier' => $defaultStore,
                    'week' => 4,
                    'day_in_week' => 3,
                    'day_offset' => 25,
                ],
            ],
        };
    }

    /**
     * Menghitung tanggal transaksi BKU yang sangat realistis:
     * - Bahan material dijadwalkan pada hari kerja (Senin - Jumat) sesuai tahapan fisik konstruksi.
     * - Upah tenaga kerja / HOK tukang dijadwalkan pada hari Sabtu (akhir pekan berjalan).
     * - Menghindari hari Minggu (hari libur toko bangunan, perbankan, dan swakelola).
     */
    public function calculateRealisticItemDate(string $baseDate, int $week = 1, int $dayInWeek = 0, string $category = 'bahan'): string
    {
        try {
            $base = Carbon::parse($baseDate);
        } catch (\Throwable $e) {
            $base = now();
        }

        if ($base->isSunday()) {
            $base->addDay();
        }

        // Cari hari Sabtu pertama pada atau setelah tanggal dasar
        $firstSaturday = $base->isSaturday() ? $base->copy() : $base->copy()->next(Carbon::SATURDAY);

        // Jika category adalah 'upah', jadwalkan pada hari Sabtu akhir pekan yang bersangkutan (Minggu 1, 2, 3, 4)
        if ($category === 'upah') {
            $wageSaturday = $firstSaturday->copy()->addWeeks(max(0, $week - 1));
            return $wageSaturday->format('Y-m-d');
        }

        // Untuk bahan material:
        // Hitung hari Senin pada minggu ke-$week (5 hari sebelum hari Sabtu)
        $weekMonday = $firstSaturday->copy()->addWeeks(max(0, $week - 1))->subDays(5);

        // Jika di minggu ke-1 tanggal dasar berada setelah hari Senin, jangan mundur sebelum $base
        if ($week === 1 && $weekMonday->lt($base)) {
            $weekMonday = $base->copy();
        }

        // Tentukan hari dalam minggu (0 = Senin, 1 = Selasa, 2 = Rabu, 3 = Kamis, 4 = Jumat)
        $targetDate = $weekMonday->copy()->addDays(max(0, min(4, $dayInWeek)));

        // Pastikan tidak jatuh di hari Minggu
        if ($targetDate->isSunday()) {
            $targetDate->addDay();
        }

        return $targetDate->format('Y-m-d');
    }

    /**
     * Menghitung tanggal berjarak progresif untuk item ringkasan (summary) atau tabel umum
     * berdasarkan tanggal dasar, dengan jarak 2-4 hari kerja tanpa jatuh di hari Minggu.
     */
    public function calculateProgressiveDate(string $baseDate, int $step = 1, string $category = 'bahan'): string
    {
        try {
            $base = Carbon::parse($baseDate);
        } catch (\Throwable $e) {
            $base = now();
        }

        if ($base->isSunday()) {
            $base->addDay();
        }

        // Hitung jarak hari kerja berdasar urutan step
        $daysToAdd = max(0, ($step - 1) * 3);
        $target = $base->copy()->addDays($daysToAdd);

        if ($target->isSunday()) {
            if ($category === 'upah') {
                $target->subDay(); // Geser ke Sabtu
            } else {
                $target->addDay(); // Geser ke Senin
            }
        }

        return $target->format('Y-m-d');
    }

    /**
     * Parse tanggal dari format string atau kembalikan default.
     */
    protected function parseDateOrDefault(?string $dateStr, string $defaultDate): string
    {
        if (empty($dateStr)) return $defaultDate;

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                return $dateStr;
            }

            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateStr, $m)) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }

            $carbon = Carbon::parse($dateStr);
            return $carbon->format('Y-m-d');
        } catch (\Throwable $e) {
            return $defaultDate;
        }
    }

    /**
     * Pencatatan Otomatis Transaksi Saldo Masuk (Penerimaan Dana SP2D) dan Penarikan Tunai Kas.
     * Menganggap tanggal inputan awal (planned_date paling awal) sebagai tanggal pencairan dana.
     */
    public function recordDisbursementTransactions(RpdDocument $document, ?int &$counter = null): array
    {
        $project = $document->project;
        $school = $document->school;

        // Tanggal pencairan: tanggal inputan awal (planned_date paling awal)
        $disbursementDate = $document->items()->min('planned_date') ?: now()->format('Y-m-d');
        $totalBudget = (float) ($document->total_budget > 0 ? $document->total_budget : $document->items()->sum('total_price'));
        $totalCashExpenses = (float) $document->items()->where('payment_method', 'belanja_tunai')->sum('total_price');

        $year = $project->fiscal_year ?? Carbon::parse($disbursementDate)->format('Y');
        $month = Carbon::parse($disbursementDate)->format('m');
        $createdTransactions = [];

        if ($counter === null) {
            $lastTrx = Transaction::where('school_id', $school->id)
                ->whereYear('transaction_date', $year)
                ->orderBy('id', 'desc')
                ->first();
            $counter = 1;
            if ($lastTrx && preg_match('/(\d+)$/', $lastTrx->transaction_number, $matches)) {
                $counter = ((int)$matches[1]) + 1;
            }
        }

        // 1. Saldo Masuk: Penerimaan Dana (SP2D Termin Masuk ke Rekening Bank)
        $existingReceipt = Transaction::where('project_id', $project->id)
            ->where('type', 'penerimaan_dana')
            ->where(function ($q) use ($document) {
                $q->where('notes', 'like', "%RPD #{$document->id}%")
                  ->orWhere('notes', 'like', "%RPD: {$document->title}%");
            })
            ->first();

        $fundingSource = !empty($project->funding_source) ? trim($project->funding_source) : 'Kas Daerah';
        $projectTitle = trim($project->title);

        if ($existingReceipt) {
            // Jika total anggaran bertambah karena ada item baru, sinkronkan nominal SP2D masuk
            if ($totalBudget > $existingReceipt->amount) {
                $existingReceipt->update(['amount' => $totalBudget]);
            }
        } elseif ($totalBudget > 0) {
            do {
                $sp2dNumber = sprintf('SP2D-REVITA/%s/%s/%04d', $year, $month, $counter++);
            } while (Transaction::where('transaction_number', $sp2dNumber)->exists());

            $recipientName = str_contains(strtolower($fundingSource), 'kas daerah') ? $fundingSource : "Kas Daerah ({$fundingSource})";
            $receipt = Transaction::create([
                'school_id' => $school->id,
                'project_id' => $project->id,
                'budget_item_id' => null,
                'transaction_number' => $sp2dNumber,
                'transaction_date' => $disbursementDate,
                'type' => 'penerimaan_dana',
                'payment_method' => 'bank_transfer',
                'description' => "Penerimaan Pencairan Dana {$document->term_stage} {$projectTitle} (Sumber Dana: {$fundingSource})",
                'recipient_name' => $recipientName,
                'recipient_address' => 'Pemerintah Kabupaten Situbondo',
                'amount' => $totalBudget,
                'has_tax' => false,
                'tax_status' => 'none',
                'notes' => "Pencatatan Otomatis Saldo Masuk Pencairan RPD #{$document->id}: {$document->title}",
                'created_by' => auth()->id() ?? 1,
            ]);
            $createdTransactions[] = $receipt;
        }

        // 2. Penarikan Kas Tunai dari Bank (Untuk Belanja Tunai & Upah agar BPK tidak minus)
        $existingWithdrawal = Transaction::where('project_id', $project->id)
            ->where('type', 'tarik_tunai')
            ->where(function ($q) use ($document) {
                $q->where('notes', 'like', "%RPD #{$document->id}%")
                  ->orWhere('notes', 'like', "%RPD: {$document->title}%");
            })
            ->first();

        if ($existingWithdrawal) {
            // Jika total belanja tunai bertambah, sinkronkan nominal penarikan tunai
            if ($totalCashExpenses > $existingWithdrawal->amount) {
                $existingWithdrawal->update(['amount' => $totalCashExpenses]);
            }
        } elseif ($totalCashExpenses > 0) {
            do {
                $trkNumber = sprintf('TRK-REVITA/%s/%s/%04d', $year, $month, $counter++);
            } while (Transaction::where('transaction_number', $trkNumber)->exists());
            $withdrawal = Transaction::create([
                'school_id' => $school->id,
                'project_id' => $project->id,
                'budget_item_id' => null,
                'transaction_number' => $trkNumber,
                'transaction_date' => $disbursementDate,
                'type' => 'tarik_tunai',
                'payment_method' => 'kas_tunai',
                'description' => "Penarikan Dana Tunai dari Rekening Bank Operasional ke Kas Tunai Brankas untuk Belanja Bahan & Upah ({$document->term_stage})",
                'recipient_name' => 'Bendahara Swakelola Sekolah',
                'recipient_address' => 'Kabupaten Situbondo',
                'amount' => $totalCashExpenses,
                'has_tax' => false,
                'tax_status' => 'none',
                'notes' => "Pencatatan Otomatis Penarikan Tunai Kas Operasional RPD #{$document->id}: {$document->title}",
                'created_by' => auth()->id() ?? 1,
            ]);
            $createdTransactions[] = $withdrawal;
        }

        return $createdTransactions;
    }

    /**
     * Posting item-item pecah bahan RPD ke Tabel Transaksi BKU resmi.
     * Otomatis menyertakan pencatatan Saldo Masuk (SP2D Termin) dan Penarikan Tunai Kas.
     */
    public function postToBku(RpdDocument $document): int
    {
        $items = $document->items()->where('is_posted', false)->get();
        if ($items->isEmpty()) {
            $created = $this->recordDisbursementTransactions($document);
            return count($created);
        }

        $project = $document->project;
        $school = $document->school;
        $year = $project->fiscal_year ?? date('Y');
        $postedCount = 0;

        DB::transaction(function () use ($items, $document, $project, $school, $year, &$postedCount) {
            $lastTrx = Transaction::where('school_id', $school->id)
                ->whereYear('transaction_date', $year)
                ->orderBy('id', 'desc')
                ->first();

            $counter = 1;
            if ($lastTrx && preg_match('/(\d+)$/', $lastTrx->transaction_number, $matches)) {
                $counter = ((int)$matches[1]) + 1;
            }

            // 1. Pencatatan Otomatis Saldo Masuk (Pencairan Dana) & Tarik Tunai Kas
            $disbursementTxs = $this->recordDisbursementTransactions($document, $counter);
            $postedCount += count($disbursementTxs);

            // 2. Pencatatan Transaksi Belanja Bahan & Upah
            $geminiService = app(GeminiRpdService::class);

            foreach ($items as $item) {
                $month = Carbon::parse($item->planned_date)->format('m');
                do {
                    $kwtNumber = sprintf('KWT-REVITA/%s/%s/%04d', $year, $month, $counter++);
                } while (Transaction::where('transaction_number', $kwtNumber)->exists());

                $hasTax = $item->has_tax && $item->tax_total > 0;
                $taxStatus = $hasTax ? 'disetor' : 'none';
                $taxNtpn = $hasTax ? $geminiService->generateNtpn() : null;
                $taxPaymentDate = $hasTax ? $geminiService->calculateTaxPaymentDate($item->planned_date, $item->category) : null;

                $transaction = Transaction::create([
                    'school_id' => $school->id,
                    'project_id' => $project->id,
                    'budget_item_id' => $item->budget_item_id,
                    'transaction_number' => $kwtNumber,
                    'transaction_date' => $item->planned_date,
                    'type' => $item->payment_method,
                    'payment_method' => $item->payment_method === 'belanja_transfer' ? 'bank_transfer' : 'kas_tunai',
                    'description' => $item->bku_description,
                    'recipient_name' => $item->supplier_name,
                    'recipient_address' => 'Kabupaten Situbondo',
                    'amount' => $item->total_price,
                    'has_tax' => $item->has_tax,
                    'tax_type' => $item->has_tax ? ($item->tax_pph22 > 0 ? 'pph22_ppn' : ($item->tax_pph21 > 0 ? 'pph21' : 'ppn')) : null,
                    'tax_ppn' => $item->tax_ppn,
                    'tax_pph21' => $item->tax_pph21,
                    'tax_pph22' => $item->tax_pph22,
                    'tax_pph23' => 0,
                    'tax_pph4_2' => 0,
                    'tax_total' => $item->tax_total,
                    'net_amount' => $item->net_amount,
                    'tax_ntpn' => $taxNtpn,
                    'tax_payment_date' => $taxPaymentDate,
                    'tax_status' => $taxStatus,
                    'notes' => "Hasil Pecah Bahan RPD: {$document->title} (Item #{$item->row_number})",
                    'created_by' => auth()->id() ?? 1,
                ]);

                $item->update([
                    'is_posted' => true,
                    'transaction_id' => $transaction->id,
                    'bku_number' => $transaction->transaction_number,
                ]);

                $postedCount++;
            }

            $document->update([
                'status' => 'posted_to_bku',
                'posted_at' => now(),
            ]);
        });

        return $postedCount;
    }

    /**
     * Membatalkan posting BKU dari suatu Dokumen RPD.
     * Menghapus seluruh transaksi BKU (belanja bahan/upah, saldo masuk pencairan termin, dan tarik tunai kas)
     * yang tercipta dari dokumen ini, serta mengembalikan status dokumen menjadi 'analyzed'.
     */
    public function unpostFromBku(RpdDocument $document): int
    {
        return DB::transaction(function () use ($document) {
            $deletedCount = 0;

            // 1. Ambil ID transaksi belanja dari item-item dokumen RPD
            $itemTrxIds = $document->items()
                ->whereNotNull('transaction_id')
                ->pluck('transaction_id')
                ->filter()
                ->toArray();

            // 2. Ambil transaksi otomatis (saldo masuk pencairan & tarik tunai) yang terkait dokumen RPD ini
            $autoTrxIds = Transaction::where('project_id', $document->project_id)
                ->where(function ($q) use ($document) {
                    $q->where('notes', 'like', "%RPD #{$document->id}%")
                      ->orWhere('notes', 'like', "%RPD: {$document->title}%")
                      ->orWhere('notes', 'like', "%Hasil Pecah Bahan RPD: {$document->title}%");
                })
                ->pluck('id')
                ->toArray();

            $allTrxIds = array_unique(array_merge($itemTrxIds, $autoTrxIds));

            if (!empty($allTrxIds)) {
                $transactions = Transaction::whereIn('id', $allTrxIds)->get();
                foreach ($transactions as $trx) {
                    if ($trx->receipt_file && Storage::disk('public')->exists($trx->receipt_file)) {
                        Storage::disk('public')->delete($trx->receipt_file);
                    }
                    $trx->delete();
                    $deletedCount++;
                }
            }

            // 3. Reset status pada seluruh item dokumen RPD
            $document->items()->update([
                'is_posted' => false,
                'transaction_id' => null,
                'bku_number' => null,
            ]);

            // 4. Reset status dokumen RPD
            $document->update([
                'status' => 'analyzed',
                'posted_at' => null,
            ]);

            return $deletedCount;
        });
    }

    /**
     * Menghapus Dokumen RPD secara tuntas beserta seluruh transaksi BKU-nya jika sudah diposting.
     */
    public function deleteDocumentWithTransactions(RpdDocument $document): void
    {
        DB::transaction(function () use ($document) {
            // 1. Batalkan posting dan hapus transaksi BKU terkait (jika pernah diposting)
            $this->unpostFromBku($document);

            // 2. Hapus berkas file RPD yang diunggah jika ada
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            // 3. Hapus seluruh item pecahan dan dokumen induk
            $document->items()->delete();
            $document->delete();
        });
    }

    /**
     * Membuat file Template Excel Resmi RPD Revitalisasi (sesuai format referensi Dinas).
     */
    public function generateOfficialTemplateSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RPD Revitalisasi');

        // Kop Laporan
        $sheet->setCellValue('B2', 'RENCANA PENGGUNAAN DANA (RPD)');
        $sheet->setCellValue('B3', 'PROGRAM REVITALISASI SATUAN PENDIDIKAN SEKOLAH DASAR');
        $sheet->setCellValue('B4', 'KAB/KOTA SITUBONDO PROPINSI JAWA TIMUR');
        $sheet->mergeCells('B2:H2');
        $sheet->mergeCells('B3:H3');
        $sheet->mergeCells('B4:H4');
        $sheet->getStyle('B2:B4')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('B2:B4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Identitas Sekolah
        $sheet->setCellValue('B6', 'Sekolah Dasar');
        $sheet->setCellValue('C6', ': SD NEGERI 3 SUMBERARGO');
        $sheet->setCellValue('B7', 'NPSN');
        $sheet->setCellValue('C7', ': 20522538');
        $sheet->setCellValue('B8', 'Kab/Kota');
        $sheet->setCellValue('C8', ': SITUBONDO');
        $sheet->setCellValue('B9', 'Provinsi');
        $sheet->setCellValue('C9', ': JAWA TIMUR');
        $sheet->getStyle('B6:B9')->getFont()->setBold(true);

        // Header Tabel RPD
        $sheet->setCellValue('A11', 'NO');
        $sheet->setCellValue('B11', 'URAIAN');
        $sheet->setCellValue('C11', 'VOL');
        $sheet->setCellValue('D11', 'SAT');
        $sheet->setCellValue('E11', "HARGA\nSATUAN (Rp.)");
        $sheet->setCellValue('F11', 'JUMLAH ANGGARAN (Rp.)');
        $sheet->setCellValue('I11', 'KETERANGAN');

        $sheet->setCellValue('F12', 'Termin 1');
        $sheet->setCellValue('G12', 'Termin 2');
        $sheet->setCellValue('H12', 'JUMLAH');

        $sheet->setCellValue('F13', '70%');
        $sheet->setCellValue('G13', '30%');
        $sheet->setCellValue('H13', '100%');

        $sheet->mergeCells('A11:A13');
        $sheet->mergeCells('B11:B13');
        $sheet->mergeCells('C11:C13');
        $sheet->mergeCells('D11:D13');
        $sheet->mergeCells('E11:E13');
        $sheet->mergeCells('F11:H11');
        $sheet->mergeCells('I11:I13');

        $sheet->getStyle('A11:I13')->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle('A11:I13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A11:I13')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');

        $rows = [
            ['I', 'PEKERJAAN FISIK', null, null, null, null, null, null, null],
            ['A', 'Rehabilitasi', null, null, null, null, null, null, null],
            [null, '- Ruang Kelas', 172.1, 'm²', 2005889, null, null, null, null],
            [null, '  - Belanja Bahan', null, null, 231000914, 161700640, 69300274, 231000914, null],
            [null, '  - Belanja Upah', null, null, 94352486, 66046740, 28305746, 94352486, null],
            [null, '- Perpustakaan', 54.6, 'm²', 2666667, null, null, null, null],
            [null, '  - Belanja Bahan', null, null, 71903090, 50332163, 21570927, 71903090, null],
            [null, '  - Belanja Upah', null, null, 30815610, 21570927, 9244683, 30815610, null],
            [null, '- Toilet - 1', 7.7, 'm²', null, null, null, null, null],
            [null, '  - Belanja Bahan', null, null, 13771940, 9640358, 4131582, 13771940, null],
            [null, '  - Belanja Upah', null, null, 5902260, 4131582, 1770678, 5902260, null],
            [null, 'Sub Total IB', null, null, null, 299650470, 128421630, 447746300, null],
            ['II', 'PEKERJAAN INTERIOR DAN PERABOT', null, null, null, null, null, null, null],
            [1, '-', null, 'Ls', null, null, null, null, null],
            [null, 'Sub Total II', null, null, null, null, null, null, null],
            ['III', 'PERENCANAAN DAN PENGAWASAN', null, null, null, null, null, null, null],
            ['A', 'Biaya Perencanaan', 1.0, 'Ls', null, null, null, null, null],
            ['B', 'Biaya Pengawasan', 1.0, 'Ls', null, null, null, null, null],
            ['C', 'Biaya Pengelolaan', 1.0, 'Ls', null, null, null, null, null],
            [null, 'Sub Total III', null, null, null, null, null, null, null],
            [null, 'TOTAL (I + II + III )', null, null, null, 299650470, 128421630, 447746300, null],
        ];

        $r = 14;
        foreach ($rows as $row) {
            $sheet->setCellValue('A' . $r, $row[0]);
            $sheet->setCellValue('B' . $r, $row[1]);
            $sheet->setCellValue('C' . $r, $row[2]);
            $sheet->setCellValue('D' . $r, $row[3]);
            $sheet->setCellValue('E' . $r, $row[4]);
            $sheet->setCellValue('F' . $r, $row[5]);
            $sheet->setCellValue('G' . $r, $row[6]);
            $sheet->setCellValue('H' . $r, $row[7]);
            $sheet->setCellValue('I' . $r, $row[8]);

            $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $r . ':H' . $r)->getNumberFormat()->setFormatCode('#,##0');

            if (in_array($row[0], ['I', 'II', 'III']) || in_array($row[1], ['Rehabilitasi', 'Sub Total IB', 'Sub Total II', 'Sub Total III', 'TOTAL (I + II + III )'])) {
                $sheet->getStyle("A{$r}:I{$r}")->getFont()->setBold(true);
            }

            if (in_array($row[1], ['Sub Total IB', 'Sub Total II', 'Sub Total III', 'TOTAL (I + II + III )'])) {
                $sheet->getStyle("A{$r}:I{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
            }

            $r++;
        }

        $sheet->getStyle("A11:I" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF94A3B8');

        // Tanda Tangan
        $sheet->setCellValue('G' . ($r + 2), 'Situbondo, 13 Juli 2026');
        $sheet->setCellValue('G' . ($r + 6), 'RISA ANDRIYANA SUHARTO');
        $sheet->setCellValue('G' . ($r + 7), 'Perencana');
        $sheet->getStyle('G' . ($r + 6))->getFont()->setBold(true);
        $sheet->getStyle('G' . ($r + 2) . ':G' . ($r + 7))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (['A' => 6, 'B' => 32, 'C' => 10, 'D' => 8, 'E' => 18, 'F' => 16, 'G' => 16, 'H' => 18, 'I' => 15] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        return $spreadsheet;
    }

    /**
     * Membuat Template PDF Resmi RPD Revitalisasi menggunakan Dompdf.
     */
    public function generateOfficialTemplatePdf(): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Helvetica, Arial, sans-serif; font-size: 8px; margin: 15px; }
  .title { text-align: center; font-weight: bold; font-size: 11px; margin-bottom: 2px; }
  .sub-title { text-align: center; font-weight: bold; font-size: 10px; margin-bottom: 12px; }
  .meta-table { width: 100%; margin-bottom: 10px; font-size: 8.5px; }
  .meta-table td { padding: 1.5px; }
  table.rpd { width: 100%; border-collapse: collapse; font-size: 8px; }
  table.rpd th, table.rpd td { border: 0.5px solid #333; padding: 3px; }
  table.rpd th { background-color: #f1f5f9; text-align: center; font-weight: bold; }
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .bold { font-weight: bold; }
</style>
</head>
<body>
  <div class="title">RENCANA PENGGUNAAN DANA (RPD)</div>
  <div class="sub-title">PROGRAM REVITALISASI SATUAN PENDIDIKAN SEKOLAH DASAR<br>KAB/KOTA SITUBONDO PROPINSI JAWA TIMUR</div>
  
  <table class="meta-table">
    <tr><td style="width: 100px;">Sekolah Dasar</td><td>: SD NEGERI 3 SUMBERARGO</td></tr>
    <tr><td>NPSN</td><td>: 20522538</td></tr>
    <tr><td>Kab/Kota</td><td>: SITUBONDO</td></tr>
    <tr><td>Provinsi</td><td>: JAWA TIMUR</td></tr>
  </table>

  <table class="rpd">
    <thead>
      <tr>
        <th rowspan="3" style="width: 20px;">NO</th>
        <th rowspan="3" style="width: 160px;">URAIAN</th>
        <th rowspan="3" style="width: 35px;">VOL</th>
        <th rowspan="3" style="width: 25px;">SAT</th>
        <th rowspan="3" style="width: 65px;">HARGA SATUAN (Rp.)</th>
        <th colspan="3">JUMLAH ANGGARAN (Rp.)</th>
        <th rowspan="3" style="width: 60px;">KETERANGAN</th>
      </tr>
      <tr>
        <th style="width: 65px;">Termin 1</th>
        <th style="width: 65px;">Termin 2</th>
        <th style="width: 70px;">JUMLAH</th>
      </tr>
      <tr>
        <th>70%</th>
        <th>30%</th>
        <th>100%</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="bold">I</td>
        <td class="bold" colspan="8">PEKERJAAN FISIK</td>
      </tr>
      <tr>
        <td class="bold">A</td>
        <td class="bold" colspan="8">Rehabilitasi</td>
      </tr>
      <tr>
        <td></td>
        <td>- Ruang Kelas</td>
        <td class="text-right">172.1</td>
        <td class="text-center">m²</td>
        <td class="text-right">2,005,889</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
      </tr>
      <tr>
        <td></td>
        <td>&nbsp;&nbsp;&nbsp;- Belanja Bahan</td>
        <td></td>
        <td></td>
        <td class="text-right">231,000,914</td>
        <td class="text-right">161,700,640</td>
        <td class="text-right">69,300,274</td>
        <td class="text-right">231,000,914</td>
        <td></td>
      </tr>
      <tr>
        <td></td>
        <td>&nbsp;&nbsp;&nbsp;- Belanja Upah</td>
        <td></td>
        <td></td>
        <td class="text-right">94,352,486</td>
        <td class="text-right">66,046,740</td>
        <td class="text-right">28,305,746</td>
        <td class="text-right">94,352,486</td>
        <td></td>
      </tr>
      <tr>
        <td></td>
        <td>- Perpustakaan</td>
        <td class="text-right">54.6</td>
        <td class="text-center">m²</td>
        <td class="text-right">2,666,667</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
      </tr>
      <tr>
        <td></td>
        <td>&nbsp;&nbsp;&nbsp;- Belanja Bahan</td>
        <td></td>
        <td></td>
        <td class="text-right">71,903,090</td>
        <td class="text-right">50,332,163</td>
        <td class="text-right">21,570,927</td>
        <td class="text-right">71,903,090</td>
        <td></td>
      </tr>
      <tr>
        <td></td>
        <td>&nbsp;&nbsp;&nbsp;- Belanja Upah</td>
        <td></td>
        <td></td>
        <td class="text-right">30,815,610</td>
        <td class="text-right">21,570,927</td>
        <td class="text-right">9,244,683</td>
        <td class="text-right">30,815,610</td>
        <td></td>
      </tr>
      <tr>
        <td></td>
        <td>- Toilet - 1</td>
        <td class="text-right">7.7</td>
        <td class="text-center">m²</td>
        <td class="text-center">-</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
      </tr>
      <tr>
        <td></td>
        <td>&nbsp;&nbsp;&nbsp;- Belanja Bahan</td>
        <td></td>
        <td></td>
        <td class="text-right">13,771,940</td>
        <td class="text-right">9,640,358</td>
        <td class="text-right">4,131,582</td>
        <td class="text-right">13,771,940</td>
        <td></td>
      </tr>
      <tr>
        <td></td>
        <td>&nbsp;&nbsp;&nbsp;- Belanja Upah</td>
        <td></td>
        <td></td>
        <td class="text-right">5,902,260</td>
        <td class="text-right">4,131,582</td>
        <td class="text-right">1,770,678</td>
        <td class="text-right">5,902,260</td>
        <td></td>
      </tr>
      <tr class="bold">
        <td colspan="5" class="text-center">Sub Total IB</td>
        <td class="text-right">299,650,470</td>
        <td class="text-right">128,421,630</td>
        <td class="text-right">447,746,300</td>
        <td></td>
      </tr>
      <tr>
        <td class="bold">II</td>
        <td class="bold" colspan="8">PEKERJAAN INTERIOR DAN PERABOT</td>
      </tr>
      <tr>
        <td>1</td>
        <td>-</td>
        <td class="text-center">-</td>
        <td class="text-center">Ls</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td></td>
      </tr>
      <tr class="bold">
        <td colspan="5" class="text-center">Sub Total II</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td></td>
      </tr>
      <tr>
        <td class="bold">III</td>
        <td class="bold" colspan="8">PERENCANAAN DAN PENGAWASAN</td>
      </tr>
      <tr>
        <td>A</td>
        <td>Biaya Perencanaan</td>
        <td class="text-right">1.0</td>
        <td class="text-center">Ls</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td></td>
      </tr>
      <tr>
        <td>B</td>
        <td>Biaya Pengawasan</td>
        <td class="text-right">1.0</td>
        <td class="text-center">Ls</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td></td>
      </tr>
      <tr>
        <td>C</td>
        <td>Biaya Pengelolaan</td>
        <td class="text-right">1.0</td>
        <td class="text-center">Ls</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td></td>
      </tr>
      <tr class="bold">
        <td colspan="5" class="text-center">Sub Total III</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td class="text-center">-</td>
        <td></td>
      </tr>
      <tr class="bold">
        <td colspan="5" class="text-center">TOTAL ( I + II + III )</td>
        <td class="text-right">299,650,470</td>
        <td class="text-right">128,421,630</td>
        <td class="text-right">447,746,300</td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <div style="margin-top: 25px; float: right; text-align: center; width: 220px; font-size: 8.5px;">
    Situbondo, 13 Juli 2026<br><br><br><br>
    <strong>RISA ANDRIYANA SUHARTO</strong><br>
    Perencana
  </div>
</body>
</html>';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Membuat Template Excel RPD Format Rincian Item Material & HOK Upah.
     */
    public function generateItemizedTemplateSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Rincian Bahan');

        $sheet->setCellValue('A1', 'RENCANA PENARIKAN DANA (RPD) - RINCIAN BAHAN & UPAH');
        $sheet->setCellValue('A2', 'KABUPATEN SITUBONDO - TAHUN ANGGARAN 2026');
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);

        $headers = [
            'A4' => 'No',
            'B4' => 'Kode Rekening',
            'C4' => 'Kategori (Bahan/Upah/Alat/Operasional)',
            'D4' => 'Uraian Barang / Bahan / Upah',
            'E4' => 'Spesifikasi / Merk',
            'F4' => 'Volume',
            'G4' => 'Satuan',
            'H4' => 'Harga Satuan (Rp)',
            'I4' => 'Total Biaya (Rp)',
            'J4' => 'Tanggal Rencana Belanja (YYYY-MM-DD)',
            'K4' => 'Toko / Rekanan Pemasok',
            'L4' => 'Metode Bayar (Kas Tunai / Transfer Bank)',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('A4:L4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $sheet->getStyle('A4:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF047857');
        $sheet->getStyle('A4:L4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(4)->setRowHeight(28);

        $sampleData = [
            [1, '5.1.02.01', 'Bahan', 'Semen Gresik 50 Kg', 'SNI PCC Type 1', 100, 'Zak', 68000, 6800000, '2026-03-05', 'Toko Bangunan Berkah Situbondo', 'Transfer Bank'],
            [2, '5.1.02.01', 'Bahan', 'Pasir Pasang Kali Sampean', 'Kualitas Kasar Bersih', 8, 'M3', 250000, 2000000, '2026-03-06', 'Pemasok Pasir Kali Situbondo', 'Kas Tunai'],
            [3, '5.1.02.01', 'Bahan', 'Bata Merah Bakar Panji', 'Standar 5x11x22 cm', 5000, 'Bh', 950, 4750000, '2026-03-08', 'Pengrajin Bata Panji Situbondo', 'Kas Tunai'],
            [4, '5.1.02.01', 'Bahan', 'Besi Beton Polos Dia. 10 mm', 'SNI Panjang 12 Meter', 50, 'Batang', 85000, 4250000, '2026-03-10', 'Toko Besi Abadi Situbondo', 'Kas Tunai'],
            [5, '5.1.02.01', 'Bahan', 'Cat Tembok Eksterior Dulux Weathershield', 'Warna Putih Salju 20L', 4, 'Pail', 1450000, 5800000, '2026-03-15', 'Toko Cat Citra Warna Situbondo', 'Transfer Bank'],
            [6, '5.1.02.02', 'Upah', 'Upah Tukang Batu & Plesteran (Minggu I)', 'HOK Tukang', 24, 'OH', 125000, 3000000, '2026-03-12', 'Kelompok Tukang Pak Slamet', 'Kas Tunai'],
            [7, '5.1.02.02', 'Upah', 'Upah Pekerja / Pembantu Tukang (Minggu I)', 'HOK Tenaga Lokal', 36, 'OH', 90000, 3240000, '2026-03-12', 'Kelompok Pekerja Swakelola', 'Kas Tunai'],
            [8, '5.1.02.03', 'Alat', 'Sewa Mesin Molen Pengaduk Semen', 'Kapasitas 350L per Hari', 5, 'Hari', 180000, 900000, '2026-03-07', 'Sewa Alat Proyek Jaya', 'Kas Tunai'],
            [9, '5.1.02.04', 'Operasional', 'Belanja ATK dan Penggandaan Laporan SPJ', 'Kertas A4 & Jilid', 1, 'Paket', 650000, 650000, '2026-03-20', 'Toko ATK Grafika Situbondo', 'Kas Tunai'],
        ];

        $rowIdx = 5;
        foreach ($sampleData as $item) {
            $sheet->setCellValue('A' . $rowIdx, $item[0]);
            $sheet->setCellValue('B' . $rowIdx, $item[1]);
            $sheet->setCellValue('C' . $rowIdx, $item[2]);
            $sheet->setCellValue('D' . $rowIdx, $item[3]);
            $sheet->setCellValue('E' . $rowIdx, $item[4]);
            $sheet->setCellValue('F' . $rowIdx, $item[5]);
            $sheet->setCellValue('G' . $rowIdx, $item[6]);
            $sheet->setCellValue('H' . $rowIdx, $item[7]);
            $sheet->setCellValue('I' . $rowIdx, "=F{$rowIdx}*H{$rowIdx}");
            $sheet->setCellValue('J' . $rowIdx, $item[9]);
            $sheet->setCellValue('K' . $rowIdx, $item[10]);
            $sheet->setCellValue('L' . $rowIdx, $item[11]);

            $sheet->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('G' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('I' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('J' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowIdx++;
        }

        $sheet->setCellValue('A' . $rowIdx, 'TOTAL RPD RINCIAN');
        $sheet->mergeCells("A{$rowIdx}:H{$rowIdx}");
        $sheet->setCellValue('I' . $rowIdx, "=SUM(I5:I" . ($rowIdx - 1) . ")");
        $sheet->getStyle("A{$rowIdx}:L{$rowIdx}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowIdx}:H{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('I' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A{$rowIdx}:L{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');

        $sheet->getStyle("A4:L{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
