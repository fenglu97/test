<?php
/**
 * 推广排行
 * @author qing.li
 * @date 2018-2-5
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class ZtgController extends AppframeController
{
	public function month_rank()
	{
		$start_day = strtotime(date('Y-m-01'));

		$map = array();
		$map['create_time'] = array('egt',$start_day);
		$map['status'] = 1;

		$channel_model = M('channel');

		$ztg_channel = $channel_model->where(array('type'=>2,'status'=>1,'parent'=>array('neq',0)))->getfield('id',true);

		$map['cid'] = array('in',implode(',',$ztg_channel));

		$month_rank_new = M('inpour')->
		where($map)->
		group('cid')->
		cache(true)->
		getfield('cid,sum(getmoney) money',true);



		$map = array();
		$map['type'] = 1;
		$map['status'] = 1;
		$map['pay_to_time'] = array('egt',$start_day);
		$map['channel'] = array('in',implode(',',$ztg_channel));

		$month_rank_old = M('syo_pay',null,C('DB_OLDSDK_CONFIG'))
		->where($map)
		->group('channel')
			->cache(true)
		->getfield('channel,sum(getmoney) rmb',true);


		$list = array();
		foreach($ztg_channel as $v)
		{
			$item = array();
			$item['cid'] = $v;
			$item['money'] = (isset($month_rank_new[$v])?$month_rank_new[$v]:0)+(isset($month_rank_old[$v])?$month_rank_old[$v]:0);
			$list[] = $item;
		}

		$new_order = array();
		foreach($list as $k=>$v)
		{
			$new_order[$k] = $v['money'];
		}
		array_multisort($new_order, SORT_DESC, $list);

		$channel_role = session('channel_role');


		if($channel_role != 'all' &&$channel_role!='')
		{
			$parent_channel = $channel_model->where(array('parent'=>0,'id'=>array('in',$channel_role)))->getfield('id');

			if(!$parent_channel)
			{
				$current_channel = explode(',',$channel_role);
				$current_channel = $current_channel[0];

				foreach($list as $k=>$v)
				{
					if($v['cid'] == $current_channel)
					{
						$curr_channel = array(
						'rank'=>$k+1,
						'money'=>$v['money']
						);
						break;
					}
				}

				$yg_rules = ($curr_channel['rank']<=10)?C('ZTG_TOP_YG'):C('ZTG_YG');

				$comission = 0;
				$ratio = '0%';

				if($curr_channel['money'] > $yg_rules['start'])
				{
					foreach($yg_rules['range'] as $range)
					{
						$money_range = explode('-',$range['money']);

						if($curr_channel['money']>=$money_range[0])
						{
							if($money_range[1] != '')
							{
								if($curr_channel['money'] > $money_range[1])
								{
									continue;
								}
							}
							$comission = $range['commision'];
							break;
						}
					}

					$ratio = $comission;

					$comission = ($curr_channel['money'] - $yg_rules['start'])*$comission*0.01;
				}

				$curr_channel['commision'] = $comission;
				$curr_channel['ratio'] = $ratio;
			}

		}

		$list = array_slice($list,0,10);


		foreach($list as $k=>$v)
		{
			if($v['money'] == 0)
			{
				$length = $k;
				break;
			}
		}

		if($length)$list = array_slice($list,0,$length);

		$this->ajaxReturn(array('list'=>$list,'curr'=>$curr_channel));

	}

	public function rank()
	{
		$time = I('time')?I('time'):date('Y-m');
		$next_month = strtotime('+1 month',strtotime($time));
		$rank_counts = I('rank_counts')?I('rank_counts'):10;
		$channel_model = M('channel');

		$admin_id = SESSION('ADMIN_ID');

		if(!$admin_id)
		{
			$this->ajaxReturn(null,'请先登陆',0);
		}

		$current_channel = $channel_model->where(Array('admin_id'=>$admin_id))->getfield('id');


		//获取最近三天

		$ztg_channel = $channel_model->
		where(array('type'=>2,'status'=>1,'parent'=>array('neq',0)))->
		getfield('id',true);

		$where = array();
		$where['_logic'] = 'or';
		$where['create_time'] = array('egt',strtotime('-3 days'));
		$where['modify_time'] = array('egt',strtotime('-3 days'));
		$where1['_complex'] = $where;
		$where1['time']  =$time;
		//推广奖金排行榜
		$rank_channel_gained = M('rank_channel_gained')->field('channel,type')->where($where1)->select();

		$rank_channel_gained_info = array();

		foreach($rank_channel_gained as $v)
		{
			$rank_channel_gained_info[$v['type']][] = $v['channel'];
		}

		$tg_bonus_rank = M('tg_qualified_info')
			->alias('a')
			->join('bt_channel as b on a.channel = b.id')
			->field('channel,sum(tg_qualified_bonus) money')
			->where(array('a.create_time'=>array(array('egt',strtotime($time)),array('lt',$next_month)),'b.parent'=>array('neq',0),'type'=>2))
			->having('money >0')
			->group('channel')
			->order('money desc')
			->cache(true)
			->limit($rank_counts)
			->select();


		//战斗力排行 小组排行
		$map = array();
		$map['create_time'] = array(array('egt',strtotime($time)),array('lt',$next_month));
		$map['status'] = array('neq',3);

		$map['cid'] = array('in',implode(',',$ztg_channel));

		$month_rank_new = M('inpour')->
		where($map)->
		group('cid')->
		cache(true)->
		getfield('cid,sum(getmoney) money',true);


		$map = array();
		$map['type'] = 1;
		$map['status'] = 1;
		$map['created'] = array(array('egt',strtotime($time)),array('lt',$next_month));
		$map['channel'] = array('in',implode(',',$ztg_channel));

//		$month_rank_old = M('syo_pay',null,C('DB_OLDSDK_CONFIG'))
//			->where($map)
//			->group('channel')
//			->cache(true)
//			->getfield('channel,sum(getmoney) rmb',true);

		$ztg_channel = $channel_model->
		where(array('type'=>2,'status'=>1,'parent'=>array('neq',0)))->
		getfield('id,parent,name',true);

		$tg_group_rank = array();
		foreach($ztg_channel as $v)
		{
			//$inc =(isset($month_rank_new[$v['id']])?$month_rank_new[$v['id']]:0)+(isset($month_rank_old[$v['id']])?$month_rank_old[$v['id']]:0);
			$inc =(isset($month_rank_new[$v['id']])?$month_rank_new[$v['id']]:0);

			if($inc > 0)
			{
				$item = array();
				$item['channel'] = $v['id'];
				$item['money'] = $inc;
				$tg_rank[] = $item;

				$tg_group_rank[$v['parent']]['money'] += $inc;
				$tg_group_rank[$v['parent']]['channel'] = $v['parent'];
			}
		}

		$new_order = array();
		foreach($tg_rank as $k=>$v)
		{
			$new_order[$k] = $v['money'];
		}
		array_multisort($new_order, SORT_DESC, $tg_rank);

		$tg_rank = array_slice($tg_rank,0,$rank_counts);

		$channel_legions = M('channel_legion')->field('name,channels')->where(1)->select();

		foreach($tg_rank as $k=>$v)
		{
			$tg_rank[$k]['channel_name'] = $ztg_channel[$v['channel']]['name'];
			$tg_rank[$k]['edit_enbaled'] = ($v['channel'] == $current_channel)?1:0;
			$tg_info = get_tg_level($v['money']);
            $tg_rank[$k]['level_name']= $tg_info['name'];
            $tg_rank[$k]['level_color']= $tg_info['color'];
			$tg_rank[$k]['is_new'] = in_array($v['channel'],$rank_channel_gained_info[2])?1:0;
			$parent_channel = M('channel')->where(array('id'=>$v['channel']))->getfield('parent');
			foreach($channel_legions as $channel_legion)
			{
				if(in_array($parent_channel,explode(',',$channel_legion['channels'])))
				{
					$tg_rank[$k]['legion_name'] = $channel_legion['name'];
				}
			}
		}

		$new_order = array();
		foreach($tg_group_rank as $k=>$v)
		{
			$new_order[$k] = $v['money'];
		}
		array_multisort($new_order, SORT_DESC, $tg_group_rank);

		$tg_group_rank = array_slice($tg_group_rank,0,$rank_counts);

		$ztg_parent_channel = $channel_model->
		where(array('type'=>2,'status'=>1,'parent'=>0))->
		getfield('id,name',true);
		foreach($tg_group_rank as $k=>$v)
		{
			$tg_group_rank[$k]['channel_name'] = $ztg_parent_channel[$v['channel']];
			$tg_group_rank[$k]['edit_enbaled'] = ($v['channel'] == $current_channel)?1:0;
			$tg_group_rank[$k]['is_new'] = in_array($v['channel'],$rank_channel_gained_info[3])?1:0;
			foreach($channel_legions as $channel_legion)
			{
				if(in_array($v['channel'],explode(',',$channel_legion['channels'])))
				{
					$tg_group_rank[$k]['legion_name'] = $channel_legion['name'];
				}
			}
		}

		foreach($tg_bonus_rank as $k=>$v)
		{
			$tg_bonus_rank[$k]['channel_name'] = $ztg_channel[$v['channel']]['name'];
			$tg_bonus_rank[$k]['edit_enbaled'] = ($v['channel'] == $current_channel)?1:0;
			$tg_bonus_rank[$k]['is_new'] = in_array($v['channel'],$rank_channel_gained_info[1])?1:0;
		}

		$this->ajaxReturn(array('tg_bonus_rank'=>$tg_bonus_rank,'tg_rank'=>$tg_rank,'tg_group_rank'=>$tg_group_rank));

	}

	public function show_rank_gain()
	{
		$time = I('time');
		$type = I('type');
		$channel = I('channel');

		$admin_id = SESSION('ADMIN_ID');

		if(!$admin_id)
		{
			$this->ajaxReturn(null,'请先登陆',0);
		}

		$current_channel = M('channel')->where(Array('admin_id'=>$admin_id))->getfield('id');

		$content = M('rank_channel_gained')->where(array('time'=>$time,'type'=>$type,'channel'=>$channel))->getfield('content');

		if($current_channel == $channel)
		{
			$content = str_replace("<br>", "\n", $content);
		}
		else
		{
			$content = str_replace("\n", "<br>", $content);
		}

		$content = $content?$content:'';

		$this->ajaxReturn($content);
	}

	public function edit_rank_gain()
	{
		$time = I('time');
		$type = I('type');
		$channel = I('channel');
		$content = str_replace("\n", "<br>", I('content'));

		$admin_id = SESSION('ADMIN_ID');

		if(!$admin_id)
		{
			$this->ajaxReturn(null,'请先登陆',0);
		}

		$current_channel = M('channel')->where(array('admin_id'=>$admin_id))->getfield('id');

		if($current_channel != $channel)
		{
			$this->ajaxReturn(null,'没有权限',0);
		}

		if(M('rank_channel_gained')->where(array('time'=>$time,'type'=>$type,'channel'=>$channel))->count()>0)
		{
			M('rank_channel_gained')
				->where(array('time'=>$time,'type'=>$type,'channel'=>$channel))
				->save(array('content'=>$content,'modify_time'=>time()));
		}
		else
		{
			M('rank_channel_gained')
				->add(array('time'=>$time,'type'=>$type,'channel'=>$channel,'content'=>$content,'create_time'=>time()));
		}

		$this->ajaxReturn(null,'修改成功');

	}

	public function test()
	{

		M('player_app')->add(array('username'=>18583083265,'appid'=>1000));
		echo M('player_app')->getDbError();

	}

}