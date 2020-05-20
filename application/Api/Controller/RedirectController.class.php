<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/5/25
 * Time: 14:29
 */

namespace Api\Controller;
use Common\Controller\AppframeController;

class RedirectController extends AppframeController{

    public function url(){
        $tag = I('tag');
        $info = M('spread')->where(array('tag'=>$tag))->find();

        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            M('spread')->where(array('tag'=>$tag))->setInc('ios_click');
            redirect($info['ios_url']);
        }else{
            M('spread')->where(array('tag'=>$tag))->setInc('android_click');
            redirect($info['android_url']);
        }
    }

    public function update(){
        sp_get_routes(true);
    }
}