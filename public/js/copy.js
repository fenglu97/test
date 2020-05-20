$(function () {
    $('.addrh').click(function(){
        var appid = $(':input[name=id]').val();
        var name = $(':input[name=name]').val();
        var ckey = $('#client_key').html();
        var skey = $('#server_key').html();
        $.post(addrh_url,{appid:appid,name:name,ckey:ckey,skey:skey},function(res){
            if(res.status == 1){
                $('.rh_data').empty();
                $('.rh_data').html(res.data);
            }else{
                layer.msg(res.info, {icon: 2,time: 1000}, function(){
                    location.reload();
                });
            }
        })
    });

    /*复制游戏数据*/
    var allcopy = new Clipboard('.allcopy', {
        text: function() {
            var str = $('#allcopy').html();
            while (str.indexOf('|')!=-1){
                str = str.replace('|','\r\n');
            }
            return str;
        }
    });
    allcopy.on('success', function(e) {layer.alert('复制成功',{shadeClose: true});});
    allcopy.on('error', function(e) {layer.alert('复制失败,请手动复制',{shadeClose: true});});

    var server_key = new Clipboard('.server_key', {
        text: function() {
            return $('#server_key').html();
        }
    });
    server_key.on('success', function(e) {layer.alert('复制成功',{shadeClose: true});});
    server_key.on('error', function(e) {layer.alert('复制失败,请手动复制',{shadeClose: true});});

    var client_key = new Clipboard('.client_key', {
        text: function() {
            return $('#client_key').html();
        }
    });
    client_key.on('success', function(e) {layer.alert('复制成功',{shadeClose: true});});
    client_key.on('error', function(e) {layer.alert('复制失败,请手动复制',{shadeClose: true});});

    /*复制融合数据*/
    var rhallcopy = new Clipboard('.rhallcopy', {
        text: function() {
            var str = $('#rhallcopy').html();
            while (str.indexOf('|')!=-1){
                str = str.replace('|','\r\n');
            }
            return str;
        }
    });
    rhallcopy.on('success', function(e) {layer.alert('复制成功',{shadeClose: true});});
    rhallcopy.on('error', function(e) {layer.alert('复制失败,请手动复制',{shadeClose: true});});

    var rh_appKey = new Clipboard('.rh_appKey', {
        text: function() {
            return $('#rh_appKey').html();
        }
    });
    rh_appKey.on('success', function(e) {layer.alert('复制成功',{shadeClose: true});});
    rh_appKey.on('error', function(e) {layer.alert('复制失败,请手动复制',{shadeClose: true});});

    var rh_appSecret = new Clipboard('.rh_appSecret', {
        text: function() {
            return $('#rh_appSecret').html();
        }
    });
    rh_appSecret.on('success', function(e) {layer.alert('复制成功',{shadeClose: true});});
    rh_appSecret.on('error', function(e) {layer.alert('复制失败,请手动复制',{shadeClose: true});});

});