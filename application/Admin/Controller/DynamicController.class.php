<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/2/6
 * Time: 9:40
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class DynamicController extends AdminbaseController{

    public function index(){
        if(I('name')){
            $map['name'] = I('name');
            $where['p.username'] = I('name');
        }
        if(I('audit')){
            $map['audit'] = I('audit');
            $where['d.audit'] = I('audit');
        }
        if(I("start")){
            $map['start'] = I('start');
            $where['d.create_time'][] = array('egt',strtotime(I('start')));
        }
        if(I("end")){
            $map['end'] = I('end');
            $where['d.create_time'][] = array('elt',strtotime(I('end').' 23:59:59'));
        }

        $where['d.status'] = 0;
        $count = M('dynamics d')->join('left join __PLAYER__ p on p.id=d.uid')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('dynamics d')
                ->field('d.id,d.level,d.uid,d.content,p.username,d.likes,d.dislike,d.share,d.comment,d.audit,d.create_time,d.top,d.publish_time,u.user_login')
                ->join('left join __PLAYER__ p on p.id=d.uid')
                ->join('left join __USERS__ u on u.id=d.adminID')
                ->where($where)
                ->order('d.top desc,d.create_time desc')
                ->limit($page->firstRow,$page->listRows)
                ->select();
        $this->data = $data;
        $this->level = json_encode(C('DRIVE_BONUS'));
        $this->page = $page->show('Admin');
        $this->map = $map;
        $this->display();
    }

    public function audit(){
        $id = I('id');
        $type = I('type');
        $level = I('level');
        $uid = I('uid');
        $end = I('end');
        $status = $type == 1 ? 1 : 3;
        if($type == 1){
            $update['level'] = $level;
            $update['reason'] = 0;
            $update['other'] = '';
        }else{
            $update['reason'] = I('reason');
            $update['other'] = I('other');
        }
        $update['audit'] = $status;
        $update['publish_time'] = I('publish_time') ? strtotime(I('publish_time')) : time();
        $update['remark'] = I('remark');
        $update['adminID'] = session('ADMIN_ID');
        if($end) $update['end_time'] = strtotime($end);

        if(M('dynamics')->where(array('id'=>$id))->setField($update) !== false){
            $times = M('dynamics')->field('publish_time,create_time')->where(array('id'=>$id))->find();
            //审核通过，判断是否每日首发，增加金币
            if($type == 1){
                $dynamics = M('dynamics')->where(array('uid'=>$uid,'_string'=>'to_days(from_unixtime(create_time)) = to_days(now())'))->count();
                if($dynamics < 2){
                    $info = C('DRIVE_BONUS');
                    foreach($info as $v){
                        if($level == $v['level']){
                            $coin = $v['bonus'];
                        }
                    }
                    $u_coin = M('player')->where(array('id'=>$uid))->getField('coin');
                    M('coin_log')->add(array('uid'=>$uid,'type'=>9,'coin_change'=>$coin,'coin_counts'=>$coin+$u_coin,'create_time'=>time()));
                    M('player')->where(array('id'=>$uid))->setInc('coin',$coin);
                    $mid = $this->getMsgID($level,1,true);
                }else{
                    $mid = $this->getMsgID($level,1,false);
                }
                M('task')->add(array('uid'=>$uid,'type'=>3,'create_time'=>$times['create_time']));
                $ext = json_encode(array('time'=>$times['publish_time']));
            }else{
                switch ($update['reason']){
                    case 1:$reason = '色情，'.$update['other'];break;
                    case 2:$reason = '暴恐，'.$update['other'];break;
                    case 3:$reason = '政治，'.$update['other'];break;
                    case 4:$reason = '反动，'.$update['other'];break;
                    case 5:$reason = '贪腐，'.$update['other'];break;
                    case 6:$reason = '其他，'.$update['other'];break;
                    default:$reason = '其他';
                }
                $ext = json_encode(array('time'=>$times['publish_time'],'reason'=>trim($reason,'，')));
                $mid = $this->getMsgID($level,2,false);
            }
            //添加审核信息

            M('user_message')->add(array('uid'=>$uid,'message_id'=>$mid,'create_time'=>time(),'ext'=>$ext));
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }

    public function add(){
        if(IS_POST){
            $data['username'] = I('username');
            $data['content'] = I('content');
            $data['top'] = I('top') ? 1 : 0;
            $data['audit'] = 1;
            $data['level'] = I('level') ? I('level') : 0;
            $data['create_time'] = time();
            $data['remark'] = I('remark');
            $data['publish_time'] = I('add_time') ? strtotime(I('add_time')) : time();
            if(I('end_time')) $data['end_time'] = strtotime(I('end_time'));
            $data['adminID'] = session('ADMIN_ID');
            if(!$data['username'] || !$data['content'] || !$data['level']){
                $this->error('加 * 项为必填');
            }
            if(!$data['uid'] = M('player')->where(array('username'=>$data['username']))->getField('id')){
                $this->error('没有该用户，请重新输入');
            }
            if($_FILES){
                $upload = new \Think\Upload();
                $upload->rootPath = C("UPLOADPATH");
                $upload->subName = array('date', 'Ymd');
                $upload->maxSize = 10485760;
                $info = $upload->upload($_FILES);
                if(!$info){
                    $this->error($upload->getError());
                }else{
                    foreach($info as $v){
                        $file_name = trim($v['fullpath'],'.');
                        $src[] = str_replace('www.sy217.com','',$file_name);
                    }
                    $data['imgs'] = json_encode($src);
                }
            }
            unset($data['username']);
            if($id = M('dynamics')->add($data)){
                $dynamics = M('dynamics')->where(array('uid'=>$data['uid'],'_string'=>'to_days(from_unixtime(create_time)) = to_days(now())'))->count();
                if($dynamics < 2){
                    $info = C('DRIVE_BONUS');
                    foreach($info as $v){
                        if($data['level'] == $v['level']){
                            $coin = $v['bonus'];
                        }
                    }
                    $u_coin = M('player')->where(array('id'=>$data['uid']))->getField('coin');
                    M('coin_log')->add(array('uid'=>$data['uid'],'type'=>9,'coin_change'=>$coin,'coin_counts'=>$coin+$u_coin,'create_time'=>time()));
                    M('player')->where(array('id'=>$data['uid']))->setInc('coin',$coin);
                    $mid = $this->getMsgID($data['level'],1,true);
                }else{
                    $mid = $this->getMsgID($data['level'],1,false);
                }
                //添加审核信息
                $time = M('dynamics')->where(array('id'=>$id))->getField('create_time');
                M('user_message')->add(array('uid'=>$data['uid'],'message_id'=>$mid,'create_time'=>time(),'ext'=>json_encode(array('time'=>$time))));
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->option = C('DRIVE_BONUS');
            $this->display();
        }
    }

    public function changeStatus(){
        $id = I('id',0);
        $status = I('status',0);
        if(M('dynamics')->where(array('id'=>$id))->setField('top',$status) !== false){
            $this->success();
        }else{
            $this->error('修改失败');
        }
    }

    public function del(){
        $type = I('type',0);
        if($type == 0){
            $id = I('id');
            if(is_array($id)){
                $id = implode(",",$id);
            }
            $where['id'] = array('in',$id);
        }else{
            $where['audit'] = 3;
        }
        if(M('dynamics')->where($where)->setField('status',1) !== false){
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }

    public function info(){
        $id = I('id');
        $data = M('dynamics')->field('content,imgs')->where(array('id'=>$id))->find();
        if($data){
            $data['imgs'] = json_decode($data['imgs'],true);

            $this->success($data);
        }else{
            $this->error('请求失败');
        }
    }


    protected function getMsgID($level,$type,$first = false){
        if($type == 1){
            if($first){
                switch ($level){
                    case 'S':$id = 3;break;
                    case 'A':$id = 4;break;
                    case 'B':$id = 5;break;
                    case 'C':$id = 6;break;
                    default:$id = 6;
                }
            }else{
                switch ($level){
                    case 'S':$id = 7;break;
                    case 'A':$id = 8;break;
                    case 'B':$id = 9;break;
                    case 'C':$id = 10;break;
                    default:$id = 10;
                }
            }
        }else{
            $id = 11;
        }
        return $id;
    }
}