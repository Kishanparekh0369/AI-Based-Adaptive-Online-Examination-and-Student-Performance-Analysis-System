<?php
function clean_data($data)
{
    return trim(htmlspecialchars($data));
}

function h($data)
{
    return htmlspecialchars((string)$data, ENT_QUOTES, "UTF-8");
}
?>
