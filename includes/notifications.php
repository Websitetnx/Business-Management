<?php
declare(strict_types=1);

/**
 * Intelligent Notification & Renewal Alert Engine
 *
 * Provides automated permit expiration checks, user renewal reminders,
 * status change notices, and in-app notification management.
 */

function create_notification(PDO $pdo, int $userId, string $message, ?int $applicationId = null): int
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, application_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $applicationId, $message]);
    return (int) $pdo->lastInsertId();
}

/**
 * Create one bounded notification per rejected document so every failure fits
 * the existing VARCHAR(500) column and remains actionable to the applicant.
 */
function create_document_scan_failure_notifications(
    PDO $pdo,
    int $userId,
    int $applicationId,
    string $reference,
    array $failures,
    string $submissionLabel = 'application'
): void {
    $count = count($failures);
    create_notification(
        $pdo,
        $userId,
        'Automated verification found ' . $count . ' document' . ($count === 1 ? '' : 's')
            . ' needing revision in ' . $submissionLabel . ' ' . $reference
            . '. Open the application to replace the listed files.',
        $applicationId
    );

    foreach ($failures as $failure) {
        $label = trim((string) ($failure['label'] ?? 'Uploaded document'));
        $reasons = array_values(array_filter(
            array_map(static fn(mixed $reason): string => trim((string) $reason), (array) ($failure['reasons'] ?? [])),
            static fn(string $reason): bool => $reason !== ''
        ));
        $message = $reference . ' - ' . $label . ' failed verification: '
            . ($reasons ? implode(' ', $reasons) : 'The file did not meet the automated document checks.')
            . ' Please re-upload a corrected copy.';

        // Core validation reasons are normally well below this limit. This is a
        // final guard for unexpectedly verbose model-provided document labels.
        if (strlen($message) > 500) {
            $suffix = ' Please open the application for the complete scan details.';
            $cutLength = 500 - strlen($suffix);
            $message = (function_exists('mb_strcut')
                ? mb_strcut($message, 0, $cutLength, 'UTF-8')
                : substr($message, 0, $cutLength)) . $suffix;
        }
        create_notification($pdo, $userId, $message, $applicationId);
    }
}

function get_user_notifications(PDO $pdo, int $userId, int $limit = 20): array
{
    $stmt = $pdo->prepare('SELECT n.id, n.message, n.application_id, n.read_at, n.created_at, a.reference, a.permit_number, a.status application_status, p.id payment_id FROM notifications n LEFT JOIN applications a ON a.id = n.application_id LEFT JOIN payments p ON p.application_id = a.id WHERE n.user_id = ? ORDER BY n.created_at DESC, n.id DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function get_unread_notification_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function mark_notification_as_read(PDO $pdo, int $notificationId, int $userId): bool
{
    $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL');
    $stmt->execute([$notificationId, $userId]);
    return $stmt->rowCount() > 0;
}

function mark_all_notifications_as_read(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
    $stmt->execute([$userId]);
    return $stmt->rowCount();
}

/**
 * Automatically scans active permits for approaching expiration dates and triggers renewal notices.
 * Standard Philippine LGU permits are valid through December 31st of the issued year.
 */
function check_and_generate_renewal_notices(PDO $pdo, int $userId): array
{
    // Find approved/released permits for this user
    $stmt = $pdo->prepare("SELECT a.id, a.reference, a.permit_number, a.approved_at, a.submitted_at, b.business_name FROM applications a JOIN businesses b ON b.id = a.business_id WHERE a.user_id = ? AND a.status IN ('Approved', 'Released') AND a.permit_number IS NOT NULL ORDER BY a.approved_at DESC");
    $stmt->execute([$userId]);
    $permits = $stmt->fetchAll();

    $generatedAlerts = [];
    $currentDate = new DateTimeImmutable();
    $currentYear = (int) $currentDate->format('Y');

    foreach ($permits as $permit) {
        $approvedDate = !empty($permit['approved_at']) ? new DateTimeImmutable($permit['approved_at']) : new DateTimeImmutable($permit['submitted_at']);
        $permitYear = (int) $approvedDate->format('Y');

        // Business permits expire on December 31 of the valid year
        $expiryDate = new DateTimeImmutable("{$permitYear}-12-31 23:59:59");
        $daysUntilExpiry = (int) $currentDate->diff($expiryDate)->format('%r%a');

        // Determine expiration condition:
        // 1. Expired
        // 2. Expiring soon (within 60 days)
        // 3. Early renewal window (Nov-Jan)
        $isExpiringSoon = $daysUntilExpiry <= 60 && $daysUntilExpiry >= 0;
        $isExpired = $daysUntilExpiry < 0;

        if ($isExpiringSoon || $isExpired) {
            // Check if renewal application is already in progress for this permit
            $renewalCheck = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ? AND permit_number = ? AND application_type = 'Renewal' AND status IN ('For Review', 'Needs Revision', 'Approved', 'Released') AND YEAR(submitted_at) = ?");
            $renewalCheck->execute([$userId, $permit['permit_number'], $currentYear]);
            $hasRenewal = (int) $renewalCheck->fetchColumn() > 0;

            if (!$hasRenewal) {
                // Check if a renewal notification was already sent in the last 14 days
                $existingNotice = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND application_id = ? AND message LIKE '%renew%' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)");
                $existingNotice->execute([$userId, $permit['id']]);
                if ((int) $existingNotice->fetchColumn() === 0) {
                    $noticeText = $isExpired
                        ? "⚠️ Action Required: Business permit {$permit['permit_number']} for {$permit['business_name']} expired on {$expiryDate->format('M j, Y')}. Submit your renewal application now to avoid late penalties."
                        : "🔔 Renewal Notice: Business permit {$permit['permit_number']} for {$permit['business_name']} will expire in {$daysUntilExpiry} days ({$expiryDate->format('M j, Y')}). Submit renewal early for fast processing.";

                    create_notification($pdo, $userId, $noticeText, (int) $permit['id']);
                }

                $generatedAlerts[] = [
                    'permit_number' => $permit['permit_number'],
                    'business_name' => $permit['business_name'],
                    'application_id' => (int) $permit['id'],
                    'expiry_date' => $expiryDate->format('M j, Y'),
                    'days_until_expiry' => $daysUntilExpiry,
                    'is_expired' => $isExpired,
                ];
            }
        }
    }

    return $generatedAlerts;
}
