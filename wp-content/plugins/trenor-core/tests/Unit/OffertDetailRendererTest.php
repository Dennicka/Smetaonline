<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Admin\OffertDetailRenderer;

final class OffertDetailRendererTest extends TestCase
{
    public function testBuildMetadataUsesWhitelistedUserFacingKeys(): void
    {
        $renderer = new OffertDetailRenderer();

        $metadata = $this->invokePrivate(
            $renderer,
            'buildMetadata',
            [
                ['source_estimate_id' => 44, 'db_row_id' => 999, 'secret' => 'drop'],
                [],
                ['version_no' => 2, 'document_number' => 'OFF-202603-00077', 'issued_at' => '2026-03-01 09:00:00'],
            ]
        );

        self::assertSame(['source_estimate_id', 'offert_version_no', 'document_number', 'issued_at_utc'], array_keys($metadata));
        self::assertArrayNotHasKey('db_row_id', $metadata);
        self::assertArrayNotHasKey('secret', $metadata);
    }

    public function testLineAndMaterialNameHelpersHandleMalformedArraysSafely(): void
    {
        $renderer = new OffertDetailRenderer();

        self::assertSame('', $this->invokePrivate($renderer, 'lineTitle', [[
            'title' => ['invalid'],
            'line_title_sv_snapshot' => null,
        ]]));

        self::assertSame('', $this->invokePrivate($renderer, 'materialName', [[
            'name' => ['invalid'],
            'material_name_sv_snapshot' => null,
        ]]));

        self::assertSame('', $this->invokePrivate($renderer, 'toScalarString', [['invalid']]));
    }

    /** @param array<int, mixed> $arguments */
    private function invokePrivate(object $subject, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($subject, $arguments);
    }
}
