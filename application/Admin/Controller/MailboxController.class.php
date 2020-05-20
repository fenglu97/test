<?php
/**
 * 匿名信箱
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/6/28
 * Time: 13:46
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class MailboxController extends AdminbaseController{

    /**
     * 信箱列表
     */
    public function index(){
        $title = I('title');
        $start = I('start');
        $end = I('end');
        $where = '';
        if($start) $where['create_time'] = array('gt',strtotime($start));
        if($end) $where['create_time'] = array('lt',strtotime($end));
        if($title) $where['title'] = array('like','%'.$title.'%');

        $count = M('mailbox')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('mailbox')->where($where)->limit($page->firstRow,$page->listRows)->select();

        $this->page = $page->show('Admin');
        $this->title = $title;
        $this->start = $start;
        $this->end = $end;
        $this->data = $data;
        $this->display();
    }

    /**
     * 新增邮件
     */
    public function add(){
        if(IS_POST){
            $data = I('post.');
            $data['is_anonymous'] = isset($data['is_anonymous']) ? 1 : 0;
            $data['create_time'] = time();
            unset($data['id']);
            if(M('mailbox')->add($data)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->display();
        }
    }

    /**
     * 删除
     */
    public function del(){
        $id = I('id');
        if(is_array($id)){
            $id = implode(',',$id);
        }
        if(M('mailbox')->where(array('id'=>array('in',$id)))->delete() !== false){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }
}