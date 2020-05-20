<?php
/**
 * 管理员评分系统
 * @author qing.li
 * @date 2018-05-25
 */

namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class AdminRateController extends AdminbaseController
{
    public function index()
    {
        $admin_user = I('admin_user');
        $rate_admin_user = I('rate_admin_user');
        $start_time = I('start_time')?I('start_time'):date('Y-m-d',time());
        $end_time = I('end_time')?I('end_time'):date('Y-m-d',time());
        $type = I('type');

        if($admin_user)
        {
            $map['admin_id'] = M('users')->where(array('user_login'=>$admin_user))->getfield('id');
        }
        if($rate_admin_user)
        {
            $map1['_logic'] = 'OR';
            $map1['rate_admin_id'] = M('users')->where(array('user_login'=>$rate_admin_user))->getfield('id');
            $map1['rate_uid'] = M('player')->where(array('username'=>$rate_admin_user))->getfield('id');
            $map['_complex'] = $map1;
        }
        if($start_time) $map['create_time'][] = array('egt',strtotime($start_time));
        if($end_time) $map['create_time'][] = array('lt',strtotime($end_time)+3600*24);
        if($type) $map['type'] = $type;

        $count = M('admin_rate')->where($map)->count();

        $page = $this->page($count, 20);

        $data =M('admin_rate')
             ->where($map)
            ->order('create_time desc')
            ->limit($page->firstRow,$page->listRows)
            ->select();

        $admin_ids = '';
        $uids = '';
        foreach($data as $v)
        {
            $admin_ids.=$v['admin_id'].',';
            $admin_ids.=$v['rate_admin_id'].',';
            $uids.=$v['rate_uid'].',';
        }

        $admin_ids = trim($admin_ids,',');
        $uids = trim($uids,',');

        $admin_users = M('users')->where(array('id'=>array('in',$admin_ids)))->getfield('id,user_login',true);

        if($uids)
        {
            $player_users = M('player')->where(array('id'=>array('in',$uids)))->getfield('id,username',true);
            $this->player_users = $player_users;
        }


        $this->type = $type;
        $this->admin_user = $admin_user;
        $this->rate_admin_user = $rate_admin_user;
        $this->start_time = $start_time;
        $this->end_time = $end_time;
        $this->admin_users =$admin_users;
        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->max = date('Y-m-d',time());
        $this->display();
    }

    public function statistics()
    {
        $end_time = I('end_time')?I('end_time'):date('Y-m-d',time());
        $start_time = I('start_time')?I('start_time'):date('Y-m-01');
        $type = I('type');

        $admin_user = I('admin_user');
        $order = I('order')?I('order'):10;

        if($start_time) $map['a.create_time'][] = array('egt',strtotime($start_time));
        if($end_time) $map['a.create_time'][] = array('lt',strtotime($end_time)+3600*24);

        if($admin_user)
        {
            $map['a.admin_id'] = M('users')->where(array('user_login'=>$admin_user))->getfield('id');
        }

        if($type)
        {
            $map['a.type'] = $type;
        }


        $result = M('admin_rate')
            ->field('u.user_login,a.rate,count(*) count')
            ->alias('a')
            ->join('bt_users u on a.admin_id = u.id')
            ->where($map)
            ->group('a.admin_id,a.rate')
            ->select();

        $data = array();
        foreach($result as $v)
        {
            $data[$v['user_login']][$v['rate']] = $v['count'];
        }

        switch ($order)
        {
            case 1:
                $key = 1;
                $arr_order = SORT_ASC;
                break;
            case 2:
                $key = 1;
                $arr_order = SORT_DESC;
                break;
            case 3:
                $key = 2;
                $arr_order = SORT_ASC;
                break;
            case 4:
                $key = 2;
                $arr_order = SORT_DESC;
                break;
            case 5:
                $key = 3;
                $arr_order = SORT_ASC;
                break;
            case 6:
                $key = 3;
                $arr_order = SORT_DESC;
                break;
            case 7:
                $key = 4;
                $arr_order = SORT_ASC;
                break;
            case 8:
                $key = 4;
                $arr_order = SORT_DESC;
                break;
            case 9:
                $key = 5;
                $arr_order = SORT_ASC;
                break;
            case 10:
                $key = 5;
                $arr_order = SORT_DESC;
                break;
            default:
        }

        //提取列数组；
        foreach ($data as $k => $v) {
            $tmp[$k] = $v[$key];
        }

        array_multisort($tmp,$arr_order,$data);
        $this->type = $type;
        $this->admin_user = $admin_user;
        $this->start_time = $start_time;
        $this->end_time = $end_time;
        $this->data = $data;
        $this->order = $order;
        $this->max = date('Y-m-d',time());
        $this->display();
    }

}