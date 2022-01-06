<?php

require_once '/usr/local/lib/php/strontium_tpl.php';
require_once '/usr/local/lib/php/database.php';
require_once '/usr/local/lib/php/common.php';

require_once "private/config.php";
require_once "private/common.php";
require_once "private/user.php";
require_once "private/message_box.php";
require_once "private/modules.php";

function main($tpl)
{

    global $tpl;
    session_start();

    $tpl->assign(NULL, ['link_location' => mk_link(['mod' => 'location']),
                        'link_catalog' => mk_link(['mod' => 'catalog']),
                        'link_new' => mk_link(['mod' => 'object']),
                        'link_boxes' => mk_link(['mod' => 'boxes']),
                        'link_absent' => mk_link(['mod' => 'absent']),
                        'link_photos' => mk_link(['mod' => 'photos']),
                        ]);

    $mbx = msg_box()->get();
    if($mbx) {
        $tpl->assign($mbx['block'], $mbx['data']);
    }

    $user = user_by_cookie();
    if (!$user) {
        $tpl->assign('user_auth');
        return;
    }

    $tpl->assign('user_logout',
                ['link_logout' => mk_query(['method' => 'user_logout'])]);

    $mod_name = "page";
    if(isset($_GET['mod']))
       $mod_name = $_GET['mod'];

    $mod = modules()->by_name($mod_name);
    if (!$mod) {
        $tpl->assign('module',
                     ['module_content' => sprintf('Module %s not found', $mod->name)]);
        return;
    }

    $tpl->assign('module_content',
                 ['content' => $mod->content($_GET)]);

}

$tpl = tpl("skeleton.html");
main($tpl);
echo $tpl->result();
header('Cache-Control: no-store');

