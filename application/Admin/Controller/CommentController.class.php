<?php
/**
 * 评论控制器
 * @author qing.li
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class CommentController extends AdminbaseController
{
	public function index()
	{
		$this->_dynamics_index();
	}

	public function del()
	{
		$ids = I('ids');
		$ids = implode(',',$ids);
		$comment_model = M('comment');

		$dynamics_id = $comment_model->where(array('id'=>array('in',$ids)))->getfield('dynamics_id',true);



		if($comment_model->where(array('id'=>array('in',$ids)))->save(array('status'=>0))!==false)
		{
			//删除他们的二级评论
			M('comment')->where(array('parent'=>array('in',$ids)))->save(array('status'=>0));
			//评论删除成功 将动态的评论数重置
			foreach($dynamics_id as $v)
			{
				$count = $comment_model->where(array('dynamics_id'=>$v,'comment_type'=>1,'status'=>1))->count();

				M('dynamics')->where(array('id'=>$v))->setfield('comment',$count);
			}
			$this->success('删除成功',U('dynamics_index'));
		}
		else
		{
			$this->error('删除失败');
		}
	}

	public function game_del()
	{
		$ids = I('ids');
		$ids = implode(',',$ids);

		if(M('comment')->where(array('id'=>array('in',$ids)))->save(array('status'=>0))!==false)
		{
			//删除他们的二级评论
			M('comment')->where(array('parent'=>array('in',$ids)))->save(array('status'=>0));
			$this->success('删除成功');
		}
		else
		{
			$this->error('删除失败');
		}

	}

	public function game_index()
	{
		$parameter['appid'] = I('appid');
		$parameter['start_time'] = I('start_time');
		$parameter['end_time'] = I('end_time');
		$parameter['is_fake'] = I('is_fake');
		$parameter['username'] = I('username');

		$game_role = session('game_role');
		$map = array('status'=>1,'comment_type'=>2,'parent'=>0);

		if(!empty($parameter['appid']))
		{
			$map['dynamics_id'] = $parameter['appid'];
		}
		else
		{
			if($game_role !='all')
			{
				$map['dynamics_id'] = array('in',$game_role);
			}
		}

		if(!empty($parameter['start_time']))
		{
			$map['create_time'][] = array('egt',strtotime($parameter['start_time']));
		}

		if(!empty($parameter['end_time']))
		{
			$map['create_time'][] = array('lt',strtotime($parameter['end_time'])+3600*24);
		}

		if(strlen($parameter['is_fake'])>0)
		{
			if($parameter['is_fake'] == 0)
			{
				$map['is_fake'] = $parameter['is_fake'];
			}
			else
			{
				$map['is_fake'] = array('neq',0);
			}
		}

		if(!empty($parameter['username']))
		{
			$uid = M('player')->where(array('username'=>$parameter['username']))->getfield('id');
			$map['uid'] = $uid;
		}


		$comment_model = M('comment');
		$count = $comment_model->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
				$page->parameter[$key] = urlencode($val);
		}

		$list = $comment_model->
		where($map)->
		order('`order` desc,create_time desc')->
		limit($page->firstRow . ',' . $page->listRows)->
		select();




		$uid_sql = '';

		$fake_uid_sql = '';
		$game_ids = '';

		$comment_ids = '';

		foreach($list as $v)
		{
			if($v['is_fake'] == 0)
			{
				$uid_sql.=$v['uid'].',';
				$uid_sql.=$v['to_uid'].',';
			}
			elseif($v['is_fake'] == 1)
			{
				$fake_uid_sql.=$v['uid'].',';
			}
			else
			{
				$fake_uid_sql.=$v['to_uid'].',';
				$uid_sql.=$v['uid'].',';
			}
			$game_ids.=$v['dynamics_id'].',';
			$comment_ids.=$v['id'].',';

		}
		$game_ids = trim($game_ids,',');
		$uid_sql = trim($uid_sql,',');
		$fake_uid_sql = trim($fake_uid_sql,',');
		$comment_ids = trim($comment_ids,',');

		$child_comment_counts  = M('comment')->where(array('parent'=>array('in',$comment_ids),'status'=>1))->group('parent')->getfield('parent,count(*)',true);


		$usernames = M('player')->where(array('id'=>array('in',$uid_sql)))->getfield('id,username',true);
		$fake_usernames = M('comment_user')->where(array('id'=>array('in',$fake_uid_sql)))->getfield('id,username',true);
		$game_names = M('game')->where(array('id'=>array('in',$game_ids)))->getfield('id,game_name',true);

		$this->child_comment_counts = $child_comment_counts;
		$this->game_names =$game_names;
		$this->fake_usernames = $fake_usernames;
		$this->usernames = $usernames;
		$this->list = $list;
		$this->page = $page->show('Admin');
		$this->parameter = $parameter;
		$this->game_list = get_game_list(I('appid'),1,'all');
		$this->display();
	}

	public function top()
	{
		$action = I('action')?I('action'):0;
		$id = I('id');
		$bonus = I('bonus');

		if($bonus > 1000)
		{
			exit(json_encode(array('res'=>0,'info'=>'奖励金额不能大于1000')));
		}

		$data = array('res'=>0,'info'=>'操作失败');
		$save =array('order'=>$action);
		if($action == 1 && $bonus >0)
		{
			//查询是否奖励 如果未奖励 用户添加奖励
			$comment_info = M('comment')->where(array('id'=>$id))->field('uid,bonus')->find();
			if($comment_info['bonus'] == 0)
			{
				M('player')->where(array('id'=>$comment_info['uid']))->setInc('coin',$bonus);
				$coin = M('player')->where(array('id'=>$comment_info['uid']))->getfield('coin');
				M('coin_log')->add(array('uid'=>$comment_info['uid'],'type'=>15,'coin_change'=>$bonus,'coin_counts'=>$coin,'create_time'=>time()));
				$save['bonus'] = $bonus;
			}

		}

		if(M('comment')->where(array('id'=>$id))->save($save)!==false)
		{
			$data = array('res'=>1,'info'=>'操作成功');
		}

		echo json_encode($data);

	}

	public function create_comment()
	{
		set_time_limit(0);
		//灌水
		$appid = I('appid');

		$game_model = M('game');

		$created_comment = $game_model->where(Array('id'=>$appid,'status'=>1))->getfield('created_comment');

		if(!isset($created_comment))
		{
			$this->error('游戏不存在',U('game/index'));
			exit;
		}

		if( $created_comment > 0)
		{
			$this->error('该游戏已经灌水',U('game/index'));
			exit;
		}

		//随机生成的评论数量
		$nums = rand(100,200);

		$comment_template = M('comment_template');

		$max_user_id = M('comment_user')->getfield('Max(id) as id');
		$max_template_id = $comment_template->getfield('Max(id) as id');
		$time = time();
		$max_time = strtotime(date('Y-m-d'))+3600*24-1;

		//生成数据
		$data = array();
		$template_ids = '';
		for($i = 0;$i < $nums ; $i++)
		{
			$user_id = rand(1,$max_user_id);
			$template_id = rand(1,$max_template_id);
			$create_time = rand($time,$max_time);

			$template_ids.=$template_id.',';

			$data[] = array(
				'uid'=>$user_id,
				'comment_type'=>2,
				'dynamics_id'=>$appid,
				'content'=>$template_id,
				'is_fake'=>1,
				'create_time'=>$create_time,
			);
		}

		$template_ids = trim($template_ids,',');

		$templates = $comment_template->where(array('id'=>array('in',$template_ids)))->getfield('id,content',true);

		foreach($data as $k=>$v)
		{
			$data[$k]['content'] = $templates[$v['content']];
		}

		if(M('comment')->addAll($data)!==false)
		{
			M('game')->where(array('id'=>$appid))->setfield('created_comment',1);
			$this->success('灌水成功',U('game_create_comment'));
		}
		else
		{
			$this->error('灌水失败',U('game_create_comment'));
		}


	}

	public function game_create_comment()
	{
		$keywords = I('keywords');

		if($keywords) $where['game_name'] = array('like','%'.$keywords.'%');
		$where['status'] = 1;
		$where['is_audit'] = 1;
		$data = M('game')->where($where)->order('id desc')->select();
		$count = count($data);
		$page = $this->page($count, 15);
		$data = array_slice($data,$page->firstRow, $page->listRows);

		$this->keywords = $keywords;
		$this->page = $page->show('Admin');
		$this->data = $data;
		$this->display();
	}

	public function dynamics_index()
	{
		$this->_dynamics_index();
	}

	public function _dynamics_index()
	{
		$id = I('get.id');
		$parameter['username'] = I('username');
		$parameter['start_time'] = I('start_time');
		$parameter['end_time'] = I('end_time');


		$map = array('status'=>1,'comment_type'=>1,'parent'=>0);
		if($id)
		{
			$map['dynamics_id'] = $id;
		}


		if(!empty($parameter['username']))
		{
			$uid = M('player')->where(array('username'=>$parameter['username']))->getfield('id');
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


		$comment_model = M('comment');
		$count = $comment_model->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameter as $key=>$val)
		{
			if(!empty($val))
				$page->parameter[$key] = urlencode($val);
		}

		$list = $comment_model->
		where($map)->
		order('`order` desc,create_time desc')->
		limit($page->firstRow . ',' . $page->listRows)->
		select();

		$uid_sql = '';

		foreach($list as $v)
		{
			$uid_sql.=$v['uid'].',';
			$uid_sql.=$v['to_uid'].',';
		}
		$uid_sql = trim($uid_sql,',');

		$usernames = M('player')->where(array('id'=>array('in',$uid_sql)))->getfield('id,username',true);

		$this->usernames = $usernames;
		$this->list = $list;
		$this->page = $page->show('Admin');
		$this->parameter = $parameter;
		$this->id = $id;

		$this->display();
	}

	public function comment_info()
	{
		$comment_id = I('request.comment_id');
		$parameter['start_time'] = I('start_time')?I('start_time'):'';
		$parameter['end_time'] = I('end_time')?I('end_time'):'';
		$parameter['username'] = I('username');

		$map = array('status'=>1,'comment_type'=>2,'parent'=>$comment_id);



		if(!empty($parameter['start_time']))
		{
			$map['create_time'][] = array('egt',strtotime($parameter['start_time']));
		}

		if(!empty($parameter['end_time']))
		{
			$map['create_time'][] = array('lt',strtotime($parameter['end_time'])+3600*24);
		}

		if(!empty($parameter['username']))
		{
			$uid = M('player')->where(array('username'=>$parameter['username']))->getfield('id');
			$map['uid'] = $uid;
		}


		$comment_model = M('comment');
		$count = $comment_model->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameter as $key=>$val)
		{
			if(strlen($val)>0)
				$page->parameter[$key] = urlencode($val);
		}

		$list = $comment_model->
		where($map)->
		order('`order` desc,create_time desc')->
		limit($page->firstRow . ',' . $page->listRows)->
		select();


		$uid_sql = '';

		$fake_uid_sql = '';
		$game_ids = '';

		foreach($list as $v)
		{
			if($v['is_fake'] == 0)
			{
				$uid_sql.=$v['uid'].',';
				$uid_sql.=$v['to_uid'].',';
			}
			elseif($v['is_fake'] == 1)
			{
				$fake_uid_sql.=$v['uid'].',';
			}
			else
			{
				$fake_uid_sql.=$v['to_uid'].',';
				$uid_sql.=$v['uid'].',';
			}
			$game_ids.=$v['dynamics_id'].',';

		}
		$game_ids = trim($game_ids,',');
		$uid_sql = trim($uid_sql,',');
		$fake_uid_sql = trim($fake_uid_sql,',');


		$usernames = M('player')->where(array('id'=>array('in',$uid_sql)))->getfield('id,username',true);
		$fake_usernames = M('comment_user')->where(array('id'=>array('in',$fake_uid_sql)))->getfield('id,username',true);
		$game_names = M('game')->where(array('id'=>array('in',$game_ids)))->getfield('id,game_name',true);

		$comment_info = M('comment')->where(array('id'=>$comment_id))->field('content,imgs')->find();
		$comment_info['imgs'] = json_decode($comment_info['imgs'],true);

		$this->comment_id = $comment_id;
		$this->ftp_url = C('FTP_URL');
		$this->comment_info = $comment_info;
		$this->game_names =$game_names;
		$this->fake_usernames = $fake_usernames;
		$this->usernames = $usernames;
		$this->list = $list;
		$this->page = $page->show('Admin');
		$this->parameter = $parameter;
		$this->game_list = get_game_list(I('appid'),1,'all');
		$this->display();
	}

	public function info()
	{
		$id = I('id');
		$data = M('comment')->field('content,imgs')->where(array('id'=>$id))->find();
		if($data){
			$data['imgs'] = json_decode($data['imgs'],true);

			$this->success($data);
		}else{
			$this->error('请求失败');
		}
	}

}