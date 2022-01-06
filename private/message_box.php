<?php

class Message_box {
    /**
     * Подготовка к выводу всплывающего окна с сообщением
     * @param $block название блока из файла message_boxes.html с шаблоном сообщения
     * @param $data массив меток для шаблона с сообщением
     */
    function set($block, $data = []) {
        $_SESSION['msg_win'] = ['name' => $block, 'data' => $data];
        header('Location: ' . mk_link());
    }

    /**
     * Функция возвращает данные всплывающего окна,
     * если ранее была запущена функция message_box_display().
     * Используется в index.php
     */
    function get()
    {
        $block = $_SESSION['msg_win']["name"];
        $data = $_SESSION['msg_win']["data"];
        unset($_SESSION['msg_win']);
        return ['block' => $block, 'data' => $data];
    }


    function err() {
        $argv = func_get_args();
        $format = array_shift($argv);
        $msg = vsprintf($format, $argv);

        $this->set("message_error",
                   ['reason' => $msg]);
    }

    function ok() {
        $argv = func_get_args();
        $format = array_shift($argv);
        $msg = vsprintf($format, $argv);

        $this->set("message_success",
                   ['reason' => $msg]);
    }
}

function msg_box() {
    static $msg_box = NULL;
    if (!$msg_box)
        $msg_box = new Message_box;
    return $msg_box;
}
