<?php

namespace App\Services;

use App\Models\RevitalizationProject;
use App\Models\RpdDocument;
use App\Models\RpdItem;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class KurvaSService
{
    /**
     * Parse berkas Kurva S (PDF atau Excel).
     */
    public function parseFile(UploadedFile|string $file, ?string $originalName = null): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $name = $originalName ?? ($file instanceof UploadedFile ? $file->getClientOriginalName() : basename($filePath));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (in_array($ext, ['xlsx', 'xls'])) {
            return $this->parseExcel($filePath, $name);
        }

        return $this->parsePdf($filePath, $name);
    }

    /**
     * Parse Kurva S dari file PDF.
     */
    public function parsePdf(string $filePath, string $filename = ''): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);
        $page = $pdf->getPages()[0] ?? null;
        $text = $pdf->getText();

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $cleanedLines = array_values(array_filter(array_map('trim', $lines), fn($l) => $l !== ''));

        // 1. Ekstraksi Nama Sekolah & Total Minggu
        $schoolName = 'Sekolah';
        $totalWeeks = 13; // default standar revitalisasi

        foreach ($cleanedLines as $line) {
            if (preg_match('/NAMA\s+SEKOLAH\s*:\s*:?\s*(.+)$/i', $line, $m)) {
                $schoolName = trim($m[1]);
            }
            if (preg_match('/^%\s+([0-9\s]+)$/', $line, $m)) {
                $nums = preg_split('/\s+/', trim($m[1]));
                $nums = array_filter(array_map('intval', $nums));
                if (!empty($nums)) {
                    $totalWeeks = max($nums);
                }
            }
        }

        // 2. Ekstraksi Bobot Rencana (%) & Akumulasi (%)
        $weeklyWeights = [];
        $cumulativeWeights = [];
        for ($w = 1; $w <= $totalWeeks; $w++) {
            $weeklyWeights[$w] = 0.0;
            $cumulativeWeights[$w] = 0.0;
        }

        $baseX = 365.0;
        $colWidth = 24.3;

        // Ambil data visual koordinat teks jika tersedia
        $dataTm = $page ? $page->getDataTm() : [];
        $percentageBlocksByY = [];

        foreach ($dataTm as $tm) {
            $t = trim($tm[1]);
            $x = (float)$tm[0][4];
            $y = round((float)$tm[0][5], 1);

            // Deteksi baris yang berisi kelompok angka persentase
            if (preg_match_all('/([0-9]+[,\.][0-9]+)\s*%/i', $t, $pm)) {
                $vals = array_map(fn($v) => (float)str_replace(',', '.', $v), $pm[1]);
                $count = count($vals);
                if ($count >= 1 && $count <= $totalWeeks) {
                    $percentageBlocksByY[(string)$y] = [
                        'x' => $x,
                        'y' => $y,
                        'vals' => $vals,
                        'count' => $count,
                        'text' => $t,
                    ];
                }
            }
        }

        // Deteksi baris rencana mingguan dan akumulasi
        foreach ($cleanedLines as $line) {
            // Baris rencana mingguan diawali "1,00" atau angka total kemudian daftar persen
            if (preg_match('/1[,\.]00\s+((?:[0-9]+[,\.][0-9]+\s*%\s*){4,})/', $line, $m)) {
                preg_match_all('/([0-9]+[,\.][0-9]+)\s*%/i', $m[1], $pctMatches);
                if (!empty($pctMatches[1])) {
                    $pcts = array_map(fn($v) => (float)str_replace(',', '.', $v), $pctMatches[1]);
                    foreach (array_slice($pcts, 0, $totalWeeks) as $idx => $val) {
                        $weeklyWeights[$idx + 1] = round($val, 2);
                    }
                }
            }
            // Baris akumulasi: mengandung 100,00% di akhir
            if (str_contains($line, '100,00%') && preg_match_all('/([0-9]+[,\.][0-9]+)\s*%/i', $line, $cumMatches)) {
                $pcts = array_map(fn($v) => (float)str_replace(',', '.', $v), $cumMatches[1]);
                if (count($pcts) >= $totalWeeks) {
                    $slice = array_slice($pcts, -$totalWeeks);
                    foreach ($slice as $idx => $val) {
                        $cumulativeWeights[$idx + 1] = round($val, 2);
                    }
                }
            }
        }

        // Jika baris rencana belum terisi dari teks, ambil dari percentage blocks
        if (empty(array_filter($weeklyWeights))) {
            foreach ($percentageBlocksByY as $block) {
                if ($block['count'] === $totalWeeks && max($block['vals']) < 35) {
                    foreach ($block['vals'] as $idx => $val) {
                        $weeklyWeights[$idx + 1] = round($val, 2);
                    }
                    break;
                }
            }
        }

        // Hitung akumulasi jika belum ada
        if (empty(array_filter($cumulativeWeights))) {
            $acc = 0.0;
            foreach ($weeklyWeights as $w => $pct) {
                $acc += $pct;
                $cumulativeWeights[$w] = round(min(100.0, $acc), 2);
            }
            if ($acc > 0) {
                $cumulativeWeights[$totalWeeks] = 100.0;
            }
        }

        // 3. Ekstraksi Item Pekerjaan
        $workItems = [];
        $itemIndex = 1;

        foreach ($cleanedLines as $line) {
            if (preg_match('/^(?:(\d+)\s+)?(PEKERJAAN\s+[A-Z0-9\s\-\/\(\)]+?|PERENCANAAN|PENGAWASAN|PENGELOLAAN)\s+([\d\.\-]+)\s+([\d\.\,]+%)(.*)$/i', $line, $m)) {
                $name = trim($m[2]);
                $costRaw = str_replace(['.', ' '], '', trim($m[3]));
                $cost = (float)($costRaw === '-' ? 0 : $costRaw);
                $weight = (float)str_replace(['%', ','], ['', '.'], trim($m[4]));
                $pctTail = trim($m[5]);

                preg_match_all('/([0-9]+[,\.][0-9]+)\s*%/i', $pctTail, $valMatches);
                $vals = !empty($valMatches[1]) ? array_map(fn($v) => (float)str_replace(',', '.', $v), $valMatches[1]) : [];
                $scopeKey = $this->determineScopeKey($name);

                $activeWeeks = [];
                $dist = [];

                if ($scopeKey === 'perencanaan') {
                    $activeWeeks = [1, 2];
                    $dist[1] = 0.0;
                    $dist[2] = 0.0;
                } elseif ($scopeKey === 'pengawasan' || $scopeKey === 'pengelolaan') {
                    $activeWeeks = range(1, $totalWeeks);
                    foreach ($activeWeeks as $w) $dist[$w] = 0.0;
                } elseif (!empty($vals)) {
                    $valCount = count($vals);

                    // Cari blok persentase dengan nilai pertama yang cocok untuk mengetahui koordinat X
                    $matchedX = null;
                    foreach ($percentageBlocksByY as $block) {
                        if ($block['count'] === $valCount && abs($block['vals'][0] - $vals[0]) < 0.01) {
                            $matchedX = $block['x'];
                            break;
                        }
                    }

                    if ($matchedX !== null) {
                        $startW = max(1, min($totalWeeks, (int)round(($matchedX - $baseX) / $colWidth) + 1));
                    } else {
                        // Fallback heuristik per lingkup
                        if ($scopeKey === 'kelas') {
                            $startW = 3;
                        } elseif ($scopeKey === 'perpus') {
                            $startW = 7;
                        } elseif ($scopeKey === 'toilet') {
                            $startW = 4;
                        } else {
                            $startW = 1;
                        }
                    }

                    for ($i = 0; $i < $valCount; $i++) {
                        $cw = $startW + $i;
                        if ($cw <= $totalWeeks) {
                            $activeWeeks[] = $cw;
                            $dist[$cw] = $vals[$i];
                        }
                    }
                } else {
                    $activeWeeks = range(1, $totalWeeks);
                }

                $workItems[] = [
                    'index' => $itemIndex++,
                    'name' => $name,
                    'scope_key' => $scopeKey,
                    'cost' => $cost,
                    'weight' => $weight,
                    'start_week' => !empty($activeWeeks) ? min($activeWeeks) : 1,
                    'end_week' => !empty($activeWeeks) ? max($activeWeeks) : $totalWeeks,
                    'weeks' => $activeWeeks,
                    'weekly_distribution' => $dist,
                ];
            }
        }

        if (empty($workItems)) {
            $workItems = $this->getDefaultWorkItems($totalWeeks);
        }

        return [
            'filename' => $filename,
            'school_name' => $schoolName,
            'total_weeks' => $totalWeeks,
            'weeks' => range(1, $totalWeeks),
            'weekly_weights' => $weeklyWeights,
            'cumulative_weights' => $cumulativeWeights,
            'work_items' => $workItems,
            'parsed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Parse Kurva S dari file Excel (.xlsx / .xls).
     */
    public function parseExcel(string $filePath, string $filename = ''): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        // Cari sheet Kurva S
        $targetSheet = null;
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $cleanName = strtolower(trim($sheetName));
            if (str_contains($cleanName, 'kurva') || str_contains($cleanName, 'jadwal') || str_contains($cleanName, 'time')) {
                $targetSheet = $spreadsheet->getSheetByName($sheetName);
                break;
            }
        }

        if (!$targetSheet) {
            $targetSheet = $spreadsheet->getActiveSheet();
        }

        $maxRow = min(70, $targetSheet->getHighestRow());
        $maxCol = min(40, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($targetSheet->getHighestColumn()));

        $schoolName = 'Sekolah';
        $totalWeeks = 13;
        $weekColMap = []; // week_number => col_index

        // 1. Cari Header Minggu
        for ($r = 1; $r <= min(25, $maxRow); $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $val = trim((string)$targetSheet->getCell([$c, $r])->getValue());
                if (preg_match('/NAMA\s+SEKOLAH\s*:\s*:?\s*(.+)$/i', $val, $m)) {
                    $schoolName = trim($m[1]);
                }
                if ($val === '1' || $val === 1) {
                    $tempMap = [1 => $c];
                    for ($step = 2; $step <= 35; $step++) {
                        $nextVal = trim((string)$targetSheet->getCell([$c + $step - 1, $r])->getValue());
                        if ($nextVal === (string)$step) {
                            $tempMap[$step] = $c + $step - 1;
                        } else {
                            break;
                        }
                    }
                    if (count($tempMap) >= 4) {
                        $weekColMap = $tempMap;
                        $totalWeeks = count($tempMap);
                        break 2;
                    }
                }
            }
        }

        if (empty($weekColMap)) {
            for ($w = 1; $w <= 13; $w++) {
                $weekColMap[$w] = 11 + $w;
            }
            $totalWeeks = 13;
        }

        // 2. Baca Item Pekerjaan & Baris Rencana
        $workItems = [];
        $weeklyWeights = [];
        $cumulativeWeights = [];
        for ($w = 1; $w <= $totalWeeks; $w++) {
            $weeklyWeights[$w] = 0.0;
            $cumulativeWeights[$w] = 0.0;
        }

        $itemIndex = 1;

        for ($r = 1; $r <= $maxRow; $r++) {
            $rowValues = [];
            for ($c = 1; $c <= min(10, $maxCol); $c++) {
                $v = $targetSheet->getCell([$c, $r])->getValue();
                if ($v !== null && $v !== '') {
                    $rowValues[] = trim((string)$v);
                }
            }
            $rowText = implode(' ', $rowValues);

            // Cek Baris Rencana (%)
            if (str_contains(strtoupper($rowText), 'RENCANA') && !str_contains(strtoupper($rowText), 'AKUMULASI')) {
                foreach ($weekColMap as $w => $col) {
                    $val = (float)$targetSheet->getCell([$col, $r])->getValue();
                    if ($val < 1.0 && $val > 0) {
                        $val *= 100;
                    }
                    $weeklyWeights[$w] = round($val, 2);
                }
            }

            // Cek Baris Akumulasi Rencana (%)
            if (str_contains(strtoupper($rowText), 'AKUMULASI RENCANA')) {
                foreach ($weekColMap as $w => $col) {
                    $val = (float)$targetSheet->getCell([$col, $r])->getValue();
                    if ($val < 1.0 && $val > 0) {
                        $val *= 100;
                    }
                    $cumulativeWeights[$w] = round($val, 2);
                }
            }

            // Cek Item Pekerjaan
            if (preg_match('/(PEKERJAAN\s+[A-Z0-9\s\-\/\(\)]+?|PERENCANAAN|PENGAWASAN|PENGELOLAAN)/i', $rowText, $m)) {
                $name = trim($m[1]);
                $scopeKey = $this->determineScopeKey($name);

                $cost = 0;
                $weight = 0;

                $firstWeekCol = min($weekColMap);
                for ($c = 1; $c < $firstWeekCol; $c++) {
                    $cellVal = $targetSheet->getCell([$c, $r])->getValue();
                    if (is_numeric($cellVal)) {
                        if ($cellVal > 1000) {
                            $cost = (float)$cellVal;
                        } elseif ($cellVal > 0 && $cellVal <= 100 && $weight == 0) {
                            $weight = (float)$cellVal;
                            if ($weight < 1.0) $weight *= 100;
                        }
                    }
                }

                $activeWeeks = [];
                $dist = [];
                foreach ($weekColMap as $w => $col) {
                    $val = (float)$targetSheet->getCell([$col, $r])->getValue();
                    if ($val > 0) {
                        if ($val < 1.0) $val *= 100;
                        $activeWeeks[] = $w;
                        $dist[$w] = round($val, 2);
                    }
                }

                if (empty($activeWeeks)) {
                    if ($scopeKey === 'perencanaan') {
                        $activeWeeks = [1, 2];
                    } elseif ($scopeKey === 'pengawasan' || $scopeKey === 'pengelolaan') {
                        $activeWeeks = range(1, $totalWeeks);
                    }
                }

                $workItems[] = [
                    'index' => $itemIndex++,
                    'name' => $name,
                    'scope_key' => $scopeKey,
                    'cost' => $cost,
                    'weight' => round($weight, 2),
                    'start_week' => !empty($activeWeeks) ? min($activeWeeks) : 1,
                    'end_week' => !empty($activeWeeks) ? max($activeWeeks) : $totalWeeks,
                    'weeks' => $activeWeeks,
                    'weekly_distribution' => $dist,
                ];
            }
        }

        if (empty($workItems)) {
            $workItems = $this->getDefaultWorkItems($totalWeeks);
        }

        return [
            'filename' => $filename,
            'school_name' => $schoolName,
            'total_weeks' => $totalWeeks,
            'weeks' => range(1, $totalWeeks),
            'weekly_weights' => $weeklyWeights,
            'cumulative_weights' => $cumulativeWeights,
            'work_items' => $workItems,
            'parsed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Tentukan scope_key untuk pencocokan semantik item RPD.
     */
    public function determineScopeKey(string $name): string
    {
        $n = strtolower($name);
        if (str_contains($n, 'kelas') || str_contains($n, 'rkb')) return 'kelas';
        if (str_contains($n, 'perpus') || str_contains($n, 'pustaka')) return 'perpus';
        if (str_contains($n, 'toilet') || str_contains($n, 'jamban') || str_contains($n, 'sanitasi')) return 'toilet';
        if (str_contains($n, 'perencanaan')) return 'perencanaan';
        if (str_contains($n, 'pengawasan')) return 'pengawasan';
        if (str_contains($n, 'pengelolaan')) return 'pengelolaan';
        if (str_contains($n, 'lab') || str_contains($n, 'laboratorium')) return 'lab';
        if (str_contains($n, 'uks')) return 'uks';
        if (str_contains($n, 'guru') || str_contains($n, 'kantor') || str_contains($n, 'admin')) return 'kantor';
        return 'umum';
    }

    /**
     * Ambil rentang tanggal untuk Minggu ke-W dari tanggal mulai proyek.
     */
    public function getWeekDateRange(string|Carbon $projectStartDate, int $weekNumber): array
    {
        $base = Carbon::parse($projectStartDate);
        $start = $base->copy()->addDays(($weekNumber - 1) * 7);
        $end = $start->copy()->addDays(6);

        // Cari hari Sabtu pada minggu tersebut (hari pembayaran upah tenaga kerja)
        $saturday = $start->copy();
        for ($i = 0; $i < 7; $i++) {
            $cur = $start->copy()->addDays($i);
            if ($cur->isSaturday()) {
                $saturday = $cur;
                break;
            }
        }

        return [
            'start' => $start,
            'end' => $end,
            'saturday' => $saturday,
        ];
    }

    /**
     * Hitung tanggal rencana belanja BKU (planned_date) untuk satu item berdasarkan Kurva S.
     */
    public function calculateItemPlannedDate(array|RpdItem $item, RevitalizationProject $project, int $itemIndex = 0, int $totalItems = 1): string
    {
        $itemName = is_array($item) ? ($item['item_name'] ?? '') : $item->item_name;
        $category = is_array($item) ? ($item['category'] ?? 'bahan') : $item->category;
        $uLower = strtolower($itemName);

        $startDate = $project->start_date ? $project->start_date->format('Y-m-d') : now()->format('Y-m-d');
        $kurvaData = $project->kurva_s_data;

        // Jika tidak memiliki Kurva S atau data kosong, gunakan tanggal default proyek
        if (empty($kurvaData) || empty($kurvaData['work_items'])) {
            $base = Carbon::parse($startDate);
            $offset = min(60, $itemIndex * 2);
            $target = $base->addDays($offset);
            if ($target->isSunday()) $target->addDay();
            return $target->format('Y-m-d');
        }

        $totalWeeks = $kurvaData['total_weeks'] ?? 13;
        $workItems = $kurvaData['work_items'] ?? [];

        // 1. Tentukan lingkup pekerjaan (scope_key) dari nama item
        $itemScope = 'umum';
        if (str_contains($uLower, 'kelas') || str_contains($uLower, 'rkb')) {
            $itemScope = 'kelas';
        } elseif (str_contains($uLower, 'perpus') || str_contains($uLower, 'perpustakaan')) {
            $itemScope = 'perpus';
        } elseif (str_contains($uLower, 'toilet') || str_contains($uLower, 'jamban') || str_contains($uLower, 'sanitasi')) {
            $itemScope = 'toilet';
        } elseif (str_contains($uLower, 'perencanaan')) {
            $itemScope = 'perencanaan';
        } elseif (str_contains($uLower, 'pengawasan')) {
            $itemScope = 'pengawasan';
        } elseif (str_contains($uLower, 'pengelolaan') || str_contains($uLower, 'spj') || str_contains($uLower, 'materai') || str_contains($uLower, 'laporan')) {
            $itemScope = 'pengelolaan';
        }

        // 2. Khusus Biaya Perencanaan: Selalu di awal proyek (Minggu 1, Hari Pertama/Kedua)
        if ($itemScope === 'perencanaan') {
            $pDate = Carbon::parse($startDate);
            if ($pDate->isSunday()) $pDate->addDay();
            return $pDate->format('Y-m-d');
        }

        // 3. Khusus Biaya Pengawasan / Pelaporan Akhir: Di akhir masa pelaksanaan (Minggu Terakhir)
        if ($itemScope === 'pengawasan' || (str_contains($uLower, 'pelaporan') && str_contains($uLower, 'akhir'))) {
            $range = $this->getWeekDateRange($startDate, $totalWeeks);
            $pDate = $range['end']->copy();
            if ($pDate->isSunday()) $pDate->subDay();
            return $pDate->format('Y-m-d');
        }

        // 4. Cari rentang minggu aktif dari item Kurva S yang cocok
        $matchedWork = null;
        foreach ($workItems as $wi) {
            if ($wi['scope_key'] === $itemScope && !empty($wi['weeks'])) {
                $matchedWork = $wi;
                break;
            }
        }

        if ($matchedWork) {
            $startWeek = $matchedWork['start_week'];
            $endWeek = $matchedWork['end_week'];
            $activeWeeks = $matchedWork['weeks'];
        } else {
            // Jika lingkup umum / operasional / mobilisasi:
            if (str_contains($uLower, 'persiapan') || str_contains($uLower, 'spanduk') || str_contains($uLower, 'papan') || str_contains($uLower, 'atk') || str_contains($uLower, 'rapat') || str_contains($uLower, 'konsumsi')) {
                $startWeek = 1;
                $endWeek = min(2, $totalWeeks);
                $activeWeeks = range($startWeek, $endWeek);
            } elseif (str_contains($uLower, 'molen') || str_contains($uLower, 'scaffolding') || str_contains($uLower, 'sewa alat') || str_contains($uLower, 'mobilisasi')) {
                $startWeek = 2;
                $endWeek = min(4, $totalWeeks);
                $activeWeeks = range($startWeek, $endWeek);
            } else {
                $startWeek = 2;
                $endWeek = max(2, $totalWeeks - 1);
                $activeWeeks = range($startWeek, $endWeek);
            }
        }

        $weekSpan = max(1, count($activeWeeks));

        // 5. Penentuan Hari berdasarkan Kategori & Urutan Konstruksi
        if ($category === 'upah') {
            // Cek apakah ada indikasi minggu pada uraian upah (contoh: "Upah Tukang (Minggu 1)", "Minggu 2", dst)
            if (preg_match('/minggu\s*([1-9]|1[0-4])/i', $uLower, $mMinggu)) {
                $upahWeekNum = (int)$mMinggu[1];
                $mappedIndex = min(count($activeWeeks) - 1, max(0, $upahWeekNum - 1));
                $targetWeek = $activeWeeks[$mappedIndex];
            } else {
                $pos = $weekSpan > 1 ? (int)floor(($itemIndex % $weekSpan)) : 0;
                $targetWeek = $activeWeeks[$pos];
            }

            // Upah selalu dibayarkan pada hari SABTU di akhir minggu tersebut
            $range = $this->getWeekDateRange($startDate, $targetWeek);
            return $range['saturday']->format('Y-m-d');
        }

        // Kategori Bahan / Alat / Operasional:
        // Bagi tahapan konstruksi material:
        // Fase Awal (0.00 - 0.35): Bongkaran, Galian, Pondasi, Semen, Pasir, Batu, Besi, Kolom
        // Fase Tengah (0.35 - 0.70): Dinding, Rangka Atap, Galvalum, Genteng, Plafon, Kusen
        // Fase Akhir (0.70 - 1.00): Keramik, Lantai, Cat, Pengecatan, Kunci, Sanitasi, Lampu, Listrik
        $progressFraction = 0.5;
        if (preg_match('/(bongkar|galian|pondasi|pasir pasang|batu|belah|besi|semen|cor|angkur)/i', $uLower)) {
            $progressFraction = 0.15;
        } elseif (preg_match('/(bata|dinding|kolom|balok|atap|baja ringan|kuda-kuda|galvalum|genteng|reng|plafon|hollow|kalsiboard|kusen)/i', $uLower)) {
            $progressFraction = 0.50;
        } elseif (preg_match('/(keramik|lantai|ubin|cat|finishing|pintu|kaca|sanitair|kloset|kran|pipa|listrik|lampu|stop kontak)/i', $uLower)) {
            $progressFraction = 0.85;
        } else {
            $progressFraction = min(0.95, max(0.05, ($itemIndex % 10) / 10));
        }

        $weekOffset = (int)floor($progressFraction * ($weekSpan - 1));
        $chosenWeek = $activeWeeks[$weekOffset] ?? $startWeek;

        $range = $this->getWeekDateRange($startDate, $chosenWeek);
        $wStart = $range['start']->copy();

        // Hari dalam minggu (Senin - Jumat, hindari Minggu)
        $dayInWeek = ($itemIndex % 5) + 1; // 1 = Senin, 2 = Selasa, dst
        $candidateDate = $wStart->copy()->addDays($dayInWeek);

        if ($candidateDate->isSunday()) {
            $candidateDate->addDay();
        }

        return $candidateDate->format('Y-m-d');
    }

    /**
     * Sinkronkan dan sesuaikan seluruh item RPD yang belum diposting mengikuti Kurva S proyek.
     */
    public function alignRpdDocumentToKurvaS(RpdDocument $document): array
    {
        $project = $document->project;
        if (!$project) {
            return [
                'success' => false,
                'message' => 'Proyek revitalisasi tidak ditemukan pada dokumen RPD ini.',
                'updated_count' => 0,
            ];
        }

        if (!$project->kurva_s_data || empty($project->kurva_s_data['work_items'])) {
            return [
                'success' => false,
                'message' => 'Paket Revitalisasi belum memiliki dokumen Kurva S. Silakan upload Kurva S pada form Paket Revitalisasi terlebih dahulu.',
                'updated_count' => 0,
            ];
        }

        $items = $document->items()->where('is_posted', false)->get();
        if ($items->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Tidak ada item draf/belum diposting yang dapat disesuaikan.',
                'updated_count' => 0,
            ];
        }

        $updatedCount = 0;
        $total = $items->count();

        foreach ($items as $idx => $item) {
            $newPlannedDate = $this->calculateItemPlannedDate($item, $project, $idx, $total);
            if ($newPlannedDate && $newPlannedDate !== ($item->planned_date ? $item->planned_date->format('Y-m-d') : '')) {
                $item->update([
                    'planned_date' => $newPlannedDate,
                ]);
                $updatedCount++;
            }
        }

        return [
            'success' => true,
            'message' => "Alhamdulillah! Sebanyak {$updatedCount} item RPD berhasil diselaraskan tanggal belanjanya sesuai jadwal Kurva S ({$project->kurva_s_filename}).",
            'updated_count' => $updatedCount,
            'total_weeks' => $project->kurva_s_data['total_weeks'] ?? 13,
        ];
    }

    /**
     * Fallback struktur pekerjaan Kurva S standar DAK / APBN jika file belum memiliki rincian lengkap.
     */
    protected function getDefaultWorkItems(int $totalWeeks = 13): array
    {
        return [
            [
                'index' => 1,
                'name' => 'PEKERJAAN REHABILITASI RUANG KELAS',
                'scope_key' => 'kelas',
                'cost' => 0,
                'weight' => 70.0,
                'start_week' => 3,
                'end_week' => $totalWeeks,
                'weeks' => range(3, $totalWeeks),
                'weekly_distribution' => [],
            ],
            [
                'index' => 2,
                'name' => 'PEKERJAAN REHABILITASI RUANG PERPUSTAKAAN',
                'scope_key' => 'perpus',
                'cost' => 0,
                'weight' => 20.0,
                'start_week' => 7,
                'end_week' => min($totalWeeks, 11),
                'weeks' => range(7, min($totalWeeks, 11)),
                'weekly_distribution' => [],
            ],
            [
                'index' => 3,
                'name' => 'PEKERJAAN REHABILITASI TOILET / JAMBAN',
                'scope_key' => 'toilet',
                'cost' => 0,
                'weight' => 10.0,
                'start_week' => 4,
                'end_week' => min($totalWeeks, 10),
                'weeks' => range(4, min($totalWeeks, 10)),
                'weekly_distribution' => [],
            ],
            [
                'index' => 4,
                'name' => 'PERENCANAAN',
                'scope_key' => 'perencanaan',
                'cost' => 0,
                'weight' => 0.0,
                'start_week' => 1,
                'end_week' => 2,
                'weeks' => [1, 2],
                'weekly_distribution' => [],
            ],
            [
                'index' => 5,
                'name' => 'PENGAWASAN & PENGELOLAAN',
                'scope_key' => 'pengawasan',
                'cost' => 0,
                'weight' => 0.0,
                'start_week' => 1,
                'end_week' => $totalWeeks,
                'weeks' => range(1, $totalWeeks),
                'weekly_distribution' => [],
            ],
        ];
    }
}
