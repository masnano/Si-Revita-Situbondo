<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\RevitalizationProject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RabImportService
{
    /**
     * Memproses file upload RAB (Excel / CSV) dan otomatis memetakan ke tabel budget_items.
     */
    public function importFromSpreadsheet(UploadedFile $file, RevitalizationProject $project, array $options = []): array
    {
        $mode = $options['mode'] ?? 'replace'; // 'replace' or 'append'
        $skipZeroVolume = $options['skip_zero_volume'] ?? true;
        $targetMenu = !empty($options['target_menu_pekerjaan']) ? trim($options['target_menu_pekerjaan']) : 'Rehabilitasi Ruang Kelas';

        $ext = strtolower($file->getClientOriginalExtension());
        $allParsedItems = [];

        if (in_array($ext, ['csv', 'txt'])) {
            $rows = $this->readCsvToArray($file);
            if (!empty($rows)) {
                $parsed = $this->parseRabMatrix($rows, $skipZeroVolume, $targetMenu);
                $allParsedItems = array_merge($allParsedItems, $parsed['items']);
            }
        } else {
            // Excel (.xlsx, .xls) - Mendukung multi-sheet maupun single-sheet
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheetCount = $spreadsheet->getSheetCount();

            for ($s = 0; $s < $sheetCount; $s++) {
                $sheet = $spreadsheet->getSheet($s);
                $sheetTitle = trim($sheet->getTitle());
                $sheetRows = $sheet->toArray(null, true, true, false);

                if (empty($sheetRows)) continue;

                // Tentukan menu pekerjaan bawaan sheet:
                // Jika sheet count > 1 dan sheet title bukan 'Sheet1' / generik, gunakan sheet title sebagai menu
                $sheetMenu = $targetMenu;
                if ($sheetCount > 1 && !preg_match('/^(sheet\s*\d*|halaman\s*\d*|table\s*\d*)$/i', $sheetTitle)) {
                    $sheetMenu = $sheetTitle;
                }

                $parsed = $this->parseRabMatrix($sheetRows, $skipZeroVolume, $sheetMenu);
                if (!empty($parsed['items'])) {
                    $allParsedItems = array_merge($allParsedItems, $parsed['items']);
                }
            }
        }

        if (empty($allParsedItems)) {
            throw new \Exception('Tidak ditemukan baris item pekerjaan valid dengan volume/anggaran pada file yang diunggah.');
        }

        return DB::transaction(function () use ($project, $allParsedItems, $mode, $targetMenu) {
            $deletedCount = 0;
            if ($mode === 'replace') {
                $query = BudgetItem::where('project_id', $project->id)
                    ->doesntHave('transactions');

                // Jika target menu spesifik dipilih dan bukan 'all', hanya reset item pada menu tersebut
                if (!empty($targetMenu) && $targetMenu !== 'all') {
                    $query->where('menu_pekerjaan', $targetMenu);
                }

                $deletedCount = $query->delete();
            }

            $insertedItems = [];
            $totalBudget = 0;

            foreach ($allParsedItems as $item) {
                $created = BudgetItem::create([
                    'project_id' => $project->id,
                    'menu_pekerjaan' => $item['menu_pekerjaan'],
                    'code' => $item['code'],
                    'category' => $item['category'],
                    'name' => $item['name'],
                    'volume' => $item['volume'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'notes' => $item['notes'],
                ]);

                $insertedItems[] = $created;
                $totalBudget += $created->total_price;
            }

            return [
                'count' => count($insertedItems),
                'total_budget' => $totalBudget,
                'deleted_previous' => $deletedCount,
                'items' => $insertedItems,
            ];
        });
    }

    /**
     * Membaca file CSV menjadi matriks array 2 dimensi.
     */
    protected function readCsvToArray(UploadedFile $file): array
    {
        $data = [];
        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
        return $data;
    }

    /**
     * Membaca file spreadsheet (.xlsx, .xls, .csv) menjadi matriks array 2 dimensi (single sheet).
     */
    protected function readSpreadsheetToArray(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, ['csv', 'txt'])) {
            return $this->readCsvToArray($file);
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        return $sheet->toArray(null, true, true, false);
    }

    /**
     * Memetakan matriks tabel RAB secara cerdas dengan mendeteksi header & struktur baris.
     */
    public function parseRabMatrix(array $matrix, bool $skipZeroVolume = true, ?string $defaultMenu = 'Rehabilitasi Ruang Kelas'): array
    {
        $headerRowIndex = $this->detectHeaderRowIndex($matrix);
        $colMap = $this->resolveColumnMapping($matrix[$headerRowIndex] ?? []);

        $items = [];
        $currentDivision = '';
        $currentSection = '';
        $rowCount = count($matrix);

        for ($i = $headerRowIndex + 1; $i < $rowCount; $i++) {
            $row = $matrix[$i] ?? [];
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $name = trim((string)($row[$colMap['name']] ?? ''));
            $nameLower = strtolower($name);

            // 1. Abaikan baris total / rekapitulasi di akhir file
            if (empty($name) ||
                str_starts_with($nameLower, 'total') ||
                str_starts_with($nameLower, 'jumlah total') ||
                str_starts_with($nameLower, 'sub total') ||
                str_starts_with($nameLower, 'rekapitulasi') ||
                str_contains($nameLower, 'total biaya')
            ) {
                // Periksa jika ada teks 'TOTAL BIAYA' di kolom lain
                $allText = strtolower(implode(' ', array_map('strval', $row)));
                if (str_contains($allText, 'total biaya')) {
                    continue;
                }
                if (empty($name)) {
                    continue;
                }
            }

            // Ambil data angka volume, harga satuan, dan jumlah
            $volRaw = $colMap['volume'] !== null ? trim((string)($row[$colMap['volume']] ?? '')) : '';
            $unitRaw = $colMap['unit'] !== null ? trim((string)($row[$colMap['unit']] ?? '')) : '';
            $priceRaw = $colMap['unit_price'] !== null ? trim((string)($row[$colMap['unit_price']] ?? '')) : '';
            $totalRaw = $colMap['total_price'] !== null ? trim((string)($row[$colMap['total_price']] ?? '')) : '';

            $volume = $this->parseNumeric($volRaw);
            $unitPrice = $this->parseNumeric($priceRaw);
            $totalPrice = $this->parseNumeric($totalRaw);

            // 2. Deteksi Judul Divisi atau Sub-Pekerjaan
            if (str_starts_with(strtoupper($name), 'DIVISI') || str_starts_with(strtoupper($name), 'DEVISI')) {
                $currentDivision = $name;
                $currentSection = '';
                continue;
            }

            if ($volume <= 0 && $unitPrice <= 0 && $totalPrice <= 0) {
                // Baris tanpa nominal (Header Sub Pekerjaan, misal "PEKERJAAN STRUKTUR BETON")
                if (preg_match('/^(PEKERJAAN|PONDASI|STRUKTUR|PENUTUP|DINDING|PLAFON|PINTU|MEKANIKAL|KLOSET)/i', $name)) {
                    $currentSection = $name;
                }
                continue;
            }

            // 3. Filter kuantitas 0 atau item tanpa anggaran jika opsi diaktifkan
            if ($skipZeroVolume && ($volume <= 0 || ($unitPrice <= 0 && $totalPrice <= 0))) {
                continue;
            }

            // Hitung total jika kosong atau hitung unit price jika total tersedia
            if ($totalPrice <= 0 && $volume > 0 && $unitPrice > 0) {
                $totalPrice = round($volume * $unitPrice, 2);
            } elseif ($unitPrice <= 0 && $volume > 0 && $totalPrice > 0) {
                $unitPrice = round($totalPrice / $volume, 2);
            }

            // 4. Resolusi Kode Rekening / AHSP
            $codeAhsp = $colMap['code_ahsp'] !== null ? trim((string)($row[$colMap['code_ahsp']] ?? '')) : '';
            $codeId = $colMap['code_id'] !== null ? trim((string)($row[$colMap['code_id']] ?? '')) : '';
            $code = !empty($codeAhsp) ? $codeAhsp : (!empty($codeId) ? $codeId : null);

            // 5. Resolusi Satuan
            $unit = $this->normalizeUnit($unitRaw);

            // 6. Deteksi Kategori Belanja
            $explicitCat = $colMap['category'] !== null ? trim((string)($row[$colMap['category']] ?? '')) : null;
            $category = $this->detectCategory($name, $currentDivision . ' ' . $currentSection, $explicitCat);

            // Jika kode masih kosong, berikan kode rekening standar pos belanja
            if (empty($code)) {
                $code = match ($category) {
                    'Material/Bahan' => '5.1.02.01',
                    'Upah Tenaga Kerja' => '5.1.02.02',
                    'Peralatan/Sewa' => '5.1.02.03',
                    'Honor & Operasional' => '5.1.02.04',
                    default => '5.1.02.99',
                };
            }

            // 7. Catatan Tambahan (Divisi / AHSP / Keterangan)
            $notesArr = [];
            if ($currentDivision) {
                $notesArr[] = $currentDivision;
            }
            if ($currentSection && $currentSection !== $currentDivision) {
                $notesArr[] = $currentSection;
            }
            if ($codeAhsp && $codeAhsp !== $code) {
                $notesArr[] = "AHSP: {$codeAhsp}";
            }
            if ($colMap['notes'] !== null && !empty($row[$colMap['notes']])) {
                $notesArr[] = trim((string)$row[$colMap['notes']]);
            }
            $notes = !empty($notesArr) ? implode(' | ', $notesArr) : null;

            $explicitMenu = $colMap['menu_pekerjaan'] !== null ? trim((string)($row[$colMap['menu_pekerjaan']] ?? '')) : null;
            $menuPekerjaan = !empty($explicitMenu) ? $explicitMenu : ($defaultMenu ?? 'Rehabilitasi Ruang Kelas');

            $items[] = [
                'menu_pekerjaan' => $menuPekerjaan,
                'code' => $code,
                'category' => $category,
                'name' => $name,
                'volume' => round($volume, 2),
                'unit' => $unit,
                'unit_price' => round($unitPrice, 2),
                'total_price' => round($totalPrice, 2),
                'notes' => $notes,
            ];
        }

        return [
            'header_row' => $headerRowIndex,
            'items' => $items,
        ];
    }

    /**
     * Mencari indeks baris header berdasarkan kata kunci kolom.
     */
    protected function detectHeaderRowIndex(array $matrix): int
    {
        $bestIndex = 0;
        $maxScore = 0;

        foreach (array_slice($matrix, 0, 25, true) as $idx => $row) {
            $score = 0;
            $rowStr = strtolower(implode(' ', array_map('strval', $row)));

            if (str_contains($rowStr, 'uraian') || str_contains($rowStr, 'pekerjaan') || str_contains($rowStr, 'nama barang')) {
                $score += 3;
            }
            if (str_contains($rowStr, 'volume') || str_contains($rowStr, 'kuantitas') || str_contains($rowStr, 'vol') || str_contains($rowStr, 'qty')) {
                $score += 2;
            }
            if (str_contains($rowStr, 'satuan') || str_contains($rowStr, 'sat') || str_contains($rowStr, 'unit')) {
                $score += 2;
            }
            if (str_contains($rowStr, 'harga') || str_contains($rowStr, 'tarif')) {
                $score += 2;
            }
            if (str_contains($rowStr, 'jumlah') || str_contains($rowStr, 'total')) {
                $score += 2;
            }
            if (str_contains($rowStr, 'kode') || str_contains($rowStr, 'ahsp')) {
                $score += 1;
            }

            if ($score > $maxScore) {
                $maxScore = $score;
                $bestIndex = $idx;
            }
        }

        return $bestIndex;
    }

    /**
     * Menghubungkan nama kolom ke indeks kolom array.
     */
    protected function resolveColumnMapping(array $headerRow): array
    {
        $mapping = [
            'menu_pekerjaan' => null,
            'name' => 2, // fallback default
            'volume' => 3,
            'unit' => 4,
            'unit_price' => 5,
            'total_price' => 6,
            'code_ahsp' => 1,
            'code_id' => 0,
            'category' => null,
            'notes' => null,
        ];

        foreach ($headerRow as $cIdx => $val) {
            $v = strtolower(trim((string)$val));
            if (empty($v)) continue;

            if (str_contains($v, 'menu pekerjaan') || str_contains($v, 'lingkup pekerjaan') || str_contains($v, 'ruang pekerjaan') || str_contains($v, 'sub pekerjaan') || str_contains($v, 'paket pekerjaan') || $v === 'menu' || $v === 'ruang') {
                $mapping['menu_pekerjaan'] = $cIdx;
            } elseif (str_contains($v, 'kode ahsp') || $v === 'ahsp') {
                $mapping['code_ahsp'] = $cIdx;
            } elseif (str_contains($v, 'kode id') || str_contains($v, 'kode rekening') || $v === 'kode' || $v === 'no') {
                $mapping['code_id'] = $cIdx;
            } elseif (str_contains($v, 'uraian') || str_contains($v, 'pekerjaan') || str_contains($v, 'nama barang') || str_contains($v, 'item')) {
                $mapping['name'] = $cIdx;
            } elseif (str_contains($v, 'kuantitas') || str_contains($v, 'volume') || $v === 'vol' || $v === 'qty') {
                $mapping['volume'] = $cIdx;
            } elseif (str_contains($v, 'harga satuan') || str_contains($v, 'harga') || str_contains($v, 'tarif')) {
                $mapping['unit_price'] = $cIdx;
            } elseif (str_contains($v, 'jumlah') || str_contains($v, 'total harga') || str_contains($v, 'jumlah harga') || $v === 'total') {
                $mapping['total_price'] = $cIdx;
            } elseif (str_contains($v, 'satuan') || $v === 'sat' || $v === 'unit') {
                $mapping['unit'] = $cIdx;
            } elseif (str_contains($v, 'kategori') || str_contains($v, 'kelompok') || str_contains($v, 'pos belanja')) {
                $mapping['category'] = $cIdx;
            } elseif (str_contains($v, 'spesifikasi') || str_contains($v, 'catatan') || str_contains($v, 'keterangan')) {
                $mapping['notes'] = $cIdx;
            }
        }

        return $mapping;
    }

    /**
     * Cek apakah sebuah baris kosong seluruhnya.
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $val) {
            if ($val !== null && trim((string)$val) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Membersihkan teks angka format lokal/internasional menjadi float murni.
     */
    protected function parseNumeric(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        $str = trim((string)$value);
        // Hapus simbol mata uang dan spasi
        $str = preg_replace('/[^\d.,-]/', '', $str);

        // Jika memiliki format Indonesia seperti 1.500.000,50
        if (str_contains($str, ',') && str_contains($str, '.')) {
            $lastComma = strrpos($str, ',');
            $lastDot = strrpos($str, '.');
            if ($lastComma > $lastDot) {
                // Koma adalah pemisah desimal
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                // Titik adalah pemisah desimal
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            // Hanya koma, kemungkinan besar desimal (contoh: 260,92)
            $str = str_replace(',', '.', $str);
        }

        return (float)$str;
    }

    /**
     * Normalisasi string satuan ukuran agar seragam & rapi.
     */
    protected function normalizeUnit(string $unitRaw): string
    {
        $u = trim($unitRaw);
        if (empty($u)) {
            return 'Unit';
        }

        $uClean = preg_replace('/\s+/', '', strtolower($u));

        if (in_array($uClean, ["m'", "m’", "m`", "m1", "m"])) {
            return "m'";
        }
        if ($uClean === 'm2' || $uClean === 'm²') {
            return 'm²';
        }
        if ($uClean === 'm3' || $uClean === 'm³') {
            return 'm³';
        }
        if (in_array($uClean, ['paket', 'pkt'])) {
            return 'Paket';
        }
        if (in_array($uClean, ['buah', 'bh', 'pcs', 'pc'])) {
            return 'Buah';
        }
        if (in_array($uClean, ['batang', 'btg'])) {
            return 'Batang';
        }
        if (in_array($uClean, ['zak', 'sak', 'zk'])) {
            return 'Zak';
        }
        if (in_array($uClean, ['titik', 'ttk'])) {
            return 'Titik';
        }
        if (in_array($uClean, ['lembar', 'lbr'])) {
            return 'Lembar';
        }
        if (in_array($uClean, ['kg', 'kilogram'])) {
            return 'Kg';
        }
        if (in_array($uClean, ['oh', 'hok', 'hari'])) {
            return 'OH';
        }

        return ucfirst($u);
    }

    /**
     * Deteksi Kategori Pos Belanja SiRevita:
     * - Material/Bahan
     * - Upah Tenaga Kerja
     * - Peralatan/Sewa
     * - Honor & Operasional
     */
    public function detectCategory(string $name, ?string $division = null, ?string $explicitCategory = null): string
    {
        if (!empty($explicitCategory)) {
            $cat = strtolower($explicitCategory);
            if (str_contains($cat, 'upah') || str_contains($cat, 'tenaga') || str_contains($cat, 'tukang')) {
                return 'Upah Tenaga Kerja';
            }
            if (str_contains($cat, 'sewa') || str_contains($cat, 'alat') || str_contains($cat, 'peralatan')) {
                return 'Peralatan/Sewa';
            }
            if (str_contains($cat, 'honor') || str_contains($cat, 'operasional') || str_contains($cat, 'k3') || str_contains($cat, 'umum')) {
                return 'Honor & Operasional';
            }
            if (str_contains($cat, 'material') || str_contains($cat, 'bahan')) {
                return 'Material/Bahan';
            }
        }

        // 1. Honor & Operasional (K3, Prasasti, Papan Nama, SPJ, Administrasi, Pengawasan)
        if (preg_match('/\b(k3|prasasti|papan\s*nama|kantor\s*sementara|rumah\s*jaga|gudang|laporan|spj|administrasi|honor|konsumsi|fotocopy|penggandaan)\b/i', $name)
            || preg_match('/\b(k3|administrasi|operasional)\b/i', (string) $division)) {
            return 'Honor & Operasional';
        }

        // 2. Peralatan & Sewa (Alat, Molen, Vibrator, Stamper, Genset, Scaffolding)
        if (preg_match('/\b(sewa|alat|molen|vibrator|stamper|mesin|generator|genset|scaffolding|perancah|excavator|jack\s*hammer)\b/i', $name)) {
            return 'Peralatan/Sewa';
        }

        // 3. Upah Tenaga Kerja (Pekerja, Tukang, HOK, Bongkar, Galian, Urukan, Angkut, Manual)
        // Perhatikan: hindari kata "pekerjaan" terpaut dengan "pekerja"
        if (preg_match('/\b(bongkar|galian|penggalian|urukan|urugan|angkut|mengangkut|tenaga|upah|tukang|mandor|hok)\b/i', $name)
            || preg_match('/\bpekerja\b(?!an)/i', $name)
            || str_contains(strtolower($name), 'secara manual')) {
            return 'Upah Tenaga Kerja';
        }

        // 4. Default: Material/Bahan (Struktur, Atap, Dinding, Lantai, Cat, Pintu, Sanitasi, Elektrikal)
        return 'Material/Bahan';
    }

    /**
     * Membuat Template Excel Resmi RAB & Pos Belanja SiRevita yang siap diunduh & diisi.
     */
    public function generateTemplateSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template RAB');

        // Kop Laporan
        $sheet->setCellValue('A1', 'RENCANA ANGGARAN BIAYA (RAB) - POS BELANJA');
        $sheet->setCellValue('A2', 'PROGRAM REVITALISASI SATUAN PENDIDIKAN - KABUPATEN SITUBONDO');
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);

        $headers = [
            'A4' => 'Menu Pekerjaan',
            'B4' => 'Kode ID',
            'C4' => 'Kode AHSP',
            'D4' => 'Uraian Pekerjaan / Nama Barang',
            'E4' => 'Kuantitas',
            'F4' => 'Satuan',
            'G4' => 'Harga Satuan (Rp)',
            'H4' => 'Jumlah (Rp)',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('A4:H4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $sheet->getStyle('A4:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF047857'); // Emerald Green Situbondo
        $sheet->getStyle('A4:H4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(4)->setRowHeight(26);

        // Contoh baris data sesuai standar AHSP Situbondo dengan pengelompokan Menu Pekerjaan
        $sampleData = [
            ['Rehabilitasi Ruang Kelas', '100', '1', 'DIVISI 1 PERSIAPAN LAPANGAN/SITE WORK', '', '', '', ''],
            ['Rehabilitasi Ruang Kelas', '', '', 'K3', 1, 'Paket', 2000000, 2000000],
            ['Rehabilitasi Ruang Kelas', '', '', 'Pembuatan Prasasti', 1, 'Buah', 250000, 250000],
            ['Rehabilitasi Ruang Kelas', '106', '1.6.9', 'Bongkar rangka atap/ reng/ kaso struktur kayu', 260.92, 'm²', 12690, 3311074.80],
            ['Rehabilitasi Ruang Kelas', '108', '1.2.1.1.1', 'Penggalian tanah biasa sedalam 0 s.d. 1 m secara manual', 1.44, 'm³', 82500, 118800],
            ['Rehabilitasi Ruang Kelas', '200', '2', 'DIVISI 2 PEKERJAAN STRUKTUR', '', '', '', ''],
            ['Rehabilitasi Ruang Kelas', '203', '2.1.1.1', 'Pemasangan Atap Pelana Rangka Atap Baja Ringan profil C75/1,00', 260.92, 'm²', 293133.03, 76483270.19],
            ['Rehabilitasi Ruang Perpustakaan', '100', '1', 'DIVISI 1 PERSIAPAN LAPANGAN/SITE WORK', '', '', '', ''],
            ['Rehabilitasi Ruang Perpustakaan', '', '', 'Pekerjaan Papan nama ukuran 0,6x0,8', 1, 'Buah', 549344.40, 549344.40],
            ['Rehabilitasi Ruang Perpustakaan', '203', '2.1.1.1', 'Pemasangan Atap Pelana Rangka Atap Baja Ringan profil C75/0.75', 82.50, 'm²', 285000, 23512500],
            ['Pembangunan / Rehabilitasi Toilet', '100', '1', 'DIVISI 1 PERSIAPAN LAPANGAN', '', '', '', ''],
            ['Pembangunan / Rehabilitasi Toilet', '', '', 'Pemasangan Pintu PVC Kamar Mandi', 2, 'Buah', 635000, 1270000],
            ['Pembangunan / Rehabilitasi Toilet', '', '', 'Pemasangan Closet Jongkok', 2, 'Buah', 265631.60, 531263.20],
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
            $sheet->setCellValue('H' . $rowIdx, "=E{$rowIdx}*G{$rowIdx}");

            if (empty($item[4]) && empty($item[6])) {
                // Baris Divisi
                $sheet->getStyle("A{$rowIdx}:H{$rowIdx}")->getFont()->setBold(true);
                $sheet->getStyle("A{$rowIdx}:H{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
            } else {
                $sheet->getStyle('B' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('F' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('H' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $rowIdx++;
        }

        $sheet->setCellValue('A' . $rowIdx, 'TOTAL BIAYA');
        $sheet->mergeCells("A{$rowIdx}:G{$rowIdx}");
        $sheet->setCellValue('H' . $rowIdx, "=SUM(H5:H" . ($rowIdx - 1) . ")");
        $sheet->getStyle("A{$rowIdx}:H{$rowIdx}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowIdx}:G{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('H' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A{$rowIdx}:H{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');

        $sheet->getStyle("A4:H{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
