<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Database\RepositoryFactory;
use Trenor\Core\Domain\Service\DocumentPdfArtifactService;
use Trenor\Core\Tests\Support\WpdbStub;

final class DocumentPdfArtifactServiceTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/trn-pdf-artifacts-tests';
        @mkdir($this->tmpRoot, 0775, true);

        trn_set_test_wpdb(new WpdbStub());
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmpRoot);
    }

    public function testCreatesOffertPdfArtifactMetadata(): void
    {
        $this->seedDocument(101, 'OFF-2026-001', 3);

        $artifact = $this->service()->getOrCreate('offert', 101);

        self::assertSame('offert', $artifact['document_type']);
        self::assertSame(101, (int) $artifact['document_id']);
        self::assertSame(3, (int) $artifact['version_no']);
        self::assertSame('pdf', $artifact['artifact_type']);
        self::assertSame('application/pdf', $artifact['mime_type']);
        self::assertFileExists((string) $artifact['storage_path']);
        self::assertStringContainsString('OFF-2026-001-id101-v3.pdf', (string) $artifact['storage_path']);
        self::assertGreaterThan(0, (int) ($artifact['file_size_bytes'] ?? 0));
        self::assertStringStartsWith('%PDF-', (string) file_get_contents((string) $artifact['storage_path']));
        self::assertSame(
            hash_file('sha256', (string) $artifact['storage_path']),
            (string) $artifact['checksum_sha256']
        );
        self::assertFileExists(dirname((string) $artifact['storage_path']) . '/index.php');
        self::assertFileExists(dirname((string) $artifact['storage_path']) . '/.htaccess');
    }

    public function testCreatesInvoicePdfArtifactMetadata(): void
    {
        $this->seedDocument(202, 'INV-2026-002', 2);

        $artifact = $this->service()->getOrCreate('invoice', 202);

        self::assertSame('invoice', $artifact['document_type']);
        self::assertSame(202, (int) $artifact['document_id']);
        self::assertSame(2, (int) $artifact['version_no']);
        self::assertFileExists((string) $artifact['storage_path']);
        self::assertStringContainsString('INV-2026-002-id202-v2.pdf', (string) $artifact['storage_path']);
    }

    public function testCreatesCreditNotePdfArtifactMetadata(): void
    {
        $this->seedDocument(303, 'CRN-2026-004', 1);

        $artifact = $this->service()->getOrCreate('credit_note', 303);

        self::assertSame('credit_note', $artifact['document_type']);
        self::assertSame(303, (int) $artifact['document_id']);
        self::assertSame(1, (int) $artifact['version_no']);
        self::assertFileExists((string) $artifact['storage_path']);
        self::assertStringContainsString('CRN-2026-004-id303-v1.pdf', (string) $artifact['storage_path']);
    }

    public function testRepeatedRequestReusesSingleArtifactPerDocumentVersion(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->next_insert_id = 1;

        $this->seedDocument(404, 'INV-2026-099', 5);
        $service = $this->service();

        $first = $service->getOrCreate('invoice', 404);
        $second = $service->getOrCreate('invoice', 404);

        self::assertSame((int) $first['id'], (int) $second['id']);
        self::assertSame((string) $first['storage_path'], (string) $second['storage_path']);
        self::assertCount(1, $wpdb->documentArtifacts);
    }

    public function testMissingDocumentThrowsExplicitException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Document not found.');

        $this->service()->getOrCreate('invoice', 99999);
    }

    private function service(): DocumentPdfArtifactService
    {
        return new DocumentPdfArtifactService(new RepositoryFactory());
    }

    private function seedDocument(int $id, string $documentNumber, int $versionNo): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];

        $wpdb->rowsById[$id] = [
            'id' => $id,
            'document_number' => $documentNumber,
            'version_no' => $versionNo,
            'status' => 'issued',
            'issued_at' => '2026-03-24 00:00:00',
            'currency' => 'SEK',
            'vat_rate_percent' => '25.0000',
            'total_inc_vat_minor' => 123450,
            'snapshot_json' => '{"estimate_title":"Apartment renovation","totals":{"total_inc_vat_minor":123450}}',
        ];

        $GLOBALS['trn_test_wp_upload_dir'] = [
            'basedir' => $this->tmpRoot,
        ];
    }

    private function deleteTree(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $path) {
            if ($path->isDir()) {
                rmdir($path->getPathname());
            } else {
                unlink($path->getPathname());
            }
        }

        rmdir($root);
    }
}
