<?php
/**
 * GM权限控制器
 * @author qing.li
 * @date 2017-09-26
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class GmPriController extends AdminbaseController
{
	public function _initialize()
	{
		parent::_initialize();
		$this->gm_pri_model = M('gm_pri');
		$this->game_model = M('game');
		$this->user_gm_model = M('user_gm');
	}

	public function index()
	{
		$appid = I('appid');
		$map = array('status'=>1,'is_audit'=>1);
		if($appid)
		{
			$map['id'] = $appid;
		}
		else
		{
			$game_role = session('game_role');

			if($game_role !='all')
			{
				$map['id'] = array('in',$game_role);
			}
		}

		$map['gm_pri_id'] = array('neq',0);

		$gm_pri_ids = $this->game_model->distinct(true)->field("gm_pri_id")->where(array($map))->select();

		$gm_pri_sql = '';

		if(is_array($gm_pri_ids))
		{
			foreach($gm_pri_ids as $gm_pri_id)
			{
				$gm_pri_sql.=$gm_pri_id['gm_pri_id'].',';
			}
			$gm_pri_sql = trim($gm_pri_sql,',');
		}


		$map = array('status'=>1);
		$map['id'] = array('in',$gm_pri_sql);

		$count = $this->gm_pri_model
		->where($map)
		->count();

		$page = $this->page($count, 20);
		$page->parameter['appid'] = urlencode($appid);

		$list = $this->gm_pri_model
		->where($map)
		->field('id,name,create_time')
		->order('create_time desc')
		->limit($page->firstRow . ',' . $page->listRows)
		->select();

		$this->assign('list',$list);
		$this->assign('games',get_game_list($appid,1,'all'));
		$this->display();
	}


	public function add()
	{
		if(IS_POST)
		{
			$data = I('post.');

			if(!$data['appid'])
			{
				$this->error('请选择游戏');
			}

			if(count($data['gear_id']) > count(array_unique($data['gear_id'])))
			{
				$this->error('档位ID不能相同');
			}

			$data['gear_info'] = '';
			if(!empty($data['gear_id']) && !empty($data['gear_name']) && !empty($data['gear_money']))
			{
				foreach($data['gear_id'] as $k=>$v)
				{
					$gear_info[] = array(
					'gear_id'=>$v,
					'gear_name'=>$data['gear_name'][$k],
					'gear_money'=>$data['gear_money'][$k]
					);
				}

				$data['gear_info'] = json_encode($gear_info);
			}

			$data['create_time'] = time();

			if($id = $this->gm_pri_model->add($data))
			{
				
				$this->game_model->where(array('id'=>array('in',implode(',',$data['appid']))))->save(array('gm_pri_id'=>$id));

				$this->success('添加成功',U('GmPri/index'));
				exit;
			}
			else
			{
				$this->error('添加失败');
			}


		}
		$this->assign('games',get_game_list('',1,'all',0));
		$this->display();
	}

	public function edit()
	{
		if(IS_POST)
		{

			$data = I('post.');
			if(!$data['appid'])
			{
				$this->error('请选择游戏');
			}
			if(count($data['gear_id']) > count(array_unique($data['gear_id'])))
			{
				$this->error('档位ID不能相同');
			}
			$data['gear_info'] = '';
			if(!empty($data['gear_id']) && !empty($data['gear_name']) && !empty($data['gear_money']))
			{
				foreach($data['gear_id'] as $k=>$v)
				{
					$gear_info[] = array(
					'gear_id'=>$v,
					'gear_name'=>$data['gear_name'][$k],
					'gear_money'=>$data['gear_money'][$k]
					);
				}

				$data['gear_info'] = json_encode($gear_info);
			}

			$data['modify_time'] = time();

			if($this->gm_pri_model->save($data))
			{
				$old_appids = $this->game_model->where(array('gm_pri_id'=>$data['id']))->getfield('id',true);

				$old_appids = array_diff($old_appids,$data['appid']);

				//解绑差集的游戏gm权限
				$this->game_model->where(array('id'=>array('in',implode(',',$old_appids))))->save(array('gm_pri_id'=>0));


				$this->game_model->where(array('id'=>array('in',implode(',',$data['appid']))))->save(array('gm_pri_id'=>$data['id']));

				$this->success('修改成功',U('GmPri/index'));
				exit;
			}
			else
			{
				$this->error('修改失败');
			}
		}

		$id = I('id');
		$gm_pri_info = $this->gm_pri_model->where(array('id'=>$id))->find();
		$gear_info = json_decode($gm_pri_info['gear_info'],true);

		$appids = $this->game_model->where(array('gm_pri_id'=>$id))->getfield('id',true);


		$this->assign('gear_info',$gear_info);
		$this->assign('gm_pri_info',$gm_pri_info);
		$this->assign('games',get_game_list($appids,1,'all',array('in',"0,{$id}")));
		$this->assign('id',$id);
		$this->display();
	}


	public function del()
	{
		$id = I('id');
		if($this->gm_pri_model->where(array('id'=>$id))->save(array('status'=>0)))
		{
			if($this->game_model->where(array('gm_pri_id'=>$id))->save(array('gm_pri_id'=>0)))
			{
				$this->success('删除成功');
			}
			else
			{
				//事件回滚
				$this->gm_pri_model->where(array('id'=>$id))->save(array('status'=>1));
			}
		}

		$this->error('删除失败');

	}

	public function authorize()
	{
		if(IS_POST)
		{
			$data = I('post.');
			$this->player_model = M('player');
			//查询用户是否存在
			$player_info = $this->player_model->where(array('username'=>$data['username'],'status'=>1))->find();

			if(!$player_info && preg_match("/^1[34578]{1}\d{9}$/",$data['username']))
			{
				$player_info = $this->player_model->where(array('mobile'=>$data['username'],'status'=>1))->find();
			}

			$data['username'] = $player_info['username'];
			if($player_info)
			{
				$map = array(
				'appid'=>$data['appid'],
				'serverid'=>$data['serverid'],
				'username'=>$data['username'],
				'gm_gear_id'=>$data['gm_gear_id']
				);

				$user_mg = $this->user_gm_model->where($map)->count();
				if($user_mg)
				{
					$this->error('玩家有该档位权限');
				}
				//想游戏研发方发送发货请求
				
				$pay_controller = A('Api/Pay');
				$res = $pay_controller->getGMPower($data['appid'],$data['serverid'],$data['username'],$data['gm_gear_id']);
	
				$res = json_decode($res,true);
				
				if($res['status'] == 1)
				{

					$this->success('授权成功');
					exit;
				}
				
				$this->error('授权失败，游戏研发方发货失败');

			}
			$this->error('玩家不存在');
		}
		$this->assign('games',get_game_list('',1,'all'));
		$this->display();
	}

	public function authorize_list()
	{
		//接受Post数据
		$parameter = array();
		$parameter['appid'] = I('appid');
		$parameter['serverid'] = I('serverid');
		$parameter['username'] = I('username');
		$parameter['orderID'] = I('orderID');
		$parameter['type'] = I('type');
		$parameter['start_time'] = I('start_time');
		$parameter['end_time'] = I('end_time');

		$game_role = session('game_role');
		if($game_role !='all')
		{
			$map['appid'] = array('in',$game_role);
		}

		foreach($parameter as $k=>$v)
		{

			if(!empty($v))
			{
				if($k=='username')
				{
					$map[$k] = array('like',"$v%");
				}
				elseif($k == 'start_time')
				{
					$map['create_time'][] = array('egt',strtotime($v));
				}
				elseif($k == 'end_time')
				{
					$map['create_time'][] = array('lt',strtotime($v)+3600*24);
				}
				else 
				{
					$map[$k] = $v;
				}				
			}
		}

        $count = $this->user_gm_model->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameter as $key=>$val)
		{
			if(!empty($val))
			$page->parameter[$key] = urlencode($val);
		}
		
		$list = $this->user_gm_model
		->where($map)
		->order('create_time desc')
		->limit($page->firstRow . ',' . $page->listRows)
		->select();
		
		$appids = '';
		if(is_array($list))
		{
			foreach($list as $v)
			{
				$appids.=$v['appid'].',';
			}
			$appids = trim($appids,',');
		}
		
		$gamename = $this->game_model->where(array('id'=>array('in',$appids)))->getfield('id,game_name');
	
		$this->assign('gamename',$gamename);
		$this->assign('parameter',$parameter);
		$this->assign('list',$list);
		$this->assign('games',get_game_list($parameter['appid'],1,'all'));
		$this->display();
	}

	/**
	 * 通过游戏获取GM档位
	 *
	 */
	public function get_gm_by_game()
	{
		$appid = I('appid');

		$gm_pri_id = $this->game_model->where(array('id'=>$appid))->getfield('gm_pri_id');
		$gm_gear_info = $this->gm_pri_model->where(array('id'=>$gm_pri_id))->getfield('gear_info');
		echo $gm_gear_info;
	}


	
	 /**
     * 获取区服数据
     */
    public function get_server()
    {

        $appid = I('appid');
        $status = 1;
        $gm_pri_id = $this->game_model->where(array('id'=>$appid))->getField('gm_pri_id');
        
        if($gm_pri_id)
        {
        	$url = $this->gm_pri_model->where(array('id'=>$gm_pri_id))->getfield('server_url');
        
        	if($url)
        	{
        		$res = curl_get($url.'?appid='.$appid);
        		$res = json_decode($res,true);
        		$status = $res['state'];
        	}
        	else 
        	{
        		$status = 3;
        	}
        }
        else 
        {
        	$status = 2;
        }
        
        
        echo json_encode(array('status'=>$status,'data'=>$res['data']));
    
    }
    




}