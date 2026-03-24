<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class CreditNotePrintViewModel
{
    /**
     * @param array<string, mixed> $creditNote
     * @param array{header: array<string, mixed>, totals: array<string, mixed>, lines: array<int, mixed>, material_lines: array<int, mixed>, metadata: array<string, mixed>} $snapshot
     * @param array{source_invoice?: array<string, mixed>, source_offert?: array<string, mixed>, source_estimate?: array<string, mixed>, project?: array<string, mixed>, property?: array<string, mixed>, client?: array<string, mixed>, document_settings?: array<string, mixed>} $context
     * @return array{document: array<string, string>, context: array<string, string>, totals: array<int, array{label: string, minor: string}>, labour_lines: array<int, array{title: string, unit: string, quantity: string, hours: string, subtotal_minor: string}>, material_lines: array<int, array{name: string, unit: string, quantity: string, subtotal_minor: string}>, issuer: array<string, string>, payment_details: array<string, string>, terms_notes: array<string, string>, currency: string}
     */
    public function build(array $creditNote, array $snapshot, array $context = []): array
    {
        $header = $snapshot['header'];
        $totals = $snapshot['totals'];
        $metadata = $snapshot['metadata'];
        $sourceInvoice = $this->normalizeMap($context['source_invoice'] ?? null);
        $sourceOffert = $this->normalizeMap($context['source_offert'] ?? null);
        $sourceEstimate = $this->normalizeMap($context['source_estimate'] ?? null);
        $project = $this->normalizeMap($context['project'] ?? null);
        $property = $this->normalizeMap($context['property'] ?? null);
        $client = $this->normalizeMap($context['client'] ?? null);
        $settings = $this->normalizeMap($context['document_settings'] ?? null);

        $currency = $this->firstScalarString([
            $creditNote['currency'] ?? null,
            $sourceInvoice['currency'] ?? null,
            $header['currency'] ?? null,
            $sourceEstimate['currency'] ?? null,
        ]);

        return [
            'document' => [
                'document_number' => $this->firstScalarString([$creditNote['document_number'] ?? null, $metadata['document_number'] ?? null]),
                'version_no' => $this->firstScalarString([$creditNote['version_no'] ?? null, $metadata['credit_note_version_no'] ?? null]),
                'status' => $this->firstScalarString([$creditNote['status'] ?? null]),
                'issued_at' => $this->firstScalarString([$creditNote['issued_at'] ?? null, $metadata['issued_at_utc'] ?? null]),
                'currency' => $currency,
                'vat_rate_percent' => $this->firstScalarString([$creditNote['vat_rate_percent'] ?? null, $header['vat_rate_percent'] ?? null]),
            ],
            'context' => [
                'source_invoice_id' => $this->firstScalarString([$creditNote['invoice_id'] ?? null, $sourceInvoice['id'] ?? null]),
                'source_invoice_document_number' => $this->firstScalarString([$metadata['source_invoice_document_number'] ?? null, $sourceInvoice['document_number'] ?? null]),
                'source_offert_id' => $this->firstScalarString([$creditNote['offert_id'] ?? null, $sourceOffert['id'] ?? null]),
                'source_estimate_id' => $this->firstScalarString([$creditNote['estimate_id'] ?? null, $sourceEstimate['id'] ?? null]),
                'source_estimate_title' => $this->firstScalarString([$metadata['source_estimate_title'] ?? null, $sourceEstimate['title'] ?? null]),
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
            ],
            'totals' => $this->buildTotals($totals, $creditNote),
            'labour_lines' => $this->buildLabourLines($snapshot['lines']),
            'material_lines' => $this->buildMaterialLines($snapshot['material_lines']),
            'issuer' => $this->buildIssuerSection($settings),
            'payment_details' => $this->buildPaymentDetailsSection($settings),
            'terms_notes' => $this->buildTermsNotesSection($settings),
            'currency' => $currency,
        ];
    }

    /** @param array<string, mixed> $totals @param array<string, mixed> $creditNote @return array<int, array{label: string, minor: string}> */
    private function buildTotals(array $totals, array $creditNote): array
    {
        $values = [
            'labour_total_minor' => $totals['labour_total_minor'] ?? ($creditNote['labour_total_minor'] ?? null),
            'materials_total_minor' => $totals['materials_total_minor'] ?? ($creditNote['materials_total_minor'] ?? null),
            'subtotal_ex_vat_minor' => $totals['subtotal_ex_vat_minor'] ?? ($creditNote['subtotal_ex_vat_minor'] ?? null),
            'vat_minor' => $totals['vat_minor'] ?? ($creditNote['vat_minor'] ?? null),
            'total_inc_vat_minor' => $totals['total_inc_vat_minor'] ?? ($creditNote['total_inc_vat_minor'] ?? null),
        ];

        $rows = [];
        foreach ($values as $label => $value) {
            $rows[] = ['label' => $label, 'minor' => $this->toScalarString($value)];
        }

        return $rows;
    }

    /** @param array<int, mixed> $lines @return array<int, array{title: string, unit: string, quantity: string, hours: string, subtotal_minor: string}> */
    private function buildLabourLines(array $lines): array
    {
        $rows = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $rows[] = [
                'title' => $this->firstScalarString([$line['title'] ?? null, $line['line_title_sv_snapshot'] ?? null, $line['line_title_ru_snapshot'] ?? null]),
                'unit' => $this->firstScalarString([$line['unit'] ?? null, $line['unit_code_snapshot'] ?? null]),
                'quantity' => $this->toScalarString($line['quantity'] ?? null),
                'hours' => $this->toScalarString($line['hours'] ?? ($line['calculated_hours'] ?? null)),
                'subtotal_minor' => $this->toScalarString($line['labour_subtotal_minor'] ?? null),
            ];
        }

        return $rows;
    }

    /** @param array<int, mixed> $lines @return array<int, array{name: string, unit: string, quantity: string, subtotal_minor: string}> */
    private function buildMaterialLines(array $lines): array
    {
        $rows = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $rows[] = [
                'name' => $this->firstScalarString([$line['name'] ?? null, $line['material_name_sv_snapshot'] ?? null, $line['material_name_ru_snapshot'] ?? null]),
                'unit' => $this->firstScalarString([$line['unit'] ?? null, $line['unit_code_snapshot'] ?? null]),
                'quantity' => $this->toScalarString($line['quantity'] ?? null),
                'subtotal_minor' => $this->toScalarString($line['subtotal_minor'] ?? ($line['materials_subtotal_minor'] ?? null)),
            ];
        }

        return $rows;
    }


    /** @param array<string, mixed> $settings @return array<string, string> */
    private function buildIssuerSection(array $settings): array
    {
        return [
            'company_name' => $this->firstScalarString([$settings['company_name'] ?? null]),
            'company_legal_name' => $this->firstScalarString([$settings['company_legal_name'] ?? null]),
            'org_number' => $this->firstScalarString([$settings['org_number'] ?? null]),
            'vat_number' => $this->firstScalarString([$settings['vat_number'] ?? null]),
            'address_line_1' => $this->firstScalarString([$settings['address_line_1'] ?? null]),
            'address_line_2' => $this->firstScalarString([$settings['address_line_2'] ?? null]),
            'postal_code' => $this->firstScalarString([$settings['postal_code'] ?? null]),
            'city' => $this->firstScalarString([$settings['city'] ?? null]),
            'country' => $this->firstScalarString([$settings['country'] ?? null]),
            'email' => $this->firstScalarString([$settings['email'] ?? null]),
            'phone' => $this->firstScalarString([$settings['phone'] ?? null]),
            'website' => $this->firstScalarString([$settings['website'] ?? null]),
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, string> */
    private function buildPaymentDetailsSection(array $settings): array
    {
        return [
            'bank_name' => $this->firstScalarString([$settings['bank_name'] ?? null]),
            'iban' => $this->firstScalarString([$settings['iban'] ?? null]),
            'bic' => $this->firstScalarString([$settings['bic'] ?? null]),
            'plusgiro' => $this->firstScalarString([$settings['plusgiro'] ?? null]),
            'bankgiro' => $this->firstScalarString([$settings['bankgiro'] ?? null]),
            'swish' => $this->firstScalarString([$settings['swish'] ?? null]),
            'payment_terms_days' => $this->firstScalarString([$settings['payment_terms_days'] ?? null]),
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, string> */
    private function buildTermsNotesSection(array $settings): array
    {
        return [
            'credit_note_footer_text' => $this->firstScalarString([$settings['credit_note_footer_text'] ?? null]),
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeMap(mixed $value): array
    {
        return is_array($value) ? $value : [];
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
}
