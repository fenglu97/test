<?php
/**
 * 数据上报
 * @author qing.li
 * @date 2018-04-23
 */

namespace Api\Controller;
use Common\Controller\AppframeController;

class GdtstaticController extends AppframeController
{

    private $sign_key = 'aff593202b53e309';
    private $encrypt_key = 'BAAAAAAAAAAAbsAY';
    private $appid = '1106415597';
    private $advertiser_id = '7258136';
    private $url = 'http://t.gdt.qq.com/conv/app/';

    public function get_click()
    {
        if((I('get.appid') ==$this->appid && I('get.advertiser_id') == $this->advertiser_id))
        {
            M('gdt_static')->add(I('get.'));
            echo json_encode(array('ret'=>0,'msg'=>'ok'));
        }

    }

    public function report_data()
    {
        $device_id = I('device_id');
        $conv_type = I('conv_type');
        $app_type = I('app_type');


        $arr = array(
            'device_id'=>$device_id,
            'conv_type'=>$conv_type,
            'app_type'=>$app_type,
            'sign'=>I('sign')
        );
        

        $res = checkSign($arr,C('API_KEY'));
        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        if($app_type == 'android')
        {
            $device_id = md5(strtolower($device_id));
        }
        else
        {
            $device_id = md5(strtoupper($device_id));
        }

        $info = M('gdt_static')->where(array('muid'=>$device_id))->find();
        if($info)
        {

            $query_string = "click_id={$info['click_id']}&muid={$info['muid']}&conv_time=".time()."&value=0";


            $page = $this->url.$this->appid.'/conv?'.$query_string;
         //   echo $page;

            $encode_page = urlencode($page);

            $property=$this->sign_key.'&GET&'.$encode_page;



            $signature = md5($property);


            $base_data = $query_string.'&sign='.urlencode($signature);



            $data = base64_encode(xor_enc($base_data,$this->encrypt_key));
            $data = str_replace(PHP_EOL, '', $data);

            $url = "{$this->url}{$this->appid}/conv?v={$data}&conv_type={$conv_type}&app_type={$app_type}&advertiser_id={$this->advertiser_id}";

        //   echo $url;die;

            $res = json_decode(curl_get($url),true);


            if(!is_dir(SITE_PATH."data/log/185sy/".date('Y-m-d',time())))
            {
                mkdir(SITE_PATH."data/log/185sy/".date('Y-m-d',time()),0777);
            }

            $file_name = SITE_PATH."data/log/185sy/".date('Y-m-d',time())."/gdt.log";

            $log = date('Y-m-d H:i:s',time())."\r\n".ACTION_NAME."\r\n".urldecode(http_build_query($_REQUEST))."\r\n";
            $log .= var_export($res,TRUE)."\r\n\r\n";

            file_put_contents($file_name,$log,FILE_APPEND);

            if($res['ret'] == 0)
            {
                $this->ajaxReturn(null,'成功');
            }

            $this->ajaxReturn(null,'失败',0);

        }

        $this->ajaxReturn(null,'匹配失败',0);


    }



}