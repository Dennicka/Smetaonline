<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Trenor\Core\Domain\Service\AvtalFromOffertService;
use Trenor\Core\Domain\Service\AvtalVersionProvider;
use Trenor\Core\Domain\Service\DocumentNumberGenerator;

final class AvtalFromOffertServiceTest extends TestCase
{
    public function testBuildPayloadForAcceptedOffert(): void
    {
        $service = new AvtalFromOffertService(
            new class () implements AvtalVersionProvider {
                public function nextVersionNo(int $offertId): int
                {
                    return 4;
                }
            },
            new class () implements DocumentNumberGenerator {
                public function next(string $docType, ?DateTimeImmutable $date = null): string
                {
                    return 'AVT-202603-00009';
                }
            }
        );

        $payload = $service->buildPayload(
            ['id' => 55, 'estimate_id' => 21, 'status' => 'accepted', 'currency' => 'sek', 'vat_rate_percent' => '25.0000', 'document_number' => 'OFF-202603-00011'],
            [
                'header' => ['project_id' => 12, 'client_id' => 18, 'title' => 'Kitchen repaint'],
                'totals' => ['total_inc_vat_minor' => 345000],
                'lines' => [['id' => 1]],
                'material_lines' => [['id' => 2]],
            ],
            new DateTimeImmutable('2026-03-24 11:00:00')
        );

        self::assertSame(55, $payload['offert_id']);
        self::assertSame(21, $payload['estimate_id']);
        self::assertSame(12, $payload['project_id']);
        self::assertSame(18, $payload['client_id']);
        self::assertSame('AVT-202603-00009', $payload['document_number']);
        self::assertSame(4, $payload['version_no']);
        self::assertSame('issued', $payload['status']);
        self::assertSame('SEK', $payload['currency']);
        self::assertSame(345000, $payload['total_inc_vat_minor']);
        self::assertNotSame('', $payload['snapshot_json']);
    }

    public function testBuildPayloadDeniesInvalidSourceStatus(): void
    {
        $service = new AvtalFromOffertService(
            new class () implements AvtalVersionProvider {
                public function nextVersionNo(int $offertId): int
                {
                    return 1;
                }
            },
            new class () implements DocumentNumberGenerator {
                public function next(string $docType, ?DateTimeImmutable $date = null): string
                {
                    return 'AVT-202603-00001';
                }
            }
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Avtal can be issued only from accepted offert.');

        $service->buildPayload(['id' => 55, 'estimate_id' => 21, 'status' => 'issued'], []);
    }
}
