/**
 * Created by Admin on 2018/8/3.
 */
$(function () {
    layui.use(['jquery', 'layer', 'form', 'element','laydate'], function(){
        var layer = layui.layer//重点处
            ,form = layui.form
            ,element = layui.element
            ,laydate = layui.laydate;



        var myDate = new Date(),
            myYear = myDate.getFullYear(),
            myMonth = myDate.getMonth()+1;
        var minDate = myYear + '-' + (myMonth-1) + '-' + 1;
        var maxDate = myYear + '-' + (myMonth+2) + '-' + new Date(myYear,myMonth+1,0).getDate();

        //因要显示近两个月的活动，每次请求一个月的数据，此值确保两个月都数据都已经请求
        var noticeFlag = 0;

        //请求活动数据
        // getNotice(myYear + '-' + (myMonth < 10 ? '0' + myMonth : myMonth));
        // getNotice(myYear + '-' + (myMonth+1 < 10 ? '0' + (myMonth+1) : myMonth+1));

        function timestampToTime(timestamp) {
            var date = new Date(timestamp * 1000);
            var Y = date.getFullYear() + '-';
            var M = (date.getMonth()+1 < 10 ? '0'+(date.getMonth()+1) : date.getMonth()+1) + '-';
            var D = (date.getDate() < 10 ? '0'+date.getDate() : date.getDate());
            return Y+M+D;
        }
        function timestampToTimeD(timestamp) {
            var date = new Date(timestamp * 1000);
            var Y = date.getFullYear() + '-';
            var M = (date.getMonth()+1 < 10 ? '0'+(date.getMonth()+1) : date.getMonth()+1) + '-';
            var D = (date.getDate() < 10 ? '0'+date.getDate() : date.getDate());
            var H = (date.getHours() < 10 ? '0'+date.getHours() : date.getHours()) + '-';
            var M = (date.getMinutes() < 10 ? '0'+date.getMinutes() : date.getMinutes());
            return Y+M+D+' '+H+M;
        }
        function clearLayuiBtnClick() {
            $('#ptflhd').unbind('click');
            $('#yxxxflhd').unbind('click');
            $('#mfhd').unbind('click');
        }
        var timeMark = {},
            timeMarkTitle = {};
        var level = 1; //1.平台返利活动 2.游戏线下返利活动 3.免费活动
        var gameid = '';
        function spliceNotice(showList) {
            // var showList = ptflhd;
            for(var i = 0; i < showList.length; i++){
                var time = new Date(showList[i].time * 1000).getDate();
                showList[i].time = timestampToTime(showList[i].time);
                timeMark[showList[i].time] = '<div class="activity'+ showList[i].level +'"><p>'+ time +'</p><p class="size_12px">('+ showList[i].remark +')</p></div>';
                timeMarkTitle[showList[i].time] = showList[i].remark;
            }
        }
        window.isNormal = function (event, data) {
            console.log(data);
            var e = window.event || event;
            if(document.all){  //只有ie识别
                e.cancelBubble=true;
            }else{
                e.stopPropagation();
            }
            $.ajax({
                type: 'GET',
                url: '/index.php?g=api&m=notice&a=get_admin_notice',
                data:{
                    time: data.formatTime,
                    appid: gameid,
                    level: level.toString(10)
                },
                //成功时执行的回调
                success:function(datas){
                    if(datas.status == 1){
                        layer.open({
                            title: ['游戏开服', 'font-size:18px;'],
                            type: 1,
                            skin: 'layui-layer-molv', //加上边框
                            area: ['800px', '550px'], //宽高
//                                            content: $('#activity'),
                            content: datas.data.content,
                            closeBtn: 0, //不显示关闭按钮
                            anim: 2,
                            btnAlign: 'c',
                            shadeClose: false, //开启遮罩关闭
                            btn: ['知道了'],
                            yes: function(index, layero){
                                layer.close(index)
                            }
                        });
                    }
                    if(datas.status == 0){
                        console.log(datas.msg)
                    }
                },
                error:function(err){
                    console.log('gcalendar请求数据失败：',err)
                }
            });
        };
        window.isKaifu = function (event, data) {
            console.log(data);
            var str = '<p>游戏名称：' + data.gamename + '</p><p>游戏区服：' + data.server_name + '</p><p>开服时间：' + timestampToTimeD(data.start_time*1000) + '</p>';
            // alert('开服')
            layer.open({
                title: ['游戏开服', 'font-size:18px;'],
                type: 1,
                skin: 'layui-layer-molv', //加上边框
                area: ['800px', '550px'], //宽高
//                                            content: $('#activity'),
                content: str,
                closeBtn: 0, //不显示关闭按钮
                anim: 2,
                btnAlign: 'c',
                shadeClose: false, //开启遮罩关闭
                btn: ['知道了'],
                yes: function(index, layero){
                    layer.close(index)
                }
            });
            var e = window.event || event;
            if(document.all){  //只有ie识别
                e.cancelBubble=true;
            }else{
                e.stopPropagation();
            }
        };
        //获取活动日期与活动详情内容
        function getNotice(obj) {
            //time, typy, now
            var arg = {
                time: myYear + '-' + (myMonth < 10 ? '0' + myMonth : myMonth),  //月份
                type: 1,    //level类型： 1.平台返利活动 2.游戏线下返利活动 3.免费活动
                now: true     //标记是否是本月
            };
            for(var key in obj){
                arg[key] = obj[key];
            }
            // console.log(arg);
            $.ajax({
                type: 'GET',
                url: '/index.php?g=api&m=notice&a=admin_notice_index',
                data:{
                    time: arg.time,  //myYear + '-' + (myMonth+1 < 10 ? '0' + (myMonth+1) : myMonth+1)
                    appid: gameid
                },
                //成功时执行的回调
                success:function(datas){
                    console.log(datas)
                    if(datas.status == 1){
                        var list = datas.data.list;
                        var ptflhd = list.ptflhd; //平台返利活动
                        var yxxxflhd = list.yxxxflhd;//游戏线下返利活动
                        var mfhd = list.mfhd;//免费活动
                        if(arg.now){
                            clearLayuiBtnClick();
                            //平台返利活动 默认
                            if(ptflhd){
                                // $('#ptflhd').unbind('click');
                                $('#ptflhd').removeClass('layui-btn-disabled');
                                $('#ptflhd').bind('click', function () {
                                    level = 1;
                                    console.log('平台返利活动');
                                    onLevel();
                                });
                            } else {
                                $('#ptflhd').addClass('layui-btn-disabled');
                                $('#ptflhd').unbind('click');
                            }
                            //游戏线下返利活动
                            if(yxxxflhd){
                                // $('#yxxxflhd').unbind('click');
                                $('#yxxxflhd').removeClass('layui-btn-disabled');
                                $('#yxxxflhd').bind('click', function () {
                                    level = 2;
                                    console.log('游戏线下返利活动');
                                    console.log(timeMark)
                                    onLevel();
                                });
                            } else {
                                $('#yxxxflhd').addClass('layui-btn-disabled');
                                $('#yxxxflhd').unbind('click');
                            }
                            //免费活动
                            if(mfhd){
                                // $('#mfhd').unbind('click');
                                $('#mfhd').removeClass('layui-btn-disabled');
                                $('#mfhd').bind('click', function () {
                                    level = 3;
                                    console.log('免费活动');
                                    onLevel();
                                });
                            } else {
                                $('#mfhd').addClass('layui-btn-disabled');
                                $('#mfhd').unbind('click');
                            }
                        }
                        ++noticeFlag;
                        //日常活动
                        if(noticeFlag == 1){
                            timeMark = {};
                            timeMarkTitle = {};
                            var commonActivity = datas.data.common_activity;
                            if(commonActivity.content){
                                $('#commonActivity').show();
                                $('#commonActivityTitle').html(commonActivity.title);
                                $('#commonActivityContent').html(commonActivity.content);
                            }
                        }
                        // console.log(arg.type)
                        if(arg.type == 1){
                            if(ptflhd){
                                spliceNotice(ptflhd);
                            }
                        } else if(arg.type == 2){
                            if(yxxxflhd){
                                spliceNotice(yxxxflhd);
                            }
                        } else if(arg.type == 3){
                            if(mfhd){
                                // mfhd = {
                                //     "1538668800": { //时间
                                //         "kaifu": { //开服
                                //             "id": "10231",
                                //             "line": "1",
                                //             "server_id": "51",
                                //             "start_time": "1538704800",
                                //             "gamename": "超级头号玩家",
                                //             "server_name": "双线 51服"
                                //         }
                                //     },
                                //     "1539878400": {
                                //         "normal": { //免费活动
                                //             "time": 1539878400,
                                //             "remark": "测试",
                                //             "level": "3"
                                //         },
                                //         "kaifu": { //开服
                                //             "id": "10691",
                                //             "line": "1",
                                //             "server_id": "57",
                                //             "start_time": "1539914400",
                                //             "gamename": "超级头号玩家",
                                //             "server_name": "双线 57服"
                                //         }
                                //     },
                                // };
                                var key = '';
                                for(key in mfhd){
                                    var mfhdData = {
                                        // time: '',
                                        normal: '',
                                        kaifu: ''
                                    };
                                    if(mfhd[key].normal){     //免费活动
                                        mfhdData.normal = mfhd[key].normal;
                                        mfhdData.normal.formatTime = timestampToTime(mfhdData.normal.time);
                                    }
                                    if(mfhd[key].kaifu){    //开服
                                        mfhdData.kaifu = mfhd[key].kaifu;
                                        mfhdData.kaifu.formatTime = timestampToTime(mfhdData.kaifu.start_time);
                                    }
                                    //onclick()传值需要特殊处理
                                    var kaifuData = JSON.stringify(mfhdData.kaifu).replace(/"/g, '&quot;');
                                    // console.log(kaifuData)
                                    var normalData = JSON.stringify(mfhdData.normal).replace(/"/g, '&quot;');
                                    if(mfhdData.normal && mfhdData.kaifu){
                                        timeMark[mfhdData.normal.formatTime] = '<div  class="activity1"><p onclick="isNormal(event,'+ normalData +')">免费活动</p><p class="size_12px" onclick="isKaifu(event,'+ kaifuData +')">(游戏开服)</p></div>';
                                    } else if(mfhdData.normal) {
                                        timeMark[mfhdData.normal.formatTime] = '<div class="activity'+ mfhdData.normal.level +'" onclick="isNormal(event,'+ normalData +')"><p>'+ new Date(Number(mfhdData.normal.time) * 1000).getDate() +'</p><p class="size_12px">(免费活动)</p></div>';
                                    } else if(mfhdData.kaifu){
                                        timeMark[mfhdData.kaifu.formatTime] = '<div class="activity1" onclick="isKaifu(event,'+ kaifuData +')"><p>'+ new Date(Number(mfhdData.kaifu.start_time) * 1000).getDate() +'</p><p class="size_12px">(游戏开服)</p></div>';
                                    }
                                    timeMarkTitle = {};
                                }
                            }
                        } else {
                            if(ptflhd){
                                spliceNotice(ptflhd);
                            } else if(yxxxflhd){
                                spliceNotice(yxxxflhd);
                            } else if(mfhd){
                                spliceNotice(mfhd);
                            }
                        }
                        if(noticeFlag == 2){
                            noticeFlag = 0; //重置
                            // console.log('+++++++++++++++++++++++++++++++++++++++++++++')
                            calendarObj = null;
                            $('#calendar').html('');
                            calendar(timeMark,timeMarkTitle);
                        }
                    }
                },
                error:function(err){
                    console.log(err)
                }
            });
        }
        //calendar


        //laydate.render不支持重复渲染数据，此值用来释放laydate.render创建的对象
        var calendarObj = null;
        function calendar(mark,markTitle) {
            //执行一个laydate实例
            calendarObj = laydate.render({
                elem: '#calendar'
                ,min: minDate	//'2018-7-1'	//formatDate(new Date().getTime())
                ,max: maxDate	//'2018-8-31'	//格式：'2017-1-1'	'2017-8-11 12:30:00'	'09:30:00'
                ,theme: '#393D49'
                ,mark: mark?mark:{} //标注重要日子
                ,show: true //直接显示
                ,showBottom: false  //不显示底部栏
                ,position: 'static'
                ,ready: function(date){
                    // console.log(date); //得到初始的日期时间对象：{year: 2017, month: 8, date: 18, hours: 0, minutes: 0, seconds: 0}
                }
                //日期时间被切换后的回调
                ,done: function(value, date, endDate){
                    if(mark && level != 3){
                        for(key in mark){
                            if(key == value){
                                $.ajax({
                                    type: 'GET',
                                    url: '/index.php?g=api&m=notice&a=get_admin_notice',
                                    data:{
                                        time: value,
                                        appid: gameid,
                                        level: level.toString(10)
                                    },
                                    //成功时执行的回调
                                    success:function(datas){
                                        console.log('---------------------')
                                        console.log(datas)
                                        if(datas.status == 1){
                                            var data = datas.data;
                                            var title = markTitle && JSON.stringify(markTitle) != '{}' ? markTitle[value] : '活动';
                                            layer.open({
                                                title: [title, 'font-size:18px;'],
                                                type: 1,
                                                skin: 'layui-layer-molv', //加上边框
                                                area: ['800px', '550px'], //宽高
//                                            content: $('#activity'),
                                                content: data.content,
                                                closeBtn: 0, //不显示关闭按钮
                                                anim: 2,
                                                btnAlign: 'c',
                                                shadeClose: false, //开启遮罩关闭
                                                btn: ['知道了'],
                                                yes: function(index, layero){
                                                    layer.close(index)
                                                }
                                            });
                                        }
                                        if(datas.status == 0){
                                            console.log(datas.msg)
                                        }
                                    },
                                    error:function(err){
                                        console.log('gcalendar请求数据失败：',err)
                                    }
                                });
                            }
                        }
                    }
                }
            });
        }

        getGameList();
        function getGameList() {
            $.ajax({
                type: 'GET',
                url: '/index.php?g=api&m=notice&a=get_game_list',
                //成功时执行的回调
                success:function(datas){
                    // console.log(datas)
                    if(datas.status == 1){
                        var gamenameSelect = document.getElementById('gamenameSelect');
                        if(gamenameSelect){
                            var html = '';
                            var data = datas.data,
                                len = data.length;
                            for(var i = 0; i < len; i++){
                                html = $('<option value="'+ data[i].id +'">'+data[i].game_name+'</option>');
                                gamenameSelect.appendChild(html[0]);
                            }
                            // console.log(gamenameSelect)
                        }
                    }
                    form.render(); //更新全部
                    // form.render('select');//刷新select选择框渲染
                    calendar();
                },
                error:function(err){
                    console.log('getGameList请求数据失败：',err)
                }
            });
        }

        form.on('select(gamename)', function(data){
            // console.log(data.elem); //得到select原始DOM对象
            console.log(data.value); //得到被选中的值
            // console.log(data.othis); //得到美化后的DOM对象

            // if(data.value){
            //     $('.notice_label>.layui-btn').removeClass('layui-btn-disabled');
            // } else {
            //     $('.notice_label>.layui-btn').addClass('layui-btn-disabled');
            // }
            if(data.value){
                gameid = data.value;
                // getNotice(myYear + '-' + (myMonth < 10 ? '0' + myMonth : myMonth), true);
                getNotice({
                    time: myYear + '-' + (myMonth < 10 ? '0' + myMonth : myMonth)  //月份
                });
                // getNotice(myYear + '-' + (myMonth+1 < 10 ? '0' + (myMonth+1) : myMonth+1));
                getNotice({
                    time: myYear + '-' + (myMonth+1 < 10 ? '0' + (myMonth+1) : myMonth+1),
                    now: false,     //标记是否是本月
                });
            } else {
                clearLayuiBtnClick();

                gameid = '';
                calendarObj = null;
                $('#calendar').html('');
                calendar();
                $('.notice_label>.layui-btn').addClass('layui-btn-disabled');
            }
        });
        function onLevel() {
            getNotice({
                time: myYear + '-' + (myMonth < 10 ? '0' + myMonth : myMonth),
                type: level,
                now: true
            });
            getNotice({
                time: myYear + '-' + (myMonth+1 < 10 ? '0' + (myMonth+1) : myMonth+1),
                type: level,
                now: false
            });
        }
    });
});