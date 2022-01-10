<?php

require_once "private/page.php";

class Mod_index extends Module {
    function content($args = []) {
        $tpl = tpl("mod_index.html");
        $search_text = isset($args['search']) ? $args['search'] : NULL;
        $tpl->assign();
    }

}
modules()->register('index', new Mod_index);