<?php
/**
 * 签到接口
 * @author qing.li
 * @date 2017-11-03
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class SignController extends AppframeController
{
	public function sign_init()
	{
		$uid = I('uid');
		$channel = I('channel');

		if(empty($uid) || empty($channel))
		{
			$this->ajaxReturn(null,'参数错误',0);
		}

		$arr = array(
		'uid'=>$uid,
		'channel'=>$channel,
		'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$player_info = M('player')->where(array('id'=>$uid))->count();

		if(!$player_info)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		$month = date('m');
		$year = date('Y');

		$days = date("t",strtotime("{$year}-{$month}"));

		$sign_config = C('SIGN_CONFIG');
		
		foreach($sign_config['ACCUM_BONUS'] as $k=>$v)
		{
			if($v['num'] == 'all')
			{
				$sign_config['ACCUM_BONUS'][$k]['num'] = $days;
			}
		}


		$sign_info = M('sign_info')
		->where(array('uid'=>$uid,'month'=>$month))
		->find();

		$today_is_sign = M('sign_log')
		->where(array('uid'=>$uid,'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d")'=>date('Y-m-d',time())))
		->count();


		$data = array(
		'day_bonus'=>$sign_config['DAY_BONUS'],
		'accum_bonus'=>$sign_config['ACCUM_BONUS'],
		'sign_counts'=>$sign_info?$sign_info['counts']:0,
		'today_is_sign'=>$today_is_sign?1:0,
		'days'=>$days,
		);

		$this->ajaxReturn($data);

	}

	public function do_sign()
	{
		$uid = I('uid');
		$channel = I('channel');

		if(empty($uid) || empty($channel))
		{
			$this->ajaxReturn(null,'参数错误',0);
		}

		$arr = array(
		'uid'=>$uid,
		'channel'=>$channel,
		'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$player_model = M('player');

		$player_info = $player_model->where(array('id'=>$uid))->count();

		if(!$player_info)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}
		
		$today_is_sign = M('sign_log')
		->where(array('uid'=>$uid,'DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d")'=>date('Y-m-d',time())))
		->count();
		
		if($today_is_sign > 0)
		{
			$this->ajaxReturn(null,'今天已签过到',0);
		}

		$channel_info = M('channel')->where(array('id'=>$channel))->count();

		$channel = $channel_info?$channel:C('MAIN_CHANNEL');

		$month = date('m');
		$year = date('Y');

		$days = date("t",strtotime("{$year}-{$month}"));
	

		$sign_config = C('SIGN_CONFIG');

		$is_vip = checkVip($uid);

		$now_time = time();

		$data_log = array(
		'uid'=>$uid,
		'cid'=>$channel,
		'is_vip'=>$is_vip,
		'create_time'=>$now_time
		);

		$sign_log_model = M('sign_log');
		$sign_info_model = M('sign_info');

		$res = $sign_log_model->add($data_log);

		if($res!==false)
		{

			$sign_info = $sign_info_model->where(array('uid'=>$uid,'month'=>$month))->find();

			if($sign_info)
			{
			    $counts = $sign_info['counts'] + 1;
				$sign_info_model->where(array('uid'=>$uid,'month'=>$month))->setInc('counts',1);
				$sign_info_model->where(array('uid'=>$uid,'month'=>$month))->save(array('modify_time'=>$now_time));
			}
			else
			{
                $counts = 1;
				$data_info = array(
				'uid'=>$uid,
				'counts'=>1,
				'month'=>$month,
				'modify_time'=>$now_time
				);
				$sign_info_model->add($data_info);
			}


			$bonus = $sign_config['DAY_BONUS']['normal'];

			if($is_vip)
			{
				$bonus+=$sign_config['DAY_BONUS']['vip_extra'];
			}

			//$sign_counts = $sign_info_model->where(array('uid'=>$uid,'month'=>$month))->getfield('counts');


			if($days == $counts)
			{
				$bonus+=$sign_config['ACCUM_BONUS'][3]['bonus'];
			}
			else
			{
				foreach($sign_config['ACCUM_BONUS'] as $v)
				{
					if($v['num'] == $counts)
					{
						$bonus+=$v['bonus'];
						break;
					}

				}
			}

			$res = $player_model->where(array('id'=>$uid))->setInc('coin',$bonus);

			if($res !==false)
			{
				$coin = $player_model->where(array('id'=>$uid))->getfield('coin');

				$data_log = array(
				'uid'=>$uid,
				'type'=>1,
				'coin_change'=>$bonus,
				'coin_counts'=>$coin,
				'create_time'=>$now_time,
				);

				M('coin_log')->add($data_log);

			}
			//如果签到成功 签到任务完成
			if(M('task')->where(array('uid'=>$uid,'type'=>1,'create_time'=>array('egt',strtotime(date('Y-m-d')))))->count() < 1)
			{
				M('task')->add(array('uid'=>$uid,'type'=>1,'create_time'=>$now_time));
			}


			$this->ajaxReturn($bonus,'签到成功');
		}
		else 
		{
			$this->ajaxReturn(null,'签到失败',0);
		}

	}
}