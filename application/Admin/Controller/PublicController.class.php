<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Tuolaji <479923197@qq.com>
// +----------------------------------------------------------------------
/**
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;
class PublicController extends AdminbaseController {

    public function _initialize() {
        C(S('sp_dynamic_config'));//加载动态配置
    }
    
    //后台登陆界面
    public function login() {
        $admin_id=session('ADMIN_ID');
    	if(!empty($admin_id)){//已经登录
    		
    		redirect(U("admin/index/index"));
    	}else{
    	    $site_admin_url_password =C("SP_SITE_ADMIN_URL_PASSWORD");
    	    $upw=session("__SP_UPW__");
    		if(!empty($site_admin_url_password) && $upw!=$site_admin_url_password){
    			redirect(__ROOT__."/");
    		}else{
    		    session("__SP_ADMIN_LOGIN_PAGE_SHOWED_SUCCESS__",true);
    			$this->display(":login");
    		}
    	}
    }
    
    public function logout(){
    	session(null);
    	redirect(__ROOT__."/");
    }
    
   public function dologin(){
		if(!is_dir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time())))
		{
			mkdir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time()),0777);
		}

		$file_name = SITE_PATH."data/log/bisdk/".date('Y-m-d',time())."/login.log";

		$_REQUEST['ip'] = get_client_ip(0,true);

		$log = date('Y-m-d H:i:s',time())."\r\n".ACTION_NAME."\r\n".urldecode(http_build_query($_REQUEST))."\r\n\r\n";

		file_put_contents($file_name,$log,FILE_APPEND);


		$login_page_showed_success=session("__SP_ADMIN_LOGIN_PAGE_SHOWED_SUCCESS__");
		if(!$login_page_showed_success){
			$this->error('login error!');
		}

		$type = I('post.type')?I('post.type'):1;

		$name = I("post.username");
		if(empty($name)){
			$this->error(L('USERNAME_OR_EMAIL_EMPTY'));
		}
		if($type == 1)
		{
			$pass = I("post.password");
			if(empty($pass)){
				$this->error(L('PASSWORD_REQUIRED'));
			}
			$verrify = I("post.verify");
			if(empty($verrify)){
				$this->error(L('CAPTCHA_REQUIRED'));
			}
			//验证码
			if(!sp_check_verify_code()){
				$this->error(L('CAPTCHA_NOT_RIGHT'));
			}
		}
		else
		{
			$mobile = I("post.mobile");
			if(empty($mobile)){
				$this->error(L('手机号不能为空'));
			}
			$code = I("post.code");
			if(empty($code)){
				$this->error(L('短信验证码不能为空'));
			}
		}


		$user = D("Common/Users");
		if(strpos($name,"@")>0){//邮箱登陆
			$where['user_email']=$name;
		}else{
			$where['user_login']=$name;
		}

		$result = $user->where($where)->find();

//		if ($result['id'] == 1)
//		{
//			//$this->error('用户不存在');
//		}

		if(!empty($result) && $result['user_type']==1){
			if($type == 1)
			{
				$login = sp_compare_password($pass,$result['user_pass']);
			}
			else
			{
				if($result['mobile'] != $mobile)
				{
					$this->error('手机号码与账号不存在绑定关系！');
				}

				$res = checkSMSCode($mobile,$code);
				if($res == 0) $login = true;
			}
			if($login) {

				$role_user_model = M("RoleUser");

				$role_user_join = C('DB_PREFIX') . 'role as b on a.role_id =b.id';

				$groups = $role_user_model->alias("a")->join($role_user_join)->where(array("user_id" => $result["id"], "status" => 1))->getField("role_id", true);

				if($result['id'] != 1 && $groups[0] !=1 )
				{
					$allow_login = 1;

					if(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')) == 'sdk')
					{
						$allow_login = 0;
					}
					elseif(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')) == 'cps')
					{

						if($groups[0] !=4 && $groups[0] !=14)
						{
							$allow_login = 0;
						}
					}
					elseif(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')) == 'cp')
					{
						if($groups[0] !=5)
						{
							$allow_login = 0;
						}
					}
					elseif(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')) == 'ztg')
					{
						if($groups[0] !=15)
						{
							$allow_login = 0;
						}
					}
					elseif(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')) == 'dept')
					{
						if($groups[0] ==4 || $groups[0] == 5 ||$groups[0] ==14 ||$groups[0] ==15 ||$groups[0] ==29 ||$groups[0] ==30  )
						{
							$allow_login = 0;
						}
					}

					if($allow_login == 0)
					{
						$this->error('非法登录');
					}
				}

				if ($type == 1) {
					$mobile_verify = M('role')->where(array('id' => $groups[0]))->getfield('mobile_verify');

//					if (($mobile_verify == 1 && $result['mobile_verify'] == 1) || $result['id'] == 1) {
//						if(!empty($result['mobile']))
//						{
//							$this->error('mobile_verify is on');
//						}
//						else
//						{
//							$this->error('该账号未绑定手机号，请联系管理员进行绑定');
//						}
//
//					}
				}


				if( $result["id"]!=1 && ( empty($groups) || empty($result['user_status'])) ){
					$this->error(L('USE_DISABLED'));
				}
				//登入成功页面跳转
				session('ADMIN_ID',$result["id"]);
				session('name',$result["user_login"]);
				session('ROLE_ID',$groups[0]);
				if($result['id'] == 1)
				{
					session('game_role','all');
					session('channel_role','all');
				}
				else
				{
					$userrights = M('userrights')->where(array('userid'=>$result['id']))->find();
					session('game_role',empty($userrights['game_role'])?'empty':$userrights['game_role']);

					session('channel_role',empty($userrights['channel_role'])?'empty':$userrights['channel_role']);
				}

				//获取用户游戏权限 和 渠道权限

				$result['last_login_ip']=get_client_ip(0,true);
				$result['last_login_time']=date("Y-m-d H:i:s");
				$user->save($result);
				cookie("admin_username",$name,3600*24*30);

				$this->success(L('LOGIN_SUCCESS'),U("Index/index"));
			}else{
				if($type == 1)
				{
					$this->error(L('PASSWORD_NOT_RIGHT'));
				}
				else
				{
					if($res == 1)
					{
						$this->error(L('验证码过期'));
					}
					else
					{
						$this->error(L('验证码错误'));
					}
				}

			}
		}else{
			$this->error(L('USERNAME_NOT_EXIST'));
		}

	}

}