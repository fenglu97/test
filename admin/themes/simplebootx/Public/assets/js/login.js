$(window).load(function () {
    //注册奖励金额
    $.ajax({
        url: '/index.php?g=api&m=Promoteruser&a=register_bonus',
        type: 'POST',
        success: function(datas){
            if(datas.status == 1){
                $('#awardSum').html(datas.data);
            }
        }
    });
    //验证码图片点击刷新
    $('.verify_img').on('click',function () {
        var path = "/index.php?g=api&m=checkcode&a=index&length=4&font_size=20&width=163&height=44&use_noise=1&use_curve=0&rnd=" + Math.random();
        $(this).attr('src',path);
    });
    var exp = [];
    exp[0] = '^[1][3,4,5,7,8][0-9]{9}$';
    exp[1] = '^[a-zA-Z0-9_]{6,16}$';
    //提交表单事件
    $('#btn').on('click',function () {
        var flag = true;
        var states = $('.state');
        for(var i=0,len=states.length; i<len; i++){
            if(!states[i].value){
                flag = false;
                states[i].className += ' hint-border';
                $('.hint1')[i].className += ' show';
            } else if(exp[i]){
                var patt = new RegExp(exp[i]);
                var val = states[i].value;
                if (!patt.test(val)) {
                    flag = false;
                    states[i].className += ' hint-border';
                    $('.hint2')[i].className += ' show';
                }
            }
        }
        if(flag){
            $.ajax({
                url: '/index.php?g=api&m=promoteruser&a=login',
                type: 'POST',
                data:$('#login_form').serialize(),
                success: function(datas){
                    if(datas.status == 1){
                        location.href = datas.data.url;
                    }else{
                        alert(datas.msg);
                    }
                }
            });
        }
    });
    //键盘事件
    $('.state').on('keyup',function () {
        if($(this).val()) {
            $(this).removeClass('hint-border');
            $(this).parent().children('span.hint1').removeClass('show');
            $(this).parent().children('span.hint2').removeClass('show');
        }
    });
    //回车触发提交事件
    $('#verify').on('keydown',function (e) {
        if (e && e.keyCode == 13) {
            $('#btn').click();
        }
    });
});
