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

    function serialize_as_arr() {
        return ['page_id' => $this->page->id,
                'from' => $this->from,
                'to' => $this->to,
                'rect' => $this->rect->serialize_as_arr()];
    }
}

class Item {
    function __construct($page, $rect, $name) {
        $this->page = $page;
        $this->rect = $rect;
        $this->name = $name;
    }

    function serialize_as_arr() {
        return ['page_id' => $this->page->id,
                'rect' => $this->rect->serialize_as_arr(),
                'name' => $this->name];
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

    function update_index_line($offset, $step) {
        $rc = db()->update("pages", $this->id,
                           ['offset' => $offset,
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
            $item = new Item($this, $rect, $row['name']);
            $list[] = $item;
        }
        return ['ok', $list];
    }


}

function page_by_index($rev, $idx) {
    $row = db()->query("select * from pages " .
                       "where rev = '%s' and " .
                           "index_start <= %d order by index_end desc limit 1",
                           $rev, $idx);

    if (!$row)
        return NULL;
    return new Page($row);
}

function page_by_id($id) {
    $row = db()->query("select * from pages " .
                       "where id = %d",
                           $id);
    if (!$row)
        return NULL;
    return new Page($row);
}

