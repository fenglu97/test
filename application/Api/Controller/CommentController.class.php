<?php
/**
 * 评论
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/10/30
 * Time: 11:27
 */
namespace Api\Controller;
use Common\Controller\AppframeController;
class CommentController extends AppframeController {

	private $page_size = 20;
	private $page_size_v2 = 10;

	public function _initialize()
	{
		if(!is_dir(SITE_PATH."data/log/185sy/".date('Y-m-d',time())))
		{
			mkdir(SITE_PATH."data/log/185sy/".date('Y-m-d',time()),0777);
		}

		$file_name = SITE_PATH."data/log/185sy/".date('Y-m-d',time())."/comment.log";

		$log = date('Y-m-d H:i:s',time())."\r\n".ACTION_NAME."\r\n".urldecode(http_build_query($_REQUEST))."\r\n\r\n";

		file_put_contents($file_name,$log,FILE_APPEND);
	}

	/**
	 * 用户每天第一条评论获取金币
	 */
	public function giveCoin(){
		$data['uid'] = I('uid');
		$data['sign'] = I('sign');
		$key = C('API_KEY');
		//检测签名
		if(!checkSign($data,$key)){
			$this->ajaxReturn('','sign error',0);
		}else{
			if(!M('player')->where(array('id'=>$data['uid']))->find()){
				$this->ajaxReturn('','非法数据',0);
			}
			//获取配置的评论后金币
			$coin_range = get_site_options();
			$coin_range = $coin_range['pl_coin'];
			//如果是VIP用户评论，获得配置的金币倍数
			if(checkVip($data['uid'])){
				$vip = get_site_options();
				$vip = $vip['vip_comment'];
				$coin_times = $vip ? $vip : 3;
			}else{
				$coin_times = 1;
			}

			//当天是否是第一次评论
			$where['uid'] = $data['uid'];
			$where['_string'] = 'DATEDIFF(FROM_UNIXTIME(create_time),NOW())=0';
			$where['type'] = 2;
			if(!M('coin_log')->where($where)->find()){
				//应给用户多少金币
				$coin_arr = explode('-',$coin_range);
				if(count($coin_arr) > 1){
					$coin = rand($coin_arr[0],$coin_arr[1]);
				}else{
					$coin = $coin_arr[0];
				}
				$coin_count = $coin * $coin_times;
				$totle = M('player')->where(array('id'=>$data['uid']))->getField('coin');
				$add = array(
					'uid' => $data['uid'],
					'type' => 2,
					'coin_change' => $coin_count,
					'coin_counts' => $coin_count+$totle,
					'create_time' => time()
				);
				M('coin_log')->add($add);
				M('player')->where(array('id'=>$data['uid']))->setInc('coin',$coin_count);
				$this->ajaxReturn(array('coin'=>$coin_count),'success');
			}
		}
		$this->ajaxReturn('','success');
	}

	public function do_comment()
	{
		$uid = I('uid');
		$to_uid = I('to_uid');
		$channel = I('channel');
		$dynamics_id = I('dynamics_id');
		$content = I('content');
		$comment_type = I('comment_type')?I('comment_type'):1; //1为动态 2为游戏
		$is_fake = I('is_fake'); //被回复的评论的是否是灌水评论
		$is_game_id = I('is_game_id')?I('is_game_id'):0;

		if(empty($uid) || strlen(trim($to_uid))<1 || empty($channel) || empty($dynamics_id) || empty($content))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'to_uid'=>$to_uid,
			'channel'=>$channel,
			'dynamics_id'=>$dynamics_id,
			'content'=>$content,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$player_closed = M('player_closed')->
		where(array('uid'=>$uid,'end_time'=>array('egt',time())))->
		find();

		if($player_closed)
		{
			$this->ajaxReturn(null,$player_closed['remark'],0);
		}

		if($uid == $to_uid)
		{
			$this->ajaxReturn(null,'不能回复自己的评论',0);
		}

		//先查询该用户是否存在

		$player_model = M('player');

		$player = $player_model->where(array('id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		if($comment_type == 1)
		{

			$dynamics_exsist = M('dynamics')->where(array('id'=>$dynamics_id,'status'=>0))->count();

			if($dynamics_exsist  == 0)
			{
				$this->ajaxReturn(null,'动态不存在',0);
			}
		}
		elseif($comment_type ==2)
		{
			if($is_game_id == 0)
			{
				$game_185_info = M('game','syo_',C('185DB'))->where(array('id'=>$dynamics_id))->field('id,tag')->find();
				if(!$game_185_info)
				{
					$this->ajaxReturn(null,'游戏不存在',0);
				}
				$game_model = M('game');
				$game_id = $game_model->where(array('tag'=>$game_185_info['tag']))->getfield('id');

				if(!$game_id)
				{
					$this->ajaxReturn(null,'游戏不存在',0);
				}
				$arr['dynamics_id'] = $game_id;
			}
			else
			{
				if(M('game')->where(array('id'=>$dynamics_id,'status'=>1))->count() == 0)
				{
					$this->ajaxReturn(null,'游戏不存在',0);
				}
			}

		}
		$comment_model = M('comment');
		$content = trim($content);
		if(!in_array($content,C('COMMENT_WHITE_LIST')))
		{
			//如果不属于白名单的内容 查找今天是否有相同内容
			$count = $comment_model->
			where(array('status'=>1,'uid'=>$uid,'content'=>$content,'dynamics_id'=>$arr['dynamics_id'],'comment_type'=>$comment_type,'create_time'=>array('egt',strtotime(date('Y-m-d'))),'is_fake'=>array('neq',1)))->
			count();

			if($count > 0)
			{
				$this->ajaxReturn(null,'相同的评论内容一天内只能评论一次',0);
			}
		}

		unset($arr['channel']);
		unset($arr['sign']);
		$now_time = time();
		$arr['create_time'] = $now_time;

		$arr['content'] = $this->_hide_sentive_word($arr['content']);
		$arr['comment_type'] = $comment_type;

		if($is_fake == 1)
		{
			$arr['is_fake'] = 2;
		}


		if($comment_model->add($arr)!==false)
		{
			//查询是否满足评论奖励条件
			$today_time = strtotime(date('Y-m-d'));
			$map = array(
				'uid'=>$uid,
				'create_time'=>array('egt',$today_time),
				'comment_type'=>$comment_type,
			);
			$comment_count = $comment_model->where($map)->count();

			if($comment_type == 1)
			{
				//添加成功 将动态的评论数+1
				M('dynamics')->where(array('id'=>$dynamics_id))->setInc('comment',1);

				$comment_bonus_con = C('DYNAMICS_COMMENT_BONUS');

				$comment_bonus = 0;

				foreach($comment_bonus_con as $v)
				{
					if($v['num'] == $comment_count)
					{
						$comment_bonus = $v['bonus'];
						break;
					}
				}
			}
			elseif($comment_type == 2 &&  $comment_count == 1)
			{
				//获取配置的评论后金币
				$coin_range = get_site_options();
				$coin_range = $coin_range['pl_coin'];
				//如果是VIP用户评论，获得配置的金币倍数
				if(checkVip($uid))
				{
					$vip = get_site_options();
					$vip = $vip['vip_comment'];
					$coin_times = $vip ? $vip : 3;
				}
				else
				{
					$coin_times = 1;
				}

				//应给用户多少金币
				$coin_arr = explode('-',$coin_range);
				if(count($coin_arr) > 1){
					$coin = rand($coin_arr[0],$coin_arr[1]);
				}else{
					$coin = $coin_arr[0];
				}
				$comment_bonus = $coin * $coin_times;
			}

			if($comment_bonus > 0)
			{
				if($player_model->where(array('id'=>$uid))->setInc('coin',$comment_bonus)!==false)
				{
					$player_coin = $player_model->where(array('id'=>$uid))->getfield('coin');

					$log_data = array(
						'uid'=>$uid,
						'type'=>($comment_type == 1)?7:2,
						'coin_change'=>$comment_bonus,
						'coin_counts'=>$player_coin,
						'create_time'=>$now_time
					);
					M('coin_log')->add($log_data);
				}
			}
			if($comment_count  == 1)
			{
				//如果签到成功 签到任务完成
				if(M('task')->where(array('uid'=>$uid,'type'=>2,'create_time'=>array('egt',strtotime(date('Y-m-d')))))->count() < 1)
				{
					M('task')->add(array('uid'=>$uid,'type'=>2,'create_time'=>$now_time));
				}
			}

			$this->ajaxReturn($comment_bonus,'评论成功');
		}
		$this->ajaxReturn(null,'评论失败',0);
	}

	public function delete_comment()
	{
		//删除动态评论时只能删除自己动态下的评论和自己发表的评论 删除游戏评论只能删除自己发表的评论
		$uid = I('uid');
		$channel = I('channel');
		$comment_id = I('comment_id');

		if(empty($uid) || empty($channel) || empty($comment_id))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'channel'=>$channel,
			'comment_id'=>$comment_id,
			'sign'=>I('sign'),
		);
		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		//先查询该用户是否存在

		$player_model = M('player');

		$player = $player_model->where(array('id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		$comment_model = M('comment');

		$comment_info =  $comment_model->where(array('id'=>$comment_id,'status'=>1))->field('uid,dynamics_id,comment_type')->find();

		if(!$comment_info)
		{
			$this->ajaxReturn(null,'评论不存在',0);
		}

		$dynamics_model = M('dynamics');
		if($uid != $comment_info['uid'])
		{
			if($comment_info['comment_type'] == 2)
			{
				$this->ajaxReturn(null,'没有权限删除该评论',0);
			}

			//如果不是用户发出的评论，查询是否用户发表的动态的评论

			$dynamics_uid = $dynamics_model->where(array('id'=>$comment_info['dynamics_id']))->getfield('uid');

			if($uid != $dynamics_uid)
			{
				$this->ajaxReturn(null,'没有权限删除该评论',0);
			}
		}

		if($comment_model->where(array('id'=>$comment_id))->save(array('status'=>0))!==false)
		{
			//评论删除成功 将动态评论数-1
			if($comment_info['comment_type'] == 1)
			{
				$dynamics_model->where(array('id'=>$comment_info['dynamics_id']))->setDec('comment',1);
			}

			$this->ajaxReturn(null,'删除成功');
		}
		$this->ajaxReturn(null,'删除失败',0);
	}

	public function comment_list()
	{
		$uid = I('uid');
		$channel = I('channel');
		$dynamics_id = I('dynamics_id');
		$type = I('type'); //1为按热度 2为按时间
		$page = I('page');
		$comment_type = I('comment_type')?I('comment_type'):1; //1为动态 2为游戏

		if(empty($dynamics_id) || empty($channel)|| empty($type) || empty($page))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'channel'=>$channel,
			'dynamics_id'=>$dynamics_id,
			'type'=>$type,
			'page'=>$page,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			//$this->ajaxReturn(null,'签名错误',0);
		}

		if($comment_type == 1)
		{

			$dynamics_exsist = M('dynamics')->where(array('id'=>$dynamics_id,'status'=>0))->count();

			if($dynamics_exsist  == 0)
			{
				$this->ajaxReturn(null,'动态不存在',0);
			}
		}
		elseif($comment_type ==2)
		{

			$game_185_info = M('game','syo_',C('185DB'))->where(array('id'=>$dynamics_id))->field('id,tag')->find();

			if(!$game_185_info)
			{
				$this->ajaxReturn(null,'游戏不存在',0);
			}
			$game_model = M('game');
			$game_id = $game_model->where(array('tag'=>$game_185_info['tag']))->getfield('id');

			if(!$game_id)
			{
				$this->ajaxReturn(null,'游戏不存在',0);
			}
			$dynamics_id = $game_id;
		}

		$comment_model = M('comment');

		$now_time = time();

		$count = $comment_model
			->where(array('parent'=>0,'dynamics_id'=>$dynamics_id,'status'=>1,'comment_type'=>$comment_type,'create_time'=>array('elt',$now_time)))
			->count();

		$type = $type?$type:1;
		$page = $page?$page:1;

		if($comment_type == 1)
		{
			$dynamics_info = M('dynamics d')
				->field('d.*,p1.username,p2.nick_name,p2.sex,p2.icon_url,p1.vip')
				->join('left join __PLAYER__ p1 on p1.id=d.uid')
				->join('left join __PLAYER_INFO__ p2 on p2.uid=d.uid')
				->where(array('d.id'=>$dynamics_id))
				->find();

			$dynamics_info['imgs'] = json_decode($dynamics_info['imgs'],true);

			foreach($dynamics_info['imgs'] as $k=>$v)
			{
				$dynamics_info['imgs'][$k] = C('FTP_URL').$v;
			}

			$dynamics_info['nick_name'] = $dynamics_info['nick_name']?$dynamics_info['nick_name']:$dynamics_info['username'];
			unset($dynamics_info['username']);

			$dynamics_info['icon_url'] = get_avatar_url($dynamics_info['icon_url']);

			$dynamics_info['sex'] = $dynamics_info['sex']?$dynamics_info['sex']:0;

			$follow = M('follow')->where(array('uid'=>$uid,'buid'=>$dynamics_info['uid']))->count();

			$dynamics_info['is_follow'] = ($follow>0)?1:0;

			if($uid != 0)
			{
				$dynamics_zan_cai = M('dynamics_like_info')->where(array('uid'=>$uid,'dynamics_id'=>$dynamics_id))->getfield('type');
			}

			$dynamics_info['operate'] = isset($dynamics_zan_cai)?$dynamics_zan_cai:2;
		}

		if($type == 1)
		{
			$order = '`order` desc,likes desc,create_time desc';
		}
		else
		{
			$order = '`order` desc,create_time desc';
		}

		if($page == 1)
		{
			$hot_list = $comment_model
				->field('id,uid,to_uid,content,likes,dislike,create_time,is_fake,order')
				->where(array('parent'=>0,'dynamics_id'=>$dynamics_id,'status'=>1,'likes'=>array('gt',0),'comment_type'=>$comment_type,'create_time'=>array('elt',$now_time)))
				->order('`order` desc,likes desc,create_time desc')
				->limit(0,3)
				->select();
		}

		$list = $comment_model
			->field('id,uid,to_uid,content,likes,dislike,create_time,is_fake,order')
			->where(array('parent'=>0,'dynamics_id'=>$dynamics_id,'status'=>1,'comment_type'=>$comment_type,'create_time'=>array('elt',$now_time)))
			->order($order)
			->limit(($page-1)*$this->page_size.','.$this->page_size)
			->select();

		$uids_sql = '';
		$comment_ids = '';
		$fake_uid_sql = '';

		foreach($list as $v)
		{
			if($v['is_fake'] == 0)
			{
				$uids_sql.=$v['uid'].',';
				$uids_sql.=$v['to_uid'].',';
			}
			elseif($v['is_fake'] == 1)
			{
				$fake_uid_sql.=$v['uid'].',';
			}
			else
			{
				$fake_uid_sql.=$v['to_uid'].',';
				$uids_sql.=$v['uid'].',';
			}
			$comment_ids.=$v['id'].',';
		}

		if(is_array($hot_list))
		{
			foreach($hot_list as $v)
			{
				if($v['is_fake'] == 0)
				{
					$uids_sql.=$v['uid'].',';
					$uids_sql.=$v['to_uid'].',';
				}
				elseif($v['is_fake'] == 1)
				{
					$fake_uid_sql.=$v['uid'].',';
				}
				else
				{
					$fake_uid_sql.=$v['to_uid'].',';
					$uids_sql.=$v['uid'].',';
				}
				$comment_ids.=$v['id'].',';
			}
		}

		$uids_sql = trim($uids_sql,',');


		$fake_uid_sql = trim($fake_uid_sql,',');
		$comment_ids = trim($comment_ids,',');


		$player_model = M('player');

		$player_info = M('player_info')->where(array('uid'=>array('in',$uids_sql)))->getfield('uid,nick_name,icon_url',true);

		$fake_usernames = M('comment_user')->where(array('uid'=>array('in',$fake_uid_sql)))->getfield('id,username,icon_url,is_vip',true);



		$is_zan_cai = array();
		if($uid != 0)
		{
			$is_zan_cai = M('comment_like_info')->where(array('comment_id'=>array('in',$comment_ids),'uid'=>$uid))->getfield('comment_id,type',true);
		}

		$player = $player_model->where(array('id'=>array('in',$uids_sql)))->getfield('id,username',true);

		//查询用户是否是VIP
		$vip_players = $player_model->where(array('id'=>array('in',$uids_sql)))->group('id')->having('vip_end>'.time())->getfield('id,vip_end',true);

		$vip_players = array_keys($vip_players);

		$list = $this->_create_user_info($list,$is_zan_cai,$player,$player_info,$vip_players,$fake_usernames);

		$hot_list = $this->_create_user_info($hot_list,$is_zan_cai,$player,$player_info,$vip_players,$fake_usernames);

		$data = array(
			'count'=>ceil($count/$this->page_size),
			'list'=>$list?$list:array(),
			'hot_list'=>$hot_list?$hot_list:array(),
		);

		if($comment_type == 1)
		{
			$data['dynamics_info'] = $dynamics_info;
		}

		$this->ajaxReturn($data);

	}

	private function _hide_sentive_word($str)
	{
		$file = trie_filter_load(SITE_PATH.'words.dic');
		$res = trie_filter_search_all($file, $str);  // 一次把所有的敏感词都检测出来
		foreach ($res as $k => $v)
		{
			$sensitive_word[] = substr($str, $v[0], $v[1]);
		}
		foreach($sensitive_word as $v)
		{
			$str = str_replace($v,'***',$str);
		}
		trie_filter_free($file);
		return $str;
	}

	private function _create_user_info($list,$is_zan_cai,$player,$player_info,$vip_players,$fake_usernames)
	{
		foreach($list as $k=>$v)
		{
			$list[$k]['like_type'] = isset($is_zan_cai[$v['id']])?$is_zan_cai[$v['id']]:2;

			if($v['is_fake'] == 0)
			{
				$list[$k]['uid_nickname'] = $this->_hide_sentive_word($player_info[$v['uid']]['nick_name'])?
					$this->_hide_sentive_word($player_info[$v['uid']]['nick_name']):
					$this->_hide_sentive_word(hideStar($player[$v['uid']]));

				$list[$k]['uid_iconurl'] = get_avatar_url($player_info[$v['uid']]['icon_url']);

				$list[$k]['uid_vip'] = (in_array($v['uid'],$vip_players))?1:0;

				if($v['to_uid'] != 0)
				{
					$list[$k]['touid_nickname'] = $this->_hide_sentive_word($player_info[$v['to_uid']]['nick_name'])?
						$this->_hide_sentive_word($player_info[$v['to_uid']]['nick_name']):
						$this->_hide_sentive_word(hideStar($player[$v['to_uid']]));
				}
			}
			elseif($v['is_fake'] == 1)
			{
				$list[$k]['uid_nickname'] = $fake_usernames[$v['uid']]['username'];
				$list[$k]['uid_iconurl'] = $fake_usernames[$v['uid']]['icon_url'];
				$list[$k]['uid_vip'] = $fake_usernames[$v['uid']]['is_vip'];
			}
			else
			{
				$list[$k]['uid_nickname'] = $player_info[$v['uid']]['nick_name']?$player_info[$v['uid']]['nick_name']:hideStar($player[$v['uid']]);
				$list[$k]['uid_iconurl'] = get_avatar_url($player_info[$v['uid']]['icon_url']);
				$list[$k]['uid_vip'] = (in_array($v['uid'],$vip_players))?1:0;

				$list[$k]['touid_nickname'] = $fake_usernames[$v['to_uid']]['username'];
			}
		}

		return $list;
	}

	public function get_comment_counts()
	{
		$channel = I('channel');
		$comment_type = I('comment_type');
		$dynamics_id = I('dynamics_id');

		if(empty($channel) || empty($comment_type) || empty($dynamics_id))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'channel'=>$channel,
			'comment_type'=>$comment_type,
			'dynamics_id'=>$dynamics_id,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		if($comment_type == 2)
		{
			$game_185_info = M('game','syo_',C('185DB'))->where(array('id'=>$dynamics_id))->field('id,tag')->find();

			if(!$game_185_info)
			{
				$this->ajaxReturn(null,'游戏不存在',0);
			}
			$game_model = M('game');
			$game_id = $game_model->where(array('tag'=>$game_185_info['tag']))->getfield('id');


			if(!$game_id)
			{
				$this->ajaxReturn(null,'游戏不存在',0);
			}
			$dynamics_id = $game_id;
		}
		else
		{
			$exsits = M('dynamics')->where(array('id'=>$dynamics_id,'status'=>0))->count();
			if($exsits <1)
			{
				$this->ajaxReturn(null,'动态不存在',0);
			}
		}

		$count = M('comment')
			->where(Array('comment_type'=>$comment_type,'dynamics_id'=>$dynamics_id,'status'=>1,'create_time'=>array('elt',time()),'parent'=>0))
			->count();



		$this->ajaxReturn($count);

	}

	public function get_replay_comment()
	{
		$uid = I('uid');
		$comment_type = I('comment_type');
		$channel = I('channel');
		$page = I('page');

		if(empty($uid) || empty($comment_type) || empty($channel) || empty($page))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'comment_type'=>$comment_type,
			'channel'=>$channel,
			'page'=>$page,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$map = array(
			'comment_type'=>$comment_type,
			'to_uid'=>$uid,
			'uid'=>array('neq',$uid),
			'status'=>1,
			'is_fake'=>0,
		);

		$page = $page?$page:1;

		$count = M('comment')
			->where($map)
			->count();

		$replay_comment = M('comment')
			->where($map)
			->field('uid,to_uid,comment_type,dynamics_id,content,is_fake,parent,create_time')
			->order('create_time desc')
			->limit(($page-1)*$this->page_size.','.$this->page_size)
			->select();

		//获取评论人的昵称

		$uids_sql = '';

		foreach($replay_comment as $v)
		{
			$uids_sql.= $v['uid'].',';
		}
		$uids_sql = trim($uids_sql,',');


		$player_info = M('player_info')->where(array('uid'=>array('in',$uids_sql)))->getfield('uid,nick_name',true);
		$player = M('player')->where(array('id'=>array('in',$uids_sql)))->getfield('id,username',true);

		foreach($replay_comment as $k=>$v)
		{
			$replay_comment[$k]['nick_name'] = $player_info[$v['uid']]?$player_info[$v['uid']]:hideStar($player[$v['uid']]);
		}


		$data = array(
			'count'=>ceil($count/$this->page_size),
			'list'=>$replay_comment?$replay_comment:array(),
		);

		$this->ajaxReturn($data);

	}

	public function user_login_app()
	{
		$username = I('username');
		$appid = I('appid');

		if( empty($username) || empty($appid) )
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}


		$arr = array(
			'username'=>$username,
			'appid'=>$appid,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			//$this->ajaxReturn(null,'签名错误',0);
		}



		$game_185_info = M('game','syo_',C('185DB'))->where(array('id'=>$appid))->field('id,tag')->find();


		if(!$game_185_info)
		{
			$this->ajaxReturn(null,'游戏不存在',0);
		}
		$game_model = M('game');
		$game_id = $game_model->where(array('tag'=>$game_185_info['tag']))->getfield('id');


		if(!$game_id)
		{
			$this->ajaxReturn(null,'游戏不存在',0);
		}


		if(M('player_app')->where(array('username'=>$username,'appid'=>$game_id))->count() > 0 )
		{
			$uid = M('player')->where(array('username'=>$username))->getfield('id');
			//查询该用户是否对游戏打过分数
			$user_game_score = M('game_score')->where(array('uid'=>$uid,'appid'=>$game_id))->getfield('score');
			$is_scored = ($user_game_score>0)?$user_game_score:0;
			$this->ajaxReturn(array('is_scored'=>$is_scored),'success',1);
		}
		else
		{
			$this->ajaxReturn(null,'用户安装并登陆过该游戏才能评论',0);
		}


	}

	public function comment_list_v2()
	{
		$uid = I('uid');
		$channel = I('channel');
		$comment_type = I('comment_type');
		$dynamics_id = I('dynamics_id');
		$type = I('type');
		$system = I('system');
		$page = I('page');

		if(empty($channel) || empty($comment_type) || empty($dynamics_id) || empty($type) || empty($page) || empty($system))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'channel'=>$channel,
			'comment_type'=>$comment_type,
			'dynamics_id'=>$dynamics_id,
			'type'=>$type,
			'system'=>$system,
			'page'=>$page,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$type = $type?$type:1;
		$page = $page?$page:1;

		if($comment_type == 1)
		{

			$dynamics_exsist = M('dynamics')->where(array('id'=>$dynamics_id,'status'=>0))->count();

			if($dynamics_exsist  == 0)
			{
				$this->ajaxReturn(null,'动态不存在',0);
			}
		}
		elseif($comment_type ==2)
		{
			$game_185_info = M('game','syo_',C('185DB'))->where(array('id'=>$dynamics_id))->field('id,tag')->find();
			if(!$game_185_info)
			{
				$this->ajaxReturn(null,'游戏不存在',0);
			}
			$game_model = M('game');
			$game_id = $game_model->where(array('tag'=>$game_185_info['tag']))->getfield('id');

			if(!$game_id)
			{
				$this->ajaxReturn(null,'游戏不存在',0);
			}
			$dynamics_id = $game_id;
			if($page == 1)
			{
				//如果是第一页 获取游戏星级评分相关信息
				$game_score['avg_score'] = sprintf("%.1f",M('game_score')->where(array('appid'=>$dynamics_id))->getfield('avg(score)')*2);
				$game_score['score_class'] = M('game_score')->where(array('appid'=>$dynamics_id))->group('score')->getfield('score,count(*) count',true);
				$score_counts = M('game_score')->where(array('appid'=>$dynamics_id))->count();

				foreach($game_score['score_class'] as $k_class=>$v_class)
				{
					$game_score['score_class'][$k_class] = sprintf("%.4f",($v_class/$score_counts));
				}

				$game_score['score_class'] = is_array($game_score['score_class'])?$game_score['score_class']:array();

				$game_score['last_7days_score'] = sprintf("%.1f",M('game_score')->where(array('appid'=>$dynamics_id,'create_time'=>array('egt',strtotime('-6 days'))))->getfield('avg(score)')*2);
				$game_score['new_version_score'] = sprintf("%.1f",M('game_score')->where(array('appid'=>$dynamics_id,'system'=>$system))->group('version_num')->order('version_num desc')->getfield('avg(score)')*2);
//				$game_score['is_scored'] = 0;
//				if($uid != 0)
//				{
//					//查询该用户是否对游戏打过分数
//					$user_game_score = M('game_score')->where(array('uid'=>$uid,'appid'=>$dynamics_id))->getfield('score');
//					$game_score['is_scored'] = ($user_game_score>0)?$user_game_score:0;
//				}

			}
		}

		$comment_model = M('comment');

		$now_time = time();
		$count = $comment_model
			->where(array('dynamics_id'=>$dynamics_id,'status'=>1,'comment_type'=>$comment_type,'create_time'=>array('elt',$now_time),'parent'=>0))
			->count();

		if($comment_type == 1)
		{
			$dynamics_info = M('dynamics d')
				->field('d.*,p1.username,p2.nick_name,p2.sex,p2.icon_url,p1.vip')
				->join('left join __PLAYER__ p1 on p1.id=d.uid')
				->join('left join __PLAYER_INFO__ p2 on p2.uid=d.uid')
				->where(array('d.id'=>$dynamics_id))
				->find();

			$dynamics_info['imgs'] = json_decode($dynamics_info['imgs'],true);

			foreach($dynamics_info['imgs'] as $k=>$v)
			{
				$dynamics_info['imgs'][$k] = C('FTP_URL').$v;
			}

			$dynamics_info['nick_name'] = $dynamics_info['nick_name']?$dynamics_info['nick_name']:$dynamics_info['username'];
			unset($dynamics_info['username']);

			$dynamics_info['icon_url'] = get_avatar_url($dynamics_info['icon_url']);

			$dynamics_info['sex'] = $dynamics_info['sex']?$dynamics_info['sex']:0;

			$follow = M('follow')->where(array('uid'=>$uid,'buid'=>$dynamics_info['uid']))->count();

			$dynamics_info['is_follow'] = ($follow>0)?1:0;

			if($uid != 0)
			{
				$dynamics_zan_cai = M('dynamics_like_info')->where(array('uid'=>$uid,'dynamics_id'=>$dynamics_id))->getfield('type');
			}

			$dynamics_info['operate'] = isset($dynamics_zan_cai)?$dynamics_zan_cai:2;
			$dynamics_uid_label = get_crazy_label($dynamics_info['uid']);
			$dynamics_info['driveLevel'] = $dynamics_uid_label[$dynamics_info['uid']]['driveLevel'];
			$dynamics_info['commentLevel'] = $dynamics_uid_label[$dynamics_info['uid']]['commentLevel'];
			$dynamics_info['helpLevel'] = $dynamics_uid_label[$dynamics_info['uid']]['helpLevel'];
			$dynamics_info['signLevel'] = $dynamics_uid_label[$dynamics_info['uid']]['signLevel'];
		}

		if($type == 1)
		{
			$order = '`order` desc,likes desc,create_time desc';
		}
		else
		{
			$order = '`order` desc,create_time desc';
		}

		$list = $comment_model
			->field('id,uid,content,likes,dislike,imgs,create_time,is_fake,order,bonus')
			->where(array('dynamics_id'=>$dynamics_id,'status'=>1,'comment_type'=>$comment_type,'create_time'=>array('elt',$now_time),'parent'=>0))
			->order($order)
			->limit(($page-1)*$this->page_size_v2.','.$this->page_size_v2)
			->select();

		$uids_sql = '';
		$comment_ids = '';

		foreach($list as $k=>$v)
		{
			$child_list = $comment_model
				->field('id,uid,to_uid,content,likes,dislike,imgs,create_time,is_fake')
				->where(array('dynamics_id'=>$dynamics_id,'status'=>1,'comment_type'=>$comment_type,'create_time'=>array('elt',$now_time),'parent'=>$v['id']))
				->order('`order` desc,create_time desc')
				->limit(0,3)
				->select();

			if(!empty($child_list)) $list[$k]['child']['list'] = $child_list;

			foreach($child_list as $child_v)
			{
				$uids_sql.=$child_v['uid'].',';
				$uids_sql.=$child_v['to_uid'].',';
			}

			$uids_sql.=$v['uid'].',';
			$comment_ids.=$v['id'].',';
		}

		$uids_sql = trim($uids_sql,',');
		$comment_ids = trim($comment_ids,',');

		$player_model = M('player');

		$player_info = M('player_info')->where(array('uid'=>array('in',$uids_sql)))->getfield('uid,nick_name,icon_url',true);

		$is_zan_cai = array();
		if($uid != 0)
		{
			$is_zan_cai = M('comment_like_info')->where(array('comment_id'=>array('in',$comment_ids),'uid'=>$uid))->getfield('comment_id,type',true);
		}

		$player = $player_model->where(array('id'=>array('in',$uids_sql)))->getfield('id,username',true);

		//查询用户是否是VIP
		$vip_players = $player_model->where(array('id'=>array('in',$uids_sql)))->group('id')->having('vip_end>'.time())->getfield('id,vip_end',true);

		$vip_players = array_keys($vip_players);

		//获取狂人标签
		$crazy_labels = get_crazy_label($uids_sql);

		//计算每个评论的二级评论数量

		$child_comment_count = $comment_model
			->where(array('dynamics_id'=>$dynamics_id,'status'=>1,'comment_type'=>$comment_type,'create_time'=>array('elt',$now_time),'parent'=>array('in',$comment_ids)))
			->group('parent')
			->getfield('parent,count(*) count',true);


		foreach($list as $k=>$v)
		{
			$list[$k]['like_type'] = isset($is_zan_cai[$v['id']])?$is_zan_cai[$v['id']]:2;
			//$list[$k]['uid_nickname'] = $this->_hide_sentive_word($player_info[$v['uid']]['nick_name'])?
				//$this->_hide_sentive_word($player_info[$v['uid']]['nick_name']):
				//$this->_hide_sentive_word(hideStar($player[$v['uid']]));
			$list[$k]['uid_nickname'] = $player_info[$v['uid']]['nick_name']?$player_info[$v['uid']]['nick_name']:hideStar($player[$v['uid']]);
			$list[$k]['uid_iconurl'] = get_avatar_url($player_info[$v['uid']]['icon_url']);
			$list[$k]['uid_vip'] = (in_array($v['uid'],$vip_players))?1:0;
			$list[$k]['imgs'] = json_decode($v['imgs'],true);
			$list[$k]['driveLevel'] = $crazy_labels[$v['uid']]['driveLevel'];
			$list[$k]['commentLevel'] = $crazy_labels[$v['uid']]['commentLevel'];
			$list[$k]['helpLevel'] = $crazy_labels[$v['uid']]['helpLevel'];
			$list[$k]['signLevel'] = $crazy_labels[$v['uid']]['signLevel'];

			if(!empty($list[$k]['imgs'][0]))
			{
				foreach($list[$k]['imgs'] as $img_k=>$img_v)
				{
					$list[$k]['imgs'][$img_k] = C('FTP_URL').$list[$k]['imgs'][$img_k];
				}

			}
			$list[$k]['imgs'] = is_array($list[$k]['imgs'])?$list[$k]['imgs']:array();


			if(is_array($v['child']['list']))
			{
				$list[$k]['child']['count'] = $child_comment_count[$v['id']];

				foreach($v['child']['list'] as $child_key=>$child_v)
				{
					$list[$k]['child']['list'][$child_key]['imgs'] = json_decode($child_v['imgs'],true);
					if(!empty($list[$k]['child']['list'][$child_key]['imgs'][0]))
					{
						foreach($list[$k]['child']['list'][$child_key]['imgs'] as $img_k=>$img_v)
						{
							$list[$k]['child']['list'][$child_key]['imgs'][$img_k] = C('FTP_URL').$list[$k]['child']['list'][$child_key]['imgs'][$img_k];
						}

					}
					$list[$k]['child']['list'][$child_key]['imgs'] = is_array($list[$k]['child']['list'][$child_key]['imgs'])?$list[$k]['child']['list'][$child_key]['imgs']:array();

					$list[$k]['child']['list'][$child_key]['uid_nickname'] = $player_info[$child_v['uid']]['nick_name']?
						$player_info[$child_v['uid']]['nick_name']:
						hideStar($player[$child_v['uid']]);
					if($child_v['to_uid'] != 0)
					{
						$list[$k]['child']['list'][$child_key]['touid_nickname'] = $player_info[$child_v['to_uid']]['nick_name']?
							$player_info[$child_v['to_uid']]['nick_name']:
							hideStar($player[$child_v['to_uid']]);
					}
				}
			}
			else
			{
				$list[$k]['child']['count'] = 0;
			}

			$list[$k]['child'] = $list[$k]['child']?$list[$k]['child']:array();
		}

		if($comment_type == 1)
		{
			$data['dynamics_info'] =$dynamics_info;
		}

		$data['count'] = $count;
		$data['list'] = $list?$list:array();

		if($comment_type == 2 && $page == 1)
		{
			$data['game_score'] = $game_score;
		}

		$this->ajaxReturn($data);



	}

	public function get_comment_info()
	{
		$uid = I('uid');
		$channel = I('channel');
		$comment_id = I('comment_id');
		$page = I('page');

		if(empty($channel) || empty($comment_id) || empty($page))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'channel'=>$channel,
			'comment_id'=>$comment_id,
			'page'=>$page,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$page = $page>0?$page:1;

		$comment_model = M('comment');

		if($page == 1)
		{
			$view_counts = $comment_model
				->where(array('id'=>$comment_id))
				->getfield('view_counts');

			$comment_model->where(array('id'=>$comment_id))->setInc('view_counts');
			$view_counts= $view_counts+1;

			if($view_counts ===null)
			{
				$this->ajaxReturn(null,'评论不存在',0);
			}
		}

		$now_time = time();
		//获取评论总数和评论列表
		$comment_count = $comment_model
			->where(array('status'=>1,'create_time'=>array('elt',$now_time),'parent'=>$comment_id))
			->count();

		$comment_list = $comment_model
			->field('id,uid,to_uid,content,likes,dislike,imgs,create_time,is_fake')
			->where(array('status'=>1,'create_time'=>array('elt',$now_time),'parent'=>$comment_id))
			->order('`order` desc,create_time desc')
			->limit(($page-1)*$this->page_size_v2.','.$this->page_size_v2)
			->select();



		$uids_sql = '';
		$comment_ids = '';

		foreach($comment_list as $k=>$v)
		{
			$uids_sql.=$v['uid'].',';
			$comment_ids.=$v['id'].',';
		}

		$uids_sql = trim($uids_sql,',');
		$comment_ids = trim($comment_ids,',');

		$player_model = M('player');

		$player_info = M('player_info')->where(array('uid'=>array('in',$uids_sql)))->getfield('uid,nick_name,icon_url',true);

		$is_zan_cai = array();
		if($uid != 0)
		{
			$is_zan_cai = M('comment_like_info')->where(array('comment_id'=>array('in',$comment_ids),'uid'=>$uid))->getfield('comment_id,type',true);
		}

		$player = $player_model->where(array('id'=>array('in',$uids_sql)))->getfield('id,username',true);

		//查询用户是否是VIP
		$vip_players = $player_model->where(array('id'=>array('in',$uids_sql)))->group('id')->having('vip_end>'.time())->getfield('id,vip_end',true);

		$vip_players = array_keys($vip_players);

		//获取狂人标签
		$crazy_labels = get_crazy_label($uids_sql);

		foreach($comment_list as $k=>$v)
		{
			$comment_list[$k]['like_type'] = isset($is_zan_cai[$v['id']])?$is_zan_cai[$v['id']]:2;
			$comment_list[$k]['uid_nickname'] = $player_info[$v['uid']]['nick_name']?
				$player_info[$v['uid']]['nick_name']:
				hideStar($player[$v['uid']]);
			$comment_list[$k]['uid_iconurl'] = get_avatar_url($player_info[$v['uid']]['icon_url']);
			$comment_list[$k]['uid_vip'] = (in_array($v['uid'],$vip_players))?1:0;
			$comment_list[$k]['imgs'] = json_decode($v['imgs'],true);
			$comment_list[$k]['driveLevel'] = $crazy_labels[$v['uid']]['driveLevel'];
			$comment_list[$k]['commentLevel'] = $crazy_labels[$v['uid']]['commentLevel'];
			$comment_list[$k]['helpLevel'] = $crazy_labels[$v['uid']]['helpLevel'];
			$comment_list[$k]['signLevel'] = $crazy_labels[$v['uid']]['signLevel'];
			if(!empty($comment_list[$k]['imgs'][0]))
			{
				foreach($comment_list[$k]['imgs'] as $img_k=>$img_v)
				{
					$comment_list[$k]['imgs'][$img_k] = C('FTP_URL').$comment_list[$k]['imgs'][$img_k];
				}
			}
			$comment_list[$k]['imgs'] = is_array($comment_list[$k]['imgs'])?$comment_list[$k]['imgs']:array();
			if($v['to_uid']!=0)
			{
				$comment_list[$k]['touid_nickname'] = $player_info[$v['to_uid']]['nick_name']?
					$player_info[$v['to_uid']]['nick_name']:
					hideStar($player[$v['to_uid']]);
			}
		}



		$data = array(
			'view_counts'=>$view_counts,
			'list'=>$comment_list,
			'count'=>$comment_count
		);

		$this->ajaxReturn($data);

	}

	public function do_comment_v2()
	{
		$uid = I('uid');
		$to_uid = I('to_uid');
		$channel = I('channel');
		$dynamics_id = I('dynamics_id');
		$content = I('content');
		$comment_type = I('comment_type');//1为动态 2为游戏
		$parent = I('parent');
		$score = I('score');
		$system = I('system');
		$is_game_id = I('is_game_id')?I('is_game_id'):0;


		if(empty($uid) || empty($channel) || empty($dynamics_id) || empty($content) ||empty($comment_type) || strlen($parent)<1 || empty($system))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'to_uid'=>$to_uid,
			'channel'=>$channel,
			'dynamics_id'=>$dynamics_id,
			'content'=>$content,
			'comment_type'=>$comment_type,
			'parent'=>$parent,
			'score'=>$score,
			'system'=>$system,
			'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			//$this->ajaxReturn(null,'签名错误',0);
		}

		$player_closed = M('player_closed')->
		where(array('uid'=>$uid,'type'=>0,'end_time'=>array('egt',time())))->
		find();

		if($player_closed)
		{
			$this->ajaxReturn(null,$player_closed['remark'],0);
		}

		if($uid == $to_uid)
		{
			$this->ajaxReturn(null,'不能回复自己的评论',0);
		}

		//先查询该用户是否存在

		$player_model = M('player');

		$player = $player_model->where(array('id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		if($comment_type == 1)
		{

			$dynamics_exsist = M('dynamics')->where(array('id'=>$dynamics_id,'status'=>0))->count();

			if($dynamics_exsist  == 0)
			{
				$this->ajaxReturn(null,'动态不存在',0);
			}
		}
		elseif($comment_type ==2)
		{
			if($is_game_id == 0)
			{
				$game_185_info = M('game','syo_',C('185DB'))->where(array('id'=>$dynamics_id))->field('id,tag')->find();
				if(!$game_185_info)
				{
					$this->ajaxReturn(null,'游戏不存在',0);
				}
				$game_model = M('game');
				$game_id = $game_model->where(array('tag'=>$game_185_info['tag']))->getfield('id');

				if(!$game_id)
				{
					$this->ajaxReturn(null,'游戏不存在',0);
				}
				$arr['dynamics_id'] = $game_id;
			}
			else
			{
				if(M('game')->where(array('id'=>$dynamics_id,'status'=>1))->count() == 0)
				{
					$this->ajaxReturn(null,'游戏不存在',0);
				}
			}


		}

		$comment_model = M('comment');
		$content = trim($content);
		if(!in_array($content,C('COMMENT_WHITE_LIST')))
		{
			//如果不属于白名单的内容 查找今天是否有相同内容
			$count = $comment_model->
			where(array('status'=>1,'uid'=>$uid,'content'=>$content,'dynamics_id'=>$arr['dynamics_id'],'comment_type'=>$comment_type,'create_time'=>array('egt',strtotime(date('Y-m-d'))),'is_fake'=>array('neq',1)))->
			count();

			if($count > 0)
			{
				$this->ajaxReturn(null,'相同的评论内容一天内只能评论一次',0);
			}
		}

		if($parent > 0)
		{
			$parent_uid = $comment_model->where(array('id'=>$parent))->getfield('uid');
			if($uid == $parent_uid)
			{
				$this->ajaxReturn(null,'不能回复自己的评论',0);
			}

			if($parent_uid == $to_uid)
			{
				$arr['to_uid'] = 0;
			}
		}

		unset($arr['channel']);
		unset($arr['sign']);
		$now_time = time();
		$arr['create_time'] = $now_time;

		//$arr['content'] = $this->_hide_sentive_word($arr['content']);
		//$arr['comment_type'] = $comment_type;

		if($_FILES['imgs']){
			$upload = new \Think\Upload(array(
				'rootPath' => './'.C("UPLOADPATH"),
				'subName' => array('date', 'Ymd'),
				'maxSize' => 10485760,
				'exts' => array('jpg', 'png', 'jpeg','gif'),
			));
			$info = $upload->upload();
			if(!$info){
				$this->ajaxReturn(null,$upload->getError(),0);
			}else{
				foreach($info as $v){
					$file_name = trim($v['fullpath'],'.');
					$src[] = str_replace('/www.sy217.com','',$file_name);
				}
				$arr['imgs'] = json_encode($src);
			}
		}

		if($score > 0 && $comment_type == 2)
		{
			//如果评分大于0 进行评分
			$field = ($system == 1)?'android_version_num':'ios_version_num';
			$version_num = M('game')->where(array('id'=>$arr['dynamics_id']))->getfield($field);
			$score_data = array(
				'version_num'=>$version_num,
				'system'=>$system,
				'appid'=>$arr['dynamics_id'],
				'uid'=>$uid,
                'score'=>$score,
				'create_time'=>$now_time,
			);

			if(M('game_score')->where(array('appid'=>$arr['dynamics_id'],'uid'=>$uid))->count() == 0)
			{
				M('game_score')->add($score_data);
			}

		}

		if($comment_model->add($arr)!==false)
		{
			//查询是否满足评论奖励条件
			$today_time = strtotime(date('Y-m-d'));
			$map = array(
				'uid'=>$uid,
				'create_time'=>array('egt',$today_time),
				'comment_type'=>$comment_type,
			);
			$comment_count = $comment_model->where($map)->count();

			if($comment_type == 1)
			{
				//添加成功 将动态的评论数+1
				M('dynamics')->where(array('id'=>$dynamics_id))->setInc('comment',1);

				$comment_bonus_con = C('DYNAMICS_COMMENT_BONUS');

				$comment_bonus = 0;

				foreach($comment_bonus_con as $v)
				{
					if($v['num'] == $comment_count)
					{
						$comment_bonus = $v['bonus'];
						break;
					}
				}
			}
			elseif($comment_type == 2 &&  $comment_count == 1)
			{
				//获取配置的评论后金币
				$coin_range = get_site_options();
				$coin_range = $coin_range['pl_coin'];
				//如果是VIP用户评论，获得配置的金币倍数
				if(checkVip($uid))
				{
					$vip = get_site_options();
					$vip = $vip['vip_comment'];
					$coin_times = $vip ? $vip : 3;
				}
				else
				{
					$coin_times = 1;
				}

				//应给用户多少金币
				$coin_arr = explode('-',$coin_range);
				if(count($coin_arr) > 1){
					$coin = rand($coin_arr[0],$coin_arr[1]);
				}else{
					$coin = $coin_arr[0];
				}
				$comment_bonus = $coin * $coin_times;
			}

			if($comment_bonus > 0)
			{
				if($player_model->where(array('id'=>$uid))->setInc('coin',$comment_bonus)!==false)
				{
					$player_coin = $player_model->where(array('id'=>$uid))->getfield('coin');

					$log_data = array(
						'uid'=>$uid,
						'type'=>($comment_type == 1)?7:2,
						'coin_change'=>$comment_bonus,
						'coin_counts'=>$player_coin,
						'create_time'=>$now_time
					);
					M('coin_log')->add($log_data);
				}
			}
			if($comment_count  == 1)
			{
				//如果签到成功 签到任务完成
				if(M('task')->where(array('uid'=>$uid,'type'=>2,'create_time'=>array('egt',strtotime(date('Y-m-d')))))->count() < 1)
				{
					M('task')->add(array('uid'=>$uid,'type'=>2,'create_time'=>$now_time));
				}
			}

			$this->ajaxReturn($comment_bonus,'评论成功');
		}
		$this->ajaxReturn(null,'评论失败',0);
	}
}