<?php

// +----------------------------------------------------------------------

// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]

// +----------------------------------------------------------------------

// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.

// +----------------------------------------------------------------------

// | Author: Tuolaji <479923197@qq.com>

// +----------------------------------------------------------------------

/**

 * 参    数：

 * 作    者：lht

 * 功    能：OAth2.0协议下第三方登录数据报表

 * 修改日期：2013-12-13

 */

namespace Api\Controller;

use Common\Controller\AppframeController;

class IndexController extends AppframeController {



	//设置

	function index(){

	echo 'The server is running successfully';
	}

	public function map()
    {
        $domain = C('API_URL');
        $h5_url = C('h5_domain_url');
		$map = array(
            'DOAMIN' => $domain,//APP接口地址
            'H5_LOGIN_MOBILE' => C('API_URL').'/api/H5sdk/login_account.html',//H5登录
            'H5_MOBILE_PAY' => C('API_URL').'/api/H5sdk/pay.html',//H5支付
            'H5_USER_CENTER' => $h5_url.'/user',
            'USER_INIT'=>$domain.'/index.php?g=api&m=user&a=do_init', //初始化接口
			'USER_CENTER'=>$domain.'/index.php?g=api&m=userbox&a=user_center_sdk',//sdk用户中心
            'REPORT_DATA'=>$domain.'/index.php?g=api&m=user&a=report_data',//上报玩家数据
            'PAY_QUERY' => $domain.'/index.php?g=api&m=pay&a=payQuery',//支付查询
	    'VIP_QUERY' => $domain.'/index.php?g=api&m=pay&a=vipQuery',//vip支付查询
            'SDK_ICON' => $domain.'/public/images/sdk_icon.png',
            'ICON_LEFT_LABEL' => $domain.'/public/images/icon_left_label.png',
			'ICON_RIGHT_LABEL' => $domain.'/public/images/icon_right_label.png',
			'HEART_BEAT'=>$domain.'/index.php?g=api&m=user&a=onlineDate', // 防沉迷心跳接口
        );
        $this->ajaxReturn($map);
    }

	

	//设置

	function setting_post(){

		if($_POST){

			$qq_key=$_POST['qq_key'];

			$qq_sec=$_POST['qq_sec'];

			$sina_key=$_POST['sina_key'];

			$sina_sec=$_POST['sina_sec'];

			$google_key=$_POST['google_key'];

			$google_sec=$_POST['google_sec'];

			$facebook_key=$_POST['facebook_key'];

			$facebook_sec=$_POST['facebook_sec'];

			$twitter_key=$_POST['twitter_key'];

			$twitter_sec=$_POST['twitter_sec'];

			$host=sp_get_host();

			$call_back = $host.__ROOT__.'/index.php?g=api&m=oauth&a=callback&type=';

			$data = array(

					'THINK_SDK_QQ' => array(

							'APP_KEY'    => $qq_key,

							'APP_SECRET' => $qq_sec,

							'CALLBACK'   => $call_back . 'qq',

					),

					'THINK_SDK_SINA' => array(

							'APP_KEY'    => $sina_key,

							'APP_SECRET' => $sina_sec,

							'CALLBACK'   => $call_back . 'sina',

					),

					'THINK_SDK_GOOGLE' => array(

							'APP_KEY'    => $google_key,

							'APP_SECRET' => $google_sec,

							'CALLBACK'   => $call_back . 'google',

					),

					'THINK_SDK_FACEBOOK' => array(

							'APP_KEY'    => $facebook_key,

							'APP_SECRET' => $facebook_sec,

							'CALLBACK'   => $call_back . 'facebook',

					),

					'THINK_SDK_TWITTER' => array(

							'APP_KEY'    => $twitter_key,

							'APP_SECRET' => $twitter_sec,

							'CALLBACK'   => $call_back . 'twitter',

					),

			);

			

			$result=sp_set_dynamic_config($data);

			

			if($result){

				$this->success("更新成功！");

			}else{

				$this->error("更新失败！");

			}

		}

	}
	
	public function clearcache(){
		unlink('./data/runtime/Data/site_options.php'); 
	}
}