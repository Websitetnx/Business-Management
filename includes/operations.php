<?php
declare(strict_types=1);

/** Durable application event, email jobs and in-app notices share one transaction. */
function notify_application(PDO $pdo, int $applicationId, string $key, string $title, string $body, string $audience = 'all'): void
{
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('INSERT INTO notification_events (event_key,application_id,title,body,audience) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
        $q->execute([hash('sha256', $key), $applicationId, $title, $body, $audience]);
        if ($q->rowCount() === 1) {
            $eventId = (int) $pdo->lastInsertId();
            $q = $pdo->prepare("SELECT u.id FROM users u WHERE u.is_active=1 AND (u.role='admin' OR (?='all' AND u.id=(SELECT user_id FROM applications WHERE id=?)))");
            $q->execute([$audience, $applicationId]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $userId) {
                $pdo->prepare('INSERT INTO email_outbox (event_id,user_id) VALUES (?,?)')->execute([$eventId,$userId]);
                $message = mb_substr($title . ': ' . $body, 0, 490);
                $pdo->prepare('INSERT INTO notifications (user_id,application_id,message) VALUES (?,?,?)')->execute([$userId,$applicationId,$message]);
            }
        }
        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function queue_scan_failure_alert(PDO $pdo, int $applicationId, array $failures): void
{
    if (!$failures) return;
    $q=$pdo->prepare('SELECT d.id,d.stored_name,s.scanned_at FROM application_documents d LEFT JOIN document_ai_scans s ON s.document_id=d.id WHERE d.application_id=? ORDER BY d.id');
    $q->execute([$applicationId]);
    $identity=json_encode($q->fetchAll(), JSON_THROW_ON_ERROR);
    $body="Some submitted files need correction. Open your application to replace the affected files.\n\n";
    foreach ($failures as $failure) $body .= document_validation_failure_message($failure) . "\n\n";
    notify_application($pdo,$applicationId,'scan-failure:'.$applicationId.':'.hash('sha256',$identity),'Document correction required',$body);
}

/** Preserve a file version and every persisted scan, including manual re-scans. */
function snapshot_document(PDO $pdo, int $documentId, ?int $uploader = null): int
{
    $q=$pdo->prepare('SELECT d.*,a.user_id FROM application_documents d JOIN applications a ON a.id=d.application_id WHERE d.id=?');
    $q->execute([$documentId]); $d=$q->fetch();
    if (!$d) throw new RuntimeException('Document not found.');
    $pdo->prepare('INSERT INTO document_versions (document_id,original_name,stored_name,mime_type,file_size,uploaded_at,uploaded_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE document_id=VALUES(document_id)')
        ->execute([$documentId,$d['original_name'],$d['stored_name'],$d['mime_type'],$d['file_size'],$d['uploaded_at'],$uploader??$d['user_id']]);
    $q=$pdo->prepare('SELECT id FROM document_versions WHERE document_id=? AND stored_name=?');
    $q->execute([$documentId,$d['stored_name']]); $versionId=(int)$q->fetchColumn();
    $q=$pdo->prepare('SELECT * FROM document_ai_scans WHERE document_id=?');
    $q->execute([$documentId]); $scan=$q->fetch();
    if ($scan) {
        $json=json_encode($scan,JSON_THROW_ON_ERROR);
        $pdo->prepare('INSERT INTO document_scan_history (version_id,snapshot_key,result_json,scanned_at,scanned_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE snapshot_key=VALUES(snapshot_key)')
            ->execute([$versionId,hash('sha256',$versionId.':'.$json),$json,$scan['scanned_at'],$scan['scanned_by']]);
    }
    return $versionId;
}

/** One worker at a time per database; crash leases are recovered without parallel sends. */
function deliver_outbox(PDO $pdo, int $limit = 50, ?callable $transport = null): array
{
    $name='permit-mail-'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $q=$pdo->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$name]);
    if ((int)$q->fetchColumn()!==1) return ['sent'=>0,'failed'=>0,'busy'=>true];
    $counts=['sent'=>0,'failed'=>0,'busy'=>false];
    try {
        // Acquiring the global lock proves the previous sender no longer owns it.
        $pdo->exec("INSERT INTO email_delivery_attempts (outbox_id,attempt_number,outcome,error_message) SELECT id,attempts,'uncertain','Worker interrupted; SMTP acceptance unknown.' FROM email_outbox WHERE status='sending'");
        $pdo->exec("UPDATE email_outbox SET status=IF(attempts>=5,'failed','pending'),available_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE),locked_at=NULL,last_error='Worker interrupted; delivery may have occurred.' WHERE status='sending'");
        $q=$pdo->query("SELECT id FROM email_outbox WHERE status='pending' AND available_at<=NOW() AND attempts<5 ORDER BY id LIMIT ".max(1,min(200,$limit)));
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $q=$pdo->prepare('SELECT o.*,e.title,e.body,e.audience,e.application_id,u.name,u.email,u.role,u.is_active,a.user_id owner_id,a.reference FROM email_outbox o JOIN notification_events e ON e.id=o.event_id JOIN users u ON u.id=o.user_id JOIN applications a ON a.id=e.application_id WHERE o.id=?');
            $q->execute([$id]); $job=$q->fetch();
            // Recheck authorization and current registered email at delivery time.
            if (!$job['is_active'] || !($job['role']==='admin' || ($job['audience']==='all' && $job['role']==='applicant' && (int)$job['user_id']===(int)$job['owner_id']))) {
                $pdo->prepare("UPDATE email_outbox SET status='cancelled',last_error='Recipient no longer authorized.' WHERE id=?")->execute([$id]);
                $pdo->prepare("INSERT INTO email_delivery_attempts (outbox_id,attempt_number,outcome,error_message) VALUES (?,?,'cancelled','Recipient no longer authorized.')")->execute([$id,$job['attempts']]);
                continue;
            }
            $pdo->prepare("UPDATE email_outbox SET status='sending',attempts=attempts+1,locked_at=NOW() WHERE id=?")->execute([$id]);
            $attempt=(int)$job['attempts']+1;
            $error=null;
            try {
                $root=rtrim((string)app_config('app_url'),'/');
                if (!filter_var($root,FILTER_VALIDATE_URL) || !in_array(parse_url($root,PHP_URL_SCHEME),['http','https'],true)) throw new RuntimeException('APP_URL is missing.');
                $path=$job['role']==='admin'?'admin/review.php?id=':'application.php?id=';
                $link=$root.'/'.$path.$job['application_id'];
                $body="Hello ".$job['name'].",\n\nApplication ".$job['reference']."\n\n".$job['body']."\n\nOpen application: ".$link."\n\nPERMIT";
                $messageId='<permit-'.hash('sha256',$name.':'.$id).'@'.parse_url($root,PHP_URL_HOST).'>';
                send_app_mail(['to_email'=>$job['email'],'to_name'=>$job['name'],'subject'=>$job['title'].' — '.$job['reference'],'text_body'=>$body,'html_body'=>'<p>'.nl2br(e($body)).'</p>','message_id'=>$messageId],$transport);
            } catch (Throwable) { $error='Delivery failed. Check SMTP configuration, recipient address and provider availability.'; }
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO email_delivery_attempts (outbox_id,attempt_number,outcome,error_message) VALUES (?,?,?,?)')->execute([$id,$attempt,$error?'failed':'sent',$error]);
            if ($error) {
                $delay=min(3600,60*(2**($attempt-1)));
                $pdo->prepare("UPDATE email_outbox SET status=?,last_error=?,locked_at=NULL,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND) WHERE id=?")->execute([$attempt>=5?'failed':'pending',$error,$delay,$id]);
                $counts['failed']++;
            } else {
                $pdo->prepare("UPDATE email_outbox SET status='sent',sent_at=NOW(),locked_at=NULL,last_error=NULL WHERE id=?")->execute([$id]);
                $counts['sent']++;
            }
            $pdo->commit();
        }
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
    }
    return $counts;
}

function generate_overdue_alerts(PDO $pdo): int
{
    $rows=$pdo->query("SELECT x.*,a.reference FROM application_assignments x JOIN applications a ON a.id=x.application_id WHERE a.status='For Review' AND x.due_at<NOW()")->fetchAll();
    foreach ($rows as $r) notify_application($pdo,(int)$r['application_id'],'overdue:'.$r['application_id'].':'.$r['revision'].':'.date('Y-m-d'),'Review overdue','The assigned review deadline was '.$r['due_at'].'. Please open the review queue.','staff');
    return count($rows);
}

function permit_verification_record(PDO $pdo, int $applicationId): array
{
    $q=$pdo->prepare("SELECT a.status,MIN(h.created_at) issued_at FROM applications a LEFT JOIN application_status_history h ON h.application_id=a.id AND h.status='Released' WHERE a.id=? GROUP BY a.id,a.status");
    $q->execute([$applicationId]); $a=$q->fetch();
    if (!$a || $a['status']!=='Released' || !$a['issued_at']) throw new RuntimeException('Released permit required.');
    $expiry=(new DateTimeImmutable($a['issued_at']))->format('Y').'-12-31 23:59:59';
    $pdo->prepare('INSERT INTO permit_verification (application_id,token,issued_at,valid_until) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE application_id=VALUES(application_id)')->execute([$applicationId,bin2hex(random_bytes(32)),$a['issued_at'],$expiry]);
    $q=$pdo->prepare('SELECT * FROM permit_verification WHERE application_id=?');$q->execute([$applicationId]);return $q->fetch();
}

function public_permit_status(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/D',$token)) return null;
    $q=$pdo->prepare("SELECT a.id,a.status,a.permit_number,v.issued_at,v.valid_until,EXISTS(SELECT 1 FROM applications newer WHERE newer.permit_number=a.permit_number AND newer.id>a.id AND newer.status='Released') superseded FROM permit_verification v JOIN applications a ON a.id=v.application_id WHERE v.token=?");
    $q->execute([$token]); $r=$q->fetch();
    if (!$r) return null;
    $status=$r['status']!=='Released'?'Not currently released':($r['superseded']?'Superseded':(new DateTimeImmutable()>new DateTimeImmutable($r['valid_until'])?'Expired':'Released'));
    return ['permit_number'=>$r['permit_number'],'status'=>$status,'issued_at'=>$r['issued_at'],'valid_until'=>$r['valid_until']];
}
