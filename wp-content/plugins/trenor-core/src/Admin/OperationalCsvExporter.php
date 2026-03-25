<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class OperationalCsvExporter
{
    /**
     * @param array<int, string> $headers
     * @param array<int, array<string, scalar|null>> $rows
     */
    public function build(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if (! is_resource($stream)) {
            return '';
        }

        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                if (! is_scalar($value) && $value !== null) {
                    $value = '';
                }
                $line[] = (string) ($value ?? '');
            }
            fputcsv($stream, $line);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return is_string($csv) ? $csv : '';
    }
}
