<?php
/**
 * 管理员操作日志
 */

namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class AdminOperatelogController extends AdminbaseController
{
	public function _initialize()
	{
		parent::_initialize();
		$this->menu_model = M('menu');
	}
	
	public function index()
	{
		$menu = I('menu');
		$admin_username = I('admin_username');
		$params = I('params');
		$start_time = I('start_time')?I('start_time'):date('Y-m-d');
		$end_time = I('end_time')?I('end_time'):date('Y-m-d');
		$map = array();
		$parameters = array();

		if(!empty($menu))
		{
			$parameters['menu'] = $menu;
			$menu = explode('|',$menu);
			$map['module_name'] = $menu[0];
			$map['controller_name'] = $menu[1];
			$map['action_name'] = $menu[2];
		}

		if(!empty($admin_username))
		{
			$map['admin_username'] = array('like',$admin_username.'%');
			$parameters['admin_username'] = $admin_username;
		}

		if(!empty($params))
		{
			$map['params'] = array('like','%'.$params.'%');
			$parameters['params'] = $params;
		}

		if(!empty($start_time))
		{
			$map['create_time'][] = array('egt',strtotime($start_time));
			$parameters['start_time'] = $start_time;
		}

		if(!empty($end_time))
		{
			$map['create_time'][] = array('lt',strtotime($end_time)+3600*24);
			$parameters['end_time'] = $end_time;
		}


		$count = M('admin_operatelog')->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameters as $k=>$v)
		{
			$page->parameter[$k] = urlencode($v);
		}


		$list =  M('admin_operatelog')->
		where($map)->
		limit($page->firstRow . ',' . $page->listRows)->
		order('create_time desc')->
		select();
		


		if(is_array($list))
		{
			foreach($list as $k=>$v)
			{
				$list[$k]['ip'] = long2ip($v['ip']);
				$list[$k]['menu'] = $this->menu_model->where(array('app'=>$v['module_name'],'model'=>$v['controller_name'],'action'=>$v['action_name']))->getfield('name');
			
				$list[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
			}
			
		}
		


		$this->assign('menu_lists',$this->_get_all_menus());	
		$this->assign('page',$page->show('Admin'));
		$this->assign('parameters',$parameters);
		$this->assign('list',$list);
		$this->display();
	}
	
	/**
	 * 获取所有菜单
	 */
    private function _get_all_menus($parent = 0,$i=0)
	{
		global $result;
		$list = $this->menu_model
		->field('id,app,model,action,name')
		->where(array('parentid'=>$parent))
		->select();
		
		if(is_array($list))
		{
			foreach($list as $v)
			{
				$v['level'] = $i;
				$result[]=$v;
				$this->_get_all_menus($v['id'],$i+1);
			}
		}
		return $result;
	}
}
