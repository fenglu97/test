<?php
/**
 * 统计（其他平台）
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class OtherStatisticsController extends AdminbaseController{

    public function _initialize() {
        parent::_initialize();
        $this->Inpour = M('Inpour');
        $this->appids = M('game')->where(array('access_type'=>2))->getfield('id',true);
    }

    public function pay_statistics()
    {
        $post_data = $_REQUEST;
        //0为普通模式 1为导出模式
        $action = !empty($post_data['action'])?$post_data['action']:0;
        //日期最大限制
        $max = date('Y-m-d',time()-3600*24);

        $start_time = $post_data['start_time']?$post_data['start_time']:date('Y-m-d',strtotime('-1 week'));
        $end_time = $post_data['end_time']?$post_data['end_time']:$max;
        $count =(strtotime($end_time)-strtotime($start_time))/(3600*24)+1;
        $page = $this->page($count, 20);


        $channel_name = '--';
        $game_name = '--';
        $p = I('get.p')?I('get.p'):1;
        $map = array();

        $game_role = session('game_role');

        if($game_role !='all')
        {
            $map['appid'] = array('in',$game_role);
        }
        else
        {
            if($this->appids)
            {
                $map['appid'] = array('in',implode(',',$this->appids));
            }
            else
            {
                $map['appid'] = array('in','');
            }
        }

        $channel_role = session('channel_role');

        if($channel_role !='all')
        {
            $map['cid'] = array('in',$channel_role);
        }

        if(!empty($post_data['cid']))
        {
            $map['cid'] = $post_data['cid'];
            $channel_name = M('Channel')->where(array('id'=>$post_data['cid']))->getfield('name');
        }
        if(!empty($post_data['appid']))
        {
            $map['appid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id'=>$post_data['appid']))->getfield('game_name');
        }





        $datearr = array ();

        if(I('action') == 1)
        {
            $end_time_conf = strtotime($end_time.' 23:59:59');

            $start_time_conf = strtotime($start_time.' 00:00:00');
            if(($end_time_conf-$start_time_conf) > 31*3600*24)
            {
                $this->error('最大能导出31天的数据');
            }
        }
        else
        {
            $end_time_conf = strtotime($end_time.' 23:59:59')-($p-1)*24*3600*20;

            $start_time_conf =$end_time_conf-24*3600*20;
        }

        if($start_time_conf <= strtotime($start_time.' 00:00:00'))
        {
            $start_time_conf = strtotime($start_time.' 00:00:00');
            $map['time'] = array(array('egt',date('Y-m-d',$start_time_conf)),array('elt',date('Y-m-d',$end_time_conf)));
        }
        else
        {
            $map['time'] = array(array('gt',date('Y-m-d',$start_time_conf)),array('elt',date('Y-m-d',$end_time_conf)));
        }




        while ( $start_time_conf < $end_time_conf )
        {
            $datearr [] = date ( 'Y-m-d', $end_time_conf);
            $end_time_conf = $end_time_conf - 3600 * 24;
        }




		$info = M('pay_by_day')->
		field('time,sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_box_user),sum(new_user),sum(valid_new_user),sum(new_user_counts),sum(new_user_number),sum(new_user_amount)')->
		where($map)->
		group('time')->
		order('time desc')->
		select();

        $map['time'] = array(array('egt',$start_time),array('elt',$end_time));

		$heji = M('pay_by_day')->
		field('sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_box_user),sum(new_user),sum(valid_new_user),sum(new_user_counts),sum(new_user_number),sum(new_user_amount)')->
		where($map)->
		find();

        $new_info = array();
        foreach($info as $v)
        {
            $new_info[$v['time']] = $v;
        }

		unset($info);
		//dump($new_info);die;
		$result = array();
		//组装数据;
		foreach($datearr as $v)
		{
			$active_users_v = isset($new_info[$v]['sum(active_user)'])?$new_info[$v]['sum(active_user)']:0;
			$pay_counts_v = isset($new_info[$v]['sum(pay_counts)'])?$new_info[$v]['sum(pay_counts)']:0;
			$uid_counts_v = isset($new_info[$v]['sum(pay_number)'])?$new_info[$v]['sum(pay_number)']:0;
			$money_v = isset($new_info[$v]['sum(pay_amount)'])?$new_info[$v]['sum(pay_amount)']:'0.00';
			$active_arpu = round($money_v/$active_users_v,2);
			$new_box_users_v = isset($new_info[$v]['sum(new_box_user)'])?$new_info[$v]['sum(new_box_user)']:0;
			$new_users_v = isset($new_info[$v]['sum(new_user)'])?$new_info[$v]['sum(new_user)']:0;
			$valid_new_users_v = isset($new_info[$v]['sum(valid_new_user)'])?$new_info[$v]['sum(valid_new_user)']:0;
			$newuser_paycounts_v = isset($new_info[$v]['sum(new_user_counts)'])?$new_info[$v]['sum(new_user_counts)']:0;
			$newuser_uidcounts_v = isset($new_info[$v]['sum(new_user_number)'])?$new_info[$v]['sum(new_user_number)']:0;
			$newuser_money_v = isset($new_info[$v]['sum(new_user_amount)'])?$new_info[$v]['sum(new_user_amount)']:'0.00';
			$newuser_arpu = round($newuser_money_v/$new_users_v,2);
			$pay_lv = round($uid_counts_v/$active_users_v,4)*100;
			$pay_lv.='%';

			$result[] = array($v,$active_users_v,$pay_counts_v,$uid_counts_v,$money_v,$new_box_users_v,$new_users_v,$valid_new_users_v,$newuser_paycounts_v,$newuser_uidcounts_v,$newuser_money_v,$active_arpu,$newuser_arpu,$pay_lv);
		}


        if($action == 0)
        {
            $this->assign('p',$p);
            $this->assign('heji',$heji);
            $this->assign('channel_name',$channel_name);
            $this->assign('game_name',$game_name);
            $this->assign('result',$result);
            $this->assign('start_time',$start_time);
            $this->assign('end_time',$end_time);
            $this->assign('channel_list',get_channel_list(I('cid'),$post_data['channel_type']));
            $this->assign('game_list',get_game_list(I('appid'),1, 1,'all','all','all','',2));
            $this->assign('page',$page->show('Admin'));
            $this->assign('max',$max);
            $this->selected_channel_type = $post_data['channel_type'];
            $this->channel_type = C('channel_type');
            $this->display();
        }
        else
        {
            //导出模式
            $xlsTitle = iconv('utf-8', 'gb2312', '订单统计');//文件名称
            $fileName = date('_YmdHis').'订单统计';//or $xlsTitle 文件名称可根据自己情况设定

			$expCellName = array('日期','游戏名称','渠道名称','活跃用户','充值次数','充值人数','总充值金额','新增APP用户','新增用户','新增用户(排重)','新增用户充值次数','新增用户充值人数','新增用户总充值金额','活跃Arpu','新增用户Arpu','付费率');

			$cellNum = count($expCellName);
			$heji_item = array('合计',$heji['sum(active_user)'],$heji['sum(pay_counts)'],
				$heji['sum(pay_number)'],$heji['sum(pay_amount)'],$heji['sum(new_box_user)'],$heji['sum(new_user)'],
				$heji['sum(valid_new_user)'],
				$heji['sum(new_user_counts)'],$heji['sum(new_user_number)'],
				$heji['sum(new_user_amount)'],
				round($heji['sum(pay_amount)']/$heji['sum(active_user)'],2),
				round($heji['sum(new_user_amount)']/$heji['sum(new_user)'],2),
				round($heji['sum(pay_number)']/$heji['sum(active_user)'],4)*100);
			$heji_item[12].= '%';
				
			array_unshift($result,$heji_item);
			$dataNum = count($result);

            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for($i=0;$i<$cellNum;$i++){
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'1', $expCellName[$i]);
            }
            // Miscellaneous glyphs, UTF-8
            for($i=0;$i<$dataNum;$i++){
                for($j=0;$j<$cellNum;$j++){
                    if($j ==0)
                    {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $result[$i][$j]);
                    }
                    elseif($j ==1)
                    {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $game_name);
                    }
                    elseif($j == 2)
                    {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $channel_name);
                    }
                    else
                    {
                        $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $result[$i][$j-2]);
                    }

                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit(1);
        }
    }

    public function active_player()
    {
        $post_data = I('post.');
        $time = $post_data['time']?$post_data['time']:date('Y-m-d',time());
        $channel_name = '--';
        $game_name = '--';

        $map = array();

        $game_role = session('game_role');

        if($game_role !='all')
        {
            $map['appid'] = array('in',$game_role);
        }
        else
        {
            if($this->appids)
            {
                $map['appid'] = array('in',implode(',',$this->appids));
            }
            else
            {
                $map['appid'] = array('in','');
            }
        }

        $channel_role = session('channel_role');

        if($channel_role !='all')
        {
            $map['channel'] = array('in',$channel_role);
        }

        if(!empty($post_data['cid']))
        {
            $map['channel'] = $post_data['cid'];
            $channel_name = M('Channel')->where(array('id'=>$post_data['cid']))->getfield('name');
        }
        if(!empty($post_data['appid']))
        {
            $map['appid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id'=>$post_data['appid']))->getfield('game_name');
        }




        $start_time = strtotime($time.' 00:00:00');
        $end_time = strtotime($time.' 23:59:59');

        $map['create_time'] = array(array('egt',$start_time),array('elt',$end_time));

        $current_player_login_logs = M('player_login_logs'.date('Ym',$start_time));

        $day_active = $current_player_login_logs
            ->field('count(distinct(uid)) as count')
            ->where($map)
            ->cache(true)
            ->find();



        $start_time = $start_time-6*24*3600;

        $map['create_time'][0] = array('egt',$start_time);

		$week_active = $current_player_login_logs
		->field('count(*) as count')
		->where($map)
		->group('uid,app_uid')
		->having('count >= 3')
		->cache(true)
		->select();

		$week_active1 = array();
		if(date('Ym',$start_time)!=date('Ym',$start_time+6*24*3600))
		{
			$week_active1 = M('player_login_logs'.date('Ym',$start_time))
				->field('count(*) as count')
				->where($map)
				->group('uid,app_uid')
				->having('count >= 3')
				->cache(true)
				->select();
		}

        $week_active = count($week_active)+count($week_active1);

        $start_time = $start_time-23*24*3600;

        $map['create_time'][0] = array('egt',$start_time);

		$month_active = $current_player_login_logs
		->field('count(*) as count')
		->where($map)
		->group('uid,app_uid')
		->having('count >= 7')
		->cache(true)
		->select();

		$month_active1 = array();
		$month_active2 = array();
		if(date('Ym',$start_time)!=date('Ym',$start_time+29*24*3600)) {
			$month_active1 = M('player_login_logs' . date('Ym', $start_time))
				->field('count(*) as count')
				->where($map)
				->group('uid,app_uid')
				->having('count >= 7')
				->cache(true)
				->select();

			if (date('m', $start_time+29*24*3600)-date('m', $start_time)== 2)
			{
				$month_active2 = M('player_login_logs' . date('Y', $start_time).'02')
					->field('count(*) as count')
					->where($map)
					->group('uid,app_uid')
					->having('count >= 7')
					->cache(true)
					->select();
			}

        }

        $month_active = count($month_active)+count($month_active1)+count($month_active2);

        $this->assign('month_active',$month_active);
        $this->assign('week_active',$week_active);
        $this->assign('day_active',$day_active);
        $this->assign('channel_name',$channel_name);
        $this->assign('game_name',$game_name);
        $this->assign('time',$time);
        $this->assign('channel_list',get_channel_list(I('cid'),$post_data['channel_type']));
        $this->assign('game_list',get_game_list(I('appid'),1, 1,'all','all','all','',2));
        $this->selected_channel_type = $post_data['channel_type'];
        $this->channel_type = C('channel_type');
        $this->display();
    }



    public function today_pay()
    {
        $post_data = $_REQUEST;
        $map = array();
        $map_other = array();
        $channel_name = '--';
        $heji = array();

        $game_role = session('game_role');

        if($game_role !='all')
        {
            $map['appid'] = array('in',$game_role);
            $map_other['a.appid'] = array('in',$game_role);
        }
        else
        {
            if($this->appids)
            {
                $map['appid'] = array('in',implode(',',$this->appids));
                $map_other['a.appid'] = array('in',implode(',',$this->appids));
            }
            else
            {
                $map['appid'] = array('in','');
                $map_other['a.appid'] = array('in','');
            }
        }

        $channel_role = session('channel_role');

        if($channel_role !='all')
        {
            $map['cid'] = array('in',$channel_role);
            $map['channel'] = array('in',$channel_role);
            $map_other['a.cid'] = array('in',$channel_role);
        }

        if(!empty($post_data['cid']))
        {
            $map['cid'] = $post_data['cid'];
            $map['channel'] = $post_data['cid'];
            $map_other['a.cid'] = $post_data['cid'];
            $channel_name = M('Channel')->where(array('id'=>$post_data['cid']))->getfield('name');
        }
        if(!empty($post_data['appid']))
        {
            $map['appid'] = $post_data['appid'];
            $map_other['a.appid'] = $post_data['appid'];
        }

        $today_day = strtotime(date('Y-m-d',time()));

        $map['create_time'] = array(array('egt',$today_day),array('lt',$today_day+3600*24));
        $map_other['a.create_time'] = array(array('egt',$today_day),array('lt',$today_day+3600*24));
        $map_other['b.first_login_time'] = array(array('egt',$today_day),array('lt',$today_day+3600*24));

//        $active_users = M('player_login_logs'.date('Ym',$today_day))
//            ->field("count(distinct(uid)) as count,count(distinct(machine_code)) as machine_count,appid")
//            ->where($map)
//            ->group('appid')
//            ->select();

		$sub_query = M('player_login_logs'.date('Ym',$today_day))
			->where($map)
			->group('appid,uid,app_uid')
			->buildSql();
		$model = new \Think\Model;

		$active_users = $model->table($sub_query.' a')->field('count(*) as count ,count(distinct(machine_code)) as machine_count,appid')->group('a.appid')->cache(true)->select();


        //重新组装数据

        $activer_users_info = array();

        if(is_array($active_users))
        {
            foreach($active_users as $v)
            {
                $activer_users_info[$v['appid']] = $v;
                $heji['active_user'] = $heji['active_user']+$v['count'];
                $heji['active_machine_count'] = $heji['active_machine_count']+$v['machine_count'];
            }
        }
        unset($active_users);




		$pay_info = $this->Inpour
		->field("count(*) as pay_counts,count(distinct uid,app_uid) as uid_counts,sum(money) as money,appid")
		->where(array_merge($map,array('status'=>1)))
		->group('appid')
		->select();

        $pay_info_info = array();

        if(is_array($pay_info))
        {
            foreach($pay_info as $v)
            {
                $pay_info_info[$v['appid']] = $v;
                $heji['pay_counts'] = $heji['pay_counts'] + $v['pay_counts'];
                $heji['pay_number'] = $heji['pay_number'] + $v['uid_counts'];
                $heji['pay_amount'] = $heji['pay_amount'] + $v['money'];

			}
		}
		unset($pay_info);

		$new_box_users = M('player')
			->where($map)
			->group('appid')
			->getfield('appid,count(*)');



		if(is_array($new_box_users))
		{
			foreach($new_box_users as $v)
			{
				$heji['new_box_user'] = $heji['new_box_user'] + $v;
			}
		}

		$new_users_map = $map;
		$new_users_map['first_login_time'] = $new_users_map['create_time'];
		unset($new_users_map['create_time']);

		$new_users = M('app_player')
		->field("count(*) as count,count(distinct(machine_code)) valid_new_user,appid")
		->where($new_users_map)
		->group('appid')
		->select();


        $new_users_info =array();

        if(is_array($new_users))
        {
            foreach($new_users as $v)
            {
                $new_users_info[$v['appid']] = $v;
                $heji['new_user'] = $heji['new_user'] + $v['count'];
                $heji['valid_new_user'] = $heji['valid_new_user'] + $v['valid_new_user'];
            }
        }
        unset($new_users);

		$newuser_pay = $this->Inpour
			->alias("a")
			->join('__APP_PLAYER__ as b ON a.app_uid = b.id')
			->field("count(distinct(a.id)) as pay_counts,count(distinct a.uid,a.app_uid) as uid_counts,sum(a.money) as money,a.appid as appid")
			->where(array_merge($map_other,array('a.status'=>1)))
			->group('a.appid')
			->select();
			
		

        $newuser_pay_info = array();

        if(is_array($newuser_pay))
        {
            foreach($newuser_pay as $v)
            {
                $newuser_pay_info[$v['appid']] = $v;
                $heji['new_user_counts'] = $heji['new_user_counts'] + $v['pay_counts'];
                $heji['new_user_number'] = $heji['new_user_number'] + $v['uid_counts'];
                $heji['new_user_amount'] = $heji['new_user_amount'] + $v['money'];
            }
        }

        unset($newuser_pay);

        $game_map = array('status'=>1,'source'=>1);
        if($map['appid'])
        {
            $game_map['id'] = $map['appid'];
        }

        $games = M('game')->field('id,game_name')->where($game_map)->select();

		$games_count = count($games);
		$list = array();
		foreach($games as $game)
		{
			$item = array(
			'game_name'=>$game['game_name'],
			'active_user'=>isset($activer_users_info[$game['id']]['count'])?$activer_users_info[$game['id']]['count']:0,
			'active_machine_count'=>isset($activer_users_info[$game['id']]['machine_count'])?$activer_users_info[$game['id']]['machine_count']:0,
			'pay_counts'=>isset($pay_info_info[$game['id']]['pay_counts'])?$pay_info_info[$game['id']]['pay_counts']:0,
			'pay_number'=>isset($pay_info_info[$game['id']]['uid_counts'])?$pay_info_info[$game['id']]['uid_counts']:0,
			'pay_amount'=>isset($pay_info_info[$game['id']]['money'])?$pay_info_info[$game['id']]['money']:'0.00',
			'active_arpu'=>round($pay_info_info[$game['id']]['money']/$activer_users_info[$game['id']]['count'],2),
			'active_machine_arpu'=>round($pay_info_info[$game['id']]['money']/$activer_users_info[$game['id']]['machine_count'],2),
			'new_box_user'=>isset($new_box_users[$game['id']])?$new_box_users[$game['id']]:0,
			'new_user'=>isset($new_users_info[$game['id']]['count'])?$new_users_info[$game['id']]['count']:0,
			'valid_new_user'=>isset($new_users_info[$game['id']]['valid_new_user'])?$new_users_info[$game['id']]['valid_new_user']:0,
			'new_user_counts'=>isset($newuser_pay_info[$game['id']]['pay_counts'])?$newuser_pay_info[$game['id']]['pay_counts']:0,
			'new_user_number'=>isset($newuser_pay_info[$game['id']]['uid_counts'])?$newuser_pay_info[$game['id']]['uid_counts']:0,
			'new_user_amount'=>isset($newuser_pay_info[$game['id']]['money'])?$newuser_pay_info[$game['id']]['money']:'0.00',
			'new_user_arpu'=>round($newuser_pay_info[$game['id']]['money']/$new_users_info[$game['id']]['count'],2),
			);

			if(!($item['active_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0 && $item['new_user'] == 0 && $item['new_box_user'] == 0
			&& $item['new_user_counts'] == 0 && $item['new_user_number'] == 0 && $item['new_user_amount'] == 0) || $games_count==1 )
			{
				$list[] = $item;
			}
		}

        $heji['pay_amount'] = sprintf("%.2f",$heji['pay_amount']);
        $heji['new_user_amount'] = sprintf("%.2f",$heji['new_user_amount']);


        foreach($list as $k=>$v)
        {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $list);

        $this->assign('heji',$heji);
        $this->assign('channel_name',$channel_name);
        $this->assign('list',$list);
        $this->assign('channel_list',get_channel_list(I('cid'),$post_data['channel_type']));
        $this->assign('game_list',get_game_list(I('appid'),1, 1,'all','all','all','',2));
        $this->selected_channel_type = $post_data['channel_type'];
        $this->channel_type = C('channel_type');
        $this->display();

    }

    public function today_pay_by_channel()
    {
        $post_data = $_REQUEST;
        $map = array();
        $map_other = array();
        $game_name = '--';
        $heji = array();

        $game_role = session('game_role');

        if($game_role !='all')
        {
            $map['appid'] = array('in',$game_role);
            $map_other['a.appid'] = array('in',$game_role);
        }
        else
        {
            if($this->appids)
            {
                $map['appid'] = array('in',implode(',',$this->appids));
                $map_other['a.appid'] = array('in',implode(',',$this->appids));
            }
            else
            {
                $map['appid'] = array('in','');
                $map_other['a.appid'] = array('in','');
            }
        }

        $channel_role = session('channel_role');

        if($channel_role !='all')
        {
            $map['cid'] = array('in',$channel_role);
            $map['channel'] = array('in',$channel_role);
            $map_other['a.cid'] = array('in',$channel_role);
        }

        if(!empty($post_data['cid']))
        {
            $map['cid'] = $post_data['cid'];
            $map['channel'] = $post_data['cid'];
            $map_other['a.cid'] = $post_data['cid'];
        }
        if(!empty($post_data['appid']))
        {
            $map['appid'] = $post_data['appid'];
            $map_other['a.appid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id'=>$post_data['appid']))->getfield('game_name');
        }



        $today_day = strtotime(date('Y-m-d',time()));


        $map['create_time'] = array(array('egt',$today_day),array('lt',$today_day+3600*24));
        $map_other['a.create_time'] = array(array('egt',$today_day),array('lt',$today_day+3600*24));
        $map_other['b.first_login_time'] = array(array('egt',$today_day),array('lt',$today_day+3600*24));




//        $active_users = M('player_login_logs'.date('Ym',$today_day))
//            ->field("count(distinct(uid)) as count,count(distinct(machine_code)) as machine_count,channel")
//            ->where($map)
//            ->group('channel')
//            ->select();

		$sub_query = M('player_login_logs'.date('Ym',$today_day))
			->where($map)
			->group('channel,uid,app_uid')
			->buildSql();
		$model = new \Think\Model;

        $active_users = $model->table($sub_query.' a')->field('count(*) as count ,count(distinct(machine_code)) as machine_count,channel')->group('a.channel')->cache(true)->select();
        //重新组装数据

        $activer_users_info = array();

        if(is_array($active_users))
        {
            foreach($active_users as $v)
            {
                $activer_users_info[$v['channel']] = $v;
                $heji['active_user'] = $heji['active_user']+$v['count'];
                $heji['active_machine_count'] = $heji['active_machine_count']+$v['machine_count'];
            }
        }
        unset($active_users);




		$pay_info = $this->Inpour
		->field("count(*) as pay_counts,count(distinct uid,app_uid) as uid_counts,sum(money) as money,cid")
		->where(array_merge($map,array('status'=>1)))
		->group('cid')
		->select();

        $pay_info_info = array();

        if(is_array($pay_info))
        {
            foreach($pay_info as $v)
            {
                $pay_info_info[$v['cid']] = $v;
                $heji['pay_counts'] = $heji['pay_counts'] + $v['pay_counts'];
                $heji['pay_number'] = $heji['pay_number'] + $v['uid_counts'];
                $heji['pay_amount'] = $heji['pay_amount'] + $v['money'];

			}
		}
		unset($pay_info);


		$new_box_users = M('player')
			->where($map)
			->group('channel')
			->getfield('channel,count(*)');

		if(is_array($new_box_users))
		{
			foreach($new_box_users as $v)
			{
				$heji['new_box_user'] = $heji['new_box_user'] + $v;
			}
		}

        $new_users_map = $map;
        $new_users_map['first_login_time'] = $new_users_map['create_time'];
        unset($new_users_map['create_time']);

		$new_users =  M('app_player')
			->field("count(*) as count,count(distinct(machine_code)) as valid_new_user,channel")
			->where($new_users_map)
			->group('channel')
			->select();


        $new_users_info =array();

        if(is_array($new_users))
        {
            foreach($new_users as $v)
            {
                $new_users_info[$v['channel']] = $v;
                $heji['new_user'] = $heji['new_user'] + $v['count'];
                $heji['valid_new_user'] = $heji['valid_new_user'] + $v['valid_new_user'];
            }
        }
        unset($new_users);


		$newuser_pay = $this->Inpour
		->alias("a")
	    ->join('__APP_PLAYER__ as b ON a.app_uid = b.id')
		->field("count(distinct(a.id)) as pay_counts,count(distinct a.uid,a.app_uid) as uid_counts,sum(a.money) as money,a.cid as cid")
		->where(array_merge($map_other,array('a.status'=>1)))
		->group('a.cid')
		->select();


        $newuser_pay_info = array();

        if(is_array($newuser_pay))
        {
            foreach($newuser_pay as $v)
            {
                $newuser_pay_info[$v['cid']] = $v;
                $heji['new_user_counts'] = $heji['new_user_counts'] + $v['pay_counts'];
                $heji['new_user_number'] = $heji['new_user_number'] + $v['uid_counts'];
                $heji['new_user_amount'] = $heji['new_user_amount'] + $v['money'];
            }
        }

        unset($newuser_pay);

        $channel_map = array('status'=>1);
        if($map['cid'])
        {
            $channel_map['id'] = $map['cid'];
        }

        $channels = M('Channel')->field('id,name')->where($channel_map)->select();

		$channel_count = count($channels);
		
		$list = array();
		foreach($channels as $channel)
		{
			$item = array(
			'channel_name'=>$channel['name'],
			'active_user'=>isset($activer_users_info[$channel['id']]['count'])?$activer_users_info[$channel['id']]['count']:0,
			'active_machine_count'=>isset($activer_users_info[$channel['id']]['machine_count'])?$activer_users_info[$channel['id']]['machine_count']:0,
			'pay_counts'=>isset($pay_info_info[$channel['id']]['pay_counts'])?$pay_info_info[$channel['id']]['pay_counts']:0,
			'pay_number'=>isset($pay_info_info[$channel['id']]['uid_counts'])?$pay_info_info[$channel['id']]['uid_counts']:0,
			'pay_amount'=>isset($pay_info_info[$channel['id']]['money'])?$pay_info_info[$channel['id']]['money']:'0.00',
			'active_arpu'=>round($pay_info_info[$channel['id']]['money']/$activer_users_info[$channel['id']]['count'],2),
			'active_machine_arpu'=>round($pay_info_info[$channel['id']]['money']/$activer_users_info[$channel['id']]['machine_count'],2),
             'new_box_user'=>isset($new_box_users[$channel['id']])?$new_box_users[$channel['id']]:0,
			'new_user'=>isset($new_users_info[$channel['id']]['count'])?$new_users_info[$channel['id']]['count']:0,
			'valid_new_user'=>isset($new_users_info[$channel['id']]['valid_new_user'])?$new_users_info[$channel['id']]['valid_new_user']:0,
			'new_user_counts'=>isset($newuser_pay_info[$channel['id']]['pay_counts'])?$newuser_pay_info[$channel['id']]['pay_counts']:0,
			'new_user_number'=>isset($newuser_pay_info[$channel['id']]['uid_counts'])?$newuser_pay_info[$channel['id']]['uid_counts']:0,
			'new_user_amount'=>isset($newuser_pay_info[$channel['id']]['money'])?$newuser_pay_info[$channel['id']]['money']:'0.00',
			'new_user_arpu'=>round($newuser_pay_info[$channel['id']]['money']/$new_users_info[$channel['id']]['count'],2),
			);
			if(!($item['active_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0 && $item['new_user'] == 0
			&& $item['new_user_counts'] == 0 && $item['new_user_number'] == 0 && $item['new_user_amount'] == 0) || $channel_count==1 )
			{
				$list[] = $item;
			}
		}

        $heji['pay_amount'] = sprintf("%.2f",$heji['pay_amount']);
        $heji['new_user_amount'] = sprintf("%.2f",$heji['new_user_amount']);

        foreach($list as $k=>$v)
        {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $list);

        $this->assign('heji',$heji);
        $this->assign('game_name',$game_name);
        $this->assign('list',$list);
        $this->assign('channel_list',get_channel_list(I('cid'),$post_data['channel_type']));
        $this->assign('game_list',get_game_list(I('appid'),1, 1,'all','all','all','',2));
        $this->selected_channel_type = $post_data['channel_type'];
        $this->channel_type = C('channel_type');

        $this->display();
    }

    public function pay_statistics_by_channel()
    {
        $post_data = $_REQUEST;

        //日期最大限制
        $max = date('Y-m-d',time()-3600*24);

        $start_time = $post_data['start_time']?$post_data['start_time']:date('Y-m-d',strtotime('-1 week'));
        $end_time = $post_data['end_time']?$post_data['end_time']:$max;
        $game_name = '--';

        $map = array();

        $game_role = session('game_role');

        if($game_role !='all')
        {
            $map['appid'] = array('in',$game_role);
        }
        else
        {
            if($this->appids)
            {
                $map['appid'] = array('in',implode(',',$this->appids));
            }
            else
            {
                $map['appid'] = array('in','');
            }
        }

        $channel_role = session('channel_role');

        if($channel_role !='all')
        {
            $map['cid'] = array('in',$channel_role);
        }

        if(!empty($post_data['cid']))
        {
            $map['cid'] = $post_data['cid'];
        }
        if(!empty($post_data['appid']))
        {
            $map['appid'] = $post_data['appid'];
            $game_name = M('Game')->where(array('id'=>$post_data['appid']))->getfield('game_name');
        }





        if(!empty($start_time) && !empty($end_time))
        {
            $map['time'] = array(array('egt',$start_time),array('elt',$end_time));
        }


		$heji = M('pay_by_day')->
		field('sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_box_user),sum(new_user),sum(valid_new_user),sum(new_user_counts),sum(new_user_number),sum(new_user_amount)')->
		where($map)->
		find();


		$list = M('pay_by_day')->
		field('cid,sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_box_user),sum(new_user),sum(valid_new_user),sum(new_user_counts),sum(new_user_number),sum(new_user_amount)')->
		where($map)->
		group('cid')->
		select();

        $list_info = array();

        if(is_array($list))
        {
            foreach($list as $v)
            {
                $list_info[$v['cid']] = $v;
            }
        }
        unset($list);


        $channel_map = array('status'=>1);
        if($map['cid'])
        {
            $channel_map['id'] = $map['cid'];
        }

		$channels = M('Channel')->field('id,name')->where($channel_map)->select();
    
		$channel_count = count($channels);
		
		$result = array();
		foreach($channels as $channel)
		{
			$item = array(
			'channel_name'=>$channel['name'],
			'active_user'=>isset($list_info[$channel['id']]['sum(active_user)'])?$list_info[$channel['id']]['sum(active_user)']:0,
			'pay_counts'=>isset($list_info[$channel['id']]['sum(pay_counts)'])?$list_info[$channel['id']]['sum(pay_counts)']:0,
			'pay_number'=>isset($list_info[$channel['id']]['sum(pay_number)'])?$list_info[$channel['id']]['sum(pay_number)']:0,
			'pay_amount'=>isset($list_info[$channel['id']]['sum(pay_amount)'])?$list_info[$channel['id']]['sum(pay_amount)']:'0.00',
			'active_arpu'=>round($list_info[$channel['id']]['sum(pay_amount)']/$list_info[$channel['id']]['sum(active_user)'],2),
	'new_box_user'=>isset($list_info[$channel['id']]['sum(new_box_user)'])?$list_info[$channel['id']]['sum(new_box_user)']:0,
			'new_user'=>isset($list_info[$channel['id']]['sum(new_user)'])?$list_info[$channel['id']]['sum(new_user)']:0,
			'valid_new_user'=>isset($list_info[$channel['id']]['sum(valid_new_user)'])?$list_info[$channel['id']]['sum(valid_new_user)']:0,
			'new_user_counts'=>isset($list_info[$channel['id']]['sum(new_user_counts)'])?$list_info[$channel['id']]['sum(new_user_counts)']:0,
			'new_user_number'=>isset($list_info[$channel['id']]['sum(new_user_number)'])?$list_info[$channel['id']]['sum(new_user_number)']:0,
			'new_user_amount'=>isset($list_info[$channel['id']]['sum(new_user_amount)'])?$list_info[$channel['id']]['sum(new_user_amount)']:'0.00',
			'new_user_arpu'=>round($list_info[$channel['id']]['sum(new_user_amount)']/$list_info[$channel['id']]['sum(new_user)'],2),
			);
			if(!($item['active_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0 && $item['new_box_user'] == 0 && $item['new_user'] == 0
			&& $item['new_user_counts'] == 0 && $item['new_user_number'] == 0 && $item['new_user_amount'] == 0) || $channel_count==1 )
			{
				$result[] = $item;
			}
		}


		foreach($result as $k=>$v)
		{
			$money_k[$k] = $v['pay_amount'];
		}
		array_multisort($money_k, SORT_DESC, $result);


        $this->assign('heji',$heji);
        $this->assign('game_name',$game_name);
        $this->assign('result',$result);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('channel_list',get_channel_list(I('cid'),$post_data['channel_type']));
        $this->assign('game_list',get_game_list(I('appid'),1, 1,'all','all','all','',2));
        $this->assign('max',$max);
        $this->selected_channel_type = $post_data['channel_type'];
        $this->channel_type = C('channel_type');
        $this->display();
    }

    public function pay_statistics_by_game()
    {
        $post_data = $_REQUEST;

        //日期最大限制
        $max = date('Y-m-d',time()-3600*24);

        $start_time = $post_data['start_time']?$post_data['start_time']:date('Y-m-d',strtotime('-1 week'));
        $end_time = $post_data['end_time']?$post_data['end_time']:$max;
        $channel_name = '--';

        $map = array();

        $game_role = session('game_role');

        if($game_role !='all')
        {
            $map['appid'] = array('in',$game_role);
        }
        else
        {
            if($this->appids)
            {
                $map['appid'] = array('in',implode(',',$this->appids));
            }
            else
            {
                $map['appid'] = array('in','');
            }
        }

        $channel_role = session('channel_role');

        if($channel_role !='all')
        {
            $map['cid'] = array('in',$channel_role);
        }

        if(!empty($post_data['cid']))
        {
            $map['cid'] = $post_data['cid'];
            $channel_name = M('Channel')->where(array('id'=>$post_data['cid']))->getfield('name');
        }
        if(!empty($post_data['appid']))
        {
            $map['appid'] = $post_data['appid'];
        }




        if(!empty($start_time) && !empty($end_time))
        {
            $map['time'] = array(array('egt',$start_time),array('elt',$end_time));
        }


		$heji = M('pay_by_day')->
		field('sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_box_user),sum(new_user),sum(valid_new_user),sum(new_user_counts),sum(new_user_number),sum(new_user_amount)')->
		where($map)->
		find();


		$list = M('pay_by_day')->
		field('appid,sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_box_user),sum(new_user),sum(valid_new_user),sum(new_user_counts),sum(new_user_number),sum(new_user_amount)')->
		where($map)->
		group('appid')->
		select();

        $list_info = array();

        if(is_array($list))
        {
            foreach($list as $v)
            {
                $list_info[$v['appid']] = $v;
            }
        }
        unset($list);

        $game_map = array('status'=>1,'source'=>1);
        if($map['appid'])
        {
            $game_map['id'] = $map['appid'];
        }

        $games = M('game')->field('id,game_name')->where($game_map)->select();

		$games_count = count($games);
		
		$result = array();
		foreach($games as $game)
		{
			$item = array(
			'game_name'=>$game['game_name'],
			'active_user'=>isset($list_info[$game['id']]['sum(active_user)'])?$list_info[$game['id']]['sum(active_user)']:0,
			'pay_counts'=>isset($list_info[$game['id']]['sum(pay_counts)'])?$list_info[$game['id']]['sum(pay_counts)']:0,
			'pay_number'=>isset($list_info[$game['id']]['sum(pay_number)'])?$list_info[$game['id']]['sum(pay_number)']:0,
			'pay_amount'=>isset($list_info[$game['id']]['sum(pay_amount)'])?$list_info[$game['id']]['sum(pay_amount)']:'0.00',
			'active_arpu'=>round($list_info[$game['id']]['sum(pay_amount)']/$list_info[$game['id']]['sum(active_user)'],2),
                        'new_box_user'=>isset($list_info[$game['id']]['sum(new_box_user)'])?$list_info[$game['id']]['sum(new_box_user)']:0,
			'new_user'=>isset($list_info[$game['id']]['sum(new_user)'])?$list_info[$game['id']]['sum(new_user)']:0,
			'valid_new_user'=>isset($list_info[$game['id']]['sum(valid_new_user)'])?$list_info[$game['id']]['sum(valid_new_user)']:0,
			'new_user_counts'=>isset($list_info[$game['id']]['sum(new_user_counts)'])?$list_info[$game['id']]['sum(new_user_counts)']:0,
			'new_user_number'=>isset($list_info[$game['id']]['sum(new_user_number)'])?$list_info[$game['id']]['sum(new_user_number)']:0,
			'new_user_amount'=>isset($list_info[$game['id']]['sum(new_user_amount)'])?$list_info[$game['id']]['sum(new_user_amount)']:'0.00',
			'new_user_arpu'=>round($list_info[$game['id']]['sum(new_user_amount)']/$list_info[$game['id']]['sum(new_user)'],2),
			);
				if(!($item['active_user'] == 0 && $item['pay_counts'] == 0 && $item['pay_number'] == 0 && $item['pay_amount'] == 0 && $item['new_box_user'] == 0 && $item['new_user'] == 0
			&& $item['new_user_counts'] == 0 && $item['new_user_number'] == 0 && $item['new_user_amount'] == 0) || $games_count==1 )
			{
				$result[] = $item;
			}
		}
		//die;

        foreach($result as $k=>$v)
        {
            $money_k[$k] = $v['pay_amount'];
        }
        array_multisort($money_k, SORT_DESC, $result);

        $this->assign('heji',$heji);
        $this->assign('channel_name',$channel_name);
        $this->assign('result',$result);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('channel_list',get_channel_list(I('cid'),$post_data['channel_type']));
        $this->assign('game_list',get_game_list(I('appid'),1, 1,'all','all','all','',2));
        $this->assign('max',$max);
        $this->selected_channel_type = $post_data['channel_type'];
        $this->channel_type = C('channel_type');
        $this->display();
    }

}