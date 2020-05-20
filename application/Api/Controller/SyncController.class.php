<?php

namespace Api\Controller;

use Common\Controller\AppframeController;
use Think\Db;
use Think\Log;

class SyncController extends AppframeController
{

    function _initialize()
    {
    }

    public function sync_data()
    {

        set_time_limit(0);

        $this->Inpour = M('Inpour');
        //可传参数time 不传递默认为前一天
        $time = I('get.time') ? I('get.time') : date('Y-m-d', strtotime('-1 day'));

        $start_time = strtotime($time);

        $table_name = 'player_login_logs' . date('Ym', $start_time);
        $this->player_login_logs = M($table_name);
        $this->app_player_model = M('app_player');
        $this->player_model = M('player');

        $map['create_time'] = array(array('egt', $start_time), array('lt', $start_time + 3600 * 24));

        $channel_1 = $this->player_login_logs->field('channel as id')->where($map)->group('channel')->select();

        $channel_2 = $this->Inpour->field('cid as id')->where($map)->group('cid')->select();

        $channels = array_merge($channel_1, $channel_2);

        $channels = array_unique(i_array_column($channels, 'id'));

        $games_1 = $this->player_login_logs->field("appid as id")->where($map)->group('appid')->select();

        $games_2 = $this->Inpour->field('appid as id')->where($map)->group('appid')->select();

        $games = array_merge($games_1, $games_2);

        $games = array_unique(i_array_column($games, 'id'));

        foreach ($channels as $channel) {
            foreach ($games as $game) {
                $map = array(
                    'channel' => $channel,
                    'appid' => $game,
                );

                $map_other = array(
                    'a.cid' => $channel,
                    'a.appid' => $game,
                    'b.channel' => $channel,
                    'b.appid' => $game,
                );

                $map['create_time'] = array(array('egt', $start_time), array('lt', $start_time + 3600 * 24));
                $map_other['a.create_time'] = array(array('egt', $start_time), array('lt', $start_time + 3600 * 24));
                $map_other['b.first_login_time'] = array(array('egt', $start_time), array('lt', $start_time + 3600 * 24));


                $active_users = $this->player_login_logs
                    ->distinct(true)->field('uid')
                    ->where($map)
                    ->select();

                $map['cid'] = $channel;
                unset($map['channel']);

                $active_user = count($active_users);
                if (!empty($active_users)) {
                    $uids = '';
                    foreach ($active_users as $v) {
                        $uids .= $v['uid'] . ',';
                    }
                    $uids = trim($uids, ',');
                    $map_active = $map;
                    $map_active['uid'] = array('in', $uids);
                    $map_active['status'] = array('neq', 3);
                    $pay_active_info = $this->Inpour
                        ->field("count(distinct uid) as pay_number,sum(money) as money")
                        ->where($map_active)
                        ->find();
                }

                $pay_info = $this->Inpour
                    ->field("count(*) as pay_counts,count(distinct uid) as uid_counts,sum(money) as money,sum(getmoney) as getmoney,sum(platform_money) as platform_money,sum(rebate) as rebate")
                    ->where(array_merge($map, array('status' => array('neq', 3))))
                    ->find();


                $new_users = $this->app_player_model
                    ->field('count(*) as count,count(distinct(machine_code)) as valid_new_user')
                    ->where(array('appid' => $game, 'channel' => $channel, 'first_login_time' => array(array('egt', $start_time), array('lt', $start_time + 3600 * 24))))
                    ->find();

                $new_box_users = $this->player_model
                    ->where(array('appid' => $game, 'channel' => $channel, 'create_time' => array(array('egt', $start_time), array('lt', $start_time + 3600 * 24))))
                    ->count('DISTINCT machine_code');

                $newuser_pay = $this->Inpour
                    ->alias("a")
                    ->join('__APP_PLAYER__ as b ON a.app_uid = b.id')
                    ->field("count(distinct(a.id)) as pay_counts,count(distinct a.uid,a.app_uid) as uid_counts,sum(a.money) as money")
                    ->where(array_merge($map_other, array('a.status' => array('neq', 3))))
                    ->find();

                //如果存在数据才插入bt_pay_by_day
                if ($active_user || $pay_active_info['pay_number'] || $pay_active_info['money'] || $pay_info['pay_counts'] || $pay_info['uid_counts'] ||
                    $pay_info['money'] || $pay_info['getmoney'] || $pay_info['platform_money'] || $pay_info['rebate'] || $new_users['count']
                    || $newuser_pay['pay_counts'] || $newuser_pay['uid_counts'] || $newuser_pay['money']) {
                    $data = array(
                        'appid' => $game,
                        'cid' => $channel,
                        'active_user' => $active_user,
                        'pay_counts' => $pay_info['pay_counts'],
                        'pay_number' => $pay_info['uid_counts'],
                        'pay_amount' => isset($pay_info['money']) ? $pay_info['money'] : 0.00,
                        'getmoney' => isset($pay_info['getmoney']) ? $pay_info['getmoney'] : 0.00,
                        'platform_money' => isset($pay_info['platform_money']) ? $pay_info['platform_money'] : 0,
                        'rebate' => isset($pay_info['rebate']) ? $pay_info['rebate'] : 0,
                        'new_box_user' => $new_box_users,
                        'pay_active_number' => isset($pay_active_info['pay_number']) ? $pay_active_info['pay_number'] : 0,
                        'pay_active_amount' => isset($pay_active_info['money']) ? $pay_active_info['money'] : 0.00,
                        'new_user' => $new_users['count'],
                        'valid_new_user' => $new_users['valid_new_user'],
                        'new_user_counts' => $newuser_pay['pay_counts'],
                        'new_user_number' => $newuser_pay['uid_counts'],
                        'new_user_amount' => isset($newuser_pay['money']) ? $newuser_pay['money'] : 0.00,
                        'time' => $time,
                    );

                    @$res = M('pay_by_day')->add($data);
                }

            }
        }
        M('script_endtime')->add(array('datetime' => time()));

        exit('success');

    }

    /**
     * 创建 日志 表
     */
    public function create_report_data()
    {

//        $table = 'bt_report_data' . date('Ymd');
        $table = 'bt_report_data' . date('Ymd', strtotime('+1 day'));

        $Model = new \Think\Model(); // 实例化一个model对象 没有对应任何数据表
        $result = $Model->query("SHOW TABLES LIKE '$table'");

        if ($result) {

            echo "Has been created";

        } else {

            $sql = "CREATE TABLE `$table` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL COMMENT '1选择服务器，2创建角色，3进入游戏，4等级提升，5退出游戏',
  `channel` int(11) unsigned NOT NULL COMMENT '渠道ID',
  `appid` int(11) NOT NULL COMMENT '游戏ID',
  `deviceID` varchar(36) CHARACTER SET utf8mb4 NOT NULL COMMENT '设备号',
  `ip` varchar(15) CHARACTER SET utf8mb4 NOT NULL COMMENT 'IP',
  `userID` int(11) NOT NULL COMMENT '用户ID',
  `serverID` int(11) NOT NULL DEFAULT '0' COMMENT '服务器ID',
  `serverName` varchar(50) CHARACTER SET utf8mb4 NOT NULL DEFAULT '' COMMENT '服务器名',
  `roleID` varchar(50) CHARACTER SET utf8mb4 NOT NULL DEFAULT '' COMMENT '角色ID',
  `roleName` varchar(50) CHARACTER SET utf8mb4 NOT NULL DEFAULT '' COMMENT '角色名',
  `roleLevel` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '角色等级',
  `money` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '角色拥有游戏币数量',
  `vip` varchar(20) CHARACTER SET utf8mb4 NOT NULL DEFAULT '' COMMENT 'VIP等级（上报可能会为字符）',
  `regTime` int(11) NOT NULL COMMENT '账号注册时间',
  `createTime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `deviceID` (`deviceID`,`channel`,`appid`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

            M('', '', 'DB_CONFIG_ACTIVE')->execute($sql);

            echo "successfully created";

        }

    }

}