<?php
/**
 * 金币接口
 * @author qing.li
 * @date 2017-11-01
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class CoinController extends AppframeController
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
		
		$count = M('coin_log')->where($map)->count();
		
		
		$page = $page?$page:1;
		
		$log = M('coin_log')
		->field('type,coin_change,coin_counts,DATE_FORMAT(FROM_UNIXTIME(create_time),"%m-%d %H:%i:%s") create_time')
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
	
	public function coin_info()
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
		'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$coin = M('player')->where(array('id'=>$uid))->getfield('coin');	
		$site_options = get_site_options();
	
		$data = array(
		'coin'=>$coin,
		'platform_coin_ratio'=>$site_options['platform_coin_ratio']?$site_options['platform_coin_ratio']:10,
		'platform_start_count'=>$site_options['platform_start_count']?$site_options['platform_start_count']:10,
		);
		
		$this->ajaxReturn($data);
	}
	
	public function my_coin()
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
		'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}
		
		$coin_log_model = M('coin_log');
		
		
		
		$coin = M('player')->where(array('id'=>$uid))->getfield('coin');
		
		//今日收益
		
		$today_time = strtotime(date('Y-m-d'));
		
		$map = array('uid'=>$uid);
		$map['create_time'] = array('egt',$today_time);
	    $map['coin_change'] = array('gt',0);
		
		$today_coin = $coin_log_model->where($map)->field('sum(coin_change) coin_change')->find();
		

		
		//本月收益
		
		$month_time = strtotime(date('Y-m-01'));
				
		$map['create_time'] = array('egt',$month_time);
		
		$month_coin = $coin_log_model->where($map)->field('sum(coin_change) coin_change')->find();
		

		
		$data = array(
		'user_counts'=>$coin,
		'today_coin'=>isset($today_coin['coin_change'])?$today_coin['coin_change']:0,
		'month_coin'=>isset($month_coin['coin_change'])?$month_coin['coin_change']:0,
		);
		
		$this->ajaxReturn($data);
	}
	
	public function del_lastmonth()
	{
		$month = date('m')-1;
		//如果是1月份，删除去年12月份的数据
		if($month == 0)
		{
			$month = 12;
		}
		M('sign_info')->where(array('month'=>$month))->delete();
		exit('success');
	}
	

}