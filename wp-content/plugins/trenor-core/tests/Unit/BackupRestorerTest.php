<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Backup\BackupRestorer;

final class BackupRestorerTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/trn-backup-restorer-tests';
        @mkdir($this->tmpRoot, 0775, true);

        trn_set_test_wpdb(new RestoreWpdbStub());
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmpRoot);
    }

    public function testRestoreRejectsInvalidConfirmation(): void
    {
        $manifestId = $this->seedValidManifest();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Restore confirmation phrase is invalid.');

        (new BackupRestorer())->restore($manifestId, 'RESTORE NOW');
    }

    public function testRestoreRejectsChecksumMismatch(): void
    {
        $manifestId = $this->seedValidManifest('invalid-checksum');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Backup checksum mismatch');

        (new BackupRestorer())->restore($manifestId, 'RESTORE ' . $manifestId);
    }

    public function testRestoreSucceedsForValidManifest(): void
    {
        /** @var RestoreWpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $manifestId = $this->seedValidManifest();

        (new BackupRestorer())->restore($manifestId, 'RESTORE ' . $manifestId);

        self::assertTrue($wpdb->sawDelete);
        self::assertTrue($wpdb->sawInsert);
    }

    private function seedValidManifest(?string $forcedChecksum = null): int
    {
        /** @var RestoreWpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];

        $bundleDir = $this->tmpRoot . '/bundle-1';
        $artifactDir = $bundleDir . '/artifacts';
        @mkdir($artifactDir, 0775, true);

        $dbSnapshotPath = $bundleDir . '/db-snapshot.json';
        $artifactManifestPath = $bundleDir . '/artifact-manifest.json';
        $artifactSourcePath = $artifactDir . '/1-source.pdf';
        $artifactTargetPath = $this->tmpRoot . '/restored/target.pdf';
        file_put_contents($artifactSourcePath, '%PDF-restore');

        $dbPayload = [
            'tables' => [
                [
                    'name' => 'wp_trn_clients',
                    'rows' => [
                        ['id' => 1, 'name' => 'Client A'],
                    ],
                ],
            ],
        ];
        $artifactPayload = [
            'files' => [
                [
                    'bundle_path' => $artifactSourcePath,
                    'storage_path' => $artifactTargetPath,
                    'checksum_sha256' => hash_file('sha256', $artifactSourcePath),
                ],
            ],
        ];

        file_put_contents($dbSnapshotPath, (string) json_encode($dbPayload));
        file_put_contents($artifactManifestPath, (string) json_encode($artifactPayload));

        $checksum = hash('sha256', hash_file('sha256', $dbSnapshotPath) . '|' . hash_file('sha256', $artifactManifestPath));
        $manifestId = 1;

        $wpdb->manifestRows[$manifestId] = [
            'id' => $manifestId,
            'status' => 'completed',
            'db_snapshot_path' => $dbSnapshotPath,
            'artifact_bundle_path' => $artifactDir,
            'checksum_sha256' => $forcedChecksum ?? $checksum,
        ];

        return $manifestId;
    }

    private function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->deleteTree($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }
}

final class RestoreWpdbStub
{
    public string $prefix = 'wp_';

    /** @var array<int, array<string,mixed>> */
    public array $manifestRows = [];

    public bool $sawDelete = false;

    public bool $sawInsert = false;

    public function prepare(string $query, ...$args): string
    {
        return vsprintf($query, $args);
    }

    public function get_row(string $query, string $output): ?array
    {
        if (preg_match('/WHERE id = (\d+)/', $query, $match) !== 1) {
            return null;
        }

        return $this->manifestRows[(int) $match[1]] ?? null;
    }

    public function query(string $query): int
    {
        if (str_contains($query, 'DELETE FROM')) {
            $this->sawDelete = true;
        }

        return 1;
    }

    public function insert(string $table, array $data, ?array $format = null): int
    {
        $this->sawInsert = true;

        return 1;
    }

    public function get_var(string $query): ?string
    {
        if (preg_match('/SHOW TABLES LIKE (.+)$/', $query, $match) === 1) {
            return trim($match[1], "' ");
        }

        return null;
    }
}
