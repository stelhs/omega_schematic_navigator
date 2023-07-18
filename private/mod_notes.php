<?php

class Mod_notes extends Module {

    function content($args = []) {
        $user = user_by_cookie();
        if (!$user)
            return "";

        $row = db()->query('select text from user_notes where user_id = %d', $user->id);
        $text = $row['text'];

        $tpl = tpl("mod_notes.html");
        $tpl->assign(0, ['form_url' => mk_query(['mod' => 'notes']),
                         'mod' => 'notes',
                         'text' => $text]);


        return $tpl->result();
    }

    function query($args)
    {
        $user = user_by_cookie();
        if (!$user) {
            return;
        }

        switch($args['method']) {
        case 'save':
            $this->save($user, $args['text']);
            header('location: '.mk_link(['mod' => 'notes']));
            return;
        }

    }

    function save($user, $new_text)
    {
        $row = db()->query('select text, text_prev from user_notes where user_id = %d', $user->id);
        if (!$row)
            db()->query('insert into user_notes set user_id = %d', $user->id);

        $last_text = stripslashes($row['text']);
        if ($new_text == $last_text) {
            return;
        }

        db()->query('update user_notes set text = "%s", text_prev = "%s" where user_id = %d',
                    addslashes($new_text), addslashes($last_text), $user->id);
    }

}

modules()->register('notes', new Mod_notes);
