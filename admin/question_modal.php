<?php $modal_row = $row; ?>
<div class="modal fade" id="editQuestion<?php echo $row["id"]; ?>" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content professional-modal">
            <div class="modal-header">
                <h5 class="modal-title">Edit Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_id" value="<?php echo $row["id"]; ?>">
                <?php include("question_form_fields.php"); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-gradient" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>
