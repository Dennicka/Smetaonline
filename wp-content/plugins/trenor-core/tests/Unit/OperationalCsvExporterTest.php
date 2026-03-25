<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Admin\OperationalCsvExporter;

final class OperationalCsvExporterTest extends TestCase
{
    public function testBuildCreatesCsvWithHeaderAndRows(): void
    {
        $csv = (new OperationalCsvExporter())->build(
            ['id', 'status', 'amount_minor'],
            [
                ['id' => 10, 'status' => 'issued', 'amount_minor' => 12345],
                ['id' => 11, 'status' => 'paid', 'amount_minor' => 0],
            ]
        );

        self::assertStringContainsString("id,status,amount_minor", $csv);
        self::assertStringContainsString("10,issued,12345", $csv);
        self::assertStringContainsString("11,paid,0", $csv);
    }
}
