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

            $dpp = $dividePpn ? round($harga / static::PPN_DIVISOR, 2) : round($harga, 2);

            $items[] = [
                'name' => $name,
                'unit' => $unit,
                'volume' => round($volume, 2),
                'harga' => $dpp,
                'pajak_percent' => $dividePpn ? $taxPercent : 0,
                'keterangan' => '',
                'kunci' => '',
            ];
        }

        return $items;
    }

    /**
     * Membuat spreadsheet template keluaran sesuai susunan kolom permintaan.
     */
    public function buildSpreadsheet(array $items, array $options = []): Spreadsheet
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
            $sheet->getStyle('C' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.##');
            $sheet->getStyle('D' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.##');
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

        if (str_contains($value, 'barang') || str_contains($value, 'jasa') || str_contains($value, 'uraian')
            || str_contains($value, 'pekerjaan') || str_contains($value, 'nama') || str_contains($value, 'item')) {
            return 'name';
        }
        if (str_contains($value, 'harga') || str_contains($value, 'tarif')) {
            if (str_contains($value, 'total') || str_contains($value, 'jumlah')) {
                return 'total';
            }
            return 'harga';
        }
        if ($value === 'vol' || str_contains($value, 'volume') || str_contains($value, 'qty') || str_contains($value, 'kuantitas')) {
            return 'volume';
        }
        if (str_contains($value, 'satuan') || $value === 'sat' || $value === 'unit') {
            return 'unit';
        }
        if (str_contains($value, 'jumlah') || str_contains($value, 'total')) {
            return 'total';
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
