<?php
// ─── No output before headers: clean any stray buffers ───
while (ob_get_level() > 0) { ob_end_clean(); }

require_once("../app/config/database.php");
$conn = conn(); require_admin();

require_once("../vendor/tcpdf/fpdf.php");

$type = $_GET['type'] ?? '';
$student_id = (int)($_GET['student_id'] ?? 0);

// ─── Shared filters ───
$search   = clean_data($_GET['search'] ?? '');
$exam_id  = (int)($_GET['exam_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$status   = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$attempt_type = $_GET['attempt_type'] ?? 'latest';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

$where = "1=1";
if ($search) { $s = mysqli_real_escape_string($conn, $search); $where .= " AND (st.name LIKE '%$s%' OR st.email LIKE '%$s%' OR e.exam_title LIKE '%$s%' OR sub.subject_name LIKE '%$s%')"; }
if ($exam_id) { $where .= " AND e.id=$exam_id"; }
if ($subject_id) { $where .= " AND e.subject_id=$subject_id"; }
if ($status === 'Pass') { $where .= " AND r.status='Pass'"; } elseif ($status === 'Fail') { $where .= " AND r.status!='Pass'"; }
if ($date_from) { $df = mysqli_real_escape_string($conn, $date_from); $where .= " AND r.submitted_at >= '$df 00:00:00'"; }
if ($date_to) { $dt = mysqli_real_escape_string($conn, $date_to); $where .= " AND r.submitted_at <= '$dt 23:59:59'"; }
if ($attempt_type === 'latest') { $where .= " AND r.id IN (SELECT MAX(r2.id) FROM results r2 WHERE r2.student_id=r.student_id GROUP BY r2.exam_id)"; }
elseif ($attempt_type === 'highest') { $where .= " AND r.id IN (SELECT r3.id FROM results r3 INNER JOIN (SELECT student_id, exam_id, MAX(percentage) AS maxp FROM results GROUP BY student_id, exam_id) best ON r3.student_id=best.student_id AND r3.exam_id=best.exam_id AND r3.percentage=best.maxp)"; }

// ══════════════════════════════════════════════════
// CSV EXPORT
// ══════════════════════════════════════════════════
if ($type === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reports_'.date('Ymd').'.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, ['Student Name','Email','Course','Exam','Subject','Marks','Total','Percentage','Status','Submitted At']);
    $q = mysqli_query($conn, "SELECT st.name, st.email, st.course, e.exam_title, sub.subject_name, r.marks_obtained, r.total_questions, r.percentage, r.status, r.submitted_at FROM results r LEFT JOIN students st ON r.student_id=st.id LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE $where ORDER BY st.name");
    if ($q) { while ($r = mysqli_fetch_assoc($q)) { fputcsv($out, [$r['name'],$r['email'],$r['course'],$r['exam_title'],$r['subject_name'],(int)$r['marks_obtained'],(int)$r['total_questions'],round((float)$r['percentage'],1).'%',$r['status'],$r['submitted_at']]); } }
    fclose($out);
    exit;
}

// ══════════════════════════════════════════════════
// EXCEL EXPORT (HTML table)
// ══════════════════════════════════════════════════
if ($type === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=reports_'.date('Ymd').'.xls');
    echo '<html><head><meta charset="UTF-8"><style>td,th{border:1px solid #ccc;padding:4px 8px;font-size:12px}</style></head><body>';
    echo '<table><thead><tr><th>Student</th><th>Email</th><th>Course</th><th>Exam</th><th>Subject</th><th>Marks</th><th>Total</th><th>Percentage</th><th>Status</th><th>Date</th></tr></thead><tbody>';
    $q = mysqli_query($conn, "SELECT st.name, st.email, st.course, e.exam_title, sub.subject_name, r.marks_obtained, r.total_questions, r.percentage, r.status, r.submitted_at FROM results r LEFT JOIN students st ON r.student_id=st.id LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE $where ORDER BY st.name");
    if ($q) { while ($r = mysqli_fetch_assoc($q)) { echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['email']).'</td><td>'.h($r['course']).'</td><td>'.h($r['exam_title']).'</td><td>'.h($r['subject_name']).'</td><td>'.(int)$r['marks_obtained'].'</td><td>'.(int)$r['total_questions'].'</td><td>'.round((float)$r['percentage'],1).'%</td><td>'.$r['status'].'</td><td>'.$r['submitted_at'].'</td></tr>'; } }
    echo '</tbody></table></body></html>';
    exit;
}

// ══════════════════════════════════════════════════
// PDF ENGINE — professional branded documents
// ══════════════════════════════════════════════════

/**
 * Make any DB/UTF-8 string safe for FPDF core fonts (Win-1252).
 * Strips HTML entities that would otherwise print literally.
 */
function pdf_text($value) {
    $value = (string)$value;
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace(["\xE2\x80\x98","\xE2\x80\x99","\xE2\x80\x9C","\xE2\x80\x9D","\xE2\x80\x93","\xE2\x80\x94","\xE2\x80\xA6"], ["'","'",'"','"','-','-','...'], $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted !== false) { return $converted; }
    }
    return preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $value);
}

function pdf_filename($name) {
    $name = preg_replace('/[^A-Za-z0-9 _-]+/', '', (string)$name);
    $name = trim(preg_replace('/\s+/', '_', $name), '_');
    return $name !== '' ? $name : 'Report';
}

class ReportPDF extends FPDF {
    public $docTitle = '';
    public $docSub = '';

    function Header() {
        // Navy brand band
        $this->SetFillColor(15, 23, 42);
        $this->Rect(0, 0, 210, 30, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 15);
        $this->SetXY(10, 7);
        $this->Cell(190, 7, 'AI-Based Adaptive Online Examination', 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->SetX(10);
        $this->Cell(190, 5, 'Student Performance Analytics  |  Generated: ' . date('d M Y, h:i A'), 0, 1, 'C');
        // Document title
        $this->SetTextColor(30, 41, 59);
        $this->SetY(34);
        if ($this->docTitle !== '') {
            $this->SetFont('Helvetica', 'B', 14);
            $this->Cell(0, 9, $this->docTitle, 0, 1, 'C');
        }
        if ($this->docSub !== '') {
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 5, $this->docSub, 0, 1, 'C');
            $this->SetTextColor(30, 41, 59);
        }
        $this->Ln(3);
        // Accent rule
        $this->SetDrawColor(37, 99, 235);
        $this->SetLineWidth(0.8);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    function Footer() {
        $this->SetY(-18);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 6, 'AI-Based Adaptive Online Examination System  |  Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function sectionTitle($title) {
        if ($this->GetY() > 250) { $this->AddPage(); }
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(29, 78, 216);
        $this->Cell(0, 7, $title, 0, 1, 'L');
        $this->SetTextColor(30, 41, 59);
        $this->Ln(1);
    }

    function infoRow($label, $value) {
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(45, 6.5, $label . ':', 0, 0, 'L');
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(0, 6.5, $value, 0, 1, 'L');
    }

    function statBoxes($stats) {
        // $stats: array of [label, value]
        $n = count($stats);
        if ($n === 0) return;
        $w = 190 / $n;
        $y = $this->GetY();
        if ($y > 245) { $this->AddPage(); $y = $this->GetY(); }
        $this->SetFillColor(248, 250, 252);
        $this->SetDrawColor(226, 232, 240);
        foreach ($stats as $i => $s) {
            $x = 10 + $i * $w;
            $this->Rect($x, $y, $w - 2, 16, 'DF');
            $this->SetXY($x, $y + 1.5);
            $this->SetFont('Helvetica', 'B', 12);
            $this->Cell($w - 2, 6, (string)$s[1], 0, 2, 'C');
            $this->SetFont('Helvetica', '', 7.5);
            $this->SetTextColor(100, 116, 139);
            $this->Cell($w - 2, 5, (string)$s[0], 0, 2, 'C');
            $this->SetTextColor(30, 41, 59);
        }
        $this->SetY($y + 20);
    }

    function tableHead($headers, $widths) {
        if ($this->GetY() > 248) { $this->AddPage(); }
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(15, 23, 42);
        $this->SetTextColor(255, 255, 255);
        foreach ($headers as $i => $h) {
            $this->Cell($widths[$i], 7.5, $h, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(30, 41, 59);
    }
}

// ─── Student Report PDF ──────────────────────
if ($type === 'student_pdf') {
    if (!$student_id) {
        redirect_alert("Please open a student record first, then download its report.", "reports.php", "warning");
    }
    $st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id=$student_id"));
    if (!$st) { redirect_alert("Student not found.", "reports.php", "error"); }

    $results = mysqli_query($conn, "SELECT r.*, e.exam_title, e.exam_mode, sub.subject_name FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE r.student_id=$student_id ORDER BY r.id ASC");
    $all = []; $total = 0; $pass = 0; $fail = 0; $sumPct = 0;
    if ($results) {
        while ($r = mysqli_fetch_assoc($results)) {
            $all[] = $r; $total++;
            $sumPct += round((float)$r['percentage'], 1);
            if ($r['status'] == 'Pass') { $pass++; } else { $fail++; }
        }
    }
    $avgPct = $total > 0 ? round($sumPct / $total, 1) : 0;

    $pdf = new ReportPDF();
    $pdf->AliasNbPages();
    $pdf->docTitle = 'Student Performance Report';
    $pdf->docSub = pdf_text($st['name']) . '  |  ' . pdf_text($st['email']);
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->AddPage();

    $pdf->sectionTitle('Student Information');
    $pdf->infoRow('Name', pdf_text($st['name']));
    $pdf->infoRow('Email', pdf_text($st['email']));
    $pdf->infoRow('Course', pdf_text($st['course']) . ' - ' . pdf_text($st['semester']));
    $pdf->infoRow('Phone', pdf_text($st['phone'] ?? 'N/A'));
    $pdf->Ln(3);

    $pdf->sectionTitle('Overall Summary');
    $pdf->statBoxes([
        ['EXAMS', $total],
        ['PASS', $pass],
        ['FAIL', $fail],
        ['AVG SCORE', $avgPct . '%'],
    ]);

    $pdf->sectionTitle('Attempt History');
    if (count($all) > 0) {
        $pdf->tableHead(['#', 'Exam', 'Subject', 'Marks', 'Score', 'Status'], [10, 62, 42, 24, 22, 30]);
        $pdf->SetFont('Helvetica', '', 8.5);
        $fill = false;
        foreach ($all as $idx => $r) {
            if ($pdf->GetY() > 252) { $pdf->AddPage(); $pdf->tableHead(['#', 'Exam', 'Subject', 'Marks', 'Score', 'Status'], [10, 62, 42, 24, 22, 30]); $pdf->SetFont('Helvetica', '', 8.5); }
            $pct = round((float)$r['percentage'], 1);
            $marksTotal = max((int)$r['total_questions'], 1);
            if ($fill) { $pdf->SetFillColor(248, 250, 252); }
            $pdf->Cell(10, 7, (string)($idx + 1), 1, 0, 'C', $fill);
            $pdf->Cell(62, 7, substr(pdf_text($r['exam_title']), 0, 38), 1, 0, 'L', $fill);
            $pdf->Cell(42, 7, substr(pdf_text($r['subject_name'] ?? ''), 0, 26), 1, 0, 'L', $fill);
            $pdf->Cell(24, 7, (int)$r['marks_obtained'] . '/' . $marksTotal, 1, 0, 'C', $fill);
            $pdf->Cell(22, 7, $pct . '%', 1, 0, 'C', $fill);
            if (($r['status'] ?? '') === 'Pass') { $pdf->SetTextColor(22, 163, 74); } else { $pdf->SetTextColor(220, 38, 38); }
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->Cell(30, 7, $r['status'] ?? '', 1, 1, 'C', $fill);
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetTextColor(30, 41, 59);
            $fill = !$fill;
        }
    } else {
        $pdf->SetFont('Helvetica', 'I', 10);
        $pdf->Cell(0, 8, 'No exam attempts recorded for this student.', 0, 1, 'C');
    }

    // AI Analysis (latest result)
    $latest = end($all);
    if ($latest && !empty($latest['ai_insight'])) {
        $pdf->Ln(2);
        $pdf->sectionTitle('AI Performance Analysis (Latest Attempt)');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetFillColor(239, 246, 255);
        $pdf->SetDrawColor(191, 219, 254);
        $x = $pdf->GetX(); $y = $pdf->GetY();
        $pdf->MultiCell(190, 5.5, pdf_text($latest['ai_insight']), 1, 'L', true);
    }

    $pdf->Output('D', 'Student_Report_' . pdf_filename($st['name']) . '_' . date('Ymd') . '.pdf');
    exit;
}

// ─── Exam Report PDF ─────────────────────────
if ($type === 'exam_pdf') {
    $scope_note = '';
    $exam_scope = '';
    if ($subject_id) {
        $sn = mysqli_fetch_assoc(mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id=$subject_id"));
        $exam_scope .= " AND e.subject_id=$subject_id";
        $scope_note = 'Subject: ' . ($sn['subject_name'] ?? '');
    }
    $examQ = mysqli_query($conn, "SELECT e.*, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id WHERE 1=1 $exam_scope ORDER BY e.exam_title");
    $exams = [];
    if ($examQ) { while ($e = mysqli_fetch_assoc($examQ)) { $exams[] = $e; } }

    $pdf = new ReportPDF();
    $pdf->AliasNbPages();
    $pdf->docTitle = 'Examination Report';
    $pdf->docSub = $scope_note !== '' ? pdf_text($scope_note) : 'All subjects  |  ' . count($exams) . ' exam(s)';
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->AddPage();

    if (count($exams) === 0) {
        $pdf->SetFont('Helvetica', 'I', 11);
        $pdf->Cell(0, 10, 'No examinations found.', 0, 1, 'C');
    } else {
        $pdf->tableHead(['Exam', 'Subject', 'T', 'P', 'F', 'Avg', 'Best'], [62, 40, 14, 14, 14, 22, 24]);
        $pdf->SetFont('Helvetica', '', 8.5);
        $fill = false;
        foreach ($exams as $exam) {
            if ($pdf->GetY() > 252) { $pdf->AddPage(); $pdf->tableHead(['Exam', 'Subject', 'T', 'P', 'F', 'Avg', 'Best'], [62, 40, 14, 14, 14, 22, 24]); $pdf->SetFont('Helvetica', '', 8.5); }
            $eid = (int)$exam['id'];
            $rq = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(CASE WHEN status='Pass' THEN 1 ELSE 0 END) AS pass, AVG(percentage) AS avgp, MAX(percentage) AS maxp FROM results WHERE exam_id=$eid");
            $rs = $rq ? mysqli_fetch_assoc($rq) : null;
            $t = (int)($rs['total'] ?? 0); $p = (int)($rs['pass'] ?? 0); $f = $t - $p;
            $avg = round((float)($rs['avgp'] ?? 0), 1);
            $max = round((float)($rs['maxp'] ?? 0), 1);
            if ($fill) { $pdf->SetFillColor(248, 250, 252); }
            $pdf->Cell(62, 7.5, substr(pdf_text($exam['exam_title']), 0, 40), 1, 0, 'L', $fill);
            $pdf->Cell(40, 7.5, substr(pdf_text($exam['subject_name'] ?? ''), 0, 26), 1, 0, 'L', $fill);
            $pdf->Cell(14, 7.5, (string)$t, 1, 0, 'C', $fill);
            $pdf->Cell(14, 7.5, (string)$p, 1, 0, 'C', $fill);
            $pdf->Cell(14, 7.5, (string)$f, 1, 0, 'C', $fill);
            $pdf->Cell(22, 7.5, $avg . '%', 1, 0, 'C', $fill);
            $pdf->Cell(24, 7.5, $max . '%', 1, 1, 'C', $fill);
            $fill = !$fill;
        }
    }
    $pdf->Output('D', 'Exam_Report_' . date('Ymd') . '.pdf');
    exit;
}

// ─── Analytics PDF ───────────────────────────
if ($type === 'analytics_pdf') {
    $total_students = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM students"))['c'];
    $total_exams    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM exams"))['c'];
    $total_attempts = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results"))['c'];
    $avg_score      = round((float)mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(percentage) AS a FROM results"))['a'], 1);
    $pass_count     = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM results WHERE status='Pass'"))['c'];
    $fail_count     = max(0, $total_attempts - $pass_count);
    $pass_rate      = $total_attempts > 0 ? round(($pass_count / $total_attempts) * 100, 1) : 0;
    $at_risk        = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS c FROM results WHERE percentage < 40"))['c'];
    $top_s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id GROUP BY s.id ORDER BY avgp DESC LIMIT 1"));
    $low_s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT s.subject_name, AVG(r.percentage) AS avgp FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id GROUP BY s.id ORDER BY avgp ASC LIMIT 1"));

    $pdf = new ReportPDF();
    $pdf->AliasNbPages();
    $pdf->docTitle = 'Overall Analytics Report';
    $pdf->docSub = 'Institution-wide performance overview';
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->AddPage();

    $pdf->sectionTitle('Key Metrics');
    $pdf->statBoxes([
        ['STUDENTS', $total_students],
        ['EXAMS', $total_exams],
        ['ATTEMPTS', number_format($total_attempts)],
        ['AVG SCORE', $avg_score . '%'],
    ]);

    $pdf->sectionTitle('Performance Breakdown');
    $rows = [
        ['Pass Count', $pass_count, $total_attempts > 0 ? ($pass_count / $total_attempts) * 100 : 0, [22, 163, 74]],
        ['Fail Count', $fail_count, $total_attempts > 0 ? ($fail_count / $total_attempts) * 100 : 0, [220, 38, 38]],
        ['Students at Risk (<40%)', $at_risk, 0, [217, 119, 6]],
    ];
    foreach ($rows as $r) {
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(70, 7, pdf_text($r[0]) . ':', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(25, 7, (string)$r[1], 0, 0, 'L');
        if ($r[2] > 0) {
            $bx = $pdf->GetX() + 2;
            $by = $pdf->GetY() + 1;
            $bw = 80; $bh = 5;
            $pdf->SetFillColor(241, 245, 249);
            $pdf->Rect($bx, $by, $bw, $bh, 'F');
            $pdf->SetFillColor($r[3][0], $r[3][1], $r[3][2]);
            $pdf->Rect($bx, $by, max(1, $bw * $r[2] / 100), $bh, 'F');
            $pdf->SetXY($bx + $bw + 3, $by - 1);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(20, 7, round($r[2], 1) . '%', 0, 1, 'L');
        } else {
            $pdf->Ln(7);
        }
    }
    $pdf->Ln(3);
    $pdf->sectionTitle('Subject Highlights');
    $pdf->infoRow('Pass Rate', $pass_rate . '% overall');
    $pdf->infoRow('Top Subject', pdf_text(($top_s['subject_name'] ?? 'N/A') . ($top_s ? ' (' . round((float)$top_s['avgp'], 1) . '%)' : '')));
    $pdf->infoRow('Needs Attention', pdf_text(($low_s['subject_name'] ?? 'N/A') . ($low_s ? ' (' . round((float)$low_s['avgp'], 1) . '%)' : '')));

    $pdf->Output('D', 'Analytics_Report_' . date('Ymd') . '.pdf');
    exit;
}

redirect_alert("Invalid export type.", "reports.php", "error");