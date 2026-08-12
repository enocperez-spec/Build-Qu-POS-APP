<?php
declare(strict_types=1);

require_once __DIR__ . '/../WebApp/api/Database.php';

$sections = array_column(Database::navigationSections(), 'label', 'key');
if (($sections[Database::SECTION_STORE_HEALTH] ?? null) !== 'Store Health') {
    throw new RuntimeException('Store Health must be available in User Roles.');
}

foreach (Database::roles() as $role) {
    $defaults = Database::defaultRolePermissions();
    if (!array_key_exists(Database::SECTION_STORE_HEALTH, $defaults[$role] ?? [])) {
        throw new RuntimeException("Store Health permission is missing for $role.");
    }
}

echo "Role permission tests passed.\n";
