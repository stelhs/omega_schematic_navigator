<?php


function mk_link($args = [])
{
    $url = sprintf("%sindex.php?", conf()['http_root_path']);
    $sep = "";
    foreach ($args as $key => $val) {
        $url .= sprintf("%s%s=%s", $sep, $key, $val);
        $sep = "&";
    }
    return $url;
}


function mk_query($args = [])
{
    $url = sprintf("%squery.php?", conf()['http_root_path']);
    $sep = "";
    foreach ($args as $key => $val) {
        $url .= sprintf("%s%s=%s", $sep, $key, $val);
        $sep = "&";
    }
    return $url;
}

function tpl($tpl_name) {
    return new strontium_tpl(sprintf("private/tpl/%s", $tpl_name),
                             conf()['default_marks'], false);
}

