<?php
/**
 * 玩家数据上报控制器
 * @author qing.li
 * @date 2018-07-11
 */

namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class ReportDataController extends AdminbaseController
{
    public function index()
    {

        $min = date('Y-m-d', strtotime("-1 month") + 3600 * 24);
        $min = (strtotime($min) < strtotime('2018-07-10')) ? '2018-07-10' : $min;

        $time = I('time') ? I('time') : date('Y-m-d', time());
        $type = I('type');
        $channel = I('channel');
        $appid = I('appid');
        $deviceID = I('deviceID');
        $username = I('username');
        $system = I('system');
        $roleName = I('roleName');

        $map = array();

        if ($type) $map['type'] = $type;
        if ($channel) $map['channel'] = $channel;
        if ($appid) $map['appid'] = $appid;
        if ($deviceID) $map['deviceID'] = $deviceID;
        if ($roleName) $map['roleName'] = array('like', '%' . $roleName . '%');
        if ($system == 1) {
            $map['_string'] = 'LOCATE("-", deviceID)=0';
        } elseif ($system == 2) {
            $map['_string'] = 'LOCATE("-", deviceID)>0';
        }

        if ($username) {
            $uid = M('player')->where(array('username' => $username))->getfield('id');
            if ($uid) {
                $map['userID'] = $uid;
            } else {
                $map['userID'] = '';
            }
        }

        try{

            $report_data_model = M('report_data' . date('Ymd', strtotime($time)));
            $count = $report_data_model->where($map)->count();
        }catch (\Exception $e){
            $this->error('今日没有角色信息',U('main/index'));
        }


        $page = $this->page($count, 20);

        $list = $report_data_model
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('createTime desc')
//            ->order('create_time desc')
            ->select();

        if (!empty($list)) {
            $appids = '';
            $channels = '';
            $userIDs = '';
            foreach ($list as $v) {
                $appids .= $v['appid'] . ',';
                $channels .= $v['channel'] . ',';
                $userIDs .= $v['userID'] . ',';
            }
            $appids = trim($appids, ',');
            $channels = trim($channels, ',');
            $userIDs = trim($userIDs, ',');

            if (!empty($appids)) $gamenames = M('game')->where(array('id' => array('in', $appids)))->getfield('id,game_name', true);
            if (!empty($channels)) $channelnames = M('channel')->where(array('id' => array('in', $channels)))->getfield('id,name', true);
            if (!empty($userIDs)) $usernames = M('player')->where(array('id' => array('in', $userIDs)))->getfield('id,username', true);
            $this->assign('gamenames', $gamenames);
            $this->assign('channelnames', $channelnames);
            $this->assign('usernames', $usernames);
        }

        $type_list = array(
            1 => '选择服务器',
            2 => '创建角色',
            3 => '进入游戏',
            4 => '等级提升',
            5 => '退出游戏',
        );

        $this->assign('roleName', $roleName);
        $this->assign('system', $system);
        $this->assign('role_id', session('ROLE_ID'));
        $this->assign('page', $page->show('Admin'));
        $this->assign('type_list', $type_list);
        $this->assign('selected_channel_type', I('channel_type'));
        $this->assign('channel_type', C('channel_type'));
        $this->assign('channel_list', get_channel_list($channel, I('channel_type')));
        $this->assign('game_list', get_game_list($appid, 1, 1, 'all', 'all', 'all', ''));
        $this->assign('list', $list);
        $this->assign('time', $time);
        $this->assign('type', $type);
        $this->assign('channel', $channel);
        $this->assign('appid', $appid);
        $this->assign('deviceID', $deviceID);
        $this->assign('username', $username);
        $this->assign('max', date('Y-m-d', time()));
        $this->assign('min', $min);
        $this->display();
    }
}