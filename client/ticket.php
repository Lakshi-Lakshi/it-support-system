
<?php

require_once __DIR__ . '/../includes/functions.php';

require_role('client');

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("
    SELECT t.*, s.name AS staff_name
    FROM tickets t
    LEFT JOIN users s ON s.id=t.assigned_to
    WHERE t.id=? AND t.user_id=?
    LIMIT 1
");

$stmt->execute([$id, current_user()['id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found.');
}

/*
 * Fetch attachments belonging to this ticket.
 * Only the ticket owner can view these attachment links.
 */
$attachmentStmt = db()->prepare("
    SELECT id, original_name, file_size, mime_type, created_at
    FROM ticket_attachments
    WHERE ticket_id=?
    ORDER BY created_at DESC
");

$attachmentStmt->execute([$id]);
$attachments = $attachmentStmt->fetchAll();

/*
 * Build a unified activity timeline from:
 * 1. ticket creation
 * 2. system notifications related to this ticket
 * 3. staff/admin comments
 *
 * This avoids changing the database schema while giving the user
 * a professional chronological ticket history.
 */

$activities = [];

$activities[] = [
    'kind' => 'created',
    'title' => 'Ticket Created',
    'description' => 'Support request was submitted by ' . current_user()['name'] . '.',
    'actor' => current_user()['name'],
    'time' => $ticket['created_at'],
];

$stmt = db()->prepare("
    SELECT n.type, n.message, n.created_at
    FROM notifications n
    WHERE n.ticket_id=?
      AND n.type IN ('Ticket Assigned','Status Changed')
    ORDER BY n.created_at ASC
");

$stmt->execute([$id]);
$events = $stmt->fetchAll();

/*
 * One real ticket action can create several notification rows because
 * different users receive notifications. The customer-facing timeline
 * should show the action once, not once per recipient.
 */

$seenAssignment = [];
$seenStatus = [];

foreach ($events as $event) {

    $message = trim($event['message']);

    if ($event['type'] === 'Ticket Assigned') {

        $assignmentKey = strtolower(
            preg_replace('/\s+/', ' ', $message)
        );

        if (isset($seenAssignment[$assignmentKey])) {
            continue;
        }

        $seenAssignment[$assignmentKey] = true;

        $activities[] = [
            'kind' => 'assigned',
            'title' => 'Ticket Assigned',
            'description' => $message,
            'actor' => 'Support System',
            'time' => $event['created_at'],
        ];

    } else {

        

        if (
            preg_match(
                '/from\s+(.+?)\s+to\s+(.+?)(?:\.|$)/i',
                $message,
                $m
            )
        ) {
            $statusKey = strtolower(trim($m[1]))
                . '|'
                . strtolower(trim($m[2]));
        } else {
            $statusKey = strtolower($message);
        }

        if (isset($seenStatus[$statusKey])) {
            continue;
        }

        $seenStatus[$statusKey] = true;

        $activities[] = [
            'kind' => 'status',
            'title' => 'Status Updated',
            'description' => $message,
            'actor' => 'Support System',
            'time' => $event['created_at'],
        ];
    }
}

$stmt = db()->prepare("
    SELECT c.comment, c.created_at, u.name AS actor_name, u.role
    FROM ticket_comments c
    JOIN users u ON u.id=c.user_id
    WHERE c.ticket_id=?
    ORDER BY c.created_at ASC
");

$stmt->execute([$id]);
$comments = $stmt->fetchAll();

foreach ($comments as $comment) {

    $activities[] = [
        'kind' => 'comment',
        'title' => 'Support Update',
        'description' => $comment['comment'],
        'actor' => $comment['actor_name'] . ' (' . ucfirst($comment['role']) . ')',
        'time' => $comment['created_at'],
    ];
}

usort(
    $activities,
    fn($a, $b) => strtotime($a['time']) <=> strtotime($b['time'])
);

$pageHeading = 'Ticket ' . $ticket['ticket_code'];
$pageTitle = 'Ticket Details | SupportHub';

include __DIR__ . '/../includes/header.php';

?>

<?php if (!empty($_GET['created'])): ?>

    <div class="alert alert-success">
        Ticket created successfully. Your support request is now being tracked.
    </div>

<?php endif; ?>

<div class="ticket-hero">

    <div>

        <div class="eyebrow">SUPPORT REQUEST</div>

        <div class="ticket-title-row">

            <h2><?= htmlspecialchars($ticket['ticket_code']) ?></h2>

            <span class="status <?= status_class($ticket['status']) ?>">
                <?= htmlspecialchars($ticket['status']) ?>
            </span>

        </div>

        <p>
            Track your support request, staff assignment, progress and resolution updates in one place.
        </p>

    </div>

    <a class="btn btn-secondary" href="/it-support-system/client/tickets.php">
        ← Back to Tickets
    </a>

</div>

<div class="ticket-summary-grid">

    <div class="card ticket-summary-card">
        <span>Category</span>
        <strong><?= htmlspecialchars($ticket['category']) ?></strong>
    </div>

    <div class="card ticket-summary-card">
        <span>Priority</span>
        <strong class="priority-<?= strtolower($ticket['priority']) ?>">
            <?= htmlspecialchars($ticket['priority']) ?>
        </strong>
    </div>

    <div class="card ticket-summary-card">
        <span>Assigned Staff</span>
        <strong>
            <?= htmlspecialchars($ticket['staff_name'] ?? 'Not assigned yet') ?>
        </strong>
    </div>

    <div class="card ticket-summary-card">
        <span>Submitted</span>
        <strong>
            <?= date('d M Y, h:i A', strtotime($ticket['created_at'])) ?>
        </strong>
    </div>

</div>

<div class="ticket-layout">

    <div class="card ticket-main-card">

        <div class="detail-heading">

            <div>
                <h3>Issue Description</h3>
                <span>Original support request</span>
            </div>

            <span class="detail-icon">▤</span>

        </div>

        <div class="issue-box">
            <?= nl2br(htmlspecialchars($ticket['issue_description'])) ?>
        </div>

        <!-- Ticket Attachments -->

        <div class="detail-heading timeline-heading">

            <div>
                <h3>Attachments</h3>
                <span>Files submitted with this support request</span>
            </div>

            <span class="timeline-count">
                <?= count($attachments) ?> files
            </span>

        </div>

        <?php if (!empty($attachments)): ?>

            <div class="ticket-attachments">

                <?php foreach ($attachments as $attachment): ?>

                    <div class="ticket-attachment-item">

                        <div class="attachment-info">

                            <span class="attachment-icon">📎</span>

                            <div>

                                <strong>
                                    <?= htmlspecialchars($attachment['original_name']) ?>
                                </strong>

                                <small>
                                    <?= number_format($attachment['file_size'] / 1024, 1) ?> KB
                                    ·
                                    <?= htmlspecialchars(strtoupper(
                                        pathinfo($attachment['original_name'], PATHINFO_EXTENSION)
                                    )) ?>
                                </small>

                            </div>

                        </div>

                        <a
                            class="btn btn-secondary"
                            href="/it-support-system/download_attachment.php?id=<?= (int)$attachment['id'] ?>"
                        >
                            Download
                        </a>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="issue-box">
                No attachments were uploaded with this ticket.
            </div>

        <?php endif; ?>

        <!-- Ticket Timeline -->

        <div class="detail-heading timeline-heading">

            <div>
                <h3>Ticket Timeline</h3>
                <span>Complete history of this support request</span>
            </div>

            <span class="timeline-count">
                <?= count($activities) ?> events
            </span>

        </div>

        <div class="timeline">

            <?php foreach ($activities as $index => $activity): ?>

                <?php

                    $icon = match ($activity['kind']) {
                        'created' => '＋',
                        'assigned' => '→',
                        'status' => '↻',
                        'comment' => '✓',
                        default => '•'
                    };

                ?>

                <div class="timeline-item <?= $index === count($activities) - 1 ? 'last' : '' ?>">

                    <div class="timeline-marker timeline-<?= htmlspecialchars($activity['kind']) ?>">
                        <?= $icon ?>
                    </div>

                    <div class="timeline-content">

                        <div class="timeline-top">

                            <strong>
                                <?= htmlspecialchars($activity['title']) ?>
                            </strong>

                            <time>
                                <?= date('d M Y, h:i A', strtotime($activity['time'])) ?>
                            </time>

                        </div>

                        <p>
                            <?= nl2br(htmlspecialchars($activity['description'])) ?>
                        </p>

                        <span class="timeline-actor">
                            By <?= htmlspecialchars($activity['actor']) ?>
                        </span>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <aside>

        <div class="card ticket-side-card">

            <div class="detail-heading">

                <div>
                    <h3>Current Status</h3>
                    <span>Live ticket state</span>
                </div>

            </div>

            <div class="current-status">

                <span class="status-dot"></span>

                <div>

                    <strong><?= htmlspecialchars($ticket['status']) ?></strong>

                    <small>
                        Last updated
                        <?= date('d M Y, h:i A', strtotime($ticket['updated_at'])) ?>
                    </small>

                </div>

            </div>

            <div class="progress-track">

                <?php

                    $steps = [
                        'Open',
                        'Assigned',
                        'In Progress',
                        'Resolved',
                        'Closed'
                    ];

                    $currentIndex = array_search($ticket['status'], $steps, true);

                    if ($currentIndex === false) {
                        $currentIndex = 0;
                    }

                ?>

                <?php foreach ($steps as $i => $step): ?>

                    <div class="progress-step <?= $i <= $currentIndex ? 'complete' : '' ?>">

                        <span>
                            <?= $i <= $currentIndex ? '✓' : ($i + 1) ?>
                        </span>

                        <label><?= htmlspecialchars($step) ?></label>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

        <div class="card ticket-side-card">

            <div class="detail-heading">

                <div>
                    <h3>Need Help?</h3>
                    <span>Keep your ticket ID for reference</span>
                </div>

            </div>

            <p class="help-text">

                When contacting support, provide
                <strong><?= htmlspecialchars($ticket['ticket_code']) ?></strong>
                so the support team can locate your request quickly.

            </p>

            <a
                class="btn btn-primary"
                style="width:100%"
                href="/it-support-system/client/tickets.php"
            >
                View My Tickets
            </a>

        </div>

    </aside>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
