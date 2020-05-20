<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class StudymaterialController extends AdminbaseController
{

	public function _initialize()
	{
		parent::_initialize();
	}

	public function index()
	{
		$type = I('type')?I('type'):1;
		
		$map = array('status'=>1);
		$map['type'] = $type;
		
		$count = M('study_material')->where(array($map))->count();
		$page = $this->page($count, 20);
		
		$list = M('study_material')
		->where(array($map))
		->order('create_time desc')
		->limit($page->firstRow . ',' . $page->listRows)
		->select();

		$this->assign('type',$type);
		$this->assign('list',$list);
		$this->assign('page',$page->show('Admin'));
		$this->display();
	}

	public function add()
	{
		if(IS_POST)
		{

		    $_POST['file_size'] = round($_POST['file_size'],2);
			$_POST['create_time'] = time();
	
			if(M('study_material')->add($_POST)!==false)
			{
				$this->success('添加成功',U('Studymaterial/index'));
			}
			else 
			{
				$this->error("添加失败！");
			}
		
		}
		else 
		{
			$this->display();
		}
	}
	
	public function edit()
	{
		if(IS_POST)
		{
			 $_POST['file_size'] = round($_POST['file_size'],2);
			 $_POST['modify_time'] = time();
			 if(M('study_material')->save($_POST)!==false)
			 {
			 	$this->success('修改成功',U('Studymaterial/index'));
			 }
			 else
			 {
			 	$this->error("修改失败！");
			 }
		}
		else 
		{
			$id = I('id');
			$data = M('study_material')->where(array('id'=>$id))->find();
			$this->assign('data',$data);
			$this->display();
		}
	}

	public function del()
	{
		$id = I('id');
		
		if(M('study_material')->where(array('id'=>$id))->save(array('status'=>0))!==false)
		{
			$this->success('删除成功');
		}
		else 
		{
			$this->error('删除失败');
		}
		
		$this->display();
	}

	public function upload()
	{
		set_time_limit(0);
		$start = time();
		$upload = new \Think\Upload();

		$upload->autoSub  = false;
		$upload->rootPath = "www.sy217.com/assets/";
		$upload->savePath = "study_material/";
		

        $fileName=$_FILES["upload"]["name"];

        $fileName=explode('.',$fileName);
        $serverFileName=$fileName[0]."_".time();  
          
        $upload->saveName=$serverFileName;//设置在服务器保存的文件名  
		
		$info = $upload->uploadOne($_FILES['upload']);
	
		if(is_array($info)){
			$info['root'] = C('FTP_URL');
			$info['fullpath'] = str_replace('www.sy217.com','',$info['fullpath']);
			$this->ajaxReturn($info);
		}
	}



}