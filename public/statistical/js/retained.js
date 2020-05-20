/**
 * Created by Administrator on 2017/5/25.
 */
$(function () {
    $('.datechange').click(function(){
        if(!$(this).hasClass('off')){
            var val = $(this).attr('data-unit');
            $('.after_period_indicator').css('display','none');
            $('.datechange').removeClass('on');
            $(this).addClass('on');

            if(val == 'daily'){
                $('#daily_after_period').css('display','table-row')
            }else if(val == 'weekly'){
                $('#weekly_after_period').css('display','table-row')
            }else{
                $('#monthly_after_period').css('display','table-row')
            }
            getData()
        }

    });
    $('.export').click(function(){
        var start = $(':input[name=daterangepicker_start]').val(),end = $(':input[name=daterangepicker_end]').val()
        var noexcel = '';
        $('#retention-period li').each(function(){
            if($(this).hasClass('on')){
                type = $(this).attr('data-unit')
            }
        });
        if(type == 'daily'){
            noexcel = '#weekly_after_period';
        }else if(type == 'weekly'){
            noexcel = '#daily_after_period';
        }
        $('#retention-table').table2excel({
            exclude : noexcel,
            // 导出的Excel文档的名称
            name: "Excel Document Name",
            // Excel文件的名称
            filename: start+'至'+end+'留存用户'

        });
    })
});
function getData(){
    $('.wait-load').css('display','block');
    var start = $(':input[name=daterangepicker_start]').val(),end = $(':input[name=daterangepicker_end]').val(),type;
    var url = request_url;
    //得到日期类型
    $('#retention-period li').each(function(){
        if($(this).hasClass('on')){
            type = $(this).attr('data-unit')
        }
    });
    //获取2个时间相差天数
    var days = daysBetween(start,end);
    days > 7 ? $('.weekly').removeClass('off') : $('.weekly').addClass('off');
//        days > 30 ? $('.monthly').removeClass('off') : $('.monthly').addClass('off');
//        alert(days);
//        alert(type);
//        alert(start);
//        alert(end)
    $.post(url,{start:start,end:end,type:type},function(res){
        if(res.state == 'success'){
            $('.wait-load').css('display','none');
            $('#data-list').empty();
            var str = '';
            if(type == 'daily'){
                for(var i=0;i<res.data.length;i++){
                    str += '<tr>';
                    str += "<td>"+res.data[i].first_time+"</td>";
                    str += "<td>"+res.data[i].installs+"</td>";
                    str += res.data[i].one_day != 0 ? "<td>"+res.data[i].one_day+" %</td>" : "<td></td>";
                    str += res.data[i].two_day != 0 ? "<td>"+res.data[i].two_day+" %</td>" : "<td></td>";
                    str += res.data[i].three_day != 0 ? "<td>"+res.data[i].three_day+" %</td>" : "<td></td>";
                    str += res.data[i].four_day != 0 ? "<td>"+res.data[i].four_day+" %</td>" : "<td></td>";
                    str += res.data[i].five_day != 0 ? "<td>"+res.data[i].five_day+" %</td>" : "<td></td>";
                    str += res.data[i].six_day != 0 ? "<td>"+res.data[i].six_day+" %</td>" : "<td></td>";
                    str += res.data[i].seven_day != 0 ? "<td>"+res.data[i].seven_day+" %</td>" : "<td></td>";
                    str += res.data[i].fourteen_day != 0 ? "<td>"+res.data[i].fourteen_day+" %</td>" : "<td></td>";
                    str += res.data[i].thirty_day != 0 ? "<td>"+res.data[i].thirty_day+" %</td>" : "<td></td>";
                    str += '</tr>';
                }
            }else{
                for(var i=0;i<res.data.length;i++){
                    str += '<tr>';
                    str += "<td>"+res.data[i].start_time+'~'+res.data[i].end_time+"</td>";
                    str += "<td>"+res.data[i].installs+"</td>";
                    str += res.data[i].one_week != 0 ? "<td>"+res.data[i].one_week+" %</td>" : "<td></td>";
                    str += res.data[i].two_week != 0 ? "<td>"+res.data[i].two_week+" %</td>" : "<td></td>";
                    str += res.data[i].three_week != 0 ? "<td>"+res.data[i].three_week+" %</td>" : "<td></td>";
                    str += res.data[i].four_week != 0 ? "<td>"+res.data[i].four_week+" %</td>" : "<td></td>";
                    str += res.data[i].five_week != 0 ? "<td>"+res.data[i].five_week+" %</td>" : "<td></td>";
                    str += res.data[i].six_week != 0 ? "<td>"+res.data[i].six_week+" %</td>" : "<td></td>";
                    str += res.data[i].seven_week != 0 ? "<td>"+res.data[i].seven_week+" %</td>" : "<td></td>";
                    str += res.data[i].eight_week != 0 ? "<td>"+res.data[i].eight_week+" %</td>" : "<td></td>";
                    str += res.data[i].nine_week != 0 ? "<td>"+res.data[i].nine_week+" %</td>" : "<td></td>";
                    str += '</tr>';
                }
            }

            $('#data-list').append(str);
        }else{

        }
    });
}

function daysBetween(sDate1,sDate2){
    var time1 = Date.parse(new Date(sDate1));
    var time2 = Date.parse(new Date(sDate2));
    var nDays = Math.abs(parseInt((time2 - time1)/1000/3600/24));
    return nDays;
}