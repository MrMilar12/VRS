<?php
// Personnel is the editable registry. Driver IDs remain stable for historical trips.
function personnel_registry_schema(PDO $db): void
{
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    foreach (
        [
            'classification' => "VARCHAR(20) NOT NULL DEFAULT 'Utility'",
            'driver_id' => 'INTEGER NULL UNIQUE',
            'license_number' => 'VARCHAR(80) NULL',
            'license_expiry' => 'DATE NULL',
        ]
        as $name => $definition
    ) {
        $columns = $db
            ->query($mysql ? 'SHOW COLUMNS FROM personnel' : 'PRAGMA table_info(personnel)')
            ->fetchAll(PDO::FETCH_ASSOC);
        if (in_array($name, array_column($columns, $mysql ? 'Field' : 'name'), true)) {
            continue;
        }
        // SQLite cannot add a UNIQUE column; the index is installed separately below.
        $ddl = $mysql ? $definition : str_replace(' UNIQUE', '', $definition);
        try {
            $db->exec("ALTER TABLE personnel ADD COLUMN $name $ddl");
        } catch (PDOException $e) {
            $columns = $db
                ->query($mysql ? 'SHOW COLUMNS FROM personnel' : 'PRAGMA table_info(personnel)')
                ->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array($name, array_column($columns, $mysql ? 'Field' : 'name'), true)) {
                throw $e;
            }
        }
    }
    if (!$mysql) {
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_personnel_driver ON personnel(driver_id)');
    }
    if (
        !$db
            ->query(
                'SELECT d.id FROM drivers d LEFT JOIN personnel p ON p.driver_id=d.id WHERE p.id IS NULL',
            )
            ->fetchColumn()
    ) {
        return;
    }
    if ($mysql) {
        $db->beginTransaction();
    } else {
        $db->exec('BEGIN IMMEDIATE');
    }
    try {
        $drivers = $db
            ->query('SELECT * FROM drivers' . ($mysql ? ' FOR UPDATE' : ''))
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($drivers as $driver) {
            $q = $db->prepare('SELECT id FROM personnel WHERE driver_id=?');
            $q->execute([$driver['id']]);
            if ($q->fetchColumn()) {
                continue;
            }
            $q = $db->prepare('SELECT id FROM personnel WHERE employee_number=?');
            $q->execute([$driver['employee_number']]);
            $id = $q->fetchColumn();
            if ($id) {
                $q = $db->prepare(
                    "UPDATE personnel SET classification='Driver',driver_id=?,license_number=?,license_expiry=? WHERE id=?",
                );
                $q->execute([
                    $driver['id'],
                    $driver['license_number'],
                    $driver['license_expiry'],
                    $id,
                ]);
            } else {
                $q = $db->prepare(
                    "INSERT INTO personnel(full_name,employee_number,office_id,position,contact,status,classification,driver_id,license_number,license_expiry) VALUES(?,?,?,'Driver',?,?,'Driver',?,?,?)",
                );
                $q->execute([
                    $driver['full_name'],
                    $driver['employee_number'],
                    $driver['office_id'] ??
                    $db->query('SELECT id FROM offices ORDER BY id')->fetchColumn(),
                    $driver['contact'],
                    $driver['status'],
                    $driver['id'],
                    $driver['license_number'],
                    $driver['license_expiry'],
                ]);
            }
            // Keep the compatibility driver row consistent with the combined staff identity.
            $q = $db->prepare('SELECT * FROM personnel WHERE driver_id=?');
            $q->execute([$driver['id']]);
            $person = $q->fetch(PDO::FETCH_ASSOC);
            $status = $driver['status'] !== 'Available' ? $driver['status'] : $person['status'];
            $q = $db->prepare('UPDATE personnel SET status=? WHERE id=?');
            $q->execute([$status, $person['id']]);
            $q = $db->prepare(
                'UPDATE drivers SET full_name=?,office_id=?,contact=?,status=? WHERE id=?',
            );
            $q->execute([
                $person['full_name'],
                $person['office_id'],
                $person['contact'],
                $status,
                $driver['id'],
            ]);
        }
        if ($mysql) {
            $db->commit();
        } else {
            $db->exec('COMMIT');
        }
    } catch (Throwable $e) {
        if ($mysql) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        } else {
            $db->exec('ROLLBACK');
        }
        throw $e;
    }
}
function personnel_lock(int $id): ?array
{
    $person = lock_record('personnel', $id);
    if (!empty($person['driver_id'])) {
        lock_record('drivers', (int) $person['driver_id']);
    }
    return $person;
}
function personnel_registry_values(array $values, int $id): array
{
    global $pdo;
    $person = $id ? personnel_lock($id) : null;
    $locking =
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' && $pdo->inTransaction()
            ? ' FOR UPDATE'
            : '';
    if (!in_array($values['classification'], ['Driver', 'Utility'], true)) {
        throw new RuntimeException('Choose Driver or Utility.');
    }
    $driverId = $person['driver_id'] ?? null;
    if (
        $id &&
        $values['status'] !== 'Available' &&
        one(
            'SELECT b.id FROM personnel_bookings b WHERE ' .
                personnel_assignment_match() .
                " AND (status='In Progress' OR (status='Approved' AND end_datetime>?))" .
                $locking,
            [$id, $id, date('Y-m-d H:i:s')],
        )
    ) {
        throw new RuntimeException(
            'Resolve personnel assignments before making this person unavailable.',
        );
    }
    if (
        $driverId &&
        one(
            "SELECT id FROM requisitions WHERE driver_id=? AND (status='Dispatched' OR (status='Approved' AND end_datetime>?))" .
                $locking,
            [$driverId, date('Y-m-d H:i:s')],
        )
    ) {
        if ($values['classification'] !== 'Driver' || $values['status'] !== 'Available') {
            throw new RuntimeException(
                'Resolve vehicle assignments before changing driver classification or availability.',
            );
        }
        if (
            one(
                "SELECT id FROM requisitions WHERE driver_id=? AND status IN ('Approved','Dispatched') AND end_datetime>?" .
                    $locking,
                [$driverId, $values['license_expiry'] . ' 23:59:59'],
            )
        ) {
            throw new RuntimeException('The license must remain valid through assigned trips.');
        }
    }
    if ($values['classification'] === 'Driver') {
        if (trim($values['license_number']) === '' || empty($values['license_expiry'])) {
            throw new RuntimeException('Drivers require a license number and expiration date.');
        }
    }
    $values['license_expiry'] = $values['license_expiry'] ?: null;
    if ($values['classification'] === 'Driver' || $driverId) {
        $driverValues = [
            $values['full_name'],
            $values['employee_number'],
            $values['office_id'],
            $values['contact'],
            $values['license_number'],
            $values['license_expiry'] ?? ($person['license_expiry'] ?? '1970-01-01'),
            $values['classification'] === 'Driver' ? $values['status'] : 'Inactive',
        ];
        if ($driverId) {
            run(
                'UPDATE drivers SET full_name=?,employee_number=?,office_id=?,contact=?,license_number=?,license_expiry=?,status=? WHERE id=?',
                [...$driverValues, $driverId],
            );
        } else {
            run(
                'INSERT INTO drivers(full_name,employee_number,office_id,contact,license_number,license_expiry,status) VALUES(?,?,?,?,?,?,?)',
                $driverValues,
            );
            $driverId = (int) $pdo->lastInsertId();
        }
    }
    $values['driver_id'] = $driverId;
    return $values;
}
function personnel_driver_issues(array $driver, array $booking): array
{
    global $pdo;
    $locking =
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' && $pdo->inTransaction()
            ? ' FOR UPDATE'
            : '';
    $person = one('SELECT * FROM personnel WHERE driver_id=?' . $locking, [$driver['id']]);
    if (!$person || $person['classification'] !== 'Driver') {
        return ['Personnel must be classified as Driver.'];
    }
    return personnel_issues($person, array_replace($booking, ['id' => 0]), false);
}
