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

    public function testCreateAndUpdatePersistAttachmentPayload(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repository = new AttachmentRepository();

        $id = $repository->create([
            'parent_entity_type' => 'project',
            'parent_entity_id' => '12',
            'file_url' => 'https://example.com/f.jpg',
            'file_path' => '/uploads/f.jpg',
            'original_filename' => 'f.jpg',
            'mime_type' => 'image/jpeg',
            'title' => 'Before',
            'caption' => 'Before work',
            'notes' => 'Keep',
            'uploaded_by' => '8',
        ]);

        self::assertSame(1, $id);
        self::assertSame('wp_trn_attachments', $wpdb->insertedTable);
        self::assertSame('project', $wpdb->insertHistory[0]['data']['parent_entity_type']);

        self::assertTrue($repository->updateEntity(1, ['title' => 'After', 'status' => 'active']));
        self::assertSame('After', $wpdb->updatedRows[0]['data']['title']);
    }

    public function testTableAndEntityTypeMapping(): void
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
}
