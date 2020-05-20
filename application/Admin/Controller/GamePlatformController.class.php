<?php
/**
 * 游戏平台后台控制器
 * @author qing.li
 * @date 2018-10-18
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class GamePlatformController extends AdminbaseController
{
    public function index()
    {
        $name = I('name');
        $map = array();
        if($name) $map['name'] = array('like',"%{$name}%");
        $count = M('game_platform')->where($map)->count();

        $page = $this->page($count, 20);

        $list = M('game_platform')->
        where($map)->
        order(array("id" =>"asc"))->
        limit($page->firstRow . ',' . $page->listRows)->
        select();

        foreach($list as $k=>$v)
        {
            $list[$k]['files'] = json_decode($v['files'],true);
        }

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
            foreach($data['file_url'] as $k=>$v)
            {
                $data['files'][$k]['file_url'] = $data['file_url'][$k];
                $data['files'][$k]['file_name'] = $data['file_name'][$k];
            }

            $data['files'] = json_encode($data['files']);


            if(M('game_platform')->add($data)!==false)
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
            foreach($data['file_url'] as $k=>$v)
            {
                $data['files'][$k]['file_url'] = $data['file_url'][$k];
                $data['files'][$k]['file_name'] = $data['file_name'][$k];
            }

            $data['files'] = json_encode($data['files']);
            if(M('game_platform')->where(array('id'=>$id))->save($data)!==false)
            {
                $this->success('修改成功');
            }
            else
            {
                $this->error('修改失败');
            }

        }
        $id = I('id');
        $info = M('game_platform')->where(array('id'=>$id))->find();
        $info['files'] = json_decode($info['files'],true);

        $this->info = $info;
        $this->display();
    }

    public function file_list()
    {
        $id = I('id');
        $info = M('game_platform')->where(array('id'=>$id))->field('name,files')->find();
        $info['files'] = json_decode($info['files'],true);
        $this->info = $info;
        $this->display();
    }


}