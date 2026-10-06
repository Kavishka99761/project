<?php

namespace App\Services\Documents;

use App\Services\Nlp\Text;
use DOMDocument;
use DOMXPath;
use RuntimeException;
use Smalot\PdfParser\Config as PdfConfig;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/**
 * Extracts plain text (page by page) from learning materials and academic
 * documents: PDF, Word (.docx, best-effort legacy .doc), PowerPoint (.pptx),
 * plain text and Markdown.
 *
 * Headings found in Word/PowerPoint styles are emitted as "# Heading" lines
 * and list items as "• item" lines, so the NLP layer can recover structure.
 */
class TextExtractor
{
    /** Refuse to inflate archive members larger than this (zip-bomb guard). */
    private const MAX_XML_BYTES = 60 * 1024 * 1024;

    /** Words per virtual page for formats without real pagination. */
    private const WORDS_PER_PAGE = 450;

    public function extract(string $path, string $extension): ExtractedText
    {
        if (! is_file($path)) {
            throw new RuntimeException('The uploaded file could not be found on the server.');
        }

        $result = match (strtolower($extension)) {
            'pdf' => $this->pdf($path),
            'docx' => $this->docx($path),
            'pptx' => $this->pptx($path),
            'doc' => $this->legacyDoc($path),
            'md', 'markdown' => $this->paginate($this->markdown($this->readText($path))),
            default => $this->paginate($this->readText($path)),
        };

        $pages = array_map(fn ($page) => Text::normalize($page), $result->pages);
        $text = Text::normalize(implode("\n\n", $pages));

        return new ExtractedText($text, $pages, max($result->pageCount, count($pages)), $result->warnings);
    }

    private function pdf(string $path): ExtractedText
    {
        $config = new PdfConfig;
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(256 * 1024 * 1024);

        try {
            $pdf = (new PdfParser([], $config))->parseFile($path);
        } catch (\Throwable $e) {
            throw new RuntimeException('This PDF could not be read ('.$e->getMessage().'). It may be encrypted or damaged.');
        }

        $pages = [];
        foreach ($pdf->getPages() as $page) {
            try {
                $pages[] = $page->getText();
            } catch (\Throwable) {
                $pages[] = '';
            }
        }

        $warnings = [];
        if (trim(implode('', $pages)) === '') {
            $warnings[] = 'No selectable text was found — this looks like a scanned PDF. Run OCR on it and upload again.';
        }

        return new ExtractedText('', $pages, count($pages), $warnings);
    }

    private function docx(string $path): ExtractedText
    {
        $zip = $this->openZip($path);
        $xml = $this->zipEntry($zip, 'word/document.xml');
        $appXml = $zip->getFromName('docProps/app.xml') ?: '';
        $zip->close();

        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new RuntimeException('The Word document is damaged and could not be parsed.');
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $pages = [''];
        foreach ($xpath->query('//w:body/w:p | //w:body/w:tbl') as $node) {
            if ($node->localName === 'tbl') {
                foreach ($xpath->query('.//w:tr', $node) as $row) {
                    $cells = [];
                    foreach ($xpath->query('./w:tc', $row) as $cell) {
                        $cells[] = trim($this->runText($xpath, $cell));
                    }
                    $pages[count($pages) - 1] .= implode(' | ', array_filter($cells))."\n";
                }
                $pages[count($pages) - 1] .= "\n";

                continue;
            }

            if ($xpath->query('.//w:br[@w:type="page"] | .//w:lastRenderedPageBreak', $node)->length > 0 && trim($pages[count($pages) - 1]) !== '') {
                $pages[] = '';
            }

            $text = trim($this->runText($xpath, $node));
            if ($text === '') {
                continue;
            }
            $style = (string) $xpath->evaluate('string(./w:pPr/w:pStyle/@w:val)', $node);
            $isList = $xpath->query('./w:pPr/w:numPr', $node)->length > 0 || preg_match('/list/i', $style);

            $line = match (true) {
                (bool) preg_match('/^(heading|title|subtitle|berschrift|titre)/i', $style) => '# '.$text,
                (bool) $isList => '• '.$text,
                default => $text,
            };
            $pages[count($pages) - 1] .= $line."\n\n";
        }

        $declared = preg_match('/<Pages>(\d+)<\/Pages>/', $appXml, $m) ? (int) $m[1] : 0;
        $pages = array_values(array_filter($pages, fn ($p) => trim($p) !== ''));

        // No explicit page breaks: fall back to virtual pages.
        if (count($pages) <= 1) {
            return $this->paginate(implode('', $pages), $declared);
        }

        return new ExtractedText('', $pages, max($declared, count($pages)));
    }

    private function pptx(string $path): ExtractedText
    {
        $zip = $this->openZip($path);
        $slides = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                $slides[(int) $m[1]] = $name;
            }
        }
        ksort($slides);

        $pages = [];
        foreach ($slides as $number => $entry) {
            $dom = new DOMDocument;
            if (! @$dom->loadXML($this->zipEntry($zip, $entry), LIBXML_NONET | LIBXML_COMPACT)) {
                $pages[] = '';

                continue;
            }
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
            $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

            $lines = [];
            foreach ($xpath->query('//p:sp') as $shape) {
                $placeholder = (string) $xpath->evaluate('string(.//p:nvPr/p:ph/@type)', $shape);
                $isTitle = in_array($placeholder, ['title', 'ctrTitle'], true);
                foreach ($xpath->query('.//a:p', $shape) as $paragraph) {
                    $text = '';
                    foreach ($xpath->query('.//a:t', $paragraph) as $run) {
                        $text .= $run->textContent;
                    }
                    $text = trim($text);
                    if ($text === '') {
                        continue;
                    }
                    $lines[] = $isTitle ? '# '.$text : (str_word_count($text) <= 3 ? $text : '• '.$text);
                }
            }
            $pages[] = implode("\n\n", $lines);
        }
        $zip->close();

        return new ExtractedText('', $pages, count($pages));
    }

    /**
     * Legacy binary .doc — recover readable text runs (UTF-16 first, then
     * 8-bit) and drop the style/font names that live in the same file.
     */
    private function legacyDoc(string $path): ExtractedText
    {
        $data = (string) file_get_contents($path);
        $runs = [];

        if (preg_match_all('/(?:[\x20-\x7E\xA0-\xFF]\x00|\x0D\x00){20,}/s', $data, $m)) {
            foreach ($m[0] as $run) {
                $runs[] = mb_convert_encoding($run, 'UTF-8', 'UTF-16LE');
            }
        }
        if (Text::wordCount(implode(' ', $runs)) < 30 && preg_match_all('/[\x20-\x7E\x0D\x92-\x97]{20,}/', $data, $m)) {
            foreach ($m[0] as $run) {
                $runs[] = mb_convert_encoding($run, 'UTF-8', 'Windows-1252');
            }
        }

        $noise = '/(Times New Roman|Calibri|Arial|Symbol|Wingdings|Microsoft (Office )?Word|Normal\.dot|Default Paragraph Font|Table Normal|No List|Heading \d|Cambria|Courier New|Root Entry|SummaryInformation|DocumentSummaryInformation|CompObj|WordDocument|1Table|0Table|_PID_|HYPERLINK)/i';
        $paragraphs = [];
        foreach ($runs as $run) {
            foreach (preg_split('/\r+/', $run) ?: [] as $paragraph) {
                $paragraph = trim(preg_replace('/[^\P{C}\n]+/u', ' ', $paragraph) ?? '');
                if (Text::wordCount($paragraph) >= 3 && ! preg_match($noise, $paragraph)) {
                    $paragraphs[] = $paragraph;
                }
            }
        }

        $result = $this->paginate(implode("\n\n", array_unique($paragraphs)));

        return new ExtractedText('', $result->pages, $result->pageCount, [
            'Legacy .doc files are extracted approximately. Save the file as .docx for the most accurate text.',
        ]);
    }

    private function markdown(string $text): string
    {
        $text = preg_replace('/```[a-z]*\n?(.*?)```/su', '$1', $text) ?? $text;      // fenced code
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $text) ?? $text;          // images
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $text) ?? $text;       // links
        $text = preg_replace('/(\*\*|__|\*|_|`)(\S.*?\S|\S)\1/u', '$2', $text) ?? $text; // emphasis
        $text = preg_replace('/^\s*>\s?/mu', '', $text) ?? $text;                     // quotes
        $text = preg_replace('/^\s*[-*+]\s+/mu', '• ', $text) ?? $text;               // lists
        $text = preg_replace('/^\s*\|?\s*:?-{3,}.*$/mu', '', $text) ?? $text;          // table rules

        return $text;
    }

    private function readText(string $path): string
    {
        $raw = (string) file_get_contents($path);

        if (str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    /** Split unpaginated text into virtual pages on paragraph boundaries. */
    private function paginate(string $text, int $declaredPages = 0): ExtractedText
    {
        $pages = [];
        $current = '';
        $words = 0;
        foreach (preg_split('/\n{2,}/u', trim($text)) ?: [] as $paragraph) {
            $count = Text::wordCount($paragraph);
            if ($words > 0 && $words + $count > self::WORDS_PER_PAGE) {
                $pages[] = $current;
                $current = '';
                $words = 0;
            }
            $current .= $paragraph."\n\n";
            $words += $count;
        }
        if (trim($current) !== '') {
            $pages[] = $current;
        }

        return new ExtractedText('', $pages, max($declaredPages, count($pages)));
    }

    private function runText(DOMXPath $xpath, \DOMNode $node): string
    {
        $text = '';
        foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $node) as $part) {
            $text .= match ($part->localName) {
                't' => $part->textContent,
                'tab' => ' ',
                default => "\n",
            };
        }

        return $text;
    }

    private function openZip(string $path): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The file is not a valid Office document (corrupt ZIP container).');
        }

        return $zip;
    }

    private function zipEntry(ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            throw new RuntimeException("The document is missing its content ({$name}).");
        }
        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new RuntimeException('The document content is too large to process.');
        }

        return (string) $zip->getFromName($name);
    }
}
