<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/notifications.php';
$user = require_role('applicant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();

$documentId = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
$applicationId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
if (!$documentId || !$applicationId) {
    flash('error', 'Invalid document or application.');
    redirect('dashboard.php');
}

$pdo = db();

// Verify the document belongs to this user's application and it's in 'Needs Revision'
$stmt = $pdo->prepare('SELECT d.id, d.document_type, d.stored_name, a.status, a.user_id, a.reference, b.email applicant_email, u.name applicant_name FROM application_documents d JOIN applications a ON a.id = d.application_id JOIN businesses b ON b.id = a.business_id JOIN users u ON u.id = a.user_id WHERE d.id = ? AND d.application_id = ?');
$stmt->execute([$documentId, $applicationId]);
$document = $stmt->fetch();

if (!$document || (int) $document['user_id'] !== (int) $user['id']) {
    flash('error', 'Document not found.');
    redirect('dashboard.php');
}
if ($document['status'] !== 'Needs Revision') {
    flash('error', 'Documents can only be re-uploaded when the application needs revision.');
    redirect('application.php?id=' . $applicationId);
}

// Validate the uploaded file
$file = $_FILES['replacement_file'] ?? null;
$config = app_config();
$definitions = document_definitions();
$label = $definitions[$document['document_type']][0] ?? $document['document_type'];
$allowedTypes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];

if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    flash('error', 'Please select a file to upload.');
    redirect('application.php?id=' . $applicationId);
}
if ((int) $file['size'] > $config['max_upload_bytes']) {
    flash('error', $label . ' must be 5 MB or smaller.');
    redirect('application.php?id=' . $applicationId);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!isset($allowedTypes[$mime])) {
    flash('error', $label . ' must be a PDF, JPG, or PNG file.');
    redirect('application.php?id=' . $applicationId);
}

$destination = null;
$oldPath = null;
$replacementCommitted = false;
$scanStorageAvailable = true;

try {
    $pdo->beginTransaction();

    // Serialize replacements and use the latest stored filename as the rollback source.
    $lock = $pdo->prepare('SELECT d.id, d.document_type, d.stored_name, a.status, a.user_id, a.reference, b.email applicant_email, u.name applicant_name FROM application_documents d JOIN applications a ON a.id = d.application_id JOIN businesses b ON b.id = a.business_id JOIN users u ON u.id = a.user_id WHERE d.id = ? AND d.application_id = ? FOR UPDATE');
    $lock->execute([$documentId, $applicationId]);
    $lockedDocument = $lock->fetch();
    if (!$lockedDocument || (int) $lockedDocument['user_id'] !== (int) $user['id']) {
        throw new RuntimeException('Document not found.');
    }
    if ($lockedDocument['status'] !== 'Needs Revision') {
        throw new RuntimeException('Documents can only be re-uploaded when the application needs revision.');
    }
    $document = $lockedDocument;
    $oldPath = $config['upload_dir'] . '/' . basename((string) $document['stored_name']);

    $extension = $allowedTypes[$mime];
    $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
    $destination = $config['upload_dir'] . '/' . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The replacement file could not be saved.');
    }

    $update = $pdo->prepare('UPDATE application_documents SET original_name = ?, stored_name = ?, mime_type = ?, file_size = ?, uploaded_at = NOW() WHERE id = ?');
    $update->execute([basename($file['name']), $storedName, $mime, (int) $file['size'], $documentId]);

    // Invalidate only the replaced document's stale scan. Other automatic and
    // manual scan results stay intact.
    try {
        $pdo->prepare('DELETE FROM document_ai_scans WHERE document_id = ?')->execute([$documentId]);
    } catch (Throwable $scanStorageError) {
        $sqlState = $scanStorageError instanceof PDOException
            ? (string) ($scanStorageError->errorInfo[0] ?? $scanStorageError->getCode())
            : '';
        if ($sqlState !== '42S02') {
            throw $scanStorageError;
        }
        $scanStorageAvailable = false;
    }

    if (!$pdo->commit()) {
        throw new RuntimeException('The replacement document could not be committed.');
    }
    $replacementCommitted = true;

    // Delete the previous file only after the database points to the replacement.
    if ($oldPath !== $destination && is_file($oldPath)) {
        @unlink($oldPath);
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable) {
            // Continue with filesystem cleanup even if the driver cannot roll back.
        }
    }
    if (!$replacementCommitted && is_string($destination) && is_file($destination)) {
        @unlink($destination);
    }
    flash('error', $ex instanceof RuntimeException ? $ex->getMessage() : 'The document could not be re-uploaded. Please try again.');
    redirect('application.php?id=' . $applicationId);
}

// The replacement is durable before any slow or unavailable external service
// is called. Failed and skipped scans are nonblocking by design.
try {
    if ($scanStorageAvailable) {
        auto_scan_uploaded_documents($pdo, $applicationId, (int) $user['id'], $documentId);
    }
} catch (Throwable) {
    // The upload is already durable. Treat an unexpected post-commit scan
    // failure as unavailable rather than converting it into a rejection.
}
$applicationFailures = [];
$replacementFailed = false;
$targetStatus = 'For Review';
$statusUpdated = false;
$statusPreserved = false;
$preservedStatus = '';

try {
    $pdo->beginTransaction();
    $applicationLock = $pdo->prepare('SELECT id, status FROM applications WHERE id = ? AND user_id = ? FOR UPDATE');
    $applicationLock->execute([$applicationId, (int) $user['id']]);
    $lockedApplication = $applicationLock->fetch();
    if (!$lockedApplication) {
        throw new RuntimeException('Application not found.');
    }

    if ($lockedApplication['status'] !== 'Needs Revision') {
        $statusPreserved = true;
        $preservedStatus = (string) $lockedApplication['status'];
    } else {
        // Recompute under the application lock. Replacement transactions also
        // lock this row, so a concurrent replacement cannot make this decision stale.
        $applicationFailures = $scanStorageAvailable
            ? application_scan_failures($pdo, $applicationId)
            : [];
        foreach ($applicationFailures as $failure) {
            if ((int) $failure['document_id'] === $documentId) {
                $replacementFailed = true;
                break;
            }
        }
        $targetStatus = $applicationFailures ? 'Needs Revision' : 'For Review';

        $pdo->prepare('UPDATE applications SET status = ?, stage = 1 WHERE id = ?')->execute([$targetStatus, $applicationId]);
        if ($targetStatus === 'For Review') {
            record_status($pdo, $applicationId, 'For Review', (int) $user['id'], 'Replacement document saved. No blocking completed scans remain; application resubmitted for review.');
            create_notification($pdo, (int) $user['id'], 'Your replacement document for application ' . $document['reference'] . ' was saved. No blocking document scan issues remain, so the application has been resubmitted for review.', $applicationId);
            audit($pdo, (int) $user['id'], 'reupload_document_all_pass', 'application', $applicationId);
        } else {
            record_status($pdo, $applicationId, 'Needs Revision', (int) $user['id'], 'Document re-uploaded: ' . $label . '. One or more completed scans still have blocking issues.');
            audit($pdo, (int) $user['id'], 'reupload_document', 'application_document', $documentId);
        }
    }

    if (!$pdo->commit()) {
        throw new RuntimeException('The application status could not be committed.');
    }
    $statusUpdated = true;
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable) {
            // The replacement was already committed and must not be removed.
        }
    }

    // Status/history/notification failures must not turn a saved replacement
    // into an upload failure. Apply the decision only while no newer status has
    // replaced Needs Revision.
    try {
        $fallback = $pdo->prepare("UPDATE applications SET status = ?, stage = 1 WHERE id = ? AND user_id = ? AND status = 'Needs Revision'");
        $fallback->execute([$targetStatus, $applicationId, (int) $user['id']]);
        $currentStatus = $pdo->prepare('SELECT status FROM applications WHERE id = ? AND user_id = ?');
        $currentStatus->execute([$applicationId, (int) $user['id']]);
        $currentStatusValue = $currentStatus->fetchColumn();
        $statusUpdated = $currentStatusValue !== false;
        if ($statusUpdated && (string) $currentStatusValue !== $targetStatus) {
            $statusPreserved = true;
            $preservedStatus = (string) $currentStatusValue;
        }
    } catch (Throwable) {
        $statusUpdated = false;
    }
}

if ($statusUpdated && !$statusPreserved && $replacementFailed) {
    $replacementFailures = array_values(array_filter(
        $applicationFailures,
        static fn(array $failure): bool => (int) ($failure['document_id'] ?? 0) === $documentId
    ));
    send_application_scan_failure_emails(
        (string) $document['applicant_email'],
        (string) $document['applicant_name'],
        (string) $document['reference'],
        $replacementFailures,
        permit_portal_url('application.php?id=' . $applicationId),
        permit_portal_url('admin/review.php?id=' . $applicationId)
    );
}

if (!$statusUpdated) {
    flash('info', 'Your replacement document was saved, but the application status could not be refreshed. Your new file was not lost; please reload or contact the BPLO Help Desk.');
} elseif ($statusPreserved) {
    flash('success', 'Your replacement document was saved. The application status remains "' . $preservedStatus . '" because it changed while automated verification was running.');
} elseif (!$applicationFailures) {
    flash('success', 'Document re-uploaded. No blocking document scan issues remain, and your application is now being reviewed.');
} elseif ($replacementFailed) {
    flash('error', $label . ' was re-uploaded but still fails document validation. Please check the scan results and try again.');
} else {
    flash('success', $label . ' was re-uploaded successfully, but another document still needs revision.');
}

redirect('application.php?id=' . $applicationId);
