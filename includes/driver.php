<?php
function driver_schema(PDO $db): void
{
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $columns = $db
        ->query($mysql ? 'SHOW COLUMNS FROM users' : 'PRAGMA table_info(users)')
        ->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('driver_id', array_column($columns, $mysql ? 'Field' : 'name'), true)) {
        try {
            $db->exec('ALTER TABLE users ADD COLUMN driver_id INTEGER NULL');
        } catch (PDOException $e) {
            $columns = $db
                ->query($mysql ? 'SHOW COLUMNS FROM users' : 'PRAGMA table_info(users)')
                ->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('driver_id', array_column($columns, $mysql ? 'Field' : 'name'), true)) {
                throw $e;
            }
        }
    }
    if ($mysql) {
        $indexes = $db->query('SHOW INDEX FROM users')->fetchAll(PDO::FETCH_ASSOC);
        if (!in_array('uq_users_driver_id', array_column($indexes, 'Key_name'), true)) {
            $db->exec('CREATE UNIQUE INDEX uq_users_driver_id ON users(driver_id)');
        }
    } else {
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_users_driver_id ON users(driver_id)');
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS driver_trip_reports(
            id ' .
            ($mysql ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT') .
            ',
            requisition_id INTEGER NOT NULL REFERENCES requisitions(id),
            user_id INTEGER NOT NULL REFERENCES users(id),
            update_type VARCHAR(40) NOT NULL,
            remarks TEXT NOT NULL,
            created_at DATETIME NOT NULL
        )' .
            ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : ''),
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS driver_vehicle_reports(
            id ' .
            ($mysql ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT') .
            ',
            requisition_id INTEGER NOT NULL REFERENCES requisitions(id),
            vehicle_id INTEGER NOT NULL REFERENCES vehicles(id),
            user_id INTEGER NOT NULL REFERENCES users(id),
            urgency VARCHAR(30) NOT NULL,
            description TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT \'Open\',
            created_at DATETIME NOT NULL
        )' .
            ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : ''),
    );
    if ($mysql) {
        $indexes = $db->query('SHOW INDEX FROM driver_trip_reports')->fetchAll(PDO::FETCH_ASSOC);
        if (!in_array('idx_driver_trip_reports_trip', array_column($indexes, 'Key_name'), true)) {
            $db->exec(
                'CREATE INDEX idx_driver_trip_reports_trip ON driver_trip_reports(requisition_id,created_at)',
            );
        }
        $indexes = $db->query('SHOW INDEX FROM driver_vehicle_reports')->fetchAll(PDO::FETCH_ASSOC);
        if (
            !in_array('idx_driver_vehicle_reports_vehicle', array_column($indexes, 'Key_name'), true)
        ) {
            $db->exec(
                'CREATE INDEX idx_driver_vehicle_reports_vehicle ON driver_vehicle_reports(vehicle_id,status,created_at)',
            );
        }
    } else {
        $db->exec(
            'CREATE INDEX IF NOT EXISTS idx_driver_trip_reports_trip ON driver_trip_reports(requisition_id,created_at)',
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS idx_driver_vehicle_reports_vehicle ON driver_vehicle_reports(vehicle_id,status,created_at)',
        );
    }
}

function driver_registration_link(string $employeeNumber, string $fullName, int $officeId): int
{
    $person = one(
        "SELECT d.id,d.status driver_status,p.full_name,p.office_id,p.status personnel_status
         FROM drivers d JOIN personnel p ON p.driver_id=d.id
         WHERE d.employee_number=? AND p.classification='Driver'",
        [$employeeNumber],
    );
    $normalize = static fn(string $name): string => mb_strtolower(
        preg_replace('/\s+/', ' ', trim($name)),
    );
    if (
        !$person ||
        $normalize($person['full_name']) !== $normalize($fullName) ||
        (int) $person['office_id'] !== $officeId ||
        $person['personnel_status'] === 'Inactive' ||
        $person['driver_status'] === 'Inactive'
    ) {
        throw new RuntimeException(
            'Driver registration must match an active personnel record. Check your name, employee number, and office with your administrator.',
        );
    }
    if (
        one(
            "SELECT id FROM users WHERE driver_id=? AND status<>'Rejected'",
            [$person['id']],
        )
    ) {
        throw new RuntimeException(
            'A driver account is already linked to this employee number. Contact your administrator.',
        );
    }
    return (int) $person['id'];
}

function driver_notification(int $driverId, string $message, ?int $requisitionId = null): void
{
    $account = one(
        "SELECT id FROM users WHERE driver_id=? AND role='Driver' AND status='Active'",
        [$driverId],
    );
    if ($account) {
        run(
            'INSERT INTO notifications(user_id,message,requisition_id,created_at) VALUES(?,?,?,?)',
            [$account['id'], $message, $requisitionId, date('Y-m-d H:i:s')],
        );
    }
}

function notify_fleet_managers(string $message, ?int $requisitionId = null): void
{
    foreach (
        all(
            "SELECT id FROM users WHERE status='Active' AND role IN ('Administrator','Administrative Officer')",
        )
        as $manager
    ) {
        run(
            'INSERT INTO notifications(user_id,message,requisition_id,created_at) VALUES(?,?,?,?)',
            [$manager['id'], $message, $requisitionId, date('Y-m-d H:i:s')],
        );
    }
}

function driver_assigned_trips(int $driverId): array
{
    return all(
        'SELECT r.*,v.model,v.plate,m.departure,m.return_time,m.odometer_out,m.odometer_in
         FROM requisitions r
         LEFT JOIN vehicles v ON v.id=r.vehicle_id
         LEFT JOIN vehicle_movements m ON m.requisition_id=r.id
         WHERE r.driver_id=?
         ORDER BY CASE r.status WHEN \'Dispatched\' THEN 0 WHEN \'Approved\' THEN 1 ELSE 2 END,
                  r.start_datetime DESC,r.id DESC',
        [$driverId],
    );
}
