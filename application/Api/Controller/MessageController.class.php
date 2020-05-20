<?php
/**
 * 消息接口
 * @author qing.li
 * @date 2017-12-19
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class MessageController extends AppframeController
{
	public function unread_counts()
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
			'sign'=>I('sign')
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$player = M('player')->where(array('id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		$map['_string'] = "`uids` = '' or find_in_set({$uid},`uids`)";
		$map['type']  = 3;
		$map['end_time'] = array('egt',time());
		$msg = M('message')->where($map)->select();

		$umsg = M('user_message')->where(array('uid' => $uid, 'type' => 3))->getField('message_id', true);
		$system = M('player')->where(array('id' => $uid))->getField('system');
		if ($msg) {
			foreach ($msg as $k => $v) {
				if ($v['system'] != 3 && $v['system'] == $system) {
					$msgid[] = $v['id'];
				} elseif ($v['system'] == 3) {
					$msgid[] = $v['id'];
				}
			}
		}
		if (!$umsg) {
			if ($msgid) {
				foreach ($msgid as $k => $v) {
					M('user_message')->add(array('message_id' => $v, 'type' => 3, 'uid' => $uid, 'create_time' => time()));
				}
			}

		} else {
			$id = array_diff($msgid, $umsg);
			if ($id) {
				foreach ($id as $k => $v) {
					M('user_message')->add(array('message_id' => $v, 'type' => 3, 'uid' => $uid, 'create_time' => time()));
				}
			}
		}

		$unread_counts = M('user_message')->where(array('uid'=>$uid,'is_read'=>0))->count();

		$this->ajaxReturn($unread_counts);
	}

	public function get_message_list()
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
			'sign'=>I('sign')
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$player = M('player')->where(array('id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		$user_message_list = M('user_message')
			->alias('a')
			->field('a.id,a.create_time,b.action,a.is_read,b.title,b.desc,a.ext')
			->join('left join __MESSAGE__ b on a.message_id=b.id')
			->where(array('a.uid'=>$uid))
			->order('a.is_read asc,a.create_time desc,a.message_id asc')
			->select();

		foreach($user_message_list as $k=>$v)
		{
			$v['ext'] = json_decode($v['ext'],true);
			foreach($v['ext'] as $key=>$value)
			{
				if($key == 'time')$value=date('m月d日H时i分',$value);
				$user_message_list[$k]['desc'] = str_replace('#'.$key.'#',$value,$user_message_list[$k]['desc']);
			}

		}

		$this->ajaxReturn($user_message_list);


	}

	public function get_message_info()
	{
		$uid = I('uid');
		$channel = I('channel');
		$user_message_id = I('user_message_id');

		if(empty($uid) || empty($channel) ||empty($user_message_id))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'channel'=>$channel,
			'user_message_id'=>$user_message_id,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$player = M('player')->where(array('id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		$user_message_model = M('user_message');

		$user_message_info = $user_message_model
			->alias('a')
			->field('a.id,a.is_get,a.is_read,a.create_time,b.title,b.desc,b.image,b.api_url,b.attach_type,b.attach_count,a.ext')
			->join('left join __MESSAGE__ b on a.message_id=b.id')
			->where(array('a.id'=>$user_message_id))
			->order('a.message_id desc')
			->find();

		$user_message_info['ext'] = json_decode($user_message_info['ext'],true);

		foreach($user_message_info['ext'] as $k=>$v)
		{
			if($k == 'time')$v = date('m月d日H时i分',$v);
			$user_message_info['desc'] = str_replace('#'.$k.'#',$v,$user_message_info['desc']);
		}



		if($user_message_info['is_read'] == 0)
		{
			//首次进入详情，将其标志为已读
			$user_message_model->where(array('id'=>$user_message_id))->save(array('is_read'=>1));
		}

		unset($user_message_info['is_read']);
		$user_message_info['api_url'] = C('API_URL').$user_message_info['api_url'];

		$this->ajaxReturn($user_message_info);
	}

	public function delete_message()
	{
		$uid = I('uid');
		$channel = I('channel');
		$user_message_id = I('user_message_id');

		if(empty($uid) || empty($channel) ||empty($user_message_id))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'channel'=>$channel,
			'user_message_id'=>$user_message_id,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$player = M('player')->where(array('id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		$user_message_model = M('user_message');

		$user_message_info = $user_message_model->where(array('id'=>$user_message_id))->find();

		M('user_message_recycle')->add($user_message_info);

		if($user_message_model->delete($user_message_id)!==false)
		{
			$this->ajaxReturn(null,'删除成功');
		}
		$this->ajaxReturn(null,'删除失败',0);
	}

	/**
	 * 消息附件通知-新 盒子
	 */
	public function message_notice(){
		$uid = I('uid');
		$channel = I('channel');

		if(empty($uid) || empty($channel))
		{
			$this->ajaxReturn(null,'参数不能为空',0);
		}

		$arr = array(
			'uid'=>$uid,
			'channel'=>$channel,
			'sign'=>I('sign')
		);

		$res = checkSign($arr,C('API_KEY'));

		if(!$res)
		{
			$this->ajaxReturn(null,'签名错误',0);
		}

		$player = M('player')->where(array('id'=>$uid))->count();

		if(!$player)
		{
			$this->ajaxReturn(null,'用户不存在',0);
		}

		$map['type'] = 4;
		$map['message_type'] = 4;
		$map['end_time'] = array('egt',time());
		$map['_string'] = "`uids` = '' or find_in_set({$uid},`uids`)";
		$msg = M('message')->where($map)->order('id desc')->find();

		if($msg){
			$map_exist = array('message_id' => $msg['id'], 'type' => 4, 'uid' => $uid);
			$user_msg = M('user_message')->where($map_exist)->find();
			if(empty($user_msg)){
				$map_exist['create_time'] = time();
				$user_msg_id = M('user_message')->add($map_exist);
				if($user_msg_id){
					$user_msg = M('user_message')->where(array('id'=>$user_msg_id))->find();
				}
			}

			$data['title'] = $msg['title'];
			$data['desc'] = $msg['desc'];
			$data['action'] = $msg['action'];
			$data['system'] = $msg['system'];
			$data['api_url'] = $msg['api_url'];
			$data['attach_type'] = $msg['attach_type'];
			$data['attach_count'] = $msg['attach_count'];
			$data['end_time'] = $msg['end_time'];
			$data['user_message_id'] = $user_msg['id'];
			$data['is_read'] = $user_msg['is_read'];
			$data['is_get'] = $user_msg['is_get'];
			$this->ajaxReturn($data);
		}
		$this->ajaxReturn(null,'暂无消息附件',0);
	}

	/**
	 * 阅读消息
	 */
	public function read_msg(){
		$uid = I('uid');
		$channel = I('channel');
		$user_message_id = I('user_message_id');

		if (empty($uid) || empty($channel) || empty($user_message_id)) {
			$this->ajaxReturn(null, '参数错误', 0);
		}

		$arr = array(
			'uid' => $uid,
			'channel' => $channel,
			'user_message_id' => $user_message_id,
			'sign' => I('sign')
		);

		$res = checkSign($arr, C('API_KEY'));

		if (!$res)
		{
			$this->ajaxReturn(null, '签名错误', 0);
		}

		$player_info = M('player')->field('id,coin,platform_money')->where(array('id' => $uid))->find();

		if (!$player_info)
		{
			$this->ajaxReturn(null, '用户不存在', 0);
		}

		$user_message_info = M('user_message')->where(array('id' => $user_message_id))->find();

		if(!$user_message_info)
		{
			$this->ajaxReturn(null, '用户消息不存在', 0);
		}

		$read = M('user_message')->where(array('id' => $user_message_id))->setField('is_read',1);
		if($read !== false){
			$this->ajaxReturn(null, '消息阅读成功');
		}
		$this->ajaxReturn(null, '消息阅读失败', 2);
	}
}