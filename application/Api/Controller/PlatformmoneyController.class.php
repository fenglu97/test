<?php
/**
 * 平台币接口
 * @author qing.li
 * @date 2017-11-01
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class PlatformmoneyController extends AppframeController
{
	private $page_size = 10;
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

		//只能查看最近7天的数据
		$start_time = strtotime(date('Y-m-d',strtotime("-6 days")));

		$map['uid'] = $uid;
		$map['create_time'] = array('egt',$start_time);

		$count = M('platform_detail_logs')->where($map)->count();

		$page = $page?$page:1;

		$log = M('platform_detail_logs')
			->field('type,platform_change,platform_counts,DATE_FORMAT(FROM_UNIXTIME(create_time),"%m-%d %H:%i:%s") create_time')
			->where($map)
			->order('create_time desc,id desc')
			->limit(($page-1)*$this->page_size.','.$this->page_size)
			->select();

		$data = array(
			'count'=>ceil($count/$this->page_size),
			'list'=>$log?$log:array()
		);

		$this->ajaxReturn($data);
	}

	public function exchange()
	{
		$uid = I('uid');
		$channel = I('channel');
		$platform_counts = I('platform_counts');

		if(empty($uid) || empty($channel) || ($platform_counts<10))
		{
			$this->ajaxReturn(null,'参数错误',0);
		}

		$arr = array(
			'uid'=>$uid,
			'channel'=>$channel,
			'platform_counts'=>$platform_counts,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$player_model = M('player');

		$player_info = $player_model->field('coin,platform_money')->where(array('id'=>$uid))->find();

		if(!$player_info)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		$site_options = get_site_options();

		$platform_coin_ratio = 	$site_options['platform_coin_ratio']?$site_options['platform_coin_ratio']:10;

		if($player_info['coin'] < $platform_counts*$platform_coin_ratio)
		{
			$this->ajaxReturn(null,'金币余额不足',0);
		}

		//兑换金币 减去$platform_counts*10 platform_money加上$platform_counts,并在金币明细表和平台币明细表内记录
		$result = $player_model->where(array('id'=>$uid))->setDec('coin',$platform_counts*$platform_coin_ratio);
		if($result!==false)
		{
			$result = $player_model->where(array('id'=>$uid))->setInc('platform_money',$platform_counts);
			if($result!==false)
			{
				//添加记录到金币明细表和平台币明细表
				$player_info = $player_model->field('coin,platform_money')->where(array('id'=>$uid))->find();

				$now_time = time();

				$data_log = array(
					'uid'=>$uid,
					'type'=>4,
					'coin_change'=>-$platform_counts*$platform_coin_ratio,
					'coin_counts'=>$player_info['coin'],
					'create_time'=>$now_time,
				);

				M('coin_log')->add($data_log);

				$data_log = array(
					'uid'=>$uid,
					'type'=>1,
					'platform_change'=>$platform_counts,
					'platform_counts'=>$player_info['platform_money'],
					'create_time'=>$now_time
				);

				M('platform_detail_logs')->add($data_log);

				if(M('task')->where(array('uid'=>$uid,'type'=>6,'create_time'=>array('egt',strtotime(date('Y-m-d')))))->count() < 1)
				{
					M('task')->add(array('uid'=>$uid,'type'=>6,'create_time'=>$now_time));
				}

				$this->ajaxReturn(null,'兑换成功');
			}
			else
			{
				//数据回滚
				$player_model->where(array('id'=>$uid))->setInc('coin',$platform_counts*$platform_coin_ratio);
				$this->ajaxReturn(null,'数据库错误',0);
			}
		}
		else
		{
			$this->ajaxReturn(null,'数据库错误',0);
		}
	}

	public function get_reigster_bonus()
	{
		$uid = I('uid');
		$channel = I('channel');
		$user_message_id = I('user_message_id');

		if (empty($uid) || empty($channel) || empty($user_message_id)) {
			$this->ajaxReturn(null, '参数错误', 0);
		}

		$arr = array(
			'uid' => $uid,
			'channel' => $channel,
			'user_message_id' => $user_message_id,
			'sign' => I('sign')
		);

		$res = checkSign($arr, C('API_KEY'));

		if (!$res)
		{
			$this->ajaxReturn(null, '签名错误', 0);
		}

		$player_info = M('player')->field('coin,platform_money')->where(array('id' => $uid))->find();

		if (!$player_info) 
		{
			$this->ajaxReturn(null, '用户不存在', 0);
		}

		$user_message_info = M('user_message')->where(array('id' => $user_message_id))->find();

		if(!$user_message_info)
		{
			$this->ajaxReturn(null, '用户消息不存在', 0);
		}

		if ($user_message_info['is_get'] >0) {
			$this->ajaxReturn(null, '已经领取奖励', 0);
		}

		$message_info = M('message')->where(array('id' => $user_message_info['message_id']))->find();

		if ($message_info['action'] != 2)
		{
			$this->ajaxReturn(null,'没有附件可领取',0);
		}

		if($message_info['attach_type'] == 0)
		{
			$this->ajaxReturn(null,'该消息没有附件',0);
		}


		if($message_info['type'] == 1)
		{
			$res = M('player')->
			where(array('id'=>$uid))->
			save(array('platform_money'=>$player_info['platform_money']+10,'is_register_bonus'=>1));
			if($res !==false)
			{
				//添加成功 添加平台币明细 修改用户消息记录
				$log_data = array(
					'uid'=>$uid,
					'type'=>6,
					'platform_change'=>10,
					'platform_counts'=>$player_info['platform_money']+10,
					'create_time'=>time(),
				);
				M('platform_detail_logs')->add($log_data);

				M('user_message')->where(array('id'=>$user_message_id))->save(array('is_get'=>1));

				$this->ajaxReturn(null,'领取成功');
			}
		}

		if($message_info['type'] == 3)
		{
			if($message_info['attach_type'] == 1) $field = 'platform_money';
			if($message_info['attach_type'] == 2) $field = 'coin';

			$res = M('player')->
			where(array('id'=>$uid))->
			setInc($field,$message_info['attach_count']);

			if($res !== false)
			{
				M('user_message')->where(array('id'=>$user_message_id))->save(array('is_get'=>1));
				if($message_info['attach_type'] == 1)
				{
					$log_data = array(
						'uid'=>$uid,
						'type'=>7,
						'platform_change'=>$message_info['attach_count'],
						'platform_counts'=>$player_info[$field]+$message_info['attach_count'],
						'create_time'=>time(),
					);
					M('platform_detail_logs')->add($log_data);
				}
				elseif($message_info['attach_type'] == 2)
				{
					$log_data = array(
						'uid'=>$uid,
						'type'=>14,
						'coin_change'=>$message_info['attach_count'],
						'coin_counts'=>$player_info[$field]+$message_info['attach_count'],
						'create_time'=>time(),
					);
					M('coin_log')->add($log_data);
				}
				$this->ajaxReturn(null,'领取成功');
			}

		}

		$this->ajaxReturn(null,'领取失败',0);

	}

	/**
	 * 领取消息附件中的平台币
	 */
	public function get_msg_platform()
	{
		$uid = I('uid');
		$channel = I('channel');
		$user_message_id = I('user_message_id');

		if (empty($uid) || empty($channel) || empty($user_message_id)) {
			$this->ajaxReturn(null, '参数错误', 0);
		}

		$arr = array(
			'uid' => $uid,
			'channel' => $channel,
			'user_message_id' => $user_message_id,
			'sign' => I('sign')
		);

		$res = checkSign($arr, C('API_KEY'));

		if (!$res)
		{
			$this->ajaxReturn(null, '签名错误', 0);
		}

		$player_info = M('player')->field('coin,platform_money')->where(array('id' => $uid))->find();

		if (!$player_info)
		{
			$this->ajaxReturn(null, '用户不存在', 0);
		}

		$user_message_info = M('user_message')->where(array('id' => $user_message_id))->find();

		if(!$user_message_info)
		{
			$this->ajaxReturn(null, '用户消息不存在', 0);
		}

		if ($user_message_info['is_get'] >0) {
			$this->ajaxReturn(null, '已经领取奖励', 0);
		}

		$message_info = M('message')->where(array('id' => $user_message_info['message_id']))->find();

		if ($message_info['action'] != 2)
		{
			$this->ajaxReturn(null,'没有附件可领取',0);
		}

		if($message_info['attach_type'] == 0)
		{
			$this->ajaxReturn(null,'该消息没有附件',0);
		}

		if($message_info['type'] == 4)
		{
			$field = 'platform_money';
			$res = M('player')->
			where(array('id'=>$uid))->
			setInc($field,$message_info['attach_count']);

			if($res !== false)
			{
				M('user_message')->where(array('id'=>$user_message_id))->save(array('is_get'=>1));
				if($message_info['attach_type'] == 1)
				{
					$log_data = array(
						'uid'=>$uid,
						'type'=>7,
						'platform_change'=>$message_info['attach_count'],
						'platform_counts'=>$player_info[$field]+$message_info['attach_count'],
						'create_time'=>time(),
					);
					M('platform_detail_logs')->add($log_data);
				}

				$this->ajaxReturn(null,'领取成功');
			}

		}

		$this->ajaxReturn(null,'领取失败',0);

	}
}