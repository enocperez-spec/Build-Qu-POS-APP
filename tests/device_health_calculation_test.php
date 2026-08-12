<?php
declare(strict_types=1);

require_once __DIR__ . '/../WebApp/api/DeviceHealthService.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertNear(float $expected, ?float $actual, string $message): void
{
    if ($actual === null || abs($expected - $actual) > 0.05) {
        throw new RuntimeException($message . ' Expected ' . $expected . ', got ' . var_export($actual, true));
    }
}

$snapshots = [
    [
        'id' => 1,
        'uploadedAt' => '2026-08-01T08:00:00-04:00',
        'rows' => [
            ['Store ID' => '100', 'Store Name' => 'AA-Test-One', 'Terminal ID' => '1', 'Computer Name' => 'QU100T1', 'Network Address' => '192.168.22.111', 'Terminal Type' => 'POS', 'Current Version' => '3.5.232.6451', 'Last Seen Online' => '08/01/2026 07:30 AM America/New_York'],
            ['Store ID' => '100', 'Store Name' => 'AA-Test-One', 'Terminal ID' => '2', 'Computer Name' => 'QU100T2', 'Network Address' => '192.168.22.112', 'Terminal Type' => 'POS', 'Current Version' => '3.5.232.6451', 'Last Seen Online' => '08/01/2026 07:31 AM America/New_York'],
            ['Store ID' => '100', 'Store Name' => 'AA-Test-One', 'Terminal ID' => '10', 'Computer Name' => 'QU100BOX', 'Network Address' => '192.168.22.10', 'Terminal Type' => 'QuBox', 'Current Version' => '3.6.952-6459', 'Last Seen Online' => '08/01/2026 07:32 AM America/New_York'],
            ['Store ID' => '200', 'Store Name' => 'CV-Test-Two', 'Terminal ID' => '20', 'Computer Name' => 'QU200T1', 'Network Address' => '192.168.22.111', 'Terminal Type' => 'POS', 'Current Version' => '3.5.232.6451', 'Last Seen Online' => '08/01/2026 07:25 AM America/New_York'],
        ],
    ],
    [
        'id' => 2,
        'uploadedAt' => '2026-08-04T08:00:00-04:00',
        'rows' => [
            ['Store ID' => '100', 'Store Name' => 'AA-Test-One', 'Terminal ID' => '1', 'Computer Name' => 'QU100T1', 'Network Address' => '192.168.22.111', 'Terminal Type' => 'POS', 'Current Version' => '3.5.232.6451', 'Last Seen Online' => '08/04/2026 07:30 AM America/New_York'],
            ['Store ID' => '100', 'Store Name' => 'AA-Test-One', 'Terminal ID' => '10', 'Computer Name' => 'QU100BOX', 'Network Address' => '192.168.22.10', 'Terminal Type' => 'QuBox', 'Current Version' => '3.6.952-6459', 'Last Seen Online' => '08/01/2026 07:32 AM America/New_York'],
            ['Store ID' => '200', 'Store Name' => 'CV-Test-Two', 'Terminal ID' => '20', 'Computer Name' => 'QU200T1', 'Network Address' => '192.168.22.111', 'Terminal Type' => 'POS', 'Current Version' => '3.5.232.6451', 'Last Seen Online' => '08/04/2026 07:25 AM America/New_York'],
        ],
    ],
    [
        'id' => 3,
        'uploadedAt' => '2026-08-11T08:00:00-04:00',
        'rows' => [
            ['Store ID' => '100', 'Store Name' => 'AA-Test-One', 'Terminal ID' => '1', 'Computer Name' => 'QU100T1', 'Network Address' => '192.168.22.111', 'Terminal Type' => 'POS', 'Current Version' => '3.5.232.6451', 'Last Seen Online' => '08/11/2026 07:30 AM America/New_York'],
            ['Store ID' => '100', 'Store Name' => 'AA-Test-One', 'Terminal ID' => '2', 'Computer Name' => 'QU100T2', 'Network Address' => '192.168.22.112', 'Terminal Type' => 'POS', 'Current Version' => '3.5.231.6408', 'Last Seen Online' => '08/01/2026 07:31 AM America/New_York'],
            ['Store ID' => '200', 'Store Name' => 'CV-Test-Two', 'Terminal ID' => '20', 'Computer Name' => 'QU200T1', 'Network Address' => '192.168.22.111', 'Terminal Type' => 'POS', 'Current Version' => '3.5.232.6451', 'Last Seen Online' => '08/11/2026 07:25 AM America/New_York'],
        ],
    ],
];

$metadata = [
    '100' => ['storeName' => 'AA-Test-One', 'brands' => ["Auntie Anne's"], 'status' => 'Live'],
    '200' => ['storeName' => 'CV-Test-Two', 'brands' => ['Carvel'], 'status' => 'Live'],
];

$dashboard = DeviceHealthService::fixtureDashboard($snapshots, $metadata);
assertSameValue(2, $dashboard['summary']['totalStores'], 'Store count must use distinct Store IDs.');
assertSameValue(5, $dashboard['summary']['totalDevices'], 'Device count must include the inferred required QuBox at Store 200.');
assertSameValue(2, $dashboard['summary']['healthy'], 'Current healthy-device count must reconcile.');
assertSameValue(0, $dashboard['summary']['warning'], 'Current warning-device count must reconcile.');
assertSameValue(1, $dashboard['summary']['critical'], 'Current critical-device count must reconcile.');
assertSameValue(2, $dashboard['summary']['offline'], 'Current offline-device count must reconcile.');
assertSameValue(
    $dashboard['summary']['totalDevices'],
    $dashboard['summary']['healthy'] + $dashboard['summary']['warning'] + $dashboard['summary']['critical'] + $dashboard['summary']['offline'],
    'Health categories must reconcile to total devices.'
);
assertNear(53.3, $dashboard['summary']['fleetHealthScore'], 'Fleet health score must use healthy checks divided by expected checks.');
assertSameValue(2, $dashboard['summary']['quboxDown'], 'Both the missing current QuBox and inferred required QuBox must be counted.');

$auntieAnnes = DeviceHealthService::fixtureDashboard($snapshots, $metadata, ['mode' => 'brand', 'brand' => "Auntie Anne's"]);
assertSameValue(1, $auntieAnnes['summary']['totalStores'], 'Brand filter must update the store denominator.');
assertSameValue(3, $auntieAnnes['summary']['totalDevices'], 'Brand filter must update device totals.');
assertNear(55.6, $auntieAnnes['summary']['fleetHealthScore'], 'Brand filter must update the score denominator.');

echo "Device health calculation tests passed.\n";
