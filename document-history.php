<?php
declare(strict_types=1);
require __DIR__.'/includes/bootstrap.php';require __DIR__.'/includes/layout.php';
$user=require_role(['applicant','admin']);$pdo=db();$admin=can_manage_applications($user['role']);
$id=filter_input(INPUT_GET,'application_id',FILTER_VALIDATE_INT);
$q=$pdo->prepare('SELECT id,user_id,reference FROM applications WHERE id=?');$q->execute([$id?:0]);$a=$q->fetch();
if (!$a || (!$admin && (int)$a['user_id']!==(int)$user['id'])) {http_response_code(404);exit('Application not found.');}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!$admin) {http_response_code(403);exit('Administrator access required.');} verify_csrf();
    $version=filter_input(INPUT_POST,'version_id',FILTER_VALIDATE_INT);$note=trim((string)($_POST['note']??''));
    if ($note==='' || mb_strlen($note)>4000) {flash('error','Enter a note of 1 to 4000 characters.');redirect('document-history.php?application_id='.$id);}
    $q=$pdo->prepare('SELECT v.id FROM document_versions v JOIN application_documents d ON d.id=v.document_id WHERE v.id=? AND d.application_id=?');$q->execute([$version?:0,$id]);
    if (!$q->fetch()) {http_response_code(404);exit('Version not found.');}
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO document_review_notes (version_id,reviewer_id,note) VALUES (?,?,?)')->execute([$version,$user['id'],$note]);
    audit($pdo,(int)$user['id'],'document_review_note','document_version',(int)$version);$pdo->commit();
    flash('success','Internal reviewer note saved.');redirect('document-history.php?application_id='.$id);
}
$q=$pdo->prepare('SELECT v.*,d.document_type,d.stored_name current_file,u.name uploader FROM document_versions v JOIN application_documents d ON d.id=v.document_id LEFT JOIN users u ON u.id=v.uploaded_by WHERE d.application_id=? ORDER BY d.id,v.id DESC');$q->execute([$id]);$versions=$q->fetchAll();
render_app_header('Document version history',$admin?'review':'track'); ?>
<p><?= e($a['reference']) ?> · Previous uploads are retained. Internal reviewer notes are visible only to BPLO administrators.</p>
<?php foreach ($versions as $v): ?><article class="panel ops-panel"><h3><?= e(document_definitions()[$v['document_type']][0]??$v['document_type']) ?> — <?= $v['stored_name']===$v['current_file']?'Current':'Previous' ?></h3>
<p><a href="<?= e(url('document-version.php?id='.$v['id'])) ?>" target="_blank" rel="noopener"><?= e($v['original_name']) ?></a></p><p>Uploaded <?= e($v['uploaded_at']) ?> by <?= e($v['uploader']??'Unknown') ?><?= $v['archived_at']?' · Replaced '.e($v['archived_at']):'' ?></p>
<?php $q=$pdo->prepare('SELECT * FROM document_scan_history WHERE version_id=? ORDER BY id DESC');$q->execute([$v['id']]);foreach($q as $s):$scan=json_decode($s['result_json'],true)?:[]; ?>
<details><summary>AI result · <?= e($s['scanned_at'].' · '.($scan['scan_status']??'')) ?></summary><p><?= e($scan['summary']??'No completed assessment.') ?></p><p>Type: <?= e($scan['detected_document_type']??'Unknown') ?> · Quality: <?= e($scan['quality_score']??'N/A') ?> · Confidence: <?= e($scan['confidence_score']??'N/A') ?></p><ul><?php foreach(document_scan_advisory_issues($scan['issues']??null) as $issue): ?><li><?= e($issue) ?></li><?php endforeach; ?></ul>
<?php if($admin): ?><pre><?= e(json_encode($scan,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?></details><?php endforeach; ?>
<?php if($admin): $q=$pdo->prepare('SELECT n.note,n.created_at,u.name FROM document_review_notes n JOIN users u ON u.id=n.reviewer_id WHERE n.version_id=? ORDER BY n.id');$q->execute([$v['id']]);foreach($q as $n): ?><p><?= e($n['created_at'].' · '.$n['name'].': '.$n['note']) ?></p><?php endforeach; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>"><label class="field">Internal reviewer note<textarea name="note" required maxlength="4000"></textarea></label><button class="button">Save note</button></form><?php endif; ?></article><?php endforeach; ?>
<?php render_app_footer(); ?>
