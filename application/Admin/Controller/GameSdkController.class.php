<?php
/**
 * SDK平台后台控制器
 * @author qing.li
 * @date 2019-04-30
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class GameSdkController extends AdminbaseController
{
    public function index()
    {
        $name = I('name');
        $map = array();
        if($name) $map['name'] = array('like',"%{$name}%");
        $count = M('game_sdk')->where($map)->count();

        $page = $this->page($count, 20);

        $list = M('game_sdk')->
        where($map)->
        order(array("id" =>"asc"))->
        limit($page->firstRow . ',' . $page->listRows)->
        select();

        $this->name = $name;
        $this->page = $page->show('Admin');
        $this->list = $list;
        $this->display();
    }

    public function add()
    {
        if(IS_POST)
        {

            $data = I('post.');
            $data['create_time'] = time();

            if(M('game_sdk')->add($data)!==false)
            {
                $this->success('添加成功');
            }
            else
            {
                $this->error('添加失败');
            }

        }
        $this->display();
    }

    public function edit()
    {
        if(IS_POST)
        {
            $data = I('post.');
            $id = I('post.id');

            if(M('game_sdk')->where(array('id'=>$id))->save($data)!==false)
            {
                $this->success('修改成功');
            }
            else
            {
                $this->error('修改失败');
            }

        }
        $id = I('id');
        $info = M('game_sdk')->where(array('id'=>$id))->find();

        $this->info = $info;
        $this->display();
    }

}