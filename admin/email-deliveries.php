<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';require dirname(__DIR__).'/includes/layout.php';
$user=require_role('admin');$pdo=db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();$id=filter_input(INPUT_POST,'outbox_id',FILTER_VALIDATE_INT);
    $q=$pdo->prepare("UPDATE email_outbox SET status='pending',attempts=0,available_at=NOW(),last_error=NULL WHERE id=? AND status='failed'");$q->execute([$id?:0]);
    if ($q->rowCount()) audit($pdo,(int)$user['id'],'retry_email','email_outbox',(int)$id);
    flash('success',$q->rowCount()?'Retry queued. Existing attempt history is retained.':'Only failed deliveries can be retried.');redirect('admin/email-deliveries.php');
}
$status=(string)($_GET['status']??'all');
$where=in_array($status,['pending','sending','sent','failed','cancelled'],true)?' WHERE o.status='.$pdo->quote($status):'';
$rows=$pdo->query('SELECT o.*,u.email,e.title,a.reference FROM email_outbox o JOIN users u ON u.id=o.user_id JOIN notification_events e ON e.id=o.event_id JOIN applications a ON a.id=e.application_id'.$where.' ORDER BY o.id DESC LIMIT 100')->fetchAll();
render_app_header('Email deliveries',''); ?>
<p>Messages are queued when applications change. The scheduled worker sends them and retries failures up to five times. “Sent” means the SMTP server accepted the message; it does not confirm inbox delivery.</p>
<form><label>Status <select name="status"><?php foreach (['all','pending','sending','sent','failed','cancelled'] as $s): ?><option <?= $s===$status?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></label><button class="button">Filter</button></form>
<div class="panel ops-panel table-responsive"><table><thead><tr><th>Application / event</th><th>Recipient</th><th>Status</th><th>Attempts</th><th>Delivery details</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['reference'].' / '.$r['title']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['status']) ?></td><td><?= (int)$r['attempts'] ?></td><td><?= e($r['last_error']??$r['sent_at']??('Next attempt: '.$r['available_at'])) ?><details><summary>Attempt history</summary><?php $q=$pdo->prepare('SELECT * FROM email_delivery_attempts WHERE outbox_id=? ORDER BY id DESC');$q->execute([$r['id']]);foreach($q as $a): ?><p><?= e($a['created_at'].' — '.$a['outcome'].' '.$a['error_message']) ?></p><?php endforeach; ?></details></td><td><?php if($r['status']==='failed'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="outbox_id" value="<?= (int)$r['id'] ?>"><button class="button">Retry</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table><p>Most recent 100 deliveries for this filter.</p></div><?php render_app_footer(); ?>
