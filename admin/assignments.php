<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
require dirname(__DIR__).'/includes/layout.php';
$user=require_role('admin'); $pdo=db(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $id=filter_input(INPUT_POST,'application_id',FILTER_VALIDATE_INT);
        $reviewer=filter_input(INPUT_POST,'reviewer_id',FILTER_VALIDATE_INT);
        $due=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',(string)($_POST['due_at']??''));
        if (!$id || !$reviewer || !$due || $due->format('Y-m-d\TH:i')!==($_POST['due_at']??'') || $due<=new DateTimeImmutable()) throw new RuntimeException('Select an application, reviewer and future deadline.');
        $pdo->beginTransaction();
        $q=$pdo->prepare("SELECT id FROM applications WHERE id=? AND status IN ('For Review','Needs Revision') FOR UPDATE");$q->execute([$id]);
        if (!$q->fetch()) throw new RuntimeException('Only applications awaiting review or correction can be assigned.');
        $q=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='admin' AND is_active=1 FOR UPDATE");$q->execute([$reviewer]);
        if (!$q->fetch()) throw new RuntimeException('Select an active BPLO administrator.');
        $pdo->prepare('INSERT INTO application_assignments (application_id,reviewer_id,assigned_by,due_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE reviewer_id=VALUES(reviewer_id),assigned_by=VALUES(assigned_by),assigned_at=NOW(),due_at=VALUES(due_at),revision=revision+1')->execute([$id,$reviewer,$user['id'],$due->format('Y-m-d H:i:s')]);
        $pdo->prepare('INSERT INTO assignment_history (application_id,reviewer_id,assigned_by,due_at) VALUES (?,?,?,?)')->execute([$id,$reviewer,$user['id'],$due->format('Y-m-d H:i:s')]);
        $event=(int)$pdo->lastInsertId();
        notify_application($pdo,$id,'assignment:'.$event,'Reviewer assignment updated','A BPLO reviewer has been assigned. Internal review deadline: '.$due->format('Y-m-d H:i').'.','staff');
        audit($pdo,(int)$user['id'],'assign_reviewer','application',$id);$pdo->commit();
        flash('success','Reviewer assigned and staff notifications queued.');redirect('admin/assignments.php');
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e instanceof PDOException?'Assignment could not be saved.':$e->getMessage(); }
}
$filter=(string)($_GET['filter']??'all');
$where="a.status IN ('For Review','Needs Revision')";
if ($filter==='mine') $where.=' AND x.reviewer_id='.(int)$user['id'];
if ($filter==='overdue') $where.=" AND a.status='For Review' AND x.due_at<NOW()";
if ($filter==='unassigned') $where.=' AND x.application_id IS NULL';
$rows=$pdo->query("SELECT a.id,a.reference,a.status,u.name,u.is_active,x.due_at FROM applications a LEFT JOIN application_assignments x ON x.application_id=a.id LEFT JOIN users u ON u.id=x.reviewer_id WHERE $where ORDER BY x.due_at IS NULL,x.due_at,a.id DESC LIMIT 200")->fetchAll();
$reviewers=$pdo->query("SELECT id,name FROM users WHERE role='admin' AND is_active=1 ORDER BY name")->fetchAll();
render_app_header('Reviewer assignments','review');
?>
<p>Deadlines are internal targets in <?= e(app_config('timezone')) ?>. Overdue alerts apply while an application is For Review; applicant corrections pause alerts.</p>
<?php if ($error): ?><p class="form-alert form-alert-error"><?= e($error) ?></p><?php endif; ?>
<form method="get"><label>Show <select name="filter"><?php foreach (['all','mine','overdue','unassigned'] as $v): ?><option <?= $filter===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></label><button class="button">Filter</button></form>
<form method="post" class="panel ops-panel"><?= csrf_field() ?>
<label class="field">Application<select name="application_id" required><?php foreach ($rows as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (int)($_GET['application_id']??0)===(int)$r['id']?'selected':'' ?>><?= e($r['reference']) ?></option><?php endforeach; ?></select></label>
<label class="field">Reviewer<select name="reviewer_id" required><?php foreach ($reviewers as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></label>
<label class="field">Review deadline<input type="datetime-local" name="due_at" required></label><button class="button">Assign / reassign</button></form>
<div class="panel ops-panel table-responsive"><table><thead><tr><th>Application</th><th>Status</th><th>Reviewer</th><th>Deadline</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><a href="<?= e(url('admin/review.php?id='.$r['id'])) ?>"><?= e($r['reference']) ?></a></td><td><?= e($r['status']) ?></td><td><?= e($r['name']??'Unassigned') ?><?= $r['name']&&!$r['is_active']?' (inactive — reassign)':'' ?></td><td><?= e($r['due_at']??'No deadline') ?> <?= $r['due_at'] && $r['status']==='For Review' && strtotime($r['due_at'])<time()?' — Overdue':'' ?></td></tr><?php endforeach; ?>
</tbody></table><p>Showing up to 200 applications. Use filters to narrow the queue.</p></div>
<?php $history=$pdo->query('SELECT h.*,a.reference,u.name FROM assignment_history h JOIN applications a ON a.id=h.application_id JOIN users u ON u.id=h.reviewer_id ORDER BY h.id DESC LIMIT 50')->fetchAll(); ?>
<details class="panel ops-panel"><summary>Recent assignment history</summary><?php foreach ($history as $h): ?><p><?= e($h['created_at'].' · '.$h['reference'].' · '.$h['name'].' · Due '.$h['due_at']) ?></p><?php endforeach; ?></details>
<?php render_app_footer(); ?>
