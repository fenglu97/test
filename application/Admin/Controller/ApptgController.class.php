<?php
/*
 * @Descripttion: 
 * @version: 
 * @Author: Sonwen
 * @Date: 2020-01-03 10:17:43
 * @LastEditors  : Sonwen
 * @LastEditTime : 2020-01-09 19:00:26
 */
/**
 * 185手游盒子APP推广
 * @author qing.li
 * @date 2018-10-30
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class ApptgController extends AdminbaseController
{
    public function index()
    {
        $channel_role = SESSION('channel_role');
        if($channel_role =='all')
        {
            $channel = C('MAIN_CHANNEL');
        }
        else
        {
            $channel_role = explode(',',$channel_role);
            $channel = $channel_role[0] ? $channel_role[0] : 1 ;
        }
        $this->url1 = C('BOX_URL').'/box_register.html?c='.$channel;
        $this->url2 = C('FREESUB_DOWNLOAD').'?c='.$channel;
        $this->url3 = C('BOX_URL').'/index/game?channel='.$channel;
        $url1_short = json_decode(curl_get('http://api.ft12.com/api.php?format=json&url='.$this->url1),true);
        $url2_short = json_decode(curl_get('http://api.ft12.com/api.php?format=json&url='.$this->url2),true);
        $url3_short = json_decode(curl_get('http://api.ft12.com/api.php?format=json&url='.$this->url3),true);

        $this->url1_short = $url1_short['url']?$url1_short['url']:'';
        $this->url2_short = $url2_short['url']?$url2_short['url']:'';
        $this->url3_short = $url3_short['url']?$url3_short['url']:'';

        $this->display();
    }


    public function trust_develop()
    {
        $this->display();
    }

    public function instruction()
    {
        $this->display();
    }
}