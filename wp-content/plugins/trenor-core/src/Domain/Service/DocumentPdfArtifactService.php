<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use RuntimeException;
use Trenor\Core\Database\DocumentArtifactRepository;
use Trenor\Core\Database\RepositoryFactory;

final class DocumentPdfArtifactService
{
    private RepositoryFactory $factory;
    private DocumentArtifactRepository $artifactRepository;
    private RealPdfGenerator $pdfGenerator;
    private BusinessDocumentPresentationBuilder $presentationBuilder;

    public function __construct(
        RepositoryFactory $factory,
        ?DocumentArtifactRepository $artifactRepository = null,
        ?RealPdfGenerator $pdfGenerator = null,
        ?BusinessDocumentPresentationBuilder $presentationBuilder = null
    ) {
        $this->factory = $factory;
        $this->artifactRepository = $artifactRepository ?? new DocumentArtifactRepository();
        $this->pdfGenerator = $pdfGenerator ?? new RealPdfGenerator();
        $this->presentationBuilder = $presentationBuilder ?? new BusinessDocumentPresentationBuilder();
    }

    public function getOrCreate(string $documentType, int $documentId): array
    {
        $normalizedType = sanitize_key($documentType);
        if ($documentId <= 0) {
            throw new RuntimeException('Document id must be a positive integer.');
        }

        $document = $this->loadDocument($normalizedType, $documentId);
        $this->assertDocumentGenerationPreconditions($normalizedType, $document);
        $versionNo = (int) ($document['version_no'] ?? 1);

        $existing = $this->artifactRepository->findByDocumentVersion($normalizedType, $documentId, $versionNo, 'pdf');
        if (is_array($existing) && $this->fileExists((string) ($existing['storage_path'] ?? ''))) {
            return $existing;
        }

        $pdfBinary = $this->pdfGenerator->generate($this->buildPdfLines($normalizedType, $document));
        $storagePath = $this->buildStoragePath($normalizedType, $document);
        $this->writeArtifact($storagePath, $pdfBinary);

        $checksum = hash('sha256', $pdfBinary);
        $artifactId = $this->artifactRepository->create([
            'document_type' => $normalizedType,
            'document_id' => $documentId,
            'version_no' => $versionNo,
            'artifact_type' => 'pdf',
            'storage_path' => $storagePath,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => strlen($pdfBinary),
            'checksum_sha256' => $checksum,
        ]);

        if (! is_int($artifactId) || $artifactId <= 0) {
            $raceSafeLookup = $this->artifactRepository->findByDocumentVersion($normalizedType, $documentId, $versionNo, 'pdf');
            if (is_array($raceSafeLookup) && $this->fileExists((string) ($raceSafeLookup['storage_path'] ?? ''))) {
                return $raceSafeLookup;
            }

            throw new RuntimeException('Failed to persist document artifact metadata.');
        }

        $created = $this->artifactRepository->findByDocumentVersion($normalizedType, $documentId, $versionNo, 'pdf');
        if (! is_array($created)) {
            throw new RuntimeException('Artifact was created but metadata lookup failed.');
        }

        return $created;
    }

    /** @param array<string, mixed> $document */
    private function assertDocumentGenerationPreconditions(string $documentType, array $document): void
    {
        $documentNumber = trim((string) ($document['document_number'] ?? ''));
        if ($documentNumber === '') {
            throw new RuntimeException('Document source is invalid: missing document_number.');
        }

        $versionNo = (int) ($document['version_no'] ?? 0);
        if ($versionNo <= 0) {
            throw new RuntimeException('Document source is invalid: missing version marker.');
        }

        $snapshotJson = trim((string) ($document['snapshot_json'] ?? ''));
        if ($snapshotJson === '') {
            throw new RuntimeException(
                sprintf('Document source is invalid for %s: missing snapshot_json.', $documentType)
            );
        }
    }

    private function loadDocument(string $documentType, int $documentId): array
    {
        if ($documentType === 'offert') {
            $document = $this->factory->offerts()->find($documentId);
        } elseif ($documentType === 'invoice') {
            $document = $this->factory->invoices()->find($documentId);
        } elseif ($documentType === 'credit_note') {
            $document = $this->factory->creditNotes()->find($documentId);
        } elseif ($documentType === 'reminder') {
            $document = $this->factory->reminders()->find($documentId);
        } elseif ($documentType === 'avtal') {
            $document = $this->factory->avtals()->find($documentId);
        } elseif ($documentType === 'ata') {
            $document = $this->factory->atas()->find($documentId);
        } else {
            throw new RuntimeException('Unsupported document type for PDF generation.');
        }

        if (! is_array($document)) {
            throw new RuntimeException('Document not found.');
        }

        return $document;
    }

    /** @param array<string, mixed> $document */
    private function buildStoragePath(string $documentType, array $document): string
    {
        $uploadsDir = $this->resolveBaseStorageDir();
        $rawDocumentNumber = (string) ($document['document_number'] ?? 'document');
        $documentNumber = function_exists('sanitize_file_name')
            ? sanitize_file_name($rawDocumentNumber)
            : preg_replace('/[^a-zA-Z0-9_-]+/', '-', $rawDocumentNumber);
        if (! is_string($documentNumber) || $documentNumber === '') {
            $documentNumber = 'document';
        }
        $versionNo = (int) ($document['version_no'] ?? 1);
        $documentId = (int) ($document['id'] ?? 0);
        $folder = $uploadsDir . DIRECTORY_SEPARATOR . 'trenor-private-documents' . DIRECTORY_SEPARATOR . $documentType;

        $directoryCreated = is_dir($folder) || (
            function_exists('wp_mkdir_p')
                ? wp_mkdir_p($folder)
                : mkdir($folder, 0775, true)
        );

        if (! $directoryCreated) {
            throw new RuntimeException('Failed to prepare artifact directory.');
        }

        $this->hardenStorageFolder($folder);

        return $folder . DIRECTORY_SEPARATOR . $documentNumber . '-id' . $documentId . '-v' . $versionNo . '.pdf';
    }

    private function resolveBaseStorageDir(): string
    {
        if (function_exists('wp_upload_dir')) {
            $uploadDir = wp_upload_dir();
            if (
                is_array($uploadDir)
                && isset($uploadDir['basedir'])
                && is_string($uploadDir['basedir'])
                && $uploadDir['basedir'] !== ''
            ) {
                return $uploadDir['basedir'];
            }
        }

        return sys_get_temp_dir();
    }

    private function fileExists(string $path): bool
    {
        return $path !== '' && file_exists($path) && is_file($path);
    }

    private function hardenStorageFolder(string $folder): void
    {
        $indexPath = $folder . DIRECTORY_SEPARATOR . 'index.php';
        if (! file_exists($indexPath)) {
            $written = file_put_contents($indexPath, "<?php\nhttp_response_code(404);\nexit;\n");
            if (! is_int($written) || $written <= 0) {
                throw new RuntimeException('Failed to harden artifact directory.');
            }
        }

        $htaccessPath = $folder . DIRECTORY_SEPARATOR . '.htaccess';
        if (! file_exists($htaccessPath)) {
            $written = file_put_contents($htaccessPath, "Require all denied\n");
            if (! is_int($written) || $written <= 0) {
                throw new RuntimeException('Failed to harden artifact directory.');
            }
        }
    }

    private function writeArtifact(string $path, string $contents): void
    {
        $written = file_put_contents($path, $contents);
        if (! is_int($written) || $written <= 0) {
            throw new RuntimeException('Failed to write PDF artifact.');
        }
    }

    /**
     * @param array<string, mixed> $document
     * @return array<int, string>
     */
    private function buildPdfLines(string $documentType, array $document): array
    {
        $snapshotPayload = $this->decodeSnapshot((string) ($document['snapshot_json'] ?? ''));
        $context = $this->buildPresentationContext($documentType, $document);
        $presentation = $this->presentationBuilder->build($documentType, $document, $snapshotPayload, $context);

        $lines = [
            'Smetaonline business document',
            'Title: ' . (string) ($presentation['title'] ?? strtoupper($documentType)),
            'Snapshot checksum source: ' . hash('sha256', (string) ($document['snapshot_json'] ?? '')),
        ];

        $lines = array_merge($lines, $this->sectionLines('Identity', $presentation['identity'] ?? [], 'value'));
        $lines = array_merge($lines, $this->sectionLines('Seller / Company', $presentation['seller'] ?? [], 'value'));
        $lines = array_merge($lines, $this->sectionLines('Customer', $presentation['customer'] ?? [], 'value'));
        $lines = array_merge($lines, $this->sectionLines('References', $presentation['references'] ?? [], 'value'));
        $lines = array_merge($lines, $this->sectionLines('Totals', $presentation['totals'] ?? [], 'amount_minor'));
        $lines = array_merge($lines, $this->sectionLines('Tax / Legal notes', $presentation['tax_notes'] ?? [], 'value'));
        $lines = array_merge($lines, $this->sectionLines('Document-specific context', $presentation['specific'] ?? [], 'value'));

        return $lines;
    }

    /** @param array<int,mixed> $rows @return array<int,string> */
    private function sectionLines(string $title, array $rows, string $valueKey): array
    {
        if ($rows === []) {
            return [];
        }

        $lines = ['-- ' . $title . ' --'];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = (string) ($row['label'] ?? '');
            $value = (string) ($row[$valueKey] ?? '');
            if ($label === '' || $value === '') {
                continue;
            }
            $lines[] = $label . ': ' . $value;
        }

        return $lines;
    }

    /** @param array<string,mixed> $document @return array<string,mixed> */
    private function buildPresentationContext(string $documentType, array $document): array
    {
        $context = (new DocumentSettings())->get();

        $estimateId = (int) ($document['estimate_id'] ?? 0);
        $offertId = (int) ($document['offert_id'] ?? 0);
        $invoiceId = (int) ($document['invoice_id'] ?? 0);

        if ($documentType === 'invoice' && $offertId > 0) {
            $offert = $this->factory->offerts()->find($offertId);
            if (is_array($offert)) {
                $estimateId = $estimateId > 0 ? $estimateId : (int) ($offert['estimate_id'] ?? 0);
                $context['source_offert_document_number'] = (string) ($offert['document_number'] ?? '');
            }
        }

        if (($documentType === 'credit_note' || $documentType === 'reminder') && $invoiceId > 0) {
            $invoice = $this->factory->invoices()->find($invoiceId);
            if (is_array($invoice)) {
                $offertId = $offertId > 0 ? $offertId : (int) ($invoice['offert_id'] ?? 0);
                $estimateId = $estimateId > 0 ? $estimateId : (int) ($invoice['estimate_id'] ?? 0);
                $context['source_invoice_document_number'] = (string) ($invoice['document_number'] ?? '');
            }
        }

        if ($offertId > 0 && ! isset($context['source_offert_document_number'])) {
            $offert = $this->factory->offerts()->find($offertId);
            if (is_array($offert)) {
                $context['source_offert_document_number'] = (string) ($offert['document_number'] ?? '');
            }
        }

        if (($documentType === 'avtal' || $documentType === 'offert') && $offertId <= 0) {
            $offertId = (int) ($document['id'] ?? 0);
        }

        if ($estimateId > 0) {
            $estimate = $this->factory->estimates()->find($estimateId);
            if (is_array($estimate)) {
                $project = $this->factory->projects()->find((int) ($estimate['project_id'] ?? 0));
                if (is_array($project)) {
                    $context['project'] = $project;
                    $property = $this->factory->properties()->find((int) ($project['property_id'] ?? 0));
                    if (is_array($property)) {
                        $context['property'] = $property;
                        $client = $this->factory->clients()->find((int) ($property['client_id'] ?? 0));
                        if (is_array($client)) {
                            $context['client'] = $client;
                        }
                    }
                }
            }
        }

        return $context;
    }

    /** @return array<string, mixed> */
    private function decodeSnapshot(string $snapshotJson): array
    {
        if ($snapshotJson === '') {
            return [];
        }

        $decoded = json_decode($snapshotJson, true);

        return is_array($decoded) ? $decoded : [];
    }
}
