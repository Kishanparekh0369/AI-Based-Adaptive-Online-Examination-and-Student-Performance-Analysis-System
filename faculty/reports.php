<?php
require_once("../app/config/database.php");

$conn = conn();
require_faculty();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Reports & Analytics";

$faculty_id = (int)$_SESSION["faculty_id"];
$assigned_subject_ids = get_faculty_assigned_subject_ids($faculty_id);
$subject_ids_csv = !empty($assigned_subject_ids) ? implode(",", $assigned_subject_ids) : "0";

// Filters
$search = clean_data($_GET['search'] ?? '');
$exam_id = (int)($_GET['exam_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$filter_course = isset($_GET['course']) ? trim($_GET['course']) : '';
if ($filter_course !== '' && !academic_valid_course($filter_course)) { $filter_course = ''; }
$filter_semester = isset($_GET['semester']) ? trim($_GET['semester']) : '';
if ($filter_semester !== '' && !academic_valid_semester($filter_semester)) { $filter_semester = ''; }
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// ================= Report engine filters (validated; scope enforced) =================
$rp_types = array(
    "exam" => "Exam Report",
    "student_performance" => "Student Performance Report",
    "subject" => "Subject Report",
    "result" => "Result Report",
    "question" => "Question Analysis",
    "passfail" => "Pass/Fail Report",
);
$rp_type = trim((string)($_GET['report_type'] ?? ''));
if (!isset($rp_types[$rp_type])) { $rp_type = ""; }
$rp_exam = isset($_GET["rpt_exam"]) ? (int)$_GET["rpt_exam"] : 0;
$rp_subject = isset($_GET["rpt_subject"]) ? (int)$_GET["rpt_subject"] : 0;
$rp_student = isset($_GET["rpt_student"]) ? (int)$_GET["rpt_student"] : 0;
$rp_semester = trim((string)($_GET["rpt_semester"] ?? ""));
$rp_course = trim((string)($_GET["rpt_course"] ?? ""));
if ($rp_course !== "" && !academic_valid_course($rp_course)) { $rp_course = ""; }
$rp_status = trim((string)($_GET["rpt_status"] ?? ""));
if (!in_array($rp_status, array("", "Pass", "Fail"), true)) { $rp_status = ""; }
$rp_from = trim((string)($_GET["rpt_from"] ?? ""));
$rp_to = trim((string)($_GET["rpt_to"] ?? ""));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rp_from)) { $rp_from = ""; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rp_to)) { $rp_to = ""; }
$rp_difficulty = trim((string)($_GET["rpt_difficulty"] ?? ""));
if (!in_array($rp_difficulty, array("", "Easy", "Medium", "Hard"), true)) { $rp_difficulty = ""; }
if ($rp_exam > 0) {
    $rp_ev = mysqli_query($conn, "SELECT id FROM exams WHERE id=$rp_exam AND subject_id IN ($subject_ids_csv) LIMIT 1");
    if (!$rp_ev || mysqli_num_rows($rp_ev) == 0) { $rp_exam = 0; }
}
if ($rp_subject > 0 && !faculty_has_subject($faculty_id, $rp_subject)) { $rp_subject = 0; }
if ($rp_student > 0) {
    $rp_sv = mysqli_query($conn, "SELECT r.id FROM results r INNER JOIN exams e ON r.exam_id=e.id WHERE r.student_id=$rp_student AND e.subject_id IN ($subject_ids_csv) LIMIT 1");
    if (!$rp_sv || mysqli_num_rows($rp_sv) == 0) { $rp_student = 0; }
}

// Summary metrics (assigned subjects only)
$total_students = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT r.student_id) AS c FROM results r INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($subject_ids_csv)"))['c'];
$total_exams = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM exams WHERE subject_id IN ($subject_ids_csv)"))['c'];
$total_attempts = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(r.id) AS c FROM results r INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($subject_ids_csv)"))['c'];
$avg_score = round((float)mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(r.percentage) AS a FROM results r INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($subject_ids_csv)"))['a'] ?? 0, 1);
$pass_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(r.id) AS c FROM results r INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($subject_ids_csv) AND r.status='Pass'"))['c'];
$fail_count = max(0, $total_attempts - $pass_count);
$pass_rate = $total_attempts > 0 ? round(($pass_count / $total_attempts) * 100, 1) : 0;

// Build WHERE for main query
$where = " e.subject_id IN ($subject_ids_csv) ";
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (st.name LIKE '%$s%' OR st.email LIKE '%$s%' OR e.exam_title LIKE '%$s%' OR sub.subject_name LIKE '%$s%') ";
}
// Ownership check: exam must belong to this faculty's assigned subjects.
if ($exam_id) {
    $own_check = mysqli_query($conn, "SELECT id FROM exams WHERE id=$exam_id AND subject_id IN ($subject_ids_csv) LIMIT 1");
    if ($own_check && mysqli_num_rows($own_check) > 0) {
        $where .= " AND e.id=$exam_id ";
    } else {
        $exam_id = 0;
    }
}
if ($subject_id && faculty_has_subject($faculty_id, $subject_id)) { $where .= " AND e.subject_id=$subject_id "; }
if ($status === 'Pass') { $where .= " AND r.status='Pass' "; }
elseif ($status === 'Fail') { $where .= " AND r.status!='Pass' "; }
if ($date_from) { $df = mysqli_real_escape_string($conn, $date_from); $where .= " AND r.submitted_at >= '$df 00:00:00' "; }
if ($date_to) { $dt = mysqli_real_escape_string($conn, $date_to); $where .= " AND r.submitted_at <= '$dt 23:59:59' "; }
if ($filter_course !== '') { $where .= " AND st.course='" . mysqli_real_escape_string($conn, $filter_course) . "' "; }
if ($filter_semester !== '') { $where .= " AND st.semester='" . mysqli_real_escape_string($conn, $filter_semester) . "' "; }

// ---- Export handler (CSV / PDF / XLS) ----
$export_format = strtolower(trim($_GET['format'] ?? ''));
if ($export_format === "csv" || $export_format === "pdf" || $export_format === "xls") {
    // When a report type is active, export its preview dataset instead.
    if ($rp_type !== "") {
        $rp_exp = rp_build_report($conn, $rp_type, $subject_ids_csv, $rp_exam, $rp_subject, $rp_student, $rp_semester, $rp_status, $rp_from, $rp_to, $rp_course);
        rp_export_dataset($export_format, $rp_types[$rp_type], $rp_exp["columns"], $rp_exp["rows"]);
        exit;
    }
    $exp_rows = array();
    $exp_q = mysqli_query($conn, "SELECT r.*, st.name AS student_name, st.email AS student_email, st.course, e.exam_title, e.total_marks AS exam_total, s.subject_name
        FROM results r
        LEFT JOIN students st ON r.student_id=st.id
        LEFT JOIN exams e ON r.exam_id=e.id
        LEFT JOIN subjects s ON e.subject_id=s.id
        WHERE $where
        ORDER BY r.id DESC");
    if ($exp_q) {
        while ($exp_row = mysqli_fetch_assoc($exp_q)) { $exp_rows[] = $exp_row; }
    }
    $exp_stamp = date('Y-m-d');

    if ($export_format === "csv") {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="faculty_exam_reports_' . $exp_stamp . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Result ID', 'Student Name', 'Student Email', 'Course', 'Exam Title', 'Subject', 'Score', 'Total Marks', 'Percentage', 'Status', 'Submitted At']);
        foreach ($exp_rows as $erow) {
            $score_val = (int)($erow['marks_obtained'] ?? $erow['correct_answers'] ?? 0);
            $total_val = (int)($erow['exam_total'] ?? $erow['total_questions'] ?? 0);
            if ($total_val <= 0) { $total_val = max(1, $score_val); }
            $pct_val = isset($erow['percentage']) && is_numeric($erow['percentage']) ? (float)$erow['percentage'] : round(($score_val / $total_val) * 100, 1);
            fputcsv($out, [
                $erow['id'],
                $erow['student_name'] ?? 'N/A',
                $erow['student_email'] ?? 'N/A',
                $erow['course'] ?? 'N/A',
                $erow['exam_title'] ?? '',
                $erow['subject_name'] ?? '',
                $score_val,
                $total_val,
                number_format($pct_val, 1) . '%',
                $erow['status'] ?? '',
                $erow['submitted_at'] ?? ''
            ]);
        }
        fclose($out);
        exit;
    }

    // PDF via bundled FPDF
    require_once("../vendor/tcpdf/fpdf.php");
    if (!function_exists('fp_pdf_text')) {
        function fp_pdf_text($s) {
            $s = (string)$s;
            if (function_exists('iconv')) {
                $c = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
                if ($c !== false) { return $c; }
            }
            return utf8_decode($s);
        }
    }
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Faculty Exam Reports', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, 'AI-Based Adaptive Online Examination System  |  Generated: ' . date('d M Y, h:i A'), 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 9);
    $filter_bits = array();
    if ($search !== '') { $filter_bits[] = 'Search: ' . $search; }
    if ($exam_id > 0) {
        $fn_res = mysqli_query($conn, "SELECT exam_title FROM exams WHERE id=$exam_id LIMIT 1");
        $fn_row = $fn_res ? mysqli_fetch_assoc($fn_res) : null;
        $filter_bits[] = 'Exam: ' . ($fn_row['exam_title'] ?? ('#' . $exam_id));
    }
    if ($subject_id > 0) {
        $fs_res = mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id=$subject_id LIMIT 1");
        $fs_row = $fs_res ? mysqli_fetch_assoc($fs_res) : null;
        $filter_bits[] = 'Subject: ' . ($fs_row['subject_name'] ?? ('#' . $subject_id));
    }
    if ($status !== '') { $filter_bits[] = 'Status: ' . $status; }
    if ($filter_course !== '') { $filter_bits[] = 'Course: ' . $filter_course; }
    if ($filter_semester !== '') { $filter_bits[] = 'Semester: ' . $filter_semester; }
    if ($date_from !== '') { $filter_bits[] = 'From: ' . $date_from; }
    if ($date_to !== '') { $filter_bits[] = 'To: ' . $date_to; }
    $pdf->Cell(0, 6, $filter_bits ? ('Filters: ' . implode('  |  ', $filter_bits)) : 'Filters: none (all records in scope)', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Total attempts: ' . count($exp_rows), 0, 1, 'C');
    $pdf->Ln(4);

    // Table header
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(30, 41, 59);
    $pdf->SetTextColor(255, 255, 255);
    $exp_cols = array('Student', 'Exam', 'Subject', 'Score', '%', 'Status', 'Date');
    $exp_widths = rp_pdf_widths($exp_cols, $exp_rows, 277);
    $exp_total_w = array_sum($exp_widths);
    foreach ($exp_cols as $eci => $ec) {
        $pdf->Cell($exp_widths[$eci], 8, $ec, 1, ($eci === count($exp_cols) - 1) ? 1 : 0, 'C', true);
    }

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(15, 23, 42);
    if (empty($exp_rows)) {
        $pdf->Cell($exp_total_w, 10, 'No records match the selected filters.', 1, 1, 'C');
    } else {
        $fill = false;
        foreach ($exp_rows as $erow) {
            $score_val = (int)($erow['marks_obtained'] ?? $erow['correct_answers'] ?? 0);
            $total_val = (int)($erow['exam_total'] ?? $erow['total_questions'] ?? 0);
            if ($total_val <= 0) { $total_val = max(1, $score_val); }
            $pct_val = isset($erow['percentage']) && is_numeric($erow['percentage']) ? (float)$erow['percentage'] : round(($score_val / $total_val) * 100, 1);
            if ($fill) { $pdf->SetFillColor(241, 245, 249); }
            $exp_vals = array(
                rp_pdf_fit($erow['student_name'] ?? 'N/A', $exp_widths[0]),
                rp_pdf_fit($erow['exam_title'] ?? '', $exp_widths[1]),
                rp_pdf_fit($erow['subject_name'] ?? '', $exp_widths[2]),
                $score_val . '/' . $total_val,
                number_format($pct_val, 1) . '%',
                $erow['status'] ?? '',
                !empty($erow['submitted_at']) ? date('d M Y', strtotime($erow['submitted_at'])) : '',
            );
            foreach ($exp_vals as $evi => $ev) {
                $pdf->Cell($exp_widths[$evi], 7, fp_pdf_text($ev), 1, ($evi === count($exp_vals) - 1) ? 1 : 0, $evi < 3 ? 'L' : 'C', $fill);
            }
            $fill = !$fill;
        }
    }

    $pdf->SetY(-15);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Cell(0, 10, 'Generated by AI-Based Adaptive Online Examination System | Page ' . $pdf->PageNo() . '/{nb}', 0, 0, 'C');
    $pdf->Output('D', 'faculty_exam_reports_' . $exp_stamp . '.pdf');
    exit;
}

$countQ = mysqli_query($conn, "SELECT COUNT(DISTINCT r.student_id) AS c FROM results r LEFT JOIN students st ON r.student_id=st.id LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects sub ON e.subject_id=sub.id WHERE $where");
$total_students_filtered = (int)mysqli_fetch_assoc($countQ)['c'];
$total_pages = max(1, ceil($total_students_filtered / $per_page));
$offset = ($page - 1) * $per_page;

$studentsQ = mysqli_query($conn, "SELECT st.id, st.name, st.email, st.course, st.semester, st.profile_image,
    COUNT(r.id) AS total_attempts,
    AVG(r.percentage) AS avg_percentage,
    MAX(r.percentage) AS best_percentage,
    MIN(r.percentage) AS lowest_percentage,
    SUM(CASE WHEN r.status='Pass' THEN 1 ELSE 0 END) AS pass_count,
    SUM(CASE WHEN r.status!='Pass' THEN 1 ELSE 0 END) AS fail_count,
    MAX(r.submitted_at) AS last_attempt_date
    FROM results r
    LEFT JOIN students st ON r.student_id=st.id
    LEFT JOIN exams e ON r.exam_id=e.id
    LEFT JOIN subjects sub ON e.subject_id=sub.id
    WHERE $where
    GROUP BY st.id, st.name, st.email, st.course, st.semester, st.profile_image
    ORDER BY avg_percentage DESC
    LIMIT $per_page OFFSET $offset");

$assigned_subjects_list = get_faculty_assigned_subjects($faculty_id);
$assigned_exams_list = mysqli_query($conn, "SELECT id, exam_title FROM exams WHERE subject_id IN ($subject_ids_csv) ORDER BY exam_title ASC");

// ================= Report engine helpers + option lists =================
// Distinct students + semesters in scope (for config modal)
$rp_student_opts = array();
$rp_sq = mysqli_query($conn, "SELECT DISTINCT st.id, st.name FROM students st INNER JOIN results r ON r.student_id=st.id INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($subject_ids_csv) ORDER BY st.name ASC");
if ($rp_sq) { while ($rq = mysqli_fetch_assoc($rp_sq)) { $rp_student_opts[] = $rq; } }
$rp_sem_opts = array();
$rp_semq = mysqli_query($conn, "SELECT DISTINCT st.semester FROM students st INNER JOIN results r ON r.student_id=st.id INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($subject_ids_csv) AND st.semester<>'' ORDER BY st.semester ASC");
if ($rp_semq) { while ($rq = mysqli_fetch_assoc($rp_semq)) { $rp_sem_opts[] = $rq["semester"]; } }

// Shared scope fragment for preview builders
function rp_scope_where($conn, $subject_ids_csv, $exam, $subject, $student, $semester, $status, $from, $to, $course = "") {
    $w = " e.subject_id IN ($subject_ids_csv) ";
    if ($exam > 0) { $w .= " AND e.id=$exam "; }
    if ($subject > 0) { $w .= " AND e.subject_id=$subject "; }
    if ($student > 0) { $w .= " AND r.student_id=$student "; }
    if ($semester !== "") { $w .= " AND st.semester='" . mysqli_real_escape_string($conn, $semester) . "' "; }
    if ($course !== "") { $w .= " AND st.course='" . mysqli_real_escape_string($conn, $course) . "' "; }
    if ($status === "Pass") { $w .= " AND r.status='Pass' "; }
    elseif ($status === "Fail") { $w .= " AND r.status!='Pass' "; }
    if ($from !== "") { $w .= " AND r.submitted_at >= '" . mysqli_real_escape_string($conn, $from) . " 00:00:00' "; }
    if ($to !== "") { $w .= " AND r.submitted_at <= '" . mysqli_real_escape_string($conn, $to) . " 23:59:59' "; }
    return $w;
}

function rp_fetch_all($conn, $sql) {
    $rows = array();
    $q = mysqli_query($conn, $sql);
    if ($q) { while ($r = mysqli_fetch_assoc($q)) { $rows[] = $r; } }
    return $rows;
}

// Build one report dataset: summary cards + table + optional chart
function rp_build_report($conn, $type, $scope_csv, $exam, $subject, $student, $semester, $status, $from, $to, $course = "") {
    $w = rp_scope_where($conn, $scope_csv, $exam, $subject, $student, $semester, $status, $from, $to, $course);
    $rep = array("title" => "", "summary" => array(), "columns" => array(), "rows" => array(), "chart" => null, "note" => "");
    if ($type === "exam") {
        if ($exam <= 0) {
            $er = mysqli_query($conn, "SELECT e.id, e.exam_title FROM exams e LEFT JOIN (SELECT exam_id, MAX(submitted_at) AS mx FROM results GROUP BY exam_id) r ON r.exam_id=e.id WHERE e.subject_id IN ($scope_csv) ORDER BY r.mx DESC, e.id DESC LIMIT 1");
            if ($er && $erow = mysqli_fetch_assoc($er)) { $exam = (int)$erow["id"]; }
            $w = rp_scope_where($conn, $scope_csv, $exam, $subject, $student, $semester, $status, $from, $to, $course);
        }
        $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT e.*, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id=s.id WHERE e.id=$exam LIMIT 1"));
        if (!$info) { $rep["note"] = "Selected exam was not found in your scope."; return $rep; }
        $rows = rp_fetch_all($conn, "SELECT r.*, st.name AS student_name, e.total_marks AS exam_total FROM results r LEFT JOIN students st ON st.id=r.student_id LEFT JOIN exams e ON e.id=r.exam_id WHERE r.exam_id=$exam ORDER BY r.percentage DESC");
        $n = count($rows);
        $sum = 0; $pass = 0; $best = 0; $worst = 100;
        $buckets = array("0-39" => 0, "40-59" => 0, "60-79" => 0, "80-100" => 0);
        foreach ($rows as $r) {
            $p = (float)$r["percentage"]; $sum += $p;
            if ($r["status"] === "Pass") { $pass++; }
            if ($p > $best) { $best = $p; }
            if ($p < $worst) { $worst = $p; }
            if ($p < 40) { $buckets["0-39"]++; } elseif ($p < 60) { $buckets["40-59"]++; } elseif ($p < 80) { $buckets["60-79"]++; } else { $buckets["80-100"]++; }
        }
        $rep["title"] = "Exam Report — " . $info["exam_title"];
        $rep["summary"] = array(
            array("Subject", $info["subject_name"] ?? "—"), array("Students", (string)$n),
            array("Average", $n > 0 ? round($sum / $n, 1) . "%" : "—"),
            array("Highest", $n > 0 ? round($best, 1) . "%" : "—"),
            array("Lowest", $n > 0 ? round($worst, 1) . "%" : "—"),
            array("Pass Rate", $n > 0 ? round(($pass / $n) * 100, 1) . "%" : "—"),
        );
        $rep["columns"] = array("Student", "Score", "%", "Correct", "Wrong", "Status");
        foreach ($rows as $r) {
            $tm = max((int)($r["exam_total"] ?? 0), 1);
            $rep["rows"][] = array($r["student_name"] ?? ("#" . $r["student_id"]), (int)($r["marks_obtained"] ?? 0) . "/" . $tm, number_format((float)$r["percentage"], 1) . "%", (int)$r["correct_answers"], (int)$r["wrong_answers"], $r["status"]);
        }
        $rep["chart"] = array("type" => "bar", "title" => "Score Distribution", "labels" => array_keys($buckets), "values" => array_values($buckets));
        return $rep;
    }
    if ($type === "student_performance") {
        if ($student <= 0) {
            $sr = mysqli_query($conn, "SELECT r.student_id, COUNT(*) AS c FROM results r INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id IN ($scope_csv) GROUP BY r.student_id ORDER BY c DESC LIMIT 1");
            if ($sr && $srow = mysqli_fetch_assoc($sr)) { $student = (int)$srow["student_id"]; }
        }
        $pinfo = $student > 0 ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, email, course, semester FROM students WHERE id=$student LIMIT 1")) : null;
        if (!$pinfo) { $rep["note"] = "No student data available for this selection."; return $rep; }
        $rows = rp_fetch_all($conn, "SELECT r.percentage, r.correct_answers, r.wrong_answers, r.total_questions, r.status, r.submitted_at, r.time_spent_seconds, e.exam_title, s.subject_name FROM results r LEFT JOIN exams e ON r.exam_id=e.id LEFT JOIN subjects s ON e.subject_id=s.id WHERE r.student_id=$student AND e.subject_id IN ($scope_csv) ORDER BY r.submitted_at ASC");
        if (empty($rows)) { $rep["note"] = "This student has no attempts in the selected scope."; return $rep; }
        $n = count($rows); $sum = 0; $corr = 0; $qt = 0; $pass = 0;
        $labels = array(); $vals = array();
        foreach ($rows as $r) {
            $p = (float)$r["percentage"]; $sum += $p;
            $corr += (int)$r["correct_answers"]; $qt += max(0, (int)$r["total_questions"]);
            if ($r["status"] === "Pass") { $pass++; }
            $labels[] = mb_substr($r["exam_title"] ?? "Exam", 0, 18);
            $vals[] = round($p, 1);
        }
        $rep["title"] = "Student Performance — " . $pinfo["name"];
        $rep["summary"] = array(
            array("Exams", (string)$n),
            array("Average", round($sum / $n, 1) . "%"),
            array("Accuracy", $qt > 0 ? round(($corr / $qt) * 100, 1) . "%" : "—"),
            array("Pass Rate", round(($pass / $n) * 100, 1) . "%"),
            array("Course", ($pinfo["course"] !== "" ? $pinfo["course"] : "—") . " / " . ($pinfo["semester"] !== "" ? $pinfo["semester"] : "—")),
        );
        $rep["columns"] = array("Exam", "Subject", "Score %", "Correct", "Wrong", "Status", "Date");
        foreach ($rows as $r) {
            $rep["rows"][] = array($r["exam_title"], $r["subject_name"], number_format((float)$r["percentage"], 1) . "%", (int)$r["correct_answers"], (int)$r["wrong_answers"], $r["status"], !empty($r["submitted_at"]) ? date("d M Y", strtotime($r["submitted_at"])) : "");
        }
        $rep["chart"] = array("type" => "line", "title" => "Performance Trend", "labels" => $labels, "values" => $vals);
        return $rep;
    }
    if ($type === "subject") {
        if ($subject <= 0) {
            $srow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM subjects WHERE id IN ($scope_csv) ORDER BY id ASC LIMIT 1"));
            $subject = $srow ? (int)$srow["id"] : 0;
        }
        $sinfo = $subject > 0 ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT subject_name, subject_code FROM subjects WHERE id=$subject LIMIT 1")) : null;
        if (!$sinfo) { $rep["note"] = "No subject available in your scope."; return $rep; }
        // Filtered attempts honoring every report control (exam/student/semester/status/dates/course).
        $wsub = rp_scope_where($conn, $scope_csv, $exam, $subject, $student, $semester, $status, $from, $to, $course);
        $fr = rp_fetch_all($conn, "SELECT r.student_id, r.percentage, r.correct_answers, r.total_questions, r.status, r.exam_id FROM results r INNER JOIN exams e ON r.exam_id=e.id LEFT JOIN students st ON st.id=r.student_id WHERE $wsub");
        $seen_students = array();
        $sum = 0; $corr = 0; $qt = 0; $passc = 0;
        $by_exam = array();
        foreach ($fr as $r) {
            $seen_students[(int)$r["student_id"]] = true;
            $p = (float)$r["percentage"]; $sum += $p;
            $corr += (int)$r["correct_answers"]; $qt += max(0, (int)$r["total_questions"]);
            if ($r["status"] === "Pass") { $passc++; }
            $eid2 = (int)$r["exam_id"];
            if (!isset($by_exam[$eid2])) { $by_exam[$eid2] = array("att" => 0, "sum" => 0, "pass" => 0); }
            $by_exam[$eid2]["att"]++;
            $by_exam[$eid2]["sum"] += $p;
            if ($r["status"] === "Pass") { $by_exam[$eid2]["pass"]++; }
        }
        $students = count($seen_students);
        $attempts = count($fr);
        $qcount = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM questions WHERE subject_id=$subject"))["c"];
        $ex_list = rp_fetch_all($conn, "SELECT id, exam_title FROM exams WHERE subject_id=$subject" . ($exam > 0 ? " AND id=$exam" : "") . " ORDER BY id DESC");
        $ecount = count($ex_list);
        if ($attempts === 0) {
            $any_q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM results r INNER JOIN exams e ON r.exam_id=e.id WHERE e.subject_id=$subject");
            $any_row = $any_q ? mysqli_fetch_assoc($any_q) : null;
            $any_n = $any_row ? (int)$any_row["c"] : 0;
            if ($any_n > 0) {
                $rep["note"] = 'No attempts match the selected filters for "' . $sinfo["subject_name"] . '". Try widening the date range or clearing the status filter.';
            } else {
                $ex_names = array();
                foreach ($ex_list as $ex0) { $ex_names[] = '"' . $ex0["exam_title"] . '"'; }
                $rep["note"] = 'No student attempts have been recorded for "' . $sinfo["subject_name"] . '" yet'
                    . (!empty($ex_names) ? ' — exam ' . implode(", ", $ex_names) . ' (' . $qcount . ' questions) is ready' : '')
                    . '. Averages, accuracy and pass rate will appear here once students submit.';
            }
            return $rep;
        }
        $avg = round($sum / $attempts, 1);
        $acc = $qt > 0 ? round(($corr / $qt) * 100, 1) : 0;
        $passr = round(($passc / $attempts) * 100, 1);
        $diff_rows = rp_fetch_all($conn, "SELECT q.difficulty, COUNT(*) AS total, SUM(sa.is_correct) AS correct FROM student_answers sa INNER JOIN questions q ON q.id=sa.question_id INNER JOIN exams e ON e.id=sa.exam_id WHERE q.subject_id=$subject GROUP BY q.difficulty");
        $diff_labels = array(); $diff_vals = array(); $weak = array(); $strong = array();
        foreach ($diff_rows as $d) {
            $t = max((int)$d["total"], 0); $c = (int)($d["correct"] ?? 0);
            $a = $t > 0 ? round(($c / $t) * 100, 1) : 0;
            $diff_labels[] = $d["difficulty"]; $diff_vals[] = $a;
            if ($a < 50) { $weak[] = $d["difficulty"] . " (" . $a . "%)"; }
            elseif ($a >= 70) { $strong[] = $d["difficulty"] . " (" . $a . "%)"; }
        }
        $rep["title"] = "Subject Report — " . $sinfo["subject_name"];
        $rep["summary"] = array(
            array("Students", (string)$students), array("Exams", (string)$ecount),
            array("Questions", (string)$qcount), array("Average", $avg . "%"),
            array("Accuracy", $acc . "%"), array("Pass Rate", $passr . "%"),
        );
        $rep["columns"] = array("Exam", "Attempts", "Average %", "Pass %");
        foreach ($ex_list as $ex0) {
            $eid0 = (int)$ex0["id"];
            $a2 = isset($by_exam[$eid0]) ? (int)$by_exam[$eid0]["att"] : 0;
            $rep["rows"][] = array($ex0["exam_title"], (string)$a2, $a2 > 0 ? round($by_exam[$eid0]["sum"] / $a2, 1) . "%" : "—", $a2 > 0 ? round(($by_exam[$eid0]["pass"] / $a2) * 100, 1) . "%" : "—");
        }
        if (!empty($diff_labels)) {
            $rep["chart"] = array("type" => "bar", "title" => "Accuracy by Difficulty", "labels" => $diff_labels, "values" => $diff_vals);
        }
        $rep["extra"] = array("strong" => $strong, "weak" => $weak);
        return $rep;
    }
    if ($type === "result") {
        $rows = rp_fetch_all($conn, "SELECT r.*, st.name AS student_name, e.exam_title, e.total_marks AS exam_total, s.subject_name FROM results r LEFT JOIN students st ON st.id=r.student_id LEFT JOIN exams e ON e.id=r.exam_id LEFT JOIN subjects s ON e.subject_id=s.id WHERE $w ORDER BY r.id DESC LIMIT 500");
        $n = count($rows);
        $pass = 0; $sum = 0;
        foreach ($rows as $r) { $sum += (float)$r["percentage"]; if ($r["status"] === "Pass") { $pass++; } }
        $rep["title"] = "Result Report";
        $rep["summary"] = array(
            array("Records", (string)$n),
            array("Average", $n > 0 ? round($sum / $n, 1) . "%" : "—"),
            array("Passed", (string)$pass),
            array("Failed", (string)($n - $pass)),
        );
        $rep["columns"] = array("Student", "Exam", "Score", "%", "Correct", "Wrong", "Skipped", "Time", "Status");
        foreach ($rows as $r) {
            $tq = max((int)$r["total_questions"], 0);
            $sk = max(0, $tq - (int)$r["correct_answers"] - (int)$r["wrong_answers"]);
            $tm = max((int)($r["exam_total"] ?? 0), $tq, 1);
            $ts = max((int)($r["time_spent_seconds"] ?? 0), 0);
            $rep["rows"][] = array(
                $r["student_name"] ?? ("#" . $r["student_id"]),
                $r["exam_title"] ?? "", (int)($r["marks_obtained"] ?? 0) . "/" . $tm,
                number_format((float)$r["percentage"], 1) . "%",
                (int)$r["correct_answers"], (int)$r["wrong_answers"], $sk,
                gmdate("H:i:s", $ts), $r["status"]
            );
        }
        $rep["chart"] = array("type" => "doughnut", "title" => "Pass vs Fail", "labels" => array("Pass", "Fail"), "values" => array($pass, $n - $pass));
        return $rep;
    }
    if ($type === "question") {
        if ($subject <= 0) {
            $srow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM subjects WHERE id IN ($scope_csv) ORDER BY id ASC LIMIT 1"));
            $subject = $srow ? (int)$srow["id"] : 0;
        }
        $sinfo = $subject > 0 ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT subject_name FROM subjects WHERE id=$subject LIMIT 1")) : null;
        if (!$sinfo) { $rep["note"] = "No subject available in your scope."; return $rep; }
        $difficulty = trim((string)($_GET["rpt_difficulty"] ?? ""));
        if (!in_array($difficulty, array("", "Easy", "Medium", "Hard"), true)) { $difficulty = ""; }
        $dq = "SELECT q.id, q.question, q.difficulty, q.marks,
            (SELECT COUNT(*) FROM student_answers sa WHERE sa.question_id=q.id) + (SELECT COUNT(*) FROM adaptive_answers aa WHERE aa.question_id=q.id) AS attempts,
            (SELECT SUM(sa.is_correct) FROM student_answers sa WHERE sa.question_id=q.id) AS c1,
            (SELECT SUM(aa.is_correct) FROM adaptive_answers aa WHERE aa.question_id=q.id) AS c2
            FROM questions q WHERE q.subject_id=$subject";
        if ($difficulty !== "") { $dq .= " AND q.difficulty='" . mysqli_real_escape_string($conn, $difficulty) . "'"; }
        $dq .= " ORDER BY q.id ASC LIMIT 300";
        $qrows = rp_fetch_all($conn, $dq);
        $hard = 0;
        foreach ($qrows as $qr) {
            $t = (int)$qr["attempts"];
            $acc = $t > 0 ? round((((int)($qr["c1"] ?? 0) + (int)($qr["c2"] ?? 0)) / $t) * 100, 1) : null;
            if ($acc !== null && $acc < 40) { $hard++; }
        }
        $rep["title"] = "Question Analysis — " . $sinfo["subject_name"];
        $rep["summary"] = array(
            array("Questions", (string)count($qrows)),
            array("Difficult (<40%)", (string)$hard),
            array("Difficulty", $difficulty !== "" ? $difficulty : "All levels"),
        );
        $rep["columns"] = array("ID", "Question", "Difficulty", "Attempts", "Correct %", "Marks");
        foreach ($qrows as $qr) {
            $t = (int)$qr["attempts"];
            $acc = $t > 0 ? round((((int)($qr["c1"] ?? 0) + (int)($qr["c2"] ?? 0)) / $t) * 100, 1) . "%" : "—";
            $rep["rows"][] = array("#" . $qr["id"], mb_substr($qr["question"], 0, 80), $qr["difficulty"], (string)$t, $acc, (int)$qr["marks"]);
        }
        return $rep;
    }
    // passfail (default fallback)
    $prow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(r.status='Pass') AS passc FROM results r INNER JOIN exams e ON r.exam_id=e.id WHERE $w"));
    if (!$prow) { $prow = array("total" => 0, "passc" => 0); }
    $total = (int)($prow["total"] ?? 0);
    $passed = (int)($prow["passc"] ?? 0);
    $failed = max(0, $total - $passed);
    $subrows = rp_fetch_all($conn, "SELECT s.subject_name, COUNT(r.id) AS total, SUM(r.status='Pass') AS passc FROM results r INNER JOIN exams e ON r.exam_id=e.id INNER JOIN subjects s ON s.id=e.subject_id WHERE $w GROUP BY s.id ORDER BY s.subject_name ASC");
    $rep["title"] = "Pass/Fail Report";
    $rep["summary"] = array(
        array("Students/Attempts", (string)$total),
        array("Passed", (string)$passed),
        array("Failed", (string)$failed),
        array("Pass %", $total > 0 ? round(($passed / $total) * 100, 1) . "%" : "—"),
        array("Fail %", $total > 0 ? round(($failed / $total) * 100, 1) . "%" : "—"),
    );
    $rep["columns"] = array("Subject", "Attempts", "Passed", "Failed", "Pass %");
    foreach ($subrows as $sr) {
        $t2 = (int)$sr["total"]; $p2 = (int)$sr["passc"];
        $rep["rows"][] = array($sr["subject_name"], (string)$t2, (string)$p2, (string)($t2 - $p2), $t2 > 0 ? round(($p2 / $t2) * 100, 1) . "%" : "—");
    }
    $rep["chart"] = array("type" => "doughnut", "title" => "Passed vs Failed", "labels" => array("Passed", "Failed"), "values" => array($passed, $failed));
    return $rep;
}

// Proportional PDF column widths: text columns get more room, numeric stay compact.
// Total always equals the usable page width so nothing is cut or overflows.
function rp_pdf_widths($columns, $rows, $usable = 277) {
    $n = max(1, count($columns));
    $weights = array();
    for ($i = 0; $i < $n; $i++) {
        $mx = mb_strlen((string)($columns[$i] ?? ""));
        $numeric = true;
        $seen = 0;
        foreach ($rows as $r) {
            $vals = array_values($r);
            $v = isset($vals[$i]) ? (string)$vals[$i] : "";
            $v = trim(strip_tags(html_entity_decode($v, ENT_QUOTES, "UTF-8")));
            if ($v !== "") {
                $seen++;
                $mx = max($mx, mb_strlen($v));
                if (!preg_match('/^[\d\s\.\:\/%\-–—]+$/u', $v)) { $numeric = false; }
            }
        }
        $wt = min(max($mx, 6), 44);
        if ($numeric && $seen > 0) { $wt = min($wt, 14); }
        $weights[$i] = $wt;
    }
    $sum = max(1, array_sum($weights));
    $out = array();
    foreach ($weights as $wt) { $out[] = max(16, floor($usable * $wt / $sum)); }
    $out[$n - 1] += $usable - array_sum($out);
    if ($out[$n - 1] < 16) { $out[$n - 1] = 16; }
    return $out;
}

// Max characters that safely fit inside a cell of $w mm at ~8-9pt Helvetica.
function rp_pdf_fit($v, $w) {
    return mb_substr((string)$v, 0, max(8, (int)floor($w / 1.7)));
}

// Generic dataset exporter used by report previews (CSV / PDF / XLS)
function rp_export_dataset($format, $title, $columns, $rows) {
    $stamp = date('Y-m-d');
    $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($title));
    $slug = trim($slug, '_');
    if ($slug === "") { $slug = "report"; }
    if ($format === "csv") {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $slug . '_' . $stamp . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $columns);
        foreach ($rows as $r) { fputcsv($out, array_map(function ($v) { return html_entity_decode(strip_tags((string)$v), ENT_QUOTES, 'UTF-8'); }, $r)); }
        fclose($out);
        return;
    }
    if ($format === "xls") {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $slug . '_' . $stamp . '.xls"');
        echo "\xEF\xBB\xBF";
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1"><thead><tr>';
        foreach ($columns as $c) { echo '<th>' . htmlspecialchars($c) . '</th>'; }
        echo '</tr></thead><tbody>';
        if (empty($rows)) {
            echo '<tr><td colspan="' . max(1, count($columns)) . '">No records.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo '<tr>';
                foreach ($r as $v) { echo '<td>' . htmlspecialchars(strip_tags((string)$v)) . '</td>'; }
                echo '</tr>';
            }
        }
        echo '</tbody></table></body></html>';
        return;
    }
    // PDF via bundled FPDF
    require_once("../vendor/tcpdf/fpdf.php");
    if (!function_exists('fp_pdf_text')) {
        function fp_pdf_text($s) {
            $s = (string)$s;
            if (function_exists('iconv')) {
                $c = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
                if ($c !== false) { return $c; }
            }
            return utf8_decode($s);
        }
    }
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, fp_pdf_text($title), 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, 'AI-Based Adaptive Online Examination System  |  Generated: ' . date('d M Y, h:i A'), 0, 1, 'C');
    $pdf->Cell(0, 6, 'Records: ' . count($rows), 0, 1, 'C');
    $pdf->Ln(4);
    $ncol = max(1, count($columns));
    $widths = rp_pdf_widths($columns, $rows, 277);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(30, 41, 59);
    $pdf->SetTextColor(255, 255, 255);
    foreach ($columns as $ci => $c) {
        $pdf->Cell($widths[$ci], 8, fp_pdf_text(rp_pdf_fit($c, $widths[$ci])), 1, ($ci === $ncol - 1) ? 1 : 0, 'C', true);
    }
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(15, 23, 42);
    if (empty($rows)) {
        $pdf->Cell(array_sum($widths), 10, 'No records match the selected filters.', 1, 1, 'C');
    } else {
        $fill = false;
        foreach ($rows as $r) {
            if ($fill) { $pdf->SetFillColor(241, 245, 249); }
            $vals = array_values($r);
            for ($ci = 0; $ci < $ncol; $ci++) {
                $v = isset($vals[$ci]) ? strip_tags(html_entity_decode((string)$vals[$ci], ENT_QUOTES, 'UTF-8')) : '';
                $pdf->Cell($widths[$ci], 7, fp_pdf_text(rp_pdf_fit(trim($v), $widths[$ci])), 1, ($ci === $ncol - 1) ? 1 : 0, 'L', $fill);
            }
            $fill = !$fill;
        }
    }
    $pdf->SetY(-15);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Cell(0, 10, 'Generated by AI-Based Adaptive Online Examination System | Page ' . $pdf->PageNo() . '/{nb}', 0, 0, 'C');
    $pdf->Output('D', $slug . '_' . $stamp . '.pdf');
}

// URL builder for pagination
function build_report_url($page, $params = []) {
    $defaults = ['search' => '', 'subject_id' => 0, 'exam_id' => 0, 'status' => '', 'date_from' => '', 'date_to' => ''];
    $params = array_merge($defaults, $params, ['page' => $page]);
    return "reports.php?" . http_build_query(array_filter($params));
}

// Build preview dataset when a report type is requested
$preview = null;
if ($rp_type !== "") {
    $preview = rp_build_report($conn, $rp_type, $subject_ids_csv, $rp_exam, $rp_subject, $rp_student, $rp_semester, $rp_status, $rp_from, $rp_to, $rp_course);
}

include("../app/layouts/faculty_header.php");
?>

<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/faculty_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb dashboard-breadcrumb">
                    <li class="breadcrumb-item">Faculty</li>
                    <li class="breadcrumb-item active">Reports & Analytics</li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 sp-fade-up">
                <div>
                    <h2 class="fw-bold mb-1">Reports & Analytics</h2>
                    <p class="text-muted small mb-0">Aggregate student exam records across your assigned subjects</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?php echo build_report_url(1, array_merge($_GET, ['format' => 'csv'])); ?>" class="btn btn-outline-success sp-fade-up" data-delay="1">
                        <i class="bi bi-filetype-csv me-1"></i>Export CSV
                    </a>
                    <a href="<?php echo build_report_url(1, array_merge($_GET, ['format' => 'pdf'])); ?>" class="btn btn-outline-primary sp-fade-up" data-delay="2">
                        <i class="bi bi-filetype-pdf me-1"></i>Export PDF
                    </a>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="row g-3 mb-4">
                <?php
                $stat_cards = [
                    ['Assessed Students', $total_students, 'bi-people', 'dash-tone-blue', 'Unique students with attempts'],
                    ['Total Submissions', $total_attempts, 'bi-calendar-check', 'dash-tone-cyan', 'All exam attempts'],
                    ['Average Score', $avg_score . '%', 'bi-graph-up', 'dash-tone-violet', 'Mean across all attempts'],
                    ['Pass Rate', $pass_rate . '%', 'bi-award', 'dash-tone-rose', "$pass_count passed / $fail_count failed"]
                ];
                $si = 0;
                foreach ($stat_cards as $sc) { $si++; ?>
                    <div class="col-6 col-md-3 sp-fade-up" data-delay="<?php echo min($si, 4); ?>">
                        <div class="dash-stat <?php echo $sc[3]; ?>">
                            <div class="dash-stat-top">
                                <span class="dash-stat-icon"><i class="bi <?php echo $sc[2]; ?>"></i></span>
                            </div>
                            <div class="dash-stat-value"><?php echo h($sc[1]); ?></div>
                            <p class="dash-stat-label"><?php echo h($sc[0]); ?></p>
                            <p class="dash-stat-sub"><?php echo h($sc[4]); ?></p>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Report Types -->
            <div class="sp-panel-head sp-fade-up mt-1 mb-3">
                <h3><i class="bi bi-layout-text-window-reverse me-2 text-primary"></i>Report Types</h3>
                <small class="text-muted">Choose a report to configure &amp; preview</small>
            </div>
            <div class="row g-3 mb-4">
                <?php
                $rp_cards = array(
                    array("exam", "Exam Report", "Attempts, scores, completion and performance per exam.", "bi-journal-check", "dash-tone-blue"),
                    array("student_performance", "Student Performance", "Individual and overall performance trends.", "bi-graph-up-arrow", "dash-tone-violet"),
                    array("subject", "Subject Report", "Subject-wise academic performance overview.", "bi-book", "dash-tone-green"),
                    array("result", "Result Report", "Detailed examination results and distributions.", "bi-trophy", "dash-tone-amber"),
                    array("question", "Question Analysis", "Difficulty-wise question performance.", "bi-patch-question", "dash-tone-cyan"),
                    array("passfail", "Pass/Fail Report", "Pass, fail and performance distribution.", "bi-pie-chart", "dash-tone-rose"),
                );
                $rp_ci = 0;
                foreach ($rp_cards as $rpc) { $rp_ci++; ?>
                    <div class="col-md-6 col-xl-4 sp-fade-up" data-delay="<?php echo min(($rp_ci % 3) + 1, 6); ?>">
                        <div class="sp-exam-card h-100 <?php echo $rp_type === $rpc[0] ? "is-completed" : ""; ?>">
                            <div class="sp-exam-head">
                                <span class="dash-stat-icon <?php echo $rpc[4]; ?>" style="width:44px;height:44px;border-radius:13px;"><i class="bi <?php echo $rpc[3]; ?>"></i></span>
                                <?php if ($rp_type === $rpc[0]) { ?><span class="sp-pill is-completed"><i class="bi bi-check2-circle"></i>Active</span><?php } ?>
                            </div>
                            <h3 class="sp-exam-title"><?php echo $rpc[1]; ?></h3>
                            <p class="text-muted small mb-3"><?php echo $rpc[2]; ?></p>
                            <div class="sp-exam-foot">
                                <button type="button" class="sp-btn sp-btn-soft" data-rp-config="<?php echo $rpc[0]; ?>" data-bs-toggle="modal" data-bs-target="#rpConfigModal"><i class="bi bi-gear"></i> Configure</button>
                                <a class="sp-btn sp-btn-ghost" href="reports.php?report_type=<?php echo $rpc[0]; ?>" title="Quick preview with defaults"><i class="bi bi-eye"></i> Preview</a>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <?php if ($preview !== null) { ?>
            <!-- Report Preview -->
            <div class="sp-panel mb-4 sp-fade-up" id="rpPreview">
                <div class="sp-panel-head flex-wrap gap-2">
                    <div>
                        <span class="sp-eyebrow"><i class="bi bi-file-earmark-bar-graph"></i> Report Preview</span>
                        <h3 class="mt-2"><?php echo h($preview["title"]); ?></h3>
                        <p class="text-muted small mb-0">Generated <?php echo date("d M Y, h:i A"); ?> &middot; Faculty: <?php echo h($_SESSION["faculty_name"] ?? "Faculty"); ?></p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php
                        $rp_exp_base = array("report_type" => $rp_type);
                        if ($rp_exam > 0) { $rp_exp_base["rpt_exam"] = $rp_exam; }
                        if ($rp_subject > 0) { $rp_exp_base["rpt_subject"] = $rp_subject; }
                        if ($rp_student > 0) { $rp_exp_base["rpt_student"] = $rp_student; }
                        if ($rp_semester !== "") { $rp_exp_base["rpt_semester"] = $rp_semester; }
                        if ($rp_status !== "") { $rp_exp_base["rpt_status"] = $rp_status; }
                        if ($rp_from !== "") { $rp_exp_base["rpt_from"] = $rp_from; }
                        if ($rp_to !== "") { $rp_exp_base["rpt_to"] = $rp_to; }
                        if ($rp_difficulty !== "") { $rp_exp_base["rpt_difficulty"] = $rp_difficulty; }
                        ?>
                        <a class="sp-btn sp-btn-ghost" href="reports.php?<?php echo http_build_query(array_merge($rp_exp_base, array("format" => "pdf"))); ?>"><i class="bi bi-filetype-pdf"></i> PDF</a>
                        <a class="sp-btn sp-btn-ghost" href="reports.php?<?php echo http_build_query(array_merge($rp_exp_base, array("format" => "xls"))); ?>"><i class="bi bi-filetype-xls"></i> Excel</a>
                        <a class="sp-btn sp-btn-ghost" href="reports.php?<?php echo http_build_query(array_merge($rp_exp_base, array("format" => "csv"))); ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
                        <a class="sp-btn sp-btn-soft" href="reports.php"><i class="bi bi-x-circle"></i> Close Preview</a>
                    </div>
                </div>
                <?php if (!empty($preview["note"])) { ?>
                    <div class="alert alert-warning"><?php echo h($preview["note"]); ?></div>
                <?php } else { ?>
                    <div class="row g-3 mb-3">
                        <?php foreach ($preview["summary"] as $ps) { ?>
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="sp-info-item text-center h-100">
                                    <div class="sp-info-label justify-content-center"><?php echo h($ps[0]); ?></div>
                                    <span class="sp-info-value"><?php echo h($ps[1]); ?></span>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if (!empty($preview["extra"]["strong"]) || !empty($preview["extra"]["weak"])) { ?>
                        <div class="sp-chip-list mb-3">
                            <?php foreach ((array)($preview["extra"]["strong"] ?? array()) as $st2) { ?><span class="sp-chip is-good"><i class="bi bi-check2-circle"></i><?php echo h($st2); ?></span><?php } ?>
                            <?php foreach ((array)($preview["extra"]["weak"] ?? array()) as $wk) { ?><span class="sp-chip is-warn"><i class="bi bi-exclamation-circle"></i><?php echo h($wk); ?></span><?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (!empty($preview["chart"])) { ?>
                        <div class="mb-3" style="max-width:560px;">
                            <h6 class="fw-bold mb-2"><i class="bi bi-bar-chart me-1 text-primary"></i><?php echo h($preview["chart"]["title"]); ?></h6>
                            <div class="chart-container chart-container-md">
                                <canvas id="rpPreviewChart" role="img" aria-label="<?php echo h($preview["chart"]["title"]); ?>"></canvas>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="table-responsive">
                    <table class="table table-hover align-middle sp-table mb-0">
                        <thead><tr><?php foreach ($preview["columns"] as $pc) { ?><th><?php echo h($pc); ?></th><?php } ?></tr></thead>
                        <tbody>
                            <?php if (!empty($preview["rows"])) { ?>
                                <?php foreach (array_slice($preview["rows"], 0, 100) as $pr) { ?>
                                    <tr><?php $pci = 0; foreach (array_values($pr) as $pv) { ?><td data-label="<?php echo h($preview["columns"][$pci] ?? ""); ?>"><?php echo h((string)$pv); ?></td><?php $pci++; } ?></tr>
                                <?php } ?>
                                <?php if (count($preview["rows"]) > 100) { ?>
                                    <tr><td colspan="<?php echo max(1, count($preview["columns"])); ?>" class="text-center text-muted small">Showing first 100 of <?php echo count($preview["rows"]); ?> rows — export for the complete dataset.</td></tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr><td colspan="<?php echo max(1, count($preview["columns"])); ?>">
                                    <div class="sp-empty">
                                        <div class="sp-empty-icon"><i class="bi bi-file-earmark-bar-graph"></i></div>
                                        <h4>No Report Data Available</h4>
                                        <p>Once students complete examinations, relevant analytics will appear here.</p>
                                    </div>
                                </td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    </div>
                <?php } ?>
            </div>
            <?php if (!empty($preview["chart"]) && empty($preview["note"])) { ?>
            <script>
            document.addEventListener("DOMContentLoaded", function () {
                if (typeof ChartManager === "undefined") { return; }
                ChartManager.create("rpPreviewChart", {
                    type: <?php echo json_encode($preview["chart"]["type"]); ?>,
                    data: {
                        labels: <?php echo json_encode($preview["chart"]["labels"]); ?>,
                        datasets: [{ data: <?php echo json_encode($preview["chart"]["values"]); ?>, backgroundColor: ["#4F46E5", "#2563EB", "#0EA5E9", "#16A34A", "#F59E0B", "#EF4444"], borderRadius: 5 }]
                    },
                    options: { plugins: { legend: { display: <?php echo $preview["chart"]["type"] === "doughnut" ? "true" : "false"; ?> } }, scales: <?php echo $preview["chart"]["type"] === "doughnut" ? "{}" : "{y:{beginAtZero:true}}"; ?> }
                });
            });
            </script>
            <?php } ?>
            <?php } ?>
            <?php if ($preview !== null) { ?>
            <script>
            document.addEventListener("DOMContentLoaded", function () {
                var pv = document.getElementById("rpPreview");
                if (pv) { setTimeout(function () { pv.scrollIntoView({ behavior: "smooth", block: "start" }); }, 150); }
            });
            </script>
            <?php } ?>

            <!-- Filter Panel -->
            <div class="sp-filters sp-fade-up">
                <form method="get" id="reportFilterForm" class="row g-2">
                    <input type="hidden" name="page" value="1">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small mb-1">Search</label>
                        <input name="search" value="<?php echo h($search); ?>" class="form-control" placeholder="Student, exam, subject..." aria-label="Search">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small mb-1">Subject</label>
                        <select name="subject_id" class="form-select">
                            <option value="">All Assigned Subjects</option>
                            <?php foreach ($assigned_subjects_list as $s) { ?>
                                <option value="<?php echo $s["id"]; ?>" <?php if ($subject_id == (int)$s["id"]) echo "selected"; ?>>
                                    <?php echo h($s["subject_name"]); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1">Exam</label>
                        <select name="exam_id" class="form-select">
                            <option value="">All Exams</option>
                            <?php if ($assigned_exams_list && mysqli_num_rows($assigned_exams_list) > 0) {
                                mysqli_data_seek($assigned_exams_list, 0);
                                while ($ex = mysqli_fetch_assoc($assigned_exams_list)) { ?>
                                    <option value="<?php echo $ex["id"]; ?>" <?php if ($exam_id == (int)$ex["id"]) echo "selected"; ?>>
                                        <?php echo h($ex["exam_title"]); ?>
                                    </option>
                                <?php }
                            } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1">Course</label>
                        <select name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach (academic_course_options() as $c_opt) { ?>
                                <option value="<?php echo h($c_opt); ?>" <?php if ($filter_course === $c_opt) echo 'selected'; ?>><?php echo h($c_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1">Semester</label>
                        <select name="semester" class="form-select">
                            <option value="">All Semesters</option>
                            <?php foreach (academic_semester_options() as $s_opt) { ?>
                                <option value="<?php echo h($s_opt); ?>" <?php if ($filter_semester === $s_opt) echo 'selected'; ?>><?php echo h($s_opt); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Pass" <?php if ($status == 'Pass') echo 'selected'; ?>>Pass</option>
                            <option value="Fail" <?php if ($status == 'Fail') echo 'selected'; ?>>Fail</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1">From Date</label>
                        <input type="date" name="date_from" value="<?php echo h($date_from); ?>" class="form-control" placeholder="From">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small mb-1">To Date</label>
                        <input type="date" name="date_to" value="<?php echo h($date_to); ?>" class="form-control" placeholder="To">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <a href="reports.php" class="btn btn-outline-secondary" title="Clear filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Reports Table -->
            <div class="sp-table-wrapper sp-fade-up">
                <table class="table sp-table align-middle" id="reportsTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course / Semester</th>
                            <th>Attempts</th>
                            <th>Avg Score</th>
                            <th>Best / Lowest</th>
                            <th>Pass / Fail</th>
                            <th>Last Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($studentsQ && mysqli_num_rows($studentsQ) > 0) {
                            while ($st = mysqli_fetch_assoc($studentsQ)) { ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="profile-avatar" style="width:36px;height:36px;font-size:0.9rem;">
                                                <?php echo strtoupper(substr($st["name"] ?? 'S', 0, 1)); ?>
                                            </span>
                                            <div class="min-w-0">
                                                <strong class="d-block text-truncate" style="max-width:180px;"><?php echo h($st["name"]); ?></strong>
                                                <small class="text-muted d-block text-truncate" style="max-width:180px;"><?php echo h($st["email"]); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?php echo h($st["course"] ?? "N/A"); ?></small>
                                        <br><small class="text-muted"><?php echo h($st["semester"] ?? "N/A"); ?></small>
                                    </td>
                                    <td><strong><?php echo (int)$st["total_attempts"]; ?></strong></td>
                                    <td>
                                        <div class="sp-score-ring" style="--p:<?php echo (int)round($st["avg_percentage"]); ?>;--c:<?php echo $st["avg_percentage"] >= 40 ? '#22C55E' : '#EF4444'; ?>;width:48px;height:48px;" aria-label="Average score <?php echo round($st["avg_percentage"],1); ?>%">
                                            <div class="sp-score-ring-inner">
                                                <strong><?php echo number_format((float)$st["avg_percentage"], 1); ?>%</strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <span class="badge bg-success-soft text-success"><?php echo number_format((float)$st["best_percentage"], 1); ?>%</span>
                                            <span class="badge bg-secondary-soft text-secondary"><?php echo number_format((float)$st["lowest_percentage"], 1); ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <span class="badge bg-success"><?php echo (int)$st["pass_count"]; ?>P</span>
                                            <span class="badge bg-danger"><?php echo (int)$st["fail_count"]; ?>F</span>
                                        </div>
                                    </td>
                                    <td><small class="text-muted"><?php echo !empty($st["last_attempt_date"]) ? date("M j, Y", strtotime($st["last_attempt_date"])) : "N/A"; ?></small></td>
                                </tr>
                            <?php }
                        } else { ?>
                            <tr>
                                <td colspan="7" class="sp-empty">
                                    <i class="bi bi-file-earmark-bar-graph"></i>
                                    <h5>No Report Records</h5>
                                    <p class="mb-0">No student exam records match the current filters.</p>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1) { ?>
                <nav class="sp-pagination mt-3" aria-label="Reports pagination">
                    <ul class="pagination justify-content-center flex-wrap gap-1">
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        if ($start > 1) $start = max(1, $end - 4);
                        if ($end < $total_pages) $end = min($total_pages, $start + 4);
                        $pg_keep = array('search' => $search, 'subject_id' => $subject_id, 'exam_id' => $exam_id, 'status' => $status, 'date_from' => $date_from, 'date_to' => $date_to);
                        if ($page > 1) { ?>
                            <li class="page-item"><a class="page-link" href="<?php echo build_report_url($page - 1, $pg_keep); ?>" aria-label="Previous"><i class="bi bi-chevron-left"></i></a></li>
                        <?php }
                        for ($i = $start; $i <= $end; $i++) { ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo build_report_url($i, $pg_keep); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php }
                        if ($page < $total_pages) { ?>
                            <li class="page-item"><a class="page-link" href="<?php echo build_report_url($page + 1, $pg_keep); ?>" aria-label="Next"><i class="bi bi-chevron-right"></i></a></li>
                        <?php } ?>
                    </ul>
                </nav>
            <?php } ?>

        </div>
    </div>
</div>

<!-- Report configuration modal -->
<div class="modal fade" id="rpConfigModal" tabindex="-1" aria-labelledby="rpConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="get" class="modal-content professional-modal" id="rpConfigForm">
            <input type="hidden" name="report_type" id="rpConfigType" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="rpConfigModalLabel"><i class="bi bi-gear me-2 text-primary"></i>Configure Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3" data-rp-field="exam">
                    <label class="fp-label" for="rpFieldExam">Exam</label>
                    <select name="rpt_exam" id="rpFieldExam" class="form-select">
                        <option value="">All Exams in Scope</option>
                        <?php if ($assigned_exams_list) { mysqli_data_seek($assigned_exams_list, 0); while ($exo = mysqli_fetch_assoc($assigned_exams_list)) { ?>
                            <option value="<?php echo (int)$exo["id"]; ?>" <?php if ($rp_exam === (int)$exo["id"]) echo "selected"; ?>><?php echo h($exo["exam_title"]); ?></option>
                        <?php } } ?>
                    </select>
                </div>
                <div class="mb-3" data-rp-field="subject">
                    <label class="fp-label" for="rpFieldSubject">Subject</label>
                    <select name="rpt_subject" id="rpFieldSubject" class="form-select">
                        <option value="">All Assigned Subjects</option>
                        <?php foreach ($assigned_subjects_list as $so) { ?>
                            <option value="<?php echo (int)$so["id"]; ?>" <?php if ($rp_subject === (int)$so["id"]) echo "selected"; ?>><?php echo h($so["subject_name"]); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-3" data-rp-field="difficulty">
                    <label class="fp-label" for="rpFieldDifficulty">Difficulty Level</label>
                    <select name="rpt_difficulty" id="rpFieldDifficulty" class="form-select">
                        <option value="">All Levels</option>
                        <?php foreach (array("Easy", "Medium", "Hard") as $rdo) { ?>
                            <option value="<?php echo $rdo; ?>" <?php if ($rp_difficulty === $rdo) echo "selected"; ?>><?php echo $rdo; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-3" data-rp-field="student">
                    <label class="fp-label" for="rpFieldStudent">Student</label>
                    <select name="rpt_student" id="rpFieldStudent" class="form-select">
                        <option value="">All Students</option>
                        <?php foreach ($rp_student_opts as $spo) { ?>
                            <option value="<?php echo (int)$spo["id"]; ?>" <?php if ($rp_student === (int)$spo["id"]) echo "selected"; ?>><?php echo h($spo["name"]); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-3" data-rp-field="course">
                    <label class="fp-label" for="rpFieldCourse">Course</label>
                    <select name="rpt_course" id="rpFieldCourse" class="form-select">
                        <option value="">All Courses</option>
                        <?php foreach (academic_course_options() as $cmo) { ?>
                            <option value="<?php echo h($cmo); ?>" <?php if ($rp_course === $cmo) echo "selected"; ?>><?php echo h($cmo); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-3" data-rp-field="semester">
                    <label class="fp-label" for="rpFieldSemester">Semester</label>
                    <select name="rpt_semester" id="rpFieldSemester" class="form-select">
                        <option value="">All Semesters</option>
                        <?php foreach ($rp_sem_opts as $smo) { ?>
                            <option value="<?php echo h($smo); ?>" <?php if ($rp_semester === $smo) echo "selected"; ?>><?php echo h($smo); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="row g-2 mb-3" data-rp-field="dates">
                    <div class="col-6">
                        <label class="fp-label" for="rpFieldFrom">From</label>
                        <input type="date" name="rpt_from" id="rpFieldFrom" class="form-control" value="<?php echo h($rp_from); ?>">
                    </div>
                    <div class="col-6">
                        <label class="fp-label" for="rpFieldTo">To</label>
                        <input type="date" name="rpt_to" id="rpFieldTo" class="form-control" value="<?php echo h($rp_to); ?>">
                    </div>
                </div>
                <div class="mb-1" data-rp-field="status">
                    <label class="fp-label" for="rpFieldStatus">Result Status</label>
                    <select name="rpt_status" id="rpFieldStatus" class="form-select">
                        <option value="">Pass &amp; Fail</option>
                        <option value="Pass" <?php if ($rp_status === "Pass") echo "selected"; ?>>Pass only</option>
                        <option value="Fail" <?php if ($rp_status === "Fail") echo "selected"; ?>>Fail only</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sp-btn sp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sp-btn sp-btn-primary" id="rpConfigSubmit"><i class="bi bi-bar-chart"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var fieldMap = {
        exam: ["exam", "dates"],
        student_performance: ["student", "subject", "dates"],
        subject: ["subject"],
        result: ["exam", "status", "dates"],
        question: ["subject", "difficulty"],
        passfail: ["subject", "dates"]
    };
    document.querySelectorAll("[data-rp-config]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var type = btn.getAttribute("data-rp-config");
            var typeInput = document.getElementById("rpConfigType");
            if (typeInput) { typeInput.value = type; }
            var show = fieldMap[type] || [];
            document.querySelectorAll("#rpConfigModal [data-rp-field]").forEach(function (wrap) {
                wrap.style.display = show.indexOf(wrap.getAttribute("data-rp-field")) !== -1 ? "" : "none";
            });
            var titles = { exam: "Exam Report", student_performance: "Student Performance Report", subject: "Subject Report", result: "Result Report", question: "Question Analysis", passfail: "Pass/Fail Report" };
            var titleEl = document.querySelector("#rpConfigModalLabel");
            if (titleEl && titles[type]) {
                titleEl.innerHTML = '<i class="bi bi-gear me-2 text-primary"></i>Configure — ' + titles[type];
            }
        });
    });
    var cfgForm = document.getElementById("rpConfigForm");
    if (cfgForm) {
        cfgForm.addEventListener("submit", function () {
            var btn = document.getElementById("rpConfigSubmit");
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Generating...';
            }
        });
    }
});
</script>

<!-- Live search script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Client-side live filter for table
    const searchInput = document.querySelector('[name="search"]');
    const table = document.getElementById('reportsTable');
    if (searchInput && table) {
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const term = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                let visibleCount = 0;
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    const show = text.indexOf(term) >= 0;
                    row.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });
                // Show/hide empty state
                const emptyRow = table.querySelector('.sp-empty');
                if (emptyRow) emptyRow.style.display = visibleCount === 0 ? 'table-cell' : 'none';
            }, 150);
        });
    }

    // Auto-submit filters on change (optional)
    document.querySelectorAll('#reportFilterForm select').forEach(sel => {
        sel.addEventListener('change', function() {
            // Uncomment to auto-submit: this.form.submit();
        });
    });
});
</script>

<?php
include("../app/layouts/faculty_footer.php");
mysqli_close($conn);
?>