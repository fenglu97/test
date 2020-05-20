/**
 * Created bylily on 2016/2/27.
 * 鐢ㄤ簬鍏抽敭璇嶆姇鏀炬祴璇�
 */
$(function(){
    setTimeout(function(){
        $('.return').addClass("active_return");
    },10000);
    setTimeout(function(){
        // $('.tip').show();
    },10000);
    setTimeout(function(){
        $('.tip').hide();
    },20000);
    $('.tip').click(function(){
        $(this).hide();
        $('.return').removeClass("active_return");
    });
    $('.return').click(function(){
        $(this).removeClass("active_return");
        $('.tip').hide();
    });
});

var referrer=document.referrer;
var urlArr = window.location.href.split('.');
var urlArr1 = window.location.search.split('/');
var source="";
var word="";
var title="";
var n="";
var h="";
var d="";
var c="";
var p="";
var new_arr = urlArr1[0].split('?');
urlArr1[0]=new_arr[1];
var host=urlArr[1];
$('#home_herf').attr("href","/app/GameSaveWeb"+host+".apk");

// 鎺ㄥ箍鍙傛暟
var tgParams = '';
var search = window.location.search;
if (search.indexOf('?') != -1) {
    var tgParamsArr = [];
    var tgParamKeys = ['tgqd', 'tgzh', 'tgjh', 'tgdy', 'tggjc'];
    search = search.split('&');
    var searchLen = search.length;
    var len = tgParamKeys.length;
    if (searchLen >= 5) {
        for (var i = 0; i < searchLen; i++) {
            if (search[i].indexOf('=') != -1) {
                for (var j = 0; j < len; j++) {
                    if (search[i].indexOf(tgParamKeys[j]) != -1) {
                        tgParamsArr[j] = search[i];
                        break;
                    }
                }
            }
        }
    }
    if (tgParamsArr.length == len) {
        tgParams = tgParamsArr.join('&');
        tgParams = tgParams.replace('?', '');
    }
}

// 鍚庨€€
window.onbeforeunload = function(e) {
    backspace();
};
function backspace() {
    if (referrer.indexOf(host+".com")>-1) {
        history.go(-1);
        return false;
    } else {
        // 濡傛灉鍚庨€€鏄叾瀹冪綉绔欑殑锛岀洿鎺ラ€€鍥為椤�
        location.replace('http://m.'+host+'.com');
        return false;
    }
}
$.each(urlArr1, function(i, value) {
    switch (value){
        case "n":
            n=urlArr1[i+1];
            break;
        case "h":
            h=urlArr1[i+1];
            break;
        case "d":
            d=urlArr1[i+1];
            break;
        case "c":
            c=urlArr1[i+1];
            break;
        case "p":
            if (tgParams != '') {
                p=urlArr1[i+1].substr(0, 1);
            } else {
                p=urlArr1[i+1];
            }
            break;
        default :
            break;
    }
});
function test_url(){
    if(n=="" ){
        n=1;
    }
    if(h=="" ){
        h=2;
    }
    if(d=="" ){
        d=3;
    }
    if(c=="" ){
        c=4;
    }
    if(p=="" ){
        p=0;
    }
}
test_url();
var tab_list =$('#tab_list ul li');
for(var i=0;i<4;i++){

    tab_list.eq(i).find('a').attr("href"," http://campaign."+host+".com/sort3.html?n/"+n+"/h/"+h+"/d/"+d+"/c/"+c+"/p/"+i+(tgParams != '' ? '&' + tgParams : ''));
}

switch(p) {
    case "1":
        tab_list.eq(1).addClass("active");
        break;
    case "2":
        tab_list.eq(2).addClass("active");
        break;
    case "3":
        tab_list.eq(3).addClass("active");
        break;
    default:
        tab_list.eq(0).addClass("active");
        break;
}

//function init_list(){
//    $.ajax({
//        type:"POST",
//        url:"/bsort2/list",
//        data:{n:n,h:h,c:c,p:p,d:d,ref:referrer},
//        datatype: "json",
//        //鍦ㄨ姹備箣鍓嶈皟鐢ㄧ殑鍑芥暟
//        beforeSend:function(){},
//        //鎴愬姛杩斿洖涔嬪悗璋冪敤鐨勫嚱鏁�
//        success:function(json){
//           var json= eval ('(' + json + ')' );
//            var $hot_top=$('.hot_top');
//            if(json.code==0 && json.data.LIST !=""){
//                for(var i=0;i<3;i++){
//                    $hot_top.find('.hotimg').eq(i).attr("src","http://"+json.data.LIST[i].url_domain+"/icon/"+json.data.LIST[i].id+"/100");
//                    $hot_top.find('a.down_btn').eq(i).attr("href","http://www."+host+".com/apps/download/"+json.data.LIST[i].id+"/100");
//                    $hot_top.find('a.link_d').eq(i).attr("href","http://m."+host+".com/apps/view3/"+json.data.LIST[i].slug+(tgParams != '' ? '?' + tgParams : ''));
//                    $hot_top.find('a.link_dd').eq(i).attr("href","http://m."+host+".com/apps/view3/"+json.data.LIST[i].slug+(tgParams != '' ? '?' + tgParams : ''));
//                    $hot_top.find('a.link_dd.name').eq(i).text(json.data.LIST[i].name);
//                }
//
//                var len = json.data.LIST.length;
//                for (var i = 0; i < len; i++) {
//                    json.data.LIST[i].tg_params = (tgParams != '' ? '?' + tgParams : '');
//                }
//
//                var html = template('tpml_init', json);
//                var ahtml= document.getElementById("con_1").innerHTML;
//                document.getElementById('con_1').innerHTML=ahtml+html;
//                source=json.data.CONF.source;
//                word=json.data.CONF.word;
//                title=json.data.CONF.title;
//                if(title !=""){
//                    $('#title').text(title);
//                    $('title').text(title);
//                }
//                else{
//                    $('#title').text("2016鐑棬鎵嬫父鎺掕姒�");
//                }
//
//                list_rank();
//            }
//
//        }   ,
//        //璋冪敤鎵ц鍚庤皟鐢ㄧ殑鍑芥暟
//        complete: function(XMLHttpRequest, textStatus){
//
//        },
//        error: function(){
//            //璇锋眰鍑洪敊澶勭悊
//        }
//    });
//}
var icon_status="";
var control_icon=function(){
    var JSONP = document.createElement("script");
    JSONP.type = "text/javascript";
    JSONP.src = "http://tj.910app.com/api/icon/status?callback=callback_gameIcon";
    document.getElementsByTagName("head")[0].appendChild(JSONP);
}
control_icon();
function callback_gameIcon(result){
    var status=result['3']['status'];
    icon_status=status;
    console.log(icon_status);
}

function statput(aid, catid){
    $.ajax({
        url: "/api/stat/gdata/aid/"+aid+"/tid/"+h+"/cid/"+n+"/catid/"+catid+"/source/"+source+"/kw/"+word,
        async: true,
        timeout:3000
    });
    if(catid==2){

        if(aid=="3550" || aid=="3389" || aid=="3589" || aid=="3613" || aid=="3585" || aid=="3523" || aid=="3528"){
            //$('#load_master').show();
            //$('.fix-yur,.yur').show();
            //setTimeout(function(){
            //    passport(3,"callback");
            //},500);
        }
        //n/50/h/2/d/3/c/1/p/1
        if(n==50 && h==2 && d==3 && c==1 &p ==1){
            $('#con_1 .item:first-child').click(function(){
                if(icon_status==1){
                    $('.fix-yur,.yur').show();
                    setTimeout(function(){
                        passport(3,"callback");
                    },500);
                }

            });

            $('.item').each(function(k){
                var ks=0;
                //console.log(k);
                $(this).find('a').click(function(){
                    ks=k;
                    console.log(ks);
                    if(ks<=6){
                        if(icon_status==1){
                            $('.fix-yur,.yur').show();
                            setTimeout(function(){
                                passport(3,"callback");
                            },500);
                        }

                    }

                });


            });


         }

    }

}
function callback(result) {
    //console.log(result);
    if(result !=""){
        $('#yur_img').attr("src",result.iconUrl);
        $('.yurGameN').text(result.gameName);
        $('.yurDes').text(result.description);
        $('.yurred').text(result.hintMsg);
        $('#read_btn').attr("data-id",result.id);
        $('#read_btn').attr("data-tsyMsg",result.tsyMsg);
    }
    else{

    }
}
//statput('10000000','0');

$.getJSON("/bsort2/page_append/n/"+n+"/h/"+h+"/d/"+d+"/c/"+c+"/p/"+p+"/page/",function(json){
    var total=json.total;
    game_list(total,"/bsort2/page_append/n/"+n+"/h/"+h+"/d/"+d+"/c/"+c+"/p/"+p+"/page/");
});
init_list();

var list_rank=function(){
    $('.tab_con').each(function (k, v) {
        $(this).find('.item').each(function (k, v) {
            $(this).find('.num').text(k+3 + 1);
        });
    });
};

function ajax_load_game(list_url){
    $.ajax({
        type:"post",
        dataType:"json",
        url:list_url,//杩欓噷闇€瑕佸垎椤碉紝6涓竴椤�
        data:{ref:referrer},
        async:true,
        success:function(json){
            var len = json.result.length;
            for (var i = 0; i < len; i++) {
                json.result[i].tg_params = (tgParams != '' ? '?' + tgParams : '');
            }
            var html = template('tpml', json);
            var ahtml= document.getElementById("con_1").innerHTML;
            document.getElementById('con_1').innerHTML=ahtml+html;
            list_rank();
        }
    });
}

//
//鑾峰彇婊氬姩鏉″綋鍓嶇殑浣嶇疆
function getScrollTop() {
    var scrollTop = 0;
    if (document.documentElement && document.documentElement.scrollTop) {
        scrollTop = document.documentElement.scrollTop;
    }
    else if (document.body) {
        scrollTop = document.body.scrollTop;
    }
    return scrollTop;
}

//鑾峰彇褰撳墠鍙槸鑼冨洿鐨勯珮搴�
function getClientHeight() {
    var clientHeight = 0;
    if (document.body.clientHeight && document.documentElement.clientHeight) {
        clientHeight = Math.min(document.body.clientHeight, document.documentElement.clientHeight);
    }
    else {
        clientHeight = Math.max(document.body.clientHeight, document.documentElement.clientHeight);
    }
    return clientHeight;
}

//鑾峰彇鏂囨。瀹屾暣鐨勯珮搴�
function getScrollHeight() {
    return Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
}

//棣栧厛锛岀獥鍙ｇ粦瀹氫簨浠秙croll
function game_list(max_page,list_url){
    //鍒ゆ柇娴忚鍣ㄥ唴鏍�
    var top_n="";
    function userAgent(){
        var ua = navigator.userAgent;
        ua = ua.toLowerCase();
        var match = /(webkit)[ \/]([\w.]+)/.exec(ua) ||
            /(opera)(?:.*version)?[ \/]([\w.]+)/.exec(ua) ||
            /(msie) ([\w.]+)/.exec(ua) ||
            !/compatible/.test(ua) && /(mozilla)(?:.*? rv:([\w.]+))?/.exec(ua) ||
            [];
        switch(match[1]){
            case "webkit":
                top_n=0;
                break;
            case "mozilla":
                top_n=1;
                break;
            default:
                break;
        }
    }
    userAgent();
    var a=1; // 绗竴椤靛凡缁忓姞杞戒簡
    $(window).bind("scroll",function() {
        var tab_list= document.getElementById('tab_list');
        var bodyh=document.body.clientHeight;
        var window_height=window.innerHeight;
        if(top_n==0){
            var top=document.body.scrollTop;//璋锋瓕
        }
        else{
            var top=document.documentElement.scrollTop;//鐏嫄
        }
        if(top>=200){
            tab_list.setAttribute("class","tab_list text-center fix_tab");
        }
        else{
            tab_list.setAttribute("class","tab_list text-center");
        }
        if (getScrollTop() + getClientHeight() == getScrollHeight()) {
                a = a + 1;//a鏄€婚〉鏁�
                ajax_load_game((list_url+a));
        }

    });
}

//    涓嬭浇寮瑰嚭绐楀彛锛屽苟涓斾笅杞芥父鎴�
$('.down_btn1,.down_btn').click(function(){
    //$('#load_master').show();
    //$('.load_msg').show();
    //setTimeout(function(){
    //    $('.load_msg').hide();
    //},2000)
});

$('#box_close_ico,.erm_down a').click(function(){

    if($('#remove_tip').is(':checked')) {
        //
        $('#load_master').remove();
    }
    else{
        $('#load_master').hide();
    }

});
$('.erm_down a').click(function(){
    //$('.load_msg').show();
    //setTimeout(function(){
    //    $('.load_msg').hide();
    //},2000)
});

