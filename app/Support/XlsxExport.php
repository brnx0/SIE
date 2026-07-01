<?php

namespace App\Support;

use Illuminate\Http\Response;
use ZipArchive;

/**
 * Gerador de planilha .xlsx (Office Open XML) sem dependência externa.
 *
 * Monta o pacote OOXML mínimo (ZipArchive + XML) que o Excel/LibreOffice abrem
 * sem aviso de "formato difere da extensão". Strings vão como `inlineStr`
 * (dispensa sharedStrings); valores numéricos viram célula numérica.
 *
 * Uso:
 *   return XlsxExport::download('arquivo.xlsx', ['Col A', 'Col B'], $rows);
 * onde $rows é um iterable de arrays de valores (mesma ordem dos headers).
 */
class XlsxExport
{
    /**
     * @param array<int,string>              $headers
     * @param iterable<int,array<int,mixed>> $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): Response
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');

        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($headers, $rows));

        $zip->close();

        $data = (string) file_get_contents($tmp);
        @unlink($tmp);

        return response($data, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Content-Length'      => (string) strlen($data),
        ]);
    }

    /** Converte índice 0-based em letra de coluna (0 => A, 26 => AA). */
    private static function col(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $r = ($i - 1) % 26;
            $s = chr(65 + $r) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    }

    private static function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** @param array<int,mixed> $cells */
    private static function row(int $rowNum, array $cells): string
    {
        $xml = '<row r="' . $rowNum . '">';
        $i = 0;
        foreach ($cells as $val) {
            $ref = self::col($i) . $rowNum;
            if (is_int($val) || is_float($val)) {
                $xml .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
            } else {
                $text = $val === null ? '' : (string) $val;
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                    . self::esc($text) . '</t></is></c>';
            }
            $i++;
        }
        return $xml . '</row>';
    }

    /**
     * @param array<int,string>              $headers
     * @param iterable<int,array<int,mixed>> $rows
     */
    private static function sheet(array $headers, iterable $rows): string
    {
        $body = self::row(1, $headers);
        $n = 2;
        foreach ($rows as $r) {
            $body .= self::row($n++, array_values($r));
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $body . '</sheetData>'
            . '</worksheet>';
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Planilha1" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }
}
