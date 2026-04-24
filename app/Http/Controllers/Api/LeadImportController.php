<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\MstLeadStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LeadImportController extends Controller
{
    private const TEMPLATE_HEADERS = [
        'BOOKING',
        "CUSTOMER'S NAME",
        "FATHER'S NAME",
        'ADDRESS1',
        'EMAIL',
        'CONTACT NO.',
        'MOBILE NO.',
        'PAN',
        'SECOND APPLICANT',
        'S/W',
        'AREA',
        'BLOCK',
        'PLOT NO.',
        'RATE',
        'PLC',
        'FACING',
        'ROAD',
        'CORNER',
        'L',
        'SHOP',
        'IDC',
        'TOTAL COST',
        'BOOKING %',
        'PENDING %',
        'PAYMENT',
        'CHQ NO./TR',
        'BANK NAME',
        'GH CODE',
        'BOOKING THRO',
        'SALES PERSON NAME',
        'REGISTRY STATUS',

        // Optional fields (recognized by importer)
        'STATUS',
        'CITY',
        'BUDGET',
        'PLOT SIZE',
        'PURPOSE',
        'TIMELINE TO BUY',
        'LOAN REQUIRED',
    ];

    public function template()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Leads');

            $col = 1;
            foreach (self::TEMPLATE_HEADERS as $h) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).'1', $h);
                $col++;
            }

            // Example row
            $example = [
                'BOOKING' => 'Yes',
                "CUSTOMER'S NAME" => 'John Doe',
                "FATHER'S NAME" => 'Richard Doe',
                'ADDRESS1' => 'Sector 21, Noida',
                'EMAIL' => 'john@example.com',
                'MOBILE NO.' => '9999999999',
                'TOTAL COST' => '4500000',
                'STATUS' => 'new',
            ];

            $col = 1;
            foreach (self::TEMPLATE_HEADERS as $h) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).'2', (string) ($example[$h] ?? ''));
                $col++;
            }

            $sheet->freezePane('A2');
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

            $writer = new Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, 'leads-import-template.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Throwable $e) {
            Log::error('Lead import template failed', ['exception' => $e]);

            return response()->json([
                'message' => 'Failed to generate template. Please contact admin.',
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls'],
            'dedupe' => ['nullable', 'boolean'],
            'allow_missing_contact' => ['nullable', 'boolean'],
        ]);

        $dedupe = (bool) $request->boolean('dedupe', true);
        $allowMissingContact = (bool) $request->boolean('allow_missing_contact', false);

        try {
            $file = $request->file('file');
            $path = $file->getRealPath();

            $spreadsheet = IOFactory::load($path);

            [$sheet, $sheetIndex, $sheetName, $rows] = $this->pickBestSheet($spreadsheet);

            if (! is_array($rows) || count($rows) < 2) {
                return response()->json(['message' => 'Excel looks empty.'], 422);
            }

            $headerRowNum = $this->findHeaderRowNum($rows);
            if (! $headerRowNum) {
                return response()->json(['message' => 'Header row not found.'], 422);
            }

            $headerRow = $rows[$headerRowNum] ?? [];
            $headerMap = [];
            foreach ($headerRow as $col => $header) {
                $key = $this->normalizeHeader($header);
                if (! $key) {
                    continue;
                }
                $headerMap[$col] = $key;
            }

            if (! count($headerMap)) {
                return response()->json(['message' => 'Header row not found.'], 422);
            }

            // Guardrail: avoid treating a data row as "header" (common when the Excel has multiple sheets).
            $knownHeaderKeys = $this->templateHeaderKeys();
            $headerKnownMatches = 0;
            foreach ($headerMap as $k) {
                if (isset($knownHeaderKeys[(string) $k])) {
                    $headerKnownMatches++;
                }
            }
            if ($headerKnownMatches < 2) {
                return response()->json([
                    'message' => 'Header row not recognized. Please upload the correct sheet or use the sample template.',
                ], 422);
            }

            $activeStatuses = MstLeadStatus::query()->get(['key', 'label', 'is_active']);
            $activeKeys = $activeStatuses->where('is_active', true)->pluck('key')->map(fn ($v) => (string) $v)->all();
            $labelToKey = [];
            foreach ($activeStatuses as $s) {
                $labelToKey[$this->normalizeHeader($s->label)] = (string) $s->key;
                $labelToKey[$this->normalizeHeader($s->key)] = (string) $s->key;
            }
            $defaultStatus = in_array('new', $activeKeys, true) ? 'new' : (string) ($activeKeys[0] ?? 'new');

            $created = 0;
            $skipped = 0;
            $skippedNoMeaningful = 0;
            $skippedNoContact = 0;
            $skippedDuplicate = 0;
            $errors = [];

            $sample = [];
            $sampleLimit = 5;

            foreach ($rows as $rowNum => $row) {
                if ((int) $rowNum <= (int) $headerRowNum) {
                    continue;
                }

                $data = [];
                foreach ($headerMap as $col => $key) {
                    $data[$key] = $this->cellToString($row[$col] ?? null);
                }

                // Skip fully empty / formatting-only rows
                $meaningfulCells = array_filter($data, fn ($v) => $this->isMeaningfulCell($v));
                if (! count($meaningfulCells)) {
                    continue;
                }

                try {
                    $name = $this->firstNonEmpty($data, [
                        'customers_name',
                        'customer_s_name',
                        'customer_name',
                        'full_name',
                        'name',
                    ]) ?? $this->firstNonEmptyKeyContainsAny($data, ['customer', 'name']);

                    $email = $this->firstNonEmpty($data, ['email'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['email']);

                    $phone = $this->firstNonEmpty($data, [
                        'mobile_no',
                        'mobile',
                        'mobile_number',
                        'contact_no',
                        'contact',
                        'phone',
                        'phone_no',
                        'phone_number',
                    ]) ?? $this->firstNonEmptyKeyContainsAny($data, ['mobile', 'contact', 'phone']);

                    $address = $this->firstNonEmpty($data, ['address1', 'address'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['address']);

                    $pan = $this->firstNonEmpty($data, ['pan'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['pan']);

                    $plotNo = $this->firstNonEmpty($data, ['plot_no', 'plot_no_'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['plot', 'no']);

                    // If the row has no identity fields, treat as blank and skip.
                    // (Prevents creating "blank leads" from formatted/extra rows or wrong-sheet detection.)
                    if (! $this->isMeaningfulCell($name)
                        && ! $this->isMeaningfulCell($phone)
                        && ! $this->isMeaningfulCell($email)) {
                        $skipped++;
                        $skippedNoMeaningful++;
                        $this->pushSample($sample, $sampleLimit, $rowNum, $name, $phone, $email, 'skipped_blank');
                        continue;
                    }

                    $city = $this->firstNonEmpty($data, ['city'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['city']);

                    $budget = $this->firstNonEmpty($data, ['budget', 'total_cost', 'total', 'rate']);

                    $plotSize = $this->firstNonEmpty($data, ['plot_size'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['plot']);

                    $purpose = $this->firstNonEmpty($data, ['purpose', 'investment_self_use'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['purpose', 'investment', 'self']);

                    $timeline = $this->firstNonEmpty($data, ['timeline_to_buy', 'timeline'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['timeline']);

                    $loanRaw = $this->firstNonEmpty($data, ['loan_required', 'loan'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['loan']);

                    $loanRequired = null;
                    if ($loanRaw !== null) {
                        $loanRequired = in_array(strtolower((string) $loanRaw), ['yes', 'true', '1'], true);
                    }

                    $ghCode = $this->firstNonEmpty($data, ['gh_code'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['gh', 'code']);

                    $bookingThro = $this->firstNonEmpty($data, ['booking_thro', 'booking_through'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['booking', 'thro']);

                    $paymentAgainst = $this->firstNonEmpty($data, ['payment_against'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['payment', 'against']);

                    if (! $this->isMeaningfulCell($paymentAgainst)) {
                        $area = $this->firstNonEmpty($data, ['area']) ?? $this->firstNonEmptyKeyContainsAny($data, ['area']);
                        $unit = $plotNo ?: ($this->firstNonEmpty($data, ['plot_no', 'plot']) ?? null);
                        $desc = 'Booking';
                        if ($this->isMeaningfulCell($unit)) {
                            $desc .= ' of Unit '.trim((string) $unit);
                        }
                        if ($this->isMeaningfulCell($area)) {
                            $desc .= ' ('.trim((string) $area).')';
                        }
                        $paymentAgainst = $desc !== 'Booking' ? $desc : null;
                    }

                    $chequeNo = $this->firstNonEmpty($data, ['chq_no_tr', 'cheque_no'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['chq', 'cheque']);

                    $bankName = $this->firstNonEmpty($data, ['bank_name'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['bank']);

                    $paymentAmount = $this->firstNonEmpty($data, ['payment', 'booking_amount', 'total_cost', 'total'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['payment', 'amount', 'total', 'cost']);

                    $phone = $phone ? $this->normalizePhone((string) $phone) : null;
                    $email = $email ? trim((string) $email) : null;

                    if (! $allowMissingContact && ! $this->isMeaningfulCell($phone) && ! $this->isMeaningfulCell($email)) {
                        $skipped++;
                        $skippedNoContact++;
                        $this->pushSample($sample, $sampleLimit, $rowNum, $name, $phone, $email, 'skipped_no_contact');
                        continue;
                    }

                    if ($dedupe && ($phone || $email)) {
                        $existsQuery = Lead::query();
                        $existsQuery->where(function ($q) use ($phone, $email) {
                            if ($phone) {
                                $q->orWhere('phone', $phone);
                            }
                            if ($email) {
                                $q->orWhere('email', $email);
                            }
                        });

                        if ($existsQuery->exists()) {
                            $skipped++;
                            $skippedDuplicate++;
                            $this->pushSample($sample, $sampleLimit, $rowNum, $name, $phone, $email, 'skipped_duplicate');
                            continue;
                        }
                    }

                    $statusInput = $this->firstNonEmpty($data, ['status'])
                        ?? $this->firstNonEmptyKeyContainsAny($data, ['status']);

                    $statusKey = $defaultStatus;
                    if ($statusInput !== null) {
                        $norm = $this->normalizeHeader($statusInput);
                        $statusKey = $labelToKey[$norm] ?? $defaultStatus;
                    }

                    Lead::create([
                        'platform' => 'excel',
                        'lead_source' => 'excel_upload',

                        'name' => $name ? trim((string) $name) : null,
                        'phone' => $phone ?: null,
                        'email' => $email ?: null,
                        'city' => $city ? trim((string) $city) : null,
                        'notes' => $address ? trim((string) $address) : null,

                        'budget' => $this->toFloatOrNull($budget),
                        'plot_size' => $plotSize ? trim((string) $plotSize) : null,
                        'purpose' => $purpose ? trim((string) $purpose) : null,
                        'timeline_to_buy' => $timeline ? trim((string) $timeline) : null,
                        'loan_required' => $loanRequired,

                        // Receipt fields (best-effort mapping from Excel columns)
                        'customer_code' => $ghCode ? trim((string) $ghCode) : null,
                        'payment_against' => $paymentAgainst ? trim((string) $paymentAgainst) : null,
                        'cheque_no' => $chequeNo ? trim((string) $chequeNo) : null,
                        'bank_name' => $bankName ? trim((string) $bankName) : null,
                        'transaction_description' => $bookingThro ? trim((string) $bookingThro) : null,
                        'transaction_amount' => $this->toFloatOrNull($paymentAmount),

                        'qualification' => $data,
                        'raw_payload' => [
                            'source' => 'excel_import',
                            'sheet_index' => (int) $sheetIndex,
                            'sheet_name' => $sheetName,
                            'header_row' => (int) $headerRowNum,
                            'row_number' => (int) $rowNum,
                        ],
                        'status' => $statusKey,
                    ]);

                    $created++;
                    $this->pushSample($sample, $sampleLimit, $rowNum, $name, $phone, $email, 'created');
                } catch (\Throwable $e) {
                    $errors[] = [
                        'row' => (int) $rowNum,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'created' => $created,
                'skipped' => $skipped,
                'skipped_blank' => $skippedNoMeaningful,
                'skipped_no_contact' => $skippedNoContact,
                'skipped_duplicate' => $skippedDuplicate,
                'sheet_index' => (int) $sheetIndex,
                'sheet_name' => $sheetName,
                'header_row' => (int) $headerRowNum,
                'detected_headers' => array_values($headerMap),
                'sample' => $sample,
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            Log::error('Lead import failed', ['exception' => $e]);

            return response()->json([
                'message' => 'Failed to import Excel. Please check file format.',
            ], 500);
        }
    }

    private function pickBestSheet(Spreadsheet $spreadsheet): array
    {
        $best = null;
        $bestScore = -1;
        $knownHeaderKeys = $this->templateHeaderKeys();

        $count = $spreadsheet->getSheetCount();
        for ($i = 0; $i < $count; $i++) {
            $sheet = $spreadsheet->getSheet($i);
            $rows = $sheet->toArray(null, true, true, true);
            if (! is_array($rows) || count($rows) < 2) {
                continue;
            }

            $headerRow = $this->findHeaderRowNum($rows);
            if (! $headerRow) {
                continue;
            }

            $norms = [];
            foreach (($rows[$headerRow] ?? []) as $cell) {
                $k = $this->normalizeHeader($cell);
                if ($k !== '') {
                    $norms[] = $k;
                }
            }

            $knownMatches = 0;
            $dataLike = 0;
            foreach ($norms as $k) {
                if (isset($knownHeaderKeys[$k])) {
                    $knownMatches++;
                }

                // Heuristic: a header row should not be mostly long strings / pure numbers / date-like tokens.
                if (preg_match('/^[0-9]+$/', $k)) {
                    $dataLike++;
                } elseif (strlen($k) > 25) {
                    $dataLike++;
                } elseif (preg_match('/^[0-9]{1,2}_[0-9]{1,2}_[0-9]{2,4}$/', $k)) {
                    $dataLike++;
                }
            }

            // If it doesn't match even a few known headers AND it looks like data, ignore this sheet.
            if ($knownMatches < 2 && $dataLike >= max(5, (int) ceil(count($norms) * 0.3))) {
                continue;
            }

            $score = count($norms) + ($knownMatches * 50);
            foreach ($norms as $k) {
                if ($k === 'email') $score += 20;
                if ($k === 'mobile_no' || $k === 'contact_no') $score += 20;
                if ($k === 'customers_name' || $k === 'customer_s_name') $score += 20;
                if (str_contains($k, 'customer') && str_contains($k, 'name')) $score += 10;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$sheet, $i, (string) $sheet->getTitle(), $rows];
            }
        }

        if ($best) {
            return $best;
        }

        // Fallback to first sheet
        $sheet0 = $spreadsheet->getSheet(0);
        return [$sheet0, 0, (string) $sheet0->getTitle(), $sheet0->toArray(null, true, true, true)];
    }

    private function findHeaderRowNum(array $rows): ?int
    {
        $maxRow = min(count($rows), 30);
        $bestRow = null;
        $bestScore = 0;

        for ($i = 1; $i <= $maxRow; $i++) {
            $row = $rows[$i] ?? null;
            if (! is_array($row)) continue;

            $norms = [];
            $nonEmpty = 0;
            foreach ($row as $cell) {
                $k = $this->normalizeHeader($cell);
                if ($k !== '') {
                    $nonEmpty++;
                    $norms[] = $k;
                }
            }

            if ($nonEmpty < 3) continue;

            $score = $nonEmpty;
            foreach ($norms as $k) {
                if ($k === 'email') $score += 20;
                if ($k === 'mobile_no' || $k === 'contact_no' || $k === 'phone' || $k === 'phone_number') $score += 15;
                if ($k === 'customers_name' || $k === 'customer_s_name') $score += 15;
                if (str_contains($k, 'email')) $score += 10;
                if (str_contains($k, 'mobile') || str_contains($k, 'contact') || str_contains($k, 'phone')) $score += 8;
                if (str_contains($k, 'customer') && str_contains($k, 'name')) $score += 8;
                if (str_contains($k, 'total') && str_contains($k, 'cost')) $score += 5;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $i;
            }
        }

        return $bestRow;
    }

    private function templateHeaderKeys(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $keys = [];
        foreach (self::TEMPLATE_HEADERS as $h) {
            $k = $this->normalizeHeader($h);
            if ($k !== '') {
                $keys[$k] = true;
            }
        }

        // Common variants seen in real-world sheets.
        $keys['customer_name'] = true;
        $keys['customers_name'] = true;
        $keys['pan_no'] = true;
        $keys['phone'] = true;
        $keys['phone_no'] = true;
        $keys['phone_number'] = true;
        $keys['mobile'] = true;
        $keys['mobile_number'] = true;
        $keys['contact'] = true;

        $cache = $keys;
        return $cache;
    }

    private function normalizeHeader($value): string
    {
        $s = strtolower(trim((string) ($value ?? '')));
        if ($s === '') return '';

        $s = str_replace(["'", '"', '`'], '', $s);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? $s;
        $s = trim($s, '_');

        return $s;
    }

    private function isMeaningfulCell($value): bool
    {
        if ($value === null) return false;
        $s = trim((string) $value);
        if ($s === '') return false;

        $lower = strtolower($s);
        if (in_array($lower, ['na', 'n/a', 'null', 'nil', 'none', '-', '--'], true)) {
            return false;
        }

        // Treat pure 0 as empty (common in formatted Excel rows)
        if ($s === '0' || $s === '0.0' || $s === '0.00') return false;

        return true;
    }

    private function cellToString($value): ?string
    {
        if ($value === null) return null;

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            if (is_float($value)) {
                $s = (string) $value;
                if (stripos($s, 'e') !== false || fmod($value, 1.0) === 0.0) {
                    return number_format($value, 0, '.', '');
                }

                return rtrim(rtrim($s, '0'), '.');
            }

            return (string) $value;
        }

        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    private function firstNonEmpty(array $data, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = $data[$k] ?? null;
            if ($this->isMeaningfulCell($v)) {
                return (string) $v;
            }
        }

        return null;
    }

    private function firstNonEmptyKeyContainsAny(array $data, array $parts): ?string
    {
        foreach ($data as $k => $v) {
            if (! $this->isMeaningfulCell($v)) continue;
            $key = (string) $k;

            foreach ($parts as $p) {
                if (str_contains($key, (string) $p)) {
                    return (string) $v;
                }
            }
        }

        return null;
    }

    private function normalizePhone(string $value): string
    {
        $s = trim($value);
        $s = preg_replace('/[^0-9+]/', '', $s) ?? $s;
        return $s;
    }

    private function toFloatOrNull($value): ?float
    {
        if (! $this->isMeaningfulCell($value)) return null;
        $s = trim((string) $value);

        $clean = preg_replace('/[^0-9.]/', '', $s);
        if ($clean === null || $clean === '') return null;

        return (float) $clean;
    }

    private function maskPhone(?string $phone): ?string
    {
        if (! $phone) return null;
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if (strlen($digits) <= 4) return $digits;
        return str_repeat('*', max(strlen($digits) - 4, 0)).substr($digits, -4);
    }

    private function maskEmail(?string $email): ?string
    {
        if (! $email) return null;
        $email = trim($email);
        $at = strpos($email, '@');
        if ($at === false) return $email;
        $name = substr($email, 0, $at);
        $domain = substr($email, $at);
        if (strlen($name) <= 2) return $name.$domain;
        return substr($name, 0, 1).str_repeat('*', strlen($name) - 2).substr($name, -1).$domain;
    }

    private function pushSample(array &$sample, int $limit, int $rowNum, ?string $name, ?string $phone, ?string $email, string $result): void
    {
        if (count($sample) >= $limit) return;

        $sample[] = [
            'row' => (int) $rowNum,
            'result' => $result,
            'name' => $name ? trim($name) : null,
            'phone' => $this->maskPhone($phone),
            'email' => $this->maskEmail($email),
        ];
    }
}
