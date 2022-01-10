<?php

require_once "private/page.php";

class Mod_index extends Module {

    function add_index($rev) {
        $c = file_get_contents(sprintf("%s/private/index_%s.txt", conf()['root'], $rev));
        $rows = string_to_rows($c);
        foreach ($rows as $row) {
            preg_match("/(\d+)-(\d+)\W(.+)/", $row, $m);
            if (count($m) != 4) {
                printf("incorrect row '%s'\n", $row);
                continue;
            }

            $start = (int)$m[1];
            $end = (int)$m[2];
            $name = $m[3];
            $id = db()->insert('index_line',
                               ['rev' => $rev,
                                'start' => $start,
                                'end' => $end,
                                'name' => addslashes($name)]);
            if ($id <= 0) {
                printf("can't insert '%s'\n", $row);
            }
        }
    }

    function content($args = []) {
//        $this->add_index('restyle');
//        exit;

        $rev = isset($args['rev']) ? $args['rev'] : 'first';

        $tpl = tpl("mod_index.html");
        $tpl->assign(0, ['form_url' => mk_link(['mod' => 'index']),
                         'mod' => 'index']);

        $revs = ['first', 'restyle'];
        foreach ($revs as $r) {
            $tpl->assign('select_revision',
                         ['rev' => $r,
                          'selected' => ($r == $rev ? 'SELECTED' : '')]);
        }

        $list = index_list($rev);
        foreach ($list as $range) {
            $page = page_by_index($rev, $range['start']);
            $tpl->assign('range', ['start' => $range['start'],
                                   'end' => $range['end'],
                                   'name' => $range['name'],
                                   'link' => mk_link(['mod' => 'page',
                                                      'id' => $page->id,
                                                      'idx' => $range['start']])]);
        }

        return $tpl->result();
    }
}
modules()->register('index', new Mod_index);
