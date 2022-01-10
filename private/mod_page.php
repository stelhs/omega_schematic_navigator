<?php

require_once "private/page.php";

class Mod_page extends Module {

    function load_images() {
        $files = scandir(sprintf("%s/i/pages", conf()['root']));

        foreach ($files as $file) {
            if ($file == '.' or $file == '..')
                continue;
            $fname = sprintf("%s/i/pages/%s", conf()['root'], $file);
            $inf = getimagesize($fname);
            $id = db()->insert('pages',
                         ['rev' => 'first',
                          'filename' => $file,
                          'width' => $inf[0],
                          'height' => $inf[1]]);

            printf("added %s, %d\n", $file, $id);
        }
    }

    function load_legend() {
        $c = file_get_contents(sprintf("%s/private/legend.txt", conf()['root']));
        $rows = string_to_rows($c);
        foreach ($rows as $row) {
            $pos = strpos($row, '-');
            $name = trim(substr($row, 0, $pos));
            $desc = trim(substr(trim(substr($row, $pos)), 1));

            if (is_numeric($name[0])) {
                printf("incorrect name '%s'\n", $row);
                continue;
            }

            if ($desc[-1] == ';')
                $desc = substr($desc, 0, -1);

            $id = db()->insert('legend', ['name' => $name,
                                          'description' => $desc]);
            if ($id <= 0) {
                printf("can't insert '%s'\n", $row);
            }
        }
    }

    function load_abbreviations() {
        $c = file_get_contents(sprintf("%s/private/abbreviations.txt", conf()['root']));
        $rows = string_to_rows($c);
        foreach ($rows as $row) {
            $pos = strpos($row, '-');
            $name = trim(substr($row, 0, $pos));
            $desc = trim(substr(trim(substr($row, $pos)), 1));

            if (is_numeric($name[0])) {
                printf("incorrect name '%s'\n", $row);
                continue;
            }

            if ($desc[-1] == ';')
                $desc = substr($desc, 0, -1);

            $id = db()->insert('abbreviations', ['name' => $name,
                                                 'description' => $desc]);
            if ($id <= 0) {
                printf("can't insert '%s'\n", $row);
            }
        }
    }

    function content($args = [])
    {
     //   $this->load_images();
       // exit;

      //  $this->load_abbreviations();

        $tpl = tpl("mod_page.html");
        $tpl->assign();

        $id = isset($args['id']) ? $args['id'] : 1;
        $idx = isset($args['idx']) ? $args['idx'] : 0;
        $from_idx = isset($args['from']) ? $args['from'] : NULL;
        $highlight_item = isset($args['item']) ? $args['item'] : NULL;
        $rev = isset($args['rev']) ? $args['rev'] : 'first';
        $mode = isset($args['mode']) ? $args['mode'] : 'navigator';

        $user = user_by_cookie();
        if ($mode == 'editor' and !$user) {
            $tpl->assign("not_access");
            return $tpl->result();
        }

        if ($id)
            $page = page_by_id($id);
        else
            $page = page_by_index($rev, $idx);

        if (!$page) {
            $tpl->assign('not_found', ['idx' => $idx]);
            return $tpl->result();
        }

        $prev_page = $page->prev();
        if ($prev_page) {
            $tpl->assign("prev_page",
                         ['link' => mk_link(['mod' => 'page', 'id' => $prev_page->id])]);
        }

        $next_page = $page->next();
        if ($next_page) {
            $tpl->assign("next_page",
                         ['link' => mk_link(['mod' => 'page', 'id' => $next_page->id])]);
        }

        $tpl->assign("page_found",
                     ['page_id' => $page->id,
                      'rev' => $page->rev,
                      'index_start' => $page->idx_start,
                      'index_end' => $page->idx_end]);

        $def_width = 1800;
        $def_height = $page->height * ($def_width / $page->width);

        $schInfo = ['sch_img' => $page->filename,
                    'id' => $page->id,
                    'original_width' => $page->width,
                    'original_height' => $page->height,
                    'def_width' => $def_width,
                    'def_height' => $def_height,
                    'index_start' => $page->idx_start,
                    'index_end' => $page->idx_end,
                    'offset' => $page->offset,
                    'step' => $page->step];

        switch ($mode) {
        case 'editor':
            $link_cancel = mk_link(['mod' => 'page',
                                    'mode' => 'navigator',
                                    'id' => $id]);
            $tpl->assign("editor_buttons", ['link_cancel' => $link_cancel]);
            $tpl->assign('editor', $schInfo);
            break;

        case 'navigator':
            $link = mk_link(['mod' => 'page',
                             'mode' => 'editor',
                             'id' => $id]);
            if ($user)
                $tpl->assign("button_edit", ['link' => $link]);
            $tpl->assign('navigator', $schInfo);

            if ($from_idx)
                $tpl->assign("show_link_point_selector",
                             ['from_index' => $from_idx,
                              'to_index' => $idx]);
            else  if ($idx)
                $tpl->assign("show_index_selector", ['index' => $idx]);

            if ($highlight_item)
                $tpl->assign("show_item_selector", ['id' => $highlight_item]);
            break;
        }


        return $tpl->result();
    }

    function query($args)
    {
        $user = user_by_cookie();

        switch($args['method']) {
        case 'save_page':
            if (!$user) {
                echo json_encode(['result' => 'error',
                                  'reason' => 'Not autorized']);
                return;
            }

            $page_id = $args['id'];
            $areas = json_decode($args['areas'], true);
            $index_line = json_decode($args['index_line'], true);
            list($result, $reason) = $this->save($page_id, $areas, $index_line);
            echo json_encode(['result' => $result,
                              'reason' => $reason]);
            return;

        case 'load_page':
            $page_id = $args['id'];
            $ret = $this->load($page_id);
            echo json_encode($ret);
            return;
        }

    }

    function save($page_id, $areas, $index_line)
    {
        $page = page_by_id($page_id);
        if (!$page)
            return ['error', sprintf("page %s is not found", $page_id)];

        $page->remove_link_points();
        $page->remove_items();

        if (is_array($index_line) and count($index_line)) {
            $ret = $page->update_index_line($index_line['start_index'], $index_line['end_index'],
                                            $index_line['offset'], $index_line['step']);
            if ($ret[0] != 'ok')
                return $ret;
        }

        if (count($areas)) {
            foreach ($areas as $area) {
                $rect = new Rect($area['rect']['left'], $area['rect']['top'],
                                 $area['rect']['width'], $area['rect']['height']);
                $name = trim($area['name']);
                if (is_numeric($name[0])) {
                    $ret = $page->add_link_point($rect, (int)$name);
                    if ($ret[0] != 'ok')
                        return $ret;
                    continue;
                }

                $ret = $page->add_item($rect, $name);
                if ($ret[0] != 'ok')
                    return $ret;
            }
        }
        return ['ok', ""];
    }

    function load($page_id)
    {
        $page = page_by_id($page_id);
        if (!$page)
            return ['result' => 'error',
                    'reason' => sprintf("page %s is not found", $page_id)];

        $ret = $page->link_points();
        if ($ret[0] != 'ok')
            return ['result' => 'error',
                    'reason' => $ret[1]];

        $lp_list = [];
        foreach ($ret[1] as $lp) {
            $lps = $lp->serialize_as_arr();
            $lps['link'] = "";

            $p = page_by_index($page->rev, $lp->to);
            if ($p)
                $lps['link'] = mk_link(['mod' => 'page',
                                        'mode' => 'navigator',
                                        'id' => $p->id,
                                        'idx' => $lp->to,
                                        'from' => $lp->from]);
            $lp_list[] = $lps;
        }

        $ret = $page->items();
        if ($ret[0] != 'ok')
            return ['result' => 'error',
                    'reason' => $ret[1]];

        $items_list = [];
        foreach ($ret[1] as $item) {
            $items_list[] = $item->serialize_as_arr();
        }

        return ['result' => 'ok',
                'link_points' => $lp_list,
                'items_list' => $items_list];
    }
}

modules()->register('page', new Mod_page);
