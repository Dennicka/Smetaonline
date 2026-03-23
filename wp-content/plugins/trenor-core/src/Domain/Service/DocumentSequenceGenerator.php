<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class DocumentSequenceGenerator
{
    /** @var object */
    private object $database;
    private string $table;

    public function __construct(?object $database = null, string $table = 'trn_document_sequences')
    {
        if ($database === null) {
            global $wpdb;
            $this->database = $wpdb;
        } else {
            $this->database = $database;
        }

        $this->table = $this->database->prefix . $table;
    }

    public function next(string $docType, ?\DateTimeImmutable $date = null): string
    {
        $date ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $yyyymm = $date->format('Ym');

        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT id, current_value FROM {$this->table} WHERE doc_type = %s AND yyyymm = %s",
                $docType,
                $yyyymm
            ),
            ARRAY_A
        );

        $next = 1;
        if (is_array($row)) {
            $next = ((int) $row['current_value']) + 1;
            $this->database->update(
                $this->table,
                ['current_value' => $next, 'updated_at' => gmdate('Y-m-d H:i:s')],
                ['id' => (int) $row['id']],
                ['%d', '%s'],
                ['%d']
            );
        } else {
            $this->database->insert(
                $this->table,
                [
                    'doc_type' => $docType,
                    'yyyymm' => $yyyymm,
                    'current_value' => 1,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ],
                ['%s', '%s', '%d', '%s']
            );
        }

        return sprintf('%s-%s-%05d', strtoupper($docType), $yyyymm, $next);
    }
}
