<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class ChangegameController extends AdminbaseController
{
    public function index()
    {
    	//接受Post数据
		$parameter = array();
		$parameter['username'] = I('username');
		$parameter['start_time'] = I('start_time')?I('start_time'):date('Y-m-d',strtotime('-6 days'));
		$parameter['end_time'] = I('end_time')?I('end_time'):date('Y-m-d');
		$parameter['status'] = I('status');
		$map = array();
		
		if(!empty($parameter['username']))
		{
			$map['username'] = array('like',"{$parameter['username']}%");
		}
		
		if(!empty($parameter['start_time']))
		{
			$map['create_time'][] = array('egt',strtotime($parameter['start_time']));
		}
		
		if(!empty($parameter['end_time']))
		{
			$map['create_time'][] = array('lt',strtotime($parameter['end_time'])+3600*24);
		}
		
		if(!empty($parameter['status']))
		{
			$map['status'] = $parameter['status'];
		}
		
		$change_game_log = M('change_game_log');
    			
        $count = $change_game_log->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameter as $key=>$val)
		{
			if(!empty($val))
			$page->parameter[$key] = urlencode($val);
		}
		
         $log_list = $change_game_log
		->where($map)
		->order('create_time desc')
		->limit($page->firstRow . ',' . $page->listRows)
		->select();

		$this->assign('page',$page->show('Admin'));
		$this->assign('list',$log_list);
		$this->assign('parameter',$parameter);
    	$this->display();
    }
    
    public function verify()
    {
    	$action = I('action');
    	$id = I('id');
    	$reason = I('reason');
    	
    	$admin_id = sp_get_current_admin_id();
    	
    	$data = array(
    	'id'=>$id,
    	'status'=>$action,
    	'admin_id'=>$admin_id,
    	'modify_time'=>time(),
    	);
    	
    	if($action == 3)
    	{
    		$data['reason'] = $reason;
    	}
    	
    	$res = M('change_game_log')->save($data);

    	if($res)
    	{
    		if($action == 3)
    		{
    			echo 1;
    			exit;
    		}
    		$this->success('操作成功');
    	}
    	else
    	{
    		if($action == 3)
    		{
    			echo 0;
    			exit;
    		}
    		$this->success('操作失败');
    	}
    	
    }
}