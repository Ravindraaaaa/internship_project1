<?php
/**
 * A lightweight, native PHP class to parse CSV and XLSX files
 * without requiring Composer or heavy dependencies.
 */
class SimpleXLSXReader {
    public static function parse($filePath) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return self::parseCSV($filePath);
        } elseif ($ext === 'xlsx') {
            return self::parseXLSX($filePath);
        }
        return ['error' => 'Unsupported file format'];
    }

    private static function parseCSV($filePath) {
        $data = [];
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
                $data[] = $row;
            }
            fclose($handle);
        } else {
            return ['error' => 'Failed to open CSV file'];
        }
        return ['status' => 'success', 'data' => $data];
    }

    private static function parseXLSX($filePath) {
        if (!class_exists('ZipArchive')) {
            return ['error' => 'ZipArchive extension is required for XLSX parsing'];
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            return ['error' => 'Failed to open XLSX file'];
        }

        // 1. Read Shared Strings
        $sharedStrings = [];
        $ssData = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssData) {
            $xml = simplexml_load_string($ssData);
            if ($xml) {
                foreach ($xml->si as $val) {
                    if (isset($val->t)) {
                        $sharedStrings[] = (string)$val->t;
                    } elseif (isset($val->r)) {
                        $str = '';
                        foreach ($val->r as $r) {
                            $str .= (string)$r->t;
                        }
                        $sharedStrings[] = $str;
                    }
                }
            }
        }

        // 2. Read first worksheet
        // Typically xl/worksheets/sheet1.xml
        $sheetData = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetData) {
            // Try to find the exact name from relationships, but for simple reader, fallback to sheet1
            return ['error' => 'Could not find sheet1.xml in XLSX'];
        }

        $xml = simplexml_load_string($sheetData);
        $data = [];
        if ($xml && isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                $rowData = [];
                $cellIndex = 0;
                foreach ($row->c as $c) {
                    $rAttr = (string)$c['r'];
                    $colLetter = preg_replace('/[0-9]/', '', $rAttr);
                    $colIndex = self::colLetterToIndex($colLetter);
                    
                    // Fill empty cells
                    while ($cellIndex < $colIndex) {
                        $rowData[] = '';
                        $cellIndex++;
                    }

                    $v = (string)$c->v;
                    $t = (string)$c['t'];
                    if ($t == 's' && isset($sharedStrings[(int)$v])) {
                        $v = $sharedStrings[(int)$v];
                    }
                    $rowData[] = $v;
                    $cellIndex++;
                }
                $data[] = $rowData;
            }
        }
        $zip->close();
        
        return ['status' => 'success', 'data' => $data];
    }

    private static function colLetterToIndex($letter) {
        $index = 0;
        $letter = strtoupper($letter);
        $len = strlen($letter);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - 64);
        }
        return $index - 1; // 0-indexed
    }
}
