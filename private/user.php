<?php
class User {
    function __construct($user_data) {
        $this->hash = $user_data['hash'];
        $this->id = $user_data['id'];
        $this->name = $user_data['name'];
    }

    function set_cookie()
    {
        setcookie('user', $this->hash,
                  time() + 60 * 60 * 24 * 365 * 3, '/');
    }

    function remove_cookie()
    {
        setcookie('user', $this->hash,
                  time() - 3600, '/');
    }
}

function user_by_login_pass($login, $pass)
{
    $query = sprintf('SELECT * FROM users WHERE `login` = "%s" ' .
                     'AND `pass` = password("%s")', $login, $pass);
    $row = db()->query("%s", $query);
    if (!$row)
      return NULL;
    return new User($row);
}

function user_by_hash($hash)
{
    $query = sprintf('SELECT * FROM users WHERE `hash` = "%s"', $hash);
    $row = db()->query($query);
    if (!$row)
      return NULL;
    return new User($row);
}

function user_by_id($id)
{
    $query = sprintf('SELECT * FROM users WHERE `id` = %d', (int)$id);
    $row = db()->query($query);
    if (!$row)
      return NULL;
    return new User($row);
}

function user_by_cookie()
{
    if(!isset($_COOKIE["user"]))
        return NULL;

    return user_by_hash($_COOKIE["user"]);
}

