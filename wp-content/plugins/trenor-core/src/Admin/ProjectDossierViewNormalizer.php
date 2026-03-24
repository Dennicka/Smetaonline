<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class ProjectDossierViewNormalizer
{
    /**
     * @param array<string, mixed> $dossier
     * @return array<string, mixed>
     */
    public function normalize(array $dossier): array
    {
        return [
            'project' => $this->normalizeProjectRow($this->toArray($dossier['project'] ?? [])),
            'property' => $this->normalizePropertyRow($this->toArray($dossier['property'] ?? [])),
            'client' => $this->normalizeClientRow($this->toArray($dossier['client'] ?? [])),
            'estimates' => $this->normalizeEstimateRows($dossier['estimates'] ?? []),
            'offerts' => $this->normalizeOffertRows($dossier['offerts'] ?? []),
            'atas' => $this->normalizeAtaRows($dossier['atas'] ?? []),
            'invoices' => $this->normalizeInvoiceRows($dossier['invoices'] ?? []),
            'payments' => $this->normalizePaymentRows($dossier['payments'] ?? []),
            'summary' => $this->normalizeSummaryRow($this->toArray($dossier['summary'] ?? [])),
        ];
    }

    /** @param array<string, mixed> $row */
    private function normalizeProjectRow(array $row): array
    {
        return [
            'id' => $this->scalar($row['id'] ?? null),
            'name' => $this->scalar($row['name'] ?? null),
            'code' => $this->scalar($row['code'] ?? null),
            'property_id' => $this->scalar($row['property_id'] ?? null),
        ];
    }

    /** @param array<string, mixed> $row */
    private function normalizePropertyRow(array $row): array
    {
        return [
            'id' => $this->scalar($row['id'] ?? null),
            'name' => $this->scalar($row['name'] ?? null),
            'address_line' => $this->scalar($row['address_line'] ?? null),
            'city' => $this->scalar($row['city'] ?? null),
            'postal_code' => $this->scalar($row['postal_code'] ?? null),
            'client_id' => $this->scalar($row['client_id'] ?? null),
        ];
    }

    /** @param array<string, mixed> $row */
    private function normalizeClientRow(array $row): array
    {
        return [
            'id' => $this->scalar($row['id'] ?? null),
            'name' => $this->scalar($row['name'] ?? null),
            'org_number' => $this->scalar($row['org_number'] ?? null),
            'email' => $this->scalar($row['email'] ?? null),
            'phone' => $this->scalar($row['phone'] ?? null),
        ];
    }

    /** @param mixed $rows */
    private function normalizeEstimateRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $item = $this->toArray($row);
            $normalized[] = [
                'id' => $this->scalar($item['id'] ?? null),
                'title' => $this->scalar($item['title'] ?? null),
                'status' => $this->scalar($item['status'] ?? null),
                'currency' => $this->scalar($item['currency'] ?? null),
                'vat_rate_percent' => $this->scalar($item['vat_rate_percent'] ?? null),
                'labour_rate_minor' => $this->scalar($item['labour_rate_minor'] ?? null),
                'calculated_at' => $this->scalar($item['calculated_at'] ?? null),
            ];
        }

        return $normalized;
    }

    /** @param mixed $rows */
    private function normalizeOffertRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $item = $this->toArray($row);
            $normalized[] = [
                'id' => $this->scalar($item['id'] ?? null),
                'estimate_id' => $this->scalar($item['estimate_id'] ?? null),
                'document_number' => $this->scalar($item['document_number'] ?? null),
                'version_no' => $this->scalar($item['version_no'] ?? null),
                'status' => $this->scalar($item['status'] ?? null),
                'total_inc_vat_minor' => $this->toInt($item['total_inc_vat_minor'] ?? null),
                'issued_at' => $this->scalar($item['issued_at'] ?? null),
            ];
        }

        return $normalized;
    }

    /** @param mixed $rows */
    private function normalizeInvoiceRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $item = $this->toArray($row);
            $normalized[] = [
                'id' => $this->scalar($item['id'] ?? null),
                'offert_id' => $this->scalar($item['offert_id'] ?? null),
                'estimate_id' => $this->scalar($item['estimate_id'] ?? null),
                'document_number' => $this->scalar($item['document_number'] ?? null),
                'version_no' => $this->scalar($item['version_no'] ?? null),
                'status' => $this->scalar($item['status'] ?? null),
                'total_inc_vat_minor' => $this->toInt($item['total_inc_vat_minor'] ?? null),
                'issued_at' => $this->scalar($item['issued_at'] ?? null),
                'paid_total_minor' => $this->toInt($item['paid_total_minor'] ?? null),
                'outstanding_minor' => $this->toInt($item['outstanding_minor'] ?? null),
                'payment_count' => $this->toInt($item['payment_count'] ?? null),
                'computed_status' => $this->scalar($item['computed_status'] ?? null),
            ];
        }

        return $normalized;
    }

    /** @param mixed $rows */
    private function normalizeAtaRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $item = $this->toArray($row);
            $normalized[] = [
                'id' => $this->scalar($item['id'] ?? null),
                'project_id' => $this->scalar($item['project_id'] ?? null),
                'document_number' => $this->scalar($item['document_number'] ?? null),
                'version_no' => $this->scalar($item['version_no'] ?? null),
                'status' => $this->scalar($item['status'] ?? null),
                'invoice_link_status' => $this->scalar($item['invoice_link_status'] ?? null),
                'total_inc_vat_minor' => $this->toInt($item['total_inc_vat_minor'] ?? null),
                'issued_at' => $this->scalar($item['issued_at'] ?? null),
                'approved_at' => $this->scalar($item['approved_at'] ?? null),
            ];
        }

        return $normalized;
    }

    /** @param mixed $rows */
    private function normalizePaymentRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $item = $this->toArray($row);
            $normalized[] = [
                'id' => $this->scalar($item['id'] ?? null),
                'invoice_id' => $this->scalar($item['invoice_id'] ?? null),
                'payment_date' => $this->scalar($item['payment_date'] ?? null),
                'amount_minor' => $this->toInt($item['amount_minor'] ?? null),
                'currency' => $this->scalar($item['currency'] ?? null),
                'method' => $this->scalar($item['method'] ?? null),
                'reference' => $this->scalar($item['reference'] ?? null),
                'created_at' => $this->scalar($item['created_at'] ?? null),
            ];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $row */
    private function normalizeSummaryRow(array $row): array
    {
        return [
            'estimates_count' => $this->toInt($row['estimates_count'] ?? null),
            'offerts_count' => $this->toInt($row['offerts_count'] ?? null),
            'atas_count' => $this->toInt($row['atas_count'] ?? null),
            'invoices_count' => $this->toInt($row['invoices_count'] ?? null),
            'payments_count' => $this->toInt($row['payments_count'] ?? null),
            'invoiced_total_minor' => $this->toInt($row['invoiced_total_minor'] ?? null),
            'paid_total_minor' => $this->toInt($row['paid_total_minor'] ?? null),
            'outstanding_total_minor' => $this->toInt($row['outstanding_total_minor'] ?? null),
            'fully_paid_invoices_count' => $this->toInt($row['fully_paid_invoices_count'] ?? null),
            'partially_paid_invoices_count' => $this->toInt($row['partially_paid_invoices_count'] ?? null),
            'archived_invoices_count' => $this->toInt($row['archived_invoices_count'] ?? null),
        ];
    }

    private function scalar(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return (string) $value;
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) round((float) $value);
        }

        return 0;
    }

    /** @return array<string, mixed> */
    private function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
