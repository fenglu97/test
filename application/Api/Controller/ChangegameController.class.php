<?php
/**
 * 转游接口
 * @author qing.li
 * @date 2017-10-30
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class ChangegameController extends AppframeController
{
	private $page_size = 10;
	
    public function get_all_game()
    {
    	$uid = I('uid');
    	$channel = I('channel');
    	
    	if(empty($uid) || empty($channel))
    	{
    		$this->ajaxReturn(null,'参数不能为空',0);
    	}
    	
    	$arr = array(
    	'uid'=>$uid,
    	'channel'=>$channel,
    	'sign'=>I('sign')
    	);
    	
    	$res = checkSign($arr,C('API_KEY'));
    	
    	if(!$res)
    	{
    		$this->ajaxReturn(null,'签名错误',0);
    	}
    	
    	$map = array();
    	
    	if($channel != C('MAIN_CHANNEL'))
    	{
    		$map['display_channel'] = 1;
    	}
    	
    	$map['status'] = 0;
		$map['isdisplay'] = 1;
		
		$list = M('game','syo_',C('185DB'))
		->field('id,gamename')
		->where($map)
		->select();
		
		$this->ajaxReturn($list);
    	
    }
    
    public function apply()
    {
        $uid = I('uid');
        $channel = I('channel');
        $origin_appname = I('origin_appname');
        $origin_servername = I('origin_servername');
        $origin_rolename = I('origin_rolename');
        $new_appname = I('new_appname');
        $new_servername = I('new_servername');
    	$new_rolename = I('new_rolename');
    	$qq = I('qq');
    	$mobile = I('mobile');
    	
    	if(empty($uid) || empty($channel) || empty($origin_appname) || empty($origin_servername) ||
    	empty($origin_rolename) || empty($new_appname) || empty($new_servername) || empty($new_rolename) ||
    	(empty($qq) && empty($mobile)))
    	{
    		$this->ajaxReturn(null,'参数不能为空',0);
    	}
    	
    	$arr = array(
    	'uid'=>$uid,
    	'channel'=>$channel,
    	'origin_appname'=>$origin_appname,
    	'origin_servername'=>$origin_servername,
    	'origin_rolename'=>$origin_rolename,
    	'new_appname'=>$new_appname,
    	'new_servername'=>$new_servername,
    	'new_rolename'=>$new_rolename,
    	'qq'=>$qq,
    	'mobile'=>$mobile,
    	'sign'=>I('sign'),
    	);
    	
        if(!empty($mobile) && !preg_match("/^1\d{10}$/", $mobile))
		{
			$this->ajaxReturn(null,'手机号码格式有误',0);
		}
    	
    	
    	$res = checkSign($arr,C('API_KEY'));
    	
    	if(!$res)
    	{
    		$this->ajaxReturn(null,'签名错误',0);
    	}
    	 	
    	
    	$username = M('player')->field('username')->where(array('id'=>$uid))->find();
    	
    	if(!$username)
    	{
    		$this->ajaxReturn(null,'用户不存在',0);
    	}
    	
    	$channel_info = M('channel')->where(array('id'=>$channel))->count();
		
		$channel = $channel_info?$channel:C('MAIN_CHANNEL');
    	
    	$arr['create_time'] = time();
    	$arr['cid'] = $channel;
    	
    	$arr['username'] = $username['username'];
    	
    	$res = M('change_game_log')->add($arr);
    	
    	if($res)
    	{
    		$this->ajaxReturn(null,'申请成功');
    	}
    	else 
    	{
    		$this->ajaxReturn(null,'申请失败',0);
    	}
    	
    }
    
    public function log()
    {
    	$uid = I('uid');
    	$channel = I('channel');
    	$page = I('page');
    	
    	if(empty($uid) || empty($channel) || empty($page))
    	{
    		$this->ajaxReturn(null,'参数不能为空',0);
    	}
    	
    	$arr = array(
    	'uid'=>$uid,
    	'channel'=>$channel,
    	'page'=>$page,
    	'sign'=>I('sign')  	
    	);
    	
    	$res = checkSign($arr,C('API_KEY'));
    	
    	if(!$res)
    	{
    		$this->ajaxReturn(null,'签名错误',0);
    	}
    	
    	$change_game_log = M('change_game_log');
    	
    	$count = $change_game_log->where(array('uid'=>$uid))->count();
    	
 

    	$page = $page?$page:1;
    	
         $log_list = $change_game_log
		->field('origin_appname,origin_servername,origin_rolename,new_appname,new_servername,new_rolename,DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d %H:%i") create_time,status,reason')
		->where(array('uid'=>$uid))
		->order('create_time desc')
		->limit(($page-1)*$this->page_size.','.$this->page_size)
		->select();
		
		

	   

		$data = array(
		'count'=>ceil($count/$this->page_size),
		'list'=>$log_list?$log_list:array()
		);

		$this->ajaxReturn($data,'');
    	
    	
    }
    
    
    public function notice()
    {
    	$channel = I('channel');
    	
    	if(empty($channel))
    	{
    		$this->ajaxReturn(null,'参数不能为空',0);
    	}
    	
    	$arr = array(
    	'channel'=>$channel,
    	'sign'=>I('sign')
    	);

    	$res = checkSign($arr,C('API_KEY'));

    	if(!$res)
    	{
    		$this->ajaxReturn(null,'签名错误',0);
    	}
    	
    	$site_options = get_site_options();
    	
    	
    	$this->ajaxReturn(array_values($site_options['change_game']));
    	
    }

}