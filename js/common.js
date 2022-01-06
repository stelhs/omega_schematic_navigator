/*
	��������� E-mail �� ������������
	@param email - ����������� E-mail �����
	@return - true ���� ����� ���������� ��� false � ��������� ������
*/
function check_email(email)
{
	var ch;
	var name = 0;
	var server;
	for(var i = 0; i < email.length; i++)
	{
		ch = email.charAt(i);
		if(ch == '@')
		{
			if(name)
				return false;
			name = i;
		}
		
		if(name)
			if((i - name) >= 3)
				return true;
	}
	return false;
}


/*
	�������� ��������� �������� radio ������
	@param radio_group_obj - ������ �����������
	@return - ��������� �������
*/
function get_radio_group_value(radio_group_obj)
{
  for (var i=0; i < radio_group_obj.length; i++)
	if (radio_group_obj[i].checked) return radio_group_obj[i].value;
	
  return null;
}



/*
������� ������������ ���� ������ � ������� integer
�������� �������� �� ����� ���� ������������� ��� �� ��������
@param obj - ������ ���� input
@return - ���������� �������� ��������
*/
function check_correct_int_field(obj)
{
	str = obj.value;
	var correct_chars, correct, new_str, k, zero;
	new_str = '';
	correct_chars = "-1234567890";
	k = 0;
	zero = 0;
	for(var i = 0; i < str.length; i++)
	{
		correct = -1;
		for(var c = 0; c < correct_chars.length; c++)
			if(str.charAt(i) == correct_chars.charAt(c))
				correct = correct_chars.charAt(c);
		
		if(correct != -1)
			if(correct == '0' && !k)
				zero = 1;
			else
			{
				zero = 0;
				k = 1;
			}	
		
		if(correct != -1 && !zero)
			new_str += correct;
	}
	if (!new_str)
		new_str = '0';
	
	if (new_str[0] == '-' && new_str.length <= 1)
		new_str = '0';

	obj.value = parseInt(new_str);
	return obj.value;
}

/*
	������� ������������ ���� ������ � ������� float
	@param obj - ������ ���� input
	@return - ���������� �������� ��������
*/
function check_correct_float_field(obj)
{
	str = obj.value;
	var correct_chars, correct, new_str, k, zero;
	new_str = '';
	correct_chars = "1234567890,.";

	k = 0;
	zero = 0;
	for(var i = 0; i < str.length; i++)
	{
		correct = -1;
		for(var c = 0; c < correct_chars.length; c++)
			if(str.charAt(i) == correct_chars.charAt(c))
				correct = correct_chars.charAt(c);
		
		if(correct != -1)
			if(correct == '0' && !k)
				zero = 1;
			else
			{
				zero = 0;
				k = 1;
			}	
		
		if(correct != -1 && !zero)
			new_str += correct;
	}
	if(!new_str)
		new_str = 1;
	
    new_str = new_str.replace(',', '.');
    
	obj.value = parseFloat(new_str);
	return obj.value;
}


/*
	������� ������������ ���� ������ � ������� "����������"
	�������� �������� �� ����� ���� ������������� ��� �� ��������
	@param obj - ������ ���� input
	@return - ���������� �������� ��������
*/
function check_correct_count_field(obj)
{
	var val = check_correct_int_field(obj);
	if (val <= 0)
		obj.value = 1;
}

/*
	������� ������������ ���� ������ � ������� ��������
	�������� �������� �� ����� ���� ��� ��������� 0 - 100% ��� �������������.
	@param obj - ������ ���� input
	@return - ���������� �������� ��������
*/
function fix_percent(obj)
{
	if(obj.value > 100)
		obj.value = 100;

	if(obj.value <= 0)
		obj.value = 0;
}


/* ��������� ������������ ���� */
function check_date(obj, default_date)
{
	correct_chars = "1234567890";
    str = obj.value;
    
    if(str.length > 10)
    {
        obj.value = default_date;
        return false;
    }    
    
	for(var i = 0; i < 2; i++)
	{
        if(!check_symbol(str.charAt(i), correct_chars))
        {
            obj.value = default_date;
            return false;
        }    
    }

    if(str.charAt(2) != '.')
    {
        obj.value = default_date;
        return false;
    }    
    
	for(var i = 3; i < 5; i++)
	{
        if(!check_symbol(str.charAt(i), correct_chars))
        {
            obj.value = default_date;
            return false;
        }    
    }

    if(str.charAt(5) != '.')
    {
        obj.value = default_date;
        return false;
    }    

	for(var i = 6; i < 10; i++)
	{
        if(!check_symbol(str.charAt(i), correct_chars))
        {
            obj.value = default_date;
            return false;
        }    
    }
    
    return true;
}


/*
	����� ������� �� ��� ID
	@id - ID �������� �������
	@return - ��������� ������
*/
function $$(id)
{
	return document.getElementById(id);
}


function count(list)
{
    var count = 0;
    for (i in list)
        count ++;

    return count;
}