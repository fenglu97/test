<?php
/**
 * GM权限接口
 * @author qing.li
 * @date 2017-09-29
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class GmpriController extends AppframeController
{
	public function _initialize()
	{
		parent::_initialize();
		$this->gm_pri_model = M('gm_pri');
	}

	/**
	 * 初始化gm权限
	 */
	public function do_init()
	{
		$appid = I('appid');
		$channel = I('channel');
		$serverid = I('serverid');
		$username = I('username');

		if(empty($appid) || empty($channel) || empty($serverid) || empty($username))
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
		'serverid'=>$serverid,
		'username'=>$username,
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

		$data = array();
		if($app_info['gm_pri_id'] == 0)
		{
			$this->ajaxReturn($data);
		}

		$gm_pri_info = M('gm_pri')->where(array('id'=>$app_info['gm_pri_id']))->find();

		$gear_info = json_decode($gm_pri_info['gear_info'],true);

		if(is_array($gear_info))
		{
			$user_gm_model = M('user_gm');
			foreach($gear_info as $v)
			{
				$exists_pri = 0;
				if($user_gm_model->where(array('appid'=>$appid,'serverid'=>$serverid,'username'=>$username,'gm_gear_id'=>$v['gear_id']))->count())
				{
					$exists_pri = 1;
				}
				$data[] = array(
				'gear_id'=>$v['gear_id'],
				'gear_name'=>$v['gear_name'],
				'gear_money'=>$v['gear_money'],
				'exsits_pri'=>$exists_pri
				);
			}
		}
		$this->ajaxReturn($data);
	}


	/**
	 * 获取档位道具内容
	 */
	public function get_prop()
	{
        $appid = I('appid');
        $gear_id = I('gear_id');
        if(empty($appid) || empty($gear_id))
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
		'gear_id'=>$gear_id,
		'sign'=>I('sign'),
		);
		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}
		

	    if(!$app_info['gm_pri_id'])
	    { 
	    	$this->ajaxReturn(null,'不存在GM权限',37);
	    }
		
	    $url = $this->gm_pri_model->where(array('id'=>$app_info['gm_pri_id']))->getfield('get_prop_url');
	    
	    if(!$url)
	    {
	    	$this->ajaxReturn(null,'道具列表地址未对接',38);
	    }
		
	    $post_data = array(
		'powerID'=>$gear_id,
		);
		

	    $str = 'powerID='.$gear_id.'&key='.$app_info['server_key'];
	    	    	    
	    $sign = md5($str);
	    
	    
		$res = curl_get($url.'?powerID='.$gear_id.'&sign='.$sign);

		$res = json_decode($res,true);

		if($res['state'] == 1)
		{
			$this->ajaxReturn($res['data']);
		}else
		{
			$this->ajaxReturn(null,'请求道具失败',0);
		}
	}

	/**
	 * 发送道具
	 */
	public function send_prop()
	{
		$appid = I('post.appid');
		$username = I('post.username');
		$serverid = I('post.serverid');
		$role_id = I('post.role_id');
		$role_name = I('post.role_name');
		$gear_id = I('post.gear_id');
		$prop_id = I('post.prop_id');
		$prop_num = I('post.prop_num');


		if(empty($appid) || empty($username) || empty($serverid) || empty($role_id) || empty($role_name) || empty($gear_id) || empty($prop_id) || empty($prop_num))
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
		'username'=>$username,
		'serverid'=>$serverid,
		'role_id'=>$role_id,
		'role_name'=>$role_name,
		'gear_id'=>$gear_id,
		'prop_id'=>$prop_id,
		'prop_num'=>$prop_num,
		'sign'=>I('sign'),
		);
		$res = checkSign($arr,$app_info['client_key']);
		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}
		
	    if(!$app_info['gm_pri_id'])
	    { 
	    	$this->ajaxReturn(null,'不存在GM权限',37);
	    }
	    
	    $user_gear = M('user_gm')->where(array('appid'=>$appid,'serverid'=>$serverid,'username'=>$username,'gm_gear_id'=>$gear_id))->count();
	    
	    if($user_gear < 1)
	    {
	    	$this->ajaxReturn(null,'用户没有该档位权限',40);
	    }
		
	    $url = $this->gm_pri_model->where(array('id'=>$app_info['gm_pri_id']))->getfield('send_prop_url');
	    
	    if(!$url)
	    {
	    	$this->ajaxReturn(null,'发送道具地址未对接',39);
	    }
		
		$post_data = array(
		'username'=>$username,
		'serverID'=>$serverid,
		'roleID'=>$role_id,
		'roleName'=>$role_name,
		'powerID'=>$gear_id,
		'id'=>$prop_id,
		'count'=>$prop_num,
		'time'=>time(),
		);
		
	    //签名sign
        $str = '';
	    foreach ($post_data as $k=>$v)
	    {
	    	$str .= $k.'='.$v.'&';
	    }
	    
	    $str = $str.'key='.$app_info['server_key'];
	    
	    
	    $sign = md5(trim($str,'&'));
	    
	    $post_data['sign'] = $sign;
		
		$data = curl_post($url,$post_data);

		
		if(strtolower($data) == 'success')
		{
			$this->ajaxReturn(null,'发送成功');
		}
		else 
		{
			$this->ajaxReturn(null,'发送失败',0);
		}
		
	}

}
