<?php

namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class CompatibleStatisticsController extends AdminbaseController
{

    public function _initialize()
    {
        parent::_initialize();
        //	$this->player_login_logs = M("player_login_logs");
        $this->Inpour = M('Inpour');
        $this->appids = M('game')->where(array('access_type' => 1))->getfield('id', true);
    }


    public function pay_statistics()
    {

        $post_data = $_REQUEST;
        //0为普通模式 1为导出模式
        $action = !empty($post_data['action']) ? $post_data['action'] : 0;
        //日期最大限制
        $max = date('Y-m-d', time() - 3600 * 24);

        $start_time = $post_data['start_time'] ? $post_data['start_time'] : date('Y-m-d', strtotime('-1 week'));
        $end_time = $post_data['end_time'] ? $post_data['end_time'] : $max;
        $count = (strtotime($end_time) - strtotime($start_time)) / (3600 * 24) + 1;
        $page = $this->page($count, 20);

        $channel_name = '--';
        $game_name = '--';
        $p = I('get.p') ? I('get.p') : 1;
        $map = array();
        $map_old = array();

        $heji_old = array();

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map_old['gameid'] = array('in', $game_role);
        } else {
            if ($this->appids) {
                $map['appid'] = array('in', implode(',', $this->appids));
            } else {
                $map['appid'] = array('in', '');
            }
        }


        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map_old['channel'] = array('in', $channel_role);
        }

        if (!empty($post_data['cid'])) {
            $map['cid'] = $post_data['cid'];
            $map_old['channel'] = $post_data['cid'];
            $channel_name = M('Channel')->where(array('id' => $post_data['cid']))->getfield('name');
        }
        if (!empty($post_data['appid'])) {
            $map['appid'] = $post_data['appid'];
            $map_old['gameid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id' => $post_data['appid']))->getfield('game_name');
        }


        $datearr = array();

        if (I('action') == 1) {
            $end_time_conf = strtotime($end_time . ' 23:59:59');
            $start_time_conf = strtotime($start_time . ' 00:00:00');
            if (($end_time_conf - $start_time_conf) > 31 * 3600 * 24) {
                $this->error('最大能导出31天的数据');
            }
        } else {
            $end_time_conf = strtotime($end_time . ' 23:59:59') - ($p - 1) * 24 * 3600 * 20;

            $start_time_conf = $end_time_conf - 24 * 3600 * 20;
        }

        if ($start_time_conf <= strtotime($start_time . ' 00:00:00')) {
            $start_time_conf = strtotime($start_time . ' 00:00:00');
            $map['time'] = array(array('egt', date('Y-m-d', $start_time_conf)), array('elt', date('Y-m-d', $end_time_conf)));
            $map_old['time'] = array(array('egt', $start_time_conf), array('elt', $end_time_conf));
            $map_old['pay_to_time'] = array(array('egt', $start_time_conf), array('elt', $end_time_conf));
        } else {
            $map['time'] = array(array('gt', date('Y-m-d', $start_time_conf)), array('elt', date('Y-m-d', $end_time_conf)));
            $map_old['time'] = array(array('gt', $start_time_conf), array('elt', $end_time_conf));
            $map_old['pay_to_time'] = array(array('gt', $start_time_conf), array('elt', $end_time_conf));
        }


        while ($start_time_conf < $end_time_conf) {
            $datearr [] = date('Y-m-d', $end_time_conf);
            $end_time_conf = $end_time_conf - 3600 * 24;
        }

        $info = M('pay_by_day')->
        field('time,sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user),sum(valid_new_user)')->
        where($map)->
        group('time')->
        order('time desc')->
        cache(true)->
        select();

        $map['time'] = array(array('egt', $start_time), array('elt', $end_time));

        $heji = M('pay_by_day')->
        field('sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user),sum(valid_new_user)')->
        where($map)->
        cache(true)->
        find();

        $login_log_model = M('login_log', null, C('DB_OLDSDK_CONFIG'));

        $active_users = $login_log_model
            ->field("count(distinct(username)) as count, FROM_UNIXTIME(time,'%Y-%m-%d') as time")
            ->where($map_old)
            ->group("FROM_UNIXTIME(time,'%Y-%m-%d')")
            ->order("FROM_UNIXTIME(time,'%Y-%m-%d') desc")
            ->cache(true)
            ->select();

        $pay_model = M('pay', 'syo_', C('DB_OLDSDK_CONFIG'));

        $pay_info = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money,FROM_UNIXTIME(pay_to_time,'%Y-%m-%d') as time")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->group("FROM_UNIXTIME(pay_to_time,'%Y-%m-%d')")
            ->order("FROM_UNIXTIME(pay_to_time,'%Y-%m-%d') desc")
            ->cache(true)
            ->select();

        $newplayer_model = M('newplayer', null, C('DB_OLDSDK_CONFIG'));

        $new_users = $newplayer_model
            ->field("count(username) as count,count(distinct(device_id)) as valid_new_user, FROM_UNIXTIME(time,'%Y-%m-%d') as time")
            ->where($map_old)
            ->group("FROM_UNIXTIME(time,'%Y-%m-%d')")
            ->order("FROM_UNIXTIME(time,'%Y-%m-%d') desc")
            ->cache(true)
            ->select();

        $map_old['time'] = array(array('egt', strtotime($start_time . ' 00:00:00')), array('elt', strtotime($end_time . ' 23:59:59')));
        $map_old['pay_to_time'] = array(array('egt', strtotime($start_time . ' 00:00:00')), array('elt', strtotime($end_time . ' 23:59:59')));

        $active_users_heji = $login_log_model
            ->field("count(distinct(username)) as count")
            ->where($map_old)
            ->cache(true)
            ->find();

        $pay_info_heji = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->cache(true)
            ->find();

        $new_users_heji = $newplayer_model
            ->field("count(username) as count,count(distinct(device_id)) as valid_new_user")
            ->where($map_old)
            ->cache(true)
            ->find();


        $new_info = array();
        foreach ($info as $v) {
            $new_info[$v['time']] = $v;
        }

        unset($info);


        $active_users_info = array();
        if (is_array($active_users)) {
            foreach ($active_users as $active_user) {
                $active_users_info[$active_user['time']] = $active_user;

            }

        }

        $heji_old['active_user'] = $active_users_heji['count'];
        unset($active_users);

        $pay_info_info = array();
        if (is_array($pay_info)) {
            foreach ($pay_info as $v) {
                $pay_info_info[$v['time']] = $v;

            }
        }

        $heji_old['pay_counts'] = $pay_info_heji['pay_counts'];
        $heji_old['pay_number'] = $pay_info_heji['uid_counts'];
        $heji_old['pay_amount'] = $pay_info_heji['money'];
        unset($pay_info);

        $new_users_info = array();
        if (is_array($new_users)) {
            foreach ($new_users as $v) {
                $new_users_info[$v['time']] = $v;

            }
        }
        $heji_old['new_user'] = $new_users_heji['count'];
        $heji_old['valid_new_user'] = $new_users_heji['valid_new_user'];
        unset($new_users);


        $result = array();
        //组装数据;
        foreach ($datearr as $v) {
            $active_users_v = (isset($new_info[$v]['sum(active_user)']) ? $new_info[$v]['sum(active_user)'] : 0) + (isset($active_users_info[$v]['count']) ? $active_users_info[$v]['count'] : 0);
            $pay_counts_v = (isset($new_info[$v]['sum(pay_counts)']) ? $new_info[$v]['sum(pay_counts)'] : 0) + (isset($pay_info_info[$v]['pay_counts']) ? $pay_info_info[$v]['pay_counts'] : 0);
            $uid_counts_v = (isset($new_info[$v]['sum(pay_number)']) ? $new_info[$v]['sum(pay_number)'] : 0) + (isset($pay_info_info[$v]['uid_counts']) ? $pay_info_info[$v]['uid_counts'] : 0);
            $money_v = (isset($new_info[$v]['sum(pay_amount)']) ? $new_info[$v]['sum(pay_amount)'] : '0.00') + (isset($pay_info_info[$v]['money']) ? $pay_info_info[$v]['money'] : '0.00');
            $money_v = sprintf("%.2f", $money_v);
            $active_arpu = round($money_v / $active_users_v, 2);

            $new_users_v = (isset($new_info[$v]['sum(new_user)']) ? $new_info[$v]['sum(new_user)'] : 0) + (isset($new_users_info[$v]['count']) ? $new_users_info[$v]['count'] : 0);
            $valid_new_users_v = (isset($new_info[$v]['sum(valid_new_user)']) ? $new_info[$v]['sum(valid_new_user)'] : 0) + (isset($new_users_info[$v]['valid_new_user']) ? $new_users_info[$v]['valid_new_user'] : 0);
            $pay_arpu = round($money_v / $uid_counts_v);
            $pay_lv = round($uid_counts_v / $active_users_v, 4) * 100;
            $pay_lv .= '%';

            $result[] = array($v, $active_users_v, $new_users_v, $valid_new_users_v, $pay_counts_v, $uid_counts_v, $money_v, $active_arpu, $pay_arpu, $pay_lv);
        }
        if ($action == 0) {
            $this->assign('p', $p);
            $this->assign('heji', $heji);
            $this->assign('heji_old', $heji_old);
            $this->assign('channel_name', $channel_name);
            $this->assign('game_name', $game_name);
            $this->assign('result', $result);
            $this->assign('start_time', $start_time);
            $this->assign('end_time', $end_time);
            $this->assign('channel_list', get_channel_list(I('cid'), I('channel_type')));
            $this->assign('game_list', get_game_list(I('appid'), 1, 'all', 'all', 'all', 'all', '', 1));
            $this->assign('page', $page->show('Admin'));
            $this->assign('max', $max);
            $this->selected_channel_type = I('channel_type');
            $this->channel_type = C('channel_type');
            $this->display();
        } else {

            //导出模式
            $xlsTitle = iconv('utf-8', 'gb2312', '订单统计');//文件名称
            $fileName = date('_YmdHis') . '订单统计';//or $xlsTitle 文件名称可根据自己情况设定

            $expCellName = array('日期', '游戏名称', '渠道名称', '活跃用户', '新增用户', '新增用户(排重)', '充值次数', '充值人数', '总充值金额', '活跃Arpu', '付费Arpu', '付费率');

            $cellNum = count($expCellName);
            $heji_item = array('合计', $heji['sum(active_user)'] + $heji_old['active_user'], $heji['sum(new_user)'] + $heji_old['new_user'],
                $heji['sum(valid_new_user)'] + $heji_old['valid_new_user'],
                $heji['sum(pay_counts)'] + $heji_old['pay_counts'],
                $heji['sum(pay_number)'] + $heji_old['pay_number'], $heji['sum(pay_amount)'] + $heji_old['pay_amount'],
                round(($heji['sum(pay_amount)'] + $heji_old['pay_amount']) / ($heji['sum(active_user)'] + $heji_old['active_user']), 2),
                round(($heji['sum(pay_amount)'] + $heji_old['pay_amount']) / ($heji['sum(pay_number)'] + $heji_old['pay_number']), 2),
                round(($heji['sum(pay_number)'] + $heji_old['pay_number']) / ($heji['sum(active_user)'] + $heji_old['active_user']), 4) * 100,);

            $heji_item[9] .= '%';

            array_unshift($result, $heji_item);

            $dataNum = count($result);

            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for ($i = 0; $i < $cellNum; $i++) {
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '1', $expCellName[$i]);
            }
            // Miscellaneous glyphs, UTF-8
            for ($i = 0; $i < $dataNum; $i++) {
                for ($j = 0; $j < $cellNum; $j++) {
                    if ($j == 0) {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $result[$i][$j]);
                    } elseif ($j == 1) {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $game_name);
                    } elseif ($j == 2) {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $channel_name);
                    } else {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $result[$i][$j - 2]);
                    }

                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit(1);
        }
    }

    public function active_player()
    {
        $post_data = I('post.');
        $time = $post_data['time'] ? $post_data['time'] : date('Y-m-d', time());
        $channel_name = '--';
        $game_name = '--';

        $map = array();
        $map_new = array();

        if (!empty($post_data['channel'])) {
            $map['channel'] = $post_data['channel'];
            $map_new['channel'] = $post_data['channel'];
            $channel_name = M('Channel')->where(array('id' => $post_data['channel']))->getfield('name');
        } else {
            if (session('channel_role') != 'all') {
                $map['channel'] = array('in', session('channel_role'));
                $map_new['channel'] = array('in', session('channel_role'));
            }
        }


        if (!empty($post_data['gameid'])) {
            $map['gameid'] = $post_data['gameid'];
            $map_new['appid'] = $post_data['gameid'];
            $game_name = M('Game')->where(array('id' => $post_data['gameid']))->getfield('game_name');
        } else {
            if (session('game_role') != 'all') {
                $map['gameid'] = array('in', session('game_role'));
                $map_new['appid'] = array('in', session('game_role'));
            } else {
                if ($this->appids) {

                    $map_new['appid'] = array('in', implode(',', $this->appids));
                } else {

                    $map_new['appid'] = array('in', '');
                }
            }
        }


        $login_log_model = M('login_log', null, C('DB_OLDSDK_CONFIG'));

        $start_time = strtotime($time . ' 00:00:00');
        $end_time = strtotime($time . ' 23:59:59');

        $map['time'] = array(array('egt', $start_time), array('elt', $end_time));
        $map_new['create_time'] = array(array('egt', $start_time), array('elt', $end_time));

        $day_active = $login_log_model
            ->field('count(distinct(username)) as count')
            ->where($map)
            ->cache(true)
            ->find();

        $current_player_login_logs = M('player_login_logs' . date('Ym', $start_time));

        $day_active_new = $current_player_login_logs
            ->field('count(distinct(username)) as count')
            ->where($map_new)
            ->cache(true)
            ->find();


        $start_time = $start_time - 6 * 24 * 3600;

        $map['time'][0] = array('egt', $start_time);
        $map_new['create_time'][0] = array('egt', $start_time);

        $week_active = $login_log_model
            ->field('count(*) as count')
            ->where($map)
            ->group('username')
            ->having('count >= 3')
            ->cache(true)
            ->select();

        $week_active_new = $current_player_login_logs
            ->field('count(*) as count')
            ->where($map_new)
            ->group('uid')
            ->having('count >= 3')
            ->cache(true)
            ->select();
        $week_active_new1 = array();
        if (date('Ym', $start_time) != date('Ym', $start_time + 6 * 24 * 3600)) {
            $week_active_new1 = M('player_login_logs' . date('Ym', $start_time))
                ->field('count(*) as count')
                ->where($map_new)
                ->group('uid')
                ->having('count >= 3')
                ->cache(true)
                ->select();
        }

        $week_active = count($week_active);
        $week_active_new = count($week_active_new) + count($week_active_new1);

        $start_time = $start_time - 23 * 24 * 3600;

        $map['time'][0] = array('egt', $start_time);
        $map_new['create_time'][0] = array('egt', $start_time);
        $month_active = $login_log_model
            ->field('count(*) as count')
            ->where($map)
            ->group('username')
            ->having('count >= 7')
            ->cache(true)
            ->select();

        $month_active_new = $current_player_login_logs
            ->field('count(*) as count')
            ->where($map_new)
            ->group('uid')
            ->having('count >= 7')
            ->cache(true)
            ->select();

        $month_active_new1 = array();
        $month_active_new2 = array();
        if (date('Ym', $start_time) != date('Ym', $start_time + 29 * 24 * 3600)) {
            $month_active_new1 = M('player_login_logs' . date('Ym', $start_time))
                ->field('count(*) as count')
                ->where($map_new)
                ->group('uid')
                ->having('count >= 7')
                ->cache(true)
                ->select();
            if (date('m', $start_time + 29 * 24 * 3600) - date('m', $start_time) == 2) {
                $month_active_new2 = M('player_login_logs' . date('Y', $start_time) . '02')
                    ->field('count(*) as count')
                    ->where($map_new)
                    ->group('uid')
                    ->having('count >= 7')
                    ->cache(true)
                    ->select();
            }

        }


        $month_active = count($month_active);
        $month_active_new = count($month_active_new) + count($month_active_new1) + count($month_active_new2);

        $this->assign('month_active', $month_active + $month_active_new);
        $this->assign('week_active', $week_active + $week_active_new);
        $this->assign('day_active', $day_active['count'] + $day_active_new['count']);
        $this->assign('channel_name', $channel_name);
        $this->assign('game_name', $game_name);
        $this->assign('time', $time);
        $this->assign('channel_list', get_channel_list(I('channel'), I('channel_type')));
        $this->assign('game_list', get_game_list(I('appid'), 1, 'all', 'all', 'all', 'all', '', 1));
        $this->selected_channel_type = I('channel_type');
        $this->channel_type = C('channel_type');
        $this->display();
    }


    public function today_pay()
    {
        $post_data = $_REQUEST;
        $map = array();
        $channel_name = '--';
        $heji = array();
        $map_old = array();
        $heji_old = array();

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map_old['gameid'] = array('in', $game_role);
        } else {
            if ($this->appids) {
                $map['appid'] = array('in', implode(',', $this->appids));

            } else {
                $map['appid'] = array('in', '');

            }
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map['channel'] = array('in', $channel_role);
            $map_old['channel'] = array('in', $channel_role);
        }

        if (!empty($post_data['cid'])) {
            $map['cid'] = $post_data['cid'];
            $map['channel'] = $post_data['cid'];
            $map_old['channel'] = $post_data['cid'];
            $channel_name = M('Channel')->where(array('id' => $post_data['cid']))->getfield('name');
        }
        if (!empty($post_data['appid'])) {
            $map['appid'] = $post_data['appid'];
            $map_old['gameid'] = $post_data['appid'];
        }

        $today_day = strtotime(date('Y-m-d', time()));


        $map['create_time'] = array(array('egt', $today_day), array('lt', $today_day + 3600 * 24));
        $map_old['time'] = array(array('egt', $today_day), array('lt', $today_day + 3600 * 24));
        $map_old['pay_to_time'] = array(array('egt', $today_day), array('lt', $today_day + 3600 * 24));

//		$active_users = M('player_login_logs'.date('Ym',time()))
//			->field("count(distinct(uid)) as count,count(distinct(machine_code)) as machine_count,appid")
//			->where($map)
//			->group('appid')
//			->cache(true)
//			->select();

        $sub_query = M('player_login_logs' . date('Ym', $today_day))
            ->where($map)
            ->group('appid,uid')
            ->buildSql();
        $model = new \Think\Model;

        $active_users = $model->table($sub_query . ' a')->field('count(*) as count ,count(distinct(machine_code)) as machine_count,appid')->group('a.appid')->cache(true)->select();

        //重新组装数据

        $activer_users_info = array();

        if (is_array($active_users)) {
            foreach ($active_users as $v) {
                $activer_users_info[$v['appid']] = $v;
                $heji['active_user'] = $heji['active_user'] + $v['count'];
                $heji['active_machine_count'] = $heji['active_machine_count'] + $v['machine_count'];
            }
        }
        unset($active_users);

        $pay_info = $this->Inpour
            ->field("count(*) as pay_counts,count(distinct(uid)) as uid_counts,sum(money) as money,appid")
            ->where(array_merge($map, array('status' => 1)))
            ->group('appid')
            ->cache(true)
            ->select();

        $pay_info_info = array();

        if (is_array($pay_info)) {
            foreach ($pay_info as $v) {
                $pay_info_info[$v['appid']] = $v;
                $heji['pay_counts'] = $heji['pay_counts'] + $v['pay_counts'];
                $heji['pay_number'] = $heji['pay_number'] + $v['uid_counts'];
                $heji['pay_amount'] = $heji['pay_amount'] + $v['money'];

            }
        }
        unset($pay_info);

//		$new_users = $this->player_login_logs
//		->field("count(uid) as count,appid")
//		->where(array_merge($map,array('is_first_login'=>1)))
//		->group('appid')
//		->cache(true)
//		->select();

        $new_users_map = $map;
        $new_users_map['first_login_time'] = $new_users_map['create_time'];
        unset($new_users_map['create_time']);

        $new_users = M('player')
            ->field("count(*) as count,count(distinct(machine_code)) as valid_new_user,appid")
            ->where($new_users_map)
            ->group('appid')
            ->cache(true)
            ->select();

        $new_users_info = array();

        if (is_array($new_users)) {
            foreach ($new_users as $v) {
                $new_users_info[$v['appid']] = $v;
                $heji['new_user'] = $heji['new_user'] + $v['count'];
                $heji['valid_new_user'] = $heji['valid_new_user'] + $v['valid_new_user'];
            }
        }
        unset($new_users);

        $login_log_model = M('login_log', null, C('DB_OLDSDK_CONFIG'));

//		$active_users_old = $login_log_model
//			->field("count(distinct(username)) as count,count(distinct(device_id)) as machine_count, gameid")
//			->where($map_old)
//			->group("gameid")
//			->cache(true)
//			->select();

        $sub_query = $login_log_model
            ->where($map_old)
            ->group("gameid,username")
            ->buildSql();


        $active_users_old = $login_log_model->table($sub_query . ' a')->field('count(*) as count ,count(distinct(device_id)) as machine_count,gameid')->group('a.gameid')->cache(true)->select();


        $pay_model = M('pay', 'syo_', C('DB_OLDSDK_CONFIG'));

        $pay_info_old = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money,gameid")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->group("gameid")
            ->cache(true)
            ->select();

        $newplayer_model = M('newplayer', null, C('DB_OLDSDK_CONFIG'));

        $new_users_old = $newplayer_model
            ->field("count(username) as count, count(distinct(device_id)) as valid_new_user ,gameid")
            ->where($map_old)
            ->group("gameid")
            ->cache(true)
            ->select();

        $active_users_old_info = array();
        if (is_array($active_users_old)) {
            foreach ($active_users_old as $active_user) {
                $active_users_old_info[$active_user['gameid']] = $active_user;
                $heji_old['active_user'] = $heji_old['active_user'] + $active_user['count'];
                $heji_old['active_machine_count'] = $heji_old['active_machine_count'] + $active_user['machine_count'];
            }

        }

        unset($active_users_old);

        $pay_info_old_info = array();
        if (is_array($pay_info_old)) {
            foreach ($pay_info_old as $v) {
                $pay_info_old_info[$v['gameid']] = $v;
                $heji_old['pay_counts'] = $heji_old['pay_counts'] + $v['pay_counts'];
                $heji_old['pay_number'] = $heji_old['pay_number'] + $v['uid_counts'];
                $heji_old['pay_amount'] = $heji_old['pay_amount'] + $v['money'];
            }
        }
        unset($pay_info_old);

        $new_users_old_info = array();
        if (is_array($new_users_old)) {
            foreach ($new_users_old as $v) {
                $new_users_old_info[$v['gameid']] = $v;
                $heji_old['new_user'] = $heji_old['new_user'] + $v['count'];
                $heji_old['valid_new_user'] = $heji_old['valid_new_user'] + $v['valid_new_user'];
            }
        }
        unset($new_users_old);


        $game_map = array('status' => 1);
        if ($map['appid']) {
            $game_map['id'] = $map['appid'];
        }

        $games = M('game')->field('id,game_name')->where($game_map)->select();

        $list = array();
        $games_count = count($games);
        foreach ($games as $game) {
            $item = array(
                'game_name' => $game['game_name'],
                'active_user' => (isset($activer_users_info[$game['id']]['count']) ? $activer_users_info[$game['id']]['count'] : 0) + (isset($active_users_old_info[$game['id']]['count']) ? $active_users_old_info[$game['id']]['count'] : 0),
                'active_machine_count' => (isset($activer_users_info[$game['id']]['machine_count']) ? $activer_users_info[$game['id']]['machine_count'] : 0) + (isset($active_users_old_info[$game['id']]['machine_count']) ? $active_users_old_info[$game['id']]['machine_count'] : 0),
                'pay_counts' => (isset($pay_info_info[$game['id']]['pay_counts']) ? $pay_info_info[$game['id']]['pay_counts'] : 0) + (isset($pay_info_old_info[$game['id']]['pay_counts']) ? $pay_info_old_info[$game['id']]['pay_counts'] : 0),
                'pay_number' => (isset($pay_info_info[$game['id']]['uid_counts']) ? $pay_info_info[$game['id']]['uid_counts'] : 0) + (isset($pay_info_old_info[$game['id']]['uid_counts']) ? $pay_info_old_info[$game['id']]['uid_counts'] : 0),
                'pay_amount' => (isset($pay_info_info[$game['id']]['money']) ? $pay_info_info[$game['id']]['money'] : '0.00') + (isset($pay_info_old_info[$game['id']]['money']) ? $pay_info_old_info[$game['id']]['money'] : '0.00'),
                //'active_arpu'=>round(($pay_info_info[$game['id']]['money']+$pay_info_old_info[$game['id']]['money'])/($active_users_old_info[$game['id']]['count']+$active_users_old_info[$game['id']]['count']),2),
                'new_user' => (isset($new_users_info[$game['id']]['count']) ? $new_users_info[$game['id']]['count'] : 0) + (isset($new_users_old_info[$game['id']]['count']) ? $new_users_old_info[$game['id']]['count'] : 0),
                'valid_new_user' => (isset($new_users_info[$game['id']]['valid_new_user']) ? $new_users_info[$game['id']]['valid_new_user'] : 0) + (isset($new_users_old_info[$game['id']]['valid_new_user']) ? $new_users_old_info[$game['id']]['valid_new_user'] : 0),
            );
            if (!($item['active_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0 && $item['new_user'] == 0) || $games_count == 1) {
                $item['active_arpu'] = round($item['pay_amount'] / $item['active_user'], 2);
                $item['active_machine_arpu'] = round($item['pay_amount'] / $item['active_machine_count'], 2);
                $item['pay_arpu'] = round($item['pay_amount'] / $item['pay_number'], 2);
                $list[] = $item;
            }
        }

        foreach ($list as $k => $v) {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $list);

        $this->assign('heji_old', $heji_old);
        $this->assign('heji', $heji);
        $this->assign('channel_name', $channel_name);
        $this->assign('list', $list);
        $this->assign('channel_list', get_channel_list(I('cid'), I('channel_type')));
        $this->assign('game_list', get_game_list(I('appid'), 1, 'all', 'all', 'all', 'all', '', 1));
        $this->selected_channel_type = I('channel_type');
        $this->channel_type = C('channel_type');

        $this->display();

    }

    public function today_pay_by_channel()
    {
        $post_data = $_REQUEST;
        $map = array();
        $game_name = '--';
        $heji = array();
        $heji_old = array();
        $map_old = array();

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map_old['gameid'] = array('in', $game_role);
        } else {
            if ($this->appids) {
                $map['appid'] = array('in', implode(',', $this->appids));

            } else {
                $map['appid'] = array('in', '');

            }
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map['channel'] = array('in', $channel_role);
            $map_old['channel'] = array('in', $channel_role);
        }

        if (!empty($post_data['cid'])) {
            $map['cid'] = $post_data['cid'];
            $map['channel'] = $post_data['cid'];
            $map_old['channel'] = $post_data['cid'];
        }
        if (!empty($post_data['appid'])) {
            $map['appid'] = $post_data['appid'];
            $map_old['gameid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id' => $post_data['appid']))->getfield('game_name');
        }


        $today_day = strtotime(date('Y-m-d', time()));


        $map['create_time'] = array(array('egt', $today_day), array('lt', $today_day + 3600 * 24));
        $map_old['time'] = array(array('egt', $today_day), array('lt', $today_day + 3600 * 24));
        $map_old['pay_to_time'] = array(array('egt', $today_day), array('lt', $today_day + 3600 * 24));


//		$active_users = M('player_login_logs'.date('Ym',time()))
//			->field("count(distinct(uid)) as count,count(distinct(machine_code)) as machine_count,channel")
//			->where($map)
//			->group('channel')
//			->cache(true)
//			->select();

        $sub_query = M('player_login_logs' . date('Ym', $today_day))
            ->where($map)
            ->group('channel,uid')
            ->buildSql();
        $model = new \Think\Model;

        $active_users = $model->table($sub_query . ' a')->field('count(*) as count ,count(distinct(machine_code)) as machine_count,channel')->group('a.channel')->cache(true)->select();

        //重新组装数据

        $activer_users_info = array();

        if (is_array($active_users)) {
            foreach ($active_users as $v) {
                $activer_users_info[$v['channel']] = $v;
                $heji['active_user'] = $heji['active_user'] + $v['count'];
                $heji['active_machine_count'] = $heji['active_machine_count'] + $v['machine_count'];
            }
        }
        unset($active_users);


        $pay_info = $this->Inpour
            ->field("count(*) as pay_counts,count(distinct(uid)) as uid_counts,sum(money) as money,cid")
            ->where(array_merge($map, array('status' => 1)))
            ->group('cid')
            ->cache(true)
            ->select();

        $pay_info_info = array();

        if (is_array($pay_info)) {
            foreach ($pay_info as $v) {
                $pay_info_info[$v['cid']] = $v;
                $heji['pay_counts'] = $heji['pay_counts'] + $v['pay_counts'];
                $heji['pay_number'] = $heji['pay_number'] + $v['uid_counts'];
                $heji['pay_amount'] = $heji['pay_amount'] + $v['money'];

            }
        }
        unset($pay_info);

//		$new_users = $this->player_login_logs
//		->field("count(uid) as count,channel")
//		->where(array_merge($map,array('is_first_login'=>1)))
//		->group('channel')
//		->cache(true)
//		->select();

        $new_users_map = $map;
        $new_users_map['first_login_time'] = $new_users_map['create_time'];
        unset($new_users_map['create_time']);

        $new_users = M('player')
            ->field("count(*) as count,count(distinct(machine_code)) as valid_new_user,channel")
            ->where($new_users_map)
            ->group('channel')
            ->cache(true)
            ->select();

        $new_users_info = array();

        if (is_array($new_users)) {
            foreach ($new_users as $v) {
                $new_users_info[$v['channel']] = $v;
                $heji['new_user'] = $heji['new_user'] + $v['count'];
                $heji['valid_new_user'] = $heji['valid_new_user'] + $v['valid_new_user'];
            }
        }
        unset($new_users);

        $login_log_model = M('login_log', null, C('DB_OLDSDK_CONFIG'));

//		$active_users_old = $login_log_model
//			->field("count(distinct(username)) as count, count(distinct(device_id)) as machine_count ,channel")
//			->where($map_old)
//			->group("channel")
//			->cache(true)
//			->select();

        $sub_query = $login_log_model
            ->where($map_old)
            ->group("channel,username")
            ->buildSql();


        $active_users_old = $login_log_model->table($sub_query . ' a')->field('count(*) as count ,count(distinct(device_id)) as machine_count,channel')->group('a.channel')->cache(true)->select();

        $pay_model = M('pay', 'syo_', C('DB_OLDSDK_CONFIG'));

        $pay_info_old = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money,channel")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->group("channel")
            ->cache(true)
            ->select();

        $newplayer_model = M('newplayer', null, C('DB_OLDSDK_CONFIG'));

        $new_users_old = $newplayer_model
            ->field("count(username) as count, count(distinct(device_id)) as valid_new_user, channel")
            ->where($map_old)
            ->group("channel")
            ->cache(true)
            ->select();

        $active_users_old_info = array();
        if (is_array($active_users_old)) {
            foreach ($active_users_old as $active_user) {
                $active_users_old_info[$active_user['channel']] = $active_user;
                $heji_old['active_user'] = $heji_old['active_user'] + $active_user['count'];
                $heji_old['active_machine_count'] = $heji_old['active_machine_count'] + $active_user['machine_count'];
            }

        }

        unset($active_users_old);

        $pay_info_old_info = array();
        if (is_array($pay_info_old)) {
            foreach ($pay_info_old as $v) {
                $pay_info_old_info[$v['channel']] = $v;
                $heji_old['pay_counts'] = $heji_old['pay_counts'] + $v['pay_counts'];
                $heji_old['pay_number'] = $heji_old['pay_number'] + $v['uid_counts'];
                $heji_old['pay_amount'] = $heji_old['pay_amount'] + $v['money'];
            }
        }
        unset($pay_info_old);

        $new_users_old_info = array();
        if (is_array($new_users_old)) {
            foreach ($new_users_old as $v) {
                $new_users_old_info[$v['channel']] = $v;
                $heji_old['new_user'] = $heji_old['new_user'] + $v['count'];
                $heji_old['valid_new_user'] = $heji_old['valid_new_user'] + $v['valid_new_user'];
            }
        }
        unset($new_users_old);


        $channel_map = array('status' => 1);
        if ($map['cid']) {
            $channel_map['id'] = $map['cid'];
        }

        $channels = M('Channel')->field('id,name')->where($channel_map)->select();

        $list = array();
        $channel_count = count($channels);
        foreach ($channels as $channel) {
            $item = array(
                'channel_name' => $channel['name'],
                'active_user' => (isset($activer_users_info[$channel['id']]['count']) ? $activer_users_info[$channel['id']]['count'] : 0) + (isset($active_users_old_info[$channel['id']]['count']) ? $active_users_old_info[$channel['id']]['count'] : 0),
                'active_machine_count' => (isset($activer_users_info[$channel['id']]['machine_count']) ? $activer_users_info[$channel['id']]['machine_count'] : 0) + (isset($active_users_old_info[$channel['id']]['machine_count']) ? $active_users_old_info[$channel['id']]['machine_count'] : 0),
                'pay_counts' => (isset($pay_info_info[$channel['id']]['pay_counts']) ? $pay_info_info[$channel['id']]['pay_counts'] : 0) + (isset($pay_info_old_info[$channel['id']]['pay_counts']) ? $pay_info_old_info[$channel['id']]['pay_counts'] : 0),
                'pay_number' => (isset($pay_info_info[$channel['id']]['uid_counts']) ? $pay_info_info[$channel['id']]['uid_counts'] : 0) + (isset($pay_info_old_info[$channel['id']]['uid_counts']) ? $pay_info_old_info[$channel['id']]['uid_counts'] : 0),
                'pay_amount' => (isset($pay_info_info[$channel['id']]['money']) ? $pay_info_info[$channel['id']]['money'] : '0.00') + (isset($pay_info_old_info[$channel['id']]['money']) ? $pay_info_old_info[$channel['id']]['money'] : '0.00'),
                //'active_arpu'=>round(($pay_info_info[$channel['id']]['money']+$pay_info_old_info[$channel['id']]['money'])/($active_users_old_info[$channel['id']]['count']+$active_users_old_info[$channel['id']]['count']),2),
                'new_user' => (isset($new_users_info[$channel['id']]['count']) ? $new_users_info[$channel['id']]['count'] : 0) + (isset($new_users_old_info[$channel['id']]['count']) ? $new_users_old_info[$channel['id']]['count'] : 0),
                'valid_new_user' => (isset($new_users_info[$channel['id']]['valid_new_user']) ? $new_users_info[$channel['id']]['valid_new_user'] : 0) + (isset($new_users_old_info[$channel['id']]['valid_new_user']) ? $new_users_old_info[$channel['id']]['valid_new_user'] : 0),
            );

            if (!($item['active_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0 && $item['new_user'] == 0) || $channel_count == 1) {
                $item['active_arpu'] = round($item['pay_amount'] / $item['active_user'], 2);
                $item['active_machine_arpu'] = round($item['pay_amount'] / $item['active_machine_count'], 2);
                $item['pay_arpu'] = round($item['pay_amount'] / $item['pay_number'], 2);
                $list[] = $item;
            }


        }

        foreach ($list as $k => $v) {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $list);

        $this->assign('heji_old', $heji_old);
        $this->assign('heji', $heji);
        $this->assign('game_name', $game_name);
        $this->assign('list', $list);
        $this->assign('channel_list', get_channel_list(I('cid'), I('channel_type')));
        $this->assign('game_list', get_game_list(I('appid'), 1, 'all', 'all', 'all', 'all', '', 1));
        $this->selected_channel_type = I('channel_type');
        $this->channel_type = C('channel_type');
        $this->display();
    }

    public function pay_statistics_by_channel()
    {
        $post_data = $_REQUEST;

        //日期最大限制
        $max = date('Y-m-d', time() - 3600 * 24);

        $start_time = $post_data['start_time'] ? $post_data['start_time'] : date('Y-m-d', strtotime('-1 week'));
        $end_time = $post_data['end_time'] ? $post_data['end_time'] : $max;
        $game_name = '--';

        if (strtotime($end_time) - strtotime($start_time) >= 180 * 3600 * 24) {
            $this->error('不能查询超过180天以上');
        }

        $map = array();
        $map_old = array();
        $heji_old = array();

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map_old['gameid'] = array('in', $game_role);
        } else {
            if ($this->appids) {
                $map['appid'] = array('in', implode(',', $this->appids));

            } else {
                $map['appid'] = array('in', '');

            }
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map_old['channel'] = array('in', $channel_role);
        }

        if (!empty($post_data['cid'])) {
            $map['cid'] = $post_data['cid'];
            $map_old['channel'] = $post_data['cid'];
        }
        if (!empty($post_data['appid'])) {
            $map['appid'] = $post_data['appid'];
            $map_old['gameid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id' => $post_data['appid']))->getfield('game_name');
        }


        if (!empty($start_time) && !empty($end_time)) {
            $map['time'] = array(array('egt', $start_time), array('elt', $end_time));
            $map_old['time'] = array(array('egt', strtotime($start_time . ' 00:00:00')), array('elt', strtotime($end_time . ' 23:59:59')));
            $map_old['pay_to_time'] = array(array('egt', strtotime($start_time . ' 00:00:00')), array('elt', strtotime($end_time . ' 23:59:59')));
        }


        $heji = M('pay_by_day')->
        field('sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user),sum(valid_new_user)')->
        where($map)->
        cache(true)->
        find();

        $list = M('pay_by_day')->
        field('cid,sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user),sum(valid_new_user)')->
        where($map)->
        group('cid')->
        cache(true)->
        select();


        $login_log_model = M('login_log', null, C('DB_OLDSDK_CONFIG'));

        $active_users = $login_log_model
            ->field("count(distinct(username)) as count,channel")
            ->where($map_old)
            ->group("channel")
            ->cache(true)
            ->select();


//		$sub_query = $login_log_model
//			->where($map_old)
//			->group("channel,username")
//			->buildSql();
//
//
//		$active_users = $login_log_model->table($sub_query.' a')->field('count(*) as count ,count(distinct(device_id)) as machine_count,channel')->group('a.channel')->cache(true)->select();


        $active_users_heji = $login_log_model
            ->field("count(distinct(username)) as count")
            ->where($map_old)
            ->cache(true)
            ->find();

//		$sub_query = $login_log_model
//			->where($map_old)
//			->group("username")
//			->buildSql();
//
//		$active_users_heji = $login_log_model->table($sub_query.' a')->field('count(*) as count ,count(distinct(device_id)) as machine_count,channel')->cache(true)->find();

        $pay_model = M('pay', 'syo_', C('DB_OLDSDK_CONFIG'));

        $pay_info = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money,channel")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->group("channel")
            ->cache(true)
            ->select();

        $pay_info_heji = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->cache(true)
            ->find();


        $newplayer_model = M('newplayer', null, C('DB_OLDSDK_CONFIG'));

        $new_users = $newplayer_model
            ->field("count(username) as count, count(distinct(device_id)) as valid_new_user,channel")
            ->where($map_old)
            ->group("channel")
            ->cache(true)
            ->select();

        $new_users_heji = $newplayer_model
            ->field("count(username) as count,count(distinct(device_id)) as valid_new_user")
            ->where($map_old)
            ->cache(true)
            ->find();


        $list_info = array();

        if (is_array($list)) {
            foreach ($list as $v) {
                $list_info[$v['cid']] = $v;
            }
        }
        unset($list);

        $active_users_info = array();
        if (is_array($active_users)) {
            foreach ($active_users as $active_user) {
                $active_users_info[$active_user['channel']] = $active_user;

            }

        }
        $heji_old['active_user'] = $active_users_heji['count'];


        unset($active_users);

        $pay_info_info = array();
        if (is_array($pay_info)) {
            foreach ($pay_info as $v) {
                $pay_info_info[$v['channel']] = $v;

            }
        }

        $heji_old['pay_counts'] = $pay_info_heji['pay_counts'];
        $heji_old['pay_number'] = $pay_info_heji['uid_counts'];
        $heji_old['pay_amount'] = $pay_info_heji['money'];
        unset($pay_info);

        $new_users_info = array();
        if (is_array($new_users)) {
            foreach ($new_users as $v) {
                $new_users_info[$v['channel']] = $v;

            }
        }
        $heji_old['new_user'] = $new_users_heji['count'];
        $heji_old['valid_new_user'] = $new_users_heji['valid_new_user'];
        unset($new_users);


        $channel_map = array('status' => 1);
        if ($map['cid']) {
            $channel_map['id'] = $map['cid'];
        }

        $channels = M('Channel')->field('id,name')->where($channel_map)->select();
        $channel_count = count($channels);
        $result = array();
        foreach ($channels as $channel) {
            $item = array(
                'channel_name' => $channel['name'],
                'active_user' => (isset($list_info[$channel['id']]['sum(active_user)']) ? $list_info[$channel['id']]['sum(active_user)'] : 0) + (isset($active_users_info[$channel['id']]['count']) ? $active_users_info[$channel['id']]['count'] : 0),
                //'active_machine_count'=>(isset($list_info[$channel['id']]['sum(active_machine_count)'])?$list_info[$channel['id']]['sum(active_machine_count)']:0)+(isset($active_users_info[$channel['id']]['machine_count'])?$active_users_info[$channel['id']]['machine_count']:0),
                'pay_counts' => (isset($list_info[$channel['id']]['sum(pay_counts)']) ? $list_info[$channel['id']]['sum(pay_counts)'] : 0) + (isset($pay_info_info[$channel['id']]['pay_counts']) ? $pay_info_info[$channel['id']]['pay_counts'] : 0),
                'pay_number' => (isset($list_info[$channel['id']]['sum(pay_number)']) ? $list_info[$channel['id']]['sum(pay_number)'] : 0) + (isset($pay_info_info[$channel['id']]['uid_counts']) ? $pay_info_info[$channel['id']]['uid_counts'] : 0),
                'pay_amount' => (isset($list_info[$channel['id']]['sum(pay_amount)']) ? $list_info[$channel['id']]['sum(pay_amount)'] : '0.00') + (isset($pay_info_info[$channel['id']]['money']) ? $pay_info_info[$channel['id']]['money'] : '0.00'),
                //	'active_arpu'=>round(($list_info[$channel['id']]['sum(pay_amount)']+$pay_info_info[$channel['id']]['money'])/($list_info[$channel['id']]['sum(active_user)']+$active_users_info[$channel['id']]['count']),2),
                'new_user' => (isset($list_info[$channel['id']]['sum(new_user)']) ? $list_info[$channel['id']]['sum(new_user)'] : 0) + (isset($new_users_info[$channel['id']]['count']) ? $new_users_info[$channel['id']]['count'] : 0),
                'valid_new_user' => (isset($list_info[$channel['id']]['sum(valid_new_user)']) ? $list_info[$channel['id']]['sum(valid_new_user)'] : 0) + (isset($new_users_info[$channel['id']]['valid_new_user']) ? $new_users_info[$channel['id']]['valid_new_user'] : 0),
            );
            if (!($item['active_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0 && $item['new_user'] == 0) || $channel_count == 1) {
                $item['active_arpu'] = round($item['pay_amount'] / $item['active_user'], 2);
                //	$item['active_machine_arpu'] = round($item['pay_amount']/$item['active_machine_count'],2);
                $item['pay_arpu'] = round($item['pay_amount'] / $item['pay_number'], 2);

                $result[] = $item;
            }

        }


        foreach ($result as $k => $v) {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $result);

        $this->assign('heji_old', $heji_old);
        $this->assign('heji', $heji);
        $this->assign('game_name', $game_name);
        $this->assign('result', $result);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('channel_list', get_channel_list(I('cid'), I('channel_type')));
        $this->assign('game_list', get_game_list(I('appid'), 1, 'all', 'all', 'all', 'all', '', 1));
        $this->assign('max', $max);
        $this->selected_channel_type = I('channel_type');
        $this->channel_type = C('channel_type');
        $this->display();
    }

    public function pay_statistics_by_game()
    {
        $post_data = $_REQUEST;

        //日期最大限制
        $max = date('Y-m-d', time() - 3600 * 24);

        $start_time = $post_data['start_time'] ? $post_data['start_time'] : date('Y-m-d', strtotime('-1 week'));
        $end_time = $post_data['end_time'] ? $post_data['end_time'] : $max;

        if (strtotime($end_time) - strtotime($start_time) >= 180 * 3600 * 24) {
            $this->error('不能查询超过180天以上');
        }

        $channel_name = '--';

        $map = array();
        $map_old = array();
        $heji_old = array();

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map_old['gameid'] = array('in', $game_role);
        } else {
            if ($this->appids) {
                $map['appid'] = array('in', implode(',', $this->appids));

            } else {
                $map['appid'] = array('in', '');

            }
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map_old['channel'] = array('in', $channel_role);
        }

        if (!empty($post_data['cid'])) {
            $map['cid'] = $post_data['cid'];
            $map_old['channel'] = $post_data['cid'];
            $channel_name = M('channel')->where(array('id' => $post_data['cid']))->getfield('name');
        }
        if (!empty($post_data['appid'])) {
            $map['appid'] = $post_data['appid'];
            $map_old['gameid'] = $post_data['appid'];
        }


        if (!empty($start_time) && !empty($end_time)) {
            $map['time'] = array(array('egt', $start_time), array('elt', $end_time));
            $map_old['time'] = array(array('egt', strtotime($start_time . ' 00:00:00')), array('elt', strtotime($end_time . ' 23:59:59')));
            $map_old['pay_to_time'] = array(array('egt', strtotime($start_time . ' 00:00:00')), array('elt', strtotime($end_time . ' 23:59:59')));
        }


        $heji = M('pay_by_day')->
        field('sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user),sum(valid_new_user)')->
        where($map)->
        cache(true)->
        find();


        $list = M('pay_by_day')->
        field('appid,sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user),sum(valid_new_user)')->
        where($map)->
        group('appid')->
        cache(true)->
        select();

        $login_log_model = M('login_log', null, C('DB_OLDSDK_CONFIG'));

        $active_users = $login_log_model
            ->field("count(distinct(username)) as count, gameid")
            ->where($map_old)
            ->group("gameid")
            ->cache(true)
            ->select();

//		$sub_query = $login_log_model
//			->where($map_old)
//			->group("gameid,username")
//			->buildSql();
//
//
//		$active_users = $login_log_model->table($sub_query.' a')->field('count(*) as count ,count(distinct(device_id)) as machine_count,gameid')->group('a.gameid')->cache(true)->select();

        $active_users_heji = $login_log_model
            ->field("count(distinct(username)) as count")
            ->where($map_old)
            ->cache(true)
            ->find();

//		$sub_query = $login_log_model
//			->where($map_old)
//			->group("username")
//			->buildSql();
//
//		$active_users_heji = $login_log_model->table($sub_query.' a')->field('count(*) as count ,count(distinct(device_id)) as machine_count,gameid')->cache(true)->select();


        $pay_model = M('pay', 'syo_', C('DB_OLDSDK_CONFIG'));

        $pay_info = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money,gameid")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->group("gameid")
            ->cache(true)
            ->select();

        $pay_info_heji = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->cache(true)
            ->find();


        $newplayer_model = M('newplayer', null, C('DB_OLDSDK_CONFIG'));

        $new_users = $newplayer_model
            ->field("count(username) as count,count(distinct(device_id)) as valid_new_user,gameid")
            ->where($map_old)
            ->group("gameid")
            ->cache(true)
            ->select();

        $new_users_heji = $newplayer_model
            ->field("count(username) as count,count(distinct(device_id)) as valid_new_user")
            ->where($map_old)
            ->cache(true)
            ->find();


        $list_info = array();

        if (is_array($list)) {
            foreach ($list as $v) {
                $list_info[$v['appid']] = $v;
            }
        }
        unset($list);


        $active_users_info = array();
        if (is_array($active_users)) {
            foreach ($active_users as $active_user) {
                $active_users_info[$active_user['gameid']] = $active_user;

            }

        }
        $heji_old['active_user'] = $active_users_heji['count'];
        unset($active_users);

        $pay_info_info = array();
        if (is_array($pay_info)) {
            foreach ($pay_info as $v) {
                $pay_info_info[$v['gameid']] = $v;
            }
        }
        $heji_old['pay_counts'] = $pay_info_heji['pay_counts'];
        $heji_old['pay_number'] = $pay_info_heji['uid_counts'];
        $heji_old['pay_amount'] = $pay_info_heji['money'];
        unset($pay_info);

        $new_users_info = array();
        if (is_array($new_users)) {
            foreach ($new_users as $v) {
                $new_users_info[$v['gameid']] = $v;
            }
        }
        $heji_old['new_user'] = $new_users_heji['count'];
        $heji_old['valid_new_user'] = $new_users_heji['valid_new_user'];
        unset($new_users);


        $game_map = array('status' => 1);
        if ($map['appid']) {
            $game_map['id'] = $map['appid'];
        }

        $games = M('game')->field('id,game_name')->where($game_map)->select();

        $games_count = count($games);
        $result = array();
        foreach ($games as $game) {
            $item = array(
                'game_name' => $game['game_name'],
                'active_user' => (isset($list_info[$game['id']]['sum(active_user)']) ? $list_info[$game['id']]['sum(active_user)'] : 0) + (isset($active_users_info[$game['id']]['count']) ? $active_users_info[$game['id']]['count'] : 0),
                //'active_machine_count'=>(isset($list_info[$game['id']]['sum(active_machine_count)'])?$list_info[$game['id']]['sum(active_machine_count)']:0)+(isset($active_users_info[$game['id']]['machine_count'])?$active_users_info[$game['id']]['machine_count']:0),
                'pay_counts' => (isset($list_info[$game['id']]['sum(pay_counts)']) ? $list_info[$game['id']]['sum(pay_counts)'] : 0) + (isset($pay_info_info[$game['id']]['pay_counts']) ? $pay_info_info[$game['id']]['pay_counts'] : 0),
                'pay_number' => (isset($list_info[$game['id']]['sum(pay_number)']) ? $list_info[$game['id']]['sum(pay_number)'] : 0) + (isset($pay_info_info[$game['id']]['uid_counts']) ? $pay_info_info[$game['id']]['uid_counts'] : 0),
                'pay_amount' => (isset($list_info[$game['id']]['sum(pay_amount)']) ? $list_info[$game['id']]['sum(pay_amount)'] : '0.00') + (isset($pay_info_info[$game['id']]['money']) ? $pay_info_info[$game['id']]['money'] : '0.00'),
                //	'active_arpu'=>round(($list_info[$game['id']]['sum(pay_amount)']+$pay_info_info[$game['id']]['money'])/($list_info[$game['id']]['sum(active_user)']+$active_users_info[$game['id']]['count']),2),
                'new_user' => (isset($list_info[$game['id']]['sum(new_user)']) ? $list_info[$game['id']]['sum(new_user)'] : 0) + (isset($new_users_info[$game['id']]['count']) ? $new_users_info[$game['id']]['count'] : 0),
                'valid_new_user' => (isset($list_info[$game['id']]['sum(valid_new_user)']) ? $list_info[$game['id']]['sum(valid_new_user)'] : 0) + (isset($new_users_info[$game['id']]['valid_new_user']) ? $new_users_info[$game['id']]['valid_new_user'] : 0),
            );


            if (!($item['active_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0 && $item['new_user'] == 0) || $games_count == 1) {
                $item['active_arpu'] = round($item['pay_amount'] / $item['active_user'], 2);
                //	$item['active_machine_arpu'] = round($item['pay_amount']/$item['active_machine_count'],2);
                $item['pay_arpu'] = round($item['pay_amount'] / $item['pay_number'], 2);

                $result[] = $item;
            }

        }
        //die;

        foreach ($result as $k => $v) {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $result);

        $this->assign('heji', $heji);
        $this->assign('heji_old', $heji_old);
        $this->assign('channel_name', $channel_name);
        $this->assign('result', $result);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('channel_list', get_channel_list(I('cid'), I('channel_type')));
        $this->assign('game_list', get_game_list(I('appid'), 1, 'all', 'all', 'all', 'all', '', 1));
        $this->assign('max', $max);
        $this->selected_channel_type = I('channel_type');
        $this->channel_type = C('channel_type');
        $this->display();
    }

    public function today_pay_by_tg()
    {
        $post_data = $_REQUEST;
        $map = array();
        $game_name = '--';
        $heji = array();
        $heji_old = array();
        $map_old = array();

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map_old['gameid'] = array('in', $game_role);
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map['channel'] = array('in', $channel_role);
            $map_old['channel'] = array('in', $channel_role);
        } else {
            $channel_ids = M('channel')->where(array('status' => 1, 'type' => 2))->Getfield('id', true);
            $channel_ids = implode(',', $channel_ids);
            $map['cid'] = array('in', $channel_ids);
            $map['channel'] = array('in', $channel_ids);
            $map_old['channel'] = array('in', $channel_ids);
        }

        if (!empty($post_data['cid'])) {
            $map['cid'] = $post_data['cid'];
            $map['channel'] = $post_data['cid'];
            $map_old['channel'] = $post_data['cid'];
        }
        if (!empty($post_data['appid'])) {
            $map['appid'] = $post_data['appid'];
            $map_old['gameid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id' => $post_data['appid']))->getfield('game_name');
        }


        $today_day = strtotime(date('Y-m-d', time()));


        $map['create_time'] = array(array('egt', $today_day), array('lt', $today_day + 3600 * 24));
        $map_old['time'] = array(array('egt', $today_day), array('lt', $today_day + 3600 * 24));
        $map_old['pay_to_time'] = array(array('egt', $today_day), array('lt', $today_day + 3600 * 24));


        $active_users = M('player_login_logs' . date('Ym', time()))
            ->field("count(distinct(uid)) as count,channel")
            ->where($map)
            ->group('channel')
            ->select();

        //重新组装数据

        $activer_users_info = array();

        if (is_array($active_users)) {
            foreach ($active_users as $v) {
                $activer_users_info[$v['channel']] = $v;
                $heji['active_user'] = $heji['active_user'] + $v['count'];
            }
        }
        unset($active_users);


        $pay_info = $this->Inpour
            ->field("count(*) as pay_counts,count(distinct(uid)) as uid_counts,sum(money) as money,cid")
            ->where(array_merge($map, array('status' => 1)))
            ->group('cid')
            ->select();

        $pay_info_info = array();

        if (is_array($pay_info)) {
            foreach ($pay_info as $v) {
                $pay_info_info[$v['cid']] = $v;
                $heji['pay_counts'] = $heji['pay_counts'] + $v['pay_counts'];
                $heji['pay_number'] = $heji['pay_number'] + $v['uid_counts'];
                $heji['pay_amount'] = $heji['pay_amount'] + $v['money'];

            }
        }
        unset($pay_info);

//		$new_users = $this->player_login_logs
//		->field("count(uid) as count,channel")
//		->where(array_merge($map,array('is_first_login'=>1)))
//		->group('channel')
//		->select();

        $new_users_map = $map;
        $new_users_map['first_login_time'] = $new_users_map['create_time'];
        unset($new_users_map['create_time']);

        $new_users = M('player')
            ->field("count(*) as count,channel")
            ->where($new_users_map)
            ->group('channel')
            ->select();


        $new_users_info = array();

        if (is_array($new_users)) {
            foreach ($new_users as $v) {
                $new_users_info[$v['channel']] = $v;
                $heji['new_user'] = $heji['new_user'] + $v['count'];
            }
        }
        unset($new_users);

        $login_log_model = M('login_log', null, C('DB_OLDSDK_CONFIG'));

        $active_users_old = $login_log_model
            ->field("count(distinct(username)) as count, channel")
            ->where($map_old)
            ->group("channel")
            ->select();

        $pay_model = M('pay', 'syo_', C('DB_OLDSDK_CONFIG'));

        $pay_info_old = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money,channel")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->group("channel")
            ->select();

        $newplayer_model = M('newplayer', null, C('DB_OLDSDK_CONFIG'));

        $new_users_old = $newplayer_model
            ->field("count(username) as count, channel")
            ->where($map_old)
            ->group("channel")
            ->select();

        $active_users_old_info = array();
        if (is_array($active_users_old)) {
            foreach ($active_users_old as $active_user) {
                $active_users_old_info[$active_user['channel']] = $active_user;
                $heji_old['active_user'] = $heji_old['active_user'] + $active_user['count'];
            }

        }

        unset($active_users_old);

        $pay_info_old_info = array();
        if (is_array($pay_info_old)) {
            foreach ($pay_info_old as $v) {
                $pay_info_old_info[$v['channel']] = $v;
                $heji_old['pay_counts'] = $heji_old['pay_counts'] + $v['pay_counts'];
                $heji_old['pay_number'] = $heji_old['pay_number'] + $v['uid_counts'];
                $heji_old['pay_amount'] = $heji_old['pay_amount'] + $v['money'];
            }
        }
        unset($pay_info_old);

        $new_users_old_info = array();
        if (is_array($new_users_old)) {
            foreach ($new_users_old as $v) {
                $new_users_old_info[$v['channel']] = $v;
                $heji_old['new_user'] = $heji_old['new_user'] + $v['count'];
            }
        }
        unset($new_users_old);


        $channel_map = array('status' => 1, 'parent' => 0);
        if ($map['cid']) {
            $channel_map['id'] = $map['cid'];
        }

        $channels = M('Channel')->where($channel_map)->getfield('id,name,parent', true);

        $channel_map['parent'] = array('neq', 0);

        $child_channels = M('channel')->where($channel_map)->field('parent,id,name')->order('parent')->select();


        foreach ($child_channels as $v) {
            $channels[$v['parent']]['child'][] = $v;
        }

        $list = array();
        foreach ($channels as $channel) {
            $item = array();
            if (is_array($channel['child'])) {
                foreach ($channel['child'] as $child_channel) {
                    $child_item = array(
                        'channel_name' => $child_channel['name'],
                        'active_user' => (isset($activer_users_info[$child_channel['id']]['count']) ? $activer_users_info[$child_channel['id']]['count'] : 0) + (isset($active_users_old_info[$child_channel['id']]['count']) ? $active_users_old_info[$child_channel['id']]['count'] : 0),
                        'pay_counts' => (isset($pay_info_info[$child_channel['id']]['pay_counts']) ? $pay_info_info[$child_channel['id']]['pay_counts'] : 0) + (isset($pay_info_old_info[$child_channel['id']]['pay_counts']) ? $pay_info_old_info[$child_channel['id']]['pay_counts'] : 0),
                        'pay_number' => (isset($pay_info_info[$child_channel['id']]['uid_counts']) ? $pay_info_info[$child_channel['id']]['uid_counts'] : 0) + (isset($pay_info_old_info[$child_channel['id']]['uid_counts']) ? $pay_info_old_info[$child_channel['id']]['uid_counts'] : 0),
                        'pay_amount' => (isset($pay_info_info[$child_channel['id']]['money']) ? $pay_info_info[$child_channel['id']]['money'] : '0.00') + (isset($pay_info_old_info[$child_channel['id']]['money']) ? $pay_info_old_info[$child_channel['id']]['money'] : '0.00'),
                        //'active_arpu'=>round(($pay_info_info[$channel['id']]['money']+$pay_info_old_info[$channel['id']]['money'])/($active_users_old_info[$channel['id']]['count']+$active_users_old_info[$channel['id']]['count']),2),
                        'new_user' => (isset($new_users_info[$child_channel['id']]['count']) ? $new_users_info[$child_channel['id']]['count'] : 0) + (isset($new_users_old_info[$child_channel['id']]['count']) ? $new_users_old_info[$child_channel['id']]['count'] : 0),
                    );

                    $child_item['active_arpu'] = round($child_item['pay_amount'] / $child_item['active_user'], 2);
                    $child_item['pay_arpu'] = round($child_item['pay_amount'] / $child_item['pay_number'], 2);

                    $item['active_user'] = $item['active_user'] + $child_item['active_user'];
                    $item['pay_counts'] = $item['pay_counts'] + $child_item['pay_counts'];
                    $item['pay_number'] = $item['pay_number'] + $child_item['pay_number'];
                    $item['pay_amount'] = $item['pay_amount'] + $child_item['pay_amount'];
                    $item['new_user'] = $item['new_user'] + $child_item['new_user'];

                    $item['child'][] = $child_item;
                }
                $pay_amount = array();
                foreach ($item['child'] as $k => $v) {
                    $pay_amount[$k] = $v['pay_amount'];
                }
                array_multisort($pay_amount, SORT_DESC, $item['child']);

            }

            $item['id'] = $channel['id'];
            $item['channel_name'] = $channel['name'];
            $item['active_user'] += (isset($activer_users_info[$channel['id']]['count']) ? $activer_users_info[$channel['id']]['count'] : 0) + (isset($active_users_old_info[$channel['id']]['count']) ? $active_users_old_info[$channel['id']]['count'] : 0);
            $item['pay_counts'] += (isset($pay_info_info[$channel['id']]['pay_counts']) ? $pay_info_info[$channel['id']]['pay_counts'] : 0) + (isset($pay_info_old_info[$channel['id']]['pay_counts']) ? $pay_info_old_info[$channel['id']]['pay_counts'] : 0);
            $item['pay_number'] += (isset($pay_info_info[$channel['id']]['uid_counts']) ? $pay_info_info[$channel['id']]['uid_counts'] : 0) + (isset($pay_info_old_info[$channel['id']]['uid_counts']) ? $pay_info_old_info[$channel['id']]['uid_counts'] : 0);
            $item['pay_amount'] += (isset($pay_info_info[$channel['id']]['money']) ? $pay_info_info[$channel['id']]['money'] : '0.00') + (isset($pay_info_old_info[$channel['id']]['money']) ? $pay_info_old_info[$channel['id']]['money'] : '0.00');
            $item['new_user'] += (isset($new_users_info[$channel['id']]['count']) ? $new_users_info[$channel['id']]['count'] : 0) + (isset($new_users_old_info[$channel['id']]['count']) ? $new_users_old_info[$channel['id']]['count'] : 0);

            //			$item = array(
            //			'channel_name'=>$channel['name'],
            //			'active_user'=>(isset($activer_users_info[$channel['id']]['count'])?$activer_users_info[$channel['id']]['count']:0)+(isset($active_users_old_info[$channel['id']]['count'])?$active_users_old_info[$channel['id']]['count']:0),
            //			'pay_counts'=>(isset($pay_info_info[$channel['id']]['pay_counts'])?$pay_info_info[$channel['id']]['pay_counts']:0)+(isset($pay_info_old_info[$channel['id']]['pay_counts'])?$pay_info_old_info[$channel['id']]['pay_counts']:0),
            //			'pay_number'=>(isset($pay_info_info[$channel['id']]['uid_counts'])?$pay_info_info[$channel['id']]['uid_counts']:0)+(isset($pay_info_old_info[$channel['id']]['uid_counts'])?$pay_info_old_info[$channel['id']]['uid_counts']:0),
            //			'pay_amount'=>(isset($pay_info_info[$channel['id']]['money'])?$pay_info_info[$channel['id']]['money']:'0.00')+(isset($pay_info_old_info[$channel['id']]['money'])?$pay_info_old_info[$channel['id']]['money']:'0.00'),
            //			//'active_arpu'=>round(($pay_info_info[$channel['id']]['money']+$pay_info_old_info[$channel['id']]['money'])/($active_users_old_info[$channel['id']]['count']+$active_users_old_info[$channel['id']]['count']),2),
            //			'new_user'=>(isset($new_users_info[$channel['id']]['count'])?$new_users_info[$channel['id']]['count']:0)+(isset($new_users_old_info[$channel['id']]['count'])?$new_users_old_info[$channel['id']]['count']:0),
            //			);

            $item['active_arpu'] = round($item['pay_amount'] / $item['active_user'], 2);
            $item['pay_arpu'] = round($item['pay_amount'] / $item['pay_number'], 2);

            $list[] = $item;
        }

        foreach ($list as $k => $v) {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $list);

        $this->assign('heji_old', $heji_old);
        $this->assign('heji', $heji);
        $this->assign('game_name', $game_name);
        $this->assign('list', $list);
        $this->assign('channel_list', get_channel_list(I('cid'), 2));
        $this->assign('game_list', get_game_list(I('appid'), 1, 'all'));
        $this->display();
    }

    public function pay_statistics_by_tg()
    {
        $post_data = $_REQUEST;

        //日期最大限制
        $max = date('Y-m-d', time() - 3600 * 24);

        $start_time = $post_data['start_time'] ? $post_data['start_time'] : date('Y-m-d', strtotime('-1 week'));
        $end_time = $post_data['end_time'] ? $post_data['end_time'] : $max;
        $game_name = '--';

        if (strtotime($end_time) - strtotime($start_time) >= 180 * 3600 * 24) {
            $this->error('不能查询超过180天以上');
        }

        $map = array();
        $map_old = array();
        $heji_old = array();

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map_old['gameid'] = array('in', $game_role);
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map_old['channel'] = array('in', $channel_role);
        } else {
            $channel_ids = M('channel')->where(array('status' => 1, 'type' => 2))->Getfield('id', true);
            $channel_ids = implode(',', $channel_ids);
            $map['cid'] = array('in', $channel_ids);
            $map_old['channel'] = array('in', $channel_ids);
        }


        if (!empty($post_data['cid'])) {
            $map['cid'] = $post_data['cid'];
            $map_old['channel'] = $post_data['cid'];
        }
        if (!empty($post_data['appid'])) {
            $map['appid'] = $post_data['appid'];
            $map_old['gameid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id' => $post_data['appid']))->getfield('game_name');
        }


        if (!empty($start_time) && !empty($end_time)) {
            $map['time'] = array(array('egt', $start_time), array('elt', $end_time));
            $map_old['time'] = array(array('egt', strtotime($start_time . ' 00:00:00')), array('elt', strtotime($end_time . ' 23:59:59')));
            $map_old['pay_to_time'] = array(array('egt', strtotime($start_time . ' 00:00:00')), array('elt', strtotime($end_time . ' 23:59:59')));
        }


        $heji = M('pay_by_day')->
        field('sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user)')->
        where($map)->
        find();


        $list = M('pay_by_day')->
        field('cid,sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user)')->
        where($map)->
        group('cid')->
        select();

        $login_log_model = M('login_log', null, C('DB_OLDSDK_CONFIG'));

        $active_users = $login_log_model
            ->field("count(distinct(username)) as count, channel")
            ->where($map_old)
            ->group("channel")
            ->select();

        $active_users_heji = $login_log_model
            ->field("count(distinct(username)) as count")
            ->where($map_old)
            ->find();


        $pay_model = M('pay', 'syo_', C('DB_OLDSDK_CONFIG'));

        $pay_info = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money,channel")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->group("channel")
            ->select();

        $pay_info_heji = $pay_model
            ->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money")
            ->where(array_merge($map_old, array('status' => 1, 'vip' => 2, 'type' => 1)))
            ->find();


        $newplayer_model = M('newplayer', null, C('DB_OLDSDK_CONFIG'));

        $new_users = $newplayer_model
            ->field("count(username) as count, channel")
            ->where($map_old)
            ->group("channel")
            ->select();

        $new_users_heji = $newplayer_model
            ->field("count(username) as count")
            ->where($map_old)
            ->find();


        $list_info = array();

        if (is_array($list)) {
            foreach ($list as $v) {
                $list_info[$v['cid']] = $v;
            }
        }
        unset($list);

        $active_users_info = array();
        if (is_array($active_users)) {
            foreach ($active_users as $active_user) {
                $active_users_info[$active_user['channel']] = $active_user;

            }

        }
        $heji_old['active_user'] = $active_users_heji['count'];

        unset($active_users);

        $pay_info_info = array();
        if (is_array($pay_info)) {
            foreach ($pay_info as $v) {
                $pay_info_info[$v['channel']] = $v;

            }
        }

        $heji_old['pay_counts'] = $pay_info_heji['pay_counts'];
        $heji_old['pay_number'] = $pay_info_heji['uid_counts'];
        $heji_old['pay_amount'] = $pay_info_heji['money'];
        unset($pay_info);

        $new_users_info = array();
        if (is_array($new_users)) {
            foreach ($new_users as $v) {
                $new_users_info[$v['channel']] = $v;

            }
        }
        $heji_old['new_user'] = $new_users_heji['count'];
        unset($new_users);


        $channel_map = array('status' => 1, 'parent' => 0);
        if ($map['cid']) {
            $channel_map['id'] = $map['cid'];
        }

        $channels = M('Channel')->where($channel_map)->getfield('id,name,parent', true);

        $channel_map['parent'] = array('neq', 0);

        $child_channels = M('channel')->where($channel_map)->field('parent,id,name')->order('parent')->select();

        foreach ($child_channels as $v) {
            $channels[$v['parent']]['child'][] = $v;
        }

        $result = array();
        foreach ($channels as $channel) {
            $item = array();
            if (is_array($channel['child'])) {
                foreach ($channel['child'] as $child_channel) {
                    $child_item = array(
                        'channel_id' => $child_channel['id'],
                        'channel_name' => $child_channel['name'],
                        'active_user' => (isset($list_info[$child_channel['id']]['sum(active_user)']) ? $list_info[$child_channel['id']]['sum(active_user)'] : 0) + (isset($active_users_info[$child_channel['id']]['count']) ? $active_users_info[$child_channel['id']]['count'] : 0),
                        'pay_counts' => (isset($list_info[$child_channel['id']]['sum(pay_counts)']) ? $list_info[$child_channel['id']]['sum(pay_counts)'] : 0) + (isset($pay_info_info[$child_channel['id']]['pay_counts']) ? $pay_info_info[$child_channel['id']]['pay_counts'] : 0),
                        'pay_number' => (isset($list_info[$child_channel['id']]['sum(pay_number)']) ? $list_info[$child_channel['id']]['sum(pay_number)'] : 0) + (isset($pay_info_info[$child_channel['id']]['uid_counts']) ? $pay_info_info[$child_channel['id']]['uid_counts'] : 0),
                        'pay_amount' => (isset($list_info[$child_channel['id']]['sum(pay_amount)']) ? $list_info[$child_channel['id']]['sum(pay_amount)'] : '0.00') + (isset($pay_info_info[$child_channel['id']]['money']) ? $pay_info_info[$child_channel['id']]['money'] : '0.00'),
                        'new_user' => (isset($list_info[$child_channel['id']]['sum(new_user)']) ? $list_info[$child_channel['id']]['sum(new_user)'] : 0) + (isset($new_users_info[$child_channel['id']]['count']) ? $new_users_info[$child_channel['id']]['count'] : 0),
                    );

                    $child_item['active_arpu'] = round($child_item['pay_amount'] / $child_item['active_user'], 2);
                    $child_item['pay_arpu'] = round($child_item['pay_amount'] / $child_item['pay_number'], 2);

                    $item['active_user'] = $item['active_user'] + $child_item['active_user'];
                    $item['pay_counts'] = $item['pay_counts'] + $child_item['pay_counts'];
                    $item['pay_number'] = $item['pay_number'] + $child_item['pay_number'];
                    $item['pay_amount'] = $item['pay_amount'] + $child_item['pay_amount'];
                    $item['new_user'] = $item['new_user'] + $child_item['new_user'];

                    $item['child'][] = $child_item;
                }
                $pay_amount = array();
                foreach ($item['child'] as $k => $v) {
                    $pay_amount[$k] = $v['pay_amount'];
                }
                array_multisort($pay_amount, SORT_DESC, $item['child']);

            }

            $item['id'] = $channel['id'];
            $item['channel_name'] = $channel['name'];
            $item['active_user'] += (isset($list_info[$channel['id']]['sum(active_user)']) ? $list_info[$channel['id']]['sum(active_user)'] : 0) + (isset($active_users_info[$channel['id']]['count']) ? $active_users_info[$channel['id']]['count'] : 0);
            $item['pay_counts'] += (isset($list_info[$channel['id']]['sum(pay_counts)']) ? $list_info[$channel['id']]['sum(pay_counts)'] : 0) + (isset($pay_info_info[$channel['id']]['pay_counts']) ? $pay_info_info[$channel['id']]['pay_counts'] : 0);
            $item['pay_number'] += (isset($list_info[$channel['id']]['sum(pay_number)']) ? $list_info[$channel['id']]['sum(pay_number)'] : 0) + (isset($pay_info_info[$channel['id']]['uid_counts']) ? $pay_info_info[$channel['id']]['uid_counts'] : 0);
            $item['pay_amount'] += (isset($list_info[$channel['id']]['sum(pay_amount)']) ? $list_info[$channel['id']]['sum(pay_amount)'] : '0.00') + (isset($pay_info_info[$channel['id']]['money']) ? $pay_info_info[$channel['id']]['money'] : '0.00');
            $item['new_user'] += (isset($list_info[$channel['id']]['sum(new_user)']) ? $list_info[$channel['id']]['sum(new_user)'] : 0) + (isset($new_users_info[$channel['id']]['count']) ? $new_users_info[$channel['id']]['count'] : 0);

            //			$item = array(
            //			'channel_name'=>$channel['name'],
            //			'active_user'=>(isset($list_info[$channel['id']]['sum(active_user)'])?$list_info[$channel['id']]['sum(active_user)']:0)+(isset($active_users_info[$channel['id']]['count'])?$active_users_info[$channel['id']]['count']:0),
            //			'pay_counts'=>(isset($list_info[$channel['id']]['sum(pay_counts)'])?$list_info[$channel['id']]['sum(pay_counts)']:0)+(isset($pay_info_info[$channel['id']]['pay_counts'])?$pay_info_info[$channel['id']]['pay_counts']:0),
            //			'pay_number'=>(isset($list_info[$channel['id']]['sum(pay_number)'])?$list_info[$channel['id']]['sum(pay_number)']:0)+(isset($pay_info_info[$channel['id']]['uid_counts'])?$pay_info_info[$channel['id']]['uid_counts']:0),
            //			'pay_amount'=>(isset($list_info[$channel['id']]['sum(pay_amount)'])?$list_info[$channel['id']]['sum(pay_amount)']:'0.00')+(isset($pay_info_info[$channel['id']]['money'])?$pay_info_info[$channel['id']]['money']:'0.00'),
            //		//	'active_arpu'=>round(($list_info[$channel['id']]['sum(pay_amount)']+$pay_info_info[$channel['id']]['money'])/($list_info[$channel['id']]['sum(active_user)']+$active_users_info[$channel['id']]['count']),2),
            //			'new_user'=>(isset($list_info[$channel['id']]['sum(new_user)'])?$list_info[$channel['id']]['sum(new_user)']:0)+(isset($new_users_info[$channel['id']]['count'])?$new_users_info[$channel['id']]['count']:0),
            //			);

            $item['active_arpu'] = round($item['pay_amount'] / $item['active_user'], 2);
            $item['pay_arpu'] = round($item['pay_amount'] / $item['pay_number'], 2);

            $result[] = $item;
        }


        foreach ($result as $k => $v) {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $result);


        $this->assign('heji_old', $heji_old);
        $this->assign('heji', $heji);
        $this->assign('game_name', $game_name);
        $this->assign('result', $result);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('channel_list', get_channel_list(I('cid'), 2));
        $this->assign('game_list', get_game_list(I('appid'), 1, 'all'));
        $this->assign('max', $max);
        $this->display();
    }

    /**
     * 推广数据汇总-注册
     */
    public function data_summary_tg()
    {

        $post_data = $_REQUEST;

        //日期最大限制
        $max = date('Y-m-d', time() - 3600 * 24);

        $start_time = $post_data['start_time'] ? $post_data['start_time'] : date('Y-m-d', strtotime('-1 week'));
        $end_time = $post_data['end_time'] ? $post_data['end_time'] : $max;
        $game_name = '--';

        if (strtotime($end_time) - strtotime($start_time) >= 180 * 3600 * 24) {
            $this->error('不能查询超过180天以上');
        }

        $map = array();
        $map_old = array();

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);

        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);

        } else {
            $channel_ids = M('channel')->where(array('status' => 1, 'type' => 2))->Getfield('id', true);
            $channel_ids = implode(',', $channel_ids);
            $map['cid'] = array('in', $channel_ids);

        }

        $cid = $post_data['cid'];
        $judge = 1;    //包含子渠道
        if (!empty($cid)) {
            $map['cid'] = $cid;
            $cids = M('channel')->where(array('parent' => $cid))->getField('id', true);

            if (!empty($cids)) {
                $ids = '';
                foreach ($cids as $v) {
                    $ids .= $v . ',';
                }
                $ids = trim($ids, ',');
                $ids = $ids . ',' . $cid;
                $map['cid'] = array('in', $ids);

            } else {
                $judge = 0;   //单个渠道
            }

        }
        if (!empty($post_data['appid'])) {
            $map['appid'] = $post_data['appid'];

            $game_name = M('Game')->where(array('id' => $post_data['appid']))->getfield('game_name');
        }


        if (!empty($start_time) && !empty($end_time)) {
            $map['time'] = array(array('egt', $start_time), array('elt', $end_time));

        }


        $heji = M('pay_by_day')->
        field('sum(active_user),sum(pay_number),sum(pay_amount),sum(new_box_user) reg_num,sum(pay_active_number),sum(pay_active_amount),sum(getmoney),sum(platform_money),sum(rebate)')->
        where($map)->
        find();


        $list = M('pay_by_day')->
        field('cid,sum(active_user),sum(pay_number),sum(pay_amount),sum(new_box_user),sum(pay_active_number),sum(pay_active_amount),sum(getmoney),sum(platform_money),sum(rebate)')->
        where($map)->
        group('cid')->
        select();


        $list_info = array();

        if (is_array($list)) {
            foreach ($list as $v) {
                $list_info[$v['cid']] = $v;
            }
        }
        unset($list);


        if ($judge == 1) {
            $channel_map = array('status' => 1, 'parent' => 0);
            if ($cid) {
                $channel_map['id'] = $cid;
            }

            $channels = M('Channel')->where($channel_map)->getfield('id,name,parent', true);

            $child_map = array('status' => 1, 'parent' => array('neq', 0));
            if ($cid) {
                $child_map['parent'] = $cid;
            }

            $child_channels = M('channel')->where($child_map)->field('parent,id,name')->order('parent')->select();
        } else {
            $channels = M('Channel')->where(array('status' => 1, 'id' => $cid))->getfield('id,name,parent', true);
            $child_channels = array();
        }

        foreach ($child_channels as $v) {
            $channels[$v['parent']]['child'][] = $v;
        }

        $result = array();
        foreach ($channels as $channel) {
            $item = array();
            if (is_array($channel['child'])) {
                foreach ($channel['child'] as $child_channel) {
                    $child_item = array(
                        'channel_id' => $child_channel['id'],
                        'channel_name' => $child_channel['name'],
                        'reg_num' => (isset($list_info[$child_channel['id']]['sum(new_box_user)']) ? $list_info[$child_channel['id']]['sum(new_box_user)'] : 0),
                        'pay_number' => (isset($list_info[$child_channel['id']]['sum(pay_number)']) ? $list_info[$child_channel['id']]['sum(pay_number)'] : 0),
                        'pay_amount' => (isset($list_info[$child_channel['id']]['sum(pay_amount)']) ? $list_info[$child_channel['id']]['sum(pay_amount)'] : 0),
                        'active_user' => (isset($list_info[$child_channel['id']]['sum(active_user)']) ? $list_info[$child_channel['id']]['sum(active_user)'] : 0),
                        'pay_active_number' => (isset($list_info[$child_channel['id']]['sum(pay_active_number)']) ? $list_info[$child_channel['id']]['sum(pay_active_number)'] : 0),
                        'pay_active_amount' => (isset($list_info[$child_channel['id']]['sum(pay_active_amount)']) ? $list_info[$child_channel['id']]['sum(pay_active_amount)'] : 0),
                        'getmoney' => (isset($list_info[$child_channel['id']]['sum(getmoney)']) ? $list_info[$child_channel['id']]['sum(getmoney)'] : 0),
                        'platform_money' => (isset($list_info[$child_channel['id']]['sum(platform_money)']) ? $list_info[$child_channel['id']]['sum(platform_money)'] : 0),
                        'rebate' => (isset($list_info[$child_channel['id']]['sum(rebate)']) ? $list_info[$child_channel['id']]['sum(rebate)'] : 0),
                    );

                    $child_item['pay_active_arpu'] = round($child_item['pay_active_amount'] / $child_item['pay_active_number'], 2);
                    $child_item['pay_arpu'] = round($child_item['pay_amount'] / $child_item['pay_number'], 2);
                    $child_item['income'] = $child_item['getmoney'] + round($child_item['platform_money'] / 10, 2) - round($child_item['rebate'] / 10, 2);

                    $item['reg_num'] = $item['reg_num'] + $child_item['reg_num'];
                    $item['pay_number'] = $item['pay_number'] + $child_item['pay_number'];
                    $item['pay_amount'] = $item['pay_amount'] + $child_item['pay_amount'];
                    $item['active_user'] = $item['active_user'] + $child_item['active_user'];
                    $item['pay_active_number'] = $item['pay_active_number'] + $child_item['pay_active_number'];
                    $item['pay_active_amount'] = $item['pay_active_amount'] + $child_item['pay_active_amount'];
                    $item['getmoney'] = $item['getmoney'] + $child_item['getmoney'];
                    $item['platform_money'] = $item['platform_money'] + $child_item['platform_money'];
                    $item['rebate'] = $item['rebate'] + $child_item['rebate'];

                    $item['child'][] = $child_item;
                }
                $pay_amount = array();
                foreach ($item['child'] as $k => $v) {
                    $pay_amount[$k] = $v['pay_amount'];
                }
                array_multisort($pay_amount, SORT_DESC, $item['child']);

            }

            $item['id'] = $channel['id'];
            $item['channel_name'] = $channel['name'];
            $item['reg_num'] += (isset($list_info[$channel['id']]['sum(new_box_user)']) ? $list_info[$channel['id']]['sum(new_box_user)'] : 0);
            $item['pay_number'] += (isset($list_info[$channel['id']]['sum(pay_number)']) ? $list_info[$channel['id']]['sum(pay_number)'] : 0);
            $item['pay_amount'] += (isset($list_info[$channel['id']]['sum(pay_amount)']) ? $list_info[$channel['id']]['sum(pay_amount)'] : 0);
            $item['active_user'] += (isset($list_info[$channel['id']]['sum(active_user)']) ? $list_info[$channel['id']]['sum(active_user)'] : 0);
            $item['pay_active_number'] += (isset($list_info[$channel['id']]['sum(pay_active_number)']) ? $list_info[$channel['id']]['sum(pay_active_number)'] : 0);
            $item['pay_active_amount'] += (isset($list_info[$channel['id']]['sum(pay_active_amount)']) ? $list_info[$channel['id']]['sum(pay_active_amount)'] : 0);
            $item['getmoney'] += (isset($list_info[$channel['id']]['sum(getmoney)']) ? $list_info[$channel['id']]['sum(getmoney)'] : 0);
            $item['platform_money'] += (isset($list_info[$channel['id']]['sum(platform_money)']) ? $list_info[$channel['id']]['sum(platform_money)'] : 0);
            $item['rebate'] += (isset($list_info[$channel['id']]['sum(rebate)']) ? $list_info[$channel['id']]['sum(rebate)'] : 0);


            $item['pay_active_arpu'] = round($item['pay_active_amount'] / $item['pay_active_number'], 2);
            $item['pay_arpu'] = round($item['pay_amount'] / $item['pay_number'], 2);
            $item['income'] = $item['getmoney'] + round($item['platform_money'] / 10, 2) - round($item['rebate'] / 10, 2);


            if ($item['reg_num'] || $item['pay_number'] || $item['pay_amount'] || $item['active_user'] || $item['pay_active_number']
                || $item['pay_active_amount'] || $item['getmoney'] || $item['platform_money'] || $item['rebate']) {
                $result[] = $item;
            }

        }


        foreach ($result as $k => $v) {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $result);


        $this->assign('heji', $heji);
        $this->assign('game_name', $game_name);
        $this->assign('result', $result);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('channel_list', get_channel_list(I('cid'), 2));
        $this->assign('game_list', get_game_list(I('appid'), 1, 'all'));
        $this->assign('max', $max);
        $this->display();
    }

    public function xinzen()
    {
        $post_data = $_REQUEST;
        //日期最大限制
        $max = date('Y-m-d', time());

        $start_time = $post_data['start_time'] ? $post_data['start_time'] : $max;
        $end_time = $post_data['end_time'] ? $post_data['end_time'] : $max;
        $game_name = '--';

        if (strtotime($end_time) - strtotime($start_time) >= 31 * 3600 * 24) {
            $this->error('不能查询超过31天以上');
        }

        $map = array();
        $map_old = array();

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map_old['gameid'] = array('in', $game_role);
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['channel'] = array('in', $channel_role);
            $map_old['channel'] = array('in', $channel_role);
        } else {
            $channel_ids = M('channel')->where(array('status' => 1, 'type' => 2))->Getfield('id', true);
            $channel_ids = implode(',', $channel_ids);
            $map['channel'] = array('in', $channel_ids);
            $map_old['channel'] = array('in', $channel_ids);
        }

        if (!empty($post_data['cid'])) {
            $map['channel'] = $post_data['cid'];
            $map_old['channel'] = $post_data['cid'];
        }
        if (!empty($post_data['appid'])) {
            $map['appid'] = $post_data['appid'];
            $map_old['gameid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id' => $post_data['appid']))->getfield('game_name');
        }

        $map['create_time'] = array(array('egt', strtotime($start_time)), array('lt', strtotime($end_time) + 3600 * 24));
        $map_old['time'] = array(array('egt', strtotime($start_time)), array('lt', strtotime($end_time) + 3600 * 24));

        //$map['is_first_login'] = 1;

//		$new_users = $this->player_login_logs
//		->where($map)
//		->group('channel')
//		->getfield("channel,count(distinct ip,machine_code) as count",true);

        $new_users_map = $map;
        $new_users_map['first_login_time'] = $new_users_map['create_time'];
        unset($new_users_map['create_time']);

        $player_model = M('player');

        $new_users = $player_model
            ->where($new_users_map)
            ->group('channel')
            ->getfield("channel,count(distinct(machine_code)) as count", true);

        $new_registers = $player_model
            ->where($map)
            ->group('channel')
            ->getfield('channel,count(distinct(machine_code)) as count', true);

//		$heji = $this->player_login_logs
//			->where($map)
//			->field("count(distinct ip,machine_code) as count")
//			->find();

        $heji = $player_model
            ->where($new_users_map)
            ->field("count(distinct(machine_code)) as count")
            ->find();

        $registers_heji = $player_model
            ->where($map)
            ->field("count(distinct(machine_code)) as count")
            ->find();

        $newplayer_model = M('newplayer', null, C('DB_OLDSDK_CONFIG'));

        $new_users_old = $newplayer_model
            ->where($map_old)
            ->group("channel")
            ->getfield("channel,count(distinct(device_id)) as count", true);

        $heji_old = $newplayer_model
            ->where($map_old)
            ->field("count(distinct(device_id)) as count")
            ->find();

        $channel_map = array('status' => 1, 'parent' => 0);
        if ($map['channel']) {
            $channel_map['id'] = $map['channel'];
        }
        //获取所有推广的入职时间

        $channel_effective = M('channel')
            ->alias('c')
            ->join('left join __USERS__ u on c.admin_id=u.id')
            ->where(array('c.type' => 2, 'c.status' => 1))
            ->getfield('c.id,u.effective', true);


        $channels = M('Channel')->where($channel_map)->getfield('id,name,parent', true);

        $channel_map['parent'] = array('neq', 0);

        $child_channels = M('channel')->where($channel_map)->field('parent,id,name')->order('parent')->select();

        foreach ($child_channels as $v) {
            $channels[$v['parent']]['child'][] = $v;
        }

        $result = array();
        foreach ($channels as $channel) {
            $item = array();
            if (is_array($channel['child'])) {
                foreach ($channel['child'] as $child_channel) {

                    $child_item = array(
                        'id' => $child_channel['id'],
                        'channel_name' => $child_channel['name'],
                        'new_user' => (isset($new_users[$child_channel['id']]) ? $new_users[$child_channel['id']] : 0) + (isset($new_users_old[$child_channel['id']]) ? $new_users_old[$child_channel['id']] : 0),
                        'registers' => (isset($new_registers[$child_channel['id']]) ? $new_registers[$child_channel['id']] : 0) + (isset($new_users_old[$child_channel['id']]) ? $new_users_old[$child_channel['id']] : 0)
                    );

                    $item['new_user'] = $item['new_user'] + $child_item['new_user'];
                    $item['registers'] = $item['registers'] + $child_item['registers'];

                    $item['child'][] = $child_item;
                }
                $new_user_order = array();
                foreach ($item['child'] as $k => $v) {
                    $new_user_order[$k] = $v['new_user'];
                }
                array_multisort($new_user_order, SORT_DESC, $item['child']);

            }

            $item['id'] = $channel['id'];
            $item['channel_name'] = $channel['name'];
            $item['new_user'] += (isset($new_users[$channel['id']]) ? $new_users[$channel['id']] : 0) + (isset($new_users_old[$channel['id']]) ? $new_users_old[$channel['id']] : 0);
            $item['registers'] += (isset($new_registers[$channel['id']]) ? $new_registers[$channel['id']] : 0) + (isset($new_users_old[$channel['id']]) ? $new_users_old[$channel['id']] : 0);
            $result[] = $item;
        }
        $new_user_order = array();
        foreach ($result as $k => $v) {
            $new_user_order[$k] = $v['new_user'];
        }
        array_multisort($new_user_order, SORT_DESC, $result);

        $this->assign('channel_effective', $channel_effective);
        $this->assign('heji_newsuser', $heji['count'] + $heji_old['count']);
        $this->assign('heji_registser', $registers_heji['count'] + $heji_old['count']);
        $this->assign('game_name', $game_name);
        $this->assign('result', $result);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('channel_list', get_channel_list(I('cid'), 2));
        $this->assign('game_list', get_game_list(I('appid'), 1, 'all'));
        $this->assign('max', $max);
        $this->display();
    }

    /**
     * 推广绩效奖金明细
     */
    public function bonus_performance_detail()
    {
        //日期最大限制
        $max = date('Y-m', time());
        $time = I('time') ? I('time') : $max;
        $channel = I('channel');

        $order = I('order') ? I('order') : 2;

        switch ($order) {
            case 1:
                $key = 'tg_qualified_counts';
                $arr_order = SORT_ASC;
                break;
            case 2:
                $key = 'tg_qualified_counts';
                $arr_order = SORT_DESC;
                break;
            case 3:
                $key = 'tg_qualified_bonus';
                $arr_order = SORT_ASC;
                break;
            case 4:
                $key = 'tg_qualified_bonus';
                $arr_order = SORT_DESC;
                break;
            case 5:
                $key = 'valid_new_users';
                $arr_order = SORT_ASC;
                break;
            case 6:
                $key = 'valid_new_users';
                $arr_order = SORT_DESC;
                break;
            case 7:
                $key = 'performance_deduct';
                $arr_order = SORT_ASC;
                break;
            case 8:
                $key = 'performance_deduct';
                $arr_order = SORT_DESC;
                break;
            default:
        }

        $map1 = array();
        $map2 = array();

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map1['cid'] = array('in', $channel_role);
            $map2['channel'] = array('in', $channel_role);
        } else {
            $channel_ids = M('channel')->where(array('status' => 1, 'type' => 2))->Getfield('id', true);
            $channel_ids = implode(',', $channel_ids);
            $map1['cid'] = array('in', $channel_ids);
            $map2['channel'] = array('in', $channel_ids);
        }

        if (!empty($channel)) {
            $map1['cid'] = $channel;
            $map2['channel'] = $channel;
        }


        $map1['time'] = array('like', $time . '%');
        $map2['create_time'] = array(array('egt', strtotime($time . '-01 00:00:00')), array('lt', strtotime('+1 month', strtotime($time . '-01 00:00:00'))));

        //查询所有推广渠道当月有效注册数
        $valid_new_users = M('pay_by_day')
            ->where($map1)
            ->group('cid')
            ->cache(true)
            ->getfield('cid,sum(valid_new_user) valid_new_user', true);

        $map3 = $map2;
        $map3['time'] = $map3['create_time'];
        unset($map3['create_time']);
        $map3['time'][1][1] = ($map3['time'][1][1] <= strtotime(date('Y-m-d', time()) . ' 00:00:00')) ? $map3['time'][1][1] : strtotime(date('Y-m-d', time()) . ' 00:00:00');
        //查询所有推广BI渠道当月有效注册数
        $valid_new_users_old = M('newplayer', null, C('DB_OLDSDK_CONFIG'))
            ->where($map3)
            ->group('channel')
            ->cache(true)
            ->getfield('channel,count(distinct(device_id)) valid_new_users', true);

        //查询所有推广渠道当月有效注册任务数
        $channel_task = get_channel_task(is_array($map1['cid']) ? $map1['cid'][1] : $map1['cid'], $time, 2);

        $channel_task_info = array();
        foreach ($channel_task as $v) {
            $channel_task_info[$v['cid']] = $v['task'];
        }

        //查询所有推广渠道推广奖金、达标个数

        $tg_qualified_info = M('tg_qualified_info')
            ->where($map2)
            ->group('channel')
            ->cache(true)
            ->getfield('channel,sum(tg_qualified_counts) tg_qualified_counts,sum(tg_qualified_bonus) tg_qualified_bonus', true);

        $channel_map = array('status' => 1, 'parent' => 0);
        if ($map1['cid']) {
            $channel_map['id'] = $map1['cid'];
        }

        $channels = M('Channel')->where($channel_map)->getfield('id,name,parent', true);

        $channel_map['parent'] = array('neq', 0);

        $child_channels = M('channel')->where($channel_map)->field('parent,id,name')->order('parent')->select();

        foreach ($child_channels as $v) {
            $channels[$v['parent']]['child'][] = $v;
        }

        $result = array();

        foreach ($channels as $channel) {
            $item = array();
            if (is_array($channel['child'])) {
                foreach ($channel['child'] as $child_channel) {
                    $child_item = array(
                        'channel_name' => $child_channel['name'],
                        'tg_qualified_counts' => (int)$tg_qualified_info[$child_channel['id']]['tg_qualified_counts'],
                        'tg_qualified_bonus' => sprintf("%.2f", $tg_qualified_info[$child_channel['id']]['tg_qualified_bonus']),
                        'valid_new_users' => (int)$valid_new_users[$child_channel['id']] + (int)$valid_new_users_old[$child_channel['id']],
                        'performance_deduct' => sprintf("%.2f", get_performance_deduct((int)$valid_new_users[$child_channel['id']] + (int)$valid_new_users_old[$child_channel['id']], (int)$channel_task_info[$child_channel['id']])),
                    );

                    $item['tg_qualified_counts'] = $item['tg_qualified_counts'] + $child_item['tg_qualified_counts'];
                    $item['tg_qualified_bonus'] = $item['tg_qualified_bonus'] + $child_item['tg_qualified_bonus'];
                    $item['valid_new_users'] = $item['valid_new_users'] + $child_item['valid_new_users'];
                    $item['performance_deduct'] = $item['performance_deduct'] + $child_item['performance_deduct'];

                    $item['child'][] = $child_item;
                }
                $new_order = array();
                foreach ($item['child'] as $k => $v) {
                    $new_order[$k] = $v[$key];
                }
                array_multisort($new_order, $arr_order, $item['child']);

            }

            $item['id'] = $channel['id'];
            $item['channel_name'] = $channel['name'];
            $item['tg_qualified_counts'] += (int)$tg_qualified_info[$channel['id']]['tg_qualified_counts'];
            $item['tg_qualified_bonus'] += $tg_qualified_info[$channel['id']]['tg_qualified_bonus'];
            $item['tg_qualified_bonus'] = sprintf("%.2f", $item['tg_qualified_bonus']);
            $item['valid_new_users'] += (int)$valid_new_users[$channel['id']] + (int)$valid_new_users_old[$channel['id']];
            $item['performance_deduct'] += get_performance_deduct((int)$valid_new_users[$channel['id']] + (int)$valid_new_users_old[$channel['id']], (int)$channel_task_info[$channel['id']]);
            $item['performance_deduct'] = sprintf("%.2f", $item['performance_deduct']);

            $result[] = $item;
        }

        $new_order = array();
        foreach ($result as $k => $v) {
            $new_order[$k] = $v[$key];
        }
        array_multisort($new_order, $arr_order, $result);

        $this->assign('order', $order);
        $this->assign('result', $result);
        $this->assign('time', $time);
        $this->assign('channel_list', get_channel_list(I('channel'), 2));
        $this->assign('max', $max);
        $this->display();


    }

    /**
     * 充值报表-游戏
     */
    public function get_money_by_game()
    {
        $request_data = I('request.');
        $action = $request_data['action'] ? $request_data['action'] : 1;//1.列表 2.导出

        //日期最大限制
        $max = date('Y-m-d', time());

        $start_time = $request_data['start_time'] ? $request_data['start_time'] : date('Y-m-d', strtotime('-1 week'));
        $end_time = $request_data['end_time'] ? $request_data['end_time'] : $max;


        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
//            $map['gameid'] = array('in', $game_role);
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map['channel'] = array('in', $channel_role);
        }

        if (!empty($request_data['cid'])) {
            $map['cid'] = $request_data['cid'];
            $map['channel'] = $request_data['cid'];

        }
        if (!empty($request_data['appid'])) {
            $map['appid'] = $request_data['appid'];
//            $map['gameid'] = $request_data['appid'];
        }

        //	$access_type= $request_data['access_type'];

        if (strtotime($end_time) - strtotime($start_time) >= 100 * 3600 * 24) {
            $this->error('不能查询超过100天以上');
        }

        if ($start_time) $map['create_time'][] = array('egt', strtotime($start_time . ' 00:00:00'));
        if ($end_time) $map['create_time'][] = array('elt', strtotime($end_time . ' 23:59:59'));
        $map['status'] = array('in', '1,2');


        $game_map = array();
        $game_map['status'] = 1;
        if ($map['appid']) $game_map['id'] = $map['appid'];
        unset($map['channel']);
        $pay_info = M('inpour')->where($map)->group('appid')->getfield('appid,sum(money) money,sum(getmoney) getmoney,sum(platform_money) platform_money,count(*) count', true);

        $heji = M('inpour')->where($map)->field('sum(money) money,sum(getmoney) getmoney,sum(platform_money) platform_money,count(*) count')->find();

        $heji['platform_money'] = $heji['platform_money'] / 10;

        //$map['pay_to_time'] = $map['create_time'];
        //$map['vip'] = 2;
        //$map['type'] = 1;
        //$map['status'] = array('in','1,3');
        //if($map['appid']) $map['gameid'] = $map['appid'];
        //$bi_pay_info = M('syo_pay',null,C('DB_OLDSDK_CONFIG'))->where($map)->group('gameid')->getfield('gameid,sum(rmb) money,sum(getmoney) getmoney,count(*) count',true);

        //$bi_heji = M('syo_pay',null,C('DB_OLDSDK_CONFIG'))->where($map)->field('sum(rmb) money,sum(getmoney) getmoney,count(*) count')->find();

        $games = M('game')->field('id,tag,game_name')->where($game_map)->select();

        $list = array();
        foreach ($games as $game) {
            $item = array();
            $item['id'] = $game['tag'];
            $item['name'] = $game['game_name'];
            $item['money'] = (isset($pay_info[$game['id']]['money']) ? $pay_info[$game['id']]['money'] : 0);
            $item['money_currency'] = (isset($pay_info[$game['id']]['money']) ? $pay_info[$game['id']]['money'] : 0) - $pay_info[$game['id']]['platform_money'] / 10;
            $item['getmoney'] = (isset($pay_info[$game['id']]['getmoney']) ? $pay_info[$game['id']]['getmoney'] : 0);
            $item['platform_money'] = (isset($pay_info[$game['id']]['platform_money']) ? $pay_info[$game['id']]['platform_money'] / 10 : 0);
            $item['count'] = (isset($pay_info[$game['id']]['count']) ? $pay_info[$game['id']]['count'] : 0);

            if ($item['money'] > 0) {
                $list[] = $item;
            }
        }

        foreach ($list as $k => $v) {
            $money_k[$k] = $v['money'];
        }
        array_multisort($money_k, SORT_DESC, $list);


        if ($action == 1) {
//			$this->assign('access_type',$access_type);

            $this->assign('heji', $heji);
            $this->assign('max', $max);
            $this->assign('start_time', $start_time);
            $this->assign('end_time', $end_time);
            $this->assign('list', $list);
            $this->display();

        } else {

            //导出模式
            $xlsTitle = iconv('utf-8', 'gb2312', '订单统计');//文件名称
            $fileName = date('_YmdHis') . '订单统计';//or $xlsTitle 文件名称可根据自己情况设定

            $expCellName = array('游戏名称', '分区ID', '订单金额（元）', '订单金额（不包含平台币）', '分成金额（元）', '平台币（元）', '充值笔数');

            $cellNum = count($expCellName);
            $heji_size = 0;
            $heji_item = array('合计', '', sprintf("%.2f", $heji['money']), sprintf("%.2f", $heji['money'] - $heji['platform_money']), sprintf("%.2f", $heji['getmoney']), $heji['platform_money'], $heji['count']);

            array_unshift($list, $heji_item);
            $heji_size++;

            $dataNum = count($list);

            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for ($i = 0; $i < $cellNum; $i++) {
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '1', $expCellName[$i]);
            }
            $field = array('name', 'id', 'money', 'money_currency', 'getmoney', 'platform_money', 'count');
            // Miscellaneous glyphs, UTF-8
            for ($i = 0; $i < $dataNum; $i++) {
                for ($j = 0; $j < $cellNum; $j++) {
                    if ($i < $heji_size) {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $list[$i][$j]);
                    } else {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $list[$i][$field[$j]]);
                    }

                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit(1);
        }
    }

    /**
     * 充值报表-游戏
     */
    public function get_money_by_game_old()
    {

        $request_data = I('request.');
        $action = $request_data['action'] ? $request_data['action'] : 1;//1.列表 2.导出

        //日期最大限制
        $max = date('Y-m-d', time());

        $start_time = $request_data['start_time'] ? $request_data['start_time'] : date('Y-m-d', strtotime('-1 week'));
        $end_time = $request_data['end_time'] ? $request_data['end_time'] : $max;


        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map['gameid'] = array('in', $game_role);
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map['channel'] = array('in', $channel_role);
        }

        if (!empty($request_data['cid'])) {
            $map['cid'] = $request_data['cid'];
            $map['channel'] = $request_data['cid'];

        }
        if (!empty($request_data['appid'])) {
            $map['appid'] = $request_data['appid'];
            $map['gameid'] = $request_data['appid'];
        }

        //	$access_type= $request_data['access_type'];

        if (strtotime($end_time) - strtotime($start_time) >= 100 * 3600 * 24) {
            $this->error('不能查询超过100天以上');
        }

        if ($start_time) $map['create_time'][] = array('egt', strtotime($start_time . ' 00:00:00'));
        if ($end_time) $map['create_time'][] = array('elt', strtotime($end_time . ' 23:59:59'));
        $map['status'] = array('in', '1,2');


        $game_map = array();
        $game_map['status'] = 1;
        if ($map['appid']) $game_map['id'] = $map['appid'];
//		if($access_type)
//		{
//			$game_map['access_type'] = $access_type;
//			$appids = M('game')->where($game_map)->getfield('id',true);
//			$map['appid'] = array('in',implode(',',$appids));
//		}


        $pay_info = M('inpour')->where($map)->group('appid')->getfield('appid,sum(money) money,sum(getmoney) getmoney,sum(platform_money) platform_money,count(*) count', true);

        $heji = M('inpour')->where($map)->field('sum(money) money,sum(getmoney) getmoney,sum(platform_money) platform_money,count(*) count')->find();

        $heji['platform_money'] = $heji['platform_money'] / 10;

        $map['pay_to_time'] = $map['create_time'];
        $map['vip'] = 2;
        $map['type'] = 1;
        $map['status'] = array('in', '1,3');
        if ($map['appid']) $map['gameid'] = $map['appid'];
        $bi_pay_info = M('syo_pay', null, C('DB_OLDSDK_CONFIG'))->where($map)->group('gameid')->getfield('gameid,sum(rmb) money,sum(getmoney) getmoney,count(*) count', true);

        $bi_heji = M('syo_pay', null, C('DB_OLDSDK_CONFIG'))->where($map)->field('sum(rmb) money,sum(getmoney) getmoney,count(*) count')->find();

        $games = M('game')->field('id,tag,game_name')->where($game_map)->select();

        $list = array();
        foreach ($games as $game) {
            $item = array();
            $item['id'] = $game['tag'];
            $item['name'] = $game['game_name'];
            $item['money'] = (isset($pay_info[$game['id']]['money']) ? $pay_info[$game['id']]['money'] : 0) + (isset($bi_pay_info[$game['id']]['money']) ? $bi_pay_info[$game['id']]['money'] : 0);
            $item['money_currency'] = (isset($pay_info[$game['id']]['money']) ? $pay_info[$game['id']]['money'] : 0) + (isset($bi_pay_info[$game['id']]['money']) ? $bi_pay_info[$game['id']]['money'] : 0) - $pay_info[$game['id']]['platform_money'] / 10;
            $item['getmoney'] = (isset($pay_info[$game['id']]['getmoney']) ? $pay_info[$game['id']]['getmoney'] : 0) + (isset($bi_pay_info[$game['id']]['getmoney']) ? $bi_pay_info[$game['id']]['getmoney'] : 0);;
            $item['platform_money'] = (isset($pay_info[$game['id']]['platform_money']) ? $pay_info[$game['id']]['platform_money'] / 10 : 0);
            $item['count'] = (isset($pay_info[$game['id']]['count']) ? $pay_info[$game['id']]['count'] : 0) + (isset($bi_pay_info[$game['id']]['count']) ? $bi_pay_info[$game['id']]['count'] : 0);;

            if ($item['money'] > 0) {
                $list[] = $item;
            }
        }

        foreach ($list as $k => $v) {
            $money_k[$k] = $v['money'];
        }
        array_multisort($money_k, SORT_DESC, $list);


        if (I('request.appid') == '' && I('request.cid') == '' && $channel_role == 'all') {
            //计算融合5+7数据
            $channelIDs = M('uchannel', null, C('RH_DB_CONFIG'))->where(array('masterID' => array('in', C('RH_SWITCHPAY_SHOWCHANNEL'))))->getfield('channelID', true);

            $rh_map['channelID'] = array('in', implode(',', $channelIDs));
            $rh_map['payType'] = array('gt', 0);
            $rh_map['state'] = array('in', '2,3');
            $rh_map['completeTime'] = array(array('egt', $start_time . ' 00:00:00'), array('elt', $end_time . ' 23:59:59'));

            $rh57 = M('uorder', null, C('RH_DB_CONFIG'))
                ->where($rh_map)
                ->field('sum(money) as money,sum(realMoney) as getmoney,count(*) count')
                ->find();
        }

        if ($action == 1) {
//			$this->assign('access_type',$access_type);
            $this->assign('rh57', $rh57);
            $this->assign('heji', $heji);
            $this->assign('bi_heji', $bi_heji);
            $this->assign('max', $max);
            $this->assign('start_time', $start_time);
            $this->assign('end_time', $end_time);
            $this->assign('list', $list);
            $this->display();

        } else {

            //导出模式
            $xlsTitle = iconv('utf-8', 'gb2312', '订单统计');//文件名称
            $fileName = date('_YmdHis') . '订单统计';//or $xlsTitle 文件名称可根据自己情况设定

            $expCellName = array('游戏名称', '分区ID', '订单金额（元）', '订单金额（不包含平台币）', '分成金额（元）', '平台币（元）', '充值笔数');

            $cellNum = count($expCellName);
            $heji_size = 0;
            $heji_item = array('合计', '', sprintf("%.2f", $heji['money'] + $bi_heji['money'] + $rh57['money'] / 100), sprintf("%.2f", $heji['money'] + $bi_heji['money'] + $rh57['money'] / 100 - $heji['platform_money']), sprintf("%.2f", $heji['getmoney'] + $bi_heji['getmoney'] + $rh57['getmoney'] / 100), $heji['platform_money'], $heji['count'] + $bi_heji['count'] + $rh57['count']);


            if (I('request.appid') == '' && I('request.cid') == '' && $channel_role == 'all') {

                $rh57_item = array('5+7', '', sprintf("%.2f", $rh57['money'] / 100), sprintf("%.2f", $rh57['money'] / 100), sprintf("%.2f", $rh57['getmoney'] / 100), '', $rh57['count']);
                array_unshift($list, $rh57_item);
                $heji_size++;
            }

            if ($channel_role == 'all') {
                $sdk_item = array('SDK合计', '', sprintf("%.2f", $heji['money'] + $bi_heji['money']), sprintf("%.2f", $heji['money'] + $bi_heji['money'] - $heji['platform_money']), sprintf("%.2f", $heji['getmoney'] + $bi_heji['getmoney']), $heji['platform_money'], $heji['count'] + $bi_heji['count']);
                array_unshift($list, $sdk_item);
                $heji_size++;
            }

            array_unshift($list, $heji_item);
            $heji_size++;

            $dataNum = count($list);

            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for ($i = 0; $i < $cellNum; $i++) {
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '1', $expCellName[$i]);
            }
            $field = array('name', 'id', 'money', 'money_currency', 'getmoney', 'platform_money', 'count');
            // Miscellaneous glyphs, UTF-8
            for ($i = 0; $i < $dataNum; $i++) {
                for ($j = 0; $j < $cellNum; $j++) {
                    if ($i < $heji_size) {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $list[$i][$j]);
                    } else {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $list[$i][$field[$j]]);
                    }

                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit(1);
        }
    }

    /**
     * 充值报表-渠道
     */
    public function get_money_by_channel()
    {
        $request_data = I('request.');
        $action = $request_data['action'] ? $request_data['action'] : 1;//1.列表 2.导出

        //日期最大限制
        $max = date('Y-m-d', time());

        $start_time = $request_data['start_time'] ? $request_data['start_time'] : date('Y-m-d', strtotime('-1 week'));
        $end_time = $request_data['end_time'] ? $request_data['end_time'] : $max;
        //$access_type= $request_data['access_type'];

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map['gameid'] = array('in', $game_role);
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map['channel'] = array('in', $channel_role);
        }

        if (!empty($request_data['cid'])) {
            $map['cid'] = $request_data['cid'];
            $map['channel'] = $request_data['cid'];

        }
        if (!empty($request_data['appid'])) {
            $map['appid'] = $request_data['appid'];
            $map['gameid'] = $request_data['appid'];
        }

        if (strtotime($end_time) - strtotime($start_time) >= 100 * 3600 * 24) {
            $this->error('不能查询超过100天以上');
        }

        if ($start_time) $map['create_time'][] = array('egt', strtotime($start_time . ' 00:00:00'));
        if ($end_time) $map['create_time'][] = array('elt', strtotime($end_time . ' 23:59:59'));
        $map['status'] = array('in', '1,2');

        $game_map = array();
        $game_map['status'] = 1;

        unset($map['channel']);
        unset($map['gameid']);
        $pay_info = M('inpour')->where($map)->group('cid')->getfield('cid,sum(money) money,sum(getmoney) getmoney,sum(platform_money) platform_money,count(*) count', true);
        $heji = M('inpour')->where($map)->field('sum(money) money,sum(getmoney) getmoney,sum(platform_money) platform_money,count(*) count')->find();
        $heji['platform_money'] = $heji['platform_money'] / 10;


        $channel_map = array('status' => 1);

        if ($map['cid']) $channel_map['id'] = $map['cid'];

        $channels = M('channel')->field('id,name')->where($channel_map)->select();

        $list = array();
        foreach ($channels as $channel) {
            $item = array();
            $item['id'] = $channel['id'];
            $item['name'] = $channel['name'];
            $item['money'] = (isset($pay_info[$channel['id']]['money']) ? $pay_info[$channel['id']]['money'] : 0);
            $item['money_currency'] = (isset($pay_info[$channel['id']]['money']) ? $pay_info[$channel['id']]['money'] : 0) - $pay_info[$channel['id']]['platform_money'] / 10;
            $item['getmoney'] = (isset($pay_info[$channel['id']]['getmoney']) ? $pay_info[$channel['id']]['getmoney'] : 0);
            $item['platform_money'] = (isset($pay_info[$channel['id']]['platform_money']) ? $pay_info[$channel['id']]['platform_money'] / 10 : 0);
            $item['count'] = (isset($pay_info[$channel['id']]['count']) ? $pay_info[$channel['id']]['count'] : 0);

            if ($item['money'] > 0) {
                $list[] = $item;
            }
        }

        foreach ($list as $k => $v) {
            $money_k[$k] = $v['money'];
        }
        array_multisort($money_k, SORT_DESC, $list);


        if ($action == 1) {
            //$this->assign('access_type',$access_type);
            $this->assign('heji', $heji);
            $this->assign('max', $max);
            $this->assign('start_time', $start_time);
            $this->assign('end_time', $end_time);
            $this->assign('list', $list);
            $this->display();

        } else {

            //导出模式
            $xlsTitle = iconv('utf-8', 'gb2312', '订单统计');//文件名称
            $fileName = date('_YmdHis') . '订单统计';//or $xlsTitle 文件名称可根据自己情况设定

            $expCellName = array('渠道名称', '渠道ID', '订单金额（元）', '订单金额（不包含平台币）', '分成金额（元）', '平台币（元）', '充值笔数');

            $cellNum = count($expCellName);

            $heji_size = 0;
            $heji_item = array('合计', '', sprintf("%.2f", $heji['money']), sprintf("%.2f", $heji['money'] - $heji['platform_money']), sprintf("%.2f", $heji['getmoney']), $heji['platform_money'], $heji['count']);


            array_unshift($list, $heji_item);
            $heji_size++;

            $dataNum = count($list);

            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for ($i = 0; $i < $cellNum; $i++) {
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '1', $expCellName[$i]);
            }
            $field = array('name', 'id', 'money', 'money_currency', 'getmoney', 'platform_money', 'count');
            // Miscellaneous glyphs, UTF-8
            for ($i = 0; $i < $dataNum; $i++) {
                for ($j = 0; $j < $cellNum; $j++) {
                    if ($i < $heji_size) {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $list[$i][$j]);
                    } else {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $list[$i][$field[$j]]);
                    }
                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit(1);
        }
    }

    /**
     * 充值报表-渠道
     */
    public function get_money_by_channel_old()
    {
        $request_data = I('request.');
        $action = $request_data['action'] ? $request_data['action'] : 1;//1.列表 2.导出

        //日期最大限制
        $max = date('Y-m-d', time());

        $start_time = $request_data['start_time'] ? $request_data['start_time'] : date('Y-m-d', strtotime('-1 week'));
        $end_time = $request_data['end_time'] ? $request_data['end_time'] : $max;
        //$access_type= $request_data['access_type'];

        $game_role = session('game_role');

        if ($game_role != 'all') {
            $map['appid'] = array('in', $game_role);
            $map['gameid'] = array('in', $game_role);
        }

        $channel_role = session('channel_role');

        if ($channel_role != 'all') {
            $map['cid'] = array('in', $channel_role);
            $map['channel'] = array('in', $channel_role);
        }

        if (!empty($request_data['cid'])) {
            $map['cid'] = $request_data['cid'];
            $map['channel'] = $request_data['cid'];

        }
        if (!empty($request_data['appid'])) {
            $map['appid'] = $request_data['appid'];
            $map['gameid'] = $request_data['appid'];
        }

        if (strtotime($end_time) - strtotime($start_time) >= 100 * 3600 * 24) {
            $this->error('不能查询超过100天以上');
        }

        if ($start_time) $map['create_time'][] = array('egt', strtotime($start_time . ' 00:00:00'));
        if ($end_time) $map['create_time'][] = array('elt', strtotime($end_time . ' 23:59:59'));
        $map['status'] = array('in', '1,2');

        $game_map = array();
        $game_map['status'] = 1;
//		if($access_type)
//		{
//			$game_map['access_type'] = $access_type;
//			$appids = M('game')->where($game_map)->getfield('id',true);
//			$map['appid'] = array('in',implode(',',$appids));
//		}


        $pay_info = M('inpour')->where($map)->group('cid')->getfield('cid,sum(money) money,sum(getmoney) getmoney,sum(platform_money) platform_money,count(*) count', true);
        $heji = M('inpour')->where($map)->field('sum(money) money,sum(getmoney) getmoney,sum(platform_money) platform_money,count(*) count')->find();
        $heji['platform_money'] = $heji['platform_money'] / 10;

        $map['pay_to_time'] = $map['create_time'];
        $map['vip'] = 2;
        $map['type'] = 1;
        $map['status'] = array('in', '1,3');
        if ($map['appid']) $map['gameid'] = $map['appid'];
        $bi_pay_info = M('syo_pay', null, C('DB_OLDSDK_CONFIG'))->where($map)->group('channel')->getfield('channel,sum(rmb) money,sum(getmoney) getmoney,count(*) count', true);

        $bi_heji = M('syo_pay', null, C('DB_OLDSDK_CONFIG'))->where($map)->field('sum(rmb) money,sum(getmoney) getmoney,count(*) count')->find();

        $channel_map = array('status' => 1);

        if ($map['cid']) $channel_map['id'] = $map['cid'];

        $channels = M('channel')->field('id,name')->where($channel_map)->select();

        $list = array();
        foreach ($channels as $channel) {
            $item = array();
            $item['id'] = $channel['id'];
            $item['name'] = $channel['name'];
            $item['money'] = (isset($pay_info[$channel['id']]['money']) ? $pay_info[$channel['id']]['money'] : 0) + (isset($bi_pay_info[$channel['id']]['money']) ? $bi_pay_info[$channel['id']]['money'] : 0);
            $item['money_currency'] = (isset($pay_info[$channel['id']]['money']) ? $pay_info[$channel['id']]['money'] : 0) + (isset($bi_pay_info[$channel['id']]['money']) ? $bi_pay_info[$channel['id']]['money'] : 0) - $pay_info[$channel['id']]['platform_money'] / 10;
            $item['getmoney'] = (isset($pay_info[$channel['id']]['getmoney']) ? $pay_info[$channel['id']]['getmoney'] : 0) + (isset($bi_pay_info[$channel['id']]['getmoney']) ? $bi_pay_info[$channel['id']]['getmoney'] : 0);;
            $item['platform_money'] = (isset($pay_info[$channel['id']]['platform_money']) ? $pay_info[$channel['id']]['platform_money'] / 10 : 0);
            $item['count'] = (isset($pay_info[$channel['id']]['count']) ? $pay_info[$channel['id']]['count'] : 0) + (isset($bi_pay_info[$channel['id']]['count']) ? $bi_pay_info[$channel['id']]['count'] : 0);;

            if ($item['money'] > 0) {
                $list[] = $item;
            }
        }

        foreach ($list as $k => $v) {
            $money_k[$k] = $v['money'];
        }
        array_multisort($money_k, SORT_DESC, $list);


        if (I('request.appid') == '' && I('request.cid') == '' && $channel_role == 'all') {
            //计算融合5+7数据
            $channelIDs = M('uchannel', null, C('RH_DB_CONFIG'))->where(array('masterID' => array('in', C('RH_SWITCHPAY_SHOWCHANNEL'))))->getfield('channelID', true);
            $rh_map['channelID'] = array('in', implode(',', $channelIDs));
            $rh_map['payType'] = array('gt', 0);
            $rh_map['state'] = array('in', '2,3');
            $rh_map['completeTime'] = array(array('egt', $start_time . ' 00:00:00'), array('elt', $end_time . ' 23:59:59'));

            $rh57 = M('uorder', null, C('RH_DB_CONFIG'))
                ->where($rh_map)
                ->field('sum(money) as money,sum(realMoney) as getmoney,count(*) count')
                ->find();
        }

        if ($action == 1) {
            //$this->assign('access_type',$access_type);
            $this->assign('rh57', $rh57);
            $this->assign('heji', $heji);
            $this->assign('bi_heji', $bi_heji);
            $this->assign('max', $max);
            $this->assign('start_time', $start_time);
            $this->assign('end_time', $end_time);
            $this->assign('list', $list);
            $this->display();

        } else {

            //导出模式
            $xlsTitle = iconv('utf-8', 'gb2312', '订单统计');//文件名称
            $fileName = date('_YmdHis') . '订单统计';//or $xlsTitle 文件名称可根据自己情况设定

            $expCellName = array('渠道名称', '渠道ID', '订单金额（元）', '订单金额（不包含平台币）', '分成金额（元）', '平台币（元）', '充值笔数');

            $cellNum = count($expCellName);

            $heji_size = 0;
            $heji_item = array('合计', '', sprintf("%.2f", $heji['money'] + $bi_heji['money'] + $rh57['money'] / 100), sprintf("%.2f", $heji['money'] + $bi_heji['money'] + $rh57['money'] / 100 - $heji['platform_money']), sprintf("%.2f", $heji['getmoney'] + $bi_heji['getmoney'] + $rh57['getmoney'] / 100), $heji['platform_money'], $heji['count'] + $bi_heji['count'] + $rh57['count']);


            if (I('request.appid') == '' && I('request.cid') == '' && $channel_role == 'all') {
                $rh57_item = array('5+7', '', sprintf("%.2f", $rh57['money'] / 100), sprintf("%.2f", $rh57['money'] / 100), sprintf("%.2f", $rh57['getmoney'] / 100), '', $rh57['count']);
                array_unshift($list, $rh57_item);
                $heji_size++;
            }
            if ($channel_role == 'all') {
                $sdk_item = array('SDK合计', '', sprintf("%.2f", $heji['money'] + $bi_heji['money']), sprintf("%.2f", $heji['money'] + $bi_heji['money'] - $heji['platform_money']), sprintf("%.2f", $heji['getmoney'] + $bi_heji['getmoney']), $heji['platform_money'], $heji['count'] + $bi_heji['count']);
                array_unshift($list, $sdk_item);
                $heji_size++;
            }

            array_unshift($list, $heji_item);
            $heji_size++;

            $dataNum = count($list);

            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for ($i = 0; $i < $cellNum; $i++) {
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '1', $expCellName[$i]);
            }
            $field = array('name', 'id', 'money', 'money_currency', 'getmoney', 'platform_money', 'count');
            // Miscellaneous glyphs, UTF-8
            for ($i = 0; $i < $dataNum; $i++) {
                for ($j = 0; $j < $cellNum; $j++) {
                    if ($i < $heji_size) {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $list[$i][$j]);
                    } else {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $list[$i][$field[$j]]);
                    }
                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit(1);
        }
    }

}