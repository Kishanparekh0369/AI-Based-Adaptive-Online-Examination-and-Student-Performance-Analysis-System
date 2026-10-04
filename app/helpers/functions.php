<?php
function ensure_support_tables()
{
    global $conn;

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_type VARCHAR(20) NOT NULL,
        user_id INT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(255) DEFAULT '#',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(120) NOT NULL,
        subject VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        reply TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $language_column = mysqli_query($conn, "SHOW COLUMNS FROM questions LIKE 'language'");
    if ($language_column && mysqli_num_rows($language_column) == 0) {
        mysqli_query($conn, "ALTER TABLE questions ADD language VARCHAR(40) NOT NULL DEFAULT 'English' AFTER question");
    }

    $time_spent_column = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'time_spent_seconds'");
    if ($time_spent_column && mysqli_num_rows($time_spent_column) == 0) {
        mysqli_query($conn, "ALTER TABLE results ADD time_spent_seconds INT NOT NULL DEFAULT 0 AFTER status");
    }

    // Adaptive exam tables
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS adaptive_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        exam_id INT NOT NULL,
        theta_value DECIMAL(5,2) DEFAULT 0.00,
        current_difficulty VARCHAR(20) DEFAULT 'Medium',
        questions_asked INT DEFAULT 0,
        correct_count INT DEFAULT 0,
        wrong_count INT DEFAULT 0,
        is_completed TINYINT(1) DEFAULT 0,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
    )");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS adaptive_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        question_id INT NOT NULL,
        student_answer CHAR(1),
        is_correct TINYINT(1) DEFAULT 0,
        time_taken_seconds INT DEFAULT 0,
        difficulty_at_time VARCHAR(20),
        FOREIGN KEY (session_id) REFERENCES adaptive_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    )");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS exam_attempt_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        exam_id INT NOT NULL,
        session_id INT NULL,
        reason VARCHAR(80) NOT NULL DEFAULT 'Navigation exit',
        blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_exam_block (student_id, exam_id),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
    )");

    // Check and add new columns to results
    $ai_insight_col = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'ai_insight'");
    if ($ai_insight_col && mysqli_num_rows($ai_insight_col) == 0) {
        mysqli_query($conn, "ALTER TABLE results ADD ai_insight TEXT AFTER status");
    }

    $skill_col = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'skill_category'");
    if ($skill_col && mysqli_num_rows($skill_col) == 0) {
        mysqli_query($conn, "ALTER TABLE results ADD skill_category VARCHAR(20) AFTER ai_insight");
    }

    $theta_col = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'theta_score'");
    if ($theta_col && mysqli_num_rows($theta_col) == 0) {
        mysqli_query($conn, "ALTER TABLE results ADD theta_score DECIMAL(5,2) AFTER skill_category");
    }

    $diff_breakdown_col = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'difficulty_breakdown'");
    if ($diff_breakdown_col && mysqli_num_rows($diff_breakdown_col) == 0) {
        mysqli_query($conn, "ALTER TABLE results ADD difficulty_breakdown JSON AFTER theta_score");
    }

    // Check exam_mode column
    $exam_mode_col = mysqli_query($conn, "SHOW COLUMNS FROM exams LIKE 'exam_mode'");
    if ($exam_mode_col && mysqli_num_rows($exam_mode_col) == 0) {
        mysqli_query($conn, "ALTER TABLE exams ADD exam_mode VARCHAR(20) DEFAULT 'Standard' AFTER status");
    }

    // Check max_questions column for adaptive exams
    $max_q_col = mysqli_query($conn, "SHOW COLUMNS FROM exams LIKE 'max_questions'");
    if ($max_q_col && mysqli_num_rows($max_q_col) == 0) {
        mysqli_query($conn, "ALTER TABLE exams ADD max_questions INT DEFAULT 10 AFTER exam_mode");
    }

    // Check ai_generated column in questions
    $ai_gen_col = mysqli_query($conn, "SHOW COLUMNS FROM questions LIKE 'ai_generated'");
    if ($ai_gen_col && mysqli_num_rows($ai_gen_col) == 0) {
        mysqli_query($conn, "ALTER TABLE questions ADD ai_generated TINYINT(1) DEFAULT 0 AFTER marks");
    }

    // Check keywords column in questions
    $keywords_col = mysqli_query($conn, "SHOW COLUMNS FROM questions LIKE 'keywords'");
    if ($keywords_col && mysqli_num_rows($keywords_col) == 0) {
        mysqli_query($conn, "ALTER TABLE questions ADD keywords VARCHAR(255) DEFAULT NULL AFTER ai_generated");
    }

    // Check session_id column in results (for adaptive exam linking)
    $session_id_col = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'session_id'");
    if ($session_id_col && mysqli_num_rows($session_id_col) == 0) {
        mysqli_query($conn, "ALTER TABLE results ADD session_id INT NULL AFTER exam_id");
    }

    // Check answers_detail column in results
    $answers_detail_col = mysqli_query($conn, "SHOW COLUMNS FROM results LIKE 'answers_detail'");
    if ($answers_detail_col && mysqli_num_rows($answers_detail_col) == 0) {
        mysqli_query($conn, "ALTER TABLE results ADD answers_detail LONGTEXT NULL AFTER difficulty_breakdown");
    }

    // Profile image columns for students
    $profile_img_col = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'profile_image'");
    if ($profile_img_col && mysqli_num_rows($profile_img_col) == 0) {
        mysqli_query($conn, "ALTER TABLE students ADD profile_image VARCHAR(255) DEFAULT NULL AFTER semester");
    }

    $profile_ts_col = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'profile_image_updated_at'");
    if ($profile_ts_col && mysqli_num_rows($profile_ts_col) == 0) {
        mysqli_query($conn, "ALTER TABLE students ADD profile_image_updated_at TIMESTAMP NULL AFTER profile_image");
    }

    $last_login_col = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'last_login'");
    if ($last_login_col && mysqli_num_rows($last_login_col) == 0) {
        mysqli_query($conn, "ALTER TABLE students ADD last_login TIMESTAMP NULL AFTER profile_image_updated_at");
    }

    // Faculty table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS faculty (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        profile_image VARCHAR(255) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        last_login TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Faculty assigned subjects
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS faculty_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT NOT NULL,
        subject_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_faculty_subject (faculty_id, subject_id)
    )");

    // Faculty ID in exams
    $fac_col = mysqli_query($conn, "SHOW COLUMNS FROM exams LIKE 'faculty_id'");
    if ($fac_col && mysqli_num_rows($fac_col) == 0) {
        mysqli_query($conn, "ALTER TABLE exams ADD faculty_id INT NULL AFTER subject_id");
    }

    // Faculty ID in questions
    $fac_q_col = mysqli_query($conn, "SHOW COLUMNS FROM questions LIKE 'faculty_id'");
    if ($fac_q_col && mysqli_num_rows($fac_q_col) == 0) {
        mysqli_query($conn, "ALTER TABLE questions ADD faculty_id INT NULL AFTER subject_id");
    }

    // ─── Academic targeting: course + semester on exams (REQUIRED for new exams) ───
    // '' (empty) = legacy exam with unconfigured scope -> hidden from students by default.
    $exam_course_col = mysqli_query($conn, "SHOW COLUMNS FROM exams LIKE 'course'");
    if ($exam_course_col && mysqli_num_rows($exam_course_col) == 0) {
        mysqli_query($conn, "ALTER TABLE exams ADD course VARCHAR(80) NOT NULL DEFAULT '' AFTER subject_id");
    }
    $exam_sem_col = mysqli_query($conn, "SHOW COLUMNS FROM exams LIKE 'semester'");
    if ($exam_sem_col && mysqli_num_rows($exam_sem_col) == 0) {
        mysqli_query($conn, "ALTER TABLE exams ADD semester VARCHAR(30) NOT NULL DEFAULT '' AFTER course");
    }

    // ─── Academic targeting: optional scope on subjects ───
    // NULL = legacy/universal subject (visible for every course+semester).
    // Non-NULL = subject belongs to that course+semester; faculty exam form
    // filters the subject dropdown and validates the combination.
    $sub_course_col = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'course'");
    if ($sub_course_col && mysqli_num_rows($sub_course_col) == 0) {
        mysqli_query($conn, "ALTER TABLE subjects ADD course VARCHAR(80) NULL DEFAULT NULL AFTER subject_code");
    }
    $sub_sem_col = mysqli_query($conn, "SHOW COLUMNS FROM subjects LIKE 'semester'");
    if ($sub_sem_col && mysqli_num_rows($sub_sem_col) == 0) {
        mysqli_query($conn, "ALTER TABLE subjects ADD semester VARCHAR(30) NULL DEFAULT NULL AFTER course");
    }

    // ─── Announcements (faculty -> students academic communication) ───
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'general',
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        course VARCHAR(80) NOT NULL DEFAULT '',
        semester VARCHAR(30) NOT NULL DEFAULT '',
        subject_id INT NULL DEFAULT NULL,
        exam_id INT NULL DEFAULT NULL,
        audience VARCHAR(20) NOT NULL DEFAULT 'all',
        priority VARCHAR(20) NOT NULL DEFAULT 'normal',
        is_pinned TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'Draft',
        publish_at DATETIME NULL DEFAULT NULL,
        expires_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_faculty (faculty_id),
        INDEX idx_status_time (status, publish_at)
    )");
    // Snapshot of explicitly picked student IDs for 'specific' audience
    $aud_ids_col = mysqli_query($conn, "SHOW COLUMNS FROM announcements LIKE 'audience_ids'");
    if ($aud_ids_col && mysqli_num_rows($aud_ids_col) == 0) {
        mysqli_query($conn, "ALTER TABLE announcements ADD audience_ids TEXT NULL AFTER audience");
    }
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS announcement_recipients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id INT NOT NULL,
        student_id INT NOT NULL,
        delivered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uniq_ann_student (announcement_id, student_id),
        INDEX idx_student (student_id)
    )");

    // ─── Password reset tokens (forgot-password flow) ───
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_role VARCHAR(20) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_user (user_id, user_role)
    )");

    // Ensure uploads directory for faculty exists
    $faculty_upload_dir = __DIR__ . "/../../assets/uploads/faculty";
    if (!is_dir($faculty_upload_dir)) {
        @mkdir($faculty_upload_dir, 0777, true);
    }
}

function get_faculty_assigned_subject_ids($faculty_id)
{
    global $conn;
    $faculty_id = (int)$faculty_id;
    $ids = array();
    if ($faculty_id <= 0) {
        return $ids;
    }
    $res = mysqli_query($conn, "SELECT subject_id FROM faculty_subjects WHERE faculty_id=$faculty_id");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $ids[] = (int)$row["subject_id"];
        }
    }
    return $ids;
}

function get_faculty_assigned_subjects($faculty_id)
{
    global $conn;
    $faculty_id = (int)$faculty_id;
    $subjects = array();
    if ($faculty_id <= 0) {
        return $subjects;
    }
    $sql = "SELECT s.* FROM subjects s INNER JOIN faculty_subjects fs ON s.id=fs.subject_id WHERE fs.faculty_id=$faculty_id ORDER BY s.subject_name ASC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $subjects[] = $row;
        }
    }
    return $subjects;
}

function faculty_has_subject($faculty_id, $subject_id)
{
    global $conn;
    $faculty_id = (int)$faculty_id;
    $subject_id = (int)$subject_id;
    if ($faculty_id <= 0 || $subject_id <= 0) {
        return false;
    }
    $res = mysqli_query($conn, "SELECT id FROM faculty_subjects WHERE faculty_id=$faculty_id AND subject_id=$subject_id LIMIT 1");
    return ($res && mysqli_num_rows($res) > 0);
}

/* ══════════════════════════════════════════════════
   Academic scope helpers (single source of truth)
   Course / Semester live ONLY in `students`.
   - exams.course + exams.semester: REQUIRED for new exams.
     '' = legacy unconfigured exam -> hidden from students.
   - subjects.course + subjects.semester: OPTIONAL scope.
     NULL = universal subject (all courses/semesters).
   ══════════════════════════════════════════════════ */

function academic_course_options()
{
    return array("BCA", "MCA", "B.Sc IT", "M.Sc IT", "BCS IT", "Computer Engineering", "Information Technology", "Computer Science", "AI & Data Science", "Cyber Security", "Other");
}

function academic_semester_options()
{
    return array("Semester 1", "Semester 2", "Semester 3", "Semester 4", "Semester 5", "Semester 6", "Semester 7", "Semester 8");
}

function academic_valid_course($course)
{
    return in_array((string)$course, academic_course_options(), true);
}

function academic_valid_semester($semester)
{
    return in_array((string)$semester, academic_semester_options(), true);
}

/**
 * Assigned subjects of a faculty narrowed to a course+semester scope.
 * Subjects with NULL scope are universal and always included.
 */
function faculty_subjects_for_scope($faculty_id, $course = "", $semester = "")
{
    global $conn;
    $faculty_id = (int)$faculty_id;
    $subjects = array();
    if ($faculty_id <= 0) {
        return $subjects;
    }
    $sql = "SELECT s.* FROM subjects s INNER JOIN faculty_subjects fs ON s.id=fs.subject_id WHERE fs.faculty_id=$faculty_id AND s.status='Active'";
    if ($course !== "") {
        $c = mysqli_real_escape_string($conn, $course);
        $sql .= " AND (s.course IS NULL OR s.course='' OR s.course='$c')";
    }
    if ($semester !== "") {
        $s = mysqli_real_escape_string($conn, $semester);
        $sql .= " AND (s.semester IS NULL OR s.semester='' OR s.semester='$s')";
    }
    $sql .= " ORDER BY s.subject_name ASC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $subjects[] = $row;
        }
    }
    return $subjects;
}

/**
 * Does a subject's configured scope accept the given course+semester?
 * NULL/empty scope on the subject = universal = always true.
 */
function subject_accepts_scope($subject_id, $course, $semester)
{
    global $conn;
    $subject_id = (int)$subject_id;
    if ($subject_id <= 0) {
        return false;
    }
    $res = mysqli_query($conn, "SELECT course, semester FROM subjects WHERE id=$subject_id LIMIT 1");
    if (!$res || mysqli_num_rows($res) == 0) {
        return false;
    }
    $row = mysqli_fetch_assoc($res);
    $sc = trim((string)($row["course"] ?? ""));
    $ss = trim((string)($row["semester"] ?? ""));
    if ($sc !== "" && $sc !== (string)$course) {
        return false;
    }
    if ($ss !== "" && $ss !== (string)$semester) {
        return false;
    }
    return true;
}

/**
 * Count of Active students eligible for a course+semester scope.
 */
function exam_eligible_student_count($course, $semester)
{
    global $conn;
    $c = mysqli_real_escape_string($conn, (string)$course);
    $s = mysqli_real_escape_string($conn, (string)$semester);
    $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM students WHERE course='$c' AND semester='$s' AND status='Active'");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return (int)$row["total"];
    }
    return 0;
}

/**
 * Server-side exam eligibility for a student.
 * Rule: student.course == exam.course AND student.semester == exam.semester
 * AND exam Active AND exam scope configured.
 * Returns array("ok" => bool, "reason" => string).
 * NEVER trust course/semester from the request — both are read from the DB.
 */
function student_exam_eligible($student_id, $exam_id)
{
    global $conn;
    $student_id = (int)$student_id;
    $exam_id = (int)$exam_id;
    if ($student_id <= 0 || $exam_id <= 0) {
        return array("ok" => false, "reason" => "Invalid exam request.");
    }
    $st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT course, semester, status FROM students WHERE id=$student_id LIMIT 1"));
    if (!$st) {
        return array("ok" => false, "reason" => "Student record not found.");
    }
    if (($st["status"] ?? "Active") !== "Active") {
        return array("ok" => false, "reason" => "Your account is currently inactive. Please contact the administrator.");
    }
    $ex = mysqli_fetch_assoc(mysqli_query($conn, "SELECT course, semester, status FROM exams WHERE id=$exam_id LIMIT 1"));
    if (!$ex) {
        return array("ok" => false, "reason" => "Exam not found.");
    }
    if (($ex["status"] ?? "") !== "Active") {
        return array("ok" => false, "reason" => "Exam not available.");
    }
    $ec = trim((string)($ex["course"] ?? ""));
    $es = trim((string)($ex["semester"] ?? ""));
    if ($ec === "" || $es === "") {
        return array("ok" => false, "reason" => "This examination is not available for your academic program or semester.");
    }
    if (trim((string)$st["course"]) !== $ec || trim((string)$st["semester"]) !== $es) {
        return array("ok" => false, "reason" => "This examination is not available for your academic program or semester.");
    }
    return array("ok" => true, "reason" => "");
}

/* ══════════════════════════════════════════════════
   Announcement system helpers
   Targeting reuses the exam academic architecture:
   Course + Semester (+ optional Subject) -> students.
   ══════════════════════════════════════════════════ */

function announcement_types()
{
    return array(
        "quiz" => array("label" => "Quiz", "icon" => "bi-patch-question-fill"),
        "exam" => array("label" => "Exam", "icon" => "bi-clipboard-check-fill"),
        "assignment" => array("label" => "Assignment", "icon" => "bi-journal-bookmark-fill"),
        "important" => array("label" => "Important", "icon" => "bi-exclamation-triangle-fill"),
        "general" => array("label" => "General", "icon" => "bi-megaphone-fill"),
    );
}

function announcement_priorities()
{
    return array("normal" => "Normal", "important" => "Important", "urgent" => "Urgent");
}

function announcement_valid_type($type)
{
    return array_key_exists((string)$type, announcement_types());
}

/**
 * Resolve eligible student IDs for an announcement scope.
 * $audience: 'all' | 'subject' | 'specific'
 * 'subject' = course+semester students having >=1 result in the subject.
 * 'specific' = $specific_ids intersected with the course+semester scope.
 */
function announcement_scope_students($course, $semester, $subject_id = 0, $audience = "all", $specific_ids = array())
{
    global $conn;
    $course = trim((string)$course);
    $semester = trim((string)$semester);
    $subject_id = (int)$subject_id;
    $ids = array();
    if ($course === "" || $semester === "") {
        return $ids;
    }
    $c = mysqli_real_escape_string($conn, $course);
    $s = mysqli_real_escape_string($conn, $semester);
    if ($audience === "specific" && !empty($specific_ids)) {
        $clean = array();
        foreach ((array)$specific_ids as $sid) {
            $sid = (int)$sid;
            if ($sid > 0) { $clean[] = $sid; }
        }
        if (empty($clean)) {
            return $ids;
        }
        $in = implode(",", array_unique($clean));
        $res = mysqli_query($conn, "SELECT id FROM students WHERE id IN ($in) AND course='$c' AND semester='$s' AND status='Active'");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) { $ids[] = (int)$row["id"]; }
        }
        return $ids;
    }
    if ($audience === "subject" && $subject_id > 0) {
        $res = mysqli_query($conn, "SELECT DISTINCT st.id FROM students st
            INNER JOIN results r ON r.student_id=st.id
            INNER JOIN exams e ON r.exam_id=e.id
            WHERE st.course='$c' AND st.semester='$s' AND st.status='Active' AND e.subject_id=$subject_id");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) { $ids[] = (int)$row["id"]; }
        }
        return $ids;
    }
    $res = mysqli_query($conn, "SELECT id FROM students WHERE course='$c' AND semester='$s' AND status='Active'");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) { $ids[] = (int)$row["id"]; }
    }
    return $ids;
}

/**
 * Materialize delivery for a Published announcement:
 * recipient rows + bell notification rows. Idempotent.
 * Returns number of newly delivered recipients.
 */
function announcement_publish($announcement_id)
{
    global $conn;
    $announcement_id = (int)$announcement_id;
    $a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM announcements WHERE id=$announcement_id LIMIT 1"));
    if (!$a || ($a["status"] ?? "") !== "Published") {
        return 0;
    }
    // Specific-student snapshot stored at create/schedule time.
    $specific = array();
    if (!empty($a["audience_ids"])) {
        $decoded = json_decode((string)$a["audience_ids"], true);
        if (is_array($decoded)) { $specific = $decoded; }
    }
    $existing = mysqli_query($conn, "SELECT COUNT(*) AS c FROM announcement_recipients WHERE announcement_id=$announcement_id");
    $existing_count = $existing ? (int)(mysqli_fetch_assoc($existing)["c"] ?? 0) : 0;
    if ($existing_count > 0) {
        return 0;
    }
    $ids = announcement_scope_students($a["course"], $a["semester"], (int)($a["subject_id"] ?? 0), $a["audience"] ?? "all", $specific);
    // For 'specific' audience with no snapshot, fall back to full scope so a
    // scheduled announcement never silently delivers to zero.
    if (empty($ids) && ($a["audience"] ?? "all") === "specific") {
        $ids = announcement_scope_students($a["course"], $a["semester"], (int)($a["subject_id"] ?? 0), "all", array());
    }
    $delivered = 0;
    $title = "Announcement: " . $a["title"];
    $link = "announcements.php?view=" . $announcement_id;
    foreach ($ids as $sid) {
        $sid = (int)$sid;
        if (mysqli_query($conn, "INSERT IGNORE INTO announcement_recipients (announcement_id, student_id) VALUES ($announcement_id, $sid)")) {
            if (mysqli_affected_rows($conn) > 0) {
                $delivered++;
                notify_user("student", $sid, $title, mb_substr($a["message"], 0, 140), $link);
            }
        }
    }
    return $delivered;
}

/**
 * Auto-publish due Scheduled announcements. Cheap: one UPDATE scan, then
 * materialize only newly due rows. Safe to call on every page load.
 */
function announcement_auto_publish()
{
    global $conn;
    $due = mysqli_query($conn, "SELECT id FROM announcements WHERE status='Scheduled' AND publish_at IS NOT NULL AND publish_at <= NOW()");
    if (!$due) {
        return 0;
    }
    $ids = array();
    while ($row = mysqli_fetch_assoc($due)) { $ids[] = (int)$row["id"]; }
    if (empty($ids)) {
        return 0;
    }
    $in = implode(",", $ids);
    mysqli_query($conn, "UPDATE announcements SET status='Published' WHERE id IN ($in)");
    $total = 0;
    foreach ($ids as $aid) {
        $total += announcement_publish($aid);
    }
    return $total;
}

/**
 * Unread announcement count for a student (published, visible now, unread).
 */
function student_unread_announcements($student_id)
{
    global $conn;
    $student_id = (int)$student_id;
    if ($student_id <= 0) {
        return 0;
    }
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM announcement_recipients ar
        INNER JOIN announcements a ON a.id=ar.announcement_id
        WHERE ar.student_id=$student_id AND ar.read_at IS NULL
        AND a.status='Published' AND (a.publish_at IS NULL OR a.publish_at <= NOW())
        AND (a.expires_at IS NULL OR a.expires_at > NOW())");
    return $res ? (int)(mysqli_fetch_assoc($res)["c"] ?? 0) : 0;
}

/**
 * Pending (Draft + Scheduled) announcement count for a faculty member.
 */
function faculty_pending_announcements($faculty_id)
{
    global $conn;
    $faculty_id = (int)$faculty_id;
    if ($faculty_id <= 0) {
        return 0;
    }
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM announcements WHERE faculty_id=$faculty_id AND status IN ('Draft','Scheduled')");
    return $res ? (int)(mysqli_fetch_assoc($res)["c"] ?? 0) : 0;
}

function faculty_profile_avatar($faculty, $class_name = "profile-avatar")
{
    $name = is_array($faculty) ? ($faculty["name"] ?? "F") : "F";
    $image = is_array($faculty) ? ($faculty["profile_image"] ?? "") : "";
    $image_path = __DIR__ . "/../../assets/uploads/faculty/" . $image;
    if ($image != "" && file_exists($image_path)) {
        return '<img src="../assets/uploads/faculty/' . h($image) . '" class="' . h($class_name) . '" style="object-fit:cover" alt="Faculty profile photo">';
    }
    return '<span class="' . h($class_name) . '">' . h(strtoupper(substr($name, 0, 1))) . '</span>';
}

function upload_faculty_profile_image($field_name, $redirect_page = "profile.php")
{
    if (empty($_FILES[$field_name]) || $_FILES[$field_name]["error"] === UPLOAD_ERR_NO_FILE) {
        return "";
    }
    if ($_FILES[$field_name]["error"] !== UPLOAD_ERR_OK) {
        redirect_alert("Profile image upload failed", $redirect_page);
    }
    $allowed = array("image/jpeg", "image/png", "image/webp");
    if (!in_array($_FILES[$field_name]["type"], $allowed)) {
        redirect_alert("Only JPEG, PNG and WebP files are allowed", $redirect_page);
    }
    if ($_FILES[$field_name]["size"] > 2 * 1024 * 1024) {
        redirect_alert("File size must be under 2MB", $redirect_page);
    }
    $ext = pathinfo($_FILES[$field_name]["name"], PATHINFO_EXTENSION);
    $filename = "faculty_" . time() . "_" . rand(100, 999) . "." . strtolower($ext);
    $target_dir = __DIR__ . "/../../assets/uploads/faculty/";
    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0777, true);
    }
    $filepath = $target_dir . $filename;
    if (!move_uploaded_file($_FILES[$field_name]["tmp_name"], $filepath)) {
        redirect_alert("Profile image upload failed", $redirect_page);
    }
    return $filename;
}

function flash_script($message, $url, $icon = "success", $title = "")
{
    $_SESSION["flash_alert"] = array(
        "message" => $message,
        "url" => $url,
        "icon" => $icon,
        "title" => $title
    );
}

function alert_icon_from_message($message)
{
    $text = strtolower((string)$message);
    $errorWords = array('required', 'invalid', 'error', 'failed', 'fail', 'cannot', 'already', 'incorrect', 'missing', 'not found', 'not available', 'must be', 'select at least', 'size must', 'only jpeg', 'exists');
    foreach ($errorWords as $word) {
        if (strpos($text, $word) !== false) {
            return 'error';
        }
    }
    if (strpos($text, 'time over') !== false || strpos($text, 'abandoned') !== false) {
        return 'warning';
    }
    return 'success';
}

function redirect_alert($message, $url, $icon = null)
{
    flash_script($message, $url, $icon ?: alert_icon_from_message($message));
    header("Location: " . $url);
    exit;
}

function notify_user($user_type, $user_id, $title, $message, $link = "#")
{
    global $conn;
    // Student notifications must name a recipient. Course/semester scoped
    // callers resolve their eligible students before invoking this helper.
    if ($user_type === "student" && $user_id === null) {
        return;
    }
    $user_type = mysqli_real_escape_string($conn, $user_type);
    $user_id_sql = $user_id === null ? "NULL" : "'" . mysqli_real_escape_string($conn, $user_id) . "'";
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $link = mysqli_real_escape_string($conn, $link);
    mysqli_query($conn, "INSERT INTO notifications (user_type,user_id,title,message,link) VALUES ('$user_type',$user_id_sql,'$title','$message','$link')");
}

function exam_completed($student_id, $exam_id)
{
    global $conn;
    $student_id = (int)$student_id;
    $exam_id = (int)$exam_id;
    $result = mysqli_query($conn, "SELECT id FROM results WHERE student_id=$student_id AND exam_id=$exam_id LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
}

function exam_blocked($student_id, $exam_id)
{
    global $conn;
    $student_id = (int)$student_id;
    $exam_id = (int)$exam_id;
    $result = mysqli_query($conn, "SELECT id FROM exam_attempt_blocks WHERE student_id=$student_id AND exam_id=$exam_id LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
}

function block_exam_attempt($student_id, $exam_id, $reason = "Navigation exit", $session_id = null)
{
    global $conn;
    $student_id = (int)$student_id;
    $exam_id = (int)$exam_id;
    $session_id_sql = $session_id ? (int)$session_id : "NULL";
    $reason = mysqli_real_escape_string($conn, substr((string)$reason, 0, 80));

    if ($student_id <= 0 || $exam_id <= 0 || exam_completed($student_id, $exam_id)) {
        return false;
    }

    mysqli_query($conn, "INSERT INTO exam_attempt_blocks (student_id, exam_id, session_id, reason)
        VALUES ($student_id, $exam_id, $session_id_sql, '$reason')
        ON DUPLICATE KEY UPDATE reason=VALUES(reason), session_id=COALESCE(VALUES(session_id), session_id)");
    return mysqli_affected_rows($conn) >= 0;
}

function notification_data($user_type, $user_id = null)
{
    global $conn;
    $user_type = mysqli_real_escape_string($conn, $user_type);
    $scope = "user_type='$user_type'";
    if ($user_id !== null) {
        $safe_id = mysqli_real_escape_string($conn, $user_id);
        // Student alerts are individually targeted. Legacy broadcast exam rows
        // have no academic scope, so including them leaks other cohorts.
        $scope .= $user_type === "student"
            ? " AND user_id='$safe_id'"
            : " AND (user_id='$safe_id' OR user_id IS NULL)";
    } else {
        $scope .= " AND user_id IS NULL";
    }

    $count = 0;
    $count_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM notifications WHERE $scope AND is_read=0");
    if ($count_result && $row = mysqli_fetch_assoc($count_result)) {
        $count = $row["total"];
    }

    $items = mysqli_query($conn, "SELECT * FROM notifications WHERE $scope ORDER BY id DESC LIMIT 8");
    // Housekeeping: drop old read notifications so the bell never goes stale.
    mysqli_query($conn, "DELETE FROM notifications WHERE is_read=1 AND created_at < (NOW() - INTERVAL 90 DAY) LIMIT 500");
    return array("count" => $count, "items" => $items);
}

function mark_notifications_read($user_type, $user_id = null)
{
    global $conn;
    $user_type = mysqli_real_escape_string($conn, $user_type);
    $scope = "user_type='$user_type'";
    if ($user_id !== null) {
        $safe_id = mysqli_real_escape_string($conn, $user_id);
        $scope .= $user_type === "student"
            ? " AND user_id='$safe_id'"
            : " AND (user_id='$safe_id' OR user_id IS NULL)";
    } else {
        $scope .= " AND user_id IS NULL";
    }
    mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE $scope AND is_read=0");
}

/**
 * Get database connection - resolves IDE red line for $conn
 * @return mysqli
 */
function conn() {
    global $conn;
    return $conn;
}

function render_flash_alert()
{
    if (!isset($_SESSION["flash_alert"])) {
        return;
    }
    $flash = $_SESSION["flash_alert"];
    unset($_SESSION["flash_alert"]);
    $message = json_encode($flash["message"]);
    $icon = json_encode($flash["icon"]);
    $title = json_encode($flash["title"] != "" ? $flash["title"] : ucfirst($flash["icon"]));
    echo "<script>document.addEventListener('DOMContentLoaded',function(){if(typeof Swal!=='undefined'){Swal.fire({title:$title,text:$message,icon:$icon,confirmButtonText:'OK'});}});</script>";
}

function csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify_request()
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        return true;
    }
    $sent = isset($_POST["csrf_token"]) ? (string)$_POST["csrf_token"] : "";
    $expected = isset($_SESSION["csrf_token"]) ? (string)$_SESSION["csrf_token"] : "";
    if ($sent === "" || $expected === "" || !hash_equals($expected, $sent)) {
        return false;
    }
    return true;
}

function perform_logout($message, $url, $name = "")
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
    session_start();
    session_regenerate_id(true);

    $name = trim((string)$name);
    if ($name !== "") {
        $parts = preg_split('/\s+/', $name);
        $first = isset($parts[0]) ? trim((string)$parts[0]) : "";
        if ($first !== "" && mb_strlen($first) <= 40) {
            $sep = (strpos($url, "?") === false) ? "?" : "&";
            $url .= $sep . "name=" . urlencode($first);
        }
    }

    flash_script($message, $url, "success", "Logged Out");
    header("Location: " . $url);
    exit;
}

function welcome_message_for_role($role, $name)
{
    $safeName = trim((string)$name);
    if ($safeName === "") {
        $safeName = "there";
    }

    if ($role === "admin") {
        return array(
            "title" => "Welcome back, " . $safeName . "!",
            "text" => "Login successful. Your Admin Dashboard is ready.",
            "label" => "Admin Dashboard"
        );
    }

    if ($role === "faculty") {
        return array(
            "title" => "Welcome back, Professor " . $safeName . "!",
            "text" => "Login successful. Your Faculty Dashboard is ready.",
            "label" => "Faculty Dashboard"
        );
    }

    return array(
        "title" => "Welcome back, " . $safeName . "!",
        "text" => "Login successful. Your Student Dashboard is ready.",
        "label" => "Student Dashboard"
    );
}

function render_login_welcome()
{
    if (empty($_SESSION["login_success"]) || !is_array($_SESSION["login_success"])) {
        return;
    }

    $payload = $_SESSION["login_success"];
    unset($_SESSION["login_success"]);

    $role = isset($payload["role"]) ? (string)$payload["role"] : "student";
    $name = isset($payload["name"]) ? (string)$payload["name"] : "";
    $welcome = welcome_message_for_role($role, $name);

    $title = json_encode($welcome["title"]);
    $text = json_encode($welcome["text"]);
    $label = json_encode($welcome["label"]);

    echo <<<HTML
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof Swal === "undefined") { return; }

    function runConfetti() {
        if (typeof confetti !== "function") { return; }
        var end = Date.now() + 1400;
        (function frame() {
            confetti({
                particleCount: 4,
                angle: 60,
                spread: 55,
                origin: { x: 0, y: 0.75 },
                colors: ["#2563EB", "#7C3AED", "#38BDF8", "#22C55E"],
                scalar: 0.9,
                disableForReducedMotion: true
            });
            confetti({
                particleCount: 4,
                angle: 120,
                spread: 55,
                origin: { x: 1, y: 0.75 },
                colors: ["#2563EB", "#7C3AED", "#38BDF8", "#22C55E"],
                scalar: 0.9,
                disableForReducedMotion: true
            });
            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        })();
    }

    function withConfettiLib(cb) {
        if (typeof confetti === "function") { cb(); return; }
        var s = document.createElement("script");
        s.src = "https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js";
        s.onload = cb;
        s.onerror = function () {};
        document.head.appendChild(s);
    }

    Swal.fire({
        icon: "success",
        title: $title,
        text: $text,
        confirmButtonText: "Continue",
        confirmButtonColor: "#2563EB",
        allowOutsideClick: false,
        allowEscapeKey: true
    }).then(function () {});

    withConfettiLib(runConfetti);
});
</script>
HTML;
}
?>
