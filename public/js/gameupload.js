/**
 * Created by Administrator on 2017/8/17.
 */
$(function(){
    $('#up_abstract').uploadifive({
        'formData':{'tag':tag},
        'auto' : true,
        'dnd' : false,
        'height' : 20,
        'width' : 58,
        'uploadScript' : up_url,
        'fileObjName' : 'upload',
        'buttonText' : '选择文件',
        'fileType' : '',
        'multi' : false,
        'fileSizeLimit'   : '2MB',
        'uploadLimit' : 1,
        'queueSizeLimit'  : 1,
        'removeCompleted' : true,
        onUploadComplete : function(file, data) {
            var obj = JSON.parse(data);
            $('#abstract_url').val(root+obj.fullpath);
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


    $('#up_vip').uploadifive({
        'formData':{'tag':tag},
        'auto' : true,
        'dnd' : false,
        'height' : 20,
        'width' : 58,
        'uploadScript' : up_url,
        'fileObjName' : 'upload',
        'buttonText' : '选择文件',
        'fileType' : 'image/*',
        'multi' : false,
        'fileSizeLimit'   : '2MB',
        'uploadLimit' : 1,
        'queueSizeLimit'  : 1,
        'removeCompleted' : true,
        onUploadComplete : function(file, data) {
            var obj = JSON.parse(data);
            $('#vip_url').val(root+obj.fullpath);
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

    $('#up_material').uploadifive({
        'formData':{'tag':tag},
        'auto' : true,
        'dnd' : false,
        'height' : 20,
        'width' : 58,
        'uploadScript' : up_url,
        'fileObjName' : 'upload',
        'buttonText' : '选择文件',
        'fileType' : '',
        'multi' : false,
        'fileSizeLimit'   : '100MB',
        'uploadLimit' : 1,
        'queueSizeLimit'  : 1,
        'removeCompleted' : true,
        onUploadComplete : function(file, data) {
            var obj = JSON.parse(data);
            $('#material_url').val(root+obj.fullpath);
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

    $('#android_url').uploadifive({

        'auto' : true,
        'dnd' : false,
        'height' : 20,
        'width' : 58,
        'uploadScript' : pack_url,
        'fileObjName' : 'upload',
        'buttonText' : '选择文件',
        'fileType' : '',
        'multi' : false,
        'fileSizeLimit'   : '1000MB',
        'uploadLimit' : 1,
        'queueSizeLimit'  : 1,
        'removeCompleted' : true,
        onUploadComplete : function(file, data) {
            var obj = JSON.parse(data);
            if(obj.state == 1){
                var p = android_pack_path+platform+'/'+tag+'/'+tag+'.apk';
                $('.android_url').val(p);
                    $.post(game_info,{p:p},function (res) {
                        if(res.status == 1){
                            $('.android_package_name').val(res.info.bundleIdentifier);
                            $('.android_version').val(res.info.bundleShortVersionString)
                            var size = res.info.fileSizeStr;
                            var len = size.length - 1;
                            size = size.substr(0,len);
                            $('.android_size').val(size);
                            $('#android_up').val(1);
                        }else{
                            layer.alert(res.info)
                        }
                    })
            }else{
                layer.alert('上传失败')
            }
        },
        onCancel : function(file) {
            $("#frontSide").val("");
            /* 注意：取消后应重新设置uploadLimit */
            $data    = $(this).data('uploadifive'), settings = $data.settings;
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

    $('#ios_url').uploadifive({
        'auto' : true,
        'dnd' : false,
        'height' : 20,
        'width' : 58,
        'uploadScript' : pack_url,
        'fileObjName' : 'upload',
        'buttonText' : '选择文件',
        'fileType' : '',
        'multi' : false,
        'fileSizeLimit'   : '1000MB',
        'uploadLimit' : 1,
        'queueSizeLimit'  : 1,
        'removeCompleted' : true,
        onUploadComplete : function(file, data) {
            var obj = JSON.parse(data);
            if(obj.state == 1){
                var p = ios_pack_path+platform+'/'+tag+'/'+tag+'.ipa';
                $('.ios_url').val(p);
                $.post(game_info,{p:p},function (res) {
                    if(res.status == 1){
                        $('.ios_package_name').val(res.info.bundleIdentifier);
                        $('.ios_version').val(res.info.bundleShortVersionString)
                        var size = res.info.fileSizeStr;
                        var len = size.length - 1;
                        size = size.substr(0,len);
                        $('.ios_size').val(size);
                        $('#ios_up').val(1);
                    }else{
                        layer.alert(res.info)
                    }
                })
            }else{
                layer.alert('上传失败')
            }
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

    $('#ios_super_url').uploadifive({
        'auto' : true,
        'dnd' : false,
        'height' : 20,
        'width' : 58,
        'uploadScript' : super_url,
        'fileObjName' : 'upload',
        'buttonText' : '选择文件',
        'fileType' : '',
        'multi' : false,
        'fileSizeLimit'   : '1000MB',
        'uploadLimit' : 1,
        'queueSizeLimit'  : 1,
        'removeCompleted' : true,
        onUploadComplete : function(file, data) {
            var obj = JSON.parse(data);
            if(obj.state == 1){
                var p = ios_pack_path+platform+'/'+tag+'/'+tag+'.ipa';
                $('.ios_super_url').val(p);
		$.post(game_info,{p:p},function (res) {
                    if(res.status == 1){
                        $('.ios_package_name').val(res.info.bundleIdentifier);
                        $('.ios_version').val(res.info.bundleShortVersionString)
                        var size = res.info.fileSizeStr;
                        var len = size.length - 1;
                        size = size.substr(0,len);
                        $('.ios_size').val(size);
                        $('#super_up').val(1);
                    }else{
                        layer.alert(res.info)
                    }
                })
            }else{
                layer.alert('上传失败')
            }
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