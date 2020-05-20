<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/11/29
 * Time: 15:03
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class AppNoticeController extends AdminbaseController{

    public function index(){
        $title = I('title');
        $start = I('start','');
        $end = I('end','');
        $channel_role = session('channel_role');
        if($channel_role != 'all' && $channel_role != 'empty') $where['channel'] = array('in',$channel_role);
        if($start) $where['add_time'] = array('gt',strtotime($start));
        if($end) $where['add_time'] = array('lt',strtotime($end));
        if($title) $where['title'] = array('like','%'.$title.'%');

        $list = M('appnotice')->where($where)->order('id desc')->select();
        $count = count($list);
        $page = $this->page($count, 20);
        $list = array_slice($list,$page->firstRow, $page->listRows);

        $this->users = M('users')->where(array('user_type'=>1))->getField('id,user_login');

        $this->page = $page->show('Admin');
        $this->start = $start;
        $this->end = $end;
        $this->list = $list;
        $this->title = $title;
        $this->display();
    }

    public function add(){
        if(IS_POST){
            $data = I('post.');
            $data['create_time'] = strtotime($data['create_time']);
            if(M('appnotice')->add($data)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->display();
        }
    }

    public function edit(){
        if(IS_POST){
            $data = I('post.');
            $data['create_time'] = strtotime($data['create_time']);

            if(M('appnotice')->save($data) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $this->id = $id;
            $this->data = M('appnotice')->where(array('id'=>$id))->find();
            $this->display('add');
        }
    }

    public function del(){
        $id = I('id');
        if(!$id) $this->error('请选择数据');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        $where['id'] = array('in',$id);
        if(M("appnotice")->where($where)->delete()){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }
}