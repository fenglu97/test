<?php
/**
 * 推广用户中心接口
 * @author qing.li
 * @date 2017-12-6
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class PromoteruserController extends AppframeController
{
	public function login()
	{

		$mobile = I('mobile');
		$password = I('password');
		$code = I('verify');

		if(empty($mobile) || empty($password))
		{
			$this->ajaxReturn(null,'手机或者密码不能为空',0);
		}

		if(!preg_match("/^1[34578]\d{9}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机号码格式有误',0);
		}

		//验证码
		if(!sp_check_verify_code())
		{
			$this->ajaxReturn(null,'验证码错误',0);
		}
		else
		{
			$user_model = D("Common/Users");
			$where['mobile'] = $mobile;
			$where['user_login'] = $mobile;
			$where['_logic'] = 'OR';

			$result = $user_model->where($where)->find();

			if(!empty($result) && $result['user_type']==1){
				if(sp_compare_password($password,$result['user_pass'])){

					$role_user_model=M("RoleUser");

					$role_user_join = C('DB_PREFIX').'role as b on a.role_id =b.id';

					$groups=$role_user_model->alias("a")->join($role_user_join)->where(array("user_id"=>$result["id"],"status"=>1))->getField("role_id",true);


					if($groups[0] !=10)
					{
						$this->ajaxReturn(null,'非法登录',0);
					}
					
					if($result['user_status'] == 0){
						$this->ajaxReturn(null,'账号被冻结',0);
					}

					//登入成功页面跳转
					session('ADMIN_ID',$result["id"]);
					session('name',$result["user_login"]);

					$userrights = M('userrights')->where(array('userid'=>$result['id']))->find();
					session('game_role',empty($userrights['game_role'])?'empty':$userrights['game_role']);

					session('channel_role',empty($userrights['channel_role'])?'empty':$userrights['channel_role']);

					//获取用户游戏权限 和 渠道权限

					$result['last_login_ip']=get_client_ip(0,true);
					$result['last_login_time']=date("Y-m-d H:i:s");
					$user_model->save($result);
					cookie("admin_username",$mobile,3600*24*30);

					$data = array('url'=>'http://'.$_SERVER['HTTP_HOST'].U("Admin/Index/index"));
					$this->ajaxReturn($data,'登录成功');
				}
				else
				{
					$this->ajaxReturn(null,L('PASSWORD_NOT_RIGHT'),0);
				}
			}
			else
			{
				$this->ajaxReturn(null,L('USERNAME_NOT_EXIST'),0);
			}
		}
	}

	public function register()
	{
		$password = I('password');
		$mobile = I('mobile');
		$code = I('code');
		$qq = I('qq');
		$email = I('email');

		if(empty($password) || empty($mobile) || empty($code))
		{
			$this->ajaxReturn(null,'密码、手机和验证码不能为空',0);
		}

		if(!preg_match("/^1[34578]\d{9}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机格式有误',0);
		}
		
		$pass_len = strlen($password);

		if($pass_len<6 || $pass_len>16)
		{
			$this->ajaxReturn(null,'密码长度为6-16位',0);
		}

		$user_model = D("Common/Users");

		$map = array('mobile'=>$mobile);

		$exists_mobile = $user_model->where(array('mobile'=>$mobile))->count();

		if($exists_mobile >0)
		{
			$this->ajaxReturn(null,'手机号已存在',0);
		}

		if(!empty($email))
		{
			$checkmail="/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
			if(!preg_match($checkmail,$email))
			{
				$this->ajaxReturn(null,'邮箱格式不正确',0);
			}
			$where['user_email'] = $email;
			$exists_email = $user_model->where(array('user_email'=>$email))->count();

			if($exists_email>0)
			{
				$this->ajaxReturn(null,'邮箱已经存在',0);
			}
		}



		$res = checkSMSCode($mobile,$code);
		if($res == 1)
		{
			$this->ajaxReturn(null,'验证码过期',0);
		}
		elseif($res == 2)
		{
			$this->ajaxReturn(null,'验证码错误',0);
		}

		//密码加密
		if(!empty($password) && strlen($password)<25){
			$password = sp_password($password);
		}

		$data = array(
		'user_login'=>$mobile,
		'user_pass'=>$password,
		'mobile'=>$mobile,
		'qq'=>$qq,
		'user_email'=>$email,
		'create_time'=>date('Y-m-d H:i:s'),
		);

		$uid = $user_model->add($data);
		if($uid !==false)
		{
			$role_id = C('TG_ROLD_ID');
			$role_user_model=M("RoleUser");
			$role_user_model->add(array("role_id"=>$role_id,"user_id"=>$uid));
			$role_info = M('role')->where(array('id'=>$role_id))->find();

			//如果不展示用户数据 权限为all
			$data = array();

			if($role_info['display_userrights'] == 0)
			{
				$data['game_role'] = 'all';
				$data['channel_role'] = 'all';
			}
			else
			{
				$data['game_role'] = '';
				$data['channel_role'] = '';
			}
			$data['userid'] = $uid;
			M('userrights')->add($data);
			session('ADMIN_ID',$uid);
			session('name',$mobile);
			session('game_role',empty($data['game_role'])?'empty':$data['game_role']);
			session('channel_role',empty($data['channel_role'])?'empty':$data['channel_role']);

			//注册成功送奖励
			
			$register_bonus = C('TG_REGISTER_BONUS');
			
			$time = time();
			$data = array(
			'uid'=>$uid,
			'usableMoney'=>$register_bonus,
			'create_time'=>$time
			);
			
			if(M('personalinfo')->add($data)!==false)
			{
				$log = array(
				'uid'=>$uid,
				'type'=>4,
				'money'=>$register_bonus,
				'money_count'=>$register_bonus,
				'create_time'=>$time
				);
				M('withdraw_log')->add($log);
			}
			
			$data = array('url'=>'http://'.$_SERVER['HTTP_HOST'].U("Admin/Index/index"));

			$this->ajaxReturn($data,'注册成功');
		}
		else
		{
			$this->ajaxReturn(null,'注册失败',0);
		}

	}



	public function forget_password()
	{
		$mobile = I('mobile');
		$code = I('code');
		$password = I('password');

		if(empty($password) || empty($mobile) || empty($code))
		{
			$this->ajaxReturn(null,'密码、手机和验证码不能为空',0);
		}

		if(!preg_match("/^1[34578]\d{9}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机格式有误',0);
		}

		$res = checkSMSCode($mobile,$code);
		if($res == 1)
		{
			$this->ajaxReturn(null,'验证码过期',0);
		}
		elseif($res == 2)
		{
			$this->ajaxReturn(null,'验证码错误',0);
		}

		$user_model = D("Common/Users");
		$data['user_pass']=sp_password($password);

		$res=$user_model
		->where(array('mobile'=>$mobile))
		->save($data);
		
		if($res!==false)
		{
			$this->ajaxReturn(null,'修改成功');
		}
		else
		{
			$this->ajaxReturn(null,'修改失败',0);
		}
	}
	
	public function is_read_study()
	{
		$admin_id = get_current_admin_id();

		$is_read_study = M('users')->where(array('id'=>$admin_id))->getfield('is_read_study');
		
		$this->ajaxReturn($is_read_study);
	}
	
	public function confirm_study()
	{
		$admin_id = get_current_admin_id();
		
		if(M('users')->where(array('id'=>$admin_id))->save(array('is_read_study'=>1))!==false)
		{
			$this->ajaxReturn(null,'确认成功');
		}
		else 
		{
			$this->ajaxReturn(null,'确认失败',0);
		}
	}
	
	public function register_bonus()
	{
		$this->ajaxReturn(C('TG_REGISTER_BONUS'));
	}
}

