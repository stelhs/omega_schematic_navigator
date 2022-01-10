<?php


require_once "private/mod_page.php";
require_once "private/mod_search.php";
require_once "private/mod_index.php";

class Module {
    public $name = "undefined";

    function content($args = [])
    {
        return sprintf("%s: No module content", $this->name);
    }

    function query($args)
    {
        $reason = sprintf("module '%s' is not supported queries", $this->name);
        message_box_set("message_error", ['reason' => $reason]);
        return mk_link();
    }
}

class Modules {
    function register($name, $module)
    {
        $this->modules_list[$name] = $module;
        $module->name = $name;
    }

    function by_name($mod_name)
    {
        if (!isset($this->modules_list[$mod_name]))
            return NULL;
        return $this->modules_list[$mod_name];
    }
}


function modules()
{
    static $modules = NULL;
    if (!$modules)
        $modules = new Modules;
    return $modules;
}

