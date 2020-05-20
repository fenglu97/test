<?php
/**
 * 用户中心接口
 * @author qing.li
 * @date 2017-08-30
 */
namespace Api\Controller;
use Common\Controller\AppframeController;
use Think\Log;

class UserController extends AppframeController
{
	private $msg_valid_time = 300;
	private $token_valid_time = 300;
	private $pay_page_size = 10;

	public function _initialize()
	{
		parent::_initialize();

		$appid = I('appid');

		if(!is_dir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time())))
		{
			mkdir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time()),0777);
		}

		$file_name = SITE_PATH."data/log/bisdk/".date('Y-m-d',time())."/{$appid}_user.log";

		$log = date('Y-m-d H:i:s',time())."\r\n".ACTION_NAME."\r\n".urldecode(http_build_query($_REQUEST))."\r\n\r\n";

		file_put_contents($file_name,$log,FILE_APPEND);

		$this->player_model = M('player');
		$this->player_info_model = M('player_info');
		$this->player_login_logs_model = M('player_login_logs'.date('Ym',time()));
	}

	/**
	 * 初始化
	 */
	public function do_init()
	{
	    diylogs('doinit',$_POST);
		$appid = I('appid');
		$channel = I('channel');
		$version = I('version');
		$system = I('system');
		$machine_code = I('machine_code');
		$ext = I('ext');

		if(empty($appid)  || empty($version) || empty($system) || empty($machine_code))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'appid'=>$appid,
			'channel'=>$channel,
			'version'=>$version,
			'system'=>$system,
			'machine_code'=>$machine_code,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		if(empty($ext)){
			$channel = C('MAIN_CHANNEL');
		}
		else{
			$ext = explode('-',$ext);
			$channel = $ext[0];
		}



		if(M('channel')->where(array('id'=>$channel))->count() == 0)
		{
			$channel = C('MAIN_CHANNEL');
		}
        $channel_info = M('channel')->where(array('id'=>$channel))->find();
		$data = array();
		//查询当前渠道最近的设备号登录
		$player_login_info = $this->player_login_logs_model->
		where(array('channel'=>$channel,'machine_code'=>$machine_code,'system'=>$system))->
		order('create_time DESC')->
		find();


		//查看是否需要更新
		$curr_version_num = ($system == 1)?$app_info['android_version_num']:$app_info['ios_version_num'];
		$curr_version =($system == 1)?$app_info['android_version']:$app_info['ios_version'];
		//需要更新
		if($app_info['update_alert'] == 1 && ($curr_version !=$version))
		{
			if($app_info['is_mandatory_update'] == 1)
			{
				$data['update'] = 1;
			}
			else
			{
				$data['update'] = 2;
			}

			$data['udpate_url'] = $this->_get_update_url($appid,$channel,$system,$curr_version_num,$channel_info['is_auto_fenbo']);

			$data['udpate_url'] = str_replace('sy217','sy218',$data['udpate_url']);

			if(empty($data['udpate_url']) && $data['update'] == 2)
			{
				$data['update'] = 0;
			}

		}
		else
		{
			$data['update'] = 0;
			$data['udpate_url'] = '';
		}

		//获取推送内容
		$map = array();
		$map['cid'] = array('in','0,'.$channel);
		$map['appid'] = array('in','0,'.$appid);
		$map['status'] = 1;
		$map['is_display'] = 1;
		$map['force'] = 1;

		$notice_info = M('notice')
			->field('title,content,add_time')
			->where($map)
			->order('appid desc,cid desc,add_time desc')
			->find();

		$static_conf_field = ($system == 1)?'type,android_key as `key`':'type,ios_key as `key`';

		$system_field = ($system == 1)?'android_key':'ios_key';

		//查询统计配置
		$static_conf_model = M('static_conf');

		$static_conf_info = $static_conf_model->field($static_conf_field)->where(array('appid'=>$appid,'channel'=>$channel,$system_field=>array('neq','')))->select();

		if(!empty($static_conf_info))
		{
			$data['static_type'] = $static_conf_info;
		}
		else
		{
			$static_conf_info = $static_conf_model->field($static_conf_field)->where(array('appid'=>$appid,'channel'=>0,$system_field=>array('neq','')))->select();
			if(!empty($static_conf_info))
			{
				$data['static_type'] = $static_conf_info;
			}
			else
			{
				$static_conf_info = $static_conf_model->field($static_conf_field)->where(array('appid'=>0,'channel'=>$channel,$system_field=>array('neq','')))->select();
				if(!empty($static_conf_info))
				{
					$data['static_type'] = $static_conf_info;
				}
				else
				{
					$static_conf_info = $static_conf_model->field($static_conf_field)->where(array('appid'=>0,'channel'=>0,$system_field=>array('neq','')))->select();
					if(!empty($static_conf_info))
					{
						$data['static_type'] = $static_conf_info;
					}
					else
					{
						$data['static_type'] = array();
					}
				}
			}
		}

		$data['update'] = $app_info['update'] ? $data['update'] : 0;
		$data['appid'] = $appid;
		$data['channel'] = $channel;
		$data['isdisplay_buoy'] = (int)$channel_info['isdisplay_buoy'];
		$data['is_accelerate'] = (int)($channel_info['is_accelerate'] && $app_info['is_accelerate']);
		$data['isdisplay_ad'] = (int)$channel_info['isdisplay_ad'];
		$data['ad_pic'] = ($channel_info['ad_pic'])?'/'.ltrim(ltrim($channel_info['ad_pic'],'\\'),'/'):'';
		$data['ad_url'] = $channel_info['ad_url'];
		$data['username'] = isset($player_login_info['username'])?$player_login_info['username']:'';
		$data['qq'] = $channel_info['qq'];
		$data['bind_mobile_enabled'] = (int)$channel_info['bind_mobile_enabled'];
		$data['name_auth_enabled'] = (int)$channel_info['name_auth_enabled'];
		$data['platform_money_enabled'] = (int)$channel_info['platform_money_enabled'];
		$data['notice_info'] = $notice_info?$notice_info:'';
		$data['register_enabled'] = (int)$channel_info['register_enabled'];
		$data['discount'] = $app_info['discount'];

		if($channel_info['box_download_enabled'] == 1)
		{
			$data['box_url'] = C('185SY_URL').'/box?channel='.$channel;
			$data['box_icon'] = C('185SY_URL').'/themes/template/Public/img/icon_5.png';
			$data['box_pic_url'] = C('185SY_URL').'/themes/template/Public/img/01_icon_tu.png';
		}


		$this->ajaxReturn($data,'初始化成功');
	}

	/**
	 * 用户名注册
	 */
	public function register_by_user()
	{
		//diylogs('register',$_POST);
		$ip_count = $this->_today_Ip_count();
		if($ip_count >= C('IP_REGISTER_LIMIT'))
		{
			$this->ajaxReturn(null,'注册IP超过上限',44);
		}

		$machine_count = $this->_today_device_count(I('post.machine_code'));
		if($machine_count >= C('DEVICE_REGISTER_LIMIT'))
		{
			$this->ajaxReturn(null,'注册设备超过上限',45);
		}

		$rules = array(
			//array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
			array('username', 'require', '账号不能为空！', 1 ),
			array('username','/^[A-Za-z]{1}\w*$/','账号格式错误！',1 , 'regex' ,1),
			array('username','6,16','账号长度为6-16位！',6,'length'), // 验证标题长度
			array('username','','用户名已经存在！',0,'unique',1),
			array('password','require','密码不能为空！',1),
			array('password','6,16','密码长度为6-16位！',6,'length'),
			array('appid', 'require', '应用ID不能为空！', 1 ),
			array('channel', 'require', '渠道ID不能为空！', 1 ),
			array('system','require','系统不能为空',1),
			array('machine_code', 'require', '设备号不能为空！', 1 ),
		);

		if($this->player_model->validate($rules)->create()===false)
		{
			$this->ajaxReturn(null,$this->player_model->getError(),11);
		}

		extract($_POST);

		$register_enabled = M('channel')->where(array('id'=>$channel))->getfield('register_enabled');

		if(!$register_enabled)
		{
			$this->ajaxReturn(null,'请使用推广页面注册账号',45);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}



		$arr = array(
			'username'=>$username,
			'password'=>$password,
			'appid'=>$appid,
			'channel'=>$channel,
			'system'=>$system,
			'maker'=>$maker,
			'mobile_model'=>$mobile_model,
			'machine_code'=>$machine_code,
			'system_version'=>$system_version,
			'sign'=>$sign,
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$channel_info = M('channel')->where(array('id'=>$channel))->find();

		if(!$channel_info)
		{
			$this->ajaxReturn(null,'渠道不存在',4);
		}

		$username=trim($username);
		$password=trim($password);

		//用户名需过滤的字符的正则
		$stripChar = '?<*.>\'"';
		if(preg_match('/['.$stripChar.']/is', $username)==1){
			$this->ajaxReturn(null,'用户名中包含'.$stripChar.'等非法字符！',24);
		}

		//用戶名不能以大寫JS開頭
		if(preg_match('/^JS/',$username) == 1)
		{
			$this->ajaxReturn(null,'用戶名不能以JS开头',24);
		}

		$banned_usernames=explode(",", sp_get_cmf_settings("banned_usernames"));
		if(in_array($username, $banned_usernames)){
			$this->ajaxReturn(null,'此用户名禁止使用！',11);
		}

		$salt = getRandomString(6);
		$time = time();

		$udata=array(
			'username' => $username,
			'password' =>sp_password_by_player($password,$salt),
			'salt'=>$salt,
			'regip' => ip2long(get_client_ip(0,true)),
			'regtime' => $time,
			'appid'=>$appid,
			'channel' => $channel,
			'system' => $system,
			'maker' => $maker,
			'mobile_model' => $mobile_model,
			'machine_code' => $machine_code,
			'system_version' => $system_version,
			'create_time' => $time,
		);
		$rst = $this->player_model->add($udata);
		if($rst){
			$udata['id'] = $rst;
			$this->_login($udata,$machine_code,$system,$appid,$app_info);
		}
		else
		{
			$this->ajaxReturn(null,'注册失败',0);
		}

	}

	/**
	 * 一键试玩
	 */
	public function register_by_trial()
	{
		
	    //diylogs('register',$_POST);
		$ip_count = $this->_today_Ip_count();

		if($ip_count >= C('IP_REGISTER_LIMIT'))
		{
			$this->ajaxReturn(null,'注册IP超过上限',44);
		}

		$machine_count = $this->_today_device_count(I('post.machine_code'));
		if($machine_count >= C('DEVICE_REGISTER_LIMIT'))
		{
			$this->ajaxReturn(null,'注册设备超过上限',45);
		}

		$rules = array(
			//array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
			array('password','require','密码不能为空！',1),
			array('password','6,16','密码长度为6-16位！',6,'length'),
			array('appid', 'require', '应用ID不能为空！', 1 ),
			array('channel', 'require', '渠道ID不能为空！', 1 ),
			array('system','require','系统不能为空',1),
			array('machine_code', 'require', '设备号不能为空！', 1 ),
		);

		if($this->player_model->validate($rules)->create()===false)
		{
			$this->ajaxReturn(null,$this->player_model->getError(),11);
		}
		extract($_POST);

		$register_enabled = M('channel')->where(array('id'=>$channel))->getfield('register_enabled');

		if(!$register_enabled)
		{
			$this->ajaxReturn(null,'请使用推广页面注册账号',45);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'password'=>$password,
			'appid'=>$appid,
			'channel'=>$channel,
			'system'=>$system,
			'maker'=>$maker,
			'mobile_model'=>$mobile_model,
			'machine_code'=>$machine_code,
			'system_version'=>$system_version,
			'sign'=>$sign,
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$channel_info = M('channel')->where(array('id'=>$channel))->find();

		if(!$channel_info)
		{
			$this->ajaxReturn(null,'渠道不存在',4);
		}

		//自动生成账号，以JS开头然后添加ID
		$max_id = M('player')->field('max(id)')->find();
		$username = 'JS'.str_pad($max_id['max(id)']+1,5,"0",STR_PAD_LEFT);

		$password=trim($password);

		$salt = getRandomString(6);
		$time = time();

		$udata=array(
			'username' => $username,
			'password' =>sp_password_by_player($password,$salt),
			'salt'=>$salt,
			'regip' => ip2long(get_client_ip(0,true)),
			'regtime' => $time,
			'appid'=>$appid,
			'channel' => $channel,
			'system' => $system,
			'maker' => $maker,
			'mobile_model' => $mobile_model,
			'machine_code' => $machine_code,
			'system_version' => $system_version,
			'create_time' => $time,
		);
		$rst = $this->player_model->add($udata);
		if($rst){
			$udata['id'] = $rst;
			$this->_login($udata,$machine_code,$system,$appid,$app_info);
		}
		else
		{
			$this->ajaxReturn(null,'注册失败',0);
		}

	}


	/**
	 * 发送短信
	 *
	 */
	function send_message()
	{

		$appid = I('appid');
		$mobile = I('mobile');
		$type = I('type');

		if(empty($appid) || empty($mobile) || empty($type))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}


		if(!preg_match("/^1\d{10}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机号码格式有误',15);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'appid'=>$appid,
			'mobile'=>$mobile,
			'type'=>$type,
			'sign'=>I('sign'),
		);
		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		if($type == 1)
		{
			$word = '注册';
		}
		elseif($type == 2)
		{
			$word = '绑定';
		}
		elseif($type == 3)
		{
			$word = '解绑';
		}
		else
		{
			$word = '找回密码';
		}


		$player = $this->player_model
			->field('id')
			->where(array('mobile'=>$mobile))
			->find();


		if($type == 1 || $type == 2)
		{
			if($player)
			{
				$this->ajaxReturn(null,'手机号已存在',10);
			}
		}
		else
		{
			if(!$player)
			{
				$this->ajaxReturn(null,'手机号不存在',19);
			}
		}


		$num = $this->_createSMSCode();
//        $content = '用户您好，您' . $word . '的验证码是' . $num . '，5分钟输入有效。【'.C('BOX_NAME').'】';
        if(!sendSms($mobile,$num)) {
            $this->ajaxReturn(null,'发送失败',0);
        } else {

			$data['mobile'] = $mobile;
			$data['code'] = $num;
			$smscode = M('smscode');
			$smscodeObj = $smscode->where(array('mobile'=>$mobile))->find();
			if($smscodeObj){
				$data['update_time'] = date('Y-m-d H:i:s');
				$success = $smscode->where(array('mobile'=>$mobile))->save($data);
				if($success !== false){
					$this->ajaxReturn($num,'发送成功');
				}
			}else{
				$data['create_time'] = date('Y-m-d H:i:s');
				$data['update_time'] = $data['create_time'];
				if($smscode->create($data)){
					$id = $smscode->add();
					if($id){
						$this->ajaxReturn($num,'发送成功');
					}
				}
			}
			
			$this->ajaxReturn(null,'发送失败',0);
		}
	}

	/**
	 * 手机注册
	 */
	public function register_by_mobile()
	{
		$ip_count = $this->_today_Ip_count();

		if($ip_count >= C('IP_REGISTER_LIMIT'))
		{
			$this->ajaxReturn(null,'注册IP超过上限',44);
		}

		$machine_count = $this->_today_device_count(I('post.machine_code'));
		if($machine_count >= C('DEVICE_REGISTER_LIMIT'))
		{
			$this->ajaxReturn(null,'注册设备超过上限',45);
		}

		$rules = array(
			//array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
			array('mobile','require','手机号码不能为空',1),
			array('mobile','/^1\d{10}$/','手机格式错误',1,'regex',1),
			array('password','require','密码不能为空！',1),
			array('password','6,16','密码长度为6-16位！',6,'length'),
			array('appid', 'require', '应用ID不能为空！', 1 ),
			array('channel', 'require', '渠道ID不能为空！', 1 ),
			array('system','require','系统不能为空',1),
			array('machine_code', 'require', '设备号不能为空！', 1 ),
			array('code','require','手机验证码不能为空',1 ),
		);

		if($this->player_model->validate($rules)->create()===false)
		{
			$this->ajaxReturn(null,$this->player_model->getError(),11);
		}

		extract($_POST);

		$register_enabled = M('channel')->where(array('id'=>$channel))->getfield('register_enabled');

		if(!$register_enabled)
		{
			$this->ajaxReturn(null,'请使用推广页面注册账号',45);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'code'=>$code,
			'mobile'=>$mobile,
			'password'=>$password,
			'appid'=>$appid,
			'channel'=>$channel,
			'system'=>$system,
			'maker'=>$maker,
			'mobile_model'=>$mobile_model,
			'machine_code'=>$machine_code,
			'system_version'=>$system_version,
			'sign'=>$sign,
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
		//	$this->ajaxReturn(null,'签名错误',2);
		}


		$channel_info = M('channel')->where(array('id'=>$channel))->find();

		if(!$channel_info)
		{
			$this->ajaxReturn(null,'渠道不存在',4);
		}

		$player = $this->player_model
			->field('id')
			->where(array('mobile'=>$mobile))
			->find();

		if($player)
		{
			$this->ajaxReturn(null,'手机已存在',10);
		}

		//验证手机验证码
		$res = $this->_checkSMSCode($mobile,$code);

		if($res == 1)
		{
			$this->ajaxReturn(null,'验证码过期',18);
		}
		elseif($res == 2)
		{
			$this->ajaxReturn(null,'验证码错误',7);
		}


		//自动生成账号，以JS开头
		$max_id = M('player')->field('max(id)')->find();
		$username = 'JS'.str_pad($max_id['max(id)']+1,5,"0",STR_PAD_LEFT);
		$password=trim($password);


		$salt = getRandomString(6);
		$time = time();

		$udata=array(
			'username' => $username,
			'password' =>sp_password_by_player($password,$salt),
			'mobile'=>$mobile,
			'salt'=>$salt,
			'regip' => ip2long(get_client_ip(0,true)),
			'regtime' => $time,
			'appid'=>$appid,
			'channel' => $channel,
			'system' => $system,
			'maker' => $maker,
			'mobile_model' => $mobile_model,
			'machine_code' => $machine_code,
			'system_version' => $system_version,
			'create_time' => $time,
		);

		$rst = $this->player_model->add($udata);
		if($rst){
			$udata['id'] = $rst;
			$this->_login($udata,$machine_code,$system,$appid,$app_info);
		}
		else
		{
			$this->ajaxReturn(null,'注册失败',0);
		}


	}

	/**
	 * 登录
	 */
	public function login()
	{
		$rules = array(
			//array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
			array('username', 'require', '用户名不能为空！', 1 ),
			array('password','require','密码不能为空！',1),
			array('appid','require','应用ID不能为空',1),
			array('channel','require','渠道信息错误，请重启游戏重试',1),
			array('system','require','系统不能为空',1),
			array('type','require','登录类型不能为空',1),
		);
		$is_web = I('post.is_web');
		if($is_web!=1)
		{
			$rules[] = array('machine_code','require','设备号不能为空',1);

		}

		if($this->player_model->validate($rules)->create()===false)
		{
			$this->ajaxReturn(null,$this->player_model->getError(),11);
		}

		extract($_POST);
		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}


		$time = time();
		$arr = array(
			'username'=>$username,
			'type'=>$type,
			'password'=>$password,
			'appid'=>$appid,
			'channel'=>$channel,
			'system'=>$system,
			'machine_code'=>$machine_code,
			'sign'=>$sign
		);

		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}


		$channel_info = M('channel')->where(array('id'=>$channel))->find();

		if(!$channel_info)
		{
			$this->ajaxReturn(null,'渠道不存在',4);
		}

		//如果该账号正在被交易（商品处于审核中，在出售，交易中可使用临时token登陆）
		$uid = M('player')->where(array('username'=>$username))->getField('id');
		$info = M('products')->where(array('uid'=>$uid,'appid'=>$appid,'status'=>array('in','1,2,3')))->find();


		if($info['token'] ==base64_encode($password.C('TOKEN_KEY')) && $info['token_time'] > time())
		{
		
			$result = $this->player_model->where(array('username'=>$username))->find();
			//创建登录token，用于登录验证
			$token = strtolower(md5($result['username'].$app_info['server_key'].uniqid()));
			//写入此次登录信息
			$data = array(
				'last_login_time' => $time,
				'count'=>$result['count']+1,
				'token'=>$token,
				'token_time'=>$time+$this->token_valid_time
			);

			//记录于登录日志
			if($result['first_login_time'] ==0)
			{
				$this->player_model->where(array('id'=>$result["id"]))->save(array('first_login_time'=>$time));
			}


			$log_data = array(
				'uid'=>$result['id'],
				'username'=>$result['username'],
				'appid'=>$appid,
				'channel'=>$result['channel'],
				'system'=>$system,
				'ip'=>ip2long(get_client_ip(0,true)),
				'machine_code'=>$machine_code,
				'create_time'=>$time,
			);

			$this->player_login_logs_model->add($log_data);


			$this->player_model->where("id=".$result["id"])->save($data);

			$player_other_info = $this->player_info_model->where(array('uid'=>$result['id']))->find();


			$max_speed = 5;
			$total_charge = M('player_charge')->where(array('uid'=>$result['id'],'appid'=>$appid))->getfield('total_charge');
			if($total_charge >= 100)
			{
				$max_speed = 10;
			}

			$question_contract_enabled = M('channel')->where(array('id'=>$result['channel']))->getfield('question_contract_enabled');

//			$data = array(
//				'id'=>$result['id'],
//				'token'=>$token,
//				'username'=>($_POST['type'] == 1)?trim($_POST['username']):$result['username'],
//				'mobile'=>$result['mobile']?$result['mobile']:'',
//				'platform_money'=>$result['platform_money']?$result['platform_money']:0,
//				'id_name'=>isset($player_other_info['real_name'])?$player_other_info['real_name']:'',
//				'id_card'=>isset($player_other_info['id_card'])?$player_other_info['id_card']:'',
//				'icon_url'=>'',
//				'max_speed'=>$max_speed,
//				'question_contract_enabled'=>$question_contract_enabled,
//			);
            $app_player = M('app_player')->field('id as app_uid,nick_name')->where(array('id'=>$info['account']))->select();
            $data = array(
                'uid'=>$result['id'],
                'username'=>$result['username'],
                'nick_name'=>$result['nick_name']?$result['nick_name']:$result['username'],
                'token'=>$token,
                'app_uid_top'=>C('APP_UID_TOP'),
                'game_name'=>$app_info['gamename'],
                'list'=>$app_player
            );

			//添加用户游戏
			if(M('player_app')->where((array('username'=>$result['username'],'appid'=>$appid)))->count() == 0)
			{
				@M('player_app')->add(array('username'=>$result['username'],'appid'=>$appid));
				//将185主站游戏玩家数+1
				$game_model = M('game','syo_',C('185DB'));
				@$game_model->where(array('tag'=>$app_info['tag']))->setInc('plays',1);
			}
			$this->ajaxReturn($data,'登陆成功');
		}

		$where['username']=$username;

		if($type == 1)
		{
			$result = $this->player_model->where($where)->find();
		}
		else
		{
			if(!preg_match("/^1\d{10}$/", $username))
			{
				$this->ajaxReturn(null,'手机格式不正确',15);
			}
			$result = $this->player_model->where(array('mobile'=>$username))
				->find();
		}

		if($result)
		{

			if((sp_password_by_player($password,$result['salt'])==$result['password']))
			{
				$this->_login($result,$machine_code,$system,$appid,$app_info);
			}
			else
			{
				$this->ajaxReturn(null,'密码错误',8);
			}

		}else{

			$this->ajaxReturn(null,'用户不存在',5);

		}

	}

	/**
	 * 选择小号进行登陆
	 */
	public function check_switch_user()
	{
		$post_data = I('post.');
		$uid = $post_data['uid'];
		$app_uid = $post_data['app_uid'];
		$token = $post_data['token'];
		$appid = $post_data['appid'];
		$system = $post_data['system'];
		$machine_code= $post_data['machine_code'];


		if(empty($uid) || empty($app_uid) || empty($token) || empty($appid) || empty($system) || empty($machine_code))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$time = time();
		$arr = array(
			'uid'=>$uid,
			'app_uid'=>$app_uid,
			'token'=>$token,
			'appid'=>$appid,
			'system'=>$system,
			'machine_code'=>$machine_code,
			'sign'=>I('post.sign'),
		);

		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}
		//验证token
		$player = $this->player_model->where(array('id'=>$uid))->find();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}
		
		if($player['token']!=$token)
		{
			$this->ajaxReturn(null,'token错误',6);
		}

		if(time()>$player['token_time'])
		{
			$this->ajaxReturn(null,'token过期',17);
		}

		//token验证通过需要将tokentime置0
		//$this->player_model->where(array('id'=>$uid))->save(array('token_time'=>0));

		$app_player = M('app_player')->where(array('uid'=>$uid,'id'=>$app_uid,'appid'=>$appid))->find();

		if(!$app_player)
		{
			$this->ajaxReturn(null,'验证失败',0);
		}

		//如果用户存在 查询用户ID和machine_code是否被封号
		$player_machine = M('player_machine')->
		where(array('machine_code'=>$machine_code,'end_time'=>array('egt',$time)))->
		find();

		if($player_machine)
		{
			$this->ajaxReturn(null,$player_machine['remark'],12);
		}

		$player_closed = M('player_closed')->
		where(array('uid'=>$uid,'type'=>1,'end_time'=>array('egt',$time)))->
		find();

		if($player_closed)
		{
			$this->ajaxReturn(null,$player_closed['remark'],12);
		}

		//写入此次登录信息
		$data = array(
			'last_login_time' => $time,
			'count'=>$player['count']+1,
		);

		$this->player_model->where(array('id'=>$uid))->save($data);


		//创建登录token，用于登录验证
		$token = strtolower(md5($player['username'].$app_info['server_key'].uniqid()));

		$app_player['token'] = $token;
		$app_player['token_time'] = $time+$this->token_valid_time;

		//记录于登录日志
		if($app_player['first_login_time'] ==0)
		{
			$app_player['first_login_time'] = $time;
		}
		$app_player['last_login_time'] = $time;

		M('app_player')->where(array('id'=>$app_uid))->save($app_player);


		$log_data = array(
			'uid'=>$player['id'],
			'username'=>$player['username'],
			'appid'=>$appid,
			'app_uid'=>$app_uid,
			'channel'=>$player['channel'],
			'system'=>$system,
			'ip'=>ip2long(get_client_ip(0,true)),
			'machine_code'=>$machine_code,
			'create_time'=>$time,
		);

		$this->player_login_logs_model->add($log_data);



		$question_contract_enabled = M('channel')->where(array('id'=>$player['channel']))->getfield('question_contract_enabled');

		//添加用户游戏
		if(M('player_app')->where((array('username'=>$player['username'],'appid'=>$appid)))->count() == 0)
		{
			@M('player_app')->add(array('username'=>$player['username'],'appid'=>$appid));
			//将185主站游戏玩家数+1
			//$game_model = M('game','syo_',C('185DB'));
			//@$game_model->where(array('tag'=>$app_info['tag']))->setInc('plays',1);

		}


		//获取SDK未读消息数量
		$map['_string'] = "`uids` = '' or find_in_set({$uid},`uids`)";
		$map['type']  = 3;
		$map['end_time'] = array('egt',time());
		$map['sdk'] = 1;
		$map['appid'] = $appid;
		$msg = M('message')->where($map)->select();
		$umsg = M('user_message')->where(array('uid' => $uid, 'type' => 1))->getField('message_id', true);

		$system = M('player')->where(array('id' => $uid))->getField('system');
		if ($msg) {
			foreach ($msg as $k => $v) {
				if ($v['system'] != 3 && $v['system'] == $system) {
					$msgid[] = $v['id'];
				} elseif ($v['system'] == 3) {
					$msgid[] = $v['id'];
				}
			}
		}

		if (!$umsg) {
			if ($msgid) {
				foreach ($msgid as $k => $v) {
					M('user_message')->add(array('message_id' => $v, 'type' => 1, 'uid' => $uid, 'create_time' => time()));
				}
			}

		} else {
			$id = array_diff($msgid, $umsg);
			if ($id) {
				foreach ($id as $k => $v) {
					M('user_message')->add(array('message_id' => $v, 'type' => 1, 'uid' => $uid, 'create_time' => time()));
				}
			}
		}

		$unread_counts = M('user_message')
			->alias('a')
			->join('left join __MESSAGE__ b on a.message_id=b.id')
			->where(array('a.uid'=>$uid,'a.is_read'=>0,'b.sdk'=>1,'b.appid'=>array('in',"{$appid},0")))
			->count();

		$data = array(
			'uid'=>$player['id'],
			'username'=>$player['username'],
			'app_uid'=>$app_uid,
			'mobile'=>$player['mobile'],
			'token'=>$token,
			'platform_money'=>$player['platform_money']?$player['platform_money']:0,
			'question_contract_enabled'=>$question_contract_enabled,
			'unread_msg'=>(int)$unread_counts,
		);

		$this->ajaxReturn($data,'登陆成功');


	}

	/**
	 * 添加小号
	 */
	public function add_appuid()
	{
		$uid = I('uid');
		$nick_name = I('nick_name');
		$appid = I('appid');
		$system = I('system');
		$machine_code = I('machine_code');

		if(empty($uid) || empty($nick_name) || empty($appid) || empty($system) || empty($machine_code))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}

		if(mb_strlen($nick_name,'utf-8') > 10)
		{
			$this->ajaxReturn(null,'不能超过10位字符',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'uid'=>$uid,
			'nick_name'=>$nick_name,
			'appid'=>$appid,
			'system'=>$system,
			'machine_code'=>$machine_code,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$player = $this->player_model->where(array('id'=>$uid))->find();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		$app_uid_count = M('app_player')->where(array('uid'=>$uid,'appid'=>$appid))->count();

		if($app_uid_count >= C('APP_UID_TOP'))
		{
			$this->ajaxReturn(null,'游戏小号最多能创建'.C('APP_UID_TOP').'个');
		}



		$data = array(
			'uid'=>$uid,
			'channel'=>$player['channel'],
			'appid'=>$appid,
			'nick_name'=>$nick_name,
			'machine_code'=>$machine_code,
			'system'=>$system,
			'ip'=>ip2long(get_client_ip(0,true)),
			'create_time'=>time(),
		);

		if(M('app_player')->add($data)!==false)
		{
			//此处查询需要连接主库 以防止同步时间差带来数据问题
			$list = M('app_player')->field('id as app_uid,nick_name')->where(array('uid'=>$uid,'appid'=>$appid))->limit(C('APP_UID_TOP'))->order('id asc')->select();
			$this->ajaxReturn($list);
		}
		else
		{
			$this->ajaxReturn(null,'添加失败',0);
		}
	}

	/**
	 * 修改小号名称
	 */
	public function edit_appuid_nickname()
	{
		$uid = I('uid');
		$app_uid = I('app_uid');
		$nick_name = I('nick_name');
		$appid = I('appid');

		if(empty($uid) || empty($app_uid) || empty($nick_name) || empty($appid))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}

		if(mb_strlen($nick_name,'utf-8') > 10)
		{
			$this->ajaxReturn(null,'不能超过10位字符',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'uid'=>$uid,
			'app_uid'=>$app_uid,
			'nick_name'=>$nick_name,
			'appid'=>$appid,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		if($this->player_model->where(array('id'=>$uid))->count() == 0)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		if(M('app_player')->where(array('id'=>$app_uid,'uid'=>$uid,'appid'=>$appid))->count())
		{
			if(M('app_player')->where(array('id'=>$app_uid))->save(array('nick_name'=>$nick_name))!==false)
			{
				$this->ajaxReturn(null,'修改成功');
			}
			else
			{
				$this->ajaxReturn(null,'修改失败',0);
			}
		}
		else
		{
			$this->ajaxReturn(null,'游戏小号不存在',0);
		}

	}

	/**
	 * 登录验证
	 */
	public function login_verify()
	{
		$appid = I('appid');
		$userID = I('userID');
		$token = I('token');

		if(empty($appid) || empty($userID) || empty($token))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'appid'=>$appid,
			'userID'=>$userID,
			'token'=>$token,
			'key'=>$app_info['server_key'],
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,'');
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$token_info = M('app_player')->field('token,token_time')->where(array('id'=>$userID))->find();
	

		if($token_info['token']!=$token)
		{
			$this->ajaxReturn(null,'token错误',6);
		}

		if(time()>$token_info['token_time'])
		{
			$this->ajaxReturn(null,'token过期',17);
		}

		//token验证通过需要将tokentime置0
		//$this->player_model->where(array('username'=>$username))->save(array('token_time'=>0));

		$this->ajaxReturn(array('userID'=>$userID,'appid'=>$appid),'成功');

	}
	/**
	 * 绑定手机
	 */
	public function bind_mobile()
	{
		$uid = I('uid');
		$mobile = I('mobile');
		$appid = I('appid');
		$code = I('code');
		if(empty($uid) || empty($mobile) || empty($appid) || empty($code))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}


		if(!preg_match("/^1\d{10}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机号码格式有误',15);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'uid'=>$uid,
			'mobile'=>$mobile,
			'appid'=>$appid,
			'code'=>$code,
			'sign'=>I('sign'),
		);
		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		//		验证手机验证码
		$res = $this->_checkSMSCode($mobile,$code);

		if($res == 1)
		{
			$this->ajaxReturn(null,'验证码过期',18);
		}
		elseif($res == 2)
		{
			$this->ajaxReturn(null,'验证码错误',7);
		}


		//验证手机号码是否存在，如果存在绑定失败
		$mobile_count = $this->player_model->where(array('mobile'=>$mobile))->count();

		if($mobile_count > 0)
		{
			$this->ajaxReturn(null,'手机已绑定',10);
		}

		//查询用户是否存在
		$player = $this->player_model->where(array('id'=>$uid,'status'=>1))->find();
		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		if($player['mobile'])
		{
			$this->ajaxReturn(null,'该用户已经绑定手机,如需重新绑定需解绑',10);
		}

		$res = $this->player_model->where(array('id'=>$uid))->save(array('mobile'=>$mobile));

		if($res)
		{
			$this->ajaxReturn(null,'成功');
		}
		else
		{
			$this->ajaxReturn(null,'失败',0);
		}

	}

	/**
	 * 解绑手机
	 */
	public function unbind_mobile()
	{
		$uid = I('uid');
		$mobile = I('mobile');
		$appid = I('appid');
		$code = I('code');
		if(empty($uid) || empty($mobile) || empty($appid) || empty($code))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}

		if(!preg_match("/^1\d{10}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机号码格式有误',15);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'uid'=>$uid,
			'mobile'=>$mobile,
			'appid'=>$appid,
			'code'=>$code,
			'sign'=>I('sign'),
		);
		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		//		验证手机验证码
		$res = $this->_checkSMSCode($mobile,$code);

		if($res == 1)
		{
			$this->ajaxReturn(null,'验证码过期',18);
		}
		elseif($res == 2)
		{
			$this->ajaxReturn(null,'验证码错误',7);
		}

		//查询用户是否存在
		$player = $this->player_model->where(array('id'=>$uid,'status'=>1))->find();
		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		if($player['mobile'] != $mobile)
		{
			$this->ajaxReturn(null,'解绑手机不是该用户的手机号码',36);
		}

		$res = $this->player_model->where(array('id'=>$uid))->save(array('mobile'=>''));

		if($res)
		{
			$this->ajaxReturn(null,'成功');
		}
		else
		{
			$this->ajaxReturn(null,'失败',0);
		}
	}

	/**
	 * 实名认证
	 */
	public function id_auth()
	{
		$uid = I('uid');
		$real_name = I('real_name');
		$id_card = I('id_card');
		$appid = I('appid');

		if(empty($uid) || empty($real_name) || empty($id_card) || empty($appid))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}

		if(!isChineseName($real_name))
		{
			$this->ajaxReturn(null,'中文名不合法',11);
		}

		if(!validateIDCard($id_card))
		{
			$this->ajaxReturn(null,'身份证号不合法',16);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'uid'=>$uid,
			'real_name'=>$real_name,
			'id_card'=>$id_card,
			'appid'=>$appid,
			'sign'=>I('sign'),
		);
		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$player_info = $this->player_info_model->where(array('uid'=>$uid,'status'=>1))->find();

		$time = time();

		if($player_info)
		{
			$arr['modifiy_time'] = $time;
			$res = $this->player_info_model->where(array('id'=>$player_info['id']))->save($arr);
		}
		else
		{
			$arr['create_time'] = $time;
			$res = $this->player_info_model->add($arr);
		}

		if($res)
		{
			$this->ajaxReturn(null,'成功');
		}
		$this->ajaxReturn(null,'失败',0);

	}

	/**
	 * 修改密码(新、旧密码)
	 */
	public function modify_password()
	{
		$rules = array(
			//array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
			array('id','require','用户ID不能为空',1),
			array('password','require','原密码不能为空！',1),
			array('newpassword','require','新密码不能为空！',1),
			array('newpassword','6,16','密码长度为6-16位！',6,'length'),
			array('appid', 'require', '应用ID不能为空！', 1 ),
		);

		if($this->player_model->validate($rules)->create()===false)
		{
			$this->ajaxReturn(null,$this->player_model->getError(),11);
		}
		extract($_POST);


		$app_info = M('Game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'id'=>$id,
			'appid'=>$appid,
			'password'=>$password,
			'newpassword'=>$newpassword,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}


		$info = $this->player_model->where(array('id'=>$id,'status'=>1))->find();

		if($info['password'] != sp_password_by_player($password,$info['salt']))
		{
			$this->ajaxReturn(null,'原密码错误',8);
		}

		$save['password'] = sp_password_by_player($newpassword,$info['salt']);
		$save['modify_time'] = time();
		if($this->player_model->where(array('id'=>$id))->save($save) !== false)
		{
			$this->ajaxReturn(null,'修改成功');
		}
		else
		{
			$this->ajaxReturn(null,'修改失败',0);
		}
	}

	/**
	 * 修改密码(短信)
	 */
	public function modify_password_msg()
	{
		$mobile = I('mobile');
		$code = I('code');
		$password = I('password');
		$appid = I('appid');

		if(!preg_match('/^1\d{10}$/',$mobile))
		{
			$this->ajaxReturn(null,'手机格式有误',0);
		}

		$pwd_len = strlen($password);
		if($pwd_len>17 || $pwd_len<6)
		{
			$this->ajaxReturn(null,'密码长度为6-16位',0);
		}

		$app_info = M('Game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}
		$arr = array(
			'mobile'=>$mobile,
			'code'=>$code,
			'password'=>$password,
			'appid'=>$appid,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null, '签名错误', 0);
		}

		$info = $this->player_model->where(array('mobile'=>$mobile))->find();

		if(!$info)
		{
			$this->ajaxReturn(null,'手机用户不存在',0);
		}


		$res_sms = $this->_checkSMSCode($mobile,$code);
		if($res_sms == 1)
		{
			$this->ajaxReturn(null,'验证码过期',18);
		}
		if($res_sms == 2)
		{
			$this->ajaxReturn(null,'验证码错误',7);
		}

		$save['password'] = sp_password_by_player($password,$info['salt']);
		$save['modify_time'] = time();

		if($this->player_model->where(array('mobile'=>$mobile))->save($save)!==false)
		{
			$this->ajaxReturn(null,'修改成功');
		}
		$this->ajaxReturn(null,'修改失败',0);

	}

	/**
	 * 生成短信验证码
	 */
	private function _createSMSCode($length = 6)
	{
		$min = pow(10 , ($length - 1));
		$max = pow(10, $length) - 1;
		return rand($min, $max);
	}


	/**
	 * 验证短信
	 */
	private function _checkSMSCode($mobile,$code)
	{
		$nowTimeStr = date('Y-m-d H:i:s');
		$smscode = M('smscode');
		$smscodeObj = $smscode->where("mobile='$mobile'")->find();
		if($smscodeObj){
			$smsCodeTimeStr = $smscodeObj['update_time'];
			$recordCode = $smscodeObj['code'];
			$flag = $this->_checkTime($nowTimeStr, $smsCodeTimeStr);
			if(!$flag){
				return 1;
			}
			if($code != $recordCode){
				return 2;
			}
			//如果验证成功，置为失效
			$smscode->where(array('mobile'=>$mobile))->save(array('update_time'=>'1970-01-01 00:00:00'));
			return 0;
		}
		return 2;
	}

	/**
	 * 验证短信是否过期
	 */
	private function _checkTime($nowTimeStr,$smsCodeTimeStr){
		//$nowTimeStr = '2016-10-15 14:39:59';
		//$smsCodeTimeStr = '2016-10-15 14:30:00';
		$nowTime = strtotime($nowTimeStr);
		$smsCodeTime = strtotime($smsCodeTimeStr);
		if(($nowTime - $smsCodeTime) > $this->msg_valid_time)
		{
			return false;
		}
		return true;

	}



	/**
	 * 验证短信(接口层 用于找回密码)
	 */
	public function check_smscode()
	{
		$mobile = I('mobile');
		$code = I('code');
		$appid =I('appid');

		if(empty($mobile) || empty($code) || empty($appid))
		{
			$this->ajaxReturn(null,'参数错误',11);

		}

		if(!preg_match("/^1\d{10}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机号码格式有误',15);
		}

		$app_info = M('Game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'mobile'=>$mobile,
			'code'=>$code,
			'appid'=>$appid,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}


		//先验证是否存在该手机
		$id = $this->player_model->where(array('mobile'=>$mobile,'status'=>1))->getfield('id');
		if(!$id)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		$res = $this->_checkSMSCode($mobile,$code);
		if($res == 1)
		{
			$this->ajaxReturn(null,'验证码过期',18);
		}
		elseif($res == 2)
		{
			$this->ajaxReturn(null,'验证码错误',7);
		}
		//生成一个md5 ,把md5和有效期入库
		$md5 = md5($mobile.$code.uniqid());
		$save = array(
			'token'=>$md5,
			'token_time'=>date('Y-m-d H:i:s')
		);
		if(M('smscode')->where(array('mobile'=>$mobile))->save($save)!==false)
		{
			$data = array(
				'id'=>$id,
				'token'=>$md5
			);
			$this->ajaxReturn($data,'验证成功');
		}
		$this->ajaxReturn(null,'验证失败',0);
	}



	/**
	 * 找回密码
	 */
	public function forget_password()
	{
		$rules = array(
			//array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
			array('id','require','用户ID不能为空',1),
			array('password','require','新密码不能为空！',1),
			array('password','6,16','密码长度为6-16位！',6,'length'),
			array('appid', 'require', '应用id不能为空！',1),
			array('token','require','token不能为空',1)
		);

		if($this->player_model->validate($rules)->create()===false)
		{
			$this->ajaxReturn(null,$this->player_model->getError(),11);

		}
		extract($_POST);

		$app_info = M('Game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'id'=>$id,
			'password'=>$password,
			'token'=>$token,
			'appid'=>$appid,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}



		$info = $this->player_model->where(array('id'=>$id,'status'=>1))->find();
		if(!$info)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		//验证token是否过期或者不正确
		$smsobj = M('smscode');
		$token_info =$smsobj->field('token,token_time')->where(array('mobile'=>$info['mobile']))->find();
		if(time()-strtotime($token_info['token_time']) > $this->token_valid_time)
		{
			$this->ajaxReturn(null,'token过期',17);
		}

		if($token_info['token'] != $token)
		{
			$this->ajaxReturn(null,'token错误',6);
		}

		$save['password'] = sp_password_by_player($password,$info['salt']);
		$save['modify_time'] = time();

		if($this->player_model->where(array('id'=>(int)$id))->save($save) !== false)
		{

			//密码修改成功后 将token有效期设置为无效
			$smsobj->where(array('mobile'=>$info['mobile']))->save(array('token_time'=>'1970-01-01 00:00:00'));

			$this->ajaxReturn(null,'修改成功');
		}
		else
		{
			$this->ajaxReturn(null,'修改失败',0);
		}
	}

	private function _get_update_url($appid,$channel,$system,$version_num,$auto_fenbao)
	{
		$app_info = M('game')->field('tag,ios_url,android_url')->where(array('status'=>1,'id'=>$appid))->find();
		//如果是主渠道 母包地址
		if($channel == C('MAIN_CHANNEL'))
		{
			return ($system == 1)?$app_info['android_url']:$app_info['ios_url'];
		}
		//其他渠道

		$subpackage_model = ($system == 1)?M('subpackage_android'):M('subpackage_ios');

		$url = $subpackage_model->
		field('fenbao_url')->
		where(array('appid'=>$appid,'cid'=>$channel,'version'=>$version_num))->find();

		if(!$url || empty($url['fenbao_url']))
		{
			if(!$url)
			{
				$data = array(
					'tag'=>$app_info['tag'],
					'appid'=>$appid,
					'cid'=>$channel,
					'version'=>$version_num,
					'auto'=>$auto_fenbao,
				);
				$subpackage_model->add($data);
			}
			$packOne = packOne($system,$app_info['tag'],$channel,$version_num,$appid,1);
			$packOne = json_decode($packOne, true);

			if($packOne['state'] == 1)
			{
				$subpackage_model->
				where(array('appid'=>$appid,'cid'=>$channel,'version'=>$version_num))
					->save(array('status'=>-1,'create_time'=>time(),'modifiy_time'=>time()));
			}
			elseif($packOne['state'] == 6 && $packOne['url'])
			{
				$subpackage_model->
				where(array('appid'=>$appid,'cid'=>$channel,'version'=>$version_num))
					->save(array('status'=>1,'fenbao_url'=>$packOne['url'],'modifiy_time'=>time()));
			}
		}

		return $url['fenbao_url'];

	}
	/**
	 * 用户充值列表
	 */
	public function pay_list_by_user()
	{
		$appid = I('appid');
		$uid = I('uid');
		$page = I('page');

		if(empty($appid) || empty($uid) || empty($page))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}

		$app_info = M('Game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'appid'=>$appid,
			'uid'=>$uid,
			'page'=>$page,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$player = M('player')->
		field('channel')->
		where(array('status'=>1,'id'=>$uid))->
		count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		$map = array(
			'uid'=>$uid,
			'appid'=>$appid
		);

		$map['status'] = array('neq',3);

		$count = M('inpour')
			->where($map)
			->count();

		$page = $page?$page:1;

		$pay_list = M('inpour')
			->field('orderid,DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d %H:%i") create_time,money,status')
			->where($map)
			->order('create_time desc')
			->limit(($page-1)*$this->pay_page_size.','.$this->pay_page_size)
			->select();

		$data = array(
			'count'=>ceil($count/$this->pay_page_size),
			'app_name'=>$app_info['game_name'],
			'list'=>$pay_list?$pay_list:array()
		);

		$this->ajaxReturn($data,'');

	}

	/**
	 * 同步平台币
	 */
	public function refresh_platmoney()
	{
		$appid = I('appid');
		$channel = I('channel');
		$uid = I('uid');

		if(empty($appid) || empty($channel) || empty($uid))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'appid'=>$appid,
			'channel'=>$channel,
			'uid'=>$uid,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}



		$channel_info = M('channel')->where(array('id'=>$channel))->count();

		if(!$channel_info)
		{
			$this->ajaxReturn(null,'渠道不存在',4);
		}

		$player = M('player')->
		field('platform_money')->
		where(array('status'=>1,'id'=>$uid))->
		find();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		$this->ajaxReturn($player['platform_money'],'');

	}

	public function max_speed()
	{
		$appid = I('appid');
		$channel = I('channel');
		$uid = I('uid');

		if(empty($appid) || empty($channel) || empty($uid))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'appid'=>$appid,
			'channel'=>$channel,
			'uid'=>$uid,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$channel_info = M('channel')->where(array('id'=>$channel))->count();

		if(!$channel_info)
		{
			$this->ajaxReturn(null,'渠道不存在',4);
		}
		$max_speed = 5;
		$total_charge = M('player_charge')->where(array('uid'=>$uid,'appid'=>$appid))->getfield('total_charge');
		if($total_charge >= 100)
		{
			$max_speed = 10;
		}

		$this->ajaxReturn($max_speed);

	}

	public function exsits_mobile()
	{
		$appid = I('appid');
		$mobile = I('mobile');

		if(empty($appid) || empty($mobile))
		{
			$this->ajaxReturn(null,'参数错误',11);
		}

		if(!preg_match("/^1\d{10}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机号码格式有误',15);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'appid'=>$appid,
			'mobile'=>$mobile,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$player_info = M('player')->where(array('mobile'=>$mobile))->count();

		$data = array('is_exsists'=>0);

		if($player_info > 0)
		{
			$data['is_exsists'] = 1;
		}


		$this->ajaxReturn($data);

	}

	public function customer_service()
	{

		$channel = I('channel');
		if(empty($channel))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'channel'=>$channel,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		//获取当前渠道的客服信息，如果没有获取主渠道的客服信息
		$channel_info = M('channel')->where(array('id'=>$channel))->find();
		if(empty($channel_info)){
			$channel_info = M('channel')->where(array('id'=>C('MAIN_CHANNEL')))->find();
		}
		$data = array();

		$channel_info['shouyou_qq'] = json_decode($channel_info['shouyou_qq'],true);
		$channel_info['fanli_qq'] = json_decode($channel_info['fanli_qq'],true);
		$channel_info['shouyou_group'] = json_decode($channel_info['shouyou_group'],true);
		$channel_info['box_group'] = json_decode($channel_info['box_group'],true);


		//$data['shouyou_qq'] = (!empty($channel_info['shouyou_qq']['number']))?$channel_info['shouyou_qq']:'';
		//$data['fanli_qq'] = (!empty($channel_info['fanli_qq']['number']))?$channel_info['fanli_qq']:'';
		//$data['shouyou_group'] = (!empty($channel_info['shouyou_group']['link']))?$channel_info['shouyou_group']:'';
		//$data['box_group'] = (!empty($channel_info['box_group']['link']))?$channel_info['box_group']:'';
		$data['shouyou_qq'] = $channel_info['shouyou_qq'];
		$data['fanli_qq'] = $channel_info['fanli_qq'];
		$data['shouyou_group'] = $channel_info['shouyou_group'];
		$data['box_group'] = $channel_info['box_group'];
		if($data['shouyou_group']['weblink']) $data['shouyou_group']['weblink'] = urldecode($data['shouyou_group']['weblink']);
		if($data['box_group']['weblink']) $data['box_group']['weblink'] = urldecode($data['box_group']['weblink']);


		$data['shouyou_qq']['name'] = '手游客服QQ';
		$data['fanli_qq']['name'] = '返利客服QQ';
		$data['shouyou_group']['name'] = '手游玩家群';
		$data['box_group']['name'] = '盒子交流群';

		$this->ajaxReturn($data);


	}


	public function  upload_log()
	{
		$appid = I('appid');

		if(!is_dir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time())))
		{
			mkdir(SITE_PATH."data/log/bisdk/".date('Y-m-d',time()),0777);
		}

		$file_name = SITE_PATH."data/log/bisdk/".date('Y-m-d',time())."/{$appid}_log.log";

		$log = date('Y-m-d H:i:s',time())."\r\n".http_build_query($_REQUEST)."\r\n\r\n";

		file_put_contents($file_name,$log,FILE_APPEND);
	}

	public function mobile_login_v2()
	{
		$post_data = I('post.');
		$mobile = $post_data['mobile'];
		$password = $post_data['password'];
		$appid = $post_data['appid'];
		$channel = $post_data['channel'];
		$system = $post_data['system'];
		$machine_code= $post_data['machine_code'];

		if(empty($mobile) || empty($password) || empty($appid) || empty($channel) || empty($system) || empty($machine_code))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$time = time();
		$arr = array(
			'mobile'=>$mobile,
			'password'=>$password,
			'appid'=>$appid,
			'channel'=>$channel,
			'system'=>$system,
			'machine_code'=>$machine_code,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}


		$channel_info = M('channel')->where(array('id'=>$channel))->find();

		if(!$channel_info)
		{
			$this->ajaxReturn(null,'渠道不存在',4);
		}

		if(!preg_match("/^1\d{10}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机格式不正确',15);
		}
		$user_info = M('business_player')->where(array('mobile'=>$mobile))->find();

		if($user_info)
		{
			$sdkuser_list=M('busi_sdk')->field('sdk_uid,sdk_username,nick_name')->where(array('busi_uid'=>$user_info['id']))->select();
			if(sp_password_by_player($password, $user_info['salt']) == $user_info['password'] &&
				!empty($sdkuser_list))
			{
				//创建登录token，用于登录验证
				$token = strtolower(md5($user_info['username'].$app_info['server_key'].uniqid()));
				M('business_player')->where(array('mobile'=>$mobile))->save(array('token'=>$token,'token_time'=>time()+$this->token_valid_time));

				$this->ajaxReturn(array('list'=>$sdkuser_list,'mobile'=>$mobile,'token'=>$token));

			}
			if(sp_password_by_player($password, $user_info['salt']) != $user_info['password'])
			{
				$wrong_password = 1;
			}

		}

		$result = $this->player_model->where(array('mobile'=>$mobile))
			->find();


		if($result != null)
		{

			//如果用户存在 查询用户ID和machine_code是否被封号
			$player_machine = M('player_machine')->
			where(array('machine_code'=>$machine_code,'end_time'=>array('egt',$time)))->
			find();

			if($player_machine)
			{
				$this->ajaxReturn(null,$player_machine['remark'],12);
			}

			$player_closed = M('player_closed')->
			where(array('uid'=>$result['id'],'end_time'=>array('egt',$time)))->
			find();

			if($player_closed)
			{
				$this->ajaxReturn(null,$player_closed['remark'],12);
			}

			if((sp_password_by_player($password,$result['salt'])==$result['password'])){

				//创建登录token，用于登录验证
				$token = strtolower(md5($result['username'].$app_info['server_key'].uniqid()));
				//写入此次登录信息
				$data = array(
					'last_login_time' => $time,
					'count'=>$result['count']+1,
					'token'=>$token,
					'token_time'=>$time+$this->token_valid_time
				);

				//记录于登录日志
				if($result['first_login_time'] ==0)
				{
					$this->player_model->where(array('id'=>$result["id"]))->save(array('first_login_time'=>$time));
				}


				$log_data = array(
					'uid'=>$result['id'],
					'username'=>$result['username'],
					'appid'=>$appid,
					'channel'=>$result['channel'],
					'system'=>$system,
					'ip'=>ip2long(get_client_ip(0,true)),
					'machine_code'=>$machine_code,
					'create_time'=>$time,
				);

				$this->player_login_logs_model->add($log_data);


				$this->player_model->where("id=".$result["id"])->save($data);

				$player_other_info = $this->player_info_model->where(array('uid'=>$result['id']))->find();


				$max_speed = 5;
				$total_charge = M('player_charge')->where(array('uid'=>$result['id'],'appid'=>$appid))->getfield('total_charge');
				if($total_charge >= 100)
				{
					$max_speed = 10;
				}

				$question_contract_enabled = M('channel')->where(array('id'=>$result['channel']))->getfield('question_contract_enabled');

				$data = array(
					'id'=>$result['id'],
					'token'=>$token,
					'username'=>($_POST['type'] == 1)?trim($_POST['username']):$result['username'],
					'mobile'=>$result['mobile']?$result['mobile']:'',
					'platform_money'=>$result['platform_money']?$result['platform_money']:0,
					'id_name'=>isset($player_other_info['real_name'])?$player_other_info['real_name']:'',
					'id_card'=>isset($player_other_info['id_card'])?$player_other_info['id_card']:'',
					'icon_url'=>'',
					'max_speed'=>$max_speed,
					'question_contract_enabled'=>$question_contract_enabled,
				);

				//添加用户游戏
				if(M('player_app')->where((array('username'=>$result['username'],'appid'=>$appid)))->count() == 0)
				{
					@M('player_app')->add(array('username'=>$result['username'],'appid'=>$appid));
					//将185主站游戏玩家数+1
					$game_model = M('game','syo_',C('185DB'));
					$res = @$game_model->where(array('android_pack_tag'=>$app_info['tag']))->setInc('plays',1);
					if($res===false)
					{
						@$game_model->where(array('ios_tag'=>$app_info['tag']))->setInc('plays',1);
					}
				}

				$this->ajaxReturn($data,'登陆成功');

			}else{

				$this->ajaxReturn(null,'密码错误',8);
			}

		}else{

			if($wrong_password == 1)
			{
				$this->ajaxReturn(null,'密码错误',8);
			}
			else
			{
				$this->ajaxReturn(null,'用户不存在',5);
			}

		}
	}
	
	public function report_data()
	{
		Log::write(json_encode($_REQUEST));
		$type = I('type');
		$channel = I('channel');
		$appid = I('appid');
		$deviceID = I('deviceID');
		$userID = I('userID');
		$serverID = I('serverID');
		$serverName = I('serverName');
		$roleID = I('roleID');
		$roleName = I('roleName');
		$roleLevel = I('roleLevel');
		$money = I('money');
		$vip = I('vip');

		if(empty($type) || empty($channel) || empty($appid) || empty($deviceID) || empty($userID))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'type'=>$type,
			'channel'=>$channel,
			'appid'=>$appid,
			'deviceID'=>$deviceID,
			'userID'=>$userID,
			'serverID'=>$serverID,
			'serverName'=>$serverName,
			'roleID'=>$roleID,
			'roleName'=>$roleName,
			'roleLevel'=>$roleLevel,
			'money'=>$money,
			'vip'=>$vip,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}


		$player_info = M('player')->where(array('id'=>$userID))->find();
		if(!$player_info)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		$arr['channel'] = $player_info['channel'];
		$arr['regTime'] = $player_info['regtime'];
		$arr['createTime'] = time();
		$arr['ip'] = get_client_ip(0,true);
		Log::write(json_encode($arr));
		Log::write('report_data'.date('Ymd'));
		if(M('report_data'.date('Ymd'))->add($arr)!==false)
		{

			if(M('player_appinfo')->where(array('appid'=>$appid,'uid'=>$userID,'server_id'=>$serverID,'role_id'=>$roleID))->count())
			{
				M('player_appinfo')->where(array('appid'=>$appid,'uid'=>$userID,'server_id'=>$serverID,'role_id'=>$roleID))->save(array('role_level'=>$roleLevel));
			}
			else
			{
				M('player_appinfo')->add(array('appid'=>$appid,'uid'=>$userID,
					'server_id'=>$serverID,'role_id'=>$roleID,
					'username'=>$player_info['username'],'role_level'=>$roleLevel,'create_time'=>$arr['createTime']));
			}

		Log::write('SUCCESS'.date('Ymd'));
			$this->ajaxReturn(null,'上报成功');
		}
Log::write('FAIL'.date('Ymd'));
		$this->ajaxReturn(null,'上报失败',0);

	}

	private function _today_Ip_count()
	{
		$ip = ip2long(get_client_ip(0,true));
		$ip_count = M('player')->where(array('regip'=>$ip,'create_time'=>array('egt',strtotime(date('Y-m-d')))))->count();
		return $ip_count;
	}

	private function _today_device_count($machine_code)
	{
		$machine_count = M('player')->where(array('machine_code'=>$machine_code,'create_time'=>array('egt',strtotime(date('Y-m-d')))))->count();
		return $machine_count;
	}

	public function report_sdk_activity()
	{
		$uid = I('uid');
		$action = I('action');
		$system = I('system');
		$appid = I('appid');
		$channel = I('channel');
		$maker = I('maker');
		$mobile_model = I('mobile_model');
		$machine_code = I('machine_code');
		$system_version = I('system_version');
		$mac = I('mac');

		if(empty($action) || empty($system) || empty($appid) || empty($channel) || empty($maker) || empty($mobile_model)
		|| empty($machine_code) || empty($system_version))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();

		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'uid'=>$uid,
			'action'=>$action,
			'system'=>$system,
			'appid'=>$appid,
			'channel'=>$channel,
			'maker'=>$maker,
			'mobile_model'=>$mobile_model,
			'machine_code'=>$machine_code,
			'system_version'=>$system_version,
			'mac'=>$mac,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$arr['ip'] = get_client_ip(0,true);
		$arr['create_time'] = time();
		if(M('sdk_activity')->add($arr)!==false)
		{
			$this->ajaxReturn(null,'上报成功');
		}
		$this->ajaxReturn(null,'上报失败',0);
	}

	public function _login($result,$machine_code,$system,$appid,$app_info)
	{
		$time = time();

		$list = M('app_player')->field('id as app_uid,nick_name')->where(array('uid'=>$result['id'],'appid'=>$appid))->limit(C('APP_UID_TOP'))->order('id asc')->select();

		//$gameinfo = get_185_gameinfo($app_info['tag']);
		$gameinfo = M('game')->where(array('id'=>$appid))->find();
		if(!$list) {
			$app_player = array(
				'uid'=>$result['id'],
				'channel'=>$result['channel'],
				'appid'=>$appid,
				//'nick_name'=>$gameinfo['gamename'].'_小号1',
				'nick_name'=>$gameinfo['game_name'].'_小号1',
				'machine_code'=>$machine_code,
				'system'=>$system,
				'ip'=>ip2long(get_client_ip(0,true)),
				'create_time'=>$time,
			);
			if(M('app_player')->add($app_player)!==false)
			{
				//此处查询需要连接主库 以防止同步时间差带来数据问题
				$list = M('app_player')->field('id as app_uid,nick_name')->where(array('uid'=>$result['id'],'appid'=>$appid))->limit(C('APP_UID_TOP'))->order('id asc')->select();
			}
			else
			{
				$this->ajaxReturn(null,'服务器错误',0);
			}

		}

		//创建登录token，用于登录验证
		$token = strtolower(md5($result['username'].$app_info['server_key'].uniqid()));
		//写入此次登录token信息
		$data['token'] = $token;
		$data['token_time'] = $time+$this->token_valid_time;


		$this->player_model->where(array('id' => $result['id']))->save($data);

		$this->ajaxReturn(array('uid'=>$result['id'],
			'username'=>$result['username'],
			'nick_name'=>$result['nick_name']?$result['nick_name']:$result['username'],
			'token'=>$token,
			'app_uid_top'=>C('APP_UID_TOP'),
			//'game_name'=>$gameinfo['gamename'],
			'game_name'=>$gameinfo['game_name'],
			'list'=>$list));
	}

}
