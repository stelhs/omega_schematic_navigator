<?php

require_once "private/page.php";

class Mod_search extends Module {
    function content($args = []) {
        $tpl = tpl("mod_search.html");
        $search_text = isset($args['search']) ? $args['search'] : NULL;
        $rev = isset($args['rev']) ? $args['rev'] : 'all';
        $layout_only = $args['layout_only'] == 1 ? TRUE : FALSE;

        preg_match("/^\d+$/", $search_text, $m);
        if (count($m) > 0) {
            $val = (int)$search_text;
            if ($val >= 100) {
                $page = page_by_index('first', $val);
                header('Location: ' . mk_link(['mod' => 'page',
                                               'id' => $page->id,
                                               'idx' => $val]));
                return "";
            }

            header('Location: ' . mk_link(['mod' => 'page',
                                           'id' => $val]));
            return "";
        }

        preg_match("/^_(\d+)$/", $search_text, $m);
        if (count($m) > 1) {
            $val = (int)$m[1];
            $page = page_by_index('restyle', $val);
            header('Location: ' . mk_link(['mod' => 'page',
                                           'id' => $page->id,
                                           'idx' => $val]));
            return "";
        }

        $tpl->assign(0, ['form_url' => mk_link(['mod' => 'page']),
                         'mod' => 'search',
                         'search_text' => $search_text,
                         'layout_only_checkded' => ($layout_only ? 'CHECKED' : '')]);

        $revs = ['all', 'first', 'restyle'];
        foreach ($revs as $r) {
            $tpl->assign('select_revision',
                         ['rev' => $r,
                          'selected' => ($r == $rev ? 'SELECTED' : '')]);
        }

        if (!$search_text) {
            $tpl->assign("not_found");
            return $tpl->result();
        }

        $exact = FALSE;
        if ($search_text[strlen($search_text) - 1] == '\\') {
            $search_text = str_replace('\\', '', $search_text);
            $exact = TRUE;
        }


        $items = $this->search_by_item_name($search_text, $rev, $exact, $layout_only);

        $list = $this->search_by_item_description($search_text, $rev, $exact, $layout_only);
        if ($list)
            foreach ($list as $item)
                $items[] = $item;

        $index_list = $this->search_by_index($search_text, $rev, $exact);
        if (!$items and !$index_list) {
            $tpl->assign("not_found");
            return $tpl->result();
        }

        $tpl->assign("found");
        foreach ($items as $item) {
            $index = '';
            $idx = $item->index_num();
            if ($idx)
                $index = index_description($item->page->rev, $idx);

            $tpl->assign("item", ['page_id' => $item->page->id,
                                  'index' => $index,
                                  'name' => $item->name,
                                  'description' => $item->description(),
                                  'link' => mk_link(['mod' => 'page',
                                                     'id' => $item->page->id,
                                                     'item' => $item->id])]);
        }

        if ($index_list)
            foreach ($index_list as $range) {
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

    function items_by_query() {
        $argv = func_get_args();
        $format = array_shift($argv);
        $query = vsprintf($format, $argv);

        $rows = db()->query_list("%s", $query);
        if ($rows < 0 or count($rows) < 1)
            return [];

        $items = [];
        foreach ($rows as $row) {
            $page = page_by_id($row['page_id']);
            $item = new Item($page,
                             new Rect($row['rect_left'], $row['rect_top'],
                                      $row['rect_width'], $row['rect_height']),
                             $row['name'], $row['id']);
            $items[] = $item;
        }
        return $items;
    }

    function search_by_item_name($text, $rev = 'all', $exact = FALSE, $layout_only = FALSE) {
        $condition = $exact == FALSE ? sprintf('"%%%s%%"', $text) : sprintf('"%s"', $text);
        $layout_only_condition = $layout_only == FALSE ? '' : ' and pages.index_start = 0 ';
        $rev_condition = $rev == 'all' ? '' : sprintf(' and pages.rev = "%s" ', $rev);

        return $this->items_by_query('select ' .
                                     'items.id as id, ' .
                                     'items.name as name, ' .
                                     'items.page_id as page_id, ' .
                                     'items.rect_left as rect_left, ' .
                                     'items.rect_top as rect_top, ' .
                                     'items.rect_width as rect_width, ' .
                                     'items.rect_height as rect_height ' .
                                     'from items ' .
                                     'left join pages on pages.id = items.page_id ' .
                                     'where items.name like %s %s %s',
                                     $condition, $rev_condition, $layout_only_condition);
    }


    function search_by_item_description($text, $rev = 'all', $exact = FALSE, $layout_only = FALSE) {
        $condition = $exact == FALSE ? sprintf('"%%%s%%"', $text) : sprintf('"%s"', $text);
        $layout_only_condition = $layout_only == FALSE ? '' : ' and pages.index_start = 0 ';
        $rev_condition = $rev == 'all' ? '' : sprintf(' and pages.rev = "%s" ', $rev);
        $rows = db()->query_list('select * from legend where description like %s', $condition);
        if ($rows < 0 or count($rows) < 1)
            return [];

        $items = [];
        foreach ($rows as $row) {
            $sublist = $this->items_by_query('select ' .
                                             'items.id as id, ' .
                                             'items.name as name, ' .
                                             'items.page_id as page_id, ' .
                                             'items.rect_left as rect_left, ' .
                                             'items.rect_top as rect_top, ' .
                                             'items.rect_width as rect_width, ' .
                                             'items.rect_height as rect_height ' .
                                             'from items ' .
                                             'left join pages on pages.id = items.page_id ' .
                                             'where name = "%s" %s %s',
                                             $row['name'], $rev_condition, $layout_only_condition);
            foreach ($sublist as $item)
                $items[] = $item;

        }
        return $items;
    }

    function search_by_index($text, $rev, $exact = FALSE) {
        $condition = $exact == FALSE ? sprintf('"%%%s%%"', $text) : sprintf('"%s"', $text);
        if ($rev == 'all') {
            $rows = db()->query_list('select * from index_line ' .
                                     'where name like %s', $condition);
            if ($rows < 0 or count($rows) < 1)
                return [];
            return $rows;
        }


        $rows = db()->query_list('select * from index_line ' .
                                 'where name like %s and rev = "%s"',
                                 $condition, $rev);
        if ($rows < 0 or count($rows) < 1)
            return [];
        return $rows;
    }

}
modules()->register('search', new Mod_search);