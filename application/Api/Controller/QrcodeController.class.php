<?php
/**
 * 二维码插件
 */

namespace Api\Controller;
use Think\Controller;

class QrcodeController extends Controller
{
    /**
     * 二维码生成
     */
    public function qrcode($level=3){

        $size = I('size')?I('size'):3;
        $url = I('url');
        Vendor('phpqrcode.phpqrcode');
        $errorCorrectionLevel =intval($level) ;//容错级别
        $matrixPointSize = intval($size);//生成图片大小
        //生成二维码图片
        //echo $_SERVER['REQUEST_URI'];
        $object = new \QRcode();

        $object->png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);
    }
    

}