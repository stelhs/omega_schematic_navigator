/*
*	Класс tpl для работы с шаблонами
*	Методы :
*		open(шаблон) - открыть шаблон
*		assign(block_name, data) - Заполнить метки данными data в блоке block_name
*		make_result() - Возвращает заполненный данными шаблон
*	By Kurochkin Michail 11.03.2009
*/

function strontium_tpl()
{
	this.source_content = '';
	this.result_content = '';
	this.open = open;
	this.assign = assign;
	this.result = result;
}

function open(source_content, def_marks)
{
	this.source_content = source_content;
	this.result_content = source_content;
	this.def_marks = def_marks;
	var matches = 0;
	var reg = new RegExp();
	
	if(this.result_content.search(/<!--[ ]+START[ ]+BLOCK[ ]+:[ ]+([a-z0-9_]+)[ ]+-->/) != -1)
	{
		while(matches = this.result_content.match(/<!--[ ]+START[ ]+BLOCK[ ]+:[ ]+([a-z0-9_]+)[ ]+-->/))
		{
			reg.compile("<!--[ ]+START[ ]+BLOCK[ ]+:[ ]+" + matches[1] + "[ ]+-->([\\s\\S]*)<!--[ ]+END[ ]+BLOCK[ ]+:[ ]+" + matches[1] + "[ ]+-->");
			this.result_content =	this.result_content.replace(reg, '<<-' + matches[1] + '->>');
		 }
	}
}

function assign(block_name, data = {})
{
	var reg = new RegExp();
	var matches;
	var content;

	if(block_name)
	{
		reg.compile("<!--[ ]+START[ ]+BLOCK[ ]+:[ ]+" + block_name + "[ ]+-->([\\s\\S]*)<!--[ ]+END[ ]+BLOCK[ ]+:[ ]+" + block_name + "[ ]+-->");
		matches = this.source_content.match(reg);
		content = matches[1];
	}
	else
		content = this.source_content;

	while(matches = content.match(/<!--[ ]+START[ ]+BLOCK[ ]+:[ ]+([a-z0-9_]+)[ ]+-->/))
	{
		reg.compile("<!--[ ]+START[ ]+BLOCK[ ]+:[ ]+" + matches[1] + "[ ]+-->([\\s\\S]*)<!--[ ]+END[ ]+BLOCK[ ]+:[ ]+" + matches[1] + "[ ]+-->");
		content = content.replace(reg, '<<-' + matches[1] + '->>');
		reg.compile('<<-' + matches[1] + '->>');
		this.result_content = this.result_content.replace(reg, '');
	}

	for(var mark in this.def_marks)
		data[mark] = this.def_marks[mark]
	 
	if(data)
		for(var mark in data) {
			reg.compile('{' + mark + '}');
			content = content.replace(reg, data[mark]);
		}
	
	if(block_name) {
		reg.compile('<<-' + block_name + '->>');
		this.result_content = this.result_content.replace(reg, content + '<<-' + block_name + '->>');
	}
	else
		this.result_content = content;
}

function result()
{
	this.result_content = this.result_content.replace(/[\\s\\S]*(<<-.*->>)[\\s\\S]*/g, "");
	this.result_content = this.result_content.replace(/[\\s\\S]*({\w+})[\\s\\S]*/g, "");
	return this.result_content;
}
