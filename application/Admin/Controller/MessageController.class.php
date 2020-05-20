<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/3/21
 * Time: 15:54
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class MessageController extends AdminbaseController{

    public function index(){
        $title = I('title');
        $start = I('start');
        $end = I('end');

        $where['m.type'] = 3;
        if($title) $where['m.title'] = array('like','%'.$title.'%');
        if($start) $where['m.create_time'][] = array('gt',strtotime($start));
        if($end) $where['m.create_time'][] = array('lt',strtotime($end.' 23:59:59'));

        $count = M('message m')
            ->join('left join __USERS__ u on u.id = m.adminID ')
            ->where($where)->count();
        $page = $this->page($count,20);
        $data = M('message m')
            ->field('m.*,u.user_login')
            ->join('left join __USERS__ u on u.id = m.adminID ')
            ->where($where)->order('id desc')->limit($page->firstRow,$page->listRows)->select();

        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->start = $start;
        $this->end = $end;
        $this->title = $title;
        $this->display();
    }

    public function add(){

        $attach_top = C('MESSAGE_ATTACH_TOP');
        if(IS_POST)
        {
            $uids = '';
            $usernames = I('usernames');
            if(!empty($usernames))
            {
                $uids = M('player')->where(array('username'=>array('in',str_replace('，',',',I('usernames')))))->getfield('id',true);
                if($uids)
                {
                    $uids = implode(',',$uids);
                }
            }
            $data = array(
                'title' => I('title'),
                'uids'=>$uids,
                'desc' => I('desc'),
                'type' => 3,
                'action' => I('action'),
                'adminID' => session('ADMIN_ID'),
                'end_time'=>strtotime(I('end_time')),
                'create_time' => time()
            );

            if($data['action'] == 2)
            {
                if(I('attach_count') > $attach_top)
                {
                    $this->error('附件数量不能超过'.$attach_top);
                }
                $data['attach_type'] = I('attach_type');
                $data['attach_count'] = I('attach_count');
                $data['api_url'] = '/index.php?g=api&m=platformmoney&a=get_reigster_bonus';
            }
            else
            {
                $data['attach_type'] = 0;
                $data['attach_count'] = 0;
                $data['api_url'] = '';
            }

            if(M('message')->add($data)){
                $this->success('添加成功');
            }else{
                $this->error('操作失败');
            }
        }
        $this->assign('default_time',date('Y-m-d',strtotime('+7 days')).' 00:00');
        $this->assign('attach_top',$attach_top);
        $this->display();

    }

    /**
     * 玩家邮件-新 添加消息
     */
    public function add_msg(){

        $attach_top = C('MESSAGE_ATTACH_TOP');
        if(IS_POST)
        {
            $uids = '';
            $usernames = I('usernames');
            if(!empty($usernames))
            {
                $uids = M('player')->where(array('username'=>array('in',str_replace('，',',',I('usernames')))))->getfield('id',true);
                if($uids)
                {
                    $uids = implode(',',$uids);
                }
            }
            $data = array(
                'title' => I('title'),
                'uids'=>$uids,
                'desc' => I('desc'),
                'type' => 4,
                'message_type' => 4,
                'action' => 1,
                'attach_type' => 0,
                'attach_count' => 0,
                'api_url' => '',
                'adminID' => session('ADMIN_ID'),
                'end_time'=>strtotime(I('end_time')),
                'create_time' => time()

            );


            if(M('message')->add($data)){
                $this->success('添加成功');
            }else{
                $this->error('操作失败');
            }
        }
        $this->assign('default_time',date('Y-m-d',strtotime('+7 days')).' 00:00');
        $this->assign('attach_top',$attach_top);
        $this->display();

    }

    /**
     * 玩家邮件-新 添加附件
     */
    public function add_attach(){

        $attach_top = C('MESSAGE_ATTACH_TOP');
        if(IS_POST)
        {

            $uids = '';
            $usernames = I('usernames');
            if(!empty($usernames))
            {
                $uids = M('player')->where(array('username'=>array('in',str_replace('，',',',I('usernames')))))->getfield('id',true);
                if($uids)
                {
                    $uids = implode(',',$uids);
                }
            }
            $data = array(
                'title' => I('title'),
                'uids'=>$uids,
                'desc' => I('desc'),
                'type' => 4,
                'message_type' => 4,
                'action' => 2,
                'attach_type' => I('attach_type'),
                'attach_count' => I('attach_count'),
                'api_url' => '/index.php?g=api&m=platformmoney&a=get_msg_platform',
                'adminID' => session('ADMIN_ID'),
                'end_time'=>strtotime(I('end_time')),
                'create_time' => time()
            );
            if(I('attach_count') > $attach_top)
            {
                $this->error('附件数量不能超过'.$attach_top);
            }

            if(M('message')->add($data)){
                $this->success('添加成功');
            }else{
                $this->error('操作失败');
            }
        }
        $this->assign('default_time',date('Y-m-d',strtotime('+7 days')).' 00:00');
        $this->assign('attach_top',$attach_top);
        $this->display();

    }

    public function edit(){
        $attach_top = C('MESSAGE_ATTACH_TOP');
        $id = I('id');
        if(IS_POST)
        {
            $data = I('post.');

            $uids = '';
            if(!empty($data['usernames']))
            {
                $uids = M('player')->where(array('username'=>array('in',str_replace('，',',',$data['usernames']))))->getfield('id',true);
                if($uids)
                {
                    $uids = implode(',',$uids);
                }
            }

            $data['uids'] = $uids;

            if($data['action'] == 2)
            {
                if($data['attach_count'] > $attach_top)
                {
                    $this->error('附件数量不能超过'.$attach_top);
                }
                $data['attach_type'] = I('attach_type');
                $data['attach_count'] = I('attach_count');
                $data['api_url'] = '/index.php?g=api&m=platformmoney&a=get_reigster_bonus';
            }
            else
            {
                $data['attach_type'] = 0;
                $data['attach_count'] = 0;
                $data['api_url'] = '';
            }

            $data['end_time'] =strtotime($data['end_time']);

            if(M('message')->where(array('id'=>$id))->save($data) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }


        $info = M('message')->where(array('id'=>$id))->find();

        $usernames = '';
        if(!empty($info['uids']))
        {
            $usernames = M('player')->where(array('id'=>array('in',$info['uids'])))->getfield('username',true);
            $usernames = implode(',',$usernames);
        }

        $this->assign('usernames',$usernames);
        $this->assign('info',$info);
        $this->assign('id',$id);
        $this->assign('attach_top',$attach_top);
        $this->display();

    }

    /**
     * 玩家邮件-新 修改信息
     */
    public function edit_msg(){
        $attach_top = C('MESSAGE_ATTACH_TOP');
        $id = I('id');
        if(IS_POST)
        {
            $data = I('post.');

            $uids = '';
            if(!empty($data['usernames']))
            {
                $uids = M('player')->where(array('username'=>array('in',str_replace('，',',',$data['usernames']))))->getfield('id',true);
                if($uids)
                {
                    $uids = implode(',',$uids);
                }
            }

            $data['uids'] = $uids;
            $data['end_time'] = strtotime($data['end_time']);

            if(M('message')->where(array('id'=>$id))->save($data) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }

        $info = M('message')->where(array('id'=>$id))->find();

        $usernames = '';
        if(!empty($info['uids']))
        {
            $usernames = M('player')->where(array('id'=>array('in',$info['uids'])))->getfield('username',true);
            $usernames = implode(',',$usernames);
        }

        $this->assign('usernames',$usernames);
        $this->assign('info',$info);
        $this->assign('id',$id);
        $this->assign('attach_top',$attach_top);
        $this->display();

    }

    /**
     * 玩家邮件-新 修改附件
     */
    public function edit_attach(){
        $attach_top = C('MESSAGE_ATTACH_TOP');
        $id = I('id');
        if(IS_POST)
        {
            $data = I('post.');

            $uids = '';
            if(!empty($data['usernames']))
            {
                $uids = M('player')->where(array('username'=>array('in',str_replace('，',',',$data['usernames']))))->getfield('id',true);
                if($uids)
                {
                    $uids = implode(',',$uids);
                }
            }

            $data['uids'] = $uids;
            if($data['attach_count'] > $attach_top)
            {
                $this->error('附件数值不能超过'.$attach_top);
            }

            $data['end_time'] = strtotime($data['end_time']);

            if(M('message')->where(array('id'=>$id))->save($data) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }


        $info = M('message')->where(array('id'=>$id))->find();

        $usernames = '';
        if(!empty($info['uids']))
        {
            $usernames = M('player')->where(array('id'=>array('in',$info['uids'])))->getfield('username',true);
            $usernames = implode(',',$usernames);
        }

        $this->assign('usernames',$usernames);
        $this->assign('info',$info);
        $this->assign('id',$id);
        $this->assign('attach_top',$attach_top);
        $this->display();

    }

    public function del(){
        $id = I('id');
        if(is_array($id)) $id = implode(',',$id);
        if(M('message')->where(array('id'=>array('in',$id)))->delete() !== false){
            M('user_message')->where(array('message_id'=>array('in',$id)))->delete();
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 玩家邮件-新
     */
    public function index_new(){
        $title = I('title');
        $start = I('start');
        $end = I('end');
        $channel_role = session('channel_role');
        $cid = I('cid');
        if($channel_role == 'all') {
            if($cid){
                $admin = M('channel')->where(array('id'=>$cid))->getField('admin_id');
                $where['m.adminID'] = $admin;
            }
        } else {
            $admin = session('ADMIN_ID');
            $where['m.adminID'] = $admin;
        }
        $where['m.type'] = 4;
        if($title) $where['m.title'] = array('like','%'.$title.'%');
        if($start) $where['m.create_time'][] = array('gt',strtotime($start));
        if($end) $where['m.create_time'][] = array('lt',strtotime($end.' 23:59:59'));

        $count = M('message m')
            ->join('left join __USERS__ u on u.id = m.adminID ')
            ->where($where)->count();
        $page = $this->page($count,20);
        $data = M('message m')
            ->field('m.*,u.user_login')
            ->join('left join __USERS__ u on u.id = m.adminID ')
            ->where($where)->order('id desc')->limit($page->firstRow,$page->listRows)->select();

        $this->role = $channel_role;
        $this->cid = $cid;
        $this->data = $data;
        $this->start = $start;
        $this->end = $end;
        $this->title = $title;
        $this->display();
    }
}