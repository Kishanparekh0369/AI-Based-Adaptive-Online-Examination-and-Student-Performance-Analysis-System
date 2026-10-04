<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once("../app/config/database.php");
$conn = conn(); require_admin();
require_once("../vendor/tcpdf/fpdf.php");

$ids = isset($_GET['student_id']) ? preg_replace('/[^0-9,]/', '', $_GET['student_id']) : '0';
$id_parts = array_filter(array_map('intval', explode(',', $ids)));
if (empty($id_parts)) {
    $id_parts = [0];
}
$first_id = (int)$id_parts[0];
$ids_sql = implode(',', $id_parts);
$where = "r.student_id IN ($ids_sql)";

$student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id=$first_id"));
$results = mysqli_query($conn, "SELECT r.*, e.exam_title, e.exam_mode, e.total_marks AS exam_total, s.subject_name FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id WHERE $where ORDER BY r.id ASC");
$all = [];
while ($r = mysqli_fetch_assoc($results)) { $all[] = $r; }

$total = count($all);
$pass = 0;
$fail = 0;
$sumPct = 0;
$bestPct = 0;
$bestSubject = "N/A";
$latestInsight = "";
$subjectStats = [];
$difficultyStats = [];

foreach ($all as $r) {
    $pct = round((float)$r['percentage'], 1);
    $sumPct += $pct;
    if ($r['status'] == 'Pass') { $pass++; } else { $fail++; }
    if ($pct >= $bestPct) {
        $bestPct = $pct;
        $bestSubject = $r['subject_name'] ?: "N/A";
    }
    if (!empty($r['ai_insight'])) {
        $latestInsight = $r['ai_insight'];
    }
    $subject = $r['subject_name'] ?: "General";
    if (!isset($subjectStats[$subject])) { $subjectStats[$subject] = ['count' => 0, 'sum' => 0]; }
    $subjectStats[$subject]['count']++;
    $subjectStats[$subject]['sum'] += $pct;

    if (!empty($r['difficulty_breakdown'])) {
        $decoded = json_decode($r['difficulty_breakdown'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $difficulty => $data) {
                if (!isset($difficultyStats[$difficulty])) { $difficultyStats[$difficulty] = ['total' => 0, 'correct' => 0]; }
                $difficultyStats[$difficulty]['total'] += (int)($data['total'] ?? 0);
                $difficultyStats[$difficulty]['correct'] += (int)($data['correct'] ?? 0);
            }
        }
    }
}

$avgPct = $total > 0 ? round($sumPct / $total, 1) : 0;
$passPct = $total > 0 ? round(($pass / $total) * 100, 1) : 0;
$latestInsight = $latestInsight ?: "AI analysis is not available for this student yet.";

function pdf_clean($value)
{
    $value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
    $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);
    $value = preg_replace('/\s+/', ' ', trim($value));
    $value = strtr($value, [
        "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'",
        "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
        "\xE2\x80\x93" => "-", "\xE2\x80\x94" => "-",
        "\xE2\x80\xA6" => "...", "\xC2\xA0" => " "
    ]);
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
    if ($converted === false) {
        $converted = preg_replace('/[^\x20-\x7E]/', '', $value);
    }
    return $converted;
}

function pdf_short($value, $length)
{
    $value = pdf_clean($value);
    return strlen($value) > $length ? substr($value, 0, max(0, $length - 3)) . '...' : $value;
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$pdf->SetFillColor(15, 23, 42);
$pdf->Rect(0, 0, 210, 27, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 14);
$pdf->SetXY(10, 7);
$pdf->Cell(190, 6, 'AI-Based Adaptive Online Examination System', 0, 1, 'C');
$pdf->SetFont('Helvetica', '', 8.5);
$pdf->Cell(190, 5, 'Professional Student Report Card', 0, 1, 'C');

$pdf->SetTextColor(30, 41, 59);
$pdf->SetY(32);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Student Information', 0, 1);
$pdf->SetFont('Helvetica', '', 8.5);
$pdf->SetFillColor(248, 250, 252);
$pdf->Rect(10, 39, 190, 24, 'F');
$pdf->SetXY(13, 42);
$pdf->Cell(58, 5, 'Name: ' . pdf_short($student['name'] ?? 'Student', 36), 0, 0);
$pdf->Cell(72, 5, 'Email: ' . pdf_short($student['email'] ?? '-', 44), 0, 0);
$pdf->Cell(50, 5, 'Phone: ' . pdf_short($student['phone'] ?? '-', 20), 0, 1);
$pdf->SetX(13);
$pdf->Cell(58, 5, 'Course: ' . pdf_short($student['course'] ?? '-', 32), 0, 0);
$pdf->Cell(72, 5, 'Semester: ' . pdf_short($student['semester'] ?? '-', 18), 0, 0);
$pdf->Cell(50, 5, 'Generated: ' . date('d M Y'), 0, 1);

$pdf->SetY(69);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Exam Summary', 0, 1);
$summary = [
    ['Total Exams', $total],
    ['Average', $avgPct . '%'],
    ['Pass %', $passPct . '%'],
    ['Pass / Fail', $pass . ' / ' . $fail],
    ['Best Subject', pdf_short($bestSubject, 18)],
    ['Best Score', $bestPct . '%']
];
$pdf->SetFont('Helvetica', 'B', 8);
$x = 10; $y = 77;
foreach ($summary as $item) {
    $pdf->SetXY($x, $y);
    $pdf->SetFillColor(245, 247, 250);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->Cell(30, 8, $item[0], 1, 0, 'C', true);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Cell(31, 8, (string)$item[1], 1, 0, 'C', true);
    $pdf->SetFont('Helvetica', 'B', 8);
    $x += 63;
    if ($x > 170) { $x = 10; $y += 8; }
}

$pdf->SetY(99);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(95, 6, 'Subject Performance', 0, 0);
$pdf->Cell(95, 6, 'Difficulty Analysis', 0, 1);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetFillColor(37, 99, 235);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(65, 6, 'Subject', 1, 0, 'L', true);
$pdf->Cell(30, 6, 'Average', 1, 0, 'C', true);
$pdf->Cell(50, 6, 'Difficulty', 1, 0, 'L', true);
$pdf->Cell(45, 6, 'Correct / Total', 1, 1, 'C', true);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Helvetica', '', 7.5);

$subjectRows = [];
foreach ($subjectStats as $subject => $stat) {
    $subjectRows[] = [pdf_short($subject, 34), round($stat['sum'] / max($stat['count'], 1), 1) . '%'];
}
$difficultyRows = [];
foreach ($difficultyStats as $difficulty => $stat) {
    $difficultyRows[] = [pdf_short($difficulty, 22), (int)$stat['correct'] . ' / ' . (int)$stat['total']];
}
if (!$difficultyRows) { $difficultyRows[] = ['Not available', '-']; }
$maxRows = max(count($subjectRows), count($difficultyRows), 1);
$maxRows = min($maxRows, 5);
for ($i = 0; $i < $maxRows; $i++) {
    $s = $subjectRows[$i] ?? ['', ''];
    $d = $difficultyRows[$i] ?? ['', ''];
    $pdf->Cell(65, 6, $s[0], 1, 0, 'L');
    $pdf->Cell(30, 6, $s[1], 1, 0, 'C');
    $pdf->Cell(50, 6, $d[0], 1, 0, 'L');
    $pdf->Cell(45, 6, $d[1], 1, 1, 'C');
}

$pdf->SetY(141);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Recent Exam Records', 0, 1);
$pdf->SetFont('Helvetica', 'B', 7.2);
$pdf->SetFillColor(15, 23, 42);
$pdf->SetTextColor(255, 255, 255);
$headers = ['Exam', 'Subject', 'Marks', 'Score', 'Status', 'Date'];
$widths = [55, 38, 24, 20, 22, 31];
foreach ($headers as $i => $header) { $pdf->Cell($widths[$i], 6, $header, 1, 0, 'C', true); }
$pdf->Ln();
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Helvetica', '', 7);
$rows = array_slice($all, 0, 6);
foreach ($rows as $r) {
    $marksTotal = max((int)$r['exam_total'], (int)$r['total_questions'], 1);
    $pdf->Cell($widths[0], 6, pdf_short($r['exam_title'], 30), 1);
    $pdf->Cell($widths[1], 6, pdf_short($r['subject_name'], 20), 1);
    $pdf->Cell($widths[2], 6, $r['marks_obtained'] . '/' . $marksTotal, 1, 0, 'C');
    $pdf->Cell($widths[3], 6, round((float)$r['percentage'], 1) . '%', 1, 0, 'C');
    $pdf->Cell($widths[4], 6, pdf_short($r['status'], 10), 1, 0, 'C');
    $pdf->Cell($widths[5], 6, pdf_short($r['submitted_at'] ?? '', 16), 1, 1, 'C');
}

$pdf->SetY(187);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(0, 6, 'AI Analysis', 0, 1);
$pdf->SetFont('Helvetica', '', 7.5);
$pdf->SetFillColor(248, 250, 252);
$pdf->MultiCell(190, 4.5, pdf_short($latestInsight, 520), 1, 'L', true);

$pdf->SetY(276);
$pdf->SetFillColor(15, 23, 42);
$pdf->Rect(0, 276, 210, 21, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetXY(10, 281);
$pdf->Cell(95, 5, 'AI Exam System | Version 1.0', 0, 0, 'L');
$pdf->Cell(95, 5, 'Developed by Kishan Parekh | Copyright ' . date('Y'), 0, 1, 'R');
$pdf->SetX(10);
$pdf->Cell(190, 5, 'Generated by Admin Panel', 0, 0, 'C');

$fileName = 'Report_' . preg_replace('/[^A-Za-z0-9_-]/', '_', pdf_clean($student['name'] ?? 'Student')) . '.pdf';

while (ob_get_level() > 0) {
    ob_end_clean();
}
$pdf->Output('D', $fileName);
exit;
