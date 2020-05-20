<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/11/24
 * Time: 11:21
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class AppstartController extends AdminbaseController{

    public function index(){
        $key = I("keywords");

        $where = '';
        if($key) $where['channel'] = $key;
        if($_SESSION['channel_role'] != 'all'){
            $where['channel'] = array('in',$_SESSION['channel_role']);
        }
//        $info = M('appstart')->where($where)->find();
        $count = M('appstart')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('appstart')->where($where)->limit($page->firstRow,$page->listRows)->select();
        $this->page = $page->show('Admin');
        $this->data = $data;
//        $this->info =  $info ? true : false;
        $this->keywords = $key;
        $this->display();
    }

    public function add(){
        if(IS_POST){
            $data = I('post.');
            if(M('appstart')->where(array('channel'=>$data['channel']))->find()){
                $this->error('该渠道已上传启动页');
            }
            $data['create_time'] = time();
            if(M('appstart')->add($data)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $cid = $_SESSION['channel_role'] == 'all' ? 0 : $_SESSION['channel_role'];
            $this->cid = $cid;
            $this->display();
        }
    }


    public function edit(){
        if(IS_POST){
            $data = I('post.');
            if(!$data['id']) $this->error('缺少参数');
            if(M('appstart')->where(array('id'=>$data['id']))->setField($data) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $data = M('appstart')->where(array('id'=>$id))->find();
            $this->cid = $_SESSION['channel_role'] == 'all' ? 0 : $_SESSION['channel_role'];
            $this->ios_url = C('FTP_URL').((strpos($data['ios_img'],'/assets/pic')!==false)?'':'/assets/pic/').$data['ios_img'];
            $this->android_url = C('FTP_URL').((strpos($data['android_img'],'/assets/pic')!==false)?'':'/assets/pic/').$data['android_img'];

            $this->data = $data;
            $this->display('add');
        }
    }



    public function del(){
        $id = I('id');
        if(M("appstart")->delete($id)){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }
}