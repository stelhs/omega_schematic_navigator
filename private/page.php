<?php

class Rect {
    function __construct($left, $top, $width, $height) {
        $this->left = $left;
        $this->top = $top;
        $this->width = $width;
        $this->height = $height;
        $this->right = $left + $width;
        $this->bottom = $top + $height;
    }

    function serialize_as_arr() {
        return ['left' => $this->left,
                'top' => $this->top,
                'width' => $this->width,
                'height' => $this->height];
    }
}

class Link_point {
    function __construct($page, $rect, $from, $to) {
        $this->page = $page;
        $this->rect = $rect;
        $this->from = $from;
        $this->to = $to;
    }

    function description_to() {
        return index_description($this->page->rev, $this->to);
    }

    function serialize_as_arr() {
        return ['page_id' => $this->page->id,
                'from' => $this->from,
                'to' => $this->to,
                'rect' => $this->rect->serialize_as_arr(),
                'description' => $this->description_to()];
    }
}

class Item {
    function __construct($page, $rect, $name, $id) {
        $this->page = $page;
        $this->rect = $rect;
        $this->name = trim($name);
        $this->id = $id;
    }

    function description() {
        $row = db()->query('select * from legend ' .
                           'where name = "%s"', $this->name);
        if ($row > 0 and isset($row['description']))
            return $row['description'];

        $row = db()->query('select * from abbreviations ' .
                           'where name = "%s"', $this->name);
        if ($row > 0 and isset($row['description']))
            return $row['description'];
        return "";
    }

    function linked_items() {
        $rows = db()->query_list('select * from items ' .
                            'where name = "%s" and id != %d',
                            $this->name, $this->id);
        if ($rows < 0 or count($rows) < 1)
            return NULL;

        $list = [];
        foreach ($rows as $row) {
            $page = page_by_id($row['page_id']);
            $item = new Item($page, new Rect($row['rect_left'], $row['rect_top'],
                                             $row['rect_width'], $row['rect_height']),
                                             $row['name'], $row['id']);
            $list[] = $item;
        }
        return $list;
    }

    function index_num() {
        return $this->page->index_by_coordinate($this->rect->left + ($this->rect->right - $this->rect->left) / 2);
    }

    function serialize_as_arr() {
        $linked_items = [];

        $items = $this->linked_items();
        if ($items)
            foreach ($items as $item) {
                $linked_items[] = ['page_id' => $item->page->id,
                                   'rev' => $item->page->rev,
                                   'link' => mk_link(['mod' => 'page',
                                                      'id' => $item->page->id,
                                                      'item' => $item->id])];
            }

        return ['page_id' => $this->page->id,
                'rect' => $this->rect->serialize_as_arr(),
                'name' => $this->name,
                'id' => $this->id,
                'description' => $this->description(),
                'linked_items' => $linked_items];
    }
}

class Page {
    function __construct($page_data) {
        $this->id = $page_data['id'];
        $this->rev = $page_data['rev'];
        $this->filename = $page_data['filename'];
        $this->width = $page_data['width'];
        $this->height = $page_data['height'];
        $this->idx_start = $page_data['index_start'];
        $this->idx_end = $page_data['index_end'];
        $this->offset = $page_data['offset'];
        $this->step = (float)$page_data['step'];
    }

    function prev() {
        $row = db()->query("select * from pages " .
                           "where id < %d order by id desc limit 1",
                               $this->id);
        if (!$row)
            return NULL;
        return new Page($row);
    }

    function next() {
        $row = db()->query("select * from pages " .
                           "where id > %d order by id asc limit 1",
                               $this->id);
        if (!$row)
            return NULL;
        return new Page($row);
    }

    function remove_items() {
        db()->query("delete from items where page_id = %d", $this->id);
    }

    function remove_link_points() {
        db()->query("delete from link_points where page_id = %d", $this->id);
    }

    function update_index_line($start_index, $end_index, $offset, $step) {
        $rc = db()->update("pages", $this->id,
                           ['index_start' => $start_index,
                            'index_end' => $end_index,
                            'offset' => $offset,
                            'step' => $step]);

        if ($rc < 0)
            return ['error', "Can't update index line"];
        $this->offset = $offset;
        $this->step = $step;
        return ['ok', ''];
    }

    function add_item($rect, $name) {
        $rc = db()->insert("items",
                           ['name' => $name,
                            'page_id' => $this->id,
                            'rect_left' => $rect->left,
                            'rect_top' => $rect->top,
                            'rect_width' => $rect->width,
                            'rect_height' => $rect->height]);
        if ($rc <= 0)
            return ['error', sprintf("Can't insert into items. Item '%s'", $name)];
        return ['ok', ''];
    }

    function find_link_point_from($rect) {
        $left = ($rect->left - $this->offset);
        $right = ($rect->right - $this->offset);
        $center = $left + $rect->width / 2;

        $x = 0;
        $entries = [];
        for ($idx = $this->idx_start; $idx <= $this->idx_end; $idx ++) {
            if ($x >= $left and $x <= $right)
                $entries[] = [$idx, $x];
            $x += $this->step;
        }

        if (!count($entries))
            return NULL;

        if (count($entries) == 1)
             return $entries[0][0];

        $min_delta = 2**32; // Maximum possible integer
        $nearest_idx = 0;
        foreach ($entries as $entry) {
            list($idx, $x) = $entry;
            $delta = abs($center - $x);
            if ($delta < $min_delta) {
                $min_delta = $delta;
                $nearest_idx = $idx;
            }
        }
        return $nearest_idx;
    }

    function add_link_point($rect, $to) {
        $from = $this->find_link_point_from($rect);
        if (!$from)
            return ['error', sprintf("Can't recognize 'form' of link point %s", $to)];

        $rc = db()->insert("link_points",
                           ['page_id' => $this->id,
                            'rect_left' => $rect->left,
                            'rect_top' => $rect->top,
                            'rect_width' => $rect->width,
                            'rect_height' => $rect->height,
                            'index_from' => $from,
                            'index_to' => $to]);
        if ($rc <= 0)
            return ['error', sprintf("Can't insert into link_points. to:%d, from:%d",
                                      $from, $to)];
        return ['ok', ''];
    }

    function link_points() {
        $rows = db()->query_list("select * from link_points where page_id = %d", $this->id);
        if ($rows < 0)
            return ['error', "Can't select from link_points"];

        if (!count($rows))
            return ['ok', []];

        $list = [];
        foreach ($rows as $row) {
            $rect = new Rect($row['rect_left'], $row['rect_top'],
                             $row['rect_width'], $row['rect_height']);
            $lp = new Link_point($this, $rect, $row['index_from'], $row['index_to']);
            $list[] = $lp;
        }
        return ['ok', $list];
    }

    function items() {
        $rows = db()->query_list("select * from items where page_id = %d", $this->id);
        if ($rows < 0)
            return ['error', "Can't select from items"];

        if (!count($rows))
            return ['ok', []];

        $list = [];
        foreach ($rows as $row) {
            $rect = new Rect($row['rect_left'], $row['rect_top'],
                             $row['rect_width'], $row['rect_height']);
            $item = new Item($this, $rect, $row['name'], $row['id']);
            $list[] = $item;
        }
        return ['ok', $list];
    }

    function index_by_coordinate($x)
    {
        if (!$this->idx_start)
            return NULL;
        return ceil((($x - $this->offset) / $this->step) + $this->idx_start);
    }


}

function page_by_index($rev, $idx) {
    $row = db()->query("select * from pages " .
                       "where rev = '%s' and " .
                           "index_start <= %d order by index_end desc limit 1",
                           $rev, $idx);

    if (!$row)
        return NULL;

    $page = new Page($row);
    if ($idx < $page->idx_start or $idx > $page->idx_end)
        return NULL;
    return $page;
}

function page_by_id($id) {
    static $pages = NULL;
    if (!$pages) {
        $pages = [];
        $rows = db()->query_list("select * from pages");
        foreach ($rows as $row)
            $pages[$row['id']] = new Page($row);
    }

    if (isset($pages[$id]))
        return $pages[$id];
    return NULL;
}

function index_list($rev) {
    $rows = db()->query_list('select * from index_line where rev = "%s"', $rev);
    return $rows;
}

function index_description($rev, $idx) {
    $row = db()->query('select name from index_line ' .
                        'where rev = "%s" and ' .
                        '%d >= start and %d <= end',
                        $rev, $idx, $idx);
    if ($row < 0 or !isset($row['name']))
        return "";

    return $row['name'];
}


