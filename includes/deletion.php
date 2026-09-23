<?php
function deletion_entities(): array
{
    return [
        'vehicles' => ['vehicle', 'vehicles'],
        'personnel' => ['personnel member', 'personnel'],
        'offices' => ['office', 'offices'],
        'users' => ['user account', 'users'],
        'requisitions' => ['vehicle requisition', 'requisitions'],
        'personnel_bookings' => ['personnel requisition', 'personnel-bookings'],
        'notifications' => ['notification', 'notifications'],
        'vehicle_blocks' => ['maintenance block', 'maintenance'],
    ];
}
function deletion_allowed(string $entity, array $record): bool
{
    global $user;
    if (!$user) {
        return false;
    }
    if ($entity === 'notifications') {
        return (int) $record['user_id'] === (int) $user['id'];
    }
    if (in_array($entity, ['requisitions', 'personnel_bookings'], true)) {
        return is_role('Administrator') || (int) $record['requester_id'] === (int) $user['id'];
    }
    if (in_array($entity, ['users', 'offices'], true)) {
        return is_role('Administrator');
    }
    return in_array($entity, ['vehicles', 'personnel', 'vehicle_blocks'], true) &&
        is_role('Administrator', 'Administrative Officer');
}
function deletion_record(string $entity, int $id, bool $lock = false): array
{
    global $pdo;
    if (!isset(deletion_entities()[$entity])) {
        throw new RuntimeException('Invalid record type.');
    }
    $record =
        $entity === 'personnel' && $lock
            ? personnel_lock($id)
            : one(
                "SELECT * FROM $entity WHERE id=?" .
                    ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                        ? ' FOR UPDATE'
                        : ''),
                [$id],
            );
    if (!$record || !deletion_allowed($entity, $record)) {
        throw new RuntimeException('Record not found or you do not have permission to delete it.');
    }
    return $record;
}
function deletion_problem(string $entity, array $record): string
{
    global $user;
    $id = (int) $record['id'];
    $links = [];
    if (in_array($entity, ['requisitions', 'personnel_bookings'], true)) {
        if (
            !in_array(
                $record['status'],
                ['Draft', 'Returned for Correction', 'Rejected', 'Cancelled'],
                true,
            )
        ) {
            return 'Only draft, returned, rejected, or cancelled requisitions can be deleted. Cancel an eligible active request first. Completed assignments are kept for history.';
        }
        if (
            $entity === 'requisitions' &&
            one('SELECT id FROM vehicle_movements WHERE requisition_id=?', [$id])
        ) {
            return 'This requisition has a vehicle movement record and must be kept for history.';
        }
        if (
            $entity === 'personnel_bookings' &&
            ($record['actual_start'] || $record['actual_end'])
        ) {
            return 'This requisition has recorded assignment progress and must be kept for history.';
        }
    }
    if ($entity === 'users') {
        if ($id === (int) $user['id']) {
            return 'You cannot delete your own account.';
        }
        $links = [
            ['requisitions', 'requester_id'],
            ['personnel_bookings', 'requester_id'],
            ['approvals', 'user_id'],
            ['personnel_booking_history', 'user_id'],
            ['vehicle_movements', 'checked_by'],
            ['vehicle_blocks', 'created_by'],
            ['audit_logs', 'user_id'],
            ['print_logs', 'user_id'],
        ];
    } elseif ($entity === 'offices') {
        $links = [
            ['users', 'office_id'],
            ['drivers', 'office_id'],
            ['personnel', 'office_id'],
            ['requisitions', 'office_id'],
            ['personnel_bookings', 'office_id'],
        ];
    } elseif ($entity === 'vehicles') {
        $links = [['requisitions', 'vehicle_id'], ['vehicle_blocks', 'vehicle_id']];
    } elseif ($entity === 'personnel') {
        $links = [
            ['personnel_bookings', 'personnel_id'],
            ['personnel_booking_assignments', 'personnel_id'],
        ];
        if (
            $record['driver_id'] &&
            one('SELECT id FROM requisitions WHERE driver_id=?', [$record['driver_id']])
        ) {
            return 'This driver is linked to vehicle requisitions. Set the personnel record to Inactive instead.';
        }
    }
    foreach ($links as [$table, $column]) {
        if (one("SELECT $column FROM $table WHERE $column=?", [$id])) {
            return 'This record is linked to other records or history. Set it to Inactive instead, or remove unused linked records first.';
        }
    }
    return '';
}
function delete_record(string $entity, int $id): void
{
    $record = deletion_record($entity, $id, true);
    $problem = deletion_problem($entity, $record);
    if ($problem !== '') {
        throw new RuntimeException($problem);
    }
    if ($entity === 'users') {
        foreach (['notifications', 'auth_factors', 'auth_preferences'] as $table) {
            run("DELETE FROM $table WHERE user_id=?", [$id]);
        }
    } elseif ($entity === 'requisitions') {
        foreach (['notifications', 'approvals', 'print_logs'] as $table) {
            run("DELETE FROM $table WHERE requisition_id=?", [$id]);
        }
    } elseif ($entity === 'personnel_bookings') {
        foreach (['personnel_booking_history', 'personnel_booking_assignments'] as $table) {
            run("DELETE FROM $table WHERE booking_id=?", [$id]);
        }
    }
    run("DELETE FROM $entity WHERE id=?", [$id]);
    if ($entity === 'personnel' && $record['driver_id']) {
        run('DELETE FROM drivers WHERE id=?', [$record['driver_id']]);
    }
    $name =
        $record['reference'] ??
        ($record['full_name'] ?? ($record['name'] ?? ($record['plate'] ?? '#' . $id)));
    audit('Record deleted', deletion_entities()[$entity][0] . ' #' . $id . ' · ' . $name);
}
function delete_link(string $entity, array $record): string
{
    if (!deletion_allowed($entity, $record)) {
        return '';
    }
    return '<a class="btn small danger" href="' .
        e(secure_record_url('index.php?page=delete&entity=' . $entity . '&id=' . $record['id'])) .
        '" aria-label="Delete ' .
        e(deletion_entities()[$entity][0]) .
        '">Delete</a>';
}
