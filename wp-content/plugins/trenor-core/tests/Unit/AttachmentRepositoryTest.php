<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\AttachmentRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class AttachmentRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testCreateAttachmentWritesExpectedBusinessInsertWithoutOrderCoupling(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repository = new AttachmentRepository();

        $id = $repository->create([
            'parent_entity_type' => 'project',
            'parent_entity_id' => 9,
            'file_url' => 'https://example.com/photo.jpg',
            'file_path' => '/uploads/photo.jpg',
            'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'title' => 'Kitchen photo',
            'caption' => 'Before work',
            'notes' => 'Taken by operator',
            'uploaded_by' => 22,
            'status' => 'active',
        ]);

        self::assertSame(1, $id);

        $businessInsert = $this->findInsertByTable($wpdb->insertHistory, 'wp_trn_attachments');
        self::assertNotNull($businessInsert);
        self::assertSame('project', $businessInsert['data']['parent_entity_type'] ?? null);
        self::assertSame(9, $businessInsert['data']['parent_entity_id'] ?? null);

        self::assertNotNull($this->findInsertByTable($wpdb->insertHistory, 'wp_trn_audit_log'));
    }

    public function testRepositoryMapsExpectedTableAndEntityType(): void
    {
        $repository = new AttachmentRepository();

        self::assertSame('wp_trn_attachments', $this->invokeProtected($repository, 'table'));
        self::assertSame('attachment', $this->invokeProtected($repository, 'entityType'));
    }

    private function invokeProtected(object $subject, string $method): mixed
    {
        $reflection = new ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($subject);
    }

    /** @param array<int, array<string, mixed>> $insertHistory */
    private function findInsertByTable(array $insertHistory, string $table): ?array
    {
        foreach ($insertHistory as $insert) {
            if ((string) ($insert['table'] ?? '') === $table) {
                return $insert;
            }
        }

        return null;
    }
}
