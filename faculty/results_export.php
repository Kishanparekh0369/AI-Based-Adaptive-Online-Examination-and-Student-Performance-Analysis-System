<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

require_once("../app/config/database.php");
$conn = conn();
require_faculty();

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

$ids = isset($_GET['student_id']) ? preg_replace('/[^0-9,]/', '', $_GET['student_id']) : '0';
$id_parts = array_filter(array_map('intval', explode(',', $ids)));
if (empty($id_parts)) {
    $id_parts = [0];
}
$first_id = (int)$id_parts[0];
$ids_sql = implode(',', $id_parts);

$where = "r.student_id IN ($ids_sql) AND e.subject_id IN ($subject_ids_csv)";

$student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE id=$first_id"));
$results = mysqli_query($conn, "SELECT r.*, e.exam_title, e.exam_mode, e.total_marks AS exam_total, s.subject_name 
    FROM results r 
    INNER JOIN exams e ON r.exam_id=e.id 
    INNER JOIN subjects s ON e.subject_id=s.id 
    WHERE $where ORDER BY r.id ASC");

$all = [];
if ($results) {
    while ($r = mysqli_fetch_assoc($results)) { $all[] = $r; }
}

if (file_exists("../vendor/tcpdf/fpdf.php")) {
    require_once("../vendor/tcpdf/fpdf.php");
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(0, 10, 'Student Performance Report (Faculty Copy)', 0, 1, 'C');
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 5, 'AI-Based Online Examination System', 0, 1, 'C');
            $this->Ln(8);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 11);

    if ($student) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 7, 'Student: ' . $student['name'] . ' (' . $student['email'] . ')', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Course: ' . ($student['course'] ?? 'N/A') . ' | Semester: ' . ($student['semester'] ?? 'N/A'), 0, 1);
        $pdf->Ln(4);
    }

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(230, 235, 245);
    $pdf->Cell(65, 8, 'Exam Title', 1, 0, 'L', true);
    $pdf->Cell(45, 8, 'Subject', 1, 0, 'L', true);
    $pdf->Cell(25, 8, 'Score', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Percent', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Result', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    foreach ($all as $row) {
        $score_val = (int)($row['marks_obtained'] ?? $row['correct_answers'] ?? 0);
        $total_val = (int)($row['exam_total'] ?? $row['total_questions'] ?? 0);
        if ($total_val <= 0) { $total_val = max(1, $score_val); }
        $pct_val = isset($row['percentage']) && is_numeric($row['percentage']) ? (float)$row['percentage'] : round(($score_val / $total_val) * 100, 1);

        $pdf->Cell(65, 7, substr($row['exam_title'], 0, 32), 1);
        $pdf->Cell(45, 7, substr($row['subject_name'], 0, 22), 1);
        $pdf->Cell(25, 7, $score_val . '/' . $total_val, 1, 0, 'C');
        $pdf->Cell(25, 7, number_format($pct_val, 1) . '%', 1, 0, 'C');
        $pdf->Cell(30, 7, $row['status'], 1, 1, 'C');
    }

    ob_end_clean();
    $pdf->Output('I', 'Student_Report_' . $first_id . '.pdf');
    exit;
} else {
    // Fallback CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="results_student_' . $first_id . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Exam Title', 'Subject', 'Score', 'Percentage', 'Status', 'Date']);
    foreach ($all as $r) {
        $score_val = (int)($r['marks_obtained'] ?? $r['correct_answers'] ?? 0);
        $total_val = (int)($r['exam_total'] ?? $r['total_questions'] ?? 0);
        if ($total_val <= 0) { $total_val = max(1, $score_val); }
        $pct_val = isset($r['percentage']) && is_numeric($r['percentage']) ? (float)$r['percentage'] : round(($score_val / $total_val) * 100, 1);
        fputcsv($out, [$r['exam_title'], $r['subject_name'], $score_val . '/' . $total_val, number_format($pct_val, 1) . '%', $r['status'], $r['submitted_at']]);
    }
    fclose($out);
    exit;
}
?>
