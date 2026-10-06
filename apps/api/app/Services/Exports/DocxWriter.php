<?php

namespace App\Services\Exports;

use ZipArchive;

/**
 * Minimal, dependency-free Word (.docx) writer for summaries and chat
 * transcripts: headings, paragraphs, bullet points and bold/italic runs.
 */
class DocxWriter
{
    /** @var list<string> */
    private array $body = [];

    public function heading(string $text, int $level = 1): static
    {
        $this->body[] = '<w:p><w:pPr><w:pStyle w:val="Heading'.max(1, min(3, $level)).'"/></w:pPr>'.$this->run($text).'</w:p>';

        return $this;
    }

    public function paragraph(string $text, bool $italic = false, string $color = ''): static
    {
        foreach (preg_split('/\n{2,}/u', trim($text)) ?: [] as $paragraph) {
            $this->body[] = '<w:p>'.$this->run(trim($paragraph), italic: $italic, color: $color).'</w:p>';
        }

        return $this;
    }

    /** "**Term** — definition" style line: bold lead + normal text. */
    public function labelled(string $label, string $text): static
    {
        $this->body[] = '<w:p>'.$this->run($label, bold: true).$this->run(' '.$text).'</w:p>';

        return $this;
    }

    public function bullet(string $text): static
    {
        $this->body[] = '<w:p><w:pPr><w:pStyle w:val="ListBullet"/></w:pPr>'.$this->run('• '.$text).'</w:p>';

        return $this;
    }

    public function toBinary(string $title): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.$this->escape($title).'</dc:title><dc:creator>EDU-SMART</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>');
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .implode('', $this->body)
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1200" w:right="1200" w:bottom="1200" w:left="1200" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>');
        $zip->close();

        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    private function run(string $text, bool $bold = false, bool $italic = false, string $color = ''): string
    {
        $props = ($bold ? '<w:b/>' : '').($italic ? '<w:i/>' : '').($color ? '<w:color w:val="'.$color.'"/>' : '');

        return '<w:r>'.($props ? '<w:rPr>'.$props.'</w:rPr>' : '').'<w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r>';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function styles(): string
    {
        $heading = fn (int $level, int $size, string $color) => '<w:style w:type="paragraph" w:styleId="Heading'.$level.'"><w:name w:val="heading '.$level.'"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="'.($level - 1).'"/></w:pPr><w:rPr><w:b/><w:color w:val="'.$color.'"/><w:sz w:val="'.$size.'"/></w:rPr></w:style>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            .$heading(1, 36, '4F46E5').$heading(2, 28, '1E293B').$heading(3, 24, '334155')
            .'<w:style w:type="paragraph" w:styleId="ListBullet"><w:name w:val="List Bullet"/><w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="360"/><w:spacing w:after="60"/></w:pPr></w:style>'
            .'</w:styles>';
    }
}
