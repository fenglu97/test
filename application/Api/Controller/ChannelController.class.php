<?php
/**
 * 渠道接口
 * @author qing.li
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class ChannelController extends AppframeController
{

	public function get_channel_list()
	{
		$role_id = session('ROLE_ID');
		if($role_id == 5 || $role_id == 14)
		{
			exit;
		}

		$type = I('type');
		import('PinYin');
		$py = new \PinYin();
		$channel_role = session('channel_role');
		$map = array();

		if($type!= '')
		{
			$map['type'] = $type;
		}

		if($channel_role == 'all')
		{
			$channels = M('channel')->field('id,name')->where(array_merge(array('status'=>1),$map))
				->select();
		}
		else
		{
			$channels = M('channel')->field('id,name')->where(array_merge(array('status'=>1,'id'=>array('in',$channel_role)),$map))->select();
		}


		$html_str = '';
		foreach($channels as $v)
		{
			$v['name'] = trim($v['name']);
			$html_str.="<option value='{$v['id']}' >{$v['name']}-{$v['id']}</option>";
		}

		exit($html_str);
	}

	public function create_admin()
	{
		set_time_limit(0);
		$userrights_model =  M('userrights');
		$channel_model = M('channel');
        $channels = $channel_model
			->field('id,type,parent')
			->where(array('status'=>1))
			->select();


		foreach($channels as $channel)
		{
			$role_id = ($channel['type'] == 2)?15:4;

			$user_map = array('b.role_id'=>$role_id);
			if($channel['parent']!=0)
			{
				$user_map['a.channel_role'] = $channel['id'];
			}
			else
			{
				$user_map['_string']='FIND_IN_SET("'.$channel['id'].'", a.channel_role)';
			}


			//查询该渠道的管理员
			$userid = $userrights_model->
			alias('a')->
			join('__ROLE_USER__ as b on a.userid = b.user_id')->
			field('a.userid')->
			where($user_map)
				->getfield('a.userid');

			if($userid)
			{
				$channel_model->where(array('id'=>$channel['id']))->save(array('admin_id'=>$userid));
			}

		}

		exit('success');
	}
}