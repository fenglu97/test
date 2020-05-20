/**
 * Created by TENONE on 2017/12/26.
 */
window.onload = function () {
    var notarizeBox = document.getElementById('notarizeBox');
    notarizeBox.style.display = 'none';
    var isDrag = false;
    var rel_x,rel_y;
//        鼠标按下
    notarizeBox.onmousedown = function(e){
        isDrag = true;
        rel_x = e.clientX - parseInt(notarizeBox.offsetLeft || 0);
        rel_y = e.clientY - parseInt(notarizeBox.offsetTop || 0);

    }
    /*鼠标拖动*/
    document.onmousemove = function(e){
        if(isDrag === true){
            notarizeBox.style.left = e.clientX - rel_x +"px";
            notarizeBox.style.top = e.clientY - rel_y +"px";
        }
    }
//        鼠标释放
    document.onmouseup = function () {
        isDrag = false;
    }
    //判断是否显示提示框
    $.ajax({
        url: '/index.php?g=api&m=Promoteruser&a=is_read_study',
        type: 'GET',
        success:function (datas) {
            if(datas.data == 1){
                notarizeBox.style.display = 'none';
            }else{
                notarizeBox.style.display = 'block';
            }
        }
    })
    //确认隐藏提示框
    $('#notarizeBtn').on('click',function () {
        $.ajax({
            url: '/index.php?g=api&m=Promoteruser&a=confirm_study',
            type: 'GET',
            success:function (datas) {
                if(datas.status == 1){
                    notarizeBox.style.display = 'none';
                    //移除顶级window下的样式
                    window.top.document.getElementById('promptNav').className = 'dropdown-toggle';
                }
            }
        })
    });


}