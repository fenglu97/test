<?php
/**
 * 免分包
 * @author qing.li
 * @date 2018-10-10
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class FreeSubController extends AppframeController
{

    public function index()
    {

        $system = check_system();

        $appid = I('ap');
        $ip = get_client_ip(0,true);
        $key = $system.$appid.$ip;

        $channel = base64_decode(I('ch'));

        $channel = str_replace(C('FREESUB_KEY').'_','',$channel);
        $channel = substr($channel,0,strpos($channel,'_'));


        if(!is_numeric($channel) ||M('channel')->where(array('id'=>$channel))->count() == 0)
        {

            exit('ch error');
        }

        if(M('freesub_info')->where(array('key'=>$key))->count())
        {
            M('freesub_info')->where(array('key'=>$key))->save(array('value'=>I('ch'),'create_time'=>time()));
        }
        else
        {
            M('freesub_info')->add(array('key'=>$key,'value'=>I('ch'),'create_time'=>time()));

        }

        $_COOKIE['channel'] = I('ch');

        if($system == 2)
        {
            $this->url ='itms-services://?action=download-manifest&url=https://ipa.185sy.com/ios/install/test.plist';
        }
        else
        {
            $this->url = 'http://www.sy218.com/assets/apk/test.apk';
        }

        $this->channel = I('ch');
        $this->display();


    }

    public function get_c()
    {

        $system = I('system');
        if($system==1 || $system==2)
        {
            $appid = I('appid');
            $ip = get_client_ip(0,true);

            $key = $system.$appid.$ip;

            if(!is_dir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time())))
            {
                mkdir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time()),0777);
            }

            $file_name = SITE_PATH."data/log/bisdk/".date('Y-m-d',time())."/freesub.log";

            $log = date('Y-m-d H:i:s',time())."\r\n".ACTION_NAME."\r\n".$key."\r\n\r\n";

            file_put_contents($file_name,$log,FILE_APPEND);

            $value = M('freesub_info')->where(array('key'=>$key))->getfield('value');
            if($value)
            {
                $this->ajaxReturn($value);
            }
            else
            {
                $this->ajaxReturn(null,'no data',0);
            }
        }

        $this->ajaxReturn(null,'no data',0);
    }

}