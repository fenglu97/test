<?php
/**
 * 自助返利
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/2
 * Time: 11:08
 */

namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class SelfRebateController extends AdminbaseController{

    /**
     * 配置列表
     */
    public function index(){
        $appid = I("appid");

        $map['status'] = 1;
        if($appid) $map['appid'] = $appid;
        $count = M('rebate_option')->where($map)->count();
        $page = $this->page($count, 20);
        $list = M('rebate_option')->where($map)->limit($page->firstRow,$page->listRows)->order('create_time desc')->select();

        $this->list = $list;
        $this->games = get_game_list($appid,1,'all');
        $this->game = M('game')->where(array('status'=>1))->getField('id,game_name',true);
        $this->page = $page->show('Admin');
        $this->display();
    }

    /**
     * 添加配置
     */
    public function add(){
        if(IS_POST){
            $data = I('');
            $data['start'] = strtotime($data['start']);
            $data['end'] = strtotime($data['end'] . '23:59:59');
            if(!$data['appid']) $this->error('请选择游戏');

            if(count($data['pid']) > count(array_unique($data['pid']))) {
                $this->error('配置ID不能相同');
            }
            if($data['start'] > $data['end']){
                $this->error('结束时间不能小于开始时间');
            }
            foreach($data['pid'] as $v){
                if(empty($v)) $this->error('配置ID不能为空');
            }
            foreach($data['percent'] as $v){
                if(empty($v)) $this->error('返利百分比不能为空');
            }
            foreach($data['need_money'] as $v){
                if(empty($v)) $this->error('满足返利充值金额不能为空');
                $money = explode('-',$v);
                if(count($money) <= 1) $this->error('请设置返利金额的区间');
                if($money[0] >= $money[1]) $this->error('请正确设置返利金额');
            }
            foreach($data['pid'] as $k=>$v){
                $option[] = array(
                    'pid' => $v,
                    'percent' => $data['percent'][$k],
                    'need_money' => $data['need_money'][$k]
                );
            }
            $map = array(
                'appid' => implode(',',$data['appid']),
                'name' => $data['name'],
                'start' => $data['start'],
                'end' => $data['end'],
                'option' => json_encode($option),
                'create_time' => time()
            );
            if($id = M('rebate_option')->add($map)){
//                M('game')->where(array('id'=>array('in',implode(',',$data['appid']))))->save(array('rebate_option'=>$id));
                $this->success('操作成功',U('SelfRebate/index'));
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->games = get_game_list('',1,'all','all','all');
            $this->display();
        }
    }


    /**
     * 修改配置
     */
    public function edit(){
        if(IS_POST){
            $data = I('');
            $data['start'] = strtotime($data['start']);
            $data['end'] = strtotime($data['end'] . '23:59:59');
            if(!$data['appid']) $this->error('请选择游戏1');

            if(count($data['pid']) > count(array_unique($data['pid']))) {
                $this->error('配置ID不能相同');
            }
            if($data['start'] > $data['end']){
                $this->error('结束时间不能小于开始时间');
            }
            foreach($data['pid'] as $v){
                if(empty($v)) $this->error('配置ID不能为空');
            }
            foreach($data['percent'] as $v){
                if(empty($v)) $this->error('返利百分比不能为空');
            }
            foreach($data['need_money'] as $v){
                if(empty($v)) $this->error('满足返利充值金额不能为空');
                $money = explode('-',$v);
                if(count($money) <= 1) $this->error('请设置返利金额的区间');
                if($money[0] >= $money[1]) $this->error('请正确设置返利金额');
            }

            foreach($data['pid'] as $k=>$v){
                $option[] = array(
                    'pid' => $v,
                    'percent' => $data['percent'][$k],
                    'need_money' => $data['need_money'][$k]
                );
            }
            $map = array(
                'id' => $data['id'],
                'appid' => implode(',',$data['appid']),
                'name' => $data['name'],
                'start' => $data['start'],
                'end' => $data['end'],
                'option' => json_encode($option),
                'edit_time' => time()
            );
            if(M('rebate_option')->save($map) !== false){
//                $games = M('game')->where(array('rebate_option'=>$data['id']))->getField('id',true);
//                M('game')->where(array('id'=>array('in',implode(",",$data['appid']))))->setField('rebate_option',$data['id']);
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $data = M('rebate_option')->where(array('id'=>$id))->find();
            $option = json_decode($data['option'],true);
//            $appids = M('game')->where(array('rebate_option'=>$id))->getField('id',true);

            $this->id = $id;
            $this->data = $data;
            $this->option = $option;
            $this->games = get_game_list(explode(',',$data['appid']),1,'all','all','all');
            $this->display();
        }
    }


    /**
     * 删除配置
     */
    public function del(){
        $id = I('id');
        if(M('rebate_option')->where(array('id'=>$id))->delete()){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 用户申请返利
     */
    public function applyList(){
        $status = I('status',-1);
        $username = I('username');
        $appid = I('appid',-1);
        if($username) $map['p.username'] = array('like','%'.$username.'%');
        if($appid != -1) $map['s.appid'] = $appid;
        if($status != -1) $map['s.status'] = $status;

        $count = M('self_rebate s')
                ->field('s.*,p.username,g.game_name')
                ->join('left join __PLAYER__ p on p.id=s.uid')
                ->join('left join __GAME__ g on g.id=s.appid')
                ->where($map)->count();
        $page = $this->page($count, 20);

        $data = M('self_rebate s')
                ->field('s.*,p.username,g.game_name')
                ->join('left join __PLAYER__ p on p.id=s.uid')
                ->join('left join __GAME__ g on g.id=s.appid')
                ->where($map)->order('s.create_time desc')->limit($page->firstRow, $page->listRows)->select();

        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->appid = $appid;
        $this->username = $username;
        $this->status = $status;
        $this->display();
    }

    /**
     * 处理返利
     */
    public function dothis(){
        $id = I('id');
        $this->data = M('self_rebate s')
                    ->field('s.*,p.username')
                    ->join('left join __PLAYER__ p on p.id=s.uid')
                    ->where(array('s.id'=>$id))->find();
        $this->type = 2;
        $this->display('Rebate/add');
    }

    /*
     * 标记状态
     */
    public function changeStatus(){
        $id = I('id');
        $state = I('state');

        if(M('self_rebate')->where(array('id'=>$id))->setField('status',$state) !== false){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }
}