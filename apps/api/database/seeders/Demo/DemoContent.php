<?php

namespace Database\Seeders\Demo;

use App\Services\Exports\DocxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Turns the Markdown sources in database/seeders/content into real uploads
 * (PDF, Word, text) so the demo data goes through the same extraction,
 * summarisation and indexing pipeline as a student's own files.
 *
 * Date placeholders keep the demo current: [[+3d]] → "9 October 2026",
 * [[-6d|long]] → "Monday, 28 September 2026".
 */
class DemoContent
{
    private static ?string $workDir = null;

    public static function markdown(string $relativePath): string
    {
        $source = (string) file_get_contents(database_path('seeders/content/'.$relativePath));

        return preg_replace_callback('/\[\[([+-]\d+)d(?:\|(long))?\]\]/', function (array $m) {
            $date = Carbon::today()->addDays((int) $m[1]);

            return ($m[2] ?? '') === 'long' ? $date->format('l, j F Y') : $date->format('j F Y');
        }, $source) ?? $source;
    }

    public static function title(string $markdown): string
    {
        return preg_match('/^#\s+(.+)$/m', $markdown, $m) ? trim($m[1]) : 'Document';
    }

    /** Build an UploadedFile in the requested format from Markdown. */
    public static function upload(string $relativePath, string $format): UploadedFile
    {
        $markdown = self::markdown($relativePath);
        $name = pathinfo($relativePath, PATHINFO_FILENAME).'.'.$format;
        $path = self::workDir().DIRECTORY_SEPARATOR.$name;

        match ($format) {
            'pdf' => file_put_contents($path, self::pdf($markdown)),
            'docx' => file_put_contents($path, self::docx($markdown)),
            'txt' => file_put_contents($path, self::plainText($markdown)),
            default => file_put_contents($path, $markdown),
        };

        $mime = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
        ][$format] ?? 'application/octet-stream';

        return new UploadedFile($path, $name, $mime, null, true);
    }

    public static function pdf(string $markdown): string
    {
        $html = '<html><head><meta charset="utf-8"><style>'
            .'@page { margin: 70px 64px; } body { font-family: Helvetica, sans-serif; font-size: 11pt; line-height: 1.5; color: #1e293b; }'
            .'h1 { font-size: 20pt; color: #1e1b4b; margin: 0 0 14px; } h2 { font-size: 13.5pt; color: #312e81; margin: 20px 0 6px; }'
            .'p { margin: 0 0 10px; text-align: justify; } li { margin-bottom: 4px; }'
            .'</style></head><body>'.Str::markdown($markdown).'</body></html>';

        return Pdf::loadHTML($html)->setPaper('a4')->setOption(['isRemoteEnabled' => false])->output();
    }

    public static function docx(string $markdown): string
    {
        $writer = new DocxWriter;
        foreach (preg_split('/\n{2,}/', trim($markdown)) ?: [] as $block) {
            $block = trim($block);
            if (preg_match('/^(#{1,3})\s+(.+)$/', $block, $m)) {
                $writer->heading($m[2], strlen($m[1]));
            } elseif (preg_match('/^[-*]\s+/', $block)) {
                foreach (preg_split('/\n/', $block) ?: [] as $line) {
                    $writer->bullet(preg_replace('/^[-*]\s+/', '', $line) ?? $line);
                }
            } else {
                $writer->paragraph(preg_replace('/\s*\n\s*/', ' ', $block) ?? $block);
            }
        }

        return $writer->toBinary(self::title($markdown));
    }

    /** Plain text: headings become stand-alone lines (detected by the NLP layer). */
    public static function plainText(string $markdown): string
    {
        return preg_replace('/^#{1,6}\s+/m', '', $markdown) ?? $markdown;
    }

    private static function workDir(): string
    {
        if (self::$workDir === null) {
            self::$workDir = storage_path('app/private/seed-work');
            if (! is_dir(self::$workDir)) {
                mkdir(self::$workDir, 0775, true);
            }
        }

        return self::$workDir;
    }

    public static function cleanup(): void
    {
        foreach (glob(storage_path('app/private/seed-work').'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir(storage_path('app/private/seed-work'));
    }
}
