<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\ContactPersonRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class ContactPersonRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testCreateAndUpdatePersistContactPersonPayload(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repository = new ContactPersonRepository();

        $id = $repository->create([
            'client_id' => '4',
            'property_id' => '10',
            'project_id' => '16',
            'name' => 'Anna Contact',
            'role_title' => 'Coordinator',
            'phone' => '+4670000',
            'email' => 'a@example.com',
            'notes' => 'Call in morning',
            'is_primary' => '1',
            'status' => 'active',
        ]);

        self::assertSame(1, $id);
        self::assertSame('wp_trn_contact_persons', $wpdb->insertedTable);
        self::assertSame('Anna Contact', $wpdb->insertHistory[0]['data']['name']);

        self::assertTrue($repository->updateEntity(1, ['name' => 'Anna Updated', 'status' => 'archived']));
        self::assertSame('Anna Updated', $wpdb->updatedRows[0]['data']['name']);
    }

    public function testTableAndEntityTypeMapping(): void
    {
        $repository = new ContactPersonRepository();

        self::assertSame('wp_trn_contact_persons', $this->invokeProtected($repository, 'table'));
        self::assertSame('contact_person', $this->invokeProtected($repository, 'entityType'));
    }

    private function invokeProtected(object $subject, string $method): mixed
    {
        $reflection = new ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($subject);
    }
}
