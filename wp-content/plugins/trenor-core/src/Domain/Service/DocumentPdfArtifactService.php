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

    public function __construct(
        RepositoryFactory $factory,
        ?DocumentArtifactRepository $artifactRepository = null,
        ?RealPdfGenerator $pdfGenerator = null
    ) {
        $this->factory = $factory;
        $this->artifactRepository = $artifactRepository ?? new DocumentArtifactRepository();
        $this->pdfGenerator = $pdfGenerator ?? new RealPdfGenerator();
    }

    public function getOrCreate(string $documentType, int $documentId): array
    {
        $normalizedType = sanitize_key($documentType);
        $document = $this->loadDocument($normalizedType, $documentId);
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

    private function loadDocument(string $documentType, int $documentId): array
    {
        if ($documentType === 'offert') {
            $document = $this->factory->offerts()->find($documentId);
        } elseif ($documentType === 'invoice') {
            $document = $this->factory->invoices()->find($documentId);
        } elseif ($documentType === 'credit_note') {
            $document = $this->factory->creditNotes()->find($documentId);
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

        $lines = [
            'Smetaonline document artifact',
            'Type: ' . strtoupper($documentType),
            'Document number: ' . (string) ($document['document_number'] ?? ''),
            'Version: ' . (string) ((int) ($document['version_no'] ?? 1)),
            'Status: ' . (string) ($document['status'] ?? ''),
            'Issued at: ' . (string) ($document['issued_at'] ?? ''),
            'Currency: ' . (string) ($document['currency'] ?? 'SEK'),
            'Total incl VAT (minor): ' . (string) ((int) ($document['total_inc_vat_minor'] ?? 0)),
            'VAT rate percent: ' . (string) ($document['vat_rate_percent'] ?? ''),
            'Snapshot checksum source: ' . hash('sha256', (string) ($document['snapshot_json'] ?? '')),
        ];

        if (isset($snapshotPayload['estimate_title']) && is_string($snapshotPayload['estimate_title'])) {
            $lines[] = 'Estimate title: ' . $snapshotPayload['estimate_title'];
        }

        if (isset($snapshotPayload['totals']) && is_array($snapshotPayload['totals'])) {
            $totals = $snapshotPayload['totals'];
            $lines[] = 'Snapshot total inc VAT (minor): ' . (string) ((int) ($totals['total_inc_vat_minor'] ?? 0));
        }

        return $lines;
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
