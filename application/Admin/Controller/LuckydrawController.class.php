<?php
/**
 * 抽奖
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/11/13
 * Time: 14:17
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class LuckydrawController extends AdminbaseController{

    /**
     * 抽奖配置
     */
    public function setting(){
        $data = M('luckydraw_setting')->select();

        foreach($data as $k1=>$v){
            $info = '';
            $setting = json_decode($v['setting'],true);
            foreach($setting as $k=>$s){
                $name = M('prize')->where(array('place'=>$k))->getField('name');
                $info .= $name.':概率'.$s.'%，';
            }
            $data[$k1]['setting'] = trim($info,'，');
        }
        $this->data = $data;
        $this->display();
    }

    /**
     * 添加配置
     */
    public function addsetting(){
        if(IS_POST){
            $place = I("place");
            $chance = I('chance');
            $money = I('money');
            $count = 0;
            foreach($place as $k=>$v){
                $count += intval($chance[$v]);
                if($chance[$v]){
                    $setting[$v] = $chance[$v];
                }
            }
            if($count != 100) $this->error('概率总和必须等于100');
            $add['money'] = $money;
            $add['setting'] = json_encode($setting);
            $add['create_time'] = time();
            if(M('luckydraw_setting')->add($add)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $list = M('prize')->order('place')->select();
            $this->list = $list;
            $this->display();
        }

    }

    /**
     * 修改配置
     */
    public function editsetting(){
        if(IS_POST){
//            dump(I());die;
            $place = I("place");
            $chance = I('chance');
            $money = I('money');
            $count = 0;
            foreach($place as $k=>$v){
                $count += intval($chance[$v]);
                if($chance[$v]){
                    $setting[$v] = $chance[$v];
                }
            }
            if($count != 100) $this->error('概率总和必须等于100');
            $add['money'] = $money;
            $add['id'] = I('id');
            $add['setting'] = json_encode($setting);
            if(M('luckydraw_setting')->save($add) !== false){
                $this->success('操作成功');
            }else{
                echo M()->_sql();
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $data = M('luckydraw_setting')->where(array('id'=>$id))->find();
            $data['setting'] = json_decode($data['setting'],true);
            $list = M('prize')->order('place')->select();
            $this->list = $list;
            $this->data = $data;
            $this->id = $id;
            $this->display('addsetting');
        }
    }

    /**
     * 删除配置
     */
    public function delsetting(){
        $id = I('id');
        if(M('luckydraw_setting')->delete($id)){
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 奖品列表
     */
    public function prize(){
        $data = M('prize')->order('place')->select();
        $this->data = $data;
        $this->display();
    }

    /**
     * 添加奖品
     */
    public function addprize(){
        if(IS_POST){
            $data = I('post.');
            $data['create_time'] = time();
            unset($data['id'],$data['upload']);
            if(M('prize')->add($data)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            if(M('prize')->count() >= 12) {
                $this->error('奖品不能超过12个');
            }
            $this->display();
        }
    }

    /**
     * 上传奖品
     */
    public function upprize(){
        $start = time();
        $upload = new \Think\Upload(array(
            'maxSize' => 3145728,
            'rootPath' => './data/upload/prize/'
        ),'local');
        $info = $upload->uploadOne($_FILES['upload']);
        if(is_array($info)){
            $this->ajaxReturn($info);
        }
    }

    /**
     * 编辑奖品
     */
    public function editprize(){
        if(IS_POST){
            $data = I('post.');
            unset($data['upload']);
            if(M('prize')->save($data) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $data = M('prize')->where(array('id'=>$id))->find();
            $this->data = $data;
            $this->id = $id;
            $this->display('addprize');
        }
    }

    /**
     * 删除奖品
     */
    public function delprize(){
        $id = I('id');
        if(M('prize')->where(array('id'=>$id))->delete()){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 检测奖品位置是否重复
     */
    public function checkIndex(){
        $place = I('place');
        $id = I('id');
        if($id){
            $where['id'] = array('neq',$id);
        }
        $where['place'] = $place;
        if(M('prize')->where($where)->find()){
            echo 'false';
        }else{
            echo 'true';
        }
    }

    /**
     * 检测限制金额是否重复
     */
    public function checkMoney(){
        $money = I('money');
        $id = I('id');
        if($id){
            $where['id'] = array('neq',$id);
        }
        $where['money'] = $money;
        if(M('luckydraw_setting')->where($where)->find()){
            echo 'false';
        }else{
            echo 'true';
        }
    }

    /**
     * 中奖明细
     */
    public function listOfWinners(){
        $username = I('keywords');
        $type = I('type');
        $status = I('status');
        $where = '';
        if($username) $where['p.username'] = array('like','%'.$username.'%');
        if($type) $where['pr.type'] = $type;
        if($status) $where['l.status'] = $status;
        $count = M('luckydraw_list l')
                ->where($where)
                ->join('left join __PLAYER__ p on p.id=l.uid')
                ->join('left join __PRIZE__ pr on pr.id=l.prizeid')
                ->count();

        $page = $page = $this->page($count, 15);
        $data = M('luckydraw_list l')
                ->field('l.*,p.username,p.mobile,pr.name')
                ->join('left join __PLAYER__ p on p.id=l.uid')
                ->join('left join __PRIZE__ pr on pr.id=l.prizeid')
                ->where($where)
                ->order('l.create_time desc')
                ->limit($page->firstRow, $page->listRows)
                ->select();

        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->status = $status;
        $this->type = $type;
        $this->keywords = $username;
        $this->display();
    }

    /**
     * 标记
     */
    public function changeStatus(){
        $id = I('id');
        if(M('luckydraw_list')->where(array('id'=>$id))->setField('status',1) !== false){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }
}