<?php
/**
 * 工单接口
 * @author qing.li
 * @date 2017-12-8
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class QuestionController extends AppframeController
{
	private $page_size = 20;
	public function get_type()
	{
		$appid = I('appid');
    	$channel = I('channel');    	
    	if(empty($channel) || empty($appid))
    	{
    		$this->ajaxReturn(null,'参数不能为空',11);
    	}
    	
        $app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
   
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}
    	
    	$arr = array(
    	'appid'=>$appid,
    	'channel'=>$channel,
    	'sign'=>I('sign')
    	);
    	
		$res = checkSign($arr,$app_info['client_key']);

    	if(!$res)
    	{
    		$this->ajaxReturn(null,'签名错误',2);
    	}
    	$data['type'] = C('QUESTION_TYPE');
    	$data['question_limit'] = C('DAY_QUESTION_LIMIT');
    	$this->ajaxReturn($data);
	}
	
	public function get_list()
	{
		$appid = I('appid');
    	$uid = I('uid');
    	$channel = I('channel');
    	$page = I('page');
    	
    	if(empty($appid) || empty($uid) || empty($channel) || empty($page))
    	{
    		$this->ajaxReturn(null,'参数不能为空',11);
    	}
    	
    	$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
    	if(!$app_info)
    	{
    		$this->ajaxReturn(null,'app不存在',3);
    	}
    	
    	$arr = array(
    	'appid'=>$appid,
    	'uid'=>$uid,
    	'channel'=>$channel,
    	'page'=>$page,
    	'sign'=>I('sign')  	
    	);
    	
    	$res = checkSign($arr,$app_info['client_key']);
    	
    	if(!$res)
    	{
    		$this->ajaxReturn(null,'签名错误',2);
    	}
    	
    	$player = M('player')->where(array('id'=>$uid))->count();

    	if(!$player)
    	{
    		$this->ajaxReturn(null,'用户不存在',5);
    	}
    	
    	$map = array('uid'=>$uid,'appid'=>$appid,'question_type'=>1);
    	
    	$question_model = M('question');
    	
    	$count = $question_model->where($map)->count();
    	
    	$page = $page?$page:1;
    	
    	$list = $question_model
    	->field('id,order_id,title,modify_time,status')
    	->where($map)
    	->order('modify_time desc')
		->limit(($page-1)*$this->page_size.','.$this->page_size)
		->select();
		
		
		$data = array(
		'count'=>ceil($count/$this->page_size),
		'list'=>$list?$list:array()
		);

		$this->ajaxReturn($data);
	}
	
	public function add_question()
	{
		$appid = I('appid');
		$uid = I('uid');
		$channel = I('channel');
		$title = I('title');
		$type = I('type');
		$desc = I('desc');
		$contract = I('contract');
		
		if(empty($appid) || empty($uid) || empty($channel) || empty($title) || empty($type) || empty($desc))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}
		

		
		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}
		
		
		$arr = array(
		'appid'=>$appid,
		'uid'=>$uid,
		'channel'=>$channel,
		'title'=>$title,
		'type'=>$type,
		'desc'=>htmlspecialchars($desc),
		'contract'=>$contract,
		'sign'=>I('sign')
		);
		
		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}
		
		$player = M('player')->where(array('id'=>$uid))->find();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}
		
		$question_contract_enabled = M('channel')->where(array('id'=>$player['channel']))->getfield('question_contract_enabled');

		if($question_contract_enabled && empty($contract))
		{
			$this->ajaxReturn(null,'联系方式不能为空',11);
		}
		
		$question_model = M('question');
		
		//查询该用户今天提交了几次工单
		$map = array();
		$map['uid'] = $uid;
		$map['appid'] = $appid;
		$map['create_time'] = array('egt',strtotime(date('Y-m-d')));
		
		$day_question_count = $question_model->where($map)->count();
		
		if($day_question_count >= C('DAY_QUESTION_LIMIT'))
		{
			$this->ajaxReturn(null,'每天只能提交'.C('DAY_QUESTION_LIMIT').'次工单',41);
		}
		
		if(empty($contract))
		{
			$channel_name = M('channel')->where(array('id'=>$player['channel']))->getfield('name');
			$contract = $channel_name.'-'.$player['channel'];
		}
		
		$time = time();
		//生成工单ID
		$data = array(
			'question_type'=>1,
		'order_id'=>uniqid(),
		'uid'=>$uid,
		'channel'=>$player['channel'],
		'appid'=>$appid,
		'title'=>$title,
		'type'=>$type,
		'desc'=>$desc,
		'contract'=>$contract,
		'create_time'=>$time,
		'modify_time'=>$time
		);
		
		if(($id = $question_model->add($data))!==false)
		{
			//玩家工单提交成功 加入消息队列
			$link = U('Admin/WorkOrder/details',array('id'=>$id));
			create_admin_message(3,$id,'all',$link,$appid);
			$this->ajaxReturn(null,'提交成功');
		}
		$this->ajaxReturn(null,'提交失败',0);
		
	}
	
	public function get_question_info()
	{
		$appid = I('appid');
		$uid = I('uid');
		$channel = I('channel');
		$question_id = I('question_id');
		$page = I('page');
		
		if(empty($appid) ||empty($uid) || empty($channel) || empty($question_id) || empty($page))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}
		
		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}
		
		$arr = array(
		'appid'=>$appid,
		'uid'=>$uid,
		'channel'=>$channel,
		'question_id'=>$question_id,
		'page'=>$page,
		'sign'=>I('sign')
		);
		
		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}		
		
		$player = M('player')->where(array('id'=>$uid))->count();
		
		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}

		$question_model = M('question');

		$question_exists = $question_model->where(array('id'=>$question_id))->count();

		if(!$question_exists)
		{
			$this->ajaxReturn(null,'工单不存在',42);
		}
		
		$page = $page?$page:1;
		
		if($page == 1)
		{
			//获取工单内容
			$question = $question_model
			->field('id,order_id,title,type,desc')
			->where(array('id'=>$question_id))
			->find();
			
			$question_type = C('QUESTION_TYPE');
			foreach($question_type as $v)
			{
				if($v['id'] == $question['type'])
				{
					$question['type'] = $v['name'];
					break;
				}
			}
		}

		
		$question_info_model = M('question_info');
		
		$map = array('question_id'=>$question_id);
		$count = $question_info_model
		->where($map)
		->count();
		
		//获取工单回复内容
    	$page = $page?$page:1;
    	
    	$question_list = $question_info_model
    	->field('type,comment,create_time')
    	->where($map)
    	->order('create_time desc')
		->limit(($page-1)*$this->page_size.','.$this->page_size)
		->select();

		//查询工单是否被评级
		$rate_info = M('admin_rate')->field('rate,reason')->where(array('type'=>2,'event_id'=>$question_id))->find();

		$question['rate'] = $rate_info['rate']?$rate_info['rate']:0;
		$question['rate_reason'] = $rate_info['reason']?$rate_info['reason']:'';

		//查询是否可以评价
		$question['is_rate_enabled']= 0;
		if(M('question_info')->where(array('question_id'=>$question_id,'type'=>2))->count() > 0 && $question['rate'] == 0)
		{
			$question['is_rate_enabled']= 1;
		}
		
		$data = array(
		'question'=>$question?$question:array(),
		'question_list'=>
		    array('count'=>ceil($count/$this->page_size),
		          'list'=>$question_list?$question_list:array()),
		);

		
		$this->ajaxReturn($data);
		
	}
	
	public function add_comment()
	{
		$appid = I('appid');
		$uid = I('uid');
		$channel = I('channel');
		$question_id = I('question_id');
		$comment = I('comment');
		
		if(empty($appid) || empty($uid) || empty($channel) ||empty($question_id) || empty($comment))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}
		
		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}
		
		$arr = array(
		'appid'=>$appid,
		'uid'=>$uid,
		'channel'=>$channel,
		'question_id'=>$question_id,
		'comment'=>$comment,
		'sign'=>I('sign')
		);
		
		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		$player = M('player')->where(array('id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}
		
		$question_model = M('question');
		
		$question = $question_model->where(array('id'=>$question_id))->find();
		
		if(!$question)
		{
			$this->ajaxReturn(null,'工单不存在',42);
		}
		
		if($question['status'] == 3)
		{
			$this->ajaxReturn(null,'工单已关闭',43);
		}
				
		
		$data = array(
		'type'=>1,
		'question_id'=>$question_id,
		'comment'=>htmlspecialchars($comment),
		'create_time'=>time(),
		);
		
		if(M('question_info')->add($data)!==false)
		{
			//玩家工单提交成功 加入消息队列
			$link = U('Admin/WorkOrder/details',array('id'=>$question_id));
			create_admin_message(3,$question_id,'all',$link,$appid);

			$question_model->where(array('id'=>$question_id))->save(array('status'=>1));
			$this->ajaxReturn(null,'回复成功');
		}
		$this->ajaxReturn(null,'回复失败',0);
	
	}

	public function user_data()
	{
		$appid = I('appid');
		$username = I('username');

		$info = M('question')->where(array('appid'=>$appid,'username'=>$username))->order('create_time desc')->field('role_name,server_name,yf_uid')->find();

		echo json_encode($info);

	}

	public function message_push()
	{
		$admin_id = session('ADMIN_ID');
		if(!$admin_id)
		{
			$this->ajaxReturn(null,'用户未登录',0);
		}

		$redis = get_redis();

		$key = 'ADMIN_MESSAGE_'.$admin_id;

		if($redis->lLen($key) > 0)
		{
			$data = $redis->lPop($key);
			$data = json_decode($data);
			$this->ajaxReturn($data);
		}
		else
		{
			$this->ajaxReturn(null,'无消息推送',0);
		}

	}


	/**
	 * 关闭并工单评级(渠道工单)
	 */
	public function do_rate()
	{
		$id = I('id');
		$rate = I('rate');
		$reason = I('reason');

		$admin_id = session('ADMIN_ID');

		$question_info = M('question')->where(array('id'=>$id))->find();

		if(!$question_info)
		{
			$this->ajaxReturn(null,'工单不存在',0);
		}

		if($question_info['admin_id'] != $admin_id)
		{
			$this->ajaxReturn(null,'没有权限进行评价',0);
		}

		//查询工单是否被评级
		$rate_info = M('admin_rate')->where(array('type'=>1,'event_id'=>$id))->find();

		if($rate_info)
		{
			$this->ajaxReturn(null,'工单已被评级过，请勿重新提交',0);
		}
		else
		{
			$admin_ids = M('question_info')->where(array('question_id'=>$id,'type'=>2))->getField('admin_id',true);

			if($admin_ids)
			{
				$time = time();
				$admin_rate_model = M('admin_rate');
				foreach($admin_ids as $v)
				{
					$data = array(
						'rate_admin_id'=>$admin_id,
						'admin_id'=>$v,
						'type'=>1,
						'event_id'=>$id,
						'reason'=>$reason,
						'rate'=>$rate,
						'create_time'=>$time
					);
					$admin_rate_model->add($data);
				}
				if(M('question')->where(array('id'=>$id))->setField(array('order'=>0,'status'=>3,'modify_time'=>$time))!==false)
				{
					$this->ajaxReturn(null,'操作成功');
				}
				else
				{
					$this->ajaxReturn(null,'操作失败',0);
				}
			}
			else
			{
				$this->ajaxReturn(null,'客服还未处理过工单,不能关闭和评级',0);
			}

		}


	}

	/**
	 * 关闭并工单评级(玩家工单)
	 */
	public function do_rate_by_player()
	{
		$uid = I('uid');
		$question_id = I('question_id');
		$appid = I('appid');
		$channel = I('channel');
		$rate = I('rate');
		$reason = I('reason');

		if(empty($uid) || empty($question_id) || empty($appid) || empty($channel) || empty($rate))
		{
			$this->ajaxReturn(null,'参数不能为空',11);
		}

		$app_info = M('game')->where(array('status'=>1,'id'=>$appid))->find();
		if(!$app_info)
		{
			$this->ajaxReturn(null,'app不存在',3);
		}

		$arr = array(
			'uid'=>$uid,
			'question_id'=>$question_id,
			'appid'=>$appid,
			'channel'=>$channel,
			'rate'=>$rate,
			'reason'=>$reason,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,$app_info['client_key']);

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',2);
		}

		if($rate < 3 && empty($reason))
		{
			$this->ajaxReturn(null,'评价低于低于3星时,请提交原因',0);
		}

		$player = M('player')->where(array('id'=>$uid))->find();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',5);
		}


		$question_info = M('question')->where(array('id'=>$question_id))->find();

		if(!$question_info)
		{
			$this->ajaxReturn(null,'工单不存在',42);
		}

		if($question_info['uid'] != $uid)
		{
			$this->ajaxReturn(null,'没有权限进行评价',0);
		}

		//查询工单是否被评级
		$rate_info = M('admin_rate')->where(array('type'=>2,'event_id'=>$question_id))->find();

		if($rate_info)
		{
			$this->ajaxReturn(null,'工单已被评级过，请勿重新提交',0);
		}
		else
		{
			$admin_ids = M('question_info')->where(array('question_id'=>$question_id,'type'=>2))->getField('admin_id',true);

			if($admin_ids)
			{
				$time = time();
				$admin_rate_model = M('admin_rate');
				foreach($admin_ids as $v)
				{
					$data = array(
						'rate_uid'=>$uid,
						'admin_id'=>$v,
						'type'=>2,
						'event_id'=>$question_id,
						'reason'=>$reason,
						'rate'=>$rate,
						'create_time'=>$time
					);
					$admin_rate_model->add($data);
				}
				if(M('question')->where(array('id'=>$question_id))->setField(array('order'=>0,'status'=>3,'modify_time'=>$time))!==false)
				{
					$this->ajaxReturn(null,'操作成功');
				}
				else
				{
					$this->ajaxReturn(null,'操作失败',0);
				}
			}
			else
			{
				$this->ajaxReturn(null,'客服还未处理过工单,不能关闭和评级',0);
			}

		}
	}

}