<?php
/**
 * 平台币控制器
 * @author qing.li
 * @date 2017-07-13
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class PlatformmoneyController extends AdminbaseController
{
	public function _initialize()
	{
		parent::_initialize();
		$this->player_model = M('Player');
		$this->platform_money_logs = M('Platform_money_logs');
	}

	public function grant()
	{
		if(IS_POST)
		{
			$post_data = I('post.');
			if(empty($post_data['player_name']) || !is_numeric($post_data['platform_money']))
			{
				$this->error('玩家账号或者金额不合法');
			}

			if($post_data['type'] == 1)
			{
				if($post_data['platform_money'] >= 20000 || $post_data['platform_money'] <= -20000)
				{
					$this->error('单笔金额不能超过20000');
				}
			}
			else
			{
				if($post_data['platform_money'] >= 200000 || $post_data['platform_money'] <= -200000)
				{
					$this->error('单笔金额不能超过200000');
				}
			}

			//查询用户是否存在
			$player_info = $this->player_model->where(array('username'=>$post_data['player_name'],'status'=>1))->find();

			if(!$player_info && preg_match("/^1[34578]{1}\d{9}$/",$post_data['player_name']))
			{
				$player_info = $this->player_model->where(array('mobile'=>$post_data['player_name'],'status'=>1))->find();
			}

			if($player_info)
			{
				$post_data['uid'] = get_current_admin_id();
				$post_data['is_audit'] = 1;
				if($post_data['uid'] != 1)
				{
					$role_id = M('role_user')->where(array('user_id'=>$post_data['uid']))->getfield('role_id');

					if($role_id != 1)
					{
						if(($post_data['type'] == 1 &&($post_data['platform_money'] >= 1000 || $post_data['platform_money'] <= -1000)) ||
						($post_data['type'] == 2 && ($post_data['platform_money'] >= 10000 || $post_data['platform_money'] <= -10000)))
						{
							//需要admin账号手动审核
							$post_data['is_audit'] = 0;
						}
					}

				}

				$today = strtotime(date('Y-m-d'));


				$now_time = time();

				$post_data['player_id'] = $player_info['id'];
				$post_data['player_name'] = $player_info['username'];
				$post_data['create_time'] = $now_time;


				if($post_data['grant_reason'] != 4)
				{
					unset($post_data['reason_info']);
				}

				if($id = $this->platform_money_logs->add($post_data))
				{
					$res = 1;
					//添加成功
					if($post_data['is_audit'] == 1)
					{
						if($post_data['platform_money'] < 0)
						{
							$platform_money = -$post_data['platform_money'];
							if($post_data['type'] == 1)
							{
								if($player_info['platform_money'] < $platform_money)
								{
									$platform_money = $player_info['platform_money'];
								}
								if($platform_money != 0)
								{
									$res = $this->player_model->
									where(array('id'=>$player_info['id']))->
									setDec('platform_money',$platform_money);
								}
							}
							else
							{
								if($player_info['coin'] < $platform_money)
								{
									$platform_money = $player_info['coin'];
								}
								if($platform_money != 0)
								{
									$res = $this->player_model->
									where(array('id'=>$player_info['id']))->
									setDec('coin',$platform_money);
								}
							}

							$money = -$platform_money;


						}
						else
						{
							if($post_data['platform_money'] !=0)
							{
								$field = ($post_data['type'] == 1)?'platform_money':'coin';
								$res = $this->player_model->
								where(array('id'=>$player_info['id']))->
								setInc($field,$post_data['platform_money']);

							}
							$money = $post_data['platform_money'];
						}
					}
					if($res)
					{
						if($post_data['is_audit'] == 1)
						{
							//将记录记录到金币明细或平台币明细
							if($money!=0)
							{
								$player_info = $this->player_model
								->field('platform_money,coin,id')
								->where(array('id'=>$player_info['id']))
								->find();



								if($post_data['type'] == 1)
								{
									$log_model = M('platform_detail_logs');

									$data_log = array(
									'uid'=>$player_info['id'],
									'type'=>4,
									'platform_change'=>$money,
									'platform_counts'=>$player_info['platform_money'],
									'create_time'=>$now_time
									);
								}
								else
								{
									$log_model = M('coin_log');

									$data_log = array(
									'uid'=>$player_info['id'],
									'type'=>6,
									'coin_change'=>$money,
									'coin_counts'=>$player_info['coin'],
									'create_time'=>$now_time
									);
								}

								$log_model->add($data_log);
							}
						}


						$this->clearCache();
						$this->success('添加成功');
					}
					else
					{
						$this->platform_money_logs->delete($id);
						$this->error('添加失败');
					}
				}
				else
				{
					$this->error('添加失败');
				}


			}
			else
			{
				$this->error('玩家账号或手机号码不存在');
			}

		}
		else
		{
			$this->display();
		}

	}

	public function logs()
	{
		//接受Post数据
		$parameter = array();
		$parameter['player_name'] = I('player_name');
		$parameter['start_time'] = I('start_time');
		$parameter['end_time'] = I('end_time');
		$parameter['type'] = I('type');

		if(!empty($parameter['player_name']))
		{
			$map['a.player_name'] = array('like',$parameter['player_name'].'%');
		}
		if(!empty($parameter['start_time']))
		{
			$map['a.create_time'][] = array('egt',strtotime($parameter['start_time']));
		}
		if(!empty($parameter['end_time']))
		{
			$map['a.create_time'][] = array('lt',strtotime($parameter['end_time'])+3600*24);
		}
		if(!empty($parameter['type']))
		{
			$map['a.type'] = $parameter['type'];
		}

		$map['a.is_audit'] = 1;

		$counts = $this->platform_money_logs->alias('a')->where($map)->count();
		$page = $this->page($counts, 20);

		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
			$page->parameter[$key] = urlencode($val);
		}

		$list = $this->platform_money_logs
		->alias('a')
		->join('__USERS__ as b on a.uid =b.id')
		->field('a.*,b.user_login')
		->where($map)
		->order('a.create_time desc,id desc')
		->limit($page->firstRow . ',' . $page->listRows)
		->select();

		$this->assign('list',$list);
		$this->assign('parameter',$parameter);
		$this->assign('page',$page->show('Admin'));
		$this->display();
	}

	public function audit()
	{
		//接受Post数据
		$parameter = array();
		$parameter['player_name'] = I('player_name');
		$parameter['start_time'] = I('start_time');
		$parameter['end_time'] = I('end_time');
		$parameter['type'] = I('type');

		if(!empty($parameter['player_name']))
		{
			$map['a.player_name'] = array('like',$parameter['player_name'].'%');
		}
		if(!empty($parameter['start_time']))
		{
			$map['a.create_time'][] = array('egt',strtotime($parameter['start_time']));
		}
		if(!empty($parameter['end_time']))
		{
			$map['a.create_time'][] = array('lt',strtotime($parameter['end_time'])+3600*24);
		}
		if(!empty($parameter['type']))
		{
			$map['a.type'] = $parameter['type'];
		}

		$map['a.is_audit'] = 0;

		$counts = $this->platform_money_logs->alias('a')->where($map)->count();
		$page = $this->page($counts, 20);

		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
			$page->parameter[$key] = urlencode($val);
		}

		$list = $this->platform_money_logs
		->alias('a')
		->join('__USERS__ as b on a.uid =b.id')
		->field('a.*,b.user_login')
		->where($map)
		->order('a.create_time desc')
		->limit($page->firstRow . ',' . $page->listRows)
		->select();

		$this->assign('list',$list);
		$this->assign('parameter',$parameter);
		$this->assign('page',$page->show('Admin'));
		$this->display();
	}

	public function do_audit()
	{
		$id =  I('get.id');
		$platform_money_info = $this->platform_money_logs->where(array('id'=>$id))->find();

		$player_info = $this->player_model->
		where(array('id'=>$platform_money_info['player_id']))
		->find();

		if($platform_money_info['platform_money'] < 0)
		{
			if($platform_money_info['type'] == 1)
			{
				$platform_money = -$platform_money_info['platform_money'];
				if($player_info['platform_money'] < $platform_money)
				{
					$platform_money = $player_money['platform_money'];
				}
				if($platform_money != 0)
				{
					$res = $this->player_model->
					where(array('id'=>$platform_money_info['player_id']))->
					setDec('platform_money',$platform_money);
				}
			}
			else
			{
				$platform_money = -$platform_money_info['platform_money'];
				if($player_info['coin'] < $platform_money)
				{
					$platform_money = $player_money['platform_money'];
				}
				if($platform_money != 0)
				{
					$res = $this->player_model->
					where(array('id'=>$platform_money_info['player_id']))->
					setDec('coin',$platform_money);
				}
			}

			$money = -$platform_money;


		}
		else
		{
			if($platform_money_info['platform_money'] !=0)
			{
				$field = ($platform_money_info['type'] == 1)?'platform_money':'coin';
				$res = $this->player_model->
				where(array('id'=>$platform_money_info['player_id']))->
				setInc($field,$platform_money_info['platform_money']);
			}
			$money = $platform_money_info['platform_money'];
		}

		if($res)
		{
			$this->platform_money_logs->where(array('id'=>$id))->save(array('is_audit'=>1));

			//将记录记录到金币明细或平台币明细
			if($money!=0)
			{
				$now_time = time();
				$player_info = $this->player_model
				->field('platform_money,coin')
				->where(array('id'=>$platform_money_info['player_id']))
				->find();



				if($platform_money_info['type'] == 1)
				{
					$log_model = M('platform_detail_logs');

					$data_log = array(
					'uid'=>$platform_money_info['player_id'],
					'type'=>4,
					'platform_change'=>$money,
					'platform_counts'=>$player_info['platform_money'],
					'create_time'=>$now_time
					);
				}
				else
				{
					$log_model = M('coin_log');

					$data_log = array(
					'uid'=>$platform_money_info['player_id'],
					'type'=>6,
					'coin_change'=>$money,
					'coin_counts'=>$player_info['coin'],
					'create_time'=>$now_time
					);
				}

				$log_model->add($data_log);
			}

			$this->clearCache();
			$this->success('审核成功');
		}
		else
		{
			$this->error('审核失败');
		}
	}

	protected function clearCache()
	{
		//updateCache(C('UPDATE_CACHE_URL'),'clearCache','player','3e0f65282d1b601e1f07cd9b2384f79a');
	}

	public function detail_log()
	{
		$parameter['username'] = I('username');
		$parameter['start_time'] = I('start_time')?I('start_time'):date('Y-m-d',strtotime('-6 days'));
		$parameter['end_time'] = I('end_time')?I('end_time'):date('Y-m-d');
		$parameter['type'] = I('type');

		$map = array();

		$player_model = M('player');

		if(!empty($parameter['username']))
		{
			$uid = $player_model->where(array('username'=>$parameter['username']))->getfield('id');
			$map['uid'] = $uid;
		}

		if(!empty($parameter['start_time']))
		{
			$map['create_time'][] = array('egt',strtotime($parameter['start_time']));
		}

		if(!empty($parameter['end_time']))
		{
			$map['create_time'][] = array('lt',strtotime($parameter['end_time'])+3600*24);
		}
		if(!empty($parameter['type']))
		{
			$map['type'] = $parameter['type'];
		}


		$platform_detail_logs = M('platform_detail_logs');

		$count = $platform_detail_logs->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameter as $key=>$val)
		{
			if(!empty($val))
			$page->parameter[$key] = urlencode($val);
		}

		$log_list = $platform_detail_logs
		->where($map)
		->order('create_time desc,id desc')
		->limit($page->firstRow . ',' . $page->listRows)
		->select();

		if(is_array($log_list))
		{
			$uids = '';
			foreach($log_list as $v)
			{
				$uids.=$v['uid'].',';
			}
			$uids = trim($uids,',');

			$usernames = $player_model->where(array('id'=>array('in',$uids)))->getfield('id,username');

		}


		$this->assign('usernames',$usernames);
		$this->assign('page',$page->show('Admin'));
		$this->assign('list',$log_list);
		$this->assign('parameter',$parameter);
		$this->display();
	}

}