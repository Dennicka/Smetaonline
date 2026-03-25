<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Backup\BackupExporter;

final class BackupExporterTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/trn-backup-exporter-tests';
        @mkdir($this->tmpRoot, 0775, true);

        $GLOBALS['trn_test_wp_upload_dir'] = ['basedir' => $this->tmpRoot];
        trn_set_test_wpdb(new BackupWpdbStub($this->tmpRoot));
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['trn_test_wp_upload_dir']);
        $this->deleteTree($this->tmpRoot);
    }

    public function testCreateBuildsManifestAndArtifacts(): void
    {
        /** @var BackupWpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->seedArtifact(10, $this->tmpRoot . '/source-artifact.pdf');

        $result = (new BackupExporter())->create(77);

        self::assertSame(1, (int) $result['manifest_id']);
        self::assertFileExists((string) $result['db_snapshot_path']);
        self::assertDirectoryExists((string) $result['artifact_bundle_path']);
        self::assertSame(64, strlen((string) $result['checksum_sha256']));

        $manifestRow = $wpdb->manifestRows[1] ?? null;
        self::assertIsArray($manifestRow);
        self::assertSame('completed', (string) ($manifestRow['status'] ?? ''));

        $manifestJson = json_decode((string) ($manifestRow['manifest_json'] ?? '{}'), true);
        self::assertIsArray($manifestJson);
        self::assertNotEmpty($manifestJson['tables'] ?? []);
        self::assertSame(1, (int) (($manifestJson['artifacts']['count'] ?? 0)));
    }

    public function testCreateFailsWhenCriticalTableIsMissing(): void
    {
        /** @var BackupWpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->dropTable('trn_clients');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Critical plugin table is missing');

        (new BackupExporter())->create(1);
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

final class BackupWpdbStub
{
    public string $prefix = 'wp_';

    public int $insert_id = 0;

    /** @var array<int, array<string,mixed>> */
    public array $manifestRows = [];

    /** @var array<string, array<int, array<string,mixed>>> */
    private array $tableRows = [];

    /** @var array<int, array<string,mixed>> */
    private array $artifactRows = [];

    public function __construct(private readonly string $tmpRoot)
    {
        foreach (self::suffixes() as $suffix) {
            $this->tableRows[$this->prefix . $suffix] = [];
        }
    }

    public function seedArtifact(int $artifactId, string $sourcePath): void
    {
        file_put_contents($sourcePath, '%PDF-test');
        $this->artifactRows[] = [
            'id' => $artifactId,
            'document_type' => 'invoice',
            'document_id' => 200,
            'version_no' => 1,
            'storage_path' => $sourcePath,
        ];
    }

    public function dropTable(string $suffix): void
    {
        unset($this->tableRows[$this->prefix . $suffix]);
    }

    public function prepare(string $query, ...$args): string
    {
        return vsprintf($query, $args);
    }

    public function insert(string $table, array $data, array $format = []): int|false
    {
        if ($table !== $this->prefix . 'trn_backup_manifests') {
            return 1;
        }

        $this->insert_id++;
        $data['id'] = $this->insert_id;
        $this->manifestRows[$this->insert_id] = $data;

        return 1;
    }

    public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int
    {
        $id = (int) ($where['id'] ?? 0);
        if ($table === $this->prefix . 'trn_backup_manifests' && $id > 0 && isset($this->manifestRows[$id])) {
            $this->manifestRows[$id] = array_merge($this->manifestRows[$id], $data);
        }

        return 1;
    }

    public function get_var(string $query): string|null
    {
        if (preg_match('/SHOW TABLES LIKE (.+)$/', $query, $match) === 1) {
            $table = trim($match[1], "' ");

            return array_key_exists($table, $this->tableRows) ? $table : null;
        }

        return null;
    }

    /** @return array<int, array<string,mixed>> */
    public function get_results(string $query, string $output): array
    {
        if (str_contains($query, $this->prefix . 'trn_document_artifacts')) {
            return $this->artifactRows;
        }

        if (preg_match('/FROM\s+([a-z0-9_]+)/i', $query, $match) === 1) {
            $table = (string) $match[1];
            return $this->tableRows[$table] ?? [];
        }

        return [];
    }

    public function get_row(string $query, string $output): ?array
    {
        if (preg_match('/WHERE id = (\d+)/', $query, $match) !== 1) {
            return null;
        }

        return $this->manifestRows[(int) $match[1]] ?? null;
    }

    /** @return array<int, string> */
    private static function suffixes(): array
    {
        return [
            'trn_schema_migrations', 'trn_audit_log', 'trn_clients', 'trn_properties', 'trn_projects', 'trn_rooms',
            'trn_document_sequences', 'trn_work_categories', 'trn_work_items', 'trn_material_categories', 'trn_materials',
            'trn_estimates', 'trn_estimate_lines', 'trn_estimate_material_lines', 'trn_offerts', 'trn_estimate_snapshots',
            'trn_invoices', 'trn_invoice_payments', 'trn_credit_notes', 'trn_operation_tokens', 'trn_operation_receipts',
            'trn_document_artifacts', 'trn_avtals', 'trn_reminders', 'trn_atas', 'trn_suppliers', 'trn_price_import_batches',
            'trn_material_supplier_prices', 'trn_backup_manifests',
        ];
    }
}
