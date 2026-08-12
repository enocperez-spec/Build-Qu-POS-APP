<?php
declare(strict_types=1);

final class ReportService
{
    private const POS_BASE_URL = 'https://qu-releases.qubeyond.com/pos/builds';
    private const KIOSK_BASE_URL = 'https://qu-releases.qubeyond.com/kiosk/builds';
    private const STALE_DAYS = 14;
    private const QUBOX_DOWN_DAYS = 2;

    public static function generate(array $currentFile, ?array $previousFile, string $appRoot, ?PDO $pdo = null): array
    {
        $currentRows = self::readCsv($currentFile['tmp_name']);
        if (count($currentRows) === 0) {
            throw new RuntimeException('The current CSV does not contain any rows.');
        }

        $currentUploadId = null;
        $previousUploadId = null;
        $previousRows = null;
        $previousName = null;
        if ($pdo && class_exists('Database')) {
            $currentUploadId = Database::saveCsvUpload($pdo, $currentFile['name'], 'uploaded', $currentRows);
            $uploads = Database::latestCsvUploads($pdo, 2);
            $currentUpload = $uploads[0] ?? null;
            $previousUpload = $uploads[1] ?? null;
            if ($currentUpload) {
                $currentUploadId = (int)$currentUpload['id'];
                $currentRows = Database::getCsvUploadRows($pdo, $currentUploadId);
            }
            if ($previousUpload) {
                $previousUploadId = (int)$previousUpload['id'];
                $previousRows = Database::getCsvUploadRows($pdo, $previousUploadId);
                $previousName = (string)$previousUpload['filename'];
            }
        } elseif ($previousFile && is_uploaded_file($previousFile['tmp_name'])) {
            $previousRows = self::readCsv($previousFile['tmp_name']);
            $previousName = $previousFile['name'] ?? null;
        }

        return self::generateFromRows($currentRows, $currentFile['name'], $previousRows, $previousName, $appRoot, $currentUploadId, $previousUploadId, true, self::storeMetadataMap($pdo));
    }

    public static function generateFromLatestUploads(PDO $pdo, string $appRoot): array
    {
        $uploads = Database::latestCsvUploads($pdo, 2);
        $currentUpload = $uploads[0] ?? null;
        if (!$currentUpload) {
            throw new RuntimeException('Upload a CSV before generating a report.');
        }
        $previousUpload = $uploads[1] ?? null;
        $currentRows = Database::getCsvUploadRows($pdo, (int)$currentUpload['id']);
        $previousRows = $previousUpload ? Database::getCsvUploadRows($pdo, (int)$previousUpload['id']) : null;
        return self::generateFromRows(
            $currentRows,
            (string)$currentUpload['filename'],
            $previousRows,
            $previousUpload['filename'] ?? null,
            $appRoot,
            (int)$currentUpload['id'],
            $previousUpload ? (int)$previousUpload['id'] : null,
            true,
            self::storeMetadataMap($pdo)
        );
    }

    public static function reportFromUpload(PDO $pdo, int $uploadId, string $appRoot): array
    {
        $currentUpload = Database::getCsvUpload($pdo, $uploadId);
        if (!$currentUpload) {
            throw new RuntimeException('CSV upload was not found.');
        }
        $previousUpload = Database::previousCsvUpload($pdo, $uploadId);
        $currentRows = Database::getCsvUploadRows($pdo, (int)$currentUpload['id']);
        $previousRows = $previousUpload ? Database::getCsvUploadRows($pdo, (int)$previousUpload['id']) : null;
        $result = self::generateFromRows(
            $currentRows,
            (string)$currentUpload['filename'],
            $previousRows,
            $previousUpload['filename'] ?? null,
            $appRoot,
            (int)$currentUpload['id'],
            $previousUpload ? (int)$previousUpload['id'] : null,
            false,
            self::storeMetadataMap($pdo)
        );
        $result['selectedUpload'] = $currentUpload;
        $result['previousUpload'] = $previousUpload;
        return $result;
    }

    public static function reportFromFileUrl(string $appRoot, string $fileUrl): ?array
    {
        $query = parse_url($fileUrl, PHP_URL_QUERY);
        if (!$query) {
            return null;
        }
        parse_str($query, $params);
        $file = (string)($params['file'] ?? '');
        $file = str_replace('\\', '/', rawurldecode($file));
        if ($file === '' || str_contains($file, '..') || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}\/[A-Za-z0-9_.-]+\.json$/', $file)) {
            return null;
        }
        $path = $appRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function generateFromRows(array $currentRows, string $sourceName, ?array $previousRows, ?string $previousName, string $appRoot, ?int $currentUploadId, ?int $previousUploadId, bool $writeFiles = true, array $storeMetadataMap = []): array
    {
        if (count($currentRows) === 0) {
            throw new RuntimeException('The current CSV does not contain any rows.');
        }
        if ($previousRows !== null && count($previousRows) === 0) {
            $previousRows = null;
            $previousName = null;
            $previousUploadId = null;
            }

        $report = self::buildReport($currentRows, $sourceName, $previousRows, $previousName, $storeMetadataMap);
        $report['currentUploadId'] = $currentUploadId;
        $report['previousUploadId'] = $previousUploadId;
        $dateFolder = date('Y-m-d');
        $htmlFile = '';
        $jsonFile = '';
        $htmlUrl = null;
        $jsonUrl = null;
        $htmlPath = null;
        $jsonPath = null;

        if ($writeFiles) {
            $html = self::renderReportHtml($report);
            $stamp = date('His');
            $reportDir = $appRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $dateFolder;
            self::ensureDirectory($reportDir);

            $baseName = 'QuPOS_CurrentVersions_' . $stamp;
            $htmlFile = $baseName . '.html';
            $jsonFile = $baseName . '.json';
            file_put_contents($reportDir . DIRECTORY_SEPARATOR . $htmlFile, $html);
            file_put_contents($reportDir . DIRECTORY_SEPARATOR . $jsonFile, json_encode($report, JSON_PRETTY_PRINT));
            $htmlUrl = 'api/report-file.php?file=' . rawurlencode($dateFolder . '/' . $htmlFile);
            $jsonUrl = 'api/report-file.php?file=' . rawurlencode($dateFolder . '/' . $jsonFile);
            $htmlPath = 'data/reports/' . rawurlencode($dateFolder) . '/' . rawurlencode($htmlFile);
            $jsonPath = 'data/reports/' . rawurlencode($dateFolder) . '/' . rawurlencode($jsonFile);
        }

        return [
            'report' => $report,
            'htmlUrl' => $htmlUrl,
            'jsonUrl' => $jsonUrl,
            'htmlPath' => $htmlPath,
            'jsonPath' => $jsonPath,
            'htmlFile' => $htmlFile,
            'currentUploadId' => $currentUploadId,
            'previousUploadId' => $previousUploadId,
            'createdAt' => date('c'),
        ];
    }

    private static function storeMetadataMap(?PDO $pdo): array
    {
        if (!$pdo || !class_exists('Database')) {
            return [];
        }
        if (method_exists('Database', 'latestStoreMetadataMap')) {
            return Database::latestStoreMetadataMap($pdo);
        }
        return Database::latestStoreStatusMap($pdo);
    }

    public static function listReports(string $appRoot): array
    {
        $reportsRoot = $appRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'reports';
        self::ensureDirectory($reportsRoot);
        $items = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($reportsRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
                continue;
            }
            $path = $file->getPathname();
            $relative = str_replace($reportsRoot . DIRECTORY_SEPARATOR, '', $path);
            $items[] = [
                'name' => $file->getFilename(),
                'date' => basename(dirname($path)),
                'size' => $file->getSize(),
                'modified' => date('c', $file->getMTime()),
                'url' => 'api/report-file.php?file=' . rawurlencode(str_replace(DIRECTORY_SEPARATOR, '/', $relative)),
            ];
        }

        usort($items, static fn(array $a, array $b): int => strcmp($b['modified'], $a['modified']));
        return $items;
    }

    private static function buildReport(array $rows, string $sourceName, ?array $previousRows, ?string $previousName, array $storeMetadataMap = []): array
    {
        $rowsWithVersions = array_values(array_filter($rows, static fn(array $row): bool => trim((string)($row['Current Version'] ?? '')) !== ''));
        if (count($rowsWithVersions) === 0) {
            throw new RuntimeException("The CSV does not contain any values in the 'Current Version' column.");
        }

        $posRows = array_values(array_filter($rowsWithVersions, static fn(array $row): bool => self::isPosVersion((string)$row['Current Version'])));
        $otherRows = array_values(array_filter($rowsWithVersions, static fn(array $row): bool => !self::isPosVersion((string)$row['Current Version'])));
        $kioskRows = array_values(array_filter($otherRows, static fn(array $row): bool => self::isKioskRow($row)));
        $quboxRows = array_values(array_filter($otherRows, static fn(array $row): bool => self::isQuBoxRow($row)));
        $qukdsRows = array_values(array_filter($otherRows, static fn(array $row): bool => self::isQuKdsRow($row)));
        $quorbRows = array_values(array_filter($otherRows, static fn(array $row): bool => self::isQuOrbRow($row)));
        $remainingOtherRows = array_values(array_filter($otherRows, static fn(array $row): bool => !self::isKioskRow($row) && !self::isQuBoxRow($row) && !self::isQuKdsRow($row) && !self::isQuOrbRow($row)));

        $posVersions = self::versionGroups($posRows, 'pos', $storeMetadataMap);
        $kioskVersions = self::versionGroups($kioskRows, 'kiosk', $storeMetadataMap);
        $quboxVersions = self::versionGroups($quboxRows, 'qubox', $storeMetadataMap);
        $qukdsVersions = self::versionGroups($qukdsRows, 'qukds', $storeMetadataMap);
        $quorbVersions = self::versionGroups($quorbRows, 'quorb', $storeMetadataMap);
        $otherVersions = self::versionGroups($remainingOtherRows, 'other', $storeMetadataMap);

        usort($posVersions, static fn(array $a, array $b): int => version_compare($b['version'], $a['version']));
        usort($kioskVersions, static fn(array $a, array $b): int => $b['terminalCount'] <=> $a['terminalCount'] ?: strcmp($b['version'], $a['version']));
        usort($quboxVersions, static fn(array $a, array $b): int => $b['terminalCount'] <=> $a['terminalCount'] ?: strcmp($b['version'], $a['version']));
        usort($qukdsVersions, static fn(array $a, array $b): int => $b['terminalCount'] <=> $a['terminalCount'] ?: strcmp($b['version'], $a['version']));
        usort($quorbVersions, static fn(array $a, array $b): int => $b['terminalCount'] <=> $a['terminalCount'] ?: strcmp($b['version'], $a['version']));
        usort($otherVersions, static fn(array $a, array $b): int => $b['terminalCount'] <=> $a['terminalCount'] ?: strcmp($b['version'], $a['version']));

        $mostCurrentVersion = $posVersions[0]['version'] ?? 'N/A';
        $stable = self::currentStableVersion($posVersions);
        $currentStableVersion = $stable['version'] ?? 'N/A';
        $currentStableVersionCount = $stable['terminalCount'] ?? 0;
        $kioskStable = self::stableAdoption($kioskVersions, count($kioskRows));
        $quboxStable = self::stableAdoption($quboxVersions, count($quboxRows));
        $qukdsStable = self::stableAdoption($qukdsVersions, count($qukdsRows));
        $quorbStable = self::stableAdoption($quorbVersions, count($quorbRows));

        $outOfDateRows = [];
        if ($currentStableVersion !== 'N/A') {
            $outOfDateRows = array_values(array_filter($posRows, static fn(array $row): bool => version_compare((string)$row['Current Version'], $currentStableVersion, '<')));
        }
        $stableUsagePercent = self::percent($currentStableVersionCount, count($posRows));
        $trends = $previousRows ? self::dashboardTrends($posRows, $previousRows, $currentStableVersion, count($outOfDateRows)) : null;

        $storeReport = self::storeVersionReport($posRows, $currentStableVersion, $storeMetadataMap);
        $mixedStores = array_values(array_filter($storeReport, static fn(array $store): bool => $store['uniqueVersionCount'] > 1));
        $staleTerminals = self::staleTerminals($posRows);
        $quboxDownStores = self::quboxDownStores($storeReport, $quboxRows);
        $farBehindStores = array_values(array_filter($storeReport, static fn(array $store): bool => $store['outOfDateTerminalCount'] > 0));
        usort($farBehindStores, static fn(array $a, array $b): int => $b['outOfDateTerminalCount'] <=> $a['outOfDateTerminalCount']);

        return [
            'generatedOn' => date('m/d/Y H:i:s'),
            'sourceCsv' => $sourceName,
            'previousCsv' => $previousName,
            'summary' => [
                'uniqueQuPosAppVersions' => count($posVersions),
                'posAppTerminals' => count($posRows),
                'mostCurrentVersion' => $mostCurrentVersion,
                'currentStableVersion' => $currentStableVersion,
                'currentStableVersionCount' => $currentStableVersionCount,
                'currentStableVersionUsagePercent' => $stableUsagePercent,
                'outOfDateStores' => self::uniqueCount($outOfDateRows, 'Store ID'),
                'outOfDatePosTerminals' => count($outOfDateRows),
                'kioskVersions' => count($kioskVersions),
                'quboxVersions' => count($quboxVersions),
                'qukdsVersions' => count($qukdsVersions),
                'quorbVersions' => count($quorbVersions),
                'kioskStableVersion' => $kioskStable['version'],
                'kioskStableUsagePercent' => $kioskStable['usagePercent'],
                'kioskStableDeviceCount' => $kioskStable['deviceCount'],
                'kioskReportingDevices' => $kioskStable['totalDevices'],
                'quboxStableVersion' => $quboxStable['version'],
                'quboxStableUsagePercent' => $quboxStable['usagePercent'],
                'quboxStableDeviceCount' => $quboxStable['deviceCount'],
                'quboxReportingDevices' => $quboxStable['totalDevices'],
                'qukdsStableVersion' => $qukdsStable['version'],
                'qukdsStableUsagePercent' => $qukdsStable['usagePercent'],
                'qukdsStableDeviceCount' => $qukdsStable['deviceCount'],
                'qukdsReportingDevices' => $qukdsStable['totalDevices'],
                'quorbStableVersion' => $quorbStable['version'],
                'quorbStableUsagePercent' => $quorbStable['usagePercent'],
                'quorbStableDeviceCount' => $quorbStable['deviceCount'],
                'quorbReportingDevices' => $quorbStable['totalDevices'],
                'otherVersions' => count($otherVersions),
                'trends' => $trends,
            ],
            'downloadableVersions' => $posVersions,
            'kioskVersions' => $kioskVersions,
            'quboxVersions' => $quboxVersions,
            'qukdsVersions' => $qukdsVersions,
            'quorbVersions' => $quorbVersions,
            'otherVersions' => $otherVersions,
            'outOfDateVersionSummary' => self::outOfDateVersionSummary($outOfDateRows),
            'stores' => $storeReport,
            'alerts' => [
                'mixedVersionStores' => $mixedStores,
                'staleTerminals' => $staleTerminals,
                'quboxDownStores' => $quboxDownStores,
                'farBehindStores' => $farBehindStores,
            ],
            'comparison' => $previousRows ? self::comparison($previousRows, $rowsWithVersions) : null,
        ];
    }

    private static function dashboardTrends(array $currentPosRows, array $previousRows, string $currentStableVersion, int $currentOutOfDateTerminalCount): array
    {
        if ($currentStableVersion === 'N/A') {
            return [];
        }
        $previousPosRows = array_values(array_filter($previousRows, static fn(array $row): bool => self::isPosVersion((string)($row['Current Version'] ?? ''))));
        $previousOutOfDateRows = array_values(array_filter($previousPosRows, static fn(array $row): bool => version_compare((string)$row['Current Version'], $currentStableVersion, '<')));
        $currentOutOfDateStores = self::uniqueCount(array_values(array_filter($currentPosRows, static fn(array $row): bool => version_compare((string)$row['Current Version'], $currentStableVersion, '<'))), 'Store ID');
        $previousOutOfDateStores = self::uniqueCount($previousOutOfDateRows, 'Store ID');
        $currentStableCount = count(array_filter($currentPosRows, static fn(array $row): bool => (string)$row['Current Version'] === $currentStableVersion));
        $previousStableCount = count(array_filter($previousPosRows, static fn(array $row): bool => (string)($row['Current Version'] ?? '') === $currentStableVersion));
        $currentUsage = self::percent($currentStableCount, count($currentPosRows));
        $previousUsage = self::percent($previousStableCount, count($previousPosRows));

        return [
            'outOfDateStores' => [
                'current' => $currentOutOfDateStores,
                'previous' => $previousOutOfDateStores,
                'delta' => $currentOutOfDateStores - $previousOutOfDateStores,
            ],
            'outOfDatePosTerminals' => [
                'current' => $currentOutOfDateTerminalCount,
                'previous' => count($previousOutOfDateRows),
                'delta' => $currentOutOfDateTerminalCount - count($previousOutOfDateRows),
            ],
            'stableVersionUsage' => [
                'currentPercent' => $currentUsage,
                'previousPercent' => $previousUsage,
                'deltaPercent' => round($currentUsage - $previousUsage, 1),
            ],
        ];
    }

    private static function stableAdoption(array $versions, int $totalDevices): array
    {
        $stable = self::currentStableVersion($versions);
        if (!$stable || $totalDevices <= 0) {
            return [
                'version' => 'No Data',
                'usagePercent' => null,
                'deviceCount' => 0,
                'totalDevices' => $totalDevices,
            ];
        }

        return [
            'version' => $stable['version'],
            'usagePercent' => self::percent((int)$stable['terminalCount'], $totalDevices),
            'deviceCount' => (int)$stable['terminalCount'],
            'totalDevices' => $totalDevices,
        ];
    }

    private static function percent(int $part, int $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }
        return round(($part / $whole) * 100, 1);
    }

    private static function readCsv(string $path): array
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

    private static function versionGroups(array $rows, string $type, array $storeMetadataMap = []): array
    {
        $groups = self::groupBy($rows, 'Current Version');
        $versions = [];
        foreach ($groups as $version => $versionRows) {
            $storeRows = self::storeRows($versionRows, $storeMetadataMap);
            $url = match ($type) {
                'pos' => self::POS_BASE_URL . '/Qu.POS_' . $version . '.zip',
                'kiosk' => self::KIOSK_BASE_URL . '/Kiosk-Setup-' . $version . '.exe',
                default => '',
            };
            $versions[] = [
                'version' => $version,
                'releaseTrain' => self::releaseTrain($version),
                'terminalCount' => count($versionRows),
                'storeCount' => count($storeRows),
                'terminalTypes' => self::uniqueJoin($versionRows, 'Terminal Type'),
                'url' => $url,
                'storeRows' => $storeRows,
            ];
        }
        return $versions;
    }

    private static function storeRows(array $rows, array $storeMetadataMap = []): array
    {
        $groups = self::groupBy($rows, 'Store ID');
        $stores = [];
        foreach ($groups as $storeId => $storeRows) {
            $first = $storeRows[0];
            $stores[] = [
                'storeId' => $storeId,
                'storeName' => (string)($first['Store Name'] ?? ''),
                'storeStatus' => self::storeStatus((string)$storeId, (string)($first['Store Name'] ?? ''), $storeMetadataMap),
                'storeBrands' => self::storeBrands((string)$storeId, (string)($first['Store Name'] ?? ''), $storeMetadataMap),
                'terminalCount' => count($storeRows),
                'terminalTypes' => self::uniqueJoin($storeRows, 'Terminal Type'),
                'latestSeen' => self::latestSeen($storeRows),
            ];
        }
        usort($stores, static fn(array $a, array $b): int => strcasecmp($a['storeName'], $b['storeName']) ?: strcmp((string)$a['storeId'], (string)$b['storeId']));
        return $stores;
    }

    private static function storeVersionReport(array $rows, string $stableVersion, array $storeMetadataMap = []): array
    {
        $stores = [];
        foreach (self::groupBy($rows, 'Store ID') as $storeId => $storeRows) {
            $versions = array_values(array_unique(array_map(static fn(array $row): string => (string)$row['Current Version'], $storeRows)));
            usort($versions, static fn(string $a, string $b): int => version_compare($b, $a));
            $versionCounts = [];
            foreach ($storeRows as $row) {
                $version = (string)$row['Current Version'];
                $versionCounts[$version] = ($versionCounts[$version] ?? 0) + 1;
            }
            arsort($versionCounts);
            $mostCommon = array_key_first($versionCounts) ?? '';
            $outdated = $stableVersion === 'N/A' ? [] : array_values(array_filter($storeRows, static fn(array $row): bool => version_compare((string)$row['Current Version'], $stableVersion, '<')));
            $first = $storeRows[0];
            $stores[] = [
                'storeId' => $storeId,
                'storeName' => (string)($first['Store Name'] ?? ''),
                'storeStatus' => self::storeStatus((string)$storeId, (string)($first['Store Name'] ?? ''), $storeMetadataMap),
                'storeBrands' => self::storeBrands((string)$storeId, (string)($first['Store Name'] ?? ''), $storeMetadataMap),
                'versionsDetected' => implode(', ', $versions),
                'versionsDetectedList' => $versions,
                'uniqueVersionCount' => count($versions),
                'mostCommonVersion' => $mostCommon,
                'outOfDateTerminalCount' => count($outdated),
                'totalPosTerminals' => count($storeRows),
                'latestSeen' => self::latestSeen($storeRows),
                'terminalVersionMap' => self::terminalMap($storeRows),
            ];
        }
        usort($stores, static fn(array $a, array $b): int => strcasecmp($a['storeName'], $b['storeName']) ?: strcmp((string)$a['storeId'], (string)$b['storeId']));
        return $stores;
    }

    private static function storeStatus(string $storeId, string $storeName, array $storeMetadataMap): string
    {
        $row = self::storeMetadataRow($storeId, $storeName, $storeMetadataMap);
        $status = is_array($row) ? trim((string)($row['status'] ?? '')) : trim((string)$row);
        return $status === '' ? 'No Store Data' : $status;
    }

    private static function storeBrands(string $storeId, string $storeName, array $storeMetadataMap): array
    {
        $row = self::storeMetadataRow($storeId, $storeName, $storeMetadataMap);
        $brands = is_array($row) ? (array)($row['brands'] ?? []) : [];
        $brands = array_merge($brands, self::brandsFromText($storeName), self::brandsFromText((string)($row['storeName'] ?? '')));
        $brands = array_values(array_unique(array_filter(array_map(
            static fn(string $brand): string => trim($brand),
            $brands
        ))));
        usort($brands, static fn(string $a, string $b): int => strcasecmp($a, $b));
        return $brands;
    }

    private static function storeMetadataRow(string $storeId, string $storeName, array $storeMetadataMap): array|string
    {
        $storeId = trim($storeId);
        if ($storeId !== '' && array_key_exists($storeId, $storeMetadataMap)) {
            return $storeMetadataMap[$storeId];
        }

        $normalizedName = self::normalizeStoreName($storeName);
        if ($normalizedName === '') {
            return '';
        }
        foreach ($storeMetadataMap as $row) {
            if (!is_array($row)) {
                continue;
            }
            $metadataName = self::normalizeStoreName((string)($row['storeName'] ?? ''));
            if ($metadataName !== '' && $metadataName === $normalizedName) {
                return $row;
            }
        }
        return '';
    }

    private static function normalizeStoreName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\\[[^\\]]+\\]/', '', $value) ?? $value;
        $value = preg_replace('/\\s+/', ' ', $value) ?? $value;
        return trim($value);
    }

    private static function brandsFromText(string $text): array
    {
        $normalized = strtolower($text);
        $matches = [];
        $patterns = [
            'Auntie Anne\'s' => ['auntie anne', '[aa]', 'aa-'],
            'Carvel' => ['carvel', '[cv]', 'cv-', ' cb-', '/ cb-'],
            'Cinnabon' => ['cinnabon', '[cn]', 'cn-'],
            'Jamba' => ['jamba', '[ja]', 'ja-'],
            'Moe\'s' => ['moes', 'moe\'s', '[moes]', 'moes-'],
            'Schlotzsky\'s' => ['schlotzsky', 'sch-', '[sch]'],
            'McAlister\'s Deli' => ['mcalister', 'mcalister\'s', 'mcalisters', '[mca]'],
        ];
        foreach ($patterns as $brand => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, strtolower($needle))) {
                    $matches[] = $brand;
                    break;
                }
            }
        }
        return $matches;
    }

    private static function terminalMap(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'terminalLabel' => self::terminalLabel((string)($row['Computer Name'] ?? ''), (string)($row['Network Address'] ?? '')),
                'computerName' => (string)($row['Computer Name'] ?? ''),
                'networkAddress' => (string)($row['Network Address'] ?? ''),
                'terminalId' => (string)($row['Terminal ID'] ?? ''),
                'terminalType' => (string)($row['Terminal Type'] ?? ''),
                'currentVersion' => (string)($row['Current Version'] ?? ''),
                'lastSeen' => (string)($row['Last Seen Online'] ?? ''),
            ];
        }
        usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['terminalLabel'], $b['terminalLabel']));
        return $items;
    }

    private static function terminalLabel(string $computerName, string $networkAddress): string
    {
        if (preg_match('/T(\d+)$/i', $computerName, $matches)) {
            return 'Terminal ' . $matches[1];
        }
        if (preg_match('/\.22\.(\d+)$/', $networkAddress, $matches)) {
            $last = (int)$matches[1];
            if ($last === 10) {
                return 'QuBox';
            }
            if ($last >= 111) {
                return 'Terminal ' . ($last - 110);
            }
        }
        return $computerName !== '' ? $computerName : 'Unknown Terminal';
    }

    private static function outOfDateVersionSummary(array $rows): array
    {
        $summary = [];
        foreach (self::groupBy($rows, 'Current Version') as $version => $versionRows) {
            $summary[] = [
                'version' => $version,
                'terminalCount' => count($versionRows),
                'storeCount' => self::uniqueCount($versionRows, 'Store ID'),
            ];
        }
        usort($summary, static fn(array $a, array $b): int => version_compare($b['version'], $a['version']));
        return $summary;
    }

    private static function staleTerminals(array $rows): array
    {
        $cutoff = strtotime('-' . self::STALE_DAYS . ' days');
        $stale = [];
        foreach ($rows as $row) {
            $lastSeenRaw = (string)($row['Last Seen Online'] ?? '');
            $time = self::parseCsvDate($lastSeenRaw);
            if ($time !== null && $time < $cutoff) {
                $stale[] = [
                    'storeId' => (string)($row['Store ID'] ?? ''),
                    'storeName' => (string)($row['Store Name'] ?? ''),
                    'terminalId' => (string)($row['Terminal ID'] ?? ''),
                    'computerName' => (string)($row['Computer Name'] ?? ''),
                    'terminalType' => (string)($row['Terminal Type'] ?? ''),
                    'currentVersion' => (string)($row['Current Version'] ?? ''),
                    'lastSeen' => $lastSeenRaw,
                    'ageDays' => floor((time() - $time) / 86400),
                ];
            }
        }
        usort($stale, static fn(array $a, array $b): int => $b['ageDays'] <=> $a['ageDays']);
        return $stale;
    }

    private static function quboxDownStores(array $storeReport, array $quboxRows): array
    {
        $quboxByStore = self::groupBy($quboxRows, 'Store ID');
        $cutoff = strtotime('-' . self::QUBOX_DOWN_DAYS . ' days');
        $downStores = [];

        foreach ($storeReport as $store) {
            $storeId = trim((string)($store['storeId'] ?? ''));
            $storeQuboxRows = $quboxByStore[$storeId] ?? [];
            if (count($storeQuboxRows) === 0) {
                $downStores[] = [
                    'storeId' => $storeId,
                    'storeName' => (string)($store['storeName'] ?? ''),
                    'storeBrands' => $store['storeBrands'] ?? [],
                    'storeStatus' => (string)($store['storeStatus'] ?? ''),
                    'issue' => 'Missing QuBox',
                    'quboxVersion' => '',
                    'computerName' => '',
                    'lastSeen' => 'Not in current terminal export',
                    'ageDays' => '',
                ];
                continue;
            }

            $latest = self::latestRowByLastSeen($storeQuboxRows);
            $lastSeenRaw = (string)($latest['Last Seen Online'] ?? '');
            $lastSeenTime = self::parseCsvDate($lastSeenRaw);
            if ($lastSeenTime === null || $lastSeenTime < $cutoff) {
                $downStores[] = [
                    'storeId' => $storeId,
                    'storeName' => (string)($store['storeName'] ?? ''),
                    'storeBrands' => $store['storeBrands'] ?? [],
                    'storeStatus' => (string)($store['storeStatus'] ?? ''),
                    'issue' => $lastSeenTime === null ? 'QuBox Last Seen Unknown' : 'QuBox Stale',
                    'quboxVersion' => (string)($latest['Current Version'] ?? ''),
                    'computerName' => (string)($latest['Computer Name'] ?? ''),
                    'lastSeen' => $lastSeenRaw,
                    'ageDays' => $lastSeenTime === null ? '' : floor((time() - $lastSeenTime) / 86400),
                ];
            }
        }

        usort($downStores, static function (array $a, array $b): int {
            $issueSort = strcmp((string)$a['issue'], (string)$b['issue']);
            if ($issueSort !== 0) {
                return $issueSort;
            }
            return strcasecmp((string)$a['storeName'], (string)$b['storeName']) ?: strcmp((string)$a['storeId'], (string)$b['storeId']);
        });
        return $downStores;
    }

    private static function latestRowByLastSeen(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            $left = self::parseCsvDate((string)($a['Last Seen Online'] ?? '')) ?? 0;
            $right = self::parseCsvDate((string)($b['Last Seen Online'] ?? '')) ?? 0;
            return $right <=> $left;
        });
        return $rows[0] ?? [];
    }

    private static function comparison(array $previousRows, array $currentRows): array
    {
        $previous = self::indexByTerminal($previousRows);
        $current = self::indexByTerminal($currentRows);
        $changed = [];
        $new = [];
        $removed = [];

        foreach ($current as $key => $row) {
            if (!isset($previous[$key])) {
                $new[] = self::terminalComparisonRow($row);
                continue;
            }
            $previousVersion = (string)($previous[$key]['Current Version'] ?? '');
            $currentVersion = (string)($row['Current Version'] ?? '');
            if ($previousVersion !== $currentVersion) {
                $item = self::terminalComparisonRow($row);
                $item['previousVersion'] = $previousVersion;
                $item['currentVersion'] = $currentVersion;
                $item['changeType'] = version_compare($currentVersion, $previousVersion, '>=') ? 'Upgraded' : 'Downgraded';
                $changed[] = $item;
            }
        }

        foreach ($previous as $key => $row) {
            if (!isset($current[$key])) {
                $removed[] = self::terminalComparisonRow($row);
            }
        }

        return [
            'changedTerminalCount' => count($changed),
            'newTerminalCount' => count($new),
            'removedTerminalCount' => count($removed),
            'changedTerminals' => $changed,
            'newTerminals' => $new,
            'removedTerminals' => $removed,
        ];
    }

    private static function terminalComparisonRow(array $row): array
    {
        return [
            'storeId' => (string)($row['Store ID'] ?? ''),
            'storeName' => (string)($row['Store Name'] ?? ''),
            'terminalId' => (string)($row['Terminal ID'] ?? ''),
            'computerName' => (string)($row['Computer Name'] ?? ''),
            'terminalType' => (string)($row['Terminal Type'] ?? ''),
            'currentVersion' => (string)($row['Current Version'] ?? ''),
            'lastSeen' => (string)($row['Last Seen Online'] ?? ''),
        ];
    }

    private static function indexByTerminal(array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            $storeId = (string)($row['Store ID'] ?? '');
            $terminalId = (string)($row['Terminal ID'] ?? '');
            $computerName = (string)($row['Computer Name'] ?? '');
            $networkAddress = (string)($row['Network Address'] ?? '');
            $key = $storeId . '|' . ($terminalId !== '' ? $terminalId : ($computerName !== '' ? $computerName : $networkAddress));
            $index[$key] = $row;
        }
        return $index;
    }

    private static function currentStableVersion(array $versions): ?array
    {
        if (count($versions) === 0) {
            return null;
        }
        usort($versions, static fn(array $a, array $b): int => $b['terminalCount'] <=> $a['terminalCount'] ?: version_compare($b['version'], $a['version']));
        return $versions[0];
    }

    private static function renderReportHtml(array $report): string
    {
        $json = json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><base href="../"><meta name="viewport" content="width=device-width,initial-scale=1"><title>QU POS Current Versions</title><link rel="stylesheet" href="assets/app.css"></head><body><div id="app" class="report-only"></div><script>window.__QU_REPORT__=' . $json . ';</script><script src="assets/app.js"></script></body></html>';
    }

    private static function isPosVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+\.\d+$/', trim($version)) === 1;
    }

    private static function isKioskVersion(string $version): bool
    {
        return preg_match('/^4\.1\.\d+-\d+$/', trim($version)) === 1;
    }

    private static function isKioskRow(array $row): bool
    {
        return strcasecmp((string)($row['Terminal Type'] ?? ''), 'Kiosk') === 0 || self::isKioskVersion((string)($row['Current Version'] ?? ''));
    }

    private static function isQuBoxVersion(string $version): bool
    {
        return preg_match('/^3\.6\.\d+-\d+$/', trim($version)) === 1;
    }

    private static function isQuBoxRow(array $row): bool
    {
        return strcasecmp((string)($row['Terminal Type'] ?? ''), 'QuBox') === 0 || self::isQuBoxVersion((string)($row['Current Version'] ?? ''));
    }

    private static function isQuKdsRow(array $row): bool
    {
        return preg_match('/\b(qu\s*kds|kds|kitchen\s*display)\b/i', self::classificationText($row)) === 1;
    }

    private static function isQuOrbRow(array $row): bool
    {
        return preg_match('/\b(qu\s*orb|orb|order\s*ready\s*board)\b/i', self::classificationText($row)) === 1;
    }

    private static function classificationText(array $row): string
    {
        $fields = [
            'Terminal Type',
            'Computer Name',
            'Terminal Name',
            'Name',
            'Terminal ID',
            'Current Version',
        ];
        $values = [];
        foreach ($fields as $field) {
            $values[] = (string)($row[$field] ?? '');
        }
        return implode(' ', $values);
    }

    private static function releaseTrain(string $version): string
    {
        $parts = explode('.', $version);
        return count($parts) >= 3 ? implode('.', array_slice($parts, 0, 3)) : $version;
    }

    private static function latestSeen(array $rows): string
    {
        $values = array_values(array_filter(array_map(static fn(array $row): string => (string)($row['Last Seen Online'] ?? ''), $rows)));
        rsort($values, SORT_STRING);
        return $values[0] ?? '';
    }

    private static function groupBy(array $rows, string $field): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = trim((string)($row[$field] ?? ''));
            if ($key === '') {
                $key = '(blank)';
            }
            $groups[$key][] = $row;
        }
        return $groups;
    }

    private static function uniqueJoin(array $rows, string $field): string
    {
        $values = array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string)($row[$field] ?? '')), $rows))));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        return implode(', ', $values);
    }

    private static function uniqueCount(array $rows, string $field): int
    {
        return count(array_unique(array_filter(array_map(static fn(array $row): string => trim((string)($row[$field] ?? '')), $rows))));
    }

    private static function parseCsvDate(string $value): ?int
    {
        $clean = preg_replace('/\s+America\/[A-Za-z_]+$/', '', trim($value));
        if ($clean === '') {
            return null;
        }
        $time = strtotime($clean);
        return $time === false ? null : $time;
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }
}
