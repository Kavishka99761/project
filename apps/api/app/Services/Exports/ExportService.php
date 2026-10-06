<?php

namespace App\Services\Exports;

use App\Models\ExportLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Common Services — data export. Any dataset (or a full account backup) can
 * be downloaded as PDF, Excel (.xlsx), CSV or JSON. Files are generated from
 * SQL Server on demand and every export is recorded in export_logs.
 */
class ExportService
{
    public const FORMATS = ['pdf', 'xlsx', 'csv', 'json'];

    private const MAX_ROWS = 20000;

    private const PDF_MAX_ROWS = 1500;

    public function __construct(private readonly DatasetRegistry $registry) {}

    /** @return list<array{key: string, label: string, module: string, module_label: string, count: int}> */
    public function catalogue(User $user): array
    {
        $out = [];
        foreach ($this->registry->all() as $key => $dataset) {
            $out[] = [
                'key' => $key,
                'label' => $dataset['label'],
                'module' => $dataset['module']->value,
                'module_label' => $dataset['module']->label(),
                'count' => $this->registry->query($key, $user)->count(),
                'filterable' => $dataset['date_column'] !== null,
            ];
        }

        return $out;
    }

    public function export(User $user, string $key, string $format, ?string $from = null, ?string $to = null): ExportFile
    {
        $dataset = $this->registry->get($key) ?? throw new \InvalidArgumentException('Unknown dataset.');
        $records = $this->registry->query($key, $user, $from, $to)->limit(self::MAX_ROWS)->get();
        [$headers, $rows] = $this->table($dataset, $records);

        $title = $dataset['label'];
        $base = 'edu-smart-'.str_replace('_', '-', $key).'-'.now()->format('Ymd-His');

        $file = match ($format) {
            'json' => new ExportFile("{$base}.json", 'application/json', json_encode([
                'meta' => $this->meta($user, $title, count($rows), $from, $to),
                'data' => isset($dataset['json'])
                    ? $records->map(fn ($r) => ($dataset['json'])($r))->all()
                    : array_map(fn ($row) => array_combine($headers, $row), $rows),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), count($rows)),
            'csv' => new ExportFile("{$base}.csv", 'text/csv; charset=UTF-8', $this->csv($headers, $rows), count($rows)),
            'xlsx' => new ExportFile("{$base}.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $this->xlsx($user, [['title' => $title, 'headers' => $headers, 'rows' => $rows]]), count($rows)),
            'pdf' => new ExportFile("{$base}.pdf", 'application/pdf', $this->pdf('exports.dataset', [
                'title' => $title,
                'module' => $dataset['module']->label(),
                'user' => $user,
                'headers' => $headers,
                'rows' => array_slice($rows, 0, self::PDF_MAX_ROWS),
                'total' => count($rows),
                'truncated' => count($rows) > self::PDF_MAX_ROWS,
                'range' => $this->rangeLabel($from, $to),
            ], count($headers) > 6), count($rows)),
            default => throw new \InvalidArgumentException('Unsupported format.'),
        };

        $this->log($user, $key, $format, $file, compact('from', 'to'));

        return $file;
    }

    /** Complete account backup across every module. */
    public function backup(User $user, string $format): ExportFile
    {
        $sections = [];
        foreach ($this->registry->all() as $key => $dataset) {
            $records = $this->registry->query($key, $user)->limit(self::MAX_ROWS)->get();
            [$headers, $rows] = $this->table($dataset, $records);
            $sections[$key] = [
                'title' => $dataset['label'],
                'module' => $dataset['module']->label(),
                'headers' => $headers,
                'rows' => $rows,
                'json' => isset($dataset['json']) ? $records->map(fn ($r) => ($dataset['json'])($r))->all() : array_map(fn ($row) => array_combine($headers, $row), $rows),
            ];
        }
        $total = array_sum(array_map(fn ($s) => count($s['rows']), $sections));
        $base = 'edu-smart-backup-'.now()->format('Ymd-His');

        $file = match ($format) {
            'json' => new ExportFile("{$base}.json", 'application/json', json_encode([
                'meta' => $this->meta($user, 'Full account backup', $total),
                'datasets' => array_map(fn ($s) => $s['json'], $sections),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $total),
            'xlsx' => new ExportFile("{$base}.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $this->xlsx($user, array_values(array_map(fn ($s) => ['title' => $s['title'], 'headers' => $s['headers'], 'rows' => $s['rows']], $sections)), summary: true), $total),
            'pdf' => new ExportFile("{$base}.pdf", 'application/pdf', $this->pdf('exports.backup', [
                'user' => $user,
                'sections' => array_map(fn ($s) => $s + ['total' => count($s['rows']), 'rows' => array_slice($s['rows'], 0, 150)], $sections),
                'total' => $total,
            ], true), $total),
            'csv' => new ExportFile("{$base}.csv", 'text/csv; charset=UTF-8', $this->backupCsv($sections), $total),
            default => throw new \InvalidArgumentException('Unsupported format.'),
        };

        $this->log($user, 'backup', $format, $file);

        return $file;
    }

    /** Render a Blade view to PDF bytes. */
    public function pdf(string $view, array $data, bool $landscape = false): string
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', $landscape ? 'landscape' : 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => true])
            ->output();
    }

    public function log(User $user, string $dataset, string $format, ExportFile $file, array $filters = []): void
    {
        ExportLog::create([
            'user_id' => $user->id,
            'dataset' => $dataset,
            'format' => $format,
            'filters' => array_filter($filters) ?: null,
            'row_count' => $file->rows,
            'file_name' => mb_substr($file->name, 0, 200),
            'file_size' => $file->size(),
        ]);

        activity()->action('exports.download')->describe(sprintf('Exported %s as %s (%d rows)', $dataset, strtoupper($format), $file->rows))
            ->with(['dataset' => $dataset, 'format' => $format, 'rows' => $file->rows, 'bytes' => $file->size()]);
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private function table(array $dataset, iterable $records): array
    {
        $headers = array_keys($dataset['columns']);
        $rows = [];
        foreach ($records as $record) {
            $row = [];
            foreach ($dataset['columns'] as $resolver) {
                $value = $resolver($record);
                $row[] = match (true) {
                    $value instanceof \BackedEnum => $value->value,
                    $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
                    is_bool($value) => $value ? 'Yes' : 'No',
                    is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
                    default => $value,
                };
            }
            $rows[] = $row;
        }

        return [$headers, $rows];
    }

    private function meta(User $user, string $title, int $rows, ?string $from = null, ?string $to = null): array
    {
        return [
            'application' => 'EDU-SMART',
            'version' => config('edusmart.version'),
            'dataset' => $title,
            'exported_at' => now()->toIso8601String(),
            'exported_by' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'rows' => $rows,
            'filters' => array_filter(['from' => $from, 'to' => $to]) ?: null,
            'source' => 'Microsoft SQL Server · '.config('database.connections.sqlsrv.database'),
        ];
    }

    private function csv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Unicode correctly
        fputcsv($handle, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($v) => $v === null ? '' : (string) $v, $row), ',', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function backupCsv(array $sections): string
    {
        $out = "\xEF\xBB\xBF";
        foreach ($sections as $section) {
            $handle = fopen('php://temp', 'w+');
            fputcsv($handle, ['# '.$section['title']], ',', '"', '');
            fputcsv($handle, $section['headers'], ',', '"', '');
            foreach ($section['rows'] as $row) {
                fputcsv($handle, array_map(fn ($v) => $v === null ? '' : (string) $v, $row), ',', '"', '');
            }
            rewind($handle);
            $out .= stream_get_contents($handle)."\n";
            fclose($handle);
        }

        return $out;
    }

    /**
     * @param  list<array{title: string, headers: list<string>, rows: list<list<mixed>>}>  $sheets
     */
    private function xlsx(User $user, array $sheets, bool $summary = false): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('EDU-SMART')
            ->setLastModifiedBy($user->name)
            ->setTitle($summary ? 'EDU-SMART account backup' : $sheets[0]['title'])
            ->setSubject('Exported from Microsoft SQL Server')
            ->setCompany('EDU-SMART');
        $spreadsheet->removeSheetByIndex(0);

        if ($summary) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Summary');
            $sheet->fromArray([['EDU-SMART account backup'], ['Student', $user->name.' <'.$user->email.'>'], ['Exported', now()->format('Y-m-d H:i')], [], ['Dataset', 'Rows']]);
            $r = 6;
            foreach ($sheets as $s) {
                $sheet->fromArray([[$s['title'], count($s['rows'])]], null, 'A'.$r++);
            }
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('4F46E5');
            $this->styleHeader($sheet, 'A5:B5');
            $sheet->getColumnDimension('A')->setWidth(42);
            $sheet->getColumnDimension('B')->setWidth(28);
        }

        $used = [];
        foreach ($sheets as $s) {
            $sheet = $spreadsheet->createSheet();
            $name = mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', '', $s['title']) ?: 'Sheet', 0, 28);
            $candidate = $name;
            for ($i = 2; isset($used[$candidate]); $i++) {
                $candidate = mb_substr($name, 0, 25).' '.$i;
            }
            $used[$candidate] = true;
            $sheet->setTitle($candidate);

            $sheet->fromArray([$s['headers']], null, 'A1');
            if ($s['rows'] !== []) {
                $sheet->fromArray(array_map(fn ($row) => array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 32000) : $v, $row), $s['rows']), null, 'A2', true);
            }

            $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($s['headers'])));
            $lastRow = max(1, count($s['rows']) + 1);
            $this->styleHeader($sheet, "A1:{$lastColumn}1");
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
            $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
            $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

            foreach (range(1, count($s['headers'])) as $index) {
                $column = Coordinate::stringFromColumnIndex($index);
                $longest = mb_strlen((string) $s['headers'][$index - 1]);
                foreach (array_slice($s['rows'], 0, 300) as $row) {
                    $longest = max($longest, mb_strlen((string) ($row[$index - 1] ?? '')));
                }
                $sheet->getColumnDimension($column)->setWidth(min(60, max(10, $longest + 2)));
                if ($longest > 60) {
                    $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getAlignment()->setWrapText(true);
                }
            }
            // Zebra striping for readability.
            for ($row = 3; $row <= $lastRow; $row += 2) {
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
        }
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return $content;
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);
    }

    private function rangeLabel(?string $from, ?string $to): ?string
    {
        return match (true) {
            $from && $to => "{$from} → {$to}",
            (bool) $from => "from {$from}",
            (bool) $to => "until {$to}",
            default => null,
        };
    }
}
