<?php
/**
 * 金币管理后台控制器
 * @author qing.li
 * @date 2017-11-01
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class CoinController extends AdminbaseController
{
 	public function log()
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
 		
 		
 		$coin_log = M('coin_log');
 		
 		$count = $coin_log->where($map)->count();

 		$page = $this->page($count, 20);

 		foreach($parameter as $key=>$val)
 		{
 			if(!empty($val))
 			$page->parameter[$key] = urlencode($val);
 		}
 		
 		$log_list = $coin_log
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