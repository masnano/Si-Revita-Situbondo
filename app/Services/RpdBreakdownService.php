<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\RevitalizationProject;
use App\Models\RpdDocument;
use App\Models\RpdItem;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RpdBreakdownService
{
    /**
     * Memproses unggahan file RPD dan melakukan analisis "Pecah Bahan" untuk BKU.
     */
    public function processUpload(UploadedFile $file, RevitalizationProject $project, array $options = []): RpdDocument
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = $file->getClientOriginalName();
        $termStage = $options['term_stage'] ?? 'Tahap 1';
        $title = $options['title'] ?? 'RPD ' . $termStage . ' - ' . $project->title;

        // Simpan file fisik
        $storedPath = $file->store('rpd_files', 'public');

        // Parse baris-baris data dari file
        $rawRows = $this->extractRowsFromFile($file->getRealPath(), $extension);

        if (empty($rawRows)) {
            throw new \Exception('File RPD tidak memiliki data baris yang valid atau format tabel tidak dikenali.');
        }

        return DB::transaction(function () use ($project, $title, $termStage, $storedPath, $originalName, $extension, $rawRows, $options) {
            // Buat dokumen RPD induk
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

            $totalMaterials = 0;
            $totalWages = 0;
            $totalEquipment = 0;
            $totalOperational = 0;
            $totalBudget = 0;
            $totalTaxEstimated = 0;
            $totalNetEstimated = 0;
            $itemCount = 0;

            $existingRabItems = BudgetItem::where('project_id', $project->id)->get();
            $defaultDate = $options['default_date'] ?? now()->format('Y-m-d');
            $defaultStore = $options['default_store'] ?? 'Toko Bangunan Berkah Situbondo';

            foreach ($rawRows as $index => $row) {
                // Analisis Kategori & Pecah Bahan
                $parsed = $this->analyzeAndBreakdownRow($row, $index + 1, $project, [
                    'default_date' => $defaultDate,
                    'default_store' => $defaultStore,
                    'existing_rab' => $existingRabItems,
                ]);

                if (!$parsed) {
                    continue;
                }

                $rpdItem = RpdItem::create(array_merge($parsed, [
                    'rpd_document_id' => $document->id,
                    'project_id' => $project->id,
                ]));

                $itemCount++;
                $totalBudget += $rpdItem->total_price;
                $totalTaxEstimated += $rpdItem->tax_total;
                $totalNetEstimated += $rpdItem->net_amount;

                match ($rpdItem->category) {
                    'bahan' => $totalMaterials += $rpdItem->total_price,
                    'upah' => $totalWages += $rpdItem->total_price,
                    'alat' => $totalEquipment += $rpdItem->total_price,
                    default => $totalOperational += $rpdItem->total_price,
                };
            }

            if ($itemCount === 0) {
                throw new \Exception('Gagal mengekstrak item bahan dari file RPD. Pastikan file memiliki kolom Uraian, Volume, dan Harga.');
            }

            $document->update([
                'total_budget' => $totalBudget,
                'total_materials' => $totalMaterials,
                'total_wages' => $totalWages,
                'total_equipment' => $totalEquipment,
                'total_operational' => $totalOperational,
                'total_tax_estimated' => $totalTaxEstimated,
                'total_net_estimated' => $totalNetEstimated,
                'total_items_count' => $itemCount,
            ]);

            return $document;
        });
    }

    /**
     * Ekstraksi baris dari file Excel / CSV.
     */
    protected function extractRowsFromFile(string $filePath, string $extension): array
    {
        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->extractFromSpreadsheet($filePath);
        }

        return $this->extractFromCsv($filePath);
    }

    /**
     * Membaca file Excel menggunakan PhpOffice/PhpSpreadsheet.
     */
    protected function extractFromSpreadsheet(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $sheetData = $worksheet->toArray(null, true, true, false);

        if (empty($sheetData)) {
            return [];
        }

        return $this->mapMatrixToStructuredRows($sheetData);
    }

    /**
     * Membaca file CSV dengan auto-detect pemisah (, atau ; atau tab).
     */
    protected function extractFromCsv(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return [];
        }

        // Deteksi delimiter
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

        return $this->mapMatrixToStructuredRows($matrix);
    }

    /**
     * Menemukan baris header dan memetakan baris-baris data ke array asosiatif.
     */
    protected function mapMatrixToStructuredRows(array $matrix): array
    {
        $headerRowIndex = null;
        $headerMap = [];

        // Cari baris header (baris yang mengandung kata seperti uraian, bahan, item, volume, harga)
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

        // Jika tidak ketemu baris header spesifik, gunakan baris pertama sebagai header atau fallback index
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

            // Abaikan baris total akhir atau header sub-bagian kosong
            if (empty($uraian) || str_starts_with(strtolower($uraian), 'total') || str_starts_with(strtolower($uraian), 'jumlah total')) {
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
     * Konversi nilai teks mata uang (Rp 1.500.000,00 atau 1,500,000) menjadi angka float murni.
     */
    protected function parseNumeric($val, float $default = 0): float
    {
        if ($val === null || $val === '') return $default;
        if (is_numeric($val)) return (float)$val;

        $str = (string)$val;
        $str = preg_replace('/[^\d,\.]/', '', $str);

        // Jika format Indonesia: 1.500.000,00
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
    protected function analyzeAndBreakdownRow(array $row, int $rowNumber, RevitalizationProject $project, array $context): ?array
    {
        $uraian = $row['uraian'];
        $vol = max(0.01, $row['volume']);
        $satuan = $row['satuan'];
        $hargaSatuan = $row['harga_satuan'];
        $totalPrice = $row['total'];

        // 1. Identifikasi Kategori
        $category = $this->determineCategory($uraian, $row['kategori']);

        // 2. Hubungkan ke RAB jika ada kecocokan
        $budgetItemId = null;
        if (!empty($context['existing_rab'])) {
            foreach ($context['existing_rab'] as $rab) {
                if (str_contains(strtolower($rab->name), strtolower(substr($uraian, 0, 15))) ||
                    str_contains(strtolower($uraian), strtolower(substr($rab->name, 0, 15)))) {
                    $budgetItemId = $rab->id;
                    break;
                }
            }
        }

        // 3. Tanggal Rencana Penarikan / Belanja BKU
        $plannedDate = $this->parseDateOrDefault($row['tanggal'], $context['default_date']);

        // 4. Toko / Pihak Ketiga / Penerima
        $supplierName = $row['toko'] ?: $this->suggestSupplier($category, $uraian, $context['default_store']);

        // 5. Metode Pembayaran BKU (Kas Tunai vs Bank Jatim)
        // Standar Pengelolaan Kas Sekolah: Di atas Rp 5.000.000 disarankan non-tunai (Transfer Bank Jatim), di bawahnya Kas Tunai
        $paymentMethod = $row['metode'] ? (
            str_contains(strtolower($row['metode']), 'bank') || str_contains(strtolower($row['metode']), 'transfer') ? 'belanja_transfer' : 'belanja_tunai'
        ) : ($totalPrice > 5000000 ? 'belanja_transfer' : 'belanja_tunai');

        // 6. Penghitungan Pajak Sesuai Ketentuan Revitalisasi Sekolah
        // - Belanja Barang/Bahan >= Rp 2.000.000 kena PPN 11% dan PPh 22 1.5%
        // - Upah Tenaga Kerja/Tukang kena PPh 21 (misal 5% jika melebihi ambang batas)
        $taxPpn = 0;
        $taxPph22 = 0;
        $taxPph21 = 0;
        $hasTax = false;

        if ($category === 'bahan' || $category === 'alat' || $category === 'operasional') {
            if ($totalPrice >= 2000000) {
                $hasTax = true;
                // Dasar Pengenaan Pajak (DPP) = Nilai Bruto / 1.11
                $dpp = round($totalPrice / 1.11, 2);
                $taxPpn = round($totalPrice - $dpp, 2); // PPN 11%
                $taxPph22 = round($dpp * 0.015, 2);     // PPh 22 1.5%
            }
        } elseif ($category === 'upah') {
            // Potongan PPh 21 untuk jasa tukang/tenaga kerja di atas batas harian (opsional simulasi)
            if ($totalPrice >= 2500000) {
                $hasTax = true;
                $taxPph21 = round($totalPrice * 0.05, 2); // 5% PPh 21
            }
        }

        $taxTotal = $taxPpn + $taxPph22 + $taxPph21;
        $netAmount = $totalPrice - ($taxPph22 + $taxPph21); // Nilai bersih yang dibayarkan ke rekanan

        // 7. Narasi Standar BKU
        $bkuDescription = $this->generateBkuDescription($category, $uraian, $vol, $satuan, $supplierName, $project->title);

        return [
            'budget_item_id' => $budgetItemId,
            'row_number' => $rowNumber,
            'item_code' => $row['kode'] ?? '5.1.02.01',
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
     * Tentukan kategori dari uraian kata kunci.
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

        // Kata kunci Upah
        if (preg_match('/(upah|tukang|pekerja|mandor|ongkos|hok|tenaga kerja|kepala tukang)/i', $text)) {
            return 'upah';
        }

        // Kata kunci Alat
        if (preg_match('/(sewa|molen|vibrator|scaffolding|peralatan|gerobak dorong|sekop|cangkul)/i', $text)) {
            return 'alat';
        }

        // Kata kunci Operasional / Administrasi
        if (preg_match('/(atk|kertas|materai|spanduk|banner|prasasti|dokumentasi|konsumsi|snack|jilid|fotocopy)/i', $text)) {
            return 'operasional';
        }

        // Default Bahan Material Bangunan
        return 'bahan';
    }

    /**
     * Rekomendasi nama pihak ketiga / toko berdasarkan kategori.
     */
    protected function suggestSupplier(string $category, string $uraian, string $defaultStore): string
    {
        return match ($category) {
            'upah' => 'Kelompok Tukang & Pekerja Swakelola',
            'alat' => 'Penyedia Sewa Peralatan Situbondo',
            'operasional' => 'Toko ATK & Percetakan Situbondo',
            default => $defaultStore,
        };
    }

    /**
     * Hasilkan uraian transaksi standar BKU Pemkab Situbondo.
     */
    protected function generateBkuDescription(string $category, string $uraian, float $vol, string $satuan, string $supplier, string $projectTitle): string
    {
        $volFormatted = number_format($vol, $vol == (int)$vol ? 0 : 2, ',', '.');

        return match ($category) {
            'upah' => "Pembayaran upah tenaga kerja/tukang {$uraian} ({$volFormatted} {$satuan}) untuk pekerjaan {$projectTitle} kepada {$supplier}",
            'alat' => "Pembayaran sewa/pengadaan alat {$uraian} ({$volFormatted} {$satuan}) pada {$supplier} untuk pekerjaan {$projectTitle}",
            'operasional' => "Pembayaran belanja operasional/administrasi {$uraian} pada {$supplier} untuk pekerjaan {$projectTitle}",
            default => "Pembayaran belanja bahan {$uraian} ({$volFormatted} {$satuan}) pada {$supplier} untuk pekerjaan {$projectTitle}",
        };
    }

    /**
     * Parse tanggal dari format string Indonesia / Excel atau kembalikan default.
     */
    protected function parseDateOrDefault(?string $dateStr, string $defaultDate): string
    {
        if (empty($dateStr)) return $defaultDate;

        try {
            // Jika format Y-m-d
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                return $dateStr;
            }

            // Jika format d/m/Y atau d-m-Y
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
     * Posting item-item pecah bahan RPD ke Tabel Transaksi BKU resmi.
     */
    public function postToBku(RpdDocument $document): int
    {
        $items = $document->items()->where('is_posted', false)->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $project = $document->project;
        $school = $document->school;
        $year = $project->fiscal_year ?? date('Y');
        $postedCount = 0;

        DB::transaction(function () use ($items, $document, $project, $school, $year, &$postedCount) {
            // Ambil nomor urut transaksi terakhir tahun ini
            $lastTrx = Transaction::where('school_id', $school->id)
                ->whereYear('transaction_date', $year)
                ->orderBy('id', 'desc')
                ->first();

            $counter = 1;
            if ($lastTrx && preg_match('/(\d+)$/', $lastTrx->transaction_number, $matches)) {
                $counter = ((int)$matches[1]) + 1;
            }

            foreach ($items as $item) {
                $month = Carbon::parse($item->planned_date)->format('m');
                $kwtNumber = sprintf('KWT-REVITA/%s/%s/%04d', $year, $month, $counter);
                $counter++;

                // Simpan transaksi BKU
                $transaction = Transaction::create([
                    'school_id' => $school->id,
                    'project_id' => $project->id,
                    'budget_item_id' => $item->budget_item_id,
                    'transaction_number' => $kwtNumber,
                    'transaction_date' => $item->planned_date,
                    'type' => $item->payment_method, // belanja_tunai atau belanja_transfer
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
                    'tax_status' => $item->tax_total > 0 ? 'dipungut' : 'none',
                    'notes' => "Hasil Pecah Bahan RPD: {$document->title} (Item #{$item->row_number})",
                    'created_by' => auth()->id(),
                ]);

                // Update item RPD terkait
                $item->update([
                    'is_posted' => true,
                    'transaction_id' => $transaction->id,
                    'bku_number' => $transaction->transaction_number,
                ]);

                $postedCount++;
            }

            // Update status dokumen RPD
            $document->update([
                'status' => 'posted_to_bku',
                'posted_at' => now(),
            ]);
        });

        return $postedCount;
    }

    /**
     * Membuat file template RPD Excel (.xlsx) dengan data contoh bahan Situbondo.
     */
    public function generateTemplateSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template RPD Revitalisasi');

        // Judul Form
        $sheet->setCellValue('A1', 'RENCANA PENARIKAN DANA (RPD) REVITALISASI SEKOLAH');
        $sheet->setCellValue('A2', 'KABUPATEN SITUBONDO - TAHUN ANGGARAN 2026');
        $sheet->mergeCells('A1:J1');
        $sheet->mergeCells('A2:J2');

        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header Tabel
        $headers = [
            'A4' => 'No',
            'B4' => 'Kode Rekening',
            'C4' => 'Kategori (Bahan/Upah/Alat/Operasional)',
            'D4' => 'Uraian Barang / Kegiatan / Bahan',
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

        $headerRange = 'A4:L4';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(4)->setRowHeight(28);

        // Contoh Data Realistis Revitalisasi Sekolah Situbondo
        $sampleData = [
            [1, '5.1.02.01', 'Bahan', 'Semen Gresik 50 Kg', 'SNI PCC Type 1', 100, 'Zak', 68000, 6800000, '2026-03-05', 'Toko Bangunan Berkah Situbondo', 'Transfer Bank'],
            [2, '5.1.02.01', 'Bahan', 'Pasir Pasang Kali Sampean', 'Kualitas Kasar Bersih', 8, 'M3', 250000, 2000000, '2026-03-06', 'Pemasok Pasir Kali Situbondo', 'Kas Tunai'],
            [3, '5.1.02.01', 'Bahan', 'Bata Merah Bakar Panji', 'Standar Ukuran 5x11x22 cm', 5000, 'Bh', 950, 4750000, '2026-03-08', 'Pengrajin Bata Panji Situbondo', 'Kas Tunai'],
            [4, '5.1.02.01', 'Bahan', 'Besi Beton Polos Dia. 10 mm', 'SNI Panjang 12 Meter', 50, 'Batang', 85000, 4250000, '2026-03-10', 'Toko Besi Abadi Situbondo', 'Kas Tunai'],
            [5, '5.1.02.01', 'Bahan', 'Cat Tembok Eksterior Dulux Weathershield', 'Warna Putih Salju 20 Liter', 4, 'Pail', 1450000, 5800000, '2026-03-15', 'Toko Cat Citra Warna Situbondo', 'Transfer Bank'],
            [6, '5.1.02.01', 'Bahan', 'Keramik Lantai 40x40 cm Kasar', 'Asia Tile Putih Polos', 60, 'Dus', 75000, 4500000, '2026-03-18', 'Toko Keramik Indah Situbondo', 'Kas Tunai'],
            [7, '5.1.02.02', 'Upah', 'Upah Tukang Batu & Plesteran (Minggu I)', 'HOK Tukang Profesional', 24, 'OH', 125000, 3000000, '2026-03-12', 'Kelompok Tukang Pak Slamet', 'Kas Tunai'],
            [8, '5.1.02.02', 'Upah', 'Upah Pekerja / Pembantu Tukang (Minggu I)', 'HOK Tenaga Lokal', 36, 'OH', 90000, 3240000, '2026-03-12', 'Kelompok Pekerja Swakelola', 'Kas Tunai'],
            [9, '5.1.02.03', 'Alat', 'Sewa Mesin Molen Pengaduk Semen', 'Kapasitas 350 Liter per Hari', 5, 'Hari', 180000, 900000, '2026-03-07', 'Sewa Alat Proyek Jaya', 'Kas Tunai'],
            [10, '5.1.02.04', 'Operasional', 'Belanja ATK dan Penggandaan Laporan SPJ', 'Kertas A4, Tinta & Jilid Buku', 1, 'Paket', 650000, 650000, '2026-03-20', 'Toko ATK Grafika Situbondo', 'Kas Tunai'],
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
            $sheet->setCellValue('I' . $rowIdx, "=F{$rowIdx}*H{$rowIdx}"); // Formula Total
            $sheet->setCellValue('J' . $rowIdx, $item[9]);
            $sheet->setCellValue('K' . $rowIdx, $item[10]);
            $sheet->setCellValue('L' . $rowIdx, $item[11]);

            // Formatting
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

        // Total Row
        $sheet->setCellValue('A' . $rowIdx, 'TOTAL RENCANA PENARIKAN DANA (RPD)');
        $sheet->mergeCells("A{$rowIdx}:H{$rowIdx}");
        $sheet->setCellValue('I' . $rowIdx, "=SUM(I5:I" . ($rowIdx - 1) . ")");
        $sheet->getStyle("A{$rowIdx}:L{$rowIdx}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowIdx}:H{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('I' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A{$rowIdx}:L{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');

        // Border
        $sheet->getStyle("A4:L{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');

        // Auto width
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
