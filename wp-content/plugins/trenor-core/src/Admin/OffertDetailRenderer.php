<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class OffertDetailRenderer
{
    /**
     * @param array<string, mixed> $offert
     * @param array{
     *     header: array<string, mixed>,
     *     totals: array<string, mixed>,
     *     lines: array<int, mixed>,
     *     material_lines: array<int, mixed>,
     *     metadata: array<string, mixed>
     * } $snapshot
     */
    public function render(array $offert, array $snapshot): void
    {
        $header = $snapshot['header'];
        $totals = $snapshot['totals'];
        $lines = $snapshot['lines'];
        $materialLines = $snapshot['material_lines'];
        $metadata = $this->buildMetadata($snapshot['metadata'], $header, $offert);

        echo '<h2>Offert #' . esc_html((string) ($offert['id'] ?? '')) . ' (read-only)</h2>';

        echo '<h3>Document summary</h3>';
        $this->renderKeyValueTable([
            'id' => $offert['id'] ?? '',
            'document_number' => $offert['document_number'] ?? '',
            'version_no' => $offert['version_no'] ?? '',
            'status' => $offert['status'] ?? '',
            'estimate_id' => $offert['estimate_id'] ?? '',
            'issued_at' => $offert['issued_at'] ?? '',
            'currency' => $offert['currency'] ?? ($header['currency'] ?? ''),
        ]);

        echo '<h3>Metadata</h3>';
        if ($metadata === []) {
            echo '<p><em>No data.</em></p>';
        } else {
            $this->renderKeyValueTable($metadata);
        }

        echo '<h3>Estimate header summary</h3>';
        if ($header === []) {
            echo '<p><em>No data.</em></p>';
        } else {
            $this->renderKeyValueTable($header);
        }

        echo '<h3>Totals summary</h3>';
        if ($totals === []) {
            echo '<p><em>No data.</em></p>';
        } else {
            $this->renderTotalsTable($totals);
        }

        echo '<h3>Labour lines</h3>';
        if ($lines === []) {
            echo '<p><em>No lines.</em></p>';
        } else {
            $this->renderLabourLinesTable($lines);
        }

        echo '<h3>Material lines</h3>';
        if ($materialLines === []) {
            echo '<p><em>No lines.</em></p>';
        } else {
            $this->renderMaterialLinesTable($materialLines);
        }
    }

    /** @param array<string, mixed> $row */
    private function renderKeyValueTable(array $row): void
    {
        echo '<table class="widefat striped"><tbody>';
        foreach ($row as $label => $value) {
            echo '<tr><th style="width:220px;">' . esc_html((string) $label) . '</th><td>' . esc_html($this->toScalarString($value)) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<string, mixed> $totals */
    private function renderTotalsTable(array $totals): void
    {
        $keys = [
            'labour_total_minor',
            'materials_total_minor',
            'subtotal_ex_vat_minor',
            'vat_minor',
            'total_inc_vat_minor',
            'rot_eligible_labour_minor',
            'preliminary_rot_minor',
            'amount_before_rot_minor',
            'amount_after_preliminary_rot_minor',
        ];

        echo '<table class="widefat striped"><tbody>';
        foreach ($keys as $key) {
            echo '<tr><th style="width:220px;">' . esc_html($key) . '</th><td>' . esc_html($this->toScalarString($totals[$key] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, mixed> $lines */
    private function renderLabourLinesTable(array $lines): void
    {
        echo '<table class="widefat striped"><thead><tr><th>id</th><th>title</th><th>unit</th><th>quantity</th><th>hours</th><th>labour subtotal</th></tr></thead><tbody>';
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html($this->toScalarString($line['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html($this->lineTitle($line)) . '</td>';
            echo '<td>' . esc_html($this->toScalarString($line['unit'] ?? ($line['unit_code_snapshot'] ?? ''))) . '</td>';
            echo '<td>' . esc_html($this->toScalarString($line['quantity'] ?? '')) . '</td>';
            echo '<td>' . esc_html($this->toScalarString($line['hours'] ?? ($line['calculated_hours'] ?? ''))) . '</td>';
            echo '<td>' . esc_html($this->toScalarString($line['labour_subtotal_minor'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, mixed> $lines */
    private function renderMaterialLinesTable(array $lines): void
    {
        echo '<table class="widefat striped"><thead><tr><th>id</th><th>name</th><th>unit</th><th>quantity</th><th>subtotal</th></tr></thead><tbody>';
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html($this->toScalarString($line['id'] ?? '')) . '</td>';
            echo '<td>' . esc_html($this->materialName($line)) . '</td>';
            echo '<td>' . esc_html($this->toScalarString($line['unit'] ?? ($line['unit_code_snapshot'] ?? ''))) . '</td>';
            echo '<td>' . esc_html($this->toScalarString($line['quantity'] ?? '')) . '</td>';
            echo '<td>' . esc_html($this->toScalarString($line['subtotal_minor'] ?? ($line['materials_subtotal_minor'] ?? ''))) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $header
     * @param array<string, mixed> $offert
     * @return array<string, mixed>
     */
    private function buildMetadata(array $metadata, array $header, array $offert): array
    {
        $values = [
            'source_estimate_id' => $metadata['source_estimate_id'] ?? ($header['source_estimate_id'] ?? null),
            'source_estimate_title' => $metadata['source_estimate_title'] ?? ($header['source_estimate_title'] ?? null),
            'offert_version_no' => $metadata['offert_version_no'] ?? ($header['offert_version_no'] ?? ($offert['version_no'] ?? null)),
            'document_number' => $metadata['document_number'] ?? ($header['document_number'] ?? ($offert['document_number'] ?? null)),
            'issued_at_utc' => $metadata['issued_at_utc'] ?? ($header['issued_at_utc'] ?? ($offert['issued_at'] ?? null)),
        ];

        $filtered = [];
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /** @param array<string, mixed> $line */
    private function lineTitle(array $line): string
    {
        $candidates = [
            $line['title'] ?? null,
            $line['line_title_sv_snapshot'] ?? null,
            $line['line_title_ru_snapshot'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                return (string) $candidate;
            }
        }

        return '';
    }

    /** @param array<string, mixed> $line */
    private function materialName(array $line): string
    {
        $candidates = [
            $line['name'] ?? null,
            $line['material_name_sv_snapshot'] ?? null,
            $line['material_name_ru_snapshot'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                return (string) $candidate;
            }
        }

        return '';
    }

    private function toScalarString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
