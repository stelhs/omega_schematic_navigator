
/*
    Функция формирует асинхронный запрос на сервер.
    @param method - название запрашиваемой операции
    @param args - ассоц. массив аргументов (key => val)
    @param func - функция обработчик запроса, вызывается
                            при успешной обработке запроса
*/
function mk_query(method, args, func, async)
{
    args['method'] = method;
    return $.ajax({
          type: "POST",
          url: query_url,
          data: args,
          success: func,
          async: async
              }).responseText;
}

teamplates = [];
def_marks = [];
mk_query('load_def_marks', {},
         function(data) {eval('def_marks = ' + data)});

function load_tpl(name) {
    teamplates[name] = mk_query('load_tpl', {'name': name}, false, false);
}

function tpl_open(name)
{
    t = new strontium_tpl();
    t.open(teamplates[name], def_marks);
    return t;
}



function dec_input(id, step, min)
{
    var o = $$(id);
    if (!o.value.length)
        return;
    var v = parseInt(o.value);
    v -= step;
    if (v < min)
        v = min;
    o.value = v;
    o.style.color = 'red';
    setTimeout(function(){ o.style.color = 'lightgray'; o.style.border = "1px solid yellow" }, 300);
}

function inc_input(id, step, max)
{
    var o = $$(id);
    var val = o.value;
    if (!o.value.length)
        val = 0;
    var v = parseInt(val);
    v += step;
    if (max > 0 && v > max)
        v = max;
    o.value = v;
    o.style.color = 'red';
    setTimeout(function(){ o.style.color = 'lightgray'; o.style.border = "1px solid yellow" }, 300);
}

function switch_view(div_id)
{
    var div = $$(div_id);
    if (div.style.display != 'none') {
        div.style.display = 'none';
        return false;
    }

    div.style.display = 'inline-block';
    return true;
}

function hide_view(div_id)
{
    $$(div_id).style.display = 'none';
}

function show_view(div_id)
{
    $$(div_id).style.display = 'inline-block';
}

