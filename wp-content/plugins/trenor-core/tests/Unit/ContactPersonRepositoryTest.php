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

    public function testCreateContactPersonWritesExpectedBusinessInsertWithoutOrderCoupling(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        $repository = new ContactPersonRepository();

        $id = $repository->create([
            'client_id' => 4,
            'property_id' => 5,
            'project_id' => 6,
            'name' => 'Ivan Petrov',
            'role_title' => 'Supervisor',
            'phone' => '+4670000000',
            'email' => 'ivan@example.com',
            'notes' => 'Primary on site',
            'is_primary' => 1,
            'status' => 'active',
        ]);

        self::assertSame(1, $id);

        $businessInsert = $this->findInsertByTable($wpdb->insertHistory, 'wp_trn_contact_persons');
        self::assertNotNull($businessInsert);
        self::assertSame(6, $businessInsert['data']['project_id'] ?? null);
        self::assertSame('Ivan Petrov', $businessInsert['data']['name'] ?? null);
        self::assertSame(1, $businessInsert['data']['is_primary'] ?? null);

        self::assertNotNull($this->findInsertByTable($wpdb->insertHistory, 'wp_trn_audit_log'));
    }

    public function testRepositoryMapsExpectedTableAndEntityType(): void
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
