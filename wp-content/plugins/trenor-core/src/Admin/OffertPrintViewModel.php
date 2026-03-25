<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class OffertPrintViewModel
{
    private DocumentDateCalculator $dateCalculator;

    public function __construct(?DocumentDateCalculator $dateCalculator = null)
    {
        $this->dateCalculator = $dateCalculator ?? new DocumentDateCalculator();
    }

    /**
     * @param array<string, mixed> $offert
     * @param array{
     *     header: array<string, mixed>,
     *     totals: array<string, mixed>,
     *     lines: array<int, mixed>,
     *     material_lines: array<int, mixed>,
     *     metadata: array<string, mixed>
     * } $snapshot
     * @param array{
     *     estimate?: array<string, mixed>,
     *     project?: array<string, mixed>,
     *     property?: array<string, mixed>,
     *     client?: array<string, mixed>,
     *     document_profile?: array<string, mixed>
     * } $context
     * @return array{
     *     document: array<string, string>,
     *     recipient: array<string, string>,
     *     project_object: array<string, string>,
     *     commercial_summary: array<string, string>,
     *     labour_lines: array<int, array{title: string, unit: string, quantity: string, hours: string, subtotal_minor: string}>,
     *     material_lines: array<int, array{name: string, unit: string, quantity: string, subtotal_minor: string}>,
     *     issuer: array<string, string>,
     *     terms_acceptance: array<string, string>,
     *     currency: string
     * }
     */
    public function build(array $offert, array $snapshot, array $context = []): array
    {
        $header = $snapshot['header'];
        $totals = $snapshot['totals'];
        $metadata = $snapshot['metadata'];
        $estimate = $this->normalizeMap($context['estimate'] ?? null);
        $project = $this->normalizeMap($context['project'] ?? null);
        $property = $this->normalizeMap($context['property'] ?? null);
        $client = $this->normalizeMap($context['client'] ?? null);
        $documentProfile = $this->normalizeMap($context['document_profile'] ?? null);

        $currency = $this->firstScalarString([
            $offert['currency'] ?? null,
            $header['currency'] ?? null,
            $estimate['currency'] ?? null,
        ]);

        $document = $this->buildDocumentSection($offert, $header, $metadata, $documentProfile, $currency);
        $contextSection = $this->buildContextSection($offert, $header, $metadata, $estimate, $project, $property, $client);

        return [
            'document' => $document,
            'recipient' => $this->buildRecipientSection($contextSection),
            'project_object' => $this->buildProjectObjectSection($contextSection),
            'commercial_summary' => $this->buildCommercialSummarySection($totals, $offert),
            'labour_lines' => $this->buildLabourLines($snapshot['lines']),
            'material_lines' => $this->buildMaterialLines($snapshot['material_lines']),
            'issuer' => $this->buildIssuerSection($documentProfile),
            'terms_acceptance' => $this->buildTermsAcceptanceSection($documentProfile, $document),
            'currency' => $currency,
        ];
    }

    /**
     * @param array<string, mixed> $offert
     * @param array<string, mixed> $header
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $profile
     * @return array<string, string>
     */
    private function buildDocumentSection(
        array $offert,
        array $header,
        array $metadata,
        array $profile,
        string $currency
    ): array {
        $issuedAt = $this->firstScalarString([
            $offert['issued_at'] ?? null,
            $metadata['issued_at_utc'] ?? null,
            $header['issued_at_utc'] ?? null,
        ]);

        $offertValidDays = $this->firstScalarString([$profile['offert_valid_days'] ?? null]);

        return [
            'document_number' => $this->firstScalarString([
                $offert['document_number'] ?? null,
                $metadata['document_number'] ?? null,
                $header['document_number'] ?? null,
            ]),
            'version_no' => $this->firstScalarString([
                $offert['version_no'] ?? null,
                $metadata['offert_version_no'] ?? null,
                $header['offert_version_no'] ?? null,
            ]),
            'status' => $this->firstScalarString([$offert['status'] ?? null]),
            'issued_at' => $issuedAt,
            'offert_valid_until' => $this->dateCalculator->addDays($issuedAt, $offertValidDays),
            'currency' => $currency,
            'tax_mode' => $this->firstScalarString([$offert['tax_mode'] ?? null, $header['tax_mode'] ?? null]),
            'reverse_charge_note' => $this->firstScalarString([$offert['reverse_charge_note'] ?? null, $header['reverse_charge_note'] ?? null]),
            'vat_rate_percent' => $this->firstScalarString([
                $offert['vat_rate_percent'] ?? null,
                $header['vat_rate_percent'] ?? null,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $offert
     * @param array<string, mixed> $header
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $estimate
     * @param array<string, mixed> $project
     * @param array<string, mixed> $property
     * @param array<string, mixed> $client
     * @return array<string, string>
     */
    private function buildContextSection(
        array $offert,
        array $header,
        array $metadata,
        array $estimate,
        array $project,
        array $property,
        array $client
    ): array {
        return [
            'source_estimate_id' => $this->firstScalarString([
                $offert['estimate_id'] ?? null,
                $metadata['source_estimate_id'] ?? null,
                $header['source_estimate_id'] ?? null,
                $estimate['id'] ?? null,
            ]),
            'source_estimate_title' => $this->firstScalarString([
                $metadata['source_estimate_title'] ?? null,
                $header['source_estimate_title'] ?? null,
                $header['title'] ?? null,
                $estimate['title'] ?? null,
            ]),
            'project_name' => $this->firstScalarString([$project['name'] ?? null]),
            'project_code' => $this->firstScalarString([$project['code'] ?? null]),
            'property_name' => $this->firstScalarString([$property['name'] ?? null]),
            'property_address' => $this->firstScalarString([$property['address_line'] ?? null]),
            'property_city' => $this->firstScalarString([$property['city'] ?? null]),
            'property_postal_code' => $this->firstScalarString([$property['postal_code'] ?? null]),
            'client_name' => $this->firstScalarString([$client['name'] ?? null]),
            'client_company_name' => $this->firstScalarString([$offert['client_company_name'] ?? null, $client['company_name'] ?? null]),
            'client_org_number' => $this->firstScalarString([$offert['client_org_number'] ?? null, $client['org_number'] ?? null]),
            'client_vat_number' => $this->firstScalarString([$offert['client_vat_number'] ?? null, $client['vat_number'] ?? null]),
            'client_email' => $this->firstScalarString([$client['email'] ?? null]),
            'client_phone' => $this->firstScalarString([$client['phone'] ?? null]),
        ];
    }

    /** @param array<string, string> $contextSection @return array<string, string> */
    private function buildRecipientSection(array $contextSection): array
    {
        return [
            'client_name' => $contextSection['client_name'] ?? '',
            'client_company_name' => $contextSection['client_company_name'] ?? '',
            'client_org_number' => $contextSection['client_org_number'] ?? '',
            'client_vat_number' => $contextSection['client_vat_number'] ?? '',
            'client_email' => $contextSection['client_email'] ?? '',
            'client_phone' => $contextSection['client_phone'] ?? '',
        ];
    }

    /** @param array<string, string> $contextSection @return array<string, string> */
    private function buildProjectObjectSection(array $contextSection): array
    {
        return [
            'source_estimate_id' => $contextSection['source_estimate_id'] ?? '',
            'source_estimate_title' => $contextSection['source_estimate_title'] ?? '',
            'project_name' => $contextSection['project_name'] ?? '',
            'project_code' => $contextSection['project_code'] ?? '',
            'property_name' => $contextSection['property_name'] ?? '',
            'property_address' => $contextSection['property_address'] ?? '',
            'property_city' => $contextSection['property_city'] ?? '',
            'property_postal_code' => $contextSection['property_postal_code'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $totals
     * @param array<string, mixed> $offert
     * @return array<string, string>
     */
    private function buildCommercialSummarySection(array $totals, array $offert): array
    {
        return [
            'labour_total' => $this->toScalarString($totals['labour_total_minor'] ?? null),
            'materials_total' => $this->toScalarString($totals['materials_total_minor'] ?? null),
            'subtotal_ex_vat' => $this->toScalarString($totals['subtotal_ex_vat_minor'] ?? null),
            'vat' => $this->toScalarString($totals['vat_minor'] ?? null),
            'tax_mode' => $this->firstScalarString([$offert['tax_mode'] ?? null, $totals['tax_mode'] ?? null]),
            'total_inc_vat' => $this->toScalarString($totals['total_inc_vat_minor'] ?? ($offert['total_inc_vat_minor'] ?? null)),
            'rot_eligible_labour' => $this->toScalarString($totals['rot_eligible_labour_minor'] ?? null),
            'preliminary_rot' => $this->toScalarString($totals['preliminary_rot_minor'] ?? null),
            'amount_before_rot' => $this->toScalarString($totals['amount_before_rot_minor'] ?? ($totals['total_inc_vat_minor'] ?? null)),
            'amount_after_preliminary_rot' => $this->toScalarString($totals['amount_after_preliminary_rot_minor'] ?? null),
        ];
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

    /** @param array<string, mixed> $profile @return array<string, string> */
    private function buildIssuerSection(array $profile): array
    {
        return [
            'company_name' => $this->firstScalarString([$profile['company_name'] ?? null]),
            'org_number' => $this->firstScalarString([$profile['org_number'] ?? null]),
            'vat_number' => $this->firstScalarString([$profile['vat_number'] ?? null]),
            'email' => $this->firstScalarString([$profile['email'] ?? null]),
            'phone' => $this->firstScalarString([$profile['phone'] ?? null]),
            'website' => $this->firstScalarString([$profile['website'] ?? null]),
            'address_line' => $this->firstScalarString([$profile['address_line'] ?? null]),
            'postal_code' => $this->firstScalarString([$profile['postal_code'] ?? null]),
            'city' => $this->firstScalarString([$profile['city'] ?? null]),
            'country' => $this->firstScalarString([$profile['country'] ?? null]),
            'bankgiro' => $this->firstScalarString([$profile['bankgiro'] ?? null]),
            'plusgiro' => $this->firstScalarString([$profile['plusgiro'] ?? null]),
            'swish' => $this->firstScalarString([$profile['swish'] ?? null]),
            'iban' => $this->firstScalarString([$profile['iban'] ?? null]),
            'bic' => $this->firstScalarString([$profile['bic'] ?? null]),
        ];
    }

    /** @param array<string, mixed> $profile @param array<string, string> $document @return array<string, string> */
    private function buildTermsAcceptanceSection(array $profile, array $document): array
    {
        return [
            'offert_note' => $this->firstScalarString([$profile['offert_note'] ?? null]),
            'offert_valid_days' => $this->firstScalarString([$profile['offert_valid_days'] ?? null]),
            'offert_valid_until' => $document['offert_valid_until'] ?? '',
            'accepted_by' => '',
            'accepted_at' => '',
            'signature' => '',
        ];
    }

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
