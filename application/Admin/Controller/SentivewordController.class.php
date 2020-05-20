<?php
/**
 * 敏感词库
 * @date 2018-03-12
 */

namespace Admin\Controller;
use Common\Controller\AdminbaseController;
use PDO;

class SentivewordController extends AdminbaseController
{
	function _initialize()
	{
		parent::_initialize();
		$this->type_name = array(
		'1'=>'暴恐',
		'2'=>'反动',
		'3'=>'民生',
		'4'=>'色情',
		'5'=>'贪腐',
		'6'=>'其他',
		);

	}

	public function index()
	{
		$name = I('name');
		$type = I('type');
		$map = array();
		$parameter = array();

		if(!empty($name))
		{
			$map['name'] = array('like',$name.'%');
			$parameter['name'] = $name;
		}

		if($type !=0)
		{
			$map['type'] = $type;
			$parameter['type'] = $type;
		}

		$count = M('sentiveword')->where($map)->count();

		$page = $this->page($count, 20);

		foreach($parameter as $k=>$v)
		{
			$page->parameter[$k] = urlencode($v);
		}


		$list =  M('sentiveword')->
		where($map)->
		limit($page->firstRow . ',' . $page->listRows)->
		order('id desc')->
		select();


		$this->assign('page',$page->show('Admin'));
		$this->assign('parameter',$parameter);
		$this->assign('list',$list);
		$this->display();
	}

	public function add()
	{
		if(IS_POST)
		{
			if(M('sentiveword')->add($_POST)!==false)
			{
				if($this->_curl_create_tree())
				{
					$this->success('添加成功');
					exit;
				}
			}

			$this->error('添加失败');
			exit;
		}
		$this->display();
	}

	public function del()
	{
		$ids = I('ids');
		$ids = implode(',',$ids);

		if(M('sentiveword')->where(array('id'=>array('in',$ids)))->delete()!==false)
		{
			if($this->_curl_create_tree())
			{
				$this->success('删除成功');
				exit;
			}
		}
		$this->error('删除失败');
		exit;
	}

	private function _curl_create_tree()
	{
	    $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_TIMEOUT, 500);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
        curl_setopt($curl,CURLOPT_REFERER,'{:C("API_URL")}');
        curl_setopt($curl,CURLOPT_URL,'{:C("API_URL")}/index.php?g=api&m=sentiveword&a=create_tree');
        $res = json_decode(curl_exec($curl),true);
        curl_close($curl);
        
        if($res['status'] == 1)
        {
        	return true;
        }
        
        return false;
        
	}
}