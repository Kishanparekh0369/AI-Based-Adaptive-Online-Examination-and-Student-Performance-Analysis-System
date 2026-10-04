<?php
require_once("../app/config/database.php");

$conn = conn();require_admin();
$asset_path = "../assets/";
$base_path = "../";
$page_title = "Feedback Management";
if (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];
    mysqli_query($conn, "DELETE FROM feedback WHERE id=$id");
    redirect_alert("Feedback deleted successfully", "feedback.php");
}
include("../app/layouts/admin_header.php");

// ---- Filter option lists (real distinct values) ----
$course_opts = array();
$cr = mysqli_query($conn, "SELECT DISTINCT st.course FROM students st INNER JOIN feedback f ON f.student_id=st.id WHERE st.course IS NOT NULL AND st.course<>'' ORDER BY st.course ASC");
if ($cr) { while ($crow = mysqli_fetch_assoc($cr)) { $course_opts[] = $crow["course"]; } }
$semester_opts = array();
$sr = mysqli_query($conn, "SELECT DISTINCT st.semester FROM students st INNER JOIN feedback f ON f.student_id=st.id WHERE st.semester IS NOT NULL AND st.semester<>'' ORDER BY st.semester ASC");
if ($sr) { while ($srow = mysqli_fetch_assoc($sr)) { $semester_opts[] = $srow["semester"]; } }

// ---- Filters (validated server-side) ----
$f_search = isset($_GET["search"]) ? trim((string)$_GET["search"]) : "";
$f_course = isset($_GET["course"]) ? trim((string)$_GET["course"]) : "";
if ($f_course !== "" && !in_array($f_course, $course_opts, true)) { $f_course = ""; }
$f_semester = isset($_GET["semester"]) ? trim((string)$_GET["semester"]) : "";
if ($f_semester !== "" && !in_array($f_semester, $semester_opts, true)) { $f_semester = ""; }
$f_rating = isset($_GET["rating"]) ? (int)$_GET["rating"] : 0;
if ($f_rating < 1 || $f_rating > 5) { $f_rating = 0; }
$f_from = isset($_GET["date_from"]) ? trim((string)$_GET["date_from"]) : "";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) { $f_from = ""; }
$f_to = isset($_GET["date_to"]) ? trim((string)$_GET["date_to"]) : "";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to)) { $f_to = ""; }
$f_page = max(1, (int)($_GET["page"] ?? 1));
$f_per = 10;

$conds = array(); $types = ""; $params = array();
if ($f_search !== "") {
    $like = "%" . $f_search . "%";
    $conds[] = "(st.name LIKE ? OR f.message LIKE ?)";
    $types .= "ss"; array_push($params, $like, $like);
}
if ($f_course !== "") { $conds[] = "st.course = ?"; $types .= "s"; $params[] = $f_course; }
if ($f_semester !== "") { $conds[] = "st.semester = ?"; $types .= "s"; $params[] = $f_semester; }
if ($f_rating > 0) { $conds[] = "f.rating = ?"; $types .= "i"; $params[] = $f_rating; }
if ($f_from !== "") { $conds[] = "f.created_at >= ?"; $types .= "s"; $params[] = $f_from . " 00:00:00"; }
if ($f_to !== "") { $conds[] = "f.created_at <= ?"; $types .= "s"; $params[] = $f_to . " 23:59:59"; }
$where_sql = $conds ? ("WHERE " . implode(" AND ", $conds)) : "";
$from_sql = "FROM feedback f LEFT JOIN students st ON f.student_id=st.id";

function admin_feedback_all($conn, $sql, $types, $params)
{
    $out = array();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { return $out; }
    if ($types !== "" && !empty($params)) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $out[] = $r; } }
    mysqli_stmt_close($stmt);
    return $out;
}

$count_rows = admin_feedback_all($conn, "SELECT COUNT(*) AS c $from_sql $where_sql", $types, $params);
$total_rows = $count_rows ? (int)$count_rows[0]["c"] : 0;
$total_pages = max(1, (int)ceil($total_rows / $f_per));
if ($f_page > $total_pages) { $f_page = $total_pages; }
$offset = ($f_page - 1) * $f_per;
$rows = admin_feedback_all($conn, "SELECT f.*, st.name, st.email, st.course, st.semester $from_sql $where_sql ORDER BY f.id DESC LIMIT $f_per OFFSET $offset", $types, $params);

// ---- Summary stats for the current filtered view (real aggregates) ----
$agg_rows = admin_feedback_all($conn, "SELECT COUNT(*) AS n, AVG(rating) AS avgr, SUM(rating=5) AS s5, SUM(rating=4) AS s4, SUM(rating=3) AS s3, SUM(rating=2) AS s2, SUM(rating=1) AS s1 $from_sql $where_sql", $types, $params);
$agg = $agg_rows ? $agg_rows[0] : array("n" => 0, "avgr" => null, "s5" => 0, "s4" => 0, "s3" => 0, "s2" => 0, "s1" => 0);
$stat_total = (int)($agg["n"] ?? 0);
$stat_avg = $stat_total > 0 && $agg["avgr"] !== null ? round((float)$agg["avgr"], 1) : 0;

$f_params = array("search" => $f_search, "course" => $f_course, "semester" => $f_semester, "rating" => $f_rating > 0 ? $f_rating : "", "date_from" => $f_from, "date_to" => $f_to);
$f_chips = array();
if ($f_search !== "") { $f_chips[] = array("Search: " . $f_search, "feedback.php" . admin_qs($f_params, array("search" => "", "page" => 1))); }
if ($f_course !== "") { $f_chips[] = array("Course: " . $f_course, "feedback.php" . admin_qs($f_params, array("course" => "", "page" => 1))); }
if ($f_semester !== "") { $f_chips[] = array("Semester: " . $f_semester, "feedback.php" . admin_qs($f_params, array("semester" => "", "page" => 1))); }
if ($f_rating > 0) { $f_chips[] = array("Rating: " . $f_rating . " star", "feedback.php" . admin_qs($f_params, array("rating" => "", "page" => 1))); }
if ($f_from !== "" || $f_to !== "") { $f_chips[] = array("Date: " . ($f_from !== "" ? $f_from : "…") . " to " . ($f_to !== "" ? $f_to : "…"), "feedback.php" . admin_qs($f_params, array("date_from" => "", "date_to" => "", "page" => 1))); }

function admin_fb_stars($rating)
{
    $rating = max(1, min(5, (int)$rating));
    $out = '<span class="text-nowrap" aria-label="Rated ' . $rating . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<i class="bi bi-star' . ($i <= $rating ? '-fill text-warning' : ' text-muted') . ' me-1" style="font-size:.75rem" aria-hidden="true"></i>';
    }
    return $out . '<small class="text-muted ms-1">(' . $rating . ')</small></span>';
}
?>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 p-0"><?php include("../app/layouts/admin_sidebar.php"); ?></div>
        <div class="col-lg-10 p-4">
            <?php admin_page_head("Feedback", "bi-chat-dots", "Communication", "Feedback Management", "Review student feedback, ratings and academic experience insights."); ?>

            <?php
            admin_stat_cards(array(
                array("Total Feedback", $stat_total, "bi-chat-square-quote-fill", "blue", "Submissions in view"),
                array("Average Rating", $stat_avg . "/5", "bi-star-half", "amber", "Mean rating"),
                array("5 Star", (int)($agg["s5"] ?? 0), "bi-star-fill", "green", "Excellent"),
                array("4 Star", (int)($agg["s4"] ?? 0), "bi-star-fill", "cyan", "Very good"),
                array("3 Star", (int)($agg["s3"] ?? 0), "bi-star-fill", "violet", "Good"),
                array("2 Star", (int)($agg["s2"] ?? 0), "bi-star-fill", "rose", "Average"),
                array("1 Star", (int)($agg["s1"] ?? 0), "bi-star-fill", "secondary", "Poor")
            ));
            ?>

            <!-- Filters -->
            <form method="get" class="toolbar-card mb-4 sp-fade-up">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1" for="fSearch">Search Feedback</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="fSearch" name="search" value="<?php echo h($f_search); ?>" class="form-control" placeholder="Student name or message...">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fCourse">Course</label>
                        <select id="fCourse" name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($course_opts as $co) { ?><option value="<?php echo h($co); ?>" <?php if ($f_course === $co) echo "selected"; ?>><?php echo h($co); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fSemester">Semester</label>
                        <select id="fSemester" name="semester" class="form-select">
                            <option value="">All Semesters</option>
                            <?php foreach ($semester_opts as $so) { ?><option value="<?php echo h($so); ?>" <?php if ($f_semester === $so) echo "selected"; ?>><?php echo h($so); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small fw-semibold mb-1" for="fRating">Rating</label>
                        <select id="fRating" name="rating" class="form-select">
                            <option value="">All</option>
                            <?php for ($rs = 5; $rs >= 1; $rs--) { ?><option value="<?php echo $rs; ?>" <?php if ($f_rating === $rs) echo "selected"; ?>><?php echo $rs; ?> ★</option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1" for="fFrom">From Date</label>
                        <input type="date" id="fFrom" name="date_from" value="<?php echo h($f_from); ?>" class="form-control">
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small fw-semibold mb-1" for="fTo">To Date</label>
                        <input type="date" id="fTo" name="date_to" value="<?php echo h($f_to); ?>" class="form-control">
                    </div>
                    <div class="col-6 col-md-1 d-flex gap-2">
                        <button class="btn btn-primary flex-fill" type="submit" aria-label="Apply filters"><i class="bi bi-funnel"></i></button>
                        <a class="btn btn-outline-secondary" href="feedback.php" title="Reset filters" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
                <?php admin_chips($f_chips, "feedback.php"); ?>
            </form>

            <!-- Feedback Table -->
            <div class="table-card p-3 sp-fade-up">
                <div class="table-responsive">
                <table class="table table-hover align-middle admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Rating</th>
                            <th>Feedback</th>
                            <th>Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)) { foreach ($rows as $row) {
                            $fid = (int)$row["id"];
                            $msg = (string)($row["message"] ?? "");
                            $short = mb_strlen($msg) > 90 ? mb_substr($msg, 0, 90) . "…" : $msg;
                        ?>
                            <tr>
                                <td data-label="Student"><div class="admin-cell-main"><strong><?php echo h($row["name"] ?? ("#" . (int)$row["student_id"])); ?></strong><small class="text-muted"><?php echo h($row["email"] ?? ""); ?></small></div></td>
                                <td data-label="Course"><?php echo h($row["course"] ?? "-"); ?></td>
                                <td data-label="Semester"><?php echo h($row["semester"] ?? "-"); ?></td>
                                <td data-label="Rating"><?php echo admin_fb_stars($row["rating"]); ?></td>
                                <td data-label="Feedback"><span title="<?php echo h($msg); ?>"><?php echo h($short); ?></span></td>
                                <td data-label="Date"><small class="text-muted"><?php echo !empty($row["created_at"]) ? h(date("d M Y, h:i A", strtotime($row["created_at"]))) : "-"; ?></small></td>
                                <td class="text-center" data-label="Actions">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewFeedbackModal<?php echo $fid; ?>" title="View full feedback" aria-label="View full feedback"><i class="bi bi-eye"></i></button>
                                    <a class="btn btn-sm btn-danger js-confirm-delete" href="feedback.php?delete=<?php echo $fid; ?>" title="Delete" aria-label="Delete feedback"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php } } else {
                            admin_empty_row(7, "bi-chat-square-dots", "No feedback found.", "Try changing your filters. New student feedback will appear here.");
                        } ?>
                    </tbody>
                </table>
                </div>
                <?php admin_pagination($total_rows, $f_page, $f_per, $f_params, "feedback.php"); ?>
            </div>

            <!-- Detail Modals -->
            <?php foreach ($rows as $row) {
                $fid = (int)$row["id"];
            ?>
                <div class="modal fade" id="viewFeedbackModal<?php echo $fid; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content professional-modal">
                            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-chat-square-quote me-2 text-primary"></i>Feedback Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                            <div class="modal-body">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="student-row-avatar"><?php echo h(strtoupper(substr((string)($row["name"] ?? "S"), 0, 1))); ?></span>
                                    <div class="min-w-0">
                                        <h5 class="mb-0"><?php echo h($row["name"] ?? ("#" . (int)$row["student_id"])); ?></h5>
                                        <small class="text-muted"><?php echo h($row["email"] ?? ""); ?></small>
                                    </div>
                                </div>
                                <div class="mb-3"><?php echo admin_fb_stars($row["rating"]); ?></div>
                                <div class="admin-detail-grid">
                                    <div class="admin-detail-item"><small>Course</small><strong><?php echo h($row["course"] ?? "-"); ?></strong></div>
                                    <div class="admin-detail-item"><small>Semester</small><strong><?php echo h($row["semester"] ?? "-"); ?></strong></div>
                                    <div class="admin-detail-item" style="grid-column:1/-1;"><small>Submitted</small><strong><?php echo !empty($row["created_at"]) ? h(date("d M Y, h:i A", strtotime($row["created_at"]))) : "-"; ?></strong></div>
                                    <div class="admin-detail-item" style="grid-column:1/-1;"><small>Message</small><strong style="font-weight:500;line-height:1.6;"><?php echo h((string)($row["message"] ?? "")); ?></strong></div>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button></div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php include("../app/layouts/admin_footer.php");
mysqli_close($conn); ?>
