<?php
/**
 * 游戏攻略
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/6/28
 * Time: 17:55
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class StrategyController extends AdminbaseController{

    /**
     * 列表
     */
    public function index(){
        $name = I('name');
        $sort = I('sort');
        $where = '';
        if($name) $where['name'] = array('like','%'.$name.'%');

        $count = M('strategy')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('strategy')->where($where)->limit($page->firstRow,$page->listRows)->order("sort {$sort},create_time desc")->select();

        $this->page = $page->show('Admin');
        $this->name = $name;
        $this->data = $data;
        $this->sort = $sort;
        $this->display();
    }

    /**
     * 新增
     */
    public function add(){
        if(IS_POST){
            $data = I('post.');
            $data['create_time'] = time();
            if(M('Strategy')->add($data)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->display();
        }
    }

    /**
     * 编辑
     */
    public function edit(){
        if(IS_POST){
            $data = I('post.');
            if(M('Strategy')->save($data) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $data = M('strategy')->where(array('id'=>$id))->find();
            $this->data = $data;
            $this->display('add');
        }
    }

    /**
     * 查看
     */
    public function view(){
        $id = I('id');
        $data = M('strategy')->where(array('id'=>$id))->find();
        $data['content'] = html_entity_decode($data['content']);
        $this->data = $data;
        $this->display();
    }

    /**
     * 删除
     */
    public function del(){
        $id = I('id');
        if(is_array($id)){
            $id = implode(',',$id);
        }
        if(M('strategy')->where(array('id'=>array('in',$id)))->delete() !== false){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 排序
     */
    public function listorders(){
        $status = parent::_listorders(M('strategy'),'sort');
        if ($status) {
            $this->success("排序更新成功！");
        } else {
            $this->error("排序更新失败！");
        }
    }
}