<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';require dirname(__DIR__).'/includes/layout.php';require dirname(__DIR__).'/includes/reports.php';
$user=require_role(['admin','treasurer']);$type=(string)($_GET['type']??(is_treasurer_role($user['role'])?'collections':'applications'));
if (is_treasurer_role($user['role']) && $type!=='collections') {http_response_code(403);exit('Treasurer access is limited to payment collections.');}
$start=(string)($_GET['start']??date('Y-m-01'));$end=(string)($_GET['end']??date('Y-m-d'));
try {$rows=report_rows(db(),$type,report_range($start,$end));}catch(InvalidArgumentException $e){http_response_code(400);exit(e($e->getMessage()));}
header('Cache-Control: private, no-store');
if (($_GET['format']??'')==='csv') {
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="permit-'.$type.'-'.$start.'-'.$end.'.csv"');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
    if ($rows) {fputcsv($out,array_keys($rows[0]),',','"','');foreach($rows as $r)fputcsv($out,array_map('csv_safe_cell',$r),',','"','');}
    else fputcsv($out,['No records in this date range'],',','"','');
    fclose($out);exit;
}
$types=is_treasurer_role($user['role'])?['collections']:['applications','processing','collections','revisions','renewals'];
$descriptions=['applications'=>'Applications submitted in this period, grouped by current status.','processing'=>'Time from submission to first approval/release, grouped by first approval date. Calendar days include waiting for applicant corrections.','collections'=>'Verified Paid payments grouped by paid date; pending and failed payments are excluded.','revisions'=>'Revision history events and distinct affected applications per day. Daily distinct counts cannot be summed into a distinct period total.','renewals'=>'Renewals submitted in this period, grouped by current status.'];
render_app_header('Reports and exports',''); ?>
<form method="get" class="ops-toolbar"><label>Report<select name="type"><?php foreach($types as $t): ?><option <?= $t===$type?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></label><label>From<input type="date" name="start" value="<?= e($start) ?>" required></label><label>Through<input type="date" name="end" value="<?= e($end) ?>" required></label><button class="button">View</button></form>
<p><?= e($descriptions[$type]) ?> Date filters include both selected days.</p>
<div class="ops-toolbar"><a class="button" href="?<?= e(http_build_query(['type'=>$type,'start'=>$start,'end'=>$end,'format'=>'csv'])) ?>">Export CSV</a><button class="button button-secondary" onclick="window.print()">Print / Save PDF</button></div>
<article class="panel ops-panel table-responsive"><h2><?= e(ucfirst($type).' report: '.$start.' to '.$end) ?></h2><p>Generated <?= e(date('Y-m-d H:i:s').' '.app_config('timezone')) ?></p><?php if (!$rows): ?><p>No matching records.</p><?php else: ?><table><thead><tr><?php foreach(array_keys($rows[0]) as $k): ?><th><?= e(str_replace('_',' ',$k)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $r): ?><tr><?php foreach($r as $v): ?><td><?= e($v??'Not specified') ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table><?php endif; ?></article>
<?php render_app_footer(); ?>
