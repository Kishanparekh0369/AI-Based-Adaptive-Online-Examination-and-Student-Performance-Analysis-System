<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

$subject_id = (int)($_GET['subject_id'] ?? 0);
$where = " e.subject_id IN ($subject_ids_csv) ";
if ($subject_id > 0 && faculty_has_subject($faculty_id, $subject_id)) {
    $where .= " AND e.subject_id=$subject_id ";
}

$query = mysqli_query($conn, "SELECT r.*, st.name AS student_name, st.email AS student_email, st.course, e.exam_title, e.total_marks AS exam_total, s.subject_name 
    FROM results r 
    INNER JOIN exams e ON r.exam_id=e.id 
    INNER JOIN subjects s ON e.subject_id=s.id 
    LEFT JOIN students st ON r.student_id=st.id 
    WHERE $where 
    ORDER BY r.id DESC");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="faculty_exam_reports_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Result ID', 'Student Name', 'Student Email', 'Course', 'Exam Title', 'Subject', 'Score', 'Total Marks', 'Percentage', 'Status', 'Submitted At']);

if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $score_val = (int)($row['marks_obtained'] ?? $row['correct_answers'] ?? 0);
        $total_val = (int)($row['exam_total'] ?? $row['total_questions'] ?? 0);
        if ($total_val <= 0) { $total_val = max(1, $score_val); }
        $pct_val = isset($row['percentage']) && is_numeric($row['percentage']) ? (float)$row['percentage'] : round(($score_val / $total_val) * 100, 1);

        fputcsv($output, [
            $row['id'],
            $row['student_name'] ?? 'N/A',
            $row['student_email'] ?? 'N/A',
            $row['course'] ?? 'N/A',
            $row['exam_title'],
            $row['subject_name'],
            $score_val,
            $total_val,
            number_format($pct_val, 1) . '%',
            $row['status'],
            $row['submitted_at']
        ]);
    }
}

fclose($output);
exit;
?>
