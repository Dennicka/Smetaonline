<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class RealPdfGenerator
{
    /** @param array<int, string> $lines */
    public function generate(array $lines): string
    {
        $content = "BT\n/F1 11 Tf\n14 TL\n50 790 Td\n";

        foreach ($lines as $line) {
            $normalized = $this->escapeText($line);
            $content .= '(' . $normalized . ") Tj\nT*\n";
        }

        $content .= "ET\n";
        $contentLength = strlen($content);

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length {$contentLength} >>\nstream\n{$content}endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapeText(string $text): string
    {
        $trimmed = trim($text);
        $singleLine = str_replace(["\r\n", "\n", "\r"], ' ', $trimmed);
        $withoutControl = preg_replace('/[^[:print:]\t]/u', '', $singleLine);
        if (! is_string($withoutControl)) {
            $withoutControl = $singleLine;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $withoutControl);
    }
}
