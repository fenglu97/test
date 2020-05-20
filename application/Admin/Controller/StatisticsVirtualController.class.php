<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class StatisticsVirtualController extends AdminbaseController{

	public function _initialize()
	{
		parent::_initialize();
		$this->Inpour = M('Inpour');
	}

	public function pay_statistics()
	{
		$post_data = $_REQUEST;
		//0为普通模式 1为导出模式
		$action = !empty($post_data['action'])?$post_data['action']:0;
		//日期最大限制
		$max = date('Y-m-d',time()-3600*24);

		$arpu_start = $post_data['arpu_start']?$post_data['arpu_start']:10;
		$arpu_end = $post_data['arpu_end']?$post_data['arpu_end']:20;
		$start_time = $post_data['start_time']?$post_data['start_time']:date('Y-m-d',strtotime('-1 week'));
		$end_time = $post_data['end_time']?$post_data['end_time']:$max;
		$count =(strtotime($end_time)-strtotime($start_time))/(3600*24)+1;
		$page = $this->page($count, 20);

		$channel_name = '--';
		$game_name = '--';
		$p = I('get.p')?I('get.p'):1;
		$map = array();
		$map_old = array();

		$heji_old = array();

		$game_role = session('game_role');

		if($game_role !='all')
		{
			$map['appid'] = array('in',$game_role);
			$map_old['gameid'] = array('in',$game_role);
		}

		$channel_role = session('channel_role');

		if($channel_role !='all')
		{
			$map['cid'] = array('in',$channel_role);
			$map_old['channel'] = array('in',$channel_role);
		}

		if(!empty($post_data['cid']))
		{
			$map['cid'] = $post_data['cid'];
			$map_old['channel'] = $post_data['cid'];
			$channel_name = M('Channel')->where(array('id'=>$post_data['cid']))->getfield('name');
		}
		if(!empty($post_data['appid']))
		{
			$map['appid'] = $post_data['appid'];
			$map_old['gameid'] = $post_data['appid'];
			$game_name = M('Game')->where(array('id'=>$post_data['appid']))->getfield('game_name');
		}


		$datearr = array ();

		$end_time_conf = strtotime($end_time.' 23:59:59')-($p-1)*24*3600*20;

		$start_time_conf =$end_time_conf-24*3600*20;

		if($start_time_conf < strtotime($start_time.' 00:00:00'))
		{
			$start_time_conf = strtotime($start_time.' 00:00:00');
			$map['time'] = array(array('egt',date('Y-m-d',$start_time_conf)),array('elt',date('Y-m-d',$end_time_conf)));
			$map_old['time'] = array(array('egt',$start_time_conf),array('elt',$end_time_conf));
			$map_old['pay_to_time'] =array(array('egt',$start_time_conf),array('elt',$end_time_conf));
		}
		else
		{
			$map['time'] = array(array('gt',date('Y-m-d',$start_time_conf)),array('elt',date('Y-m-d',$end_time_conf)));
			$map_old['time'] = array(array('gt',$start_time_conf),array('elt',$end_time_conf));
			$map_old['pay_to_time'] = array(array('gt',$start_time_conf),array('elt',$end_time_conf));
		}


		while ( $start_time_conf < $end_time_conf )
		{
			$datearr [] = date ( 'Y-m-d', $end_time_conf);
			$end_time_conf = $end_time_conf - 3600 * 24;
		}

		$info = M('pay_by_day')->
		field('time,sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user)')->
		where($map)->
		group('time')->
		order('time desc')->
		select();

		$map['time'] = array(array('egt',$start_time),array('elt',$end_time));

		$heji = M('pay_by_day')->
		field('sum(active_user),sum(pay_counts),sum(pay_number),sum(pay_amount),sum(new_user)')->
		where($map)->
		find();

		$login_log_model = M('login_log',null,C('DB_OLDSDK_CONFIG'));

		$active_users = $login_log_model
		->field("count(distinct(username)) as count, FROM_UNIXTIME(time,'%Y-%m-%d') as time")
		->where($map_old)
		->group("FROM_UNIXTIME(time,'%Y-%m-%d')")
		->order("FROM_UNIXTIME(time,'%Y-%m-%d') desc")
		->select();

		$pay_model = M('pay','syo_',C('DB_OLDSDK_CONFIG'));

		$pay_info = $pay_model
		->field("count(id) as pay_counts,count(distinct(username)) as uid_counts,sum(rmb) as money,FROM_UNIXTIME(pay_to_time,'%Y-%m-%d') as time")
		->where(array_merge($map_old,array('status'=>1,'vip'=>2,'type'=>1)))
		->group("FROM_UNIXTIME(pay_to_time,'%Y-%m-%d')")
		->order("FROM_UNIXTIME(pay_to_time,'%Y-%m-%d') desc")
		->select();

		$newplayer_model = M('newplayer',null,C('DB_OLDSDK_CONFIG'));

		$new_users = $newplayer_model
		->field("count(username) as count, FROM_UNIXTIME(time,'%Y-%m-%d') as time")
		->where($map_old)
		->group("FROM_UNIXTIME(time,'%Y-%m-%d')")
		->order("FROM_UNIXTIME(time,'%Y-%m-%d') desc")
		->select();

		$map_old['time'] = array(array('egt',strtotime($start_time.' 00:00:00')),array('elt',strtotime($end_time.' 23:59:59')));
		$map_old['pay_to_time'] = array(array('egt',strtotime($start_time.' 00:00:00')),array('elt',strtotime($end_time.' 23:59:59')));

		$active_users_heji = $login_log_model
		->field("count(distinct(username)) as count")
		->where($map_old)
		->find();



		$new_users_heji = $newplayer_model
		->field("count(username) as count")
		->where($map_old)
		->find();


		$new_info = array();
		foreach($info as $v)
		{
			$new_info[$v['time']] = $v;
		}

		unset($info);


		$active_users_info = array();
		if(is_array($active_users))
		{
			foreach($active_users as $active_user)
			{
				$active_users_info[$active_user['time']] = $active_user;

			}

		}

		$heji_old['active_user'] = $active_users_heji['count'];
		unset($active_users);

		$pay_info_info = array();
		if(is_array($pay_info))
		{
			foreach($pay_info as $v)
			{
				$pay_info_info[$v['time']] = $v;

			}
		}

		unset($pay_info);

		$new_users_info = array();
		if(is_array($new_users))
		{
			foreach($new_users as $v)
			{
				$new_users_info[$v['time']] = $v;

			}
		}
		$heji_old['new_user'] = $new_users_heji['count'];
		unset($new_users);


		$result = array();
		//组装数据;
		foreach($datearr as $v)
		{
			$arpu_1 = rand($arpu_start,$arpu_end)+rand(0,99)*0.01;

		
			$active_users_v = (isset($new_info[$v]['sum(active_user)'])?$new_info[$v]['sum(active_user)']:0)+(isset($active_users_info[$v]['count'])?$active_users_info[$v]['count']:0);
			$pay_counts_v = (isset($new_info[$v]['sum(pay_counts)'])?$new_info[$v]['sum(pay_counts)']:0)+(isset($pay_info_info[$v]['pay_counts'])?$pay_info_info[$v]['pay_counts']:0);
			$uid_counts_v = (isset($new_info[$v]['sum(pay_number)'])?$new_info[$v]['sum(pay_number)']:0)+(isset($pay_info_info[$v]['uid_counts'])?$pay_info_info[$v]['uid_counts']:0);
			$money_v = (isset($new_info[$v]['sum(pay_amount)'])?$new_info[$v]['sum(pay_amount)']:'0.00')+(isset($pay_info_info[$v]['money'])?$pay_info_info[$v]['money']:'0.00');
			$active_arpu = round($money_v/$active_users_v,2);

			$new_users_v = (isset($new_info[$v]['sum(new_user)'])?$new_info[$v]['sum(new_user)']:0)+(isset($new_users_info[$v]['count'])?$new_users_info[$v]['count']:0);

			$beishu_1 = $arpu_1/$active_arpu;

			$pay_counts_v = ceil($pay_counts_v*$beishu_1);
			$arpu_active = rand(50,80)*0.01;

			$uid_counts_v = (ceil($uid_counts_v*$beishu_1)>=$active_users_v)?ceil($active_users_v*$arpu_active):ceil($uid_counts_v*$beishu_1);
		
			$money_v = sprintf("%.2f",ceil($money_v*$beishu_1));

			$heji_old['pay_counts']+=$pay_counts_v;
			$heji_old['pay_number']+= $uid_counts_v;
			$heji_old['pay_amount']+= $money_v;

			$result[] = array($v,$active_users_v,$new_users_v,$pay_counts_v,$uid_counts_v,$money_v,round($active_arpu*$beishu_1,2));
		}




		if($action == 0)
		{
			$this->assign('heji',$heji);
			$this->assign('heji_old',$heji_old);
			$this->assign('channel_name',$channel_name);
			$this->assign('game_name',$game_name);
			$this->assign('result',$result);
			$this->assign('start_time',$start_time);
			$this->assign('end_time',$end_time);
			$this->assign('channel_list',get_channel_list(I('cid')));
			$this->assign('game_list',get_game_list(I('appid'),1,'all'));
			$this->assign('page',$page->show('Admin'));
			$this->assign('max',$max);
			$this->assign('arpu_start',$arpu_start);
			$this->assign('arpu_end',$arpu_end);
			$this->display();
		}
		else
		{

			//导出模式
			$xlsTitle = iconv('utf-8', 'gb2312', '订单统计');//文件名称
			$fileName = date('_YmdHis').'订单统计';//or $xlsTitle 文件名称可根据自己情况设定

			$expCellName = array('日期','游戏名称','渠道名称','活跃用户','新增用户','充值次数','充值人数','总充值金额','活跃Arpu');

			$cellNum = count($expCellName);
			$heji_item = array('合计',$heji['sum(active_user)']+$heji_old['active_user'],$heji['sum(new_user)']+$heji_old['new_user'],
			ceil($heji['sum(pay_counts)']+$heji_old['pay_counts']*$beishu),
			ceil($heji['sum(pay_number)']+$heji_old['pay_number']*$beishu),ceil($heji['sum(pay_amount)']+$heji_old['pay_amount']*$beishu),
			round(($heji['sum(pay_amount)']+$heji_old['pay_amount'])/($heji['sum(active_user)']+$heji_old['active_user'])*$beishu,2));

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
}