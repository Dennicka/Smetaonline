<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class AvtalDetailRenderer
{
    /**
     * @param array<string, mixed> $avtal
     * @param array{header: array<string, mixed>, totals: array<string, mixed>, lines: array<int, mixed>, material_lines: array<int, mixed>, metadata: array<string, mixed>} $snapshot
     * @param array{source_offert?: array<string, mixed>, source_estimate?: array<string, mixed>, project?: array<string, mixed>, property?: array<string, mixed>, client?: array<string, mixed>, document_profile?: array<string, mixed>} $context
     */
    public function render(array $avtal, array $snapshot, array $context = []): void
    {
        $header = is_array($snapshot['header'] ?? null) ? $snapshot['header'] : [];
        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
        $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
        $sourceOffert = is_array($context['source_offert'] ?? null) ? $context['source_offert'] : [];
        $sourceEstimate = is_array($context['source_estimate'] ?? null) ? $context['source_estimate'] : [];
        $project = is_array($context['project'] ?? null) ? $context['project'] : [];
        $property = is_array($context['property'] ?? null) ? $context['property'] : [];
        $client = is_array($context['client'] ?? null) ? $context['client'] : [];
        $profile = is_array($context['document_profile'] ?? null) ? $context['document_profile'] : [];
        $currency = (string) ($avtal['currency'] ?? ($header['currency'] ?? 'SEK'));

        echo '<h2>Avtal / Agreement</h2>';
        echo '<p>Agreement artifact synchronized with source offert snapshot and numbering lineage.</p>';

        echo '<h3>1. Document identity</h3>';
        $this->renderKeyValueTable([
            'Document number' => $this->first([$avtal['document_number'] ?? null, $metadata['document_number'] ?? null]),
            'Version' => $this->first([$avtal['version_no'] ?? null, $metadata['avtal_version_no'] ?? null]),
            'Status' => $avtal['status'] ?? '',
            'Issued at' => $this->first([$avtal['issued_at'] ?? null, $metadata['issued_at_utc'] ?? null]),
            'Currency' => $currency,
        ]);

        echo '<h3>2. Source references</h3>';
        $this->renderKeyValueTable([
            'Source offert id' => $this->first([$avtal['offert_id'] ?? null, $metadata['source_offert_id'] ?? null, $sourceOffert['id'] ?? null]),
            'Source offert document number' => $this->first([$metadata['source_offert_document_number'] ?? null, $sourceOffert['document_number'] ?? null]),
            'Source estimate id' => $this->first([$avtal['estimate_id'] ?? null, $metadata['source_estimate_id'] ?? null, $sourceEstimate['id'] ?? null]),
            'Source estimate title' => $this->first([$metadata['source_estimate_title'] ?? null, $sourceEstimate['title'] ?? null]),
            'Project' => $project['name'] ?? '',
            'Property' => $property['name'] ?? '',
            'Client' => $this->first([$avtal['client_name'] ?? null, $client['name'] ?? null]),
        ]);

        echo '<h3>3. Totals and tax summary</h3>';
        $this->renderKeyValueTable([
            'Subtotal excl. VAT' => $this->formatMinorMoney($totals['subtotal_ex_vat_minor'] ?? null, $currency),
            'VAT' => $this->formatMinorMoney($totals['vat_minor'] ?? null, $currency),
            'Total incl. VAT' => $this->formatMinorMoney($totals['total_inc_vat_minor'] ?? ($avtal['total_inc_vat_minor'] ?? null), $currency),
            'Tax mode' => (string) ($avtal['tax_mode'] ?? ($header['tax_mode'] ?? '')),
            'VAT rate (%)' => (string) ($avtal['vat_rate_percent'] ?? ($header['vat_rate_percent'] ?? '')),
        ]);

        echo '<h3>4. Agreement terms</h3>';
        $this->renderKeyValueTable([
            'Offert note' => $profile['offert_note'] ?? '',
            'Offert valid days baseline' => $this->scalarToString($profile['offert_valid_days'] ?? ''),
            'Seller company' => $profile['company_name'] ?? '',
            'Seller org/VAT' => trim((string) (($profile['org_number'] ?? '') . ' ' . ($profile['vat_number'] ?? ''))),
        ]);
    }

    /** @param array<string, mixed> $rows */
    private function renderKeyValueTable(array $rows): void
    {
        echo '<table class="widefat striped"><tbody>';
        foreach ($rows as $label => $value) {
            $normalized = $this->scalarToString($value);
            if ($normalized === '') {
                continue;
            }
            echo '<tr><th style="width:260px;">' . esc_html((string) $label) . '</th><td>' . esc_html($normalized) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function formatMinorMoney(mixed $minor, string $currency): string
    {
        if (! is_numeric($minor)) {
            return '';
        }

        return number_format(((float) $minor) / 100, 2, '.', ' ') . ' ' . strtoupper($currency !== '' ? $currency : 'SEK');
    }

    /** @param array<int, mixed> $values */
    private function first(array $values): string
    {
        foreach ($values as $value) {
            $normalized = $this->scalarToString($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function scalarToString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
