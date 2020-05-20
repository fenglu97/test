<?php
/**
 * 跑马灯公告
 * @author qing.li
 * @date 2018-08-02
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class LoopNoticeController extends AdminbaseController
{
    public function index()
    {
        $start = I('start','');
        $end = I('end','');
        $game_role = session('game_role');
        $channel_role = session('channel_role');
        if($game_role != 'all') $where['appid'] = array('in',$game_role);
        if($channel_role != 'all') $where['channel'] = array('in',$channel_role.',0');
        if($start) $where['create_time'] = array('gt',strtotime($start.' 00:00:00'));
        if($end) $where['create_time'] = array('lt',strtotime($end.' 23:59:59'));
        $where['status'] = 1;

        $count = M('loop_notice')->where($where)->count();
        $page = $this->page($count, 20);
        $list = M('loop_notice')->where($where)->order('create_time desc')->limit($page->firstRow, $page->listRows)->select();


        $this->users = M('users')->where(array('user_type'=>1))->getField('id,user_login');

        $this->page = $page->show('Admin');
        $this->start = $start;
        $this->end = $end;
        $this->list = $list;
        $this->display();
    }

    public function add()
    {
        if(IS_POST)
        {
            $data['title'] = I('title');
            $data['content'] = I('content');
            $data['appid'] = I('appid');
            $data['channel'] = I('channel');
            $data['loop_times'] = I('loop_times');
            $data['loop_interval'] = I('loop_interval');
            $data['create_time'] = time();
            $data['admin_id'] = SESSION('ADMIN_ID');

            if(M('loop_notice')->add($data)!==false)
            {
                $this->success('添加成功');
            }
            else
            {
                $this->success('添加失败');
            }

        }
        $this->display();
    }

    public function edit()
    {
        if(IS_POST)
        {
            $data['id'] = I('id');
            $data['title'] = I('title');
            $data['content'] = I('content');
            $data['appid'] = I('appid');
            $data['channel'] = I('channel');
            $data['loop_times'] = I('loop_times');
            $data['loop_interval'] = I('loop_interval');

            if(M('loop_notice')->save($data)!==false)
            {
                $this->success('修改成功');
            }
            else
            {
                $this->success('修改失败');
            }

        }
        $info = M('loop_notice')->where(array('id'=>I('id')))->find();
        $this->assign('info',$info);
        $this->display();
    }

    public function del()
    {
        $id = I('id');
        if(!$id) $this->error('请选择数据');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        $where['id'] = array('in',$id);
        if(M('loop_notice')->where($where)->setField(array('status'=>0))){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

}