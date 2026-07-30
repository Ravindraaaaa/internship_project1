<?php
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class SimplePDFReader {
    public static function parse($filePath) {
        if (!class_exists('Smalot\PdfParser\Parser')) {
            return ['error' => 'PDF Parser library is not installed or loaded.'];
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            
            if (strlen(trim($text)) < 15) {
                return ['error' => 'No extractable text found in PDF. This appears to be a scanned or image-based PDF. OCR is required. Please upload a text-based PDF or CSV/XLSX.'];
            }

            // Try to parse the text as CSV/TSV format
            $lines = explode("\n", $text);
            $data = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Assume comma separated if commas exist, else try tabs, else fallback
                if (strpos($line, ',') !== false) {
                    $data[] = str_getcsv($line, ',');
                } else if (strpos($line, "\t") !== false) {
                    $data[] = str_getcsv($line, "\t");
                } else {
                    // Just split by multiple spaces as a fallback
                    $row = preg_split('/\s{2,}/', $line);
                    $data[] = $row;
                }
            }

            return ['status' => 'success', 'data' => $data];
        } catch (Exception $e) {
            return ['error' => 'Failed to parse PDF: ' . $e->getMessage()];
        }
    }
}
