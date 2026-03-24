<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use RuntimeException;

final class DocumentSequenceGenerator implements DocumentNumberGenerator
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

        $this->database->query('START TRANSACTION');

        try {
            $this->ensureSequenceRow($docType, $yyyymm);
            $this->database->query(
                $this->database->prepare(
                    "UPDATE {$this->table}
                    SET current_value = LAST_INSERT_ID(current_value + 1), updated_at = %s
                    WHERE doc_type = %s AND yyyymm = %s",
                    gmdate('Y-m-d H:i:s'),
                    $docType,
                    $yyyymm
                )
            );

            $next = (int) $this->database->get_var('SELECT LAST_INSERT_ID()');
            if ($next <= 0) {
                throw new RuntimeException('Unable to allocate next document sequence value.');
            }

            $this->database->query('COMMIT');
        } catch (\Throwable $throwable) {
            $this->database->query('ROLLBACK');
            throw $throwable;
        }

        return sprintf('%s-%s-%05d', strtoupper($docType), $yyyymm, $next);
    }

    private function ensureSequenceRow(string $docType, string $yyyymm): void
    {
        $insertSql = $this->database->prepare(
            "INSERT INTO {$this->table} (doc_type, yyyymm, current_value, updated_at)
            VALUES (%s, %s, 0, %s)
            ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)",
            $docType,
            $yyyymm,
            gmdate('Y-m-d H:i:s')
        );

        $result = $this->database->query($insertSql);
        if ($result === false) {
            throw new RuntimeException('Unable to initialize document sequence row.');
        }
    }
}
