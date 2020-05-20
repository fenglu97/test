<?php
/**
 * 老SDK订单控制器
 * @author qing.li
 * @date 2017-07-12
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class PayOldController extends AdminbaseController
{
	public function _initialize()
	{
		parent::_initialize();
		$this->Inpour = M('Inpour');
		$this->Inpour_cut = M('Inpour_cut');
	}

	public function index()
	{
		//接受Post数据
		$parameter = array();
		$parameter['gameid'] = I('gameid');
		$parameter['channel'] = I('channel');
		$parameter['status'] = I('status')?I('status'):1;
		$parameter['username'] = I('username');
		$parameter['orderid'] = I('orderid');
		$parameter['orderid_get'] = I('orderid_get');
		$today_time = date('Y-m-d',time());
		$parameter['start_time'] = I('start_time')?I('start_time'):$today_time;
		$parameter['end_time'] = I('end_time')?I('end_time'):$today_time;


		$pay_model = M('pay','syo_',C('DB_OLDSDK_CONFIG'));
		$map = array();

		if((strtotime($parameter['end_time']) - strtotime($parameter['start_time'])) >= 3600*24*180)
		{
			$this->error('不能查询时间间隔180天以上的数据');
		}


		if($parameter['gameid'])
		{
			$map['gameid'] = $parameter['gameid'];
		}
		else
		{
			if(session('game_role')!='all')
			{
				$map['gameid'] = array('in',session('game_role'));
			}
		}

		if($parameter['channel'])
		{
			$map['channel'] = $parameter['channel'];
		}
		else
		{
			if(session('channel_role')!='all')
			{
				$map['channel'] = array('in',session('channel_role'));
			}
		}

		if($parameter['status'] && $parameter['status']!='all')
		{
			$map['status'] = $parameter['status'];
		}

		if($parameter['username'])
		{
			$map['username'] = array('like',"%{$parameter['username']}%");
		}

		if($parameter['start_time'])
		{
			$map['created'][] = array('egt',strtotime($parameter['start_time']));
		}

		if($parameter['end_time'])
		{
			$map['created'][] = array('lt',strtotime($parameter['end_time'])+3600*24);
		}

		if($parameter['orderid'])
		{
			$map['orderid'] = $parameter['orderid'];
		}

		if($parameter['orderid_get'])
		{
			$map['orderid_get'] = $parameter['orderid_get'];
		}


		$map['type'] = 1;

		$count = $pay_model->where($map)->count();

		$page = $this->page($count, 20);
		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
			$page->parameter[$key] = urlencode($val);
		}


		$list = $pay_model
		->where($map)
		->limit($page->firstRow . ',' . $page->listRows)
		->order('created desc')
		->select();

		$total = $pay_model->field('sum(rmb) rmb,sum(getmoney) getmoney')->where($map)->find();

		$gids = '';

		if(is_array($list))
		{
			foreach($list as $v)
			{
				$gids.= $v['gameid'].',';
			}
			$gids = trim($gids,',');
		}

		$gamenames = M('game')->field('id,game_name')->where(array('id'=>array('in',$gids)))->select();

		$gamenames_info = array();
		foreach($gamenames as $gamename)
		{
			$gamenames_info[$gamename['id']] = $gamename['game_name'];
		}

		$this->assign('gamenames_info',$gamenames_info);
		$this->assign('page',$page->show('Admin'));
		$this->assign('game_list',get_game_list($parameter['gameid'],1,2));
		$this->assign('channel_list',get_channel_list($parameter['channel']));
		$this->assign('parameter',$parameter);
		$this->assign('list',$list);
		$this->total = $total;
		$this->display();

	}


	public function channel_pay()
	{
		//接受Post数据
		$parameter = array();
		$parameter['gameid'] = I('gameid');
		$parameter['channel'] = I('channel');
		$today_time = date('Y-m-d',time());
		$parameter['start_time'] = I('start_time')?I('start_time'):$today_time;
		$parameter['end_time'] = I('end_time')?I('end_time'):$today_time;

		$pay_model = M('pay','syo_',C('DB_OLDSDK_CONFIG'));
		//只查询支付成功的
		$map = array('status'=>1);
		$map['type'] = 1;

		if($parameter['gameid'])
		{
			$map['gameid'] = $parameter['gameid'];
		}
		else
		{
			if(session('game_role')!='all')
			{
				$map['gameid'] = array('in',session('game_role'));
			}
		}

		if($parameter['channel'])
		{

			$child_channel = M('channel')
			->field('id')
			->where(array('parent'=>$parameter['channel']))
			->select();
			
			$child_channels = '';
			if(!empty($child_channel))
			{
				foreach($child_channel as $v)
				{
					$child_channels.=$v['id'].',';
				}
				$child_channels = trim($child_channels,',');

				$map['channel'] = array('in',$child_channels.','.$parameter['channel']);
			}
			else
			{
				$map['channel'] = $parameter['channel'];
			}
		}
		else
		{
			if(session('channel_role')!='all')
			{
				$map['channel'] = array('in',session('channel_role'));
			}
		}

		if($parameter['start_time'])
		{
			$map['pay_to_time'][] = array('egt',strtotime($parameter['start_time']));
		}

		if($parameter['end_time'])
		{
			$map['pay_to_time'][] = array('lt',strtotime($parameter['end_time'])+3600*24);
		}

		$count = $pay_model->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
			$page->parameter[$key] = urlencode($val);
		}


		$list = $pay_model
		->field('orderid,orderid_get,rmb,getmoney,gameid,channel,pay_to_time,username')
		->where($map)
		->limit($page->firstRow . ',' . $page->listRows)
		->order('pay_to_time desc')
		->select();



		$total = $pay_model
		->field('sum(rmb),sum(getmoney)')
		->where($map)
		->find();


		$gids = '';
		$cids = '';

		if(is_array($list))
		{
			foreach($list as $v)
			{
				$gids.= $v['gameid'].',';
				$cids.= $v['channel'].',';
			}
			$gids = trim($gids,',');
			$cids = trim($cids,',');
		}


		
		if($parameter['channel'])
		{			
			$channel_gainsharing_info = M('channel')
			->field('gain_sharing,parent')
			->where(array('id'=>$parameter['channel']))
			->find();
			
			if($channel_gainsharing_info['parent']!=0)
			{
				$parent_gain_sharing = M('channel')->where(array('id'=>$channel_gainsharing_info['parent']))->getfield('gain_sharing');
				$channel_gainsharing_info = $channel_gainsharing_info['gain_sharing']*$parent_gain_sharing;
			}
			else 
			{
				$channel_gainsharing_info = $channel_gainsharing_info['gain_sharing'];
			}
		}
		else 
		{
			$channel_gainsharings = M('channel')->field('id,gain_sharing,parent')->where(array('id'=>array('in',$cids)))->select();
			
		    $channel_gainsharing_info = array();
			foreach($channel_gainsharings as $channel_gainsharing )
			{
				if($channel_gainsharing['parent'] !=0)
				{
					$parent_gain_sharing = M('channel')->where(array('id'=>$channel_gainsharing['parent']))->getfield('gain_sharing');
					$channel_gainsharing_info[$channel_gainsharing['id']] = $parent_gain_sharing*$channel_gainsharing['gain_sharing'];
				}
				else
				{
					$channel_gainsharing_info[$channel_gainsharing['id']] = $channel_gainsharing['gain_sharing'];
				}

			}
		}

        $gamenames = M('game')->field('id,game_name')->where(array('id'=>array('in',$gids)))->select();

		$gamenames_info = array();
		foreach($gamenames as $gamename)
		{
			$gamenames_info[$gamename['id']] = $gamename['game_name'];
		}


		$this->assign('total',$total);
		$this->assign('parameter',$parameter);
		$this->assign('channel_gainsharing_info',$channel_gainsharing_info);
		$this->assign('gamenames_info',$gamenames_info);
		$this->assign('page',$page->show('Admin'));
		$this->assign('list',$list);
		$this->assign('game_list',get_game_list($parameter['gameid'],1,2));
		$this->assign('channel_list',get_channel_list($parameter['channel']));
		$this->assign('total',$total);
		$this->display();
	}

	public function game_recon()
	{
		//接受Post数据
		$parameter = array();
		$parameter['gameid'] = I('gameid');
		$parameter['channel'] = I('channel');
		$today_time = date('Y-m-d',time());
		$parameter['start_time'] = I('start_time')?I('start_time'):$today_time;
		$parameter['end_time'] = I('end_time')?I('end_time'):$today_time;

		$pay_model = M('pay','syo_',C('DB_OLDSDK_CONFIG'));
		//只查询支付成功的
		$map = array('status'=>1);
		$map['type'] = 1;

		if($parameter['gameid'])
		{
			$map['gameid'] = $parameter['gameid'];
		}
		else
		{
			if(session('game_role')!='all')
			{
				$map['gameid'] = array('in',session('game_role'));
			}
		}

		if($parameter['channel'])
		{
			$child_channel = M('channel')
			->field('id')
			->where(array('parent'=>$parameter['channel']))
			->select();
			$child_channels = '';
			if(!empty($child_channel))
			{
				foreach($child_channel as $v)
				{
					$child_channels.=$v['id'].',';
				}
				$child_channels = trim($child_channels,',');

				$map['channel'] = array('in',$child_channels.','.$parameter['channel']);
			}
			else
			{
				$map['channel'] = $parameter['channel'];
			}
		}
		else
		{
			if(session('channel_role')!='all')
			{
				$map['channel'] = array('in',session('channel_role'));
			}
		}

		if($parameter['start_time'])
		{
			$map['pay_to_time'][] = array('egt',strtotime($parameter['start_time']));
		}

		if($parameter['end_time'])
		{
			$map['pay_to_time'][] = array('lt',strtotime($parameter['end_time'])+3600*24);
		}

		$count = $pay_model->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
			$page->parameter[$key] = urlencode($val);
		}

		$list = $pay_model
		->field('orderid,orderid_get,rmb,getmoney,gameid,channel,pay_to_time,username')
		->where($map)
		->limit($page->firstRow . ',' . $page->listRows)
		->order('pay_to_time desc')
		->select();

		$total = $pay_model
		->field('sum(rmb),sum(getmoney)')
		->where($map)
		->find();

		$gids = '';
		$cids = '';

		if(is_array($list))
		{
			foreach($list as $v)
			{
				$gids.= $v['gameid'].',';
				$cids.= $v['channel'].',';
			}
			$gids = trim($gids,',');
			$cids = trim($cids,',');
		}

		$gamenames = M('game')->field('id,game_name')->where(array('id'=>array('in',$gids)))->select();
		$channel_gainsharings = M('channel')->field('id,gain_sharing')->where(array('id'=>array('in',$cids)))->select();


		$gamenames_info = array();
		foreach($gamenames as $gamename)
		{
			$gamenames_info[$gamename['id']] = $gamename['game_name'];
		}



		$this->assign('total',$total);
		$this->assign('parameter',$parameter);

		$this->assign('gamenames_info',$gamenames_info);
		$this->assign('page',$page->show('Admin'));
		$this->assign('list',$list);
		$this->assign('game_list',get_game_list($parameter['gameid'],1,2));
		$this->assign('channel_list',get_channel_list($parameter['channel']));
		$this->assign('total',$total);
		$this->display();
	}

	public function info()
	{
		$id = I('get.id');
		$pay_model = M('pay','syo_',C('DB_OLDSDK_CONFIG'));
		$info = $pay_model->
		where(array('id'=>$id))->
		field('id,orderid,orderid_get,pay_to,call_back,gameid,username,serverid,product_id')->
		find();
		
		$gamename = M('game',null,C('DB_OLDSDK_CONFIG'))
		->where(array('game_id'=>$info['gameid']))
		->getfield('game_name');
		
		
		$this->assign('gamename',$gamename);
		$this->assign('info',$info);
		$this->display();
	}

	public function retransmission()
	{
		$orderid = I('orderid');
		if(!empty($orderid))
		{
			$url = C('OLD_SDK_RETRANS');
			$url.="/order/{$orderid}.html";

			$res = curl_get($url);

			$res = json_decode($res,true);

			if($res['status'] == 1)
			{
				$this->success('正在进行重发');
			}
			else
			{
				$this->error($res['msg']);
			}
		}
		else
		{
			$this->error('重发失败');
		}
	}

	public function apply_rebate()
	{
		$username = I('username');
		$gameid = I('gameid');


		if(!I(username))
		{
			$this->error('请输入游戏账号');
		}

		$player_info = M('syo_member',null,C('DB_OLDSDK_CONFIG'))->where(array('username'=>$username))->field('channel,id')->find();


		$channel_role = session('channel_role');
		if($channel_role !='all')
		{
			if(!in_array($player_info['channel'],explode(',',$channel_role)))
			{
				$this->error('游戏账号不属于该账号所属渠道');
			}
			$map['channel'] = array('in',$channel_role);
		}

		if($gameid)
		{
			$map['gameid'] = $gameid;
		}

		$map['username'] = $username;
		//获取三天内的充值 排序平台币

		$start_day = strtotime(date('Y-m-d',strtotime('-2 days')));
		$map['created'] = array('egt',$start_day);
		$map['vip'] = 2;
		$map['status'] = 1;
		$map['is_rebated'] = 0;
		$map['type'] = 1;



		$pay_info = M('syo_pay',null,C('DB_OLDSDK_CONFIG'))->field('id,gameid,serverid,rmb,DATE_FORMAT(FROM_UNIXTIME(created),"%Y-%m-%d") create_time')->where($map)->select();
		//组装数据 将满足条件的充值生成渠道工单 并将订单设置为已申请返利

		$data = array();
		foreach($pay_info as $v)
		{
			$key = $v['gameid'].'_'.$v['serverid'].'_'.$v['create_time'];
			$data[$key]['money'] += $v['rmb'];
			$data[$key]['pay_ids'] .= $v['id'].',';
		}

		//单日满足100以上才能进行申请
		$result = array();
		foreach($data as $k=>$v)
		{
			if($v['money'] >= 100)
			{
				$result[$k] = $v;
			}
		}
		unset($data);

		$admin_channel = M('channel')->where(array('admin_id'=>session('ADMIN_ID')))->getfield('id');

		$admin_channel = $admin_channel?$admin_channel:C('MAIN_CHANNEL');

		if(!empty($result))
		{
			$time = time();
			foreach($result as $k=>$v)
			{
				$key = explode('_',$k);
				$data = array(
					'question_type'=>2,
					'order_id'=>uniqid(),
					'admin_id'=>session('ADMIN_ID'),
					'uid'=>$player_info['id'],
					'username'=>$username,
					'channel'=>$admin_channel,
					'appid'=>$key[0],
					'server_name'=>$key[1],
					'title'=>'申请返利',
					'type'=>1,
					'desc'=>$key[2].'充值累计金额'.$v['money'],
					'create_time'=>$time,
					'modify_time'=>$time,
				);

				if(($id = M('question')->add($data))!==false)
				{
					M('syo_pay',null,C('DB_OLDSDK_CONFIG'))->where(array('id'=>array('in',trim($v['pay_ids'],','))))->setField('is_rebated',1);

					//渠道工单建立后 发送信息队列
					$link = U('Admin/WorkOrder/channel_details',array('id'=>$id));
					create_admin_message(4,$id,'all',$link,$key[0]);
				}

			}

			$this->success('操作成功');
		}
		else
		{
			$this->error('没有满足条件的订单');
		}

	}

	public function set_rebate()
	{
		if(M('syo_pay',null,C('DB_OLDSDK_CONFIG'))->where(array('id'=>I('id')))->setField('is_rebated',1)!==false)
		{
			$this->success('操作成功');
		}
		else
		{
			$this->error('操作失败');
		}
	}
}