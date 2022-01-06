<?php

require_once "private/page.php";

class Mod_page extends Module {

    function content($args = [])
    {
        $tpl = tpl("mod_page.html");
        $tpl->assign();

        $id = isset($args['id']) ? $args['id'] : 0;
        $idx = isset($args['idx']) ? $args['idx'] : 100;
        $rev = isset($args['rev']) ? $args['rev'] : 'first';

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
        $tpl->assign('schematic_image',
                     ['sch_img' => $page->filename,
                      'id' => $page->id,
                      'original_width' => $page->width,
                      'original_height' => $page->height,
                      'def_width' => $def_width,
                      'def_height' => $def_height,
                      'index_start' => $page->idx_start,
                      'index_end' => $page->idx_end,
                      'offset' => $page->offset,
                      'step' => $page->step]);
        return $tpl->result();
    }

    function query($args)
    {
        switch($args['method']) {
        case 'save_page':
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
            $ret = $page->update_index_line($index_line['offset'], $index_line['step']);
            if ($ret[0] != 'ok')
                return $ret;
        }

        if (count($areas)) {
            foreach ($areas as $area) {
                $rect = new Rect($area['rect']['left'], $area['rect']['top'],
                                 $area['rect']['width'], $area['rect']['height']);

                if (is_numeric($area['name'][0])) {
                    $ret = $page->add_link_point($rect, (int)$area['name']);
                    if ($ret[0] != 'ok')
                        return $ret;
                    continue;
                }

                $ret = $page->add_item($rect, $area['name']);
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
            $lp_list[] = $lp->serialize_as_arr();
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
