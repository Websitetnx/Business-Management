<?php
declare(strict_types=1);

function report_range(string $start, string $end): array
{
    $a=DateTimeImmutable::createFromFormat('!Y-m-d',$start);$b=DateTimeImmutable::createFromFormat('!Y-m-d',$end);
    if (!$a || !$b || $a->format('Y-m-d')!==$start || $b->format('Y-m-d')!==$end || $a>$b || $a->diff($b)->days>366) throw new InvalidArgumentException('Select a valid date range of up to 367 days.');
    return [$a->format('Y-m-d H:i:s'),$b->modify('+1 day')->format('Y-m-d H:i:s')];
}

function report_rows(PDO $pdo, string $type, array $range): array
{
    $queries=[
        'applications'=>"SELECT DATE(submitted_at) day,application_type,status,COUNT(*) application_count FROM applications WHERE submitted_at>=? AND submitted_at<? GROUP BY DATE(submitted_at),application_type,status ORDER BY day,application_type,status",
        'processing'=>"SELECT DATE(first_approval) approval_day,application_type,COUNT(*) approvals,ROUND(AVG(TIMESTAMPDIFF(SECOND,submitted_at,first_approval))/86400,2) average_calendar_days,ROUND(MAX(TIMESTAMPDIFF(SECOND,submitted_at,first_approval))/86400,2) longest_calendar_days FROM (SELECT a.id,a.application_type,a.submitted_at,MIN(h.created_at) first_approval FROM applications a JOIN application_status_history h ON h.application_id=a.id AND h.status IN ('Approved','Released') GROUP BY a.id,a.application_type,a.submitted_at) approvals WHERE first_approval>=? AND first_approval<? AND first_approval>=submitted_at GROUP BY DATE(first_approval),application_type ORDER BY approval_day,application_type",
        'collections'=>"SELECT DATE(paid_at) payment_day,payment_method,COUNT(*) verified_payments,SUM(amount) total_php FROM payments WHERE status='Paid' AND paid_at>=? AND paid_at<? GROUP BY DATE(paid_at),payment_method ORDER BY payment_day,payment_method",
        'revisions'=>"SELECT DATE(created_at) revision_day,COUNT(*) revision_events,COUNT(DISTINCT application_id) affected_applications FROM application_status_history WHERE status='Needs Revision' AND created_at>=? AND created_at<? GROUP BY DATE(created_at) ORDER BY revision_day",
        'renewals'=>"SELECT DATE(submitted_at) day,status,COUNT(*) renewals FROM applications WHERE application_type='Renewal' AND submitted_at>=? AND submitted_at<? GROUP BY DATE(submitted_at),status ORDER BY day,status",
    ];
    if (!isset($queries[$type])) throw new InvalidArgumentException('Unknown report.');
    $q=$pdo->prepare($queries[$type]);$q->execute($range);return $q->fetchAll();
}

function csv_safe_cell(mixed $value): string
{
    $text=(string)($value??'');
    return preg_match('/^[\s]*[=+@\-]/u',$text) || preg_match('/^[\t\r\n]/',$text)?"'".$text:$text;
}
