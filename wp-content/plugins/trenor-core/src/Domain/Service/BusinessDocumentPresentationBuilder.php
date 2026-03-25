<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class BusinessDocumentPresentationBuilder
{
    /**
     * @param array<string,mixed> $document
     * @param array<string,mixed> $snapshot
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function build(string $documentType, array $document, array $snapshot = [], array $context = []): array
    {
        $header = $this->map($snapshot['header'] ?? null);
        $totals = $this->map($snapshot['totals'] ?? null);
        $metadata = $this->map($snapshot['metadata'] ?? null);
        $currency = $this->first([$document['currency'] ?? null, $header['currency'] ?? null, 'SEK']);

        $identity = [
            ['label' => 'Document type', 'value' => strtoupper($documentType)],
            ['label' => 'Document number', 'value' => $this->first([$document['document_number'] ?? null, $metadata['document_number'] ?? null])],
            ['label' => 'Version', 'value' => $this->string($document['version_no'] ?? null)],
            ['label' => 'Status', 'value' => $this->string($document['status'] ?? null)],
            ['label' => 'Issued date', 'value' => $this->date($document['issued_at'] ?? ($metadata['issued_at_utc'] ?? null))],
            ['label' => 'Currency', 'value' => $currency],
        ];

        $seller = $this->contactBlock([
            'name' => $context['seller_name'] ?? ($context['company_name'] ?? null),
            'org' => $context['seller_org'] ?? ($context['org_number'] ?? null),
            'vat' => $context['seller_vat'] ?? ($context['vat_number'] ?? null),
            'email' => $context['seller_email'] ?? ($context['email'] ?? null),
            'phone' => $context['seller_phone'] ?? ($context['phone'] ?? null),
            'address' => $context['seller_address'] ?? ($context['address_line'] ?? ($context['address_line_1'] ?? null)),
            'postal_code' => $context['seller_postal_code'] ?? ($context['postal_code'] ?? null),
            'city' => $context['seller_city'] ?? ($context['city'] ?? null),
            'country' => $context['seller_country'] ?? ($context['country'] ?? null),
            'bankgiro' => $context['bankgiro'] ?? null,
            'plusgiro' => $context['plusgiro'] ?? null,
            'swish' => $context['swish'] ?? null,
            'iban' => $context['iban'] ?? null,
            'bic' => $context['bic'] ?? null,
        ]);

        $customer = $this->contactBlock([
            'name' => $document['client_name'] ?? ($context['client']['name'] ?? null),
            'company_name' => $this->first([$document['client_company_name'] ?? null, $context['client']['company_name'] ?? null]),
            'org' => $this->first([$document['client_org_number'] ?? null, $context['client']['org_number'] ?? null]),
            'vat' => $this->first([$document['client_vat_number'] ?? null, $context['client']['vat_number'] ?? null]),
            'email' => $context['client']['email'] ?? null,
            'phone' => $context['client']['phone'] ?? null,
        ]);

        $references = [
            ['label' => 'Estimate reference', 'value' => $this->first([$document['estimate_id'] ?? null, $metadata['source_estimate_id'] ?? null])],
            ['label' => 'Estimate title', 'value' => $this->first([$metadata['source_estimate_title'] ?? null, $snapshot['estimate_title'] ?? null])],
            ['label' => 'Offert reference', 'value' => $this->string($document['offert_id'] ?? null)],
            ['label' => 'Invoice reference', 'value' => $this->string($document['invoice_id'] ?? ($metadata['source_invoice_document_number'] ?? null))],
            ['label' => 'Project', 'value' => $context['project']['name'] ?? null],
            ['label' => 'Property', 'value' => $context['property']['name'] ?? null],
            ['label' => 'Property address', 'value' => $context['property']['address_line'] ?? null],
            ['label' => 'ROT property reference', 'value' => $this->first([$header['rot_property_reference'] ?? null, $document['rot_property_reference'] ?? null])],
            ['label' => 'Reminder level', 'value' => $this->string($document['reminder_level'] ?? null)],
        ];

        $taxMode = TaxMode::normalize($document['tax_mode'] ?? ($header['tax_mode'] ?? null));
        $vatMinor = (int) ($totals['vat_minor'] ?? ($document['vat_minor'] ?? 0));
        $totalMinor = (int) ($totals['total_inc_vat_minor'] ?? ($document['total_inc_vat_minor'] ?? 0));
        $beforeRot = (int) ($totals['amount_before_rot_minor'] ?? $totalMinor);
        $afterRot = (int) ($totals['amount_after_preliminary_rot_minor'] ?? 0);

        $totalsRows = [
            ['label' => 'Labour total', 'amount_minor' => $this->string($totals['labour_total_minor'] ?? null)],
            ['label' => 'Material total', 'amount_minor' => $this->string($totals['materials_total_minor'] ?? null)],
            ['label' => 'Subtotal excl. VAT', 'amount_minor' => $this->string($totals['subtotal_ex_vat_minor'] ?? null)],
            ['label' => 'VAT', 'amount_minor' => $this->string($vatMinor)],
            ['label' => 'Total incl. VAT', 'amount_minor' => $this->string($totalMinor)],
        ];

        if (($totals['preliminary_rot_minor'] ?? null) !== null || ($totals['rot_eligible_labour_minor'] ?? null) !== null) {
            $totalsRows[] = ['label' => 'ROT-eligible labour', 'amount_minor' => $this->string($totals['rot_eligible_labour_minor'] ?? null)];
            $totalsRows[] = ['label' => 'Preliminary ROT deduction', 'amount_minor' => $this->string($totals['preliminary_rot_minor'] ?? null)];
            $totalsRows[] = ['label' => 'Amount before ROT deduction', 'amount_minor' => $this->string($beforeRot)];
            $totalsRows[] = ['label' => 'Amount after ROT deduction', 'amount_minor' => $this->string($afterRot)];
        }

        $taxNotes = $this->taxNotes($taxMode, $document, $header, $vatMinor);

        $specific = [
            ['label' => 'Document title', 'value' => $this->titleForType($documentType)],
            ['label' => 'Payment due date', 'value' => $this->date($document['payment_due_date'] ?? null)],
            ['label' => 'Offert validity days', 'value' => $this->string($context['offert_valid_days'] ?? null)],
            ['label' => 'Agreement context', 'value' => $documentType === 'avtal' ? 'Agreement based on offert and project references.' : ''],
            ['label' => 'Reminder context', 'value' => $documentType === 'reminder' ? 'Payment reminder linked to source invoice.' : ''],
            ['label' => 'Credit note context', 'value' => $documentType === 'credit_note' ? 'This credit note reverses/corrects previously invoiced amounts.' : ''],
        ];

        return [
            'title' => $this->titleForType($documentType),
            'currency' => $currency,
            'identity' => $this->cleanRows($identity),
            'seller' => $this->cleanRows($seller),
            'customer' => $this->cleanRows($customer),
            'references' => $this->cleanRows($references),
            'totals' => $this->cleanRows($totalsRows, 'amount_minor'),
            'tax_notes' => $this->cleanRows($taxNotes),
            'specific' => $this->cleanRows($specific),
        ];
    }

    /** @param array<string,mixed> $input @return array<int,array{label:string,value:string}> */
    private function contactBlock(array $input): array
    {
        return [
            ['label' => 'Name', 'value' => $this->first([$input['name'] ?? null, $input['company_name'] ?? null])],
            ['label' => 'Company', 'value' => $this->string($input['company_name'] ?? null)],
            ['label' => 'Org number', 'value' => $this->string($input['org'] ?? null)],
            ['label' => 'VAT number', 'value' => $this->string($input['vat'] ?? null)],
            ['label' => 'Email', 'value' => $this->string($input['email'] ?? null)],
            ['label' => 'Phone', 'value' => $this->string($input['phone'] ?? null)],
            ['label' => 'Address', 'value' => $this->string($input['address'] ?? null)],
            ['label' => 'Postal code', 'value' => $this->string($input['postal_code'] ?? null)],
            ['label' => 'City', 'value' => $this->string($input['city'] ?? null)],
            ['label' => 'Country', 'value' => $this->string($input['country'] ?? null)],
            ['label' => 'Bankgiro', 'value' => $this->string($input['bankgiro'] ?? null)],
            ['label' => 'Plusgiro', 'value' => $this->string($input['plusgiro'] ?? null)],
            ['label' => 'Swish', 'value' => $this->string($input['swish'] ?? null)],
            ['label' => 'IBAN', 'value' => $this->string($input['iban'] ?? null)],
            ['label' => 'BIC', 'value' => $this->string($input['bic'] ?? null)],
        ];
    }

    /** @return array<int,array{label:string,value:string}> */
    private function taxNotes(string $taxMode, array $document, array $header, int $vatMinor): array
    {
        if (TaxMode::isReverseCharge($taxMode)) {
            return [
                ['label' => 'Tax mode', 'value' => 'Reverse charge (VAT reported by buyer).'],
                ['label' => 'VAT handling', 'value' => 'VAT amount is 0 on this document due to reverse charge rules.'],
                ['label' => 'Legal note', 'value' => $this->first([$document['reverse_charge_note'] ?? null, $header['reverse_charge_note'] ?? null, 'Omvänd betalningsskyldighet gäller.'])],
            ];
        }

        if (($header['preliminary_rot_minor'] ?? null) !== null || ($document['rot_requested'] ?? null)) {
            return [
                ['label' => 'Tax mode', 'value' => 'ROT deduction applied where eligible labour is included.'],
                ['label' => 'VAT handling', 'value' => 'Standard VAT applies before ROT deduction. VAT shown: ' . $this->string($vatMinor) . ' minor.'],
                ['label' => 'ROT note', 'value' => 'Customer amount after preliminary ROT is shown separately.'],
            ];
        }

        return [
            ['label' => 'Tax mode', 'value' => 'Standard VAT.'],
            ['label' => 'VAT handling', 'value' => 'VAT is included according to the shown VAT rate.'],
        ];
    }

    private function titleForType(string $documentType): string
    {
        return match ($documentType) {
            'offert' => 'Offert / Commercial Proposal',
            'avtal' => 'Avtal / Agreement',
            'invoice' => 'Invoice',
            'reminder' => 'Payment Reminder',
            'credit_note' => 'Credit Note',
            default => strtoupper($documentType),
        };
    }

    private function date(mixed $value): string
    {
        $string = $this->string($value);
        if ($string === '') {
            return '';
        }

        $timestamp = strtotime($string);
        if ($timestamp === false) {
            return $string;
        }

        return gmdate('Y-m-d', $timestamp);
    }

    private function first(array $values): string
    {
        foreach ($values as $value) {
            $scalar = $this->string($value);
            if ($scalar !== '') {
                return $scalar;
            }
        }

        return '';
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @return array<string,mixed> */
    private function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array<int,array<string,string>>
     */
    private function cleanRows(array $rows, string $valueKey = 'value'): array
    {
        $filtered = [];
        foreach ($rows as $row) {
            if (($row[$valueKey] ?? '') === '') {
                continue;
            }
            $filtered[] = $row;
        }

        return $filtered;
    }
}
