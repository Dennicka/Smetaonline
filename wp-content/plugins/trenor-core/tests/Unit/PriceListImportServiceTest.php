<?php

declare(strict_types=1);

namespace Trenor\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trenor\Core\Import\PriceImportException;
use Trenor\Core\Import\PriceListImportService;

final class PriceListImportServiceTest extends TestCase
{
    public function testValidImportCreatesBatchAndRows(): void
    {
        $batchRepository = new InMemoryBatchRepository();
        $priceRepository = new InMemoryPriceRepository();
        $service = new PriceListImportService($batchRepository, $priceRepository);

        $result = $service->importCsv(10, 'prices.csv', "material_key,buy_price_minor,title\nMAT-1,2500,Primer", 77);

        self::assertSame('completed', $result['status']);
        self::assertSame(1, $result['imported_rows']);
        self::assertSame(1, $batchRepository->createdBatches);
    }

    public function testIdenticalImportIsDeterministic(): void
    {
        $batchRepository = new InMemoryBatchRepository();
        $priceRepository = new InMemoryPriceRepository();
        $service = new PriceListImportService($batchRepository, $priceRepository);

        $csv = "material_key,buy_price_minor\nMAT-1,2500";
        $first = $service->importCsv(10, 'prices.csv', $csv, 77);
        $second = $service->importCsv(10, 'prices.csv', $csv, 77);

        self::assertSame('completed', $first['status']);
        self::assertSame('duplicate', $second['status']);
        self::assertSame(1, $batchRepository->createdBatches);
    }

    public function testUnchangedPriceDoesNotCreateHistoryDuplicates(): void
    {
        $batchRepository = new InMemoryBatchRepository();
        $priceRepository = new InMemoryPriceRepository();
        $priceRepository->active['10|MAT-1'] = ['id' => 5, 'buy_price_minor' => 2500];
        $service = new PriceListImportService($batchRepository, $priceRepository);

        $result = $service->importCsv(10, 'prices.csv', "material_key,buy_price_minor\nMAT-1,2500", 77);

        self::assertSame(1, $result['unchanged_rows']);
        self::assertCount(0, $priceRepository->created);
    }

    public function testChangedPriceClosesOldAndCreatesNewHistoryState(): void
    {
        $batchRepository = new InMemoryBatchRepository();
        $priceRepository = new InMemoryPriceRepository();
        $priceRepository->active['10|MAT-1'] = ['id' => 9, 'buy_price_minor' => 2500];
        $service = new PriceListImportService($batchRepository, $priceRepository);

        $result = $service->importCsv(10, 'prices.csv', "material_key,buy_price_minor\nMAT-1,3000", 77);

        self::assertSame(1, $result['changed_rows']);
        self::assertSame([9], $priceRepository->closedIds);
        self::assertCount(1, $priceRepository->created);
    }

    public function testInvalidFormatIsRejectedExplicitly(): void
    {
        $this->expectException(PriceImportException::class);
        $this->expectExceptionMessage('Unsupported format');

        $service = new PriceListImportService(new InMemoryBatchRepository(), new InMemoryPriceRepository());
        $service->importCsv(10, 'prices.csv', "sku,price\nMAT-1,1000", 77);
    }
}

final class InMemoryBatchRepository extends \Trenor\Core\Database\PriceImportBatchRepository
{
    public int $createdBatches = 0;

    /** @var array<string, array<string, mixed>> */
    private array $completedByChecksum = [];

    public function create(array $data): ?int
    {
        $this->createdBatches++;

        return $this->createdBatches;
    }

    public function updateStatus(int $id, string $status, array $resultSummary): bool
    {
        $checksum = '';
        if (isset($resultSummary['checksum']) && is_string($resultSummary['checksum'])) {
            $checksum = $resultSummary['checksum'];
        }

        if ($checksum !== '') {
            $this->completedByChecksum[$checksum] = ['id' => $id, 'status' => $status];
        }

        return true;
    }

    public function findCompletedByChecksum(int $supplierId, string $checksum): ?array
    {
        return $this->completedByChecksum[$checksum] ?? null;
    }
}

final class InMemoryPriceRepository extends \Trenor\Core\Database\MaterialSupplierPriceRepository
{
    /** @var array<string, array<string, mixed>> */
    public array $active = [];

    /** @var array<int, int> */
    public array $closedIds = [];

    /** @var array<int, array<string, mixed>> */
    public array $created = [];

    public function findCurrentPrice(int $supplierId, string $materialKey): ?array
    {
        return $this->active[$supplierId . '|' . $materialKey] ?? null;
    }

    public function closeActivePrice(int $priceId, string $effectiveTo): bool
    {
        $this->closedIds[] = $priceId;

        return true;
    }

    public function createPrice(array $data): ?int
    {
        $this->created[] = $data;

        return count($this->created);
    }
}
