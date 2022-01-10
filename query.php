<?php
require_once '/usr/local/lib/php/database.php';
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/strontium_tpl.php';

require_once "private/config.php";
require_once "private/modules.php";
require_once "private/common.php";
require_once "private/user.php";
require_once "private/message_box.php";

function process_query($args)
{
    if (!isset($args['method'])) {
        msg_box()->err("field 'method' not found");
        return;
    }

    if ($args['method'] == 'user_auth') {
        $user = user_by_login_pass($args['login'], $args['pass']);
        if (!$user) {
            msg_box()->set("message_auth_error");
            return;
        }
        $user->set_cookie();
        header('Location: ' . mk_link());
    }

    switch ($args['method']) {
    case 'user_logout':
        $user = user_by_cookie();
        if (!$user)
            return;

        $user->remove_cookie();
        header('Location: ' . mk_link());
        return;

    /* AJAX requests */
    case 'load_def_marks':
        echo json_encode(conf()['default_marks']);
        return;

    case 'load_tpl':
        echo file_get_contents(sprintf('%s/private/tpl/%s.html',
                                 conf()['root'],
                                 $args['name']));
        return;
    }

    if (!$args['mod']) {
        header('Location: ' . mk_link());
        return;
    }

    $mod = modules()->by_name($args['mod']);
    if (!$mod) {
        msg_box()->err("Module %s is not found", $args['mod']);
        return;
    }
    $ret = $mod->query($args);
    if (!$ret)
        return;
    header('Location: ' . $ret);
}

session_start();
$args = array_merge($_GET, $_POST);
process_query($args);
