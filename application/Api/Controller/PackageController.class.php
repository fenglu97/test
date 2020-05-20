<?php
/**
 * 礼包接口
 * @author qing.li
 * @date 2017-09-05
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class PackageController extends AppframeController
{
	private $packs_page_size = 10;

	public function _initialize()
	{
		parent::_initialize();
		$this->package_model = M('package');
		$this->package_code_model = M('package_code');
		$this->package_get_logs_model = M('package_get_logs');
	}

	/**
	 * 礼包列表
	 */
	public function package_list()
	{
		$appid = I('appid');
		$channel = I('channel');
		$uid = I('uid');
		$system = I('system');
		$page = I('page');

		if(empty($appid) || empty($channel) || empty($uid) || empty($system) || empty($page))
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
		'channel'=>$channel,
		'uid'=>$uid,
		'system'=>$system,
		'page'=>$page,
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

		$player = M('player')->where(array('status'=>1,'id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		$page = $page?$page:1;

		$map = array(
		'status'=>1,
		'is_verify'=>1,
		'ios_android'=>array('in','3,'.$system),
		'appid'=>$appid,
		);

		$count = $this->package_model->where($map)->cache(true)->count();

		$package_list = $this->package_model
		->field('id,pack_name,pack_type,pack_counts,pack_get_counts')
		->where($map)
		->order('create_time desc')
		->limit(($page-1)*$this->packs_page_size.','.$this->packs_page_size)
		->cache(true)
		->select();

		//查询用户是否领取过这些领导

		if(is_array($package_list))
		{
			$pids = '';

			foreach($package_list as $v)
			{
				$pids.=$v['id'].',';
			}
			$pids = trim($pids,',');

			$logs = $this->package_get_logs_model->
			field('pid,card')->
			where(array('pid'=>array('in',$pids),'uid'=>$uid))->
			select();

			$user_card_info = array();

			if(is_array($logs))
			{
				foreach($logs as $log)
				{
					$user_card_info[$log['pid']] = $log['card'];
				}

			}
			unset($logs);

			foreach($package_list as $k=>$v)
			{
				$package_list[$k]['card'] = isset($user_card_info[$v['id']])?$user_card_info[$v['id']]:'';
			}

		}

		$data = array(
		'count'=>ceil($count/$this->packs_page_size),
		'list'=>$package_list?$package_list:array()
		);

		$this->ajaxReturn($data,'');

	}
	/**
	 * 礼包领取
	 */
	public function get_package_code()
	{
		$appid = I('appid');
		$channel = I('channel');
		$uid = I('uid');
		$pid = I('pid');
		$machine_code = I('machine_code');

		if(empty($appid) || empty($channel) || empty($uid) || empty($pid) || empty($machine_code))
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
		'channel'=>$channel,
		'uid'=>$uid,
		'pid'=>$pid,
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

		$player = M('player')->where(array('status'=>1,'id'=>$uid))->find();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		$package = $this->package_model->
		where(array('id'=>$pid,'status'=>1,'is_verify'=>1))
		->find();

		if(!$package)
		{
			$this->ajaxReturn(null,'礼包不存在',23);
		}

		$now_time = time();

		if($package['start_time'] > $now_time || $package['end_time'] < $now_time)
		{
			$this->ajaxReturn(null,'礼包已过期或未生效',34);
		}

		if(($package['pack_counts']-($package['pack_get_counts']+$package['pack_export_counts'])) == 0)
		{
			$this->ajaxReturn(null,'礼包已领取完',35);
		}

		$is_get_package = $this->package_get_logs_model->
		where(array('pid'=>$pid,'uid'=>$uid))->
		count();

		if($is_get_package)
		{
			$this->ajaxReturn(null,'已领取过该礼包',31);
		}

		//获取礼包码
		$card_info = $this->package_code_model->where(array('pid'=>$pid))->find();

		$this->package_code_model->delete($card_info['id']);

		//礼包get_pack_counts+1

		$this->package_model->where(array('pid'=>$pid))->setInc('pack_get_counts',1);

		//记录礼包日志

		$log_data = array(
		'cid'=>$player['channel'],
		'pid'=>$pid,
		'appid'=>$appid,
		'uid'=>$uid,
		'ip'=>ip2long(get_client_ip(0,true)),
		'device_id'=>$machine_code,
		'card'=>$card_info['card'],
		'create_time'=>$now_time
		);

		$this->package_get_logs_model->add($log_data);

		$this->ajaxReturn(array('pid'=>$pid,'machine_code'=>$machine_code,'card'=>$card_info['card']),'领取成功');


	}

	/**
	 * 盒子礼包详情
	 */
	public function pack_info()
	{
		$pid = I('pid');
		$username = I('username');
		$channel = I('channel');
		$machine_code = I('machine_code');
		$terminal_type = I('terminal_type')?I('terminal_type'):2;
		$system = I('system');

		if(empty($machine_code) && $terminal_type == 2)
		{
			$this->ajaxReturn(null,'设备号不能为空',0);
		}

		$arr['pid'] = $pid;
		$arr['username'] = $username;
		$arr['channel'] = $channel;
		$arr['machine_code'] = $machine_code;
		$arr['terminal_type'] = $terminal_type;
		$arr['system'] = $system;
		$arr['sign'] = I('sign');

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$pack_info = M('Packs','syo_',C('185DB'))
		->field('id,game_id,pack_name,pack_abstract,pack_counts,pack_used_counts,pack_method,pack_notice,start_time,end_time')
		->where(array('id'=>$pid,'status'=>1,'is_verify'=>1))
		->find();

		if(!$pack_info)
		{
			$this->ajaxReturn(null,'礼包不存在',0);
		}



			//如果用户没有登录，web版以IP为唯一标示，APP以device_id为唯一标示
			if($terminal_type == 2)
			{
				$where = array('device_id'=>$machine_code,'terminal_type'=>$terminal_type);
			}
			else
			{
				$where = array('ip'=>get_client_ip(0,true),'terminal_type'=>$terminal_type);
			}


		$where['pid'] = $pid;

		$card = M('pack_card_log','syo_',C('185DB'))->where($where)->Getfield('card');

		$pack_info['card'] = $card;

		$game_info = M('game','syo_',C('185DB'))->where(array('id'=>$pack_info['game_id']))->field('gamename,logo,size,content,tag,android_pack,ios_pack')->find();

		$pack_info['game_name'] = $game_info['gamename'];
		$pack_info['logo'] = C('185SY_URL').$game_info['logo'];
		$pack_info['game_size'] = $game_info['size'];
		$pack_info['game_content'] = $game_info['content'];


		$game_sdk = M('game')->where(array('tag'=>$game_info['tag']))->field('id,android_url,ios_url')->find();
		if($system == 1){
			$pack_info['android_pack'] = $game_info['android_pack'];
			$pack_info['android_url'] = $game_sdk['android_url'];
		}
		else{
			$pack_info['ios_pack'] = $game_info['ios_pack'];
			$pack_info['ios_url'] = $game_sdk['ios_url'];
		}
		$this->ajaxReturn($pack_info);

	}

}