$('#findPwd_form').validator({
    rules: {
        // 使用正则表达式定义规则
        mobile: [/^1[3-9]\d{9}$/, "请填写有效的手机号"],
        pwd: [/^\w{6,12}$/, "请填写6-12位数字、字母、下划线"]
    },
    fields: {
		'mobile':{
            rule:'required;mobile',
            msg:{
                  required: "请填写手机号"
            }
        },
        'code':{
            rule:'required',
            msg:{
                  required: "验证码不能为空"
            }
        },
        'password':{
            rule:'密码: required;pwd;',
            msg:{
                  required: "请填写密码"
            }
        },
        'pwdAgain':{
        	rule:'确认密码: required; match(password)',
        	msg:{
                  required: "请填写确认密码"
            }
        },
    }
}).on("valid.form",function(e, form){
	$.ajax({
		url: '/index.php?g=api&m=promoteruser&a=forget_password',
		type: 'POST',
		data: $(form).serialize(),
		success: function(datas){
			if(datas.status == 1){
				alert('修改成功');
				location.href = "/index.php?g=admin&m=public&a=tg_login";
			}else{
				alert(datas.msg);
			}
		}
	});
});
var flag = false;
//			手机验证通过
$('#mobile').on('valid.field', function(e, result){
    flag = true;
});
//			手机验证不通过
$('#username').on('invalid.field', function(e, result){
    flag = false
});
//获取验证码
$('.codeBtn').on('click',function(e) {
	e.stopPropagation();
	if (flag) {
		var mobile = $('#mobile').val();
		//发请求
		$.ajax({
			url: '/index.php?g=api&m=userbox&a=send_message',
			type: 'POST',
			data: { "mobile": mobile, "type": 2, "client": 1},
			success: function(datas){
				if(datas.status != 1){
                    alert(datas.msg);
				}
			}
		});
		$(this).addClass('codeBtnOut');
		$(this).prop("disabled","true");
		var time = 60;
		var timer = setInterval(function() {
			if (time > 0) {
				time--;
				$(this).val(time);
			} else{
				$(this).val('获取验证码');
				$(this).removeClass('codeBtnOut');
				clearInterval(timer);
				$(this).removeAttr('disabled');
			}
		}.bind(this),1000);
	} else{
		alert('请检查手机格式');
	}
});