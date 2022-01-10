<?php

require_once "private/page.php";

class Mod_search extends Module {
    function content($args = []) {
        $tpl = tpl("mod_search.html");
        $search_text = isset($args['search']) ? $args['search'] : NULL;
        $rev = isset($args['rev']) ? $args['rev'] : 'first';

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
                         'search_text' => $search_text]);

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

        $items = $this->search_by_item_name($search_text, $rev);

        $list = $this->search_by_item_description($search_text, $rev);
        if ($list)
            foreach ($list as $item)
                $items[] = $item;

        $index_list = $this->search_by_index($search_text, $rev);
        if (!$items and !$index_list) {
            $tpl->assign("not_found");
            return $tpl->result();
        }

        $tpl->assign("found");
        foreach ($items as $item) {
            $tpl->assign("item", ['page_id' => $item->page->id,
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

    function search_by_item_name($text, $rev = 'all') {
        if ($rev == 'all')
            return $this->items_by_query('select * from items where name like "%%%s%%"', $text);
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
                                     'where items.name like "%%%s%%" and pages.rev = "%s"',
                                     $text, $rev);
    }


    function search_by_item_description($text, $rev = 'all') {
        $rows = db()->query_list('select * from legend where description like "%%%s%%"', $text);
        if ($rows < 0 or count($rows) < 1)
            return [];

        $items = [];
        foreach ($rows as $row) {
            if ($rev == 'all') {
                $sublist = $this->items_by_query('select * from items where name = "%s"', $row['name']);
                foreach ($sublist as $item)
                    $items[] = $item;
                continue;
            }

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
                                             'where name = "%s" and pages.rev = "%s"',
                                             $row['name'], $rev);
            foreach ($sublist as $item)
                $items[] = $item;

        }
        return $items;
    }

    function search_by_index($text, $rev) {
        if ($rev == 'all') {
            $rows = db()->query_list('select * from index_line ' .
                                     'where name like "%%%s%%"', $text);
            if ($rows < 0 or count($rows) < 1)
                return [];
            return $rows;
        }


        $rows = db()->query_list('select * from index_line ' .
                                 'where name like "%%%s%%" and rev = "%s"',
                                 $text, $rev);
        if ($rows < 0 or count($rows) < 1)
            return [];
        return $rows;
    }

}
modules()->register('search', new Mod_search);