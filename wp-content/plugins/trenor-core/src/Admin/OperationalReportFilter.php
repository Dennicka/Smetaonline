<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

final class OperationalReportFilter
{
    private const ALLOWED_PERIODS = ['today', '7d', '30d', 'month'];

    /**
     * @param array<string, mixed> $raw
     * @return array{filters:array{status:string,date_from:string,date_to:string,period:string},errors:array<int,string>}
     */
    public function normalize(array $raw): array
    {
        $status = $this->scalarText($raw['status'] ?? '');
        $rawPeriod = $this->scalarText($raw['period'] ?? '');
        $period = $this->normalizePeriod($rawPeriod);
        $rawDateFrom = $this->scalarText($raw['date_from'] ?? '');
        $rawDateTo = $this->scalarText($raw['date_to'] ?? '');
        $dateFrom = $this->normalizeDate($rawDateFrom);
        $dateTo = $this->normalizeDate($rawDateTo);
        $errors = [];

        if ($rawPeriod !== '' && $period === '') {
            $errors[] = 'Invalid period value.';
        }

        if ($period !== '') {
            [$dateFrom, $dateTo] = $this->periodRange($period);
        } else {
            if ($rawDateFrom !== '' && $dateFrom === '') {
                $errors[] = 'Invalid date_from value.';
            }

            if ($rawDateTo !== '' && $dateTo === '') {
                $errors[] = 'Invalid date_to value.';
            }

            if ($dateFrom !== '' && $dateTo !== '' && strcmp($dateFrom, $dateTo) > 0) {
                $errors[] = 'Date range is invalid: date_from is after date_to.';
                $dateFrom = '';
                $dateTo = '';
            }
        }

        return [
            'filters' => [
                'status' => sanitize_key($status),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'period' => $period,
            ],
            'errors' => $errors,
        ];
    }

    private function normalizePeriod(mixed $value): string
    {
        $normalized = sanitize_key($this->scalarText($value));
        if (! in_array($normalized, self::ALLOWED_PERIODS, true)) {
            return '';
        }

        return $normalized;
    }

    private function normalizeDate(mixed $value): string
    {
        $text = $this->scalarText($value);
        if ($text === '') {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $text);
        if (! $parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $text) {
            return '';
        }

        return $text;
    }

    /** @return array{0:string,1:string} */
    private function periodRange(string $period): array
    {
        $today = new \DateTimeImmutable('today');

        if ($period === 'today') {
            $value = $today->format('Y-m-d');

            return [$value, $value];
        }

        if ($period === '7d') {
            return [$today->modify('-6 days')->format('Y-m-d'), $today->format('Y-m-d')];
        }

        if ($period === '30d') {
            return [$today->modify('-29 days')->format('Y-m-d'), $today->format('Y-m-d')];
        }

        if ($period === 'month') {
            return [$today->modify('first day of this month')->format('Y-m-d'), $today->format('Y-m-d')];
        }

        return ['', ''];
    }

    private function scalarText(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
