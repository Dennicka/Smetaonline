<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\ProjectDossierViewNormalizer;

final class ProjectDossierViewNormalizerTest extends TestCase
{
    private ProjectDossierViewNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ProjectDossierViewNormalizer();
    }

    public function testNormalizesSparseValuesSafely(): void
    {
        $normalized = $this->normalizer->normalize([
            'project' => ['id' => null, 'name' => 'Project', 'property_id' => null],
            'property' => ['city' => null],
            'client' => ['email' => null],
            'estimates' => [['id' => null, 'title' => null]],
            'offerts' => [['total_inc_vat_minor' => null]],
            'invoices' => [['paid_total_minor' => null, 'payment_count' => null]],
            'payments' => [['amount_minor' => null, 'reference' => null]],
            'summary' => ['paid_total_minor' => null],
        ]);

        self::assertSame('', $normalized['project']['id']);
        self::assertSame('', $normalized['project']['property_id']);
        self::assertSame('', $normalized['property']['city']);
        self::assertSame('', $normalized['client']['email']);
        self::assertSame('', $normalized['estimates'][0]['title']);
        self::assertSame(0, $normalized['offerts'][0]['total_inc_vat_minor']);
        self::assertSame(0, $normalized['invoices'][0]['paid_total_minor']);
        self::assertSame(0, $normalized['payments'][0]['amount_minor']);
        self::assertSame(0, $normalized['summary']['paid_total_minor']);
    }

    public function testPreservesRowOrderFromBuilderInput(): void
    {
        $normalized = $this->normalizer->normalize([
            'estimates' => [
                ['id' => 5, 'title' => 'First'],
                ['id' => 2, 'title' => 'Second'],
            ],
            'offerts' => [
                ['id' => 8, 'estimate_id' => 5],
                ['id' => 4, 'estimate_id' => 2],
            ],
            'invoices' => [
                ['id' => 9],
                ['id' => 1],
            ],
            'payments' => [
                ['id' => 3],
                ['id' => 7],
            ],
        ]);

        self::assertSame('5', $normalized['estimates'][0]['id']);
        self::assertSame('2', $normalized['estimates'][1]['id']);
        self::assertSame('8', $normalized['offerts'][0]['id']);
        self::assertSame('4', $normalized['offerts'][1]['id']);
        self::assertSame('9', $normalized['invoices'][0]['id']);
        self::assertSame('1', $normalized['invoices'][1]['id']);
        self::assertSame('3', $normalized['payments'][0]['id']);
        self::assertSame('7', $normalized['payments'][1]['id']);
    }

    public function testHandlesEmptyDossierSafely(): void
    {
        $normalized = $this->normalizer->normalize([]);

        self::assertSame([], $normalized['estimates']);
        self::assertSame([], $normalized['offerts']);
        self::assertSame([], $normalized['invoices']);
        self::assertSame([], $normalized['payments']);
        self::assertSame(0, $normalized['summary']['estimates_count']);
        self::assertSame('', $normalized['project']['name']);
        self::assertSame('', $normalized['property']['name']);
        self::assertSame('', $normalized['client']['name']);
    }
}
