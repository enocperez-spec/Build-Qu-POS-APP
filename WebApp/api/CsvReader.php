<?php
declare(strict_types=1);

final class CsvReader
{
    public static function read(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException('Unable to read uploaded CSV.');
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if (!$headers) {
            fclose($handle);
            return [];
        }
        $headers = array_map(static fn($value): string => trim((string)$value), $headers);
        $rows = [];
        while (($data = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }
}
