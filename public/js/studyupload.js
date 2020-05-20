/**
 * Created by Administrator on 2017/8/17.
 */
$(function(){
    $('#up_file').uploadifive({
        'auto' : true,
        'dnd' : false,
        'height' : 20,
        'width' : 58,
        'uploadScript' : up_url,
        'fileObjName' : 'upload',
        'buttonText' : '选择文件',
        'fileType' : '',
        'multi' : false,
        'fileSizeLimit'   : '20MB',
        'uploadLimit' : 1,
        'queueSizeLimit'  : 1,
        'removeCompleted' : true,
        onUploadComplete : function(file, data) {
            var obj = JSON.parse(data);
            $('#file_url').val(root+obj.fullpath);
            $('#file_size').val(obj.size/(1024*1024));
        },
        onCancel : function(file) {
            $("#frontSide").val("");
            /* 注意：取消后应重新设置uploadLimit */
            $data    = $(this).data('uploadifive'),
                settings = $data.settings;
            settings.uploadLimit++;
        },
        onFallback : function() {
            alert("该浏览器无法使用!");
        },
        onProgress   : function(file, e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 100)-1;
            }
            file.queueItem.find('.fileinfo').html(' - ' + percent + '%');
            file.queueItem.find('.progress-bar').css('width', percent + '%');
        }
    });
});