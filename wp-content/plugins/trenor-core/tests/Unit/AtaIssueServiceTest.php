<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Trenor\Core\Domain\Service\AtaIssueService;
use Trenor\Core\Domain\Service\AtaTotalsCalculator;
use Trenor\Core\Domain\Service\AtaVersionProvider;
use Trenor\Core\Domain\Service\DocumentNumberGenerator;

final class AtaIssueServiceTest extends TestCase
{
    public function testBuildDraftPayloadContainsNumberVersionAndConsistentTotals(): void
    {
        $service = new AtaIssueService(new class implements AtaVersionProvider {
            public function nextVersionNo(int $projectId): int
            {
                return 7;
            }
        }, new class implements DocumentNumberGenerator {
            public function next(string $docType, ?DateTimeImmutable $date = null): string
            {
                return 'ATA-202603-00007';
            }
        }, new AtaTotalsCalculator());

        $payload = $service->buildDraftPayload([
            'project_id' => 44,
            'title' => 'Extra concrete work',
            'scope_change_text' => 'Additional reinforcement required.',
            'amount_ex_vat_minor' => 20000,
            'vat_rate_percent' => 25,
            'currency' => 'sek',
        ]);

        self::assertSame(44, $payload['project_id']);
        self::assertSame('ATA-202603-00007', $payload['document_number']);
        self::assertSame(7, $payload['version_no']);
        self::assertSame('draft', $payload['status']);
        self::assertSame(20000, $payload['amount_ex_vat_minor']);
        self::assertSame(5000, $payload['vat_minor']);
        self::assertSame(25000, $payload['total_inc_vat_minor']);
        self::assertSame('SEK', $payload['currency']);
        self::assertSame('not_invoiced', $payload['invoice_link_status']);
        self::assertNotSame('', (string) $payload['snapshot_json']);
    }
}
