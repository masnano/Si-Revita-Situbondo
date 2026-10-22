<?php

namespace App\Services;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Smalot\PdfParser\Parser;

class HpsConversionService
{
    /**
     * Rasio pemisah DPP terhadap harga yang sudah termasuk PPN.
     */
    protected const PPN_DIVISOR = 1.11;

    /**
     * Judul kolom template keluaran konversi HPS.
     */
    protected const TEMPLATE_HEADERS = [
        'Jenis Barang/Jasa',
        'Satuan',
        'Vol',
        'Harga',
        'Pajak(%)',
        'Keterangan',
        'Kunci',
    ];

    /**
     * Mengonversi berkas HPS (Excel / CSV / PDF) menjadi baris template keluaran.
     */
    public function convertFileToRows(string $filePath, string $extension, array $options = []): array
    {
        $extension = strtolower($extension);

        if ($extension === 'pdf') {
            [$header, $rows] = $this->readPdfToMatrix($filePath);
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            [$header, $rows] = $this->readSpreadsheetToMatrix($filePath);
        } else {
            [$header, $rows] = $this->readCsvToMatrix($filePath);
        }

        $map = $this->resolveHeaderMapping($header);
        $items = $this->normalizeItems($rows, $map, $options);

        if (empty($items)) {
            throw new \RuntimeException('Tidak ditemukan baris barang/jasa dengan harga satuan yang dapat dikonversi pada berkas HPS yang diunggah.');
        }

        return $items;
    }

    /**
     * Mengubah baris mentah HPS menjadi item template (DPP, pajak, vol, satuan).
     *
     * Kolom Harga diisi DPP (harga satuan HPS dibagi 1,11) karena sistem tujuan
     * (SPSE) meminta nilai DPP. Setelah DPP diinput ke kolom DPP SPSE, nilai itu
     * tidak boleh dihitung PPN lagi agar tidak terjadi pemotongan ganda.
     */
    public function normalizeItems(array $rows, array $map, array $options = []): array
    {
        $taxPercent = (float) ($options['tax_percent'] ?? 11);
        $dividePpn = (bool) ($options['divide_ppn'] ?? true);
        $noKeyColumn = count($map) === 1 && isset($map['name']);

        $items = [];
        foreach ($rows as $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $name = trim((string) ($noKeyColumn ? ($row[0] ?? '') : ($this->columnValue($row, $map, 'name'))));

            // Sebagian HPS menaruh nama item di kolom lain (mis. kolom D) sehingga
            // kolom uraian utama kosong atau hanya berisi singkatan seperti "a.".
            if (!$noKeyColumn) {
                $name = $this->resolveRowName($name, $row, $map);
            }

            if ($name === '') {
                continue;
            }

            $nameLower = strtolower($name);
            if (preg_match('/^(sub\s*total|sub\s*jumlah|total|jumlah|rekapitulasi|grand\s*total)\b/i', $nameLower)
                || str_contains($nameLower, 'total biaya')
                || str_contains($nameLower, 'harga perkiraan sendiri')) {
                continue;
            }

            $volume = $this->columnValue($row, $map, 'volume');
            $volume = $volume === null ? 1.0 : $this->parseNumeric($volume);
            if ($volume <= 0) {
                $volume = 1.0;
            }

            $unit = strtoupper(trim((string) $this->columnValue($row, $map, 'unit')));
            if ($unit === '') {
                $unit = '-';
            }

            $hargaRaw = $this->parseNumeric($this->columnValue($row, $map, 'harga'));
            $totalRaw = $this->parseNumeric($this->columnValue($row, $map, 'total'));

            $harga = $hargaRaw;
            if ($harga <= 0 && $totalRaw > 0) {
                $harga = round($totalRaw / $volume, 2);
            }

            if ($harga <= 0) {
                continue;
            }

            $hargaAsli = $harga;
            $volumeRounded = round($volume, 2);
            if ($volumeRounded <= 0) {
                $volumeRounded = 0.01;
            }

            $harga = $dividePpn ? round($harga / static::PPN_DIVISOR, 2) : round($harga, 2);

            // Total baris mengikuti kolom Jumlah pada dokumen HPS bila tersedia,
            // sehingga Total rincian sama persis dengan dokumen aslinya.
            $totalBarisHps = $totalRaw > 0 ? round($totalRaw, 2) : round($hargaAsli * $volume, 2);
            $jumlahBaris = $totalBarisHps;

            $pajakPercent = ($options['divide_ppn'] ?? false)
                ? $taxPercent
                : ($options['include_tax_label'] ?? true ? $taxPercent : 0);

            $items[] = [
                'name' => $name,
                'unit' => $unit,
                'volume' => $volumeRounded,
                'harga' => $harga,
                'pajak_percent' => $pajakPercent,
                'keterangan' => '',
                'kunci' => '',
                'jumlah' => $jumlahBaris,
                'total_hps' => $totalBarisHps,
            ];
        }

        return $this->matchHpsTotal($items, $options);
    }

    /**
     * Menyesuaikan harga DPP tiap baris agar Total rincian (harga x 1,11 x vol)
     * tepat sama dengan Jumlah baris pada dokumen HPS.
     *
     * Penyesuaian dilakukan per baris sehingga selisih tidak menumpuk dan
     * Total tiap baris identik dengan HPS, meskipun volume HPS tidak konsisten
     * dengan kolom Jumlah-nya.
     */
    protected function matchHpsTotal(array $items, array $options = []): array
    {
        if (empty($items) || !($options['match_hps_total'] ?? false)) {
            foreach ($items as $idx => $item) {
                if (isset($items[$idx]['total_hps'])) {
                    $items[$idx]['jumlah'] = (float) $items[$idx]['total_hps'];
                }
                unset($items[$idx]['total_hps']);
            }

            return $items;
        }

        foreach ($items as $idx => $item) {
            $targetBaris = round((float) ($item['total_hps'] ?? 0), 2);
            $volume = max(0.01, (float) $item['volume']);

            if ($targetBaris <= 0) {
                unset($items[$idx]['total_hps']);
                continue;
            }

            // Harga DPP ideal agar harga x 1,11 x vol = Jumlah HPS.
            $ideal = $targetBaris / (static::PPN_DIVISOR * $volume);
            $harga = round($ideal, 2);

            // Koreksi satu sen bila pembulatan belum tepat.
            if (abs(round($harga * static::PPN_DIVISOR * $volume, 2) - $targetBaris) >= 0.005) {
                $best = null;
                for ($offset = -100; $offset <= 100; $offset++) {
                    $candidate = round($ideal + $offset * 0.01, 2);
                    $diff = abs(round($candidate * static::PPN_DIVISOR * $volume, 2) - $targetBaris);

                    if ($best === null || $diff < $best['diff']) {
                        $best = ['harga' => $candidate, 'diff' => $diff];
                    }
                    if ($diff < 0.005) {
                        break;
                    }
                }
                if ($best !== null) {
                    $harga = $best['harga'];
                }
            }

            $items[$idx]['harga'] = $harga;
            $items[$idx]['jumlah'] = $targetBaris;
            unset($items[$idx]['total_hps']);
        }

        $this->closeResidual($items);

        return $items;
    }

    /**
     * Menutup sisa pembulatan total dengan menggeser beberapa sen pada baris
     * yang perubahan harganya paling kecil, sehingga total tetap akurat.
     */
    protected function closeResidual(array &$items, float $target = null): void
    {
        if ($target === null) {
            $target = 0.0;
            foreach ($items as $item) {
                $target += (float) ($item['jumlah'] ?? 0);
            }
            $target = round($target, 2);
        }

        $guard = 0;
        while ($guard < 2000) {
            $guard++;

            $current = $this->totalOfRows($items);
            $diff = round($target - $current, 2);
            if (abs($diff) < 0.005) {
                return;
            }

            $best = null;
            foreach ($items as $idx => $item) {
                foreach ([0.01, -0.01] as $step) {
                    $original = $items[$idx]['harga'];
                    $items[$idx]['harga'] = round($original + $step, 2);
                    $newDiff = abs(round($target - $this->totalOfRows($items), 2));
                    $items[$idx]['harga'] = $original;

                    if ($best === null || $newDiff < $best['error'] - 0.0000001) {
                        $best = ['idx' => $idx, 'step' => $step, 'error' => $newDiff];
                    }
                }
            }

            if ($best === null || $best['error'] >= abs($diff)) {
                return;
            }

            $items[$best['idx']]['harga'] = round($items[$best['idx']]['harga'] + $best['step'], 2);
        }
    }

    /**
     * Total nilai baris: harga x 1,11 x volume per baris.
     */
    protected function totalOfRows(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += round(((float) $item['harga']) * static::PPN_DIVISOR * ((float) $item['volume']), 2);
        }

        return round($sum, 2);
    }

    /**
     * Menentukan nama item yang paling informatif pada satu baris.
     *
     * Kolom uraian utama diutamakan, namun bila kosong atau hanya berisi
     * singkatan (mis. "a.") maka teks pada kolom lain dipakai atau digabung.
     */
    protected function resolveRowName(string $primary, array $row, array $map): string
    {
        $primary = trim($primary);

        if ($this->isMeaningfulName($primary)) {
            return $primary;
        }

        $fallback = $this->fallbackRowName($row, $map);

        if ($fallback === '') {
            return $primary;
        }

        // Gabungkan bila keduanya informatif sebagian (mis. "a." + "Asuransi").
        if ($primary !== '' && strtolower($primary) !== strtolower($fallback)) {
            return trim($primary . ' ' . $fallback);
        }

        return $fallback;
    }

    /**
     * Memeriksa apakah teks cukup bermakna untuk dijadikan nama item.
     */
    protected function isMeaningfulName(string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        // Terlalu pendek atau hanya penanda urut seperti "a.", "1)", "I".
        if (mb_strlen($name) <= 3) {
            return false;
        }

        return true;
    }

    /**
     * Mengambil nama item dari kolom lain pada baris yang sama ketika kolom
     * uraian utama kosong. Kolom angka (volume, harga, jumlah) dilewati.
     */
    protected function fallbackRowName(array $row, array $map): string
    {
        $skip = [];
        foreach (['volume', 'harga', 'total'] as $key) {
            if (isset($map[$key])) {
                $skip[$map[$key]] = true;
            }
        }

        $nameIndex = $map['name'] ?? null;
        $best = '';

        foreach ($row as $idx => $cell) {
            if (isset($skip[$idx]) || $idx === $nameIndex) {
                continue;
            }

            if ($cell === null) {
                continue;
            }

            $value = trim((string) $cell);
            if ($value === '') {
                continue;
            }

            // Lewati sel yang hanya berisi nomor urut.
            if (preg_match('/^[0-9]+[.)]?$/', $value)) {
                continue;
            }

            // Ambil teks terpanjang sebagai nama paling deskriptif.
            if (mb_strlen($value) > mb_strlen($best)) {
                $best = $value;
            }
        }

        return $best;
    }

    /**
     * Menyusun spreadsheet keluaran. Bila template resmi SPSE tersedia, template
     * tersebut dimuat lalu datanya di-append mulai baris data pertama sehingga
     * header, format, dan validasi dropdown milik SPSE tetap terjaga.
     */
    public function buildSpreadsheet(array $items, array $options = []): Spreadsheet
    {
        $templatePath = $options['template_path'] ?? $this->resolveOfficialTemplatePath();

        if ($templatePath !== null && is_file($templatePath)) {
            return $this->buildFromOfficialTemplate($templatePath, $items);
        }

        return $this->buildFromScratch($items, $options);
    }

    /**
     * Menentukan lokasi template resmi SPSE di folder public/template.
     */
    protected function resolveOfficialTemplatePath(): ?string
    {
        $candidate = public_path('template/template-rincian.xlsx');

        return is_file($candidate) ? $candidate : null;
    }

    /**
     * Meng-append data ke template resmi SPSE tanpa mengubah struktur template.
     */
    protected function buildFromOfficialTemplate(string $templatePath, array $items): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($templatePath);
        $spreadsheet = $reader->load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $startRow = $this->detectFirstDataRow($sheet);

        // Bersihkan sisa nilai lama pada area data (header & validasi tetap utuh).
        $highestRow = $sheet->getHighestRow();
        if ($highestRow >= $startRow) {
            for ($row = $startRow; $row <= $highestRow; $row++) {
                foreach (range('A', 'G') as $col) {
                    $sheet->setCellValue($col . $row, null);
                }
            }
        }

        // Pertahankan format & validasi dari baris kosong berikutnya sebagai acuan baris baru.
        $rowIdx = $startRow;
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $rowIdx, $item['name']);
            $sheet->setCellValue('B' . $rowIdx, $item['unit']);
            $sheet->setCellValueExplicit('C' . $rowIdx, (float) round((float) $item['volume'], 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('D' . $rowIdx, (float) round((float) $item['harga'], 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValue('E' . $rowIdx, $item['pajak_percent']);
            $sheet->setCellValue('F' . $rowIdx, $item['keterangan']);
            $sheet->setCellValue('G' . $rowIdx, $item['kunci']);

            $sheet->getStyle('C' . $rowIdx)->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle('D' . $rowIdx)->getNumberFormat()->setFormatCode('_-* #,##0.00_-;\-* #,##0.00_-;_-* "-"??_-;_-@_-');

            $rowIdx++;
        }

        return $spreadsheet;
    }

    /**
     * Mendeteksi baris data pertama pada template (baris setelah baris header).
     */
    protected function detectFirstDataRow($sheet): int
    {
        $highestColumn = $sheet->getHighestColumn();

        for ($row = 1; $row <= 10; $row++) {
            $cells = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, false, false, false);
            $values = $cells[0] ?? [];

            $headerHits = 0;
            foreach ($values as $value) {
                if (is_string($value) && $this->canonicalColumnKey($value) !== null) {
                    $headerHits++;
                }
            }

            if ($headerHits >= 3) {
                return $row + 1;
            }
        }

        return 2;
    }

    /**
     * Membuat spreadsheet keluaran dari nol (fallback bila template resmi tidak ada).
     */
    protected function buildFromScratch(array $items, array $options = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Konversi');

        $headerRow = (int) ($options['header_row'] ?? 1);

        foreach (static::TEMPLATE_HEADERS as $idx => $title) {
            $column = $this->columnLetter($idx + 1);
            $sheet->setCellValue($column . $headerRow, $title);
        }

        $lastColumn = $this->columnLetter(count(static::TEMPLATE_HEADERS));
        $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
        $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFB6C2CF');

        $rowIdx = $headerRow + 1;
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $rowIdx, $item['name']);
            $sheet->setCellValue('B' . $rowIdx, $item['unit']);
            $sheet->setCellValueExplicit('C' . $rowIdx, (float) round((float) $item['volume'], 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('D' . $rowIdx, (float) round((float) $item['harga'], 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValue('E' . $rowIdx, $item['pajak_percent']);
            $sheet->setCellValue('F' . $rowIdx, $item['keterangan']);
            $sheet->setCellValue('G' . $rowIdx, $item['kunci']);

            $sheet->getStyle('A' . $rowIdx . ':G' . $rowIdx)->getBorders()
                ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFDCE3EA');
            $sheet->getStyle('C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('C' . $rowIdx)->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle('D' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('E' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowIdx++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Membaca sheet HPS menjadi matriks [header, rows].
     */
    protected function readSpreadsheetToMatrix(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $matrix = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        return $this->splitMatrix($matrix);
    }

    /**
     * Membaca berkas CSV HPS menjadi matriks [header, rows].
     */
    protected function readCsvToMatrix(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $delimiter = ',';
        if (!empty($lines)) {
            $best = 0;
            foreach ([',', ';', "\t", '|'] as $candidate) {
                $count = substr_count($lines[0], $candidate);
                if ($count > $best) {
                    $best = $count;
                    $delimiter = $candidate;
                }
            }
        }

        $matrix = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                $matrix[] = $data;
            }
            fclose($handle);
        }

        return $this->splitMatrix($matrix);
    }

    /**
     * Membaca berkas PDF HPS dan menyusun matriks kolom dari baris teks.
     */
    protected function readPdfToMatrix(string $filePath): array
    {
        $rawText = '';
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $rawText = (string) $pdf->getText();
        } catch (\Throwable $e) {
            $rawText = '';
        }

        if (trim($rawText) === '') {
            throw new \RuntimeException('Dokumen PDF tidak memuat lapisan teks yang dapat dibaca. Silakan gunakan berkas Excel atau CSV.');
        }

        $header = null;
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $rawText) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cells = explode("\t", $line);
            if (count($cells) < 2) {
                $cells = preg_split('/\s{2,}/', $line) ?: [$line];
            }

            if ($header === null && $this->looksLikeHeader($cells)) {
                $header = $cells;
                continue;
            }

            if (!empty($cells)) {
                $rows[] = $cells;
            }
        }

        if ($header === null) {
            $header = static::TEMPLATE_HEADERS;
        }

        return [$header, $rows];
    }

    /**
     * Memisahkan baris header (yang memuat label kolom) dari baris data.
     */
    protected function splitMatrix(array $matrix): array
    {
        $header = null;
        $headerIndex = null;

        foreach (array_slice($matrix, 0, 30, true) as $idx => $row) {
            if ($this->looksLikeHeader($row)) {
                $header = $row;
                $headerIndex = $idx;
                break;
            }
        }

        $rows = $matrix;
        if ($headerIndex !== null) {
            $rows = array_slice($matrix, $headerIndex + 1);
        }

        return [$header ?? static::TEMPLATE_HEADERS, $rows];
    }

    /**
     * Mendeteksi apakah sebuah baris merupakan baris judul kolom.
     */
    protected function looksLikeHeader(array $row): bool
    {
        $score = 0;
        foreach ($row as $cell) {
            $value = strtolower(trim((string) $cell));
            if ($value === '') {
                continue;
            }

            if (str_contains($value, 'barang') || str_contains($value, 'jasa') || str_contains($value, 'uraian')
                || str_contains($value, 'pekerjaan') || str_contains($value, 'nama') || str_contains($value, 'item')) {
                $score += 3;
            }
            if (str_contains($value, 'satuan') || $value === 'sat' || $value === 'unit') {
                $score += 2;
            }
            if ($value === 'vol' || str_contains($value, 'volume') || str_contains($value, 'qty') || str_contains($value, 'kuantitas')) {
                $score += 2;
            }
            if (str_contains($value, 'harga') || str_contains($value, 'tarif')) {
                $score += 2;
            }
        }

        return $score >= 4;
    }

    /**
     * Memetakan label kolom template Ke header HPS yang tersedia.
     */
    protected function resolveHeaderMapping(?array $header): array
    {
        $map = [];
        foreach (($header ?? []) as $idx => $cell) {
            $key = $this->canonicalColumnKey($cell);
            if ($key !== null && !isset($map[$key])) {
                $map[$key] = $idx;
            }
        }

        return $map;
    }

    /**
     * Menentukan kunci template dari teks judul kolom.
     */
    protected function canonicalColumnKey(mixed $cell): ?string
    {
        $value = strtolower(trim((string) $cell));
        if ($value === '') {
            return null;
        }

        // Kolom nomor urut tidak dipakai sebagai nama item.
        if (in_array($value, ['no', 'no.', 'nomor', 'no urut', 'nourut', '#', 'kode', 'kode barang'], true)) {
            return null;
        }

        // Kolom harga diperiksa lebih dahulu: "Harga Satuan" harus terbaca sebagai harga,
        // bukan sebagai kolom satuan meskipun memuat kata "satuan".
        if (str_contains($value, 'harga') || str_contains($value, 'tarif')) {
            if (str_contains($value, 'total') || str_contains($value, 'jumlah')) {
                return 'total';
            }
            return 'harga';
        }
        if (str_contains($value, 'satuan') || $value === 'sat' || $value === 'sat.' || $value === 'unit' || $value === 'uom') {
            return 'unit';
        }
        if ($value === 'vol' || $value === 'vol.' || str_contains($value, 'volume') || str_contains($value, 'qty') || str_contains($value, 'kuantitas')) {
            return 'volume';
        }
        if (str_contains($value, 'jumlah') || str_contains($value, 'total')) {
            return 'total';
        }
        if (str_contains($value, 'barang') || str_contains($value, 'jasa') || str_contains($value, 'uraian')
            || str_contains($value, 'pekerjaan') || str_contains($value, 'nama') || str_contains($value, 'item')
            || str_contains($value, 'deskripsi')) {
            return 'name';
        }

        return null;
    }

    /**
     * Mengambil nilai kolom sesuai pemetaan HPS.
     */
    protected function columnValue(array $row, array $map, string $key): mixed
    {
        $index = $map[$key] ?? null;
        if ($index === null) {
            return null;
        }

        return $row[$index] ?? null;
    }

    /**
     * Membersihkan teks angka format lokal menjadi float murni.
     */
    protected function parseNumeric(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);
        $str = preg_replace('/[^\d.,-]/', '', $str);

        if (str_contains($str, ',') && str_contains($str, '.')) {
            if (strrpos($str, ',') > strrpos($str, '.')) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        return is_numeric($str) ? (float) $str : 0.0;
    }

    /**
     * Memeriksa apakah seluruh sel baris kosong.
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Mengubah indeks numerik kolom menjadi huruf kolom.
     */
    protected function columnLetter(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
