<?php
$field_id = $modal_row["id"];
$field_subjects = isset($subjects_list) ? $subjects_list : [];
if (!$field_subjects) {
    $field_subject_query = mysqli_query($conn, "SELECT * FROM subjects ORDER BY subject_name");
    while ($field_subject_query && $field_subject = mysqli_fetch_assoc($field_subject_query)) {
        $field_subjects[] = $field_subject;
    }
}
?>
<div class="row g-3">
    <div class="col-md-4 form-floating">
        <select id="questionSubject<?php echo $field_id; ?>" name="subject_id" class="form-select" required>
            <option value="">Select Subject</option>
            <?php foreach ($field_subjects as $field_subject) { ?>
                <option value="<?php echo $field_subject["id"]; ?>" <?php if ($modal_row["subject_id"] == $field_subject["id"]) {
                                                                        echo "selected";
                                                                    } ?>><?php echo h($field_subject["subject_name"]); ?></option>
            <?php } ?>
        </select>
        <label for="questionSubject<?php echo $field_id; ?>">Subject</label>
    </div>
    <div class="col-md-4 form-floating">
        <select id="questionLanguage<?php echo $field_id; ?>" name="language" class="form-select" required>
            <?php foreach (array("English", "Hindi", "Gujarati") as $language_option) { ?>
                <option value="<?php echo $language_option; ?>" <?php if (isset($modal_row["language"]) && $modal_row["language"] == $language_option) { echo "selected"; } ?>><?php echo $language_option; ?></option>
            <?php } ?>
        </select>
        <label for="questionLanguage<?php echo $field_id; ?>">Language</label>
    </div>
    <div class="col-md-4 form-floating">
        <input id="questionText<?php echo $field_id; ?>" name="question" value="<?php echo h($modal_row["question"]); ?>" class="form-control" placeholder="Question" required>
        <label for="questionText<?php echo $field_id; ?>">Question</label>
    </div>
    <div class="col-md-3 form-floating"><input id="optionA<?php echo $field_id; ?>" name="option_a" value="<?php echo h($modal_row["option_a"]); ?>" class="form-control" placeholder="Option A" required><label for="optionA<?php echo $field_id; ?>">Option A</label></div>
    <div class="col-md-3 form-floating"><input id="optionB<?php echo $field_id; ?>" name="option_b" value="<?php echo h($modal_row["option_b"]); ?>" class="form-control" placeholder="Option B" required><label for="optionB<?php echo $field_id; ?>">Option B</label></div>
    <div class="col-md-3 form-floating"><input id="optionC<?php echo $field_id; ?>" name="option_c" value="<?php echo h($modal_row["option_c"]); ?>" class="form-control" placeholder="Option C" required><label for="optionC<?php echo $field_id; ?>">Option C</label></div>
    <div class="col-md-3 form-floating"><input id="optionD<?php echo $field_id; ?>" name="option_d" value="<?php echo h($modal_row["option_d"]); ?>" class="form-control" placeholder="Option D" required><label for="optionD<?php echo $field_id; ?>">Option D</label></div>
    <div class="col-md-4 form-floating">
        <select id="correctAnswer<?php echo $field_id; ?>" name="correct_answer" class="form-select">
            <option <?php if ($modal_row["correct_answer"] == "A") {
                        echo "selected";
                    } ?>>A</option>
            <option <?php if ($modal_row["correct_answer"] == "B") {
                        echo "selected";
                    } ?>>B</option>
            <option <?php if ($modal_row["correct_answer"] == "C") {
                        echo "selected";
                    } ?>>C</option>
            <option <?php if ($modal_row["correct_answer"] == "D") {
                        echo "selected";
                    } ?>>D</option>
        </select>
        <label for="correctAnswer<?php echo $field_id; ?>">Correct Answer</label>
    </div>
    <div class="col-md-4 form-floating">
        <select id="difficulty<?php echo $field_id; ?>" name="difficulty" class="form-select">
            <option <?php if ($modal_row["difficulty"] == "Easy") {
                        echo "selected";
                    } ?>>Easy</option>
            <option <?php if ($modal_row["difficulty"] == "Medium") {
                        echo "selected";
                    } ?>>Medium</option>
            <option <?php if ($modal_row["difficulty"] == "Hard") {
                        echo "selected";
                    } ?>>Hard</option>
        </select>
        <label for="difficulty<?php echo $field_id; ?>">Difficulty</label>
    </div>
    <div class="col-md-4 form-floating"><input id="marks<?php echo $field_id; ?>" type="number" name="marks" value="<?php echo h($modal_row["marks"]); ?>" class="form-control" placeholder="Marks" min="1" required><label for="marks<?php echo $field_id; ?>">Marks</label></div>
    <div class="col-md-12 form-floating">
        <input id="keywords<?php echo $field_id; ?>" name="keywords" value="<?php echo isset($modal_row["keywords"]) ? h($modal_row["keywords"]) : ""; ?>" class="form-control" placeholder="Enter keywords separated by commas">
        <label for="keywords<?php echo $field_id; ?>">Keywords (comma-separated)</label>
    </div>
</div>
