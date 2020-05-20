<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/5/25
 * Time: 10:39
 */

namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class UrlRedirectController extends AdminbaseController{

    /**
     * 列表
     */
    public function index(){
        $tag = I('tag');
        $where = '';
        if($tag) $where['tag'] = array('like','%'.$tag.'%');
        $count = M('spread')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('spread')->where($where)->order('id desc')->limit($page->firstRow,$page->listRows)->select();
        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->tag = $tag;
        $this->display();
    }

    public function add(){
        if(IS_POST){
            $data = I('');
            if(M('spread')->where(array('tag'=>$data['tag']))->find()){
                $this->error('简写已存在');
            }
            $data['create_time'] = time();
            if($id = M('spread')->add($data)){
                M('route')->add(array('rid'=>$id,'full_url'=>"api/redirect/url?tag={$data['tag']}",'url'=>$data['tag']));
                sp_get_routes(true);
                //通知218更新路由
                file_get_contents('http://game.sy218.com/index.php?g=api&m=Redirect&a=update');
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
            $data = I('');
            if(M('spread')->where(array('tag'=>$data['tag'],'id'=>array('neq',$data['id'])))->find()){
                $this->error('简写已存在');
            }
            if(M('spread')->save($data) !== false){
                M('route')->where(array('rid'=>$data['id']))->setField(array('full_url'=>"api/redirect/url?tag={$data['tag']}",'url'=>$data['tag']));
                sp_get_routes(true);
                //通知218更新路由
                file_get_contents('http://game.sy218.com/index.php?g=api&m=Redirect&a=update');
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $info = M('spread')->where(array('id'=>$id))->find();
            $this->data = $info;
            $this->display('add');
        }
    }

    public function del(){
        $id = I('id');
        if(is_array($id)){
            $id = implode(',',$id);
        }
        if(M('spread')->where(array('id'=>array('in',$id)))->delete()){
            M('route')->where(array('rid'=>array('in',$id)))->delete();
            sp_get_routes(true);
            //通知218更新路由
            file_get_contents('http://game.sy218.com/index.php?g=api&m=Redirect&a=update');
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    public function clean(){
        $id = I('id');
        if(M('spread')->where(array('id'=>$id))->setField(array('ios_click'=>0,'android_click'=>0)) !== false){
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }
}