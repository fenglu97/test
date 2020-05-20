<?php
/**
 * 整体趋势统计
 * @author qing.li
 * @date 2017-05-23
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class OveralltrendController extends AdminbaseController{

	public function index()
	{
		date_default_timezone_set ( "PRC" );
		$today = time();
	
		$yesterday = $today - 3600*24;

		$map_today['create_time'] = array(array('gt',$yesterday),array('elt',$today));
		$map_yesterday['create_time'] = array(array('gt',$yesterday-3600*24),array('elt',$yesterday));

		$box_install_info_obj = M('box_install_info','syo_','185DB');
		$box_boot_info_obj = M('box_boot_info','syo_','185DB');

		//新增用户 今日 昨日
		$xinzen_today =	$box_install_info_obj->where(array($map_today))
		->count();

		$xinzen_yesterday = $box_install_info_obj->where(array($map_yesterday))
		->count();
		

	

		//活跃用户 今日 昨日
		$huoyue_today = $box_boot_info_obj->where(array($map_today))
		->count('distinct(machine_code)');

		$huoyue_yesterday = $box_boot_info_obj->where(array($map_yesterday))
		->count('distinct(machine_code)');

		//启动次数 今日 昨日
		$boot_today = $box_boot_info_obj->where(array($map_today))
		->count();

		$boot_yesterday = $box_boot_info_obj->where(array($map_yesterday))
		->count();

		//默认30天的数据
		$xinzen_info = $box_install_info_obj
		->field("count(*) as count,count(if(system=1,1,null)) android,count(if(system=2,1,null)) ios, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where(array('create_time'=>array(array('egt',$today-3600*24*14),array('lt',$today+3600*24))))
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();

		$huoyue_info = $box_boot_info_obj
		->field("count(distinct(machine_code)) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where(array('create_time'=>array(array('egt',$today-3600*24*14),array('lt',$today+3600*24))))
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();

		$boot_info = $box_boot_info_obj
		->field("count(*) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where(array('create_time'=>array(array('egt',$today-3600*24*14),array('lt',$today+3600*24))))
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();


		$stime = $today-3600*24*14;
		$etime = $today;
		$datearr = array ();
		while ( $stime <= $etime )
		{
			$datearr [] = date ( 'Y-m-d', $stime );
			$stime = $stime + 3600 * 24;
		}

        $system = $xinzen_info;
		if(is_array($xinzen_info))
		{
			$time = array();
			$count = array();
			foreach($xinzen_info as $v)
			{
				$time[] = $v['time'];
				$count[] = $v['count'];
			}
			$xinzen_info = array_combine($time,$count);
		}

		if(is_array($huoyue_info))
		{
			$time = array();
			$count = array();
			foreach($huoyue_info as $v)
			{
				$time[] = $v['time'];
				$count[] = $v['count'];
			}
			$huoyue_info = array_combine($time,$count);
		}

		if(is_array($boot_info))
		{
			$time = array();
			$count = array();
			foreach($boot_info as $v)
			{
				$time[] = $v['time'];
				$count[] = $v['count'];
			}
			$boot_info = array_combine($time,$count);
		}
		$result = array();
		//组装数据;
		foreach($datearr as $k=>$v)
		{
			$xinzen = isset($xinzen_info[$v])?$xinzen_info[$v]:0;
			$huoyue = isset($huoyue_info[$v])?$huoyue_info[$v]:0;
			$boot = isset($boot_info[$v])?$boot_info[$v]:0;
            $android = $system[$k]['time'] == $v?$system[$k]['android']:0;
            $ios = $system[$k]['time'] == $v?$system[$k]['ios']:0;
			$result[] = array($v,$xinzen,$huoyue,$boot,$android,$ios);
		}
		$result_json = json_encode($result);
		

		//var_dump($result);die;
		//明细 30条为一页
		$this->assign('xinzen_today',$xinzen_today);
		$this->assign('xinzen_yesterday',$xinzen_yesterday);
		$this->assign('huoyue_today',$huoyue_today);
		$this->assign('huoyue_yesterday',$huoyue_yesterday);
		$this->assign('boot_today',$boot_today);
		$this->assign('boot_yesterday',$boot_yesterday);
		$this->assign('today',date('Y-m-d'));
		$this->assign('before_15_date',date('Y-m-d',strtotime('-14 days')));
		$this->assign('result_json',$result_json);
		$this->assign('result',$result);
		$this->display();
	}

	public function ajax_data()
	{
		$get_data = I('get.');


		$start_time = $stime = strtotime($get_data['start_time'].' 00:00:00');
		$etime = strtotime($get_data['end_time'].' 23:59:59');


		$datearr = array ();
		while ( $stime <= $etime )
		{
			$datearr [] = date ( 'Y-m-d', $stime );
			$stime = $stime + 3600 * 24;
		}

		if($get_data['type'] == 'xinzen')
		{
			$res = M('box_install_info','syo_','185DB')
			->field("count(*) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
			->where(array('create_time'=>array(array('egt',$start_time),array('elt',$etime))))
			->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
			->select();
			
		}
		if($get_data['type'] == 'huoyue')
		{
			$res = M('box_boot_info','syo_','185DB')
			->field("count(distinct(machine_code)) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
			->where(array('create_time'=>array(array('egt',$start_time),array('elt',$etime))))
			->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
			->select();
		}
		if($get_data['type'] == 'boot')
		{
			$res = M('box_boot_info','syo_','185DB')
			->field("count(*) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
			->where(array('create_time'=>array(array('egt',$start_time),array('lt',$etime))))
			->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
			->select();
		}
		
		if(is_array($res))
		{
			$time = array();
			$count = array();
			foreach($res as $v)
			{
				$time[] = $v['time'];
				$count[] = $v['count'];
			}
			$res = array_combine($time,$count);
		}
	
		$result = array();
		//组装数据;
		foreach($datearr as $v)
		{
			$res_v = isset($res[$v])?$res[$v]:0;
			$result[] = array($v,$res_v);
		}
		
		echo json_encode($result);

	}

	public function ajax_data_info()
	{
        $get_data = I('get.');
        $get_data['p'] = isset($get_data['p'])?$get_data['p']:1;
        $counts = (strtotime($get_data['end_time']) - strtotime($get_data['start_time']))/86400+1; 
        if($counts == 0)
        {
        	exit('');
        }
        
        $pagecount = ceil($counts/30); 
               
        if($get_data['p'] > $pagecount)
        {
        	exit('');
        }
        
		$page       = new \Think\Page($counts,30);
        if($get_data['p'] == $pagecount)
        {
        	$start_time = strtotime($get_data['start_time'].' 00:00:00');
        }
        else 
        {
        	$start_time = strtotime($get_data['end_time'].' 00:00:00')-($page->listRows*($get_data['p'])-1)*3600*24;
        }
        $end_time = strtotime($get_data['end_time'].' 23:59:59')-$page->listRows*3600*24*($get_data['p']-1);
        
       $stime = $start_time;


        $datearr = array ();
        while ( $stime <= $end_time )
        {
        	$datearr [] = date ( 'Y-m-d', $stime );
        	$stime = $stime + 3600 * 24;
        }
        
		$box_install_info_obj = M('box_install_info','syo_','185DB');
		$box_boot_info_obj = M('box_boot_info','syo_','185DB');
        
		$xinzen_info = $box_install_info_obj
		->field("count(*) as count,count(if(system=1,1,null)) android,count(if(system=2,1,null)) ios, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where(array('create_time'=>array(array('egt',$start_time),array('elt',$end_time))))
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();

		$huoyue_info = $box_boot_info_obj
		->field("count(distinct(machine_code)) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where(array('create_time'=>array(array('egt',$start_time),array('elt',$end_time))))
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();

		$boot_info = $box_boot_info_obj
		->field("count(*) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where(array('create_time'=>array(array('egt',$start_time),array('elt',$end_time))))
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();
		$system = $xinzen_info;
		if(is_array($xinzen_info))
		{
			$time = array();
			$count = array();
			foreach($xinzen_info as $v)
			{
				$time[] = $v['time'];
				$count[] = $v['count'];
			}
			$xinzen_info = array_combine($time,$count);
		}

		if(is_array($huoyue_info))
		{
			$time = array();
			$count = array();
			foreach($huoyue_info as $v)
			{
				$time[] = $v['time'];
				$count[] = $v['count'];
			}
			$huoyue_info = array_combine($time,$count);
		}

		if(is_array($boot_info))
		{
			$time = array();
			$count = array();
			foreach($boot_info as $v)
			{
				$time[] = $v['time'];
				$count[] = $v['count'];
			}
			$boot_info = array_combine($time,$count);
		}
		$data = array();
		//组装数据;
		foreach($datearr as $k=>$v)
		{
			$xinzen = isset($xinzen_info[$v])?$xinzen_info[$v]:0;
			$huoyue = isset($huoyue_info[$v])?$huoyue_info[$v]:0;
			$boot = isset($boot_info[$v])?$boot_info[$v]:0;
            $android = $system[$k]['time'] == $v?$system[$k]['android']:0;
            $ios = $system[$k]['time'] == $v?$system[$k]['ios']:0;
			$data[] = array($v,$xinzen,$huoyue,$boot,$android,$ios);
		}

		
		
		
		
		$pager = $page->show();
	
		$pattern = '/\/Statistical-Overalltrend-ajax_data_info-p-(\d*)-start_time-\d{4}-\d{2}-\d{2}-end_time-\d{4}-\d{2}-\d{2}\.html/i';
		$replacement = "javascript:get_page($1)";
		$pager = preg_replace($pattern, $replacement, $pager);


		$res = array(
		'data'=>$data,
		'page'=>$pager
		);
		
		echo json_encode($res);
        
        
	}
}