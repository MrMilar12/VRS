<?php
/** Build reminders from records already scoped to the signed-in user's access. */
function work_reminders(array $vehicles, array $personnel, string $now, int $viewerId): array
{
    $today = substr($now, 0, 10);
    $items = [];
    foreach (['Vehicle' => $vehicles, 'Personnel' => $personnel] as $kind => $records) {
        foreach ($records as $r) {
            $status = $r['status'];
            if (
                !in_array(
                    $status,
                    [
                        'Draft',
                        'Returned for Correction',
                        'Pending Supervisor',
                        'Pending Administrative Approval',
                        'Approved',
                        'Dispatched',
                        'In Progress',
                        'Returned',
                    ],
                    true,
                )
            ) {
                continue;
            }
            if (
                in_array($status, ['Draft', 'Returned for Correction'], true) &&
                (int) $r['requester_id'] !== $viewerId
            ) {
                continue;
            }
            $ongoing = in_array($status, ['Dispatched', 'In Progress', 'Returned'], true);
            $upcoming = substr($r['start_datetime'], 0, 10) > $today && !$ongoing;
            $carryover = substr($r['start_datetime'], 0, 10) < $today;
            $late = $r['end_datetime'] <= $now;
            $task = match ($status) {
                'Draft' => 'Submit draft',
                'Returned for Correction' => 'Revise request',
                'Pending Supervisor', 'Pending Administrative Approval' => 'Awaiting approval',
                'Approved' => $late
                    ? 'Review missed schedule'
                    : ($kind === 'Personnel'
                        ? 'Needs start'
                        : 'Needs dispatch'),
                'Dispatched' => 'Record vehicle return',
                'In Progress' => 'Complete assignment',
                'Returned' => 'Complete trip',
                default => 'Review request',
            };
            $items[] = array_replace($r, [
                'request_kind' => $kind,
                'reminder_task' => $task,
                'carryover' => $carryover,
                'overdue' => $late,
                'work_group' => $carryover ? 'unfinished' : ($upcoming ? 'upcoming' : 'today'),
                'reminder_priority' => $late ? 0 : ($carryover ? 1 : ($upcoming ? 3 : 2)),
            ]);
        }
    }
    usort(
        $items,
        fn($a, $b) => $a['reminder_priority'] <=> $b['reminder_priority'] ?:
        strcmp($a['start_datetime'], $b['start_datetime']) ?:
        strcmp($a['reference'], $b['reference']),
    );
    return $items;
}
