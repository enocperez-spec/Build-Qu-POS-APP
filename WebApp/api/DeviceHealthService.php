<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class DeviceHealthService
{
    public const DEFAULT_DAYS = 30;
    private const HEALTHY_MAX_HOURS = 48;
    private const WARNING_MAX_HOURS = 168;
    private const STATUS_HEALTHY = 'H';
    private const STATUS_WARNING = 'W';
    private const STATUS_CRITICAL = 'C';
    private const STATUS_OFFLINE = 'O';

    public static function dashboard(PDO $pdo, array $filters = [], int $days = self::DEFAULT_DAYS): array
    {
        $model = self::loadModel($pdo, $days);
        return self::dashboardFromModel($model, $filters);
    }

    public static function fixtureDashboard(array $snapshots, array $storeMetadata = [], array $filters = []): array
    {
        return self::dashboardFromModel(self::buildFixtureModel($snapshots, $storeMetadata), $filters);
    }

    private static function dashboardFromModel(array $model, array $filters): array
    {
        $stores = self::filteredStores($model, $filters);
        $snapshotCount = count($model['snapshots']);
        $statusCounts = self::emptyStatusCounts();
        $productCounts = [];
        $storeRows = [];
        $healthyChecks = 0;
        $totalChecks = 0;
        $storesAt100 = 0;
        $storesBelow95 = 0;
        $trendHealthy = array_fill(0, $snapshotCount, 0);
        $trendExpected = array_fill(0, $snapshotCount, 0);

        foreach ($stores as $store) {
            $summary = self::summarizeStore($store, $model);
            $storeRows[] = $summary;
            $healthyChecks += $summary['healthyChecks'];
            $totalChecks += $summary['totalChecks'];
            if ($summary['healthScore'] !== null && $summary['healthScore'] >= 99.999) {
                $storesAt100++;
            }
            if ($summary['healthScore'] !== null && $summary['healthScore'] < 95) {
                $storesBelow95++;
            }

            foreach ($store['devices'] as $device) {
                $currentCode = self::currentStatusCode($device);
                $statusCounts[self::statusKey($currentCode)]++;
                $product = $device['product'];
                if (!isset($productCounts[$product])) {
                    $productCounts[$product] = self::emptyStatusCounts() + ['total' => 0];
                }
                $productCounts[$product]['total']++;
                $productCounts[$product][self::statusKey($currentCode)]++;

                $statuses = (string)$device['statuses'];
                $firstIndex = (int)$device['firstSnapshotIndex'];
                for ($offset = 0, $length = strlen($statuses); $offset < $length; $offset++) {
                    $index = $firstIndex + $offset;
                    if ($index >= $snapshotCount) {
                        break;
                    }
                    $trendExpected[$index]++;
                    if ($statuses[$offset] === self::STATUS_HEALTHY) {
                        $trendHealthy[$index]++;
                    }
                }
            }
        }

        usort($storeRows, static fn(array $left, array $right): int =>
            ($left['healthScore'] ?? -1) <=> ($right['healthScore'] ?? -1)
                ?: strnatcasecmp((string)$left['storeName'], (string)$right['storeName'])
        );

        $trend = [];
        foreach ($model['snapshots'] as $index => $snapshot) {
            $trend[] = [
                'timestamp' => $snapshot['uploadedAt'],
                'label' => date('M j, g:i A', strtotime($snapshot['uploadedAt'])),
                'score' => self::percentage($trendHealthy[$index], $trendExpected[$index]),
                'healthyChecks' => $trendHealthy[$index],
                'totalChecks' => $trendExpected[$index],
            ];
        }

        $stableVersions = self::stableAdoption($stores, $model['stableVersions']);
        $issuesByType = [];
        foreach (self::orderedProducts(array_keys($productCounts)) as $product) {
            $counts = $productCounts[$product];
            $stable = $stableVersions[$product] ?? ['version' => null, 'reportingDevices' => 0, 'stableDevices' => 0, 'usagePercent' => null];
            $issuesByType[] = [
                'product' => $product,
                'label' => self::productLabel($product),
                'total' => $counts['total'],
                'healthy' => $counts['healthy'],
                'warning' => $counts['warning'],
                'critical' => $counts['critical'],
                'offline' => $counts['offline'],
                'stableVersion' => $stable['version'],
                'stableDevices' => $stable['stableDevices'],
                'reportingDevices' => $stable['reportingDevices'],
                'stableUsagePercent' => $stable['usagePercent'],
            ];
        }

        $downNow = $statusCounts['warning'] + $statusCounts['critical'] + $statusCounts['offline'];
        return [
            'period' => self::periodPayload($model),
            'filters' => self::filterPayload($filters),
            'availableBrands' => self::availableBrands($model['stores']),
            'summary' => [
                'fleetHealthScore' => self::percentage($healthyChecks, $totalChecks),
                'totalStores' => count($stores),
                'totalDevices' => array_sum($statusCounts),
                'storesAt100' => $storesAt100,
                'storesBelow95' => $storesBelow95,
                'devicesDownNow' => $downNow,
                'quboxDown' => self::downCount($productCounts['qubox'] ?? null),
                'kioskDown' => self::downCount($productCounts['kiosk'] ?? null),
                'healthy' => $statusCounts['healthy'],
                'warning' => $statusCounts['warning'],
                'critical' => $statusCounts['critical'],
                'offline' => $statusCounts['offline'],
                'healthyChecks' => $healthyChecks,
                'totalChecks' => $totalChecks,
            ],
            'trend' => $trend,
            'worstStores' => array_slice($storeRows, 0, 5),
            'stores' => $storeRows,
            'issuesByType' => $issuesByType,
            'methodology' => self::methodology(),
        ];
    }

    public static function store(PDO $pdo, string $storeId, int $days = self::DEFAULT_DAYS): array
    {
        $model = self::loadModel($pdo, $days);
        $resolvedId = self::resolveRequestedStoreId($model['stores'], $storeId);
        if ($resolvedId === null) {
            throw new RuntimeException('Store was not found in the selected device-health period.');
        }
        $store = $model['stores'][$resolvedId];
        $summary = self::summarizeStore($store, $model);
        $devices = [];
        foreach ($store['devices'] as $device) {
            $healthyChecks = substr_count((string)$device['statuses'], self::STATUS_HEALTHY);
            $totalChecks = strlen((string)$device['statuses']);
            $currentStatus = self::statusPayload(self::currentStatusCode($device));
            $stableVersion = $model['stableVersions'][$device['product']]['version'] ?? null;
            $devices[] = [
                'deviceId' => $device['deviceId'],
                'label' => $device['label'],
                'product' => $device['product'],
                'productLabel' => self::productLabel($device['product']),
                'currentStatus' => $currentStatus,
                'currentVersion' => $device['currentVersion'] ?: null,
                'stableVersion' => $stableVersion,
                'stableStatus' => self::stableStatus((string)$device['currentVersion'], (string)$stableVersion),
                'lastSeen' => $device['lastSeen'] ?: null,
                'computerName' => $device['computerName'] ?: null,
                'networkAddress' => $device['networkAddress'] ?: null,
                'healthScore' => self::percentage($healthyChecks, $totalChecks),
                'healthyChecks' => $healthyChecks,
                'totalChecks' => $totalChecks,
                'impact' => self::impact(self::percentage($healthyChecks, $totalChecks)),
                'isInferred' => (bool)$device['isInferred'],
                'dataIssue' => $device['dataIssue'] ?: null,
            ];
        }
        usort($devices, static fn(array $left, array $right): int =>
            self::productOrder((string)$left['product']) <=> self::productOrder((string)$right['product'])
                ?: strnatcasecmp((string)$left['label'], (string)$right['label'])
        );

        $timeline = self::storeTimeline($store, $model);
        $missingFields = [];
        if (!$store['operationalStatus']) {
            $missingFields[] = 'Store Information Status';
        }
        if (!$store['brands']) {
            $missingFields[] = 'Store Information Brand';
        }
        if (array_filter($devices, static fn(array $device): bool => $device['dataIssue'] !== null)) {
            $missingFields[] = 'Last Seen Online for one or more devices';
        }

        return [
            'period' => self::periodPayload($model),
            'store' => [
                'storeId' => $summary['storeId'],
                'storeName' => $summary['storeName'],
                'brands' => $summary['brands'],
                'operationalStatus' => $summary['operationalStatus'] ?: null,
                'healthScore' => $summary['healthScore'],
                'expectedDevices' => $summary['totalDevices'],
                'healthy' => $summary['healthy'],
                'warning' => $summary['warning'],
                'critical' => $summary['critical'],
                'offline' => $summary['offline'],
                'downOrStale' => $summary['downDevices'],
                'snapshotCount' => count($model['snapshots']),
                'lastGoodSnapshot' => $summary['lastGoodSnapshot'],
            ],
            'devices' => $devices,
            'timeline' => $timeline,
            'stableVersions' => $model['stableVersions'],
            'missingData' => array_values(array_unique($missingFields)),
            'methodology' => self::methodology(),
        ];
    }

    public static function searchStores(PDO $pdo, string $query, int $days = self::DEFAULT_DAYS): array
    {
        $model = self::loadModel($pdo, $days);
        $needle = self::normalizeSearch($query);
        if ($needle === '') {
            return [];
        }
        $matches = [];
        foreach ($model['stores'] as $store) {
            $haystack = self::normalizeSearch(implode(' ', [
                $store['storeId'],
                $store['storeName'],
                implode(' ', $store['brands']),
            ]));
            if (str_contains($haystack, $needle)) {
                $matches[] = [
                    'storeId' => $store['storeId'],
                    'storeName' => $store['storeName'],
                    'brands' => $store['brands'],
                    'operationalStatus' => $store['operationalStatus'] ?: null,
                ];
            }
        }
        usort($matches, static fn(array $left, array $right): int => strnatcasecmp((string)$left['storeName'], (string)$right['storeName']));
        return array_slice($matches, 0, 20);
    }

    public static function buildFixtureModel(array $snapshots, array $storeMetadata = []): array
    {
        $uploads = array_map(static fn(array $snapshot): array => [
            'id' => (int)($snapshot['id'] ?? 0),
            'filename' => (string)($snapshot['filename'] ?? 'fixture.csv'),
            'uploadedAt' => (string)$snapshot['uploadedAt'],
        ], $snapshots);
        return self::buildModel($uploads, static function (array $upload) use ($snapshots): array {
            foreach ($snapshots as $snapshot) {
                if ((int)($snapshot['id'] ?? 0) === (int)$upload['id']) {
                    return $snapshot['rows'] ?? [];
                }
            }
            return [];
        }, $storeMetadata, self::DEFAULT_DAYS);
    }

    private static function loadModel(PDO $pdo, int $days): array
    {
        Database::initialize($pdo);
        $days = self::normalizeDays($days);
        $uploads = self::snapshotUploads($pdo, $days);
        if (!$uploads) {
            return self::emptyModel($days);
        }
        $storeImportId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM store_imports')->fetchColumn();
        $first = $uploads[0]['id'];
        $last = $uploads[count($uploads) - 1]['id'];
        $cacheKey = "device-health:$days:$first:$last:$storeImportId:v1";
        $cacheStatement = $pdo->prepare('SELECT payload FROM device_health_cache WHERE cache_key = :cache_key LIMIT 1');
        $cacheStatement->execute([':cache_key' => $cacheKey]);
        $cached = $cacheStatement->fetchColumn();
        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $rowStatement = $pdo->prepare(
            'SELECT `row_number`, store_id, store_name, terminal_id, computer_name,
                    network_address, serial_number, terminal_type, current_version, last_seen_online
             FROM terminal_rows
             WHERE upload_id = :upload_id
             ORDER BY id ASC'
        );
        $metadata = Database::latestStoreMetadataMap($pdo);
        $model = self::buildModel($uploads, static function (array $upload) use ($rowStatement): array {
            $rowStatement->execute([':upload_id' => $upload['id']]);
            return $rowStatement->fetchAll();
        }, $metadata, $days);
        $encoded = json_encode($model, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            $saveStatement = $pdo->prepare(
                'INSERT INTO device_health_cache (cache_key, payload, created_at)
                 VALUES (:cache_key, :payload, :created_at)
                 ON DUPLICATE KEY UPDATE payload = VALUES(payload), created_at = VALUES(created_at)'
            );
            $saveStatement->execute([
                ':cache_key' => $cacheKey,
                ':payload' => $encoded,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $model;
    }

    private static function snapshotUploads(PDO $pdo, int $days): array
    {
        $latest = $pdo->query('SELECT MAX(uploaded_at) FROM csv_uploads')->fetchColumn();
        if (!$latest) {
            return [];
        }
        $timezone = new DateTimeZone('America/New_York');
        $end = new DateTimeImmutable((string)$latest, $timezone);
        $start = $end->modify('-' . $days . ' days');
        $statement = $pdo->prepare(
            'SELECT id, original_filename, uploaded_at
             FROM csv_uploads
             WHERE uploaded_at >= :period_start AND uploaded_at <= :period_end
             ORDER BY uploaded_at ASC, id ASC'
        );
        $statement->execute([
            ':period_start' => $start->format('Y-m-d H:i:s'),
            ':period_end' => $end->format('Y-m-d H:i:s'),
        ]);
        return array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'filename' => (string)$row['original_filename'],
            'uploadedAt' => (new DateTimeImmutable((string)$row['uploaded_at'], $timezone))->format(DATE_ATOM),
        ], $statement->fetchAll());
    }

    private static function buildModel(array $uploads, callable $rowProvider, array $metadata, int $periodDays): array
    {
        if (!$uploads) {
            return self::emptyModel(self::DEFAULT_DAYS);
        }
        $stores = [];
        $metadataNameIndex = self::metadataNameIndex($metadata);

        foreach ($uploads as $snapshotIndex => $upload) {
            foreach ($stores as &$existingStore) {
                foreach ($existingStore['devices'] as &$existingDevice) {
                    $existingDevice['statuses'] .= self::STATUS_OFFLINE;
                }
                unset($existingDevice);
            }
            unset($existingStore);

            $rows = $rowProvider($upload);
            foreach ($rows as $row) {
                $normalizedRow = self::normalizeRow($row);
                $storeId = self::resolveStoreId($normalizedRow['storeId'], $normalizedRow['storeName']);
                if ($storeId === '') {
                    continue;
                }
                $product = self::classifyProduct($normalizedRow);
                $deviceIdentity = self::deviceIdentity($normalizedRow, $product);
                if ($deviceIdentity === '') {
                    continue;
                }
                if (!isset($stores[$storeId])) {
                    $metadataRow = self::metadataForStore($storeId, $normalizedRow['storeName'], $metadata, $metadataNameIndex);
                    $stores[$storeId] = [
                        'storeId' => $storeId,
                        'storeName' => $normalizedRow['storeName'] ?: ($metadataRow['storeName'] ?? "Store $storeId"),
                        'brands' => self::uniqueText(array_merge(
                            $metadataRow['brands'] ?? [],
                            self::brandsFromText($normalizedRow['storeName'])
                        )),
                        'operationalStatus' => trim((string)($metadataRow['status'] ?? '')),
                        'devices' => [],
                    ];
                }
                $deviceKey = $product . '|' . $deviceIdentity;
                $status = self::observationStatus($normalizedRow['lastSeen'], $upload['uploadedAt']);
                if (!isset($stores[$storeId]['devices'][$deviceKey])) {
                    $stores[$storeId]['devices'][$deviceKey] = [
                        'deviceId' => $deviceIdentity,
                        'label' => self::deviceLabel($normalizedRow, $product, $deviceIdentity),
                        'product' => $product,
                        'firstSnapshotIndex' => $snapshotIndex,
                        'statuses' => $status['code'],
                        'currentVersion' => $normalizedRow['currentVersion'],
                        'lastSeen' => $normalizedRow['lastSeen'],
                        'lastSeenTimestamp' => $status['lastSeenTimestamp'],
                        'computerName' => $normalizedRow['computerName'],
                        'networkAddress' => $normalizedRow['networkAddress'],
                        'isInferred' => false,
                        'dataIssue' => $status['dataIssue'],
                    ];
                    continue;
                }

                $device = &$stores[$storeId]['devices'][$deviceKey];
                $lastOffset = strlen((string)$device['statuses']) - 1;
                $existingCode = $lastOffset >= 0 ? $device['statuses'][$lastOffset] : self::STATUS_OFFLINE;
                if (self::statusRank($status['code']) <= self::statusRank($existingCode)) {
                    $device['statuses'][$lastOffset] = $status['code'];
                    $device['currentVersion'] = $normalizedRow['currentVersion'];
                    $device['lastSeen'] = $normalizedRow['lastSeen'];
                    $device['lastSeenTimestamp'] = $status['lastSeenTimestamp'];
                    $device['computerName'] = $normalizedRow['computerName'];
                    $device['networkAddress'] = $normalizedRow['networkAddress'];
                    $device['dataIssue'] = $status['dataIssue'];
                }
                unset($device);
            }
            unset($rows);
        }

        $snapshotCount = count($uploads);
        foreach ($stores as &$store) {
            $hasPos = false;
            $hasQuBox = false;
            foreach ($store['devices'] as $device) {
                $hasPos = $hasPos || $device['product'] === 'pos';
                $hasQuBox = $hasQuBox || $device['product'] === 'qubox';
            }
            if ($hasPos && !$hasQuBox && !self::isNotOperational((string)$store['operationalStatus'])) {
                $store['devices']['qubox|required'] = [
                    'deviceId' => 'required-qubox',
                    'label' => 'Required QuBox',
                    'product' => 'qubox',
                    'firstSnapshotIndex' => 0,
                    'statuses' => str_repeat(self::STATUS_OFFLINE, $snapshotCount),
                    'currentVersion' => '',
                    'lastSeen' => '',
                    'lastSeenTimestamp' => null,
                    'computerName' => '',
                    'networkAddress' => '',
                    'isInferred' => true,
                    'dataIssue' => 'Required QuBox was not found in any snapshot during the selected period.',
                ];
            }
        }
        unset($store);

        ksort($stores, SORT_NATURAL);
        return [
            'generatedAt' => date('c'),
            'periodDays' => $periodDays,
            'snapshots' => $uploads,
            'stores' => $stores,
            'stableVersions' => self::stableVersions($stores),
        ];
    }

    private static function summarizeStore(array $store, array $model): array
    {
        $counts = self::emptyStatusCounts();
        $healthyChecks = 0;
        $totalChecks = 0;
        foreach ($store['devices'] as $device) {
            $counts[self::statusKey(self::currentStatusCode($device))]++;
            $healthyChecks += substr_count((string)$device['statuses'], self::STATUS_HEALTHY);
            $totalChecks += strlen((string)$device['statuses']);
        }
        return [
            'storeId' => $store['storeId'],
            'storeName' => $store['storeName'],
            'brands' => $store['brands'],
            'operationalStatus' => $store['operationalStatus'] ?: null,
            'healthScore' => self::percentage($healthyChecks, $totalChecks),
            'totalDevices' => array_sum($counts),
            'healthy' => $counts['healthy'],
            'warning' => $counts['warning'],
            'critical' => $counts['critical'],
            'offline' => $counts['offline'],
            'downDevices' => $counts['warning'] + $counts['critical'] + $counts['offline'],
            'healthyChecks' => $healthyChecks,
            'totalChecks' => $totalChecks,
            'lastGoodSnapshot' => self::lastGoodSnapshot($store, $model),
        ];
    }

    private static function storeTimeline(array $store, array $model): array
    {
        $daily = [];
        foreach ($model['snapshots'] as $index => $snapshot) {
            $expected = 0;
            $counts = self::emptyStatusCounts();
            foreach ($store['devices'] as $device) {
                $offset = $index - (int)$device['firstSnapshotIndex'];
                if ($offset < 0 || $offset >= strlen((string)$device['statuses'])) {
                    continue;
                }
                $expected++;
                $counts[self::statusKey($device['statuses'][$offset])]++;
            }
            $date = date('Y-m-d', strtotime($snapshot['uploadedAt']));
            if (!isset($daily[$date])) {
                $daily[$date] = self::emptyStatusCounts() + ['snapshotCount' => 0, 'expectedChecks' => 0, 'scoreTotal' => 0.0, 'scoreSamples' => 0];
            }
            $daily[$date]['snapshotCount']++;
            $daily[$date]['expectedChecks'] += $expected;
            foreach (array_keys(self::emptyStatusCounts()) as $key) {
                $daily[$date][$key] += $counts[$key];
            }
            $score = self::percentage($counts['healthy'], $expected);
            if ($score !== null) {
                $daily[$date]['scoreTotal'] += $score;
                $daily[$date]['scoreSamples']++;
            }
        }

        $timeline = [];
        foreach ($daily as $date => $day) {
            $statusCode = $day['offline'] > 0
                ? self::STATUS_OFFLINE
                : ($day['critical'] > 0 ? self::STATUS_CRITICAL : ($day['warning'] > 0 ? self::STATUS_WARNING : self::STATUS_HEALTHY));
            $timeline[] = [
                'date' => $date,
                'label' => date('M j', strtotime($date)),
                'status' => self::statusPayload($statusCode),
                'snapshotCount' => $day['snapshotCount'],
                'score' => $day['scoreSamples'] ? round($day['scoreTotal'] / $day['scoreSamples'], 1) : null,
                'healthyChecks' => $day['healthy'],
                'warningChecks' => $day['warning'],
                'criticalChecks' => $day['critical'],
                'offlineChecks' => $day['offline'],
                'totalChecks' => $day['expectedChecks'],
            ];
        }
        return $timeline;
    }

    private static function filteredStores(array $model, array $filters): array
    {
        return array_filter($model['stores'], static function (array $store) use ($filters): bool {
            $mode = (string)($filters['mode'] ?? 'all');
            $brands = $store['brands'];
            if ($mode === 'brand' && !in_array((string)($filters['brand'] ?? ''), $brands, true)) {
                return false;
            }
            if ($mode === 'cobranded') {
                if (count($brands) < 2) {
                    return false;
                }
                $combination = trim((string)($filters['combination'] ?? ''));
                if ($combination !== '' && self::brandCombination($brands) !== $combination) {
                    return false;
                }
            }
            if ($mode === 'combination') {
                $selected = self::uniqueText($filters['selectedBrands'] ?? []);
                if (!$selected) {
                    return false;
                }
                $match = (string)($filters['match'] ?? 'any');
                $intersection = array_intersect($selected, $brands);
                if ($match === 'all' && count($intersection) !== count($selected)) {
                    return false;
                }
                if ($match === 'exact' && (count($brands) !== count($selected) || count($intersection) !== count($selected))) {
                    return false;
                }
                if ($match === 'any' && !$intersection) {
                    return false;
                }
            }
            $query = self::normalizeSearch((string)($filters['query'] ?? ''));
            if ($query !== '') {
                $haystack = self::normalizeSearch(implode(' ', [$store['storeId'], $store['storeName'], implode(' ', $brands)]));
                if (!str_contains($haystack, $query)) {
                    return false;
                }
            }
            return true;
        });
    }

    private static function stableVersions(array $stores): array
    {
        $versionCounts = [];
        $reportingCounts = [];
        foreach ($stores as $store) {
            foreach ($store['devices'] as $device) {
                if (self::currentStatusCode($device) === self::STATUS_OFFLINE) {
                    continue;
                }
                $product = $device['product'];
                $reportingCounts[$product] = ($reportingCounts[$product] ?? 0) + 1;
                $version = trim((string)$device['currentVersion']);
                if ($version !== '') {
                    $versionCounts[$product][$version] = ($versionCounts[$product][$version] ?? 0) + 1;
                }
            }
        }
        $stable = [];
        foreach (array_unique(array_merge(array_keys($reportingCounts), array_keys($versionCounts))) as $product) {
            $versions = $versionCounts[$product] ?? [];
            uksort($versions, static function (string $left, string $right) use ($versions): int {
                return $versions[$right] <=> $versions[$left] ?: self::compareVersionStrings($right, $left);
            });
            $stableVersion = array_key_first($versions);
            $stableDevices = $stableVersion !== null ? (int)$versions[$stableVersion] : 0;
            $reporting = (int)($reportingCounts[$product] ?? 0);
            $stable[$product] = [
                'version' => $stableVersion,
                'stableDevices' => $stableDevices,
                'reportingDevices' => $reporting,
                'usagePercent' => self::percentage($stableDevices, $reporting),
            ];
        }
        return $stable;
    }

    private static function stableAdoption(array $stores, array $globalStableVersions): array
    {
        $counts = [];
        foreach ($stores as $store) {
            foreach ($store['devices'] as $device) {
                if (self::currentStatusCode($device) === self::STATUS_OFFLINE) {
                    continue;
                }
                $product = $device['product'];
                if (!isset($counts[$product])) {
                    $counts[$product] = ['reportingDevices' => 0, 'stableDevices' => 0];
                }
                $counts[$product]['reportingDevices']++;
                $stableVersion = (string)($globalStableVersions[$product]['version'] ?? '');
                if ($stableVersion !== '' && (string)$device['currentVersion'] === $stableVersion) {
                    $counts[$product]['stableDevices']++;
                }
            }
        }
        $result = [];
        foreach ($globalStableVersions as $product => $stable) {
            $reporting = (int)($counts[$product]['reportingDevices'] ?? 0);
            $stableDevices = (int)($counts[$product]['stableDevices'] ?? 0);
            $result[$product] = [
                'version' => $stable['version'],
                'reportingDevices' => $reporting,
                'stableDevices' => $stableDevices,
                'usagePercent' => self::percentage($stableDevices, $reporting),
            ];
        }
        return $result;
    }

    private static function normalizeRow(array $row): array
    {
        $value = static function (array $names) use ($row): string {
            foreach ($names as $name) {
                if (array_key_exists($name, $row) && trim((string)$row[$name]) !== '') {
                    return trim((string)$row[$name]);
                }
            }
            return '';
        };
        return [
            'rowNumber' => $value(['row_number', 'Row Number']),
            'storeId' => $value(['store_id', 'Store ID']),
            'storeName' => $value(['store_name', 'Store Name']),
            'terminalId' => $value(['terminal_id', 'Terminal ID']),
            'computerName' => $value(['computer_name', 'Computer Name']),
            'networkAddress' => $value(['network_address', 'Network Address']),
            'serialNumber' => $value(['serial_number', 'Serial Number']),
            'terminalType' => $value(['terminal_type', 'Terminal Type']),
            'currentVersion' => $value(['current_version', 'Current Version']),
            'lastSeen' => $value(['last_seen_online', 'Last Seen Online']),
        ];
    }

    private static function classifyProduct(array $row): string
    {
        $text = strtolower(implode(' ', [$row['terminalType'], $row['computerName'], $row['currentVersion']]));
        if (preg_match('/qu\s*box|\bqubox\b/', $text) || preg_match('/^3\.6\.\d+-\d+$/', $row['currentVersion'])) {
            return 'qubox';
        }
        if (str_contains($text, 'kiosk') || preg_match('/^4\.1\.\d+-\d+$/', $row['currentVersion'])) {
            return 'kiosk';
        }
        if (preg_match('/qu\s*kds|\bkds\b|kitchen\s*display/', $text) || preg_match('/^4\.0\.\d+-\d+$/', $row['currentVersion'])) {
            return 'qukds';
        }
        if (preg_match('/qu\s*orb|\borb\b|order\s*ready\s*board|orderreadyboard/', $text)) {
            return 'quorb';
        }
        if (strcasecmp($row['terminalType'], 'POS') === 0 || preg_match('/^\d+\.\d+\.\d+\.\d+$/', $row['currentVersion'])) {
            return 'pos';
        }
        return 'other';
    }

    private static function deviceIdentity(array $row, string $product): string
    {
        foreach ([$row['terminalId'], $row['computerName'], $row['networkAddress'], $row['serialNumber']] as $candidate) {
            $normalized = preg_replace('/[^a-z0-9:.\-_]+/i', '', strtolower(trim((string)$candidate))) ?? '';
            if ($normalized !== '') {
                return $normalized;
            }
        }
        $fallback = trim($row['rowNumber']);
        return $fallback !== '' ? $product . '-row-' . $fallback : '';
    }

    private static function deviceLabel(array $row, string $product, string $identity): string
    {
        if ($product === 'pos') {
            if (preg_match('/(?:^|[^a-z0-9])t(?:erminal)?[-_ ]?(\d{1,2})(?:\b|$)/i', $row['computerName'], $matches)) {
                return 'Terminal ' . (int)$matches[1];
            }
            if (preg_match('/192\.168\.22\.(11[1-7])$/', $row['networkAddress'], $matches)) {
                return 'Terminal ' . ((int)$matches[1] - 110);
            }
        }
        $base = self::productLabel($product);
        if ($row['computerName'] !== '') {
            return $base . ' - ' . $row['computerName'];
        }
        return $base . ' - ' . $identity;
    }

    private static function observationStatus(string $lastSeen, string $snapshotAt): array
    {
        $lastSeenTimestamp = self::parseDate($lastSeen);
        $snapshotTimestamp = self::parseDate($snapshotAt);
        if ($lastSeenTimestamp === null || $snapshotTimestamp === null) {
            return [
                'code' => self::STATUS_CRITICAL,
                'lastSeenTimestamp' => $lastSeenTimestamp,
                'dataIssue' => 'Last Seen Online is missing or could not be parsed.',
            ];
        }
        $ageHours = max(0, ($snapshotTimestamp - $lastSeenTimestamp) / 3600);
        if ($ageHours <= self::HEALTHY_MAX_HOURS) {
            $code = self::STATUS_HEALTHY;
        } elseif ($ageHours <= self::WARNING_MAX_HOURS) {
            $code = self::STATUS_WARNING;
        } else {
            $code = self::STATUS_CRITICAL;
        }
        return ['code' => $code, 'lastSeenTimestamp' => $lastSeenTimestamp, 'dataIssue' => null];
    }

    private static function parseDate(string $value): ?int
    {
        $clean = trim($value);
        if ($clean === '') {
            return null;
        }
        try {
            if (preg_match('/\s+(America\/[A-Za-z_]+)$/', $clean, $matches)) {
                $timezone = new DateTimeZone($matches[1]);
                $withoutZone = trim(substr($clean, 0, -strlen($matches[0])));
                return (new DateTimeImmutable($withoutZone, $timezone))->getTimestamp();
            }
            return (new DateTimeImmutable($clean, new DateTimeZone('America/New_York')))->getTimestamp();
        } catch (Throwable) {
            return null;
        }
    }

    private static function metadataNameIndex(array $metadata): array
    {
        $index = [];
        foreach ($metadata as $row) {
            $name = self::normalizeSearch((string)($row['storeName'] ?? ''));
            if ($name !== '' && !isset($index[$name])) {
                $index[$name] = $row;
            }
        }
        return $index;
    }

    private static function metadataForStore(string $storeId, string $storeName, array $metadata, array $nameIndex): array
    {
        if (isset($metadata[$storeId])) {
            return $metadata[$storeId];
        }
        return $nameIndex[self::normalizeSearch($storeName)] ?? [];
    }

    private static function resolveStoreId(string $storeId, string $storeName): string
    {
        $clean = trim($storeId);
        if ($clean !== '') {
            return $clean;
        }
        return preg_match('/^([0-9]{3,})(?:\b|[-_\s])/', trim($storeName), $matches) ? $matches[1] : '';
    }

    private static function resolveRequestedStoreId(array $stores, string $query): ?string
    {
        $trimmed = trim($query);
        if (isset($stores[$trimmed])) {
            return $trimmed;
        }
        $needle = self::normalizeSearch($trimmed);
        foreach ($stores as $storeId => $store) {
            if (self::normalizeSearch((string)$store['storeName']) === $needle) {
                return (string)$storeId;
            }
        }
        return null;
    }

    private static function brandsFromText(string $text): array
    {
        $patterns = [
            'Auntie Anne\'s' => '/\b(auntie anne\'?s|aa[-_\s])/i',
            'Carvel' => '/\b(carvel|cv[-_\s]|\[cv\])\b/i',
            'Cinnabon' => '/\b(cinnabon|cb[-_\s])\b/i',
            'Jamba' => '/\b(jamba|ja[-_\s])\b/i',
            'McAlister\'s' => '/\b(mcalister\'?s|mca[-_\s])\b/i',
            'Moe\'s' => '/\b(moe\'?s|moe[-_\s])\b/i',
            'Schlotzsky\'s' => '/\b(schlotzsky\'?s|sch[-_\s])\b/i',
        ];
        $brands = [];
        foreach ($patterns as $brand => $pattern) {
            if (preg_match($pattern, $text)) {
                $brands[] = $brand;
            }
        }
        return $brands;
    }

    private static function availableBrands(array $stores): array
    {
        $brands = [];
        foreach ($stores as $store) {
            $brands = array_merge($brands, $store['brands']);
        }
        $brands = self::uniqueText($brands);
        natcasesort($brands);
        return array_values($brands);
    }

    private static function uniqueText(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            $trimmed = trim((string)$value);
            if ($trimmed !== '') {
                $unique[strtolower($trimmed)] = $trimmed;
            }
        }
        return array_values($unique);
    }

    private static function periodPayload(array $model): array
    {
        $snapshots = $model['snapshots'];
        return [
            'days' => $model['periodDays'] ?: self::DEFAULT_DAYS,
            'snapshotCount' => count($snapshots),
            'start' => $snapshots[0]['uploadedAt'] ?? null,
            'end' => $snapshots[count($snapshots) - 1]['uploadedAt'] ?? null,
            'latestFilename' => $snapshots[count($snapshots) - 1]['filename'] ?? null,
            'generatedAt' => $model['generatedAt'],
        ];
    }

    private static function filterPayload(array $filters): array
    {
        return [
            'mode' => $filters['mode'] ?? 'all',
            'brand' => $filters['brand'] ?? '',
            'combination' => $filters['combination'] ?? '',
            'selectedBrands' => $filters['selectedBrands'] ?? [],
            'match' => $filters['match'] ?? 'any',
            'query' => $filters['query'] ?? '',
        ];
    }

    private static function methodology(): array
    {
        return [
            'score' => 'Healthy device checks divided by all expected device checks across the selected historical CSV snapshots.',
            'identity' => 'Distinct devices use Store ID plus Terminal ID, with Computer Name, Network Address, Serial Number, and row number as ordered fallbacks.',
            'healthy' => 'Device is present in a snapshot and Last Seen Online is no more than 48 hours old at that snapshot.',
            'warning' => 'Device is present and Last Seen Online is more than 48 hours but no more than 7 days old.',
            'critical' => 'Device is present but Last Seen Online is more than 7 days old, missing, or not parseable.',
            'offline' => 'An expected device is absent from a snapshot. A required QuBox is also inferred for POS stores when no QuBox appears in the selected period.',
            'expected' => 'A device becomes expected from its first observed snapshot forward. This avoids penalizing a store before a newly installed device first reports.',
            'sources' => ['csv_uploads.uploaded_at', 'terminal_rows.Store ID', 'Terminal ID', 'Computer Name', 'Network Address', 'Terminal Type', 'Current Version', 'Last Seen Online', 'latest Store Information Brand and Status'],
        ];
    }

    private static function lastGoodSnapshot(array $store, array $model): ?string
    {
        for ($index = count($model['snapshots']) - 1; $index >= 0; $index--) {
            $expected = 0;
            $healthy = 0;
            foreach ($store['devices'] as $device) {
                $offset = $index - (int)$device['firstSnapshotIndex'];
                if ($offset < 0 || $offset >= strlen((string)$device['statuses'])) {
                    continue;
                }
                $expected++;
                if ($device['statuses'][$offset] === self::STATUS_HEALTHY) {
                    $healthy++;
                }
            }
            if ($expected > 0 && $expected === $healthy) {
                return $model['snapshots'][$index]['uploadedAt'];
            }
        }
        return null;
    }

    private static function currentStatusCode(array $device): string
    {
        $statuses = (string)$device['statuses'];
        return $statuses !== '' ? $statuses[strlen($statuses) - 1] : self::STATUS_OFFLINE;
    }

    private static function statusPayload(string $code): array
    {
        return match ($code) {
            self::STATUS_HEALTHY => ['key' => 'healthy', 'label' => 'Healthy'],
            self::STATUS_WARNING => ['key' => 'warning', 'label' => 'Warning'],
            self::STATUS_CRITICAL => ['key' => 'critical', 'label' => 'Critical'],
            default => ['key' => 'offline', 'label' => 'Offline'],
        };
    }

    private static function statusKey(string $code): string
    {
        return self::statusPayload($code)['key'];
    }

    private static function statusRank(string $code): int
    {
        return match ($code) {
            self::STATUS_HEALTHY => 0,
            self::STATUS_WARNING => 1,
            self::STATUS_CRITICAL => 2,
            default => 3,
        };
    }

    private static function emptyStatusCounts(): array
    {
        return ['healthy' => 0, 'warning' => 0, 'critical' => 0, 'offline' => 0];
    }

    private static function percentage(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : null;
    }

    private static function downCount(?array $counts): int
    {
        return $counts ? (int)$counts['warning'] + (int)$counts['critical'] + (int)$counts['offline'] : 0;
    }

    private static function productLabel(string $product): string
    {
        return match ($product) {
            'pos' => 'POS Terminal',
            'qubox' => 'QuBox',
            'kiosk' => 'Kiosk',
            'qukds' => 'QuKDS',
            'quorb' => 'QuORB',
            default => 'Other',
        };
    }

    private static function productOrder(string $product): int
    {
        $index = array_search($product, ['pos', 'qubox', 'kiosk', 'qukds', 'quorb', 'other'], true);
        return $index === false ? 99 : $index;
    }

    private static function orderedProducts(array $products): array
    {
        usort($products, static fn(string $left, string $right): int => self::productOrder($left) <=> self::productOrder($right));
        return $products;
    }

    private static function impact(?float $score): string
    {
        if ($score === null) {
            return 'Not Available';
        }
        if ($score >= 100) {
            return 'None';
        }
        if ($score >= 90) {
            return 'Low';
        }
        if ($score >= 50) {
            return 'Medium';
        }
        return 'High';
    }

    private static function stableStatus(string $version, string $stableVersion): array
    {
        if ($version === '' || $stableVersion === '') {
            return ['key' => 'unavailable', 'label' => 'Not Available'];
        }
        $comparison = self::compareVersionStrings($version, $stableVersion);
        if ($comparison === 0) {
            return ['key' => 'stable', 'label' => 'Stable'];
        }
        return $comparison < 0
            ? ['key' => 'outdated', 'label' => 'Out of Date']
            : ['key' => 'higher', 'label' => 'Higher'];
    }

    private static function compareVersionStrings(string $left, string $right): int
    {
        $leftParts = array_map('intval', preg_split('/[^0-9]+/', $left, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $rightParts = array_map('intval', preg_split('/[^0-9]+/', $right, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $length = max(count($leftParts), count($rightParts));
        for ($index = 0; $index < $length; $index++) {
            $difference = ($leftParts[$index] ?? 0) <=> ($rightParts[$index] ?? 0);
            if ($difference !== 0) {
                return $difference;
            }
        }
        return 0;
    }

    private static function brandCombination(array $brands): string
    {
        natcasesort($brands);
        return implode(' + ', $brands);
    }

    private static function normalizeSearch(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? ''));
    }

    private static function isNotOperational(string $status): bool
    {
        return str_contains(strtolower($status), 'not operational');
    }

    private static function normalizeDays(int $days): int
    {
        return in_array($days, [7, 30, 60, 90], true) ? $days : self::DEFAULT_DAYS;
    }

    private static function emptyModel(int $days): array
    {
        return [
            'generatedAt' => date('c'),
            'periodDays' => $days,
            'snapshots' => [],
            'stores' => [],
            'stableVersions' => [],
        ];
    }
}
