<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class InvoicePrintViewModel
{
    /**
     * @param array<string, mixed> $invoice
     * @param array{
     *     header: array<string, mixed>,
     *     totals: array<string, mixed>,
     *     lines: array<int, mixed>,
     *     material_lines: array<int, mixed>,
     *     metadata: array<string, mixed>
     * } $snapshot
     * @param array{
     *     source_offert?: array<string, mixed>,
     *     source_estimate?: array<string, mixed>,
     *     project?: array<string, mixed>,
     *     property?: array<string, mixed>,
     *     client?: array<string, mixed>,
     *     payments?: array<int, mixed>,
     *     payment_summary?: array<string, mixed>
     * } $context
     * @return array{
     *     document: array<string, string>,
     *     context: array<string, string>,
     *     totals: array<int, array{label: string, minor: string}>,
     *     labour_lines: array<int, array{title: string, unit: string, quantity: string, hours: string, subtotal_minor: string}>,
     *     material_lines: array<int, array{name: string, unit: string, quantity: string, subtotal_minor: string}>,
     *     payment_summary: array<string, string>,
     *     payments: array<int, array{payment_date: string, amount_minor: string, currency: string, method: string, reference: string, note: string}>,
     *     currency: string
     * }
     */
    public function build(array $invoice, array $snapshot, array $context = []): array
    {
        $header = $snapshot['header'];
        $totals = $snapshot['totals'];
        $metadata = $snapshot['metadata'];

        $sourceOffert = $this->normalizeMap($context['source_offert'] ?? null);
        $sourceEstimate = $this->normalizeMap($context['source_estimate'] ?? null);
        $project = $this->normalizeMap($context['project'] ?? null);
        $property = $this->normalizeMap($context['property'] ?? null);
        $client = $this->normalizeMap($context['client'] ?? null);
        $paymentSummary = $this->normalizeMap($context['payment_summary'] ?? null);

        $currency = $this->firstScalarString([
            $invoice['currency'] ?? null,
            $header['currency'] ?? null,
            $sourceEstimate['currency'] ?? null,
        ]);

        return [
            'document' => $this->buildDocumentSection($invoice, $header, $metadata, $currency),
            'context' => $this->buildContextSection($invoice, $header, $metadata, $sourceOffert, $sourceEstimate, $project, $property, $client),
            'totals' => $this->buildTotalsSection($totals, $invoice),
            'labour_lines' => $this->buildLabourLines($snapshot['lines']),
            'material_lines' => $this->buildMaterialLines($snapshot['material_lines']),
            'payment_summary' => $this->buildPaymentSummarySection($invoice, $paymentSummary),
            'payments' => $this->buildPaymentRows($context['payments'] ?? [], $currency),
            'currency' => $currency,
        ];
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<string, mixed> $header
     * @param array<string, mixed> $metadata
     * @return array<string, string>
     */
    private function buildDocumentSection(array $invoice, array $header, array $metadata, string $currency): array
    {
        return [
            'document_number' => $this->firstScalarString([
                $invoice['document_number'] ?? null,
                $metadata['document_number'] ?? null,
                $header['document_number'] ?? null,
            ]),
            'version_no' => $this->firstScalarString([
                $invoice['version_no'] ?? null,
                $metadata['invoice_version_no'] ?? null,
                $header['invoice_version_no'] ?? null,
            ]),
            'status' => $this->firstScalarString([$invoice['status'] ?? null]),
            'issued_at' => $this->firstScalarString([
                $invoice['issued_at'] ?? null,
                $metadata['issued_at_utc'] ?? null,
                $header['issued_at_utc'] ?? null,
            ]),
            'currency' => $currency,
            'vat_rate_percent' => $this->firstScalarString([
                $invoice['vat_rate_percent'] ?? null,
                $header['vat_rate_percent'] ?? null,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<string, mixed> $header
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $sourceOffert
     * @param array<string, mixed> $sourceEstimate
     * @param array<string, mixed> $project
     * @param array<string, mixed> $property
     * @param array<string, mixed> $client
     * @return array<string, string>
     */
    private function buildContextSection(
        array $invoice,
        array $header,
        array $metadata,
        array $sourceOffert,
        array $sourceEstimate,
        array $project,
        array $property,
        array $client
    ): array {
        return [
            'source_offert_id' => $this->firstScalarString([
                $invoice['offert_id'] ?? null,
                $metadata['source_offert_id'] ?? null,
                $header['source_offert_id'] ?? null,
                $sourceOffert['id'] ?? null,
            ]),
            'source_estimate_id' => $this->firstScalarString([
                $invoice['estimate_id'] ?? null,
                $metadata['source_estimate_id'] ?? null,
                $header['source_estimate_id'] ?? null,
                $sourceEstimate['id'] ?? null,
            ]),
            'source_title' => $this->firstScalarString([
                $metadata['source_estimate_title'] ?? null,
                $metadata['source_offert_document_number'] ?? null,
                $header['source_estimate_title'] ?? null,
                $header['title'] ?? null,
                $sourceEstimate['title'] ?? null,
                $sourceOffert['document_number'] ?? null,
            ]),
            'project_name' => $this->firstScalarString([$project['name'] ?? null]),
            'project_code' => $this->firstScalarString([$project['code'] ?? null]),
            'property_name' => $this->firstScalarString([$property['name'] ?? null]),
            'property_address' => $this->firstScalarString([$property['address_line'] ?? null]),
            'property_city' => $this->firstScalarString([$property['city'] ?? null]),
            'property_postal_code' => $this->firstScalarString([$property['postal_code'] ?? null]),
            'client_name' => $this->firstScalarString([$client['name'] ?? null]),
            'client_org_number' => $this->firstScalarString([$client['org_number'] ?? null]),
            'client_email' => $this->firstScalarString([$client['email'] ?? null]),
            'client_phone' => $this->firstScalarString([$client['phone'] ?? null]),
        ];
    }

    /**
     * @param array<string, mixed> $totals
     * @param array<string, mixed> $invoice
     * @return array<int, array{label: string, minor: string}>
     */
    private function buildTotalsSection(array $totals, array $invoice): array
    {
        $values = [
            'labour_total_minor' => $totals['labour_total_minor'] ?? null,
            'materials_total_minor' => $totals['materials_total_minor'] ?? null,
            'subtotal_ex_vat_minor' => $totals['subtotal_ex_vat_minor'] ?? null,
            'vat_minor' => $totals['vat_minor'] ?? null,
            'total_inc_vat_minor' => $totals['total_inc_vat_minor'] ?? ($invoice['total_inc_vat_minor'] ?? null),
        ];

        $rows = [];
        foreach ($values as $label => $value) {
            $rows[] = [
                'label' => $label,
                'minor' => $this->toScalarString($value),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, mixed> $lines
     * @return array<int, array{title: string, unit: string, quantity: string, hours: string, subtotal_minor: string}>
     */
    private function buildLabourLines(array $lines): array
    {
        $rows = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $rows[] = [
                'title' => $this->firstScalarString([
                    $line['title'] ?? null,
                    $line['line_title_sv_snapshot'] ?? null,
                    $line['line_title_ru_snapshot'] ?? null,
                ]),
                'unit' => $this->firstScalarString([
                    $line['unit'] ?? null,
                    $line['unit_code_snapshot'] ?? null,
                ]),
                'quantity' => $this->toScalarString($line['quantity'] ?? null),
                'hours' => $this->toScalarString($line['hours'] ?? ($line['calculated_hours'] ?? null)),
                'subtotal_minor' => $this->toScalarString($line['labour_subtotal_minor'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, mixed> $lines
     * @return array<int, array{name: string, unit: string, quantity: string, subtotal_minor: string}>
     */
    private function buildMaterialLines(array $lines): array
    {
        $rows = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $rows[] = [
                'name' => $this->firstScalarString([
                    $line['name'] ?? null,
                    $line['material_name_sv_snapshot'] ?? null,
                    $line['material_name_ru_snapshot'] ?? null,
                ]),
                'unit' => $this->firstScalarString([
                    $line['unit'] ?? null,
                    $line['unit_code_snapshot'] ?? null,
                ]),
                'quantity' => $this->toScalarString($line['quantity'] ?? null),
                'subtotal_minor' => $this->toScalarString($line['subtotal_minor'] ?? ($line['materials_subtotal_minor'] ?? null)),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<string, mixed> $paymentSummary
     * @return array<string, string>
     */
    private function buildPaymentSummarySection(array $invoice, array $paymentSummary): array
    {
        return [
            'invoice_total_minor' => $this->firstScalarString([
                $paymentSummary['invoice_total_minor'] ?? null,
                $invoice['total_inc_vat_minor'] ?? null,
            ]),
            'paid_total_minor' => $this->toScalarString($paymentSummary['paid_total_minor'] ?? null),
            'outstanding_minor' => $this->toScalarString($paymentSummary['outstanding_minor'] ?? null),
            'payment_count' => $this->toScalarString($paymentSummary['payment_count'] ?? null),
            'computed_status' => $this->firstScalarString([$paymentSummary['computed_status'] ?? null]),
        ];
    }

    /**
     * @param mixed $payments
     * @return array<int, array{payment_date: string, amount_minor: string, currency: string, method: string, reference: string, note: string}>
     */
    private function buildPaymentRows(mixed $payments, string $currency): array
    {
        if (! is_array($payments)) {
            return [];
        }

        $rows = [];
        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $rows[] = [
                'payment_date' => $this->toScalarString($payment['payment_date'] ?? null),
                'amount_minor' => $this->toScalarString($payment['amount_minor'] ?? null),
                'currency' => $this->firstScalarString([$payment['currency'] ?? null, $currency]),
                'method' => $this->toScalarString($payment['method'] ?? null),
                'reference' => $this->toScalarString($payment['reference'] ?? null),
                'note' => $this->toScalarString($payment['note'] ?? null),
            ];
        }

        return $rows;
    }

    /** @param array<int, mixed> $candidates */
    private function firstScalarString(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = $this->toScalarString($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function toScalarString(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /** @return array<string, mixed> */
    private function normalizeMap(mixed $value): array
    {
        return is_array($value) && array_is_list($value) === false ? $value : [];
    }
}
