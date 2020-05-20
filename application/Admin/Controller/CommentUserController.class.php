<?php
/**
 * 灌水用户
 * @date 2018-03-15
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class CommentUserController extends AdminbaseController
{
	public function index()
	{
		$paramter['username'] = I('username');
		$order = '';

		if(!empty($paramter['username']))
		{
			$map['username'] = array('like',$paramter['username'].'%');
			$order = 'length(username) asc,';
		}

		$count = M('comment_user')->where($map)->count();

		$page = $this->page($count, 20);

		foreach($paramter as $k=>$v)
		{
			if(!empty($v))
			{
				$page->parameter[$k] = urlencode($v);
			}
		}

		$list =  M('Comment_user')->
		where($map)->
		limit($page->firstRow . ',' . $page->listRows)->
		order($order.'id desc')->
		select();



		$this->assign('list',$list);
		$this->assign('paramter',$paramter);
		$this->assign('page',$page->show('Admin'));
		$this->display();
	}

	public function add()
	{
		if(IS_POST)
		{
             $is_exists_player = M('player')->where(array('username'=>$_POST['username']))->count();
             $is_exists_commentuser = M('comment_user')->where(array('username'=>$_POST['username']))->count();
             if($is_exists_player>0 || $is_exists_commentuser>0)
             {
             	$this->error('用户名已存在');
             }
             $_POST['icon_url'] = $_POST['icon_url']?'http://www.sy218.com/assets/pic/'.$_POST['icon_url']:'';
             $_POST['create_time'] = time();
             if(M('comment_user')->add($_POST)!==false)
             {
             	 $this->success('添加成功',U('CommentUser/index'));
             	 exit;
             }
             $this->error('添加失败');
             exit;
		}
		$this->display();
	}

	public function edit()
	{
		$id = I('id');
		if(IS_POST)
		{
              $is_exists_player = M('player')->where(array('username'=>$_POST['username']))->count();
             $is_exists_commentuser = M('comment_user')->where(array('username'=>$_POST['username'],'id'=>array('neq',$id)))->count();
             if($is_exists_player>0 || $is_exists_commentuser>0)
             {
             	$this->error('用户名已存在');
             }
             $_POST['icon_url'] = $_POST['icon_url']?'http://www.sy218.com/assets/pic/'.$_POST['icon_url']:'';
             if(M('comment_user')->save($_POST)!==false)
             {
             	 $this->success('修改成功',U('CommentUser/index'));
             	 exit;
             }
             $this->error('添加失败');
             exit;          
		}
		$info = M('comment_user')->where(array('id'=>$id))->find();
		$this->assign('info',$info);
		$this->display();
	}

	public function del()
	{
        $ids = I('ids');
        if(M('comment_user')->where(array('id'=>array('in',implode(',',$ids))))->delete()!==false)
        {
        	$this->success('删除成功');
        }
        else 
        {
        	$this->error('删除失败');
        }
	}
}