<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Trenor\Core\Database\InvoicePaymentRepository;
use Trenor\Core\Database\InvoiceRepository;
use Trenor\Core\Database\OffertRepository;
use Trenor\Core\Tests\Support\WpdbStub;

final class RepositorySmokeTest extends TestCase
{
    protected function setUp(): void
    {
        trn_set_test_wpdb(new WpdbStub());
    }

    public function testOffertInvoiceAndPaymentRepositoriesMapTableAndEntity(): void
    {
        $offert = new OffertRepository();
        $invoice = new InvoiceRepository();
        $payment = new InvoicePaymentRepository();

        self::assertSame('wp_trn_offerts', $this->invokeProtected($offert, 'table'));
        self::assertSame('offert', $this->invokeProtected($offert, 'entityType'));

        self::assertSame('wp_trn_invoices', $this->invokeProtected($invoice, 'table'));
        self::assertSame('invoice', $this->invokeProtected($invoice, 'entityType'));

        self::assertSame('wp_trn_invoice_payments', $this->invokeProtected($payment, 'table'));
        self::assertSame('invoice_payment', $this->invokeProtected($payment, 'entityType'));
    }

    public function testStatusTransitionsAcceptOnlyAllowedStatuses(): void
    {
        $offert = new OffertRepository();
        $invoice = new InvoiceRepository();

        self::assertTrue($offert->transitionStatus(11, 'issued'));
        self::assertTrue($offert->transitionStatus(11, 'accepted'));
        self::assertFalse($offert->transitionStatus(11, 'cancelled'));

        self::assertTrue($invoice->transitionStatus(12, 'issued'));
        self::assertTrue($invoice->transitionStatus(12, 'partially_paid'));
        self::assertTrue($invoice->transitionStatus(12, 'paid'));
        self::assertTrue($invoice->transitionStatus(12, 'archived'));
        self::assertFalse($invoice->transitionStatus(12, 'accepted'));
    }

    public function testVersionIncrementMethodsBehaveCorrectly(): void
    {
        /** @var WpdbStub $wpdb */
        $wpdb = $GLOBALS['wpdb'];

        $offert = new OffertRepository();
        $invoice = new InvoiceRepository();

        $wpdb->maxVersion = 0;
        self::assertSame(1, $offert->nextVersionNo(44));

        $wpdb->maxVersion = 7;
        self::assertSame(8, $offert->nextVersionNo(44));

        $wpdb->maxVersion = 0;
        self::assertSame(1, $invoice->nextVersionNo(77));

        $wpdb->maxVersion = 5;
        self::assertSame(6, $invoice->nextVersionNo(77));
    }

    private function invokeProtected(object $subject, string $method): mixed
    {
        $reflection = new ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($subject);
    }
}
