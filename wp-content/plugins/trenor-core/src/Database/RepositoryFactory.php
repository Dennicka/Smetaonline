<?php

declare(strict_types=1);

namespace Trenor\Core\Database;

final class RepositoryFactory
{
    public function clients(): ClientRepository
    {
        return new ClientRepository();
    }

    public function properties(): PropertyRepository
    {
        return new PropertyRepository();
    }

    public function projects(): ProjectRepository
    {
        return new ProjectRepository();
    }

    public function rooms(): RoomRepository
    {
        return new RoomRepository();
    }

    public function workCategories(): WorkCategoryRepository
    {
        return new WorkCategoryRepository();
    }

    public function workItems(): WorkItemRepository
    {
        return new WorkItemRepository();
    }

    public function materialCategories(): MaterialCategoryRepository
    {
        return new MaterialCategoryRepository();
    }

    public function materials(): MaterialRepository
    {
        return new MaterialRepository();
    }

    public function suppliers(): SupplierRepository
    {
        return new SupplierRepository();
    }

    public function priceImportBatches(): PriceImportBatchRepository
    {
        return new PriceImportBatchRepository();
    }

    public function materialSupplierPrices(): MaterialSupplierPriceRepository
    {
        return new MaterialSupplierPriceRepository();
    }

    public function estimates(): EstimateRepository
    {
        return new EstimateRepository();
    }

    public function estimateLines(): EstimateLineRepository
    {
        return new EstimateLineRepository();
    }

    public function estimateMaterialLines(): EstimateMaterialLineRepository
    {
        return new EstimateMaterialLineRepository();
    }


    public function offerts(): OffertRepository
    {
        return new OffertRepository();
    }

    public function estimateSnapshots(): EstimateSnapshotRepository
    {
        return new EstimateSnapshotRepository();
    }

    public function invoices(): InvoiceRepository
    {
        return new InvoiceRepository();
    }

    public function invoicePayments(): InvoicePaymentRepository
    {
        return new InvoicePaymentRepository();
    }

    public function creditNotes(): CreditNoteRepository
    {
        return new CreditNoteRepository();
    }

    public function reminders(): ReminderRepository
    {
        return new ReminderRepository();
    }


    public function avtals(): AvtalRepository
    {
        return new AvtalRepository();
    }

    public function documentArtifacts(): DocumentArtifactRepository
    {
        return new DocumentArtifactRepository();
    }

    public function atas(): AtaRepository
    {
        return new AtaRepository();
    }

    /** @return array<int, array<string, mixed>> */
    public function auditLogs(int $limit = 100): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'trn_audit_log';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and prefixed.
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
            ARRAY_A
        ) ?: [];
    }
}
