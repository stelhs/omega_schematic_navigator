<?php
ini_set('error_reporting', E_ERROR);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


function conf()
{
    $path = parse_json_config('private/.path.json');
    $http_root = $path['http_root']; // Внутренний путь к файлам
    $root = $path['root']; // Абсолютный пусть к файлам

    return ['default_marks' => ['http_root' => $http_root,
                                'http_css' => $http_root.'css/',
                                'http_img' => $http_root.'i/',
                                'http_js' => $http_root.'js/',
                                'time' => time(),
                                'query_url' => $http_root.'query.php'],
                                'http_root' => $http_root,
                                'root' => $root];
}

function conf_db()
{
    static $config = NULL;
    if (!is_array($config))
        $config = parse_json_config('private/.database.json');

    return $config;
}

?>
