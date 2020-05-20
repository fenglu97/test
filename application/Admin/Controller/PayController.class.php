<?php
/**
 * 订单控制器
 * @author qing.li
 * @date 2017-07-12
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class PayController extends AdminbaseController
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
		$parameter['appid'] = I('appid');
		$parameter['cid'] = I('cid');
		$parameter['status'] = (I('status') === '')?'1':I('status');
		$parameter['username'] = I('username');
		$parameter['orderID'] = I('orderID');
		$parameter['jz_other'] = I('jz_other');
		$today_time = date('Y-m-d',time());
		$parameter['start_time'] = I('start_time')?I('start_time'):$today_time;
		$parameter['end_time'] = I('end_time')?I('end_time'):$today_time;
		$parameter['mobile'] = I('mobile');
		$parameter['money'] = I('money');
		$parameter['getmoney'] = I('getmoney');
		$parameter['platform_money'] = I('platform_money');
		$parameter['payType'] = I('payType');
		$parameter['deviceType'] = I('deviceType');
		$parameter['access_type'] = I('access_type');
		$parameter['app_uid'] = I('app_uid');


		if((strtotime($parameter['end_time']) - strtotime($parameter['start_time'])) >= 3600*24*180)
		{
			$this->error('不能查询时间间隔180天以上的数据');
		}


		//组装搜索条件
		$map = array();

		$game_role = session('game_role');

		if($game_role !='all')
		{
			$map['a.appid'] = array('in',$game_role);
		}
		else
		{
			if($parameter['access_type'])
			{
				$appids = M('game')->where(array('access_type'=>$parameter['access_type']))->getfield('id',true);
				if($appids)
				{
					$map['a.appid'] = array('in',implode(',',$appids));
				}
				else
				{
					$map['a.appid'] = array('in','');
				}


			}
		}

		$channel_role = session('channel_role');

		if($channel_role !='all')
		{
			$map['a.cid'] = array('in',$channel_role);
		}

		foreach($parameter as $k=>$v)
		{
			if(strlen($v)>0)
			{
				if($k != 'username')
				{
					if($k == 'orderID' || $k == 'jz_other')
					{
						$map['a.'.$k] = array('like',$v.'%');
					}
					elseif($k == 'start_time')
					{
						$map['a.create_time'][] = array('egt',strtotime($v));
					}
					elseif($k == 'end_time')
					{
						$map['a.create_time'][] = array('lt',strtotime($v)+3600*24);
					}
					elseif($k == 'payType')
					{
						if($v == 1)
						{
							$map['a.payType'] = array('neq',10);
						}
						elseif($v == 2)
						{
							$map['a.payType'] = 10;
						}
					}
					elseif($k == 'mobile')
					{
						$uid = M('player')->where(array('mobile'=>$v))->getfield('id');
						$map['a.uid'] = $uid;
					}
					else
					{
						if($k !='access_type')
						{
							$map['a.'.$k] = $v;
						}
					}

					if($k == 'status' && $v == 'all')
					{
						unset($map['a.'.$k]);
					}
					if($k == 'money' && !empty($v)){
						$map['a.'.$k] = $v;
					}
					if($k == 'deviceType' && !empty($v)){
						$map['a.'.$k] = $v;
					}
					if($k == 'getmoney' && !empty($v)){
						$map['a.'.$k] = $v;
					}
					if($k == 'platform_money' && !empty($v)){
						$map['a.'.$k] = $v;
					}
				}
				else
				{
					$map['a.'.$k] = array('like',$v.'%');
				}


			}
		}




		$counts = $this->Inpour
			->alias('a')
			->where($map)
			->count();


		$page = $this->page($counts, 20);

		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
				$page->parameter[$key] = urlencode($val);
		}


		$list = $this->Inpour
			->alias('a')
			->join('__GAME__ as c ON c.id = a.appid')
			->join('__CHANNEL__ as d ON d.id = a.cid')
			->field('a.*,c.game_name,c.game_platform,d.name cname')
			->where($map)
			->order('a.create_time desc')
			->limit($page->firstRow . ',' . $page->listRows)
			->select();



		$map['status'] = 1;
		$total = M('inpour a')
			->field('sum(money) as money,sum(getmoney) as total_getmoney,sum(platform_money) as total_platform_money,sum(rebate) as total_rebate')
			->where($map)->find();


		if(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')) == 'cp')
		{
			$this->assign('cp',1);
		}

		$this->assign('game_platfrom',M('game_platform')->getfield('id,name',true));
		$this->assign('parameter',$parameter);
		$this->assign('total',$total);
		$this->assign('page',$page->show('Admin'));
		$this->assign('list',$list);
		$this->assign('game_list',get_game_list($parameter['appid']));
		$this->assign('channel_list',get_channel_list($parameter['cid']));
		$this->display();
	}

	public function ptb_pay(){
        //接受Post数据
        $parameter = array();
        $parameter['status'] = (I('status') === '')?'1':I('status');
        $parameter['username'] = I('username');
        $parameter['orderID'] = I('orderID');
        $parameter['jz_other'] = I('jz_other');
        $today_time = date('Y-m-d',time());
        $parameter['start_time'] = I('start_time')?I('start_time'):$today_time;
        $parameter['end_time'] = I('end_time')?I('end_time'):$today_time;
        $parameter['money'] = I('money');
        $parameter['payType'] = I('payType');
        $parameter['deviceType'] = I('deviceType');

        $table = M('inpour_ptb');
        if((strtotime($parameter['end_time']) - strtotime($parameter['start_time'])) >= 3600*24*180)
        {
            $this->error('不能查询时间间隔180天以上的数据');
        }


        //组装搜索条件
        $map = array();

        $game_role = session('game_role');

        if($game_role !='all')
        {
            $map['a.appid'] = array('in',$game_role);
        }
        else
        {
            if($parameter['access_type'])
            {
                $appids = M('game')->where(array('access_type'=>$parameter['access_type']))->getfield('id',true);
                if($appids)
                {
                    $map['a.appid'] = array('in',implode(',',$appids));
                }
                else
                {
                    $map['a.appid'] = array('in','');
                }


            }
        }

        $channel_role = session('channel_role');

        if($channel_role !='all')
        {
            $map['a.cid'] = array('in',$channel_role);
        }

        foreach($parameter as $k=>$v)
        {
            if(strlen($v)>0)
            {
                if($k != 'username')
                {
                    if($k == 'orderID' || $k == 'jz_other')
                    {
                        $map['a.'.$k] = array('like',$v.'%');
                    }
                    elseif($k == 'start_time')
                    {
                        $map['a.create_time'][] = array('egt',strtotime($v));
                    }
                    elseif($k == 'end_time')
                    {
                        $map['a.create_time'][] = array('lt',strtotime($v)+3600*24);
                    }
                    elseif($k == 'payType')
                    {
                        if($v == 1)
                        {
                            $map['a.payType'] = array('neq',10);
                        }
                        elseif($v == 2)
                        {
                            $map['a.payType'] = 10;
                        }
                    }
                    elseif($k == 'mobile')
                    {
                        $uid = M('player')->where(array('mobile'=>$v))->getfield('id');
                        $map['a.uid'] = $uid;
                    }
                    else
                    {
                        if($k !='access_type')
                        {
                            $map['a.'.$k] = $v;
                        }
                    }

                    if($k == 'status' && $v == 'all')
                    {
                        unset($map['a.'.$k]);
                    }
                    if($k == 'money' && !empty($v)){
                        $map['a.'.$k] = $v;
                    }
                    if($k == 'deviceType' && !empty($v)){
                        $map['a.'.$k] = $v;
                    }
                    if($k == 'getmoney' && !empty($v)){
                        $map['a.'.$k] = $v;
                    }
                    if($k == 'platform_money' && !empty($v)){
                        $map['a.'.$k] = $v;
                    }
                }
                else
                {
                    $map['a.'.$k] = array('like',$v.'%');
                }


            }
        }

        $counts = $table
            ->alias('a')
            ->where($map)
            ->count();


        $page = $this->page($counts, 20);

        foreach($parameter as $key=>$val)
        {
            if(strlen($val)>0)
                $page->parameter[$key] = urlencode($val);
        }


        $list = $table
            ->alias('a')
            ->join('__GAME__ as c ON c.id = a.appid')
            ->join('__CHANNEL__ as d ON d.id = a.cid')
            ->field('a.*,c.game_name,c.game_platform,d.name cname')
            ->where($map)
            ->order('a.create_time desc')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();



        $map['status'] = 1;
        $total = M('inpour_ptb a')
            ->field('sum(money) as money,sum(getmoney) as total_getmoney,sum(platform_money) as total_platform_money,sum(rebate) as total_rebate')
            ->where($map)->find();


        if(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')) == 'cp')
        {
            $this->assign('cp',1);
        }

        $this->assign('game_platfrom',M('game_platform')->getfield('id,name',true));
        $this->assign('parameter',$parameter);
        $this->assign('total',$total);
        $this->assign('page',$page->show('Admin'));
        $this->assign('list',$list);
        $this->assign('game_list',get_game_list($parameter['appid']));
        $this->assign('channel_list',get_channel_list($parameter['cid']));
        $this->display();
    }


	public function channel_pay()
	{
		//接受Post数据
		$parameter = array();
		$parameter['appid'] = I('appid');
		$parameter['cid'] = I('cid');
		$today_time = date('Y-m-d',time());
		$parameter['start_time'] = I('start_time')?I('start_time'):$today_time;
		$parameter['end_time'] = I('end_time')?I('end_time'):$today_time;



		//组装搜索条件
		$map = array();

		$game_role = session('game_role');

		$map['a.parent'] = 0;

		if($game_role !='all')
		{
			$map['a.appid'] = array('in',$game_role);
		}

		$channel_role = session('channel_role');

		if($channel_role !='all')
		{
			$map['a.cid'] = array('in',$channel_role);

			$channel_role_arr =  M('channel')->where(array('id'=>array('in',$channel_role)))->getfield('parent',true);
			if($channel_role_arr && !in_array(0,$channel_role_arr))
			{
				unset($map['a.parent']);
			}
		}

		foreach($parameter as $k=>$v)
		{
			if(strlen($v)>0)
			{

				if($k == 'orderid' || $k == 'orderid_other')
				{
					$map['a.'.$k] = array('like',$v.'%');
				}
				elseif($k == 'start_time')
				{
					$map['a.create_time'][] = array('egt',strtotime($v));
				}
				elseif($k == 'end_time')
				{
					$map['a.create_time'][] = array('lt',strtotime($v)+3600*24);
				}
				else
				{
					if($k == 'cid' )
					{
						unset($map['a.parent']);
					}
					$map['a.'.$k] = $v;
				}

				if($k == 'status' && $v == 'all')
				{
					unset($map['a.'.$k]);
				}
			}
		}



		$counts = $this->Inpour_cut
			->alias('a')
			->where($map)
			->count();



		$page = $this->page($counts, 20);

		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
				$page->parameter[$key] = urlencode($val);
		}


		$list = $this->Inpour_cut
			->alias('a')
			->join('__GAME__ as b ON b.id = a.appid')
			->field('a.*,b.game_name')
			->where($map)
			->order('a.create_time desc')
			->limit($page->firstRow . ',' . $page->listRows)
			->select();



		$order_ids = '';
		if(is_array($list))
		{
			foreach($list as $v)
			{
				$order_ids.=$v['orderid'].',';
			}
			$order_ids = trim($order_ids,',');
		}



		$usernames = $this->Inpour->where(array('orderID'=>array('in',$order_ids)))->getfield('orderID,username');


		//计算合计
		$total = $this->Inpour_cut
			->alias('a')
			->field('sum(ordermoney) as ordermoney,sum(getmoney) as total_getmoney,sum(actualmoney) as total_actualmoney')
			->where($map)
			->find();

		$this->assign('usernames',$usernames);
		$this->assign('parameter',$parameter);
		$this->assign('page',$page->show('Admin'));
		$this->assign('list',$list);
		$this->assign('game_list',get_game_list($parameter['appid']));
		$this->assign('channel_list',get_channel_list($parameter['cid']));
		$this->assign('total',$total);
		$this->display();
	}


	public function game_recon()
	{
		//接受Post数据
		$parameter = array();
		$parameter['appid'] = I('appid');
		$parameter['cid'] = I('cid');
		$today_time = date('Y-m-d',time());
		$parameter['start_time'] = I('start_time')?I('start_time'):$today_time;
		$parameter['end_time'] = I('end_time')?I('end_time'):$today_time;

		//组装搜索条件
		$map = array();

		$map['a.parent'] = 0;

		$game_role = session('game_role');

		if($game_role !='all')
		{
			$map['a.appid'] = array('in',$game_role);
		}

		$channel_role = session('channel_role');

		if($channel_role !='all')
		{
			$map['a.cid'] = array('in',$channel_role);
		}

		foreach($parameter as $k=>$v)
		{
			if(strlen($v)>0)
			{

				if($k == 'orderid' || $k == 'orderid_other')
				{
					$map['a.'.$k] = array('like',$v.'%');
				}
				elseif($k == 'start_time')
				{
					$map['a.create_time'][] = array('egt',strtotime($v));
				}
				elseif($k == 'end_time')
				{
					$map['a.create_time'][] = array('lt',strtotime($v)+3600*24);
				}
				else
				{
					if($k == 'cid' )
					{
						unset($map['a.parent']);
					}
					$map['a.'.$k] = $v;
				}

				if($k == 'status' && $v == 'all')
				{
					unset($map['a.'.$k]);
				}
			}
		}

		$counts = $this->Inpour_cut
			->alias('a')
			->where($map)
			->count();

		$page = $this->page($counts, 20);

		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
				$page->parameter[$key] = urlencode($val);
		}

		$list = $this->Inpour_cut
			->alias('a')
			->join('__GAME__ as b ON b.id = a.appid')
			->field('a.*,b.game_name')
			->where($map)
			->order('a.create_time desc')
			->limit($page->firstRow . ',' . $page->listRows)
			->select();

		$order_ids = '';
		if(is_array($list))
		{
			foreach($list as $v)
			{
				$order_ids.=$v['orderid'].',';
			}
			$order_ids = trim($order_ids,',');
		}

		$usernames = $this->Inpour->where(array('orderID'=>array('in',$order_ids)))->getfield('orderID,username');

		//计算合计
		$total = $this->Inpour_cut
			->alias('a')
			->field('sum(ordermoney) as ordermoney,sum(getmoney) as total_getmoney,sum(actualmoney) as total_actualmoney')
			->where($map)
			->find();

		if(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')) == 'cp')
		{
			$this->assign('cp',1);
		}

		$this->assign('usernames',$usernames);
		$this->assign('parameter',$parameter);
		$this->assign('page',$page->show('Admin'));
		$this->assign('list',$list);
		$this->assign('game_list',get_game_list($parameter['appid']));
		$this->assign('channel_list',get_channel_list($parameter['cid']));
		$this->assign('total',$total);
		$this->display();
	}

	public function info()
	{
		$id = I('get.id');
		$info = $this->Inpour->
		where(array('id'=>$id))->
		field('id,orderID,jz_other,payType,pay_to,call_back,username,appid,serverID,serverNAME,productID,productNAME,roleID,roleNAME')->
		find();

		$appname = M('game')->where(array('id'=>$info['appid']))->getfield('game_name');

		//是否接入融合
		$is_ronghe = (int)(strpos($info['pay_to'],'dev.singmaan.com/')!==false);


		$this->assign('is_ronghe',$is_ronghe);
		$this->assign('appname',$appname);
		$this->assign('info',$info);
		$this->display();
	}


	public function getSign($arr,$serverkey){
		$str = '';
		foreach ($arr as $k=>$v){
			$str .= $k.'='.$v.'&';
		}
		$str = md5(trim($str,'&').$serverkey);
		return $str;
	}

	public function apply_rebate()
	{
		$username = I('username');
		$appid = I('appid');

		if(!$username)
		{
			$this->error('请输入游戏账号');
		}

		$player_info = M('player')->where(array('username'=>$username))->field('channel,id')->find();

		$channel_role = session('channel_role');

		if($channel_role !='all')
		{
			if(!in_array($player_info['channel'],explode(',',$channel_role)))
			{
				$this->error('游戏账号不属于该账号所属渠道');
			}
			$map['cid'] = array('in',$channel_role);
		}

		if($appid)
		{
			$map['appid'] = $appid;
		}

		$map['username'] = $username;
		//获取三天内的充值 排序平台币

		$start_day = strtotime(date('Y-m-d',strtotime('-2 days')));
		$map['create_time'] = array('egt',$start_day);
		$map['payType'] = array('neq',10);
		$map['status'] = 1;
		$map['is_rebated'] = 0;
		$inpour_Info = M('inpour')->field('id,appid,serverID,money,roleNAME,DATE_FORMAT(FROM_UNIXTIME(create_time),"%Y-%m-%d") create_time')->where($map)->select();

		//组装数据 将满足条件的充值生成渠道工单 并将订单设置为已申请返利

		$data = array();
		foreach($inpour_Info as $v)
		{
			$key = $v['appid'].'_'.$v['serverID'].'_'.$v['create_time'];
			$data[$key]['money'] += $v['money'];
			$data[$key]['pay_ids'] .= $v['id'].',';
			$data[$key]['role_name'] = $v['roleNAME'];
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
					'role_name'=>$v['role_name'],
					'server_name'=>$key[1],
					'title'=>'申请返利',
					'type'=>1,
					'desc'=>$key[2].'单日充值累计金额'.$v['money'].'元',
					'create_time'=>$time,
					'modify_time'=>$time,
				);

				if(($id = M('question')->add($data))!==false)
				{
					M('inpour')->where(array('id'=>array('in',trim($v['pay_ids'],','))))->setField('is_rebated',1);

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
		if(M('inpour')->where(array('id'=>I('id')))->setField('is_rebated',1)!==false)
		{
			$this->success('操作成功');
		}
		else
		{
			$this->error('操作失败');
		}
	}

	/**
	 * ajax返平台币操作
	 */
	public function do_rebate(){
		$id = I('id');
		$rate = I('rate');
		$info = M('inpour')->where(array('id'=>$id))->find();
		if($info['status'] == 1 || $info['status'] == 2){  //状态为 成功、发货中
			$rebate = $info['money'] * ($rate/100) * 10;
			$result = M('inpour')->where(array('id'=>$id))->setField('rebate',$rebate + $info['rebate']);
			if($result !== false){
				$player = M('player')->where(array('id'=>$info['uid']))->find();
				$platform_money = $player['platform_money'] + $rebate;
				M('player')->where(array('id'=>$info['uid']))->setField('platform_money',$platform_money);
				$data['uid'] = $info['uid'];
				$data['type'] = 8;  //返平台币
				$data['platform_change'] = $rebate;
				$data['platform_counts'] = $platform_money;
				$data['create_time'] = time();
				M('platform_detail_logs')->add($data);
				$this->success('操作成功');
			}
		}
		$this->error('操作失败');

	}

	/**
     * 对账统计
     */
	public function pay_ztg(){
	    $start = I('start_time');
	    $end = I('end_time');
	    $game = I('appid','--');
	    $cid = I('cid');

        $maxTime = date('Y-m-d',time());
        $startTime = $start ? : date('Y-m-d',strtotime('-1 week'));
        $endTime = $end ? : $maxTime;


        if(strtotime($endTime) - strtotime($startTime) >= 180*3600*24) {
            $this->error('不能查询超过180天以上');
        }

        //没有game参数
        if($game == '--'){
            $game_role = session('game_role');
            if($game_role !='all'){
                $map_sdk['appid'] = array('in',$game_role);
            }
        }else{
            $map_sdk['appid'] = $game;
            $game = M('Game')->where(array('id'=>$game))->getfield('game_name');
        }
        //没有渠道参数
        if(empty($cid)){
            $channel_role = session('channel_role');
            if($channel_role !='all'){
                $ids = M('channel')->where(array('id|parent'=>array('in',$channel_role),'status'=>1,'type'=>2))->getField('id',true);
                if(is_array($ids))
				{
					$ids = implode(',',$ids);
				}
				else
				{
					$ids = '';
				}

                $map_sdk['cid'] = array('in',$ids);
            }else{
                $channel_ids = M('channel')->where(array('status'=>1,'type'=>2))->getField('id',true);
				if(is_array($channel_ids))
				{
					$channel_ids = implode(',',$channel_ids);
				}
				else
				{
					$channel_ids = '';
				}
                $map_sdk['cid'] = array('in',$channel_ids);
            }
        }else{
            $map_sdk['cid'] = $cid;
        }

        $map_sdk['pay_to_time'] = array(array('egt',strtotime($startTime)),array('elt',strtotime($endTime.' 23:59:59')));



        //sdk支付数据
        $sdk_pay = M('inpour')->where(array_merge($map_sdk,array('status'=>1)))->order('cid')->group('cid')->getfield('cid,sum(money) money,sum(getmoney) getmoney',true);



        $channel_map = array('status'=>1,'type'=>2);
        $channel_map['id'] = $map_sdk['cid'];
        if($cid){
            $channels = M('Channel')->where($channel_map)->getfield('id,name,parent',true);
        }else{
            $channel_map['parent'] = 0;
            $channels = M('Channel')->where($channel_map)->getfield('id,name,parent',true);
            $channel_map['parent'] = array('neq',0);

            $child_channels = M('channel')->where($channel_map)->field('parent,id,name')->order('parent')->select();

            foreach($child_channels as $v){
                $channels[$v['parent']]['child'][] = $v;
            }
        }
//        dump($sdk_pay);
        $totalMoney = 0;
        $totalGetMoney = 0;
		$totalLevelBonus = 0;
        foreach($channels as $k=>$v){
            if(isset($v['child'])){
                foreach($v['child'] as $k1=>$v1){
                    $channels[$k]['child'][$k1]['money'] = isset($sdk_pay[$v1['id']]) ? $sdk_pay[$v1['id']]['money'] : 0.00;
                    $channels[$k]['child'][$k1]['getmoney'] = isset($sdk_pay[$v1['id']]) ? $sdk_pay[$v1['id']]['getmoney'] : 0.00;
					$tg_level = get_tg_level($channels[$k]['child'][$k1]['getmoney']);
                    $channels[$k]['child'][$k1]['level_bonus'] = $tg_level['bonus'];
                    $channels[$k]['money'] += isset($sdk_pay[$v1['id']]) ? $sdk_pay[$v1['id']]['money'] : 0.00;
                    $channels[$k]['getmoney'] += isset($sdk_pay[$v1['id']]) ? $sdk_pay[$v1['id']]['getmoney'] : 0.00;
					$channels[$k]['level_bonus'] += $channels[$k]['child'][$k1]['level_bonus'];
                }
                $channels[$k]['child'] = array_sort_td($channels[$k]['child'], 'money', SORT_DESC);
            }else{
                $channels[$k]['money'] = isset($sdk_pay[$k]) ? $sdk_pay[$k]['money'] : 0.00;
                $channels[$k]['getmoney'] = isset($sdk_pay[$k]) ? $sdk_pay[$k]['getmoney'] : 0.00;
				$tg_level = get_tg_level($channels[$k]['getmoney']);
                $channels[$k]['level_bonus'] = $tg_level['bonus'];
            }
            $totalMoney += $channels[$k]['money'];
            $totalGetMoney += $channels[$k]['getmoney'];
            $totalLevelBonus += $channels[$k]['level_bonus'];
        }
        $channels = array_sort_td($channels,'money',SORT_DESC);
//        dump($channels);
        $this->channel_list = get_channel_list(I('cid'),2);
        $this->game_list = get_game_list(I('appid'),1,'all');
        $this->max = $maxTime;
        $this->start_time = $startTime;
        $this->end_time = $endTime;
        $this->data = $channels;
        $this->game = $game;
        $this->totalMoney = $totalMoney;
        $this->totalGetMoney = $totalGetMoney;
		$this->totalLevelBonus = $totalLevelBonus;
	    $this->display();
    }
}