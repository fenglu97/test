<?php
/**
 * 灌水评论模板
 * @date 2018-03-15
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class CommentTemplateController extends AdminbaseController
{
	public function index()
	{
		$count = M('comment_template')->count();

		$page = $this->page($count, 20);

		$list =  M('comment_template')->
		limit($page->firstRow . ',' . $page->listRows)->
		order('id desc,create_time desc')->
		select();

		$this->assign('list',$list);
		$this->assign('page',$page->show('Admin'));
		$this->display();
	}

	public function add()
	{
		if(IS_POST)
		{
             $_POST['create_time'] = time();
             $_POST['admin_username'] = session('name');
             $_POST['content'] = trim($_POST['content']);
             if(M('comment_template')->add($_POST)!==false)
             {
             	 $this->success('添加成功',U('index'));
             	 exit;
             }
             $this->error('添加失败');
             exit;
		}
		$this->display();
	}

	public function edit()
	{
		if(IS_POST)
		{
             $_POST['admin_username'] = session('name');
             $_POST['content'] = trim($_POST['content']);
             if(M('comment_template')->save($_POST)!==false)
             {
             	 $this->success('修改成功',U('index'));
             	 exit;
             }
             $this->error('修改失败');
             exit;        
		}
	    $id = I('id');
		$info = M('comment_template')->where(array('id'=>$id))->find();
		$this->assign('info',$info);
		$this->display();
	}

	public function del()
	{
        $ids = I('ids');
        if(M('comment_template')->where(array('id'=>array('in',implode(',',$ids))))->delete()!==false)
        {
        	$this->success('删除成功');
        }
        else 
        {
        	$this->error('删除失败');
        }
	}
}