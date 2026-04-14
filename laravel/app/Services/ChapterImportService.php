<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;
use DOMDocument;
use DOMXPath;

class ChapterImportService
{
    public function parseWorkbook(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Impossible d’ouvrir le classeur XLSX.');
        }

        try {
            $sharedStrings = $this->loadSharedStrings($zip);
            [$sheetPath, $sheetName] = $this->resolveSecondWorksheet($zip);
            $rows = $this->loadWorksheetRows($zip, $sheetPath, $sharedStrings);
        } finally {
            $zip->close();
        }

        return [
            'sheet_name' => $sheetName,
            'rows' => $rows,
        ];
    }

    public function buildPreview(array $worksheetRows): array
    {
        if (count($worksheetRows) < 2) {
            throw new RuntimeException('Le fichier doit contenir une ligne d’en-tête et au moins une ligne de données.');
        }

        $warnings = [];
        $errors = [];
        $header = array_slice($worksheetRows[0], 0, 4);
        $rows = [];
        $seenHierarchicalLevels = [];
        $seenRootDuplicates = [];

        foreach (array_slice($worksheetRows, 1) as $index => $rawRow) {
            $excelRow = $index + 2;
            $level = trim((string) ($rawRow[0] ?? ''));
            $label = trim((string) ($rawRow[1] ?? ''));
            $startSource = trim((string) ($rawRow[2] ?? ''));
            $startTarget = trim((string) ($rawRow[3] ?? ''));

            if ($level === '' && $label === '' && $startSource === '' && $startTarget === '') {
                continue;
            }

            if ($level === '') {
                $errors[] = "Ligne {$excelRow} : niveau manquant.";
                continue;
            }

            if (!preg_match('/^[^.]+(?:\.[^.]+)*$/', $level)) {
                $errors[] = "Ligne {$excelRow} : niveau invalide \"{$level}\".";
                continue;
            }

            $parentLevel = str_contains($level, '.') ? substr($level, 0, strrpos($level, '.')) : null;
            if ($parentLevel !== null) {
                if (isset($seenHierarchicalLevels[$level])) {
                    $errors[] = "Ligne {$excelRow} : niveau hiérarchique dupliqué \"{$level}\".";
                    continue;
                }
                if (!isset($seenHierarchicalLevels[$parentLevel]) && !isset($seenRootDuplicates[$parentLevel])) {
                    $errors[] = "Ligne {$excelRow} : parent introuvable pour le niveau \"{$level}\".";
                    continue;
                }
                $seenHierarchicalLevels[$level] = true;
            } else {
                if (isset($seenRootDuplicates[$level])) {
                    $warnings[] = "Ligne {$excelRow} : niveau racine \"{$level}\" répété, importé comme entrée sœur.";
                }
                $seenRootDuplicates[$level] = true;
                $seenHierarchicalLevels[$level] = true;
            }

            if ($label === '') {
                $warnings[] = "Ligne {$excelRow} : libellé vide.";
            }
            if ($startSource === '') {
                $warnings[] = "Ligne {$excelRow} : ancre source vide.";
            }
            if ($startTarget === '') {
                $warnings[] = "Ligne {$excelRow} : ancre cible vide.";
            }

            $rows[] = [
                'row_number' => $excelRow,
                'level' => $level,
                'label' => $label,
                'label_source' => $label,
                'label_target' => $label,
                'parent_level' => $parentLevel,
                'start_line_source' => $startSource,
                'start_line_target' => $startTarget,
                'id_tome_source' => 0,
                'id_tome_target' => 0,
            ];
        }

        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }

        if ($rows === []) {
            throw new RuntimeException('Aucune ligne de chapitre exploitable n’a été trouvée.');
        }

        return [
            'header' => $header,
            'rows' => $rows,
            'warnings' => $warnings,
            'summary' => [
                'count' => count($rows),
                'root_count' => count(array_filter($rows, fn (array $row) => $row['parent_level'] === null)),
            ],
        ];
    }

    private function resolveSecondWorksheet(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            throw new RuntimeException('Classeur XLSX incomplet.');
        }

        $workbook = new DOMDocument();
        $workbook->loadXML($workbookXml);
        $workbookXpath = new DOMXPath($workbook);
        $workbookXpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbookXpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $sheetNodes = $workbookXpath->query('//main:sheets/main:sheet');
        if ($sheetNodes === false || $sheetNodes->length < 2) {
            throw new RuntimeException('Le classeur doit contenir au moins deux feuilles.');
        }

        $secondSheet = $sheetNodes->item(1);
        $sheetName = $secondSheet?->attributes?->getNamedItem('name')?->nodeValue ?: 'Feuille 2';
        $relationshipId = $secondSheet?->attributes?->getNamedItemNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'id'
        )?->nodeValue;

        if (!$relationshipId) {
            throw new RuntimeException('Impossible de résoudre la deuxième feuille du classeur.');
        }

        $rels = new DOMDocument();
        $rels->loadXML($relsXml);
        $relsXpath = new DOMXPath($rels);
        $relsXpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $relation = $relsXpath->query(sprintf('//rel:Relationship[@Id="%s"]', $relationshipId))->item(0);
        $target = $relation?->attributes?->getNamedItem('Target')?->nodeValue;
        if (!$target) {
            throw new RuntimeException('Impossible de localiser la feuille XLSX cible.');
        }

        $sheetPath = 'xl/' . ltrim($target, '/');

        return [$sheetPath, $sheetName];
    }

    private function loadSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];
        foreach ($xpath->query('//main:si') as $node) {
            $textParts = [];
            foreach ($xpath->query('.//main:t', $node) as $textNode) {
                $textParts[] = $textNode->textContent;
            }
            $strings[] = implode('', $textParts);
        }

        return $strings;
    }

    private function loadWorksheetRows(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $xml = $zip->getFromName($sheetPath);
        if ($xml === false) {
            throw new RuntimeException('Feuille XLSX introuvable.');
        }

        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($xpath->query('//main:sheetData/main:row') as $rowNode) {
            $current = array_fill(0, 4, '');
            foreach ($xpath->query('./main:c', $rowNode) as $cell) {
                $reference = $cell->attributes?->getNamedItem('r')?->nodeValue ?: '';
                $columnIndex = $this->columnIndexFromCellReference($reference);
                if ($columnIndex < 1 || $columnIndex > 4) {
                    continue;
                }

                $type = $cell->attributes?->getNamedItem('t')?->nodeValue ?: '';
                $value = '';
                if ($type === 'inlineStr') {
                    foreach ($xpath->query('./main:is/main:t', $cell) as $textNode) {
                        $value .= $textNode->textContent;
                    }
                } else {
                    $rawValue = $xpath->evaluate('string(./main:v)', $cell);
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $rawValue] ?? '';
                    } else {
                        $value = (string) $rawValue;
                    }
                }

                $current[$columnIndex - 1] = $value;
            }

            $rows[] = $current;
        }

        return $rows;
    }

    private function columnIndexFromCellReference(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index;
    }
}
