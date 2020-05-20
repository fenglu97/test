<?php

/**
 * 赞踩接口
 * @author qing.li
 * @date 2018-01-03
 */

namespace Api\Controller;

use Common\Controller\AppframeController;

class LikeinfoController extends AppframeController
{
	function _initialize()
	{
		parent::_initialize();
		$this->redis = new \Redis();
		$this->redis->connect('127.0.0.1',6379);
		$this->redis->auth(C('REDIS_PASS'));
	}


	public function dynamics_like()
	{
		$uid = I('uid');
		$channel = I('channel');
		$dynamics_id = I('dynamics_id');
		$type = I('type');

		$this->key = "{$uid}_dynamics_{$dynamics_id}";
		if(!$this->_make_mark())
		{
			//如果上一次接口未完成直接返回成功;
			$this->ajaxReturn(null,'操作成功',1,false);
		}

		if(empty($uid) ||empty($channel) || empty($dynamics_id) || strlen(trim($type))<1)
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
		'uid'=>$uid,
		'channel'=>$channel,
		'dynamics_id'=>$dynamics_id,
		'type'=>$type,
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

		$dynamics_exsist = M('dynamics')->where(array('id'=>$dynamics_id))->count();

		if($dynamics_exsist  == 0)
		{
			$this->ajaxReturn(null,'动态不存在',0);
		}

		//查询该用户是否该动态进行赞踩
		$dynamics_like_info_model = M('dynamics_like_info');
		$like_count = $dynamics_like_info_model->where(array('dynamics_id'=>$dynamics_id,'uid'=>$uid))->count();

		if($like_count > 0)
		{
			$this->ajaxReturn(null,'用户已经对该动态进行赞踩',0);
		}


		unset($arr['channel']);
		unset($arr['sign']);
		$now_time = time();
		$arr['create_time'] = $now_time;
		$arr['type'] = ($type == 0)?$type:1;
		if($dynamics_like_info_model->add($arr)!==false)
		{
			//添加成功，将动态的赞踩总数进行+1
			if($type == 0)
			{
				$field = 'dislike';
			}
			else
			{
				$field = 'likes';
			}

			M('dynamics')->where(array('id'=>$dynamics_id))->setInc($field,1);

			//当天是否已获得点赞奖励
			$today_bonus = M('coin_log')
			->where(array('uid'=>$uid,'type'=>8,'create_time'=>array('egt',strtotime(date('Y-m-d')))))
			->count();

			if($arr['type'] == 1 && $today_bonus ==0)
			{
				//查询是否是用户当日首赞
				$today_time = strtotime(date('Y-m-d'));

				$map = array(
				'uid'=>$uid,
				'type'=>1,
				'create_time'=>array('egt',$today_time),
				);

				$dynamiecs_like_count = $dynamics_like_info_model->where($map)->count();
				$comment_like_count = M('comment_like_info')->where($map)->count();
				$like_bonus = 0;
				if(($dynamiecs_like_count+$comment_like_count) == 1)
				{
					$like_bonus = C('DYNAMICS_LIKE_BONUS');
					//是当日首赞
					if($player_model->where(array('id'=>$uid))->setInc('coin',$like_bonus)!==false)
					{
						$player_coin = $player_model->where(array('id'=>$uid))->getfield('coin');

						$log_data = array(
						'uid'=>$uid,
						'type'=>8,
						'coin_change'=>$like_bonus,
						'coin_counts'=>$player_coin,
						'create_time'=>$now_time
						);
						M('coin_log')->add($log_data);
					}
				}

			}
			$this->ajaxReturn(array('bonus'=>$like_bonus,'operate'=>$type),'操作成功');
		}

		$this->ajaxReturn(null,'操作失败',0);

	}

	public function comment_like()
	{
		$uid = I('uid');
		$channel = I('channel');
		$comment_id = I('comment_id');
		$type = I('type');

		$this->key = "{$uid}_comment_{$comment_id}";
		if(!$this->_make_mark())
		{
			//如果上一次接口未完成直接返回成功;
			$this->ajaxReturn(null,'操作成功',1,false);
		}

		if(empty($uid) ||empty($channel) || empty($comment_id) || strlen(trim($type))<1)
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
		'uid'=>$uid,
		'channel'=>$channel,
		'comment_id'=>$comment_id,
		'type'=>$type,
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

		$comment_type = M('comment')->where(array('id'=>$comment_id,'status'=>1))->getfield('comment_type');

		if(!$comment_type)
		{
			$this->ajaxReturn(null,'评论不存在',0);
		}

		//查询该用户是否该评论进行赞踩
		$comment_like_info_model = M('comment_like_info');
		$like_count = $comment_like_info_model->where(array('comment_id'=>$comment_id,'uid'=>$uid))->count();

		if($like_count > 0)
		{
			$this->ajaxReturn(null,'用户已经对该评论进行赞踩',0);
		}


		unset($arr['channel']);
		unset($arr['sign']);
		$now_time = time();
		$arr['create_time'] = $now_time;
		$arr['type'] = ($type == 0)?$type:1;
		if($comment_like_info_model->add($arr)!==false)
		{
			//添加成功，将评论的赞踩总数进行+1
			if($type == 0)
			{
				$field = 'dislike';
			}
			else
			{
				$field = 'likes';
			}

			M('comment')->where(array('id'=>$comment_id))->setInc($field,1);

			//当天是否已获得点赞奖励 仅适用于动态评论的赞踩
			$like_bonus = 0;

			if($comment_type == 1)
			{
				$today_bonus = M('coin_log')
				->where(array('uid'=>$uid,'type'=>8,'create_time'=>array('egt',strtotime(date('Y-m-d')))))
				->count();
				if($arr['type'] == 1 && $today_bonus==0)
				{
					//查询是否是用户当日首赞
					$today_time = strtotime(date('Y-m-d'));

					$map = array(
					'uid'=>$uid,
					'type'=>1,
					'create_time'=>array('egt',$today_time),
					);

					$dynamiecs_like_count = M('dynamics_like_info')->where($map)->count();
					$comment_like_count = $comment_like_info_model->where($map)->count();

					if(($dynamiecs_like_count+$comment_like_count) == 1)
					{
						$like_bonus = C('DYNAMICS_LIKE_BONUS');
						//是当日首赞
						if($player_model->where(array('id'=>$uid))->setInc('coin',$like_bonus)!==false)
						{
							$player_coin = $player_model->where(array('id'=>$uid))->getfield('coin');

							$log_data = array(
							'uid'=>$uid,
							'type'=>8,
							'coin_change'=>$like_bonus,
							'coin_counts'=>$player_coin,
							'create_time'=>$now_time
							);
							M('coin_log')->add($log_data);
						}
					}

				}
			}

			$this->ajaxReturn(array('bonus'=>$like_bonus,'operate'=>$type),'操作成功');
		}

		$this->ajaxReturn(null,'操作失败',0);
	}

	public function cancel_dynamics_like()
	{
		$uid = I('uid');
		$channel = I('channel');
		$dynamics_id = I('dynamics_id');
		$type = I('type');

		$this->key = "{$uid}_dynamics_{$dynamics_id}";

		if(!$this->_make_mark())
		{
			//如果上一次接口未完成直接返回成功;
			$this->ajaxReturn(null,'操作成功',1,false);
		}

		if(empty($uid) ||empty($channel) || empty($dynamics_id) || strlen(trim($type))<1)
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}



		$arr = array(
		'uid'=>$uid,
		'channel'=>$channel,
		'dynamics_id'=>$dynamics_id,
		'type'=>$type,
		'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		if(M('dynamics_like_info')->where(array('uid'=>$uid,'dynamics_id'=>$dynamics_id,'type'=>$type))->delete()!=0)
		{
			//操作成功，将动态的赞踩总数进行-1
			if($type == 0)
			{
				$field = 'dislike';
			}
			else
			{
				$field = 'likes';
			}

			M('dynamics')->where(array('id'=>$dynamics_id))->setdec($field,1);
			$this->ajaxReturn(array('operate'=>2),'取消成功');
		}
		else
		{
			$this->ajaxReturn(null,'取消失败',0);
		}
	}

	public function cancel_comment_like()
	{
		$uid = I('uid');
		$channel = I('channel');
		$comment_id = I('comment_id');
		$type = I('type');

		$this->key = "{$uid}_comment_{$comment_id}";
		if(!$this->_make_mark())
		{
			//如果上一次接口未完成直接返回成功;
			$this->ajaxReturn(null,'操作成功',1,false);
		}

		if(empty($uid) ||empty($channel) || empty($comment_id) || strlen(trim($type))<1)
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
		'uid'=>$uid,
		'channel'=>$channel,
		'comment_id'=>$comment_id,
		'type'=>$type,
		'sign'=>I('sign'),
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		if(M('comment_like_info')->where(array('uid'=>$uid,'comment_id'=>$comment_id,'type'=>$type))->delete()!=0)
		{
			//操作成功，将评论的赞踩总数进行-1
			if($type == 0)
			{
				$field = 'dislike';
			}
			else
			{
				$field = 'likes';
			}

			M('comment')->where(array('id'=>$comment_id))->setDec($field,1);
			$this->ajaxReturn(array('operate'=>2),'取消成功');
		}
		else
		{
			$this->ajaxReturn(null,'取消失败',0);
		}


	}

	public function article_like()
	{
		$uid = I('uid');
		$channel = I('channel');
		$article_id = I('article_id');
		$type = I('type');

		$this->key = "{$uid}_article_id_{$article_id}";
		if(!$this->_make_mark())
		{
			//如果上一次接口未完成直接返回成功;
			$this->ajaxReturn(null,'操作成功',1,false);
		}

		if(empty($uid) ||empty($channel) || empty($article_id) || strlen(trim($type))<1)
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'channel'=>$channel,
			'article_id'=>$article_id,
			'type'=>$type,
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

		$article_model = M('syo_article',null,C('185DB'));
		$article_exsist = $article_model->where(array('id'=>$article_id))->count();

		if($article_exsist  == 0)
		{
			$this->ajaxReturn(null,'文章不存在',0);
		}

		//查询该用户是否该动态进行赞踩
		$article_like_info_model = M('article_like_info');
		$like_count = $article_like_info_model->where(array('article_id'=>$article_id,'uid'=>$uid))->count();

		if($like_count > 0)
		{
			$this->ajaxReturn(null,'用户已经对该文章进行赞踩',0);
		}


		unset($arr['channel']);
		unset($arr['sign']);
		$now_time = time();
		$arr['create_time'] = $now_time;
		$arr['type'] = ($type == 0)?$type:1;
		if($article_like_info_model->add($arr)!==false)
		{
			//添加成功，将动态的赞踩总数进行+1
			if($type == 0)
			{
				$field = 'dislikes';
			}
			else
			{
				$field = 'likes';
			}

			$article_model->where(array('id'=>$article_id))->setInc($field,1);

			$this->ajaxReturn(null,'操作成功');
		}

		$this->ajaxReturn(null,'操作失败',0);
	}

	//通过用户ID 类型 类型ID 生成唯一值 防止多次点击
	private function _make_mark()
	{
		if(!$this->redis->exists($this->key))
		{
			$this->redis->set($this->key,1);
			return true;
		}
		return false;
	}
	//程序结束时删除mark
	private function _delete_mark()
	{
		$this->redis->delete($this->key);
	}


	public function __destruct()
	{
		$this->redis->close();
	}



	protected function ajaxReturn($result, $msg = '', $status = 1, $is_delete_mark = true,$json_option = 0)
	{
		//删除mark值
		if($is_delete_mark)
		{
			$this->_delete_mark();
		}

		$data = array(
			'data' => $result,
			'msg' => $msg,
			'status' => $status,
		);

		if(strlen($_GET['callback'])>0)
		{
			$type = 'JSONP';
		}


		//    $data['referer']=$data['url'] ? $data['url'] : "";


		//     $data['state']=$data['status'] ? "success" : "fail";


		if (empty($type)) $type = C('DEFAULT_AJAX_RETURN');

		header("Access-Control-Allow-Origin:http://across.185sy.com");

		switch (strtoupper($type)) {


			case 'JSON' :


				// 返回JSON数据格式到客户端 包含状态信息


				header('Content-Type:application/json; charset=utf-8');


				exit(json_encode($data, $json_option));


			case 'XML'  :


				// 返回xml格式数据


				header('Content-Type:text/xml; charset=utf-8');


				exit(xml_encode($data));


			case 'JSONP':


				// 返回JSON数据格式到客户端 包含状态信息


				header('Content-Type:application/json; charset=utf-8');


				$handler = isset($_GET[C('VAR_JSONP_HANDLER')]) ? $_GET[C('VAR_JSONP_HANDLER')] : C('DEFAULT_JSONP_HANDLER');


				exit($handler . '(' . json_encode($data, $json_option) . ');');


			case 'EVAL' :


				// 返回可执行的js脚本


				header('Content-Type:text/html; charset=utf-8');


				exit($data);


			case 'AJAX_UPLOAD':


				// 返回JSON数据格式到客户端 包含状态信息


				header('Content-Type:text/html; charset=utf-8');


				exit(json_encode($data, $json_option));


			default :


				// 用于扩展其他返回格式数据


				Hook::listen('ajax_return', $data);


		}

	}



}