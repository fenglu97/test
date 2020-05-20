<?php
/**
 * 统计
 * @author qing.li
 * @date 2017-10-12
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class StatiadminController extends AdminbaseController
{
	public function admin_set()
	{
		$max = date('Ymd',time());
		
		$post_data = $_REQUEST;
		
	
		
		$post_data['end_time'] = $post_data['end_time']?$post_data['end_time']:$max;
		$post_data['start_time'] = $post_data['start_time']?$post_data['start_time']:date('Ymd',strtotime('-6 days'));
		
		$p = isset($_GET['p'])?$_GET['p']:1;
		$counts = (strtotime($post_data['end_time']) - strtotime($post_data['start_time']))/86400+1;


		$pagecount = ceil($counts/20);
		
		$page = new \Think\Page($counts,20);//实例化分页类 传入总记录数和每页显示的记录数

		
		if(!empty($post_data['start_time']))
		{
			$page->parameter['start_time'] = urlencode($post_data['start_time']);
		}
		if(!empty($post_data['end_time']))
		{
			$page->parameter['end_time'] = urlencode($post_data['end_time']);
		}

		$pager  = $page->show();// 分页显示输出
		
		
		if($p == $pagecount)
		{
			$start_time = strtotime($post_data['start_time'].' 00:00:00');
		}
		else
		{
			$start_time = strtotime($post_data['end_time'].' 00:00:00')-($page->listRows*($p)-1)*3600*24;
		}
		$end_time = strtotime($post_data['end_time'].' 23:59:59')-$page->listRows*3600*24*($p-1);

		$stime = $end_time;
		

		$datearr = array ();
		while ( $stime >= $start_time )
		{
			$datearr [] = date ( 'Y-m-d', $stime );
			$stime = $stime - 3600 * 24;
		}

		$box_install_info_obj = M('box_install_info','syo_','185DB');
	

		$xinzen_info = $box_install_info_obj
		->field("count(*) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where(array('create_time'=>array(array('egt',$start_time),array('elt',$end_time))))
		->group("FROM_UNIXTIME(create_time,'%Y%m%d')")
		->select();
		
		$xinzen_multi = M('statistics_set','syo_','185DB')->
		where(array('time'=>array(array('egt',date('Y-m-d',$start_time)),array('elt',date('Y-m-d',$end_time)))))->
		getfield('time,xinzen_multiple');
		


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

		$data = array();
		//组装数据;
		
		foreach($datearr as $v)
		{
			$xinzen = isset($xinzen_info[$v])?$xinzen_info[$v]:0;
	
			$xinzen_multi_v = isset($xinzen_multi[$v])?$xinzen_multi[$v]:'1.00';
            $xinzen_new = ceil($xinzen * $xinzen_multi_v);
			$data[] = array($v,$xinzen,$xinzen_new,$xinzen_multi_v);
		}
			

		$this->assign('data',$data);
		$this->assign('post_data',$post_data);
		$this->assign('max',date('Y-m-d',time()));
		$this->assign('pager',$pager);
		$this->display();
	}
	
	public function actual_time_sta()
	{
		$max = date('Y-m-d',strtotime('-1 day'));
		$parameter = array();
        $parameter['channel_id'] = I('channel_id');
	
		$parameter['end_time'] = I('end_time')?I('end_time'):date('Ymd',strtotime('-1 day'));
		$parameter['start_time'] = I('start_time')?I('start_time'):date('Ymd',strtotime('-2 days'));
		

		$map =array();
        $map['first_time'] = array('between',array(strtotime($parameter['start_time']),strtotime($parameter['end_time'])));

        $map_channel = array();
        if(!empty($parameter['channel_id'])){
            $map['channel'] = $parameter['channel_id'];
        }else{
            if($_SESSION['channel_role'] != 'all'){
                $map['channel'] = array('in',$_SESSION['channel_role']);
                $map_channel['channel'] = array('in',$_SESSION['channel_role']);
            }

        }

        
        if(!isset($map['channel']) || empty($map['channel']))
        {
        	$retained_day_model = M('retained_day','syo_','185DB');
        	$field = '';
        }
        else 
        {
        	$retained_day_model = M('retained_day_channel','syo_','185DB');
        	$field = 'SUM(huoyue) as huoyue,';
        }

        $counts = $retained_day_model->
        field('id')->
        where($map)->
        group('first_time')->
        select();

        
        $page = new \Think\Page(count($counts),20);//实例化分页类 传入总记录数和每页显示的记录数
        
        foreach($parameter as $key=>$val)
        {
        	if(!empty($val))
        	$page->parameter[$key] = urlencode($val);
        }

        
        $data = $retained_day_model->
        field($field.'first_time,SUM(installs) as installs,SUM(one_day) as one_day ,SUM(two_day) as two_day,
        SUM(three_day) as three_day,SUM(four_day) as four_day,SUM(five_day) as five_day ,SUM(six_day) as six_day,
        SUM(seven_day) as seven_day,SUM(fourteen_day) as fourteen_day,SUM(thirty_day) as thirty_day')->
        where($map)->
        order('first_time desc')->
        group('first_time')->
        limit($page->firstRow . ',' . $page->listRows)->
        select();
        
       $box_boot_info_obj = M('box_boot_info','syo_','185DB');
   
        if(!isset($map['channel']) || empty($map['channel']))
        {
        	$map = array();
            $map['create_time'] = array('between',array(strtotime($parameter['start_time']),strtotime($parameter['end_time'])+24*3600-1));
        	$huoyue_info = $box_boot_info_obj
        	->field("count(distinct(machine_code)) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
        	->where($map)
        	->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
        	->order('time desc')
        	->select();
        	
        	foreach($data as $k=>$v)
        	{
        		$data[$k]['huoyue'] = $huoyue_info[$k]['count'];
        	}
        }
   
        
		$xinzen_multi = M('statistics_set','syo_','185DB')->
		where(array('time'=>array(array('egt',date('Y-m-d',strtotime($parameter['start_time']))),array('elt',date('Y-m-d',strtotime($parameter['end_time']))))))->
		getfield('UNIX_TIMESTAMP(time) as time,xinzen_multiple');

		
		
		$channels = $box_boot_info_obj->where($map_channel)->field('distinct(channel) channel')->select();

		$this->assign('channels',$channels);
        $this->assign('data',$data);
		$this->assign('max',$max);
		$this->assign('post_data',$post_data);
		$this->assign('pager',$page->show());
		$this->assign('parameter',$parameter);
		$this->assign('xinzen_multi',$xinzen_multi);
		$this->selected_channel_type = I('channel_type');
		$this->channel_type = C('channel_type');
		$this->display();
	}
	
	public function set_xinzen_multi()
	{
		$setmulti = I('post.setmulti');
		$statistics_set_obj = M('statistics_set','syo_','185DB');
		
		$res = array(
		'info'=>'设置失败',
		'status'=>0,
		'url'=>'',
		'referer'=>'',
		'state'=>'fail',
		);
		
		if(is_array($setmulti))
		{
			foreach($setmulti as $k=>$v)
			{
				$statistics_set_obj->add(array('xinzen_multiple'=>$v,'time'=>$k),array(),$replace=true);
			}
			
			$res['info'] = '设置成功';
			$res['status'] = 1;
			$res['state'] = 'success';
		}
		
		echo json_encode($res);
	        
	}
	
	public function real_time_stat()
	{
		$max = date('Ymd',time());

		$map = array();
		$post_data = $_REQUEST;


		$post_data['end_time'] = $post_data['end_time']?$post_data['end_time']:$max;
		$post_data['start_time'] = $post_data['start_time']?$post_data['start_time']:date('Ymd',strtotime('-1 days'));


		$p = isset($_GET['p'])?$_GET['p']:1;
		$counts = (strtotime($post_data['end_time']) - strtotime($post_data['start_time']))/86400+1;


		$pagecount = ceil($counts/20);
        $page = $this->page($counts, 20);

		if(!empty($post_data['start_time']))
		{
			$page->parameter['start_time'] = urlencode($post_data['start_time']);
		}
		if(!empty($post_data['end_time']))
		{
			$page->parameter['end_time'] = urlencode($post_data['end_time']);
		}
		if(!empty($post_data['channel_id']))
		{
			$page->parameter['channel_id'] = urlencode($post_data['channel_id']);
			$map['channel'] = $post_data['channel_id'];
		}
		else
		{
			if($_SESSION['channel_role'] != 'all'){
				$map['channel'] = array('in',$_SESSION['channel_role']);
			}
		}

		if(I('action') == 1)
		{
			$start_time = strtotime($post_data['start_time'].' 00:00:00');
			$end_time = strtotime($post_data['end_time'].' 23:59:59');
			if(($end_time-$start_time) > 31*3600*24)
			{
				$this->error('最大能导出31天的数据');
			}
		}
		else
		{
			if($p == $pagecount)
			{
				$start_time = strtotime($post_data['start_time'].' 00:00:00');
			}
			else
			{
				$start_time = strtotime($post_data['end_time'].' 00:00:00')-($page->listRows*($p)-1)*3600*24;
			}
			$end_time = strtotime($post_data['end_time'].' 23:59:59')-$page->listRows*3600*24*($p-1);
		}


		$stime = $end_time;



		$datearr = array ();
		while ( $stime >= $start_time )
		{
			$datearr [] = date ( 'Y-m-d', $stime );
			$stime = $stime - 3600 * 24;
		}

		$box_install_info_obj = M('box_install_info','syo_','185DB');
		$box_boot_info_obj = M('box_boot_info','syo_','185DB');
		$box_download_obj = M('box_download','syo_','185DB');
	
		$map['create_time'] = array(array('egt',$start_time),array('elt',$end_time));

		if($post_data['distinct_xinzen'] ==1)
		{
			$field = "FROM_UNIXTIME(create_time,'%Y-%m-%d') time,
			count(distinct machine_code) as count,count(distinct machine_code,if(system=1,1,null)) android,count(distinct machine_code,if(system=2,1,null)) ios";
		}
		else
		{
			$field = "FROM_UNIXTIME(create_time,'%Y-%m-%d') time,count(*) as count,count(if(system=1,1,null)) android,count(if(system=2,1,null)) ios";
		}

		//$field = "FROM_UNIXTIME(create_time,'%Y-%m-%d') time,count(*) as count,count(if(system=1,1,null)) android,count(if(system=2,1,null)) ios";
		$xinzen_info = $box_install_info_obj
		->where($map)
		->group("FROM_UNIXTIME(create_time,'%Y%m%d')")
        ->order('time desc')
		->getField($field,true);


		$huoyue_info = $box_boot_info_obj
		->where($map)
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
        ->order('time desc')
		->getField("FROM_UNIXTIME(create_time,'%Y-%m-%d') time,count(distinct(machine_code)) as count",true);

		$and_where = $map;
		$ios_where = $map;
		$and_where['system'] = 1;
        $ios_where['system'] = 2;
        $android = $box_boot_info_obj
            ->where($and_where)
            ->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
            ->order('time desc')
            ->getField("FROM_UNIXTIME(create_time,'%Y-%m-%d') time,count(distinct(machine_code)) as count",true);
        $ios = $box_boot_info_obj
            ->where($ios_where)
            ->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
            ->order('time desc')
            ->getField("FROM_UNIXTIME(create_time,'%Y-%m-%d') time,count(distinct(machine_code)) as count",true);
        foreach($huoyue_info as $k=>$v){
            $huoyue_info[$k]['android'] = $android[$k] ? $android[$k]['count'] : 0;
            $huoyue_info[$k]['ios'] = $ios[$k] ? $ios[$k]['count'] : 0;
        }
        
		$boot_info = $box_boot_info_obj
		->where($map)
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
        ->order('time desc')
		->getField("FROM_UNIXTIME(create_time,'%Y-%m-%d') time,count(*) as count,count(if(system=1,1,null)) android,count(if(system=2,1,null)) ios",true);
		
		$download_info=$box_download_obj
		->field("count(distinct(ip)) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where($map)
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();
		

        $newadd = $xinzen_info;
        $active = $huoyue_info;
        $start  = $boot_info;
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


		foreach($datearr as $k=>$v){

			$xinzen = isset($xinzen_info[$v])?$xinzen_info[$v]:0;
			$huoyue = isset($huoyue_info[$v])?$huoyue_info[$v]:0;
			$boot = isset($boot_info[$v])?$boot_info[$v]:0;

            $new_android = $newadd[$v]?$newadd[$v]['android']:0;
            $new_ios = $newadd[$v]?$newadd[$v]['ios']:0;
            $active_android = $active[$v]?$active[$v]['android']:0;
            $active_ios = $active[$v]?$active[$v]['ios']:0;
            $start_android = $start[$v]?$start[$v]['android']:0;
            $start_ios = $start[$v]?$start[$v]['ios']:0;
			$data[] = array($v,$new_android,$new_ios,$xinzen,$active_android,$active_ios,$huoyue,$start_android,$start_ios,$boot);
		}
		$channels = M('box_boot_info','syo_','185DB')->field('distinct(channel) channel')->select();

		if(I('action') == 1)
		{
			//导出模式
			$xlsTitle = iconv('utf-8', 'gb2312', '盒子实时统计');//文件名称
			$fileName = date('_YmdHis').'盒子实时统计';//or $xlsTitle 文件名称可根据自己情况设定

			$expCellName = array('日期','新增安卓用户','新增苹果用户','新增用户总数','安卓活跃用户','苹果活跃用户','活跃用户总数','安卓启动次数','苹果启动次数','启动总数');

			$cellNum = count($expCellName);
			$dataNum = count($data);

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
					$objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $data[$i][$j]);
				}
			}

			header('pragma:public');
			header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
			header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
			$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
			$objWriter->save('php://output');
			exit(1);
		}
		else
		{

			$this->assign('p',$p);
			$this->assign('data',$data);
			$this->assign('post_data',$post_data);
			$this->assign('max',date('Y-m-d',time()));
			$this->assign('pager',$page->show('Admin'));
			$this->assign('channels',$channels);
			$this->assign('system',$post_data['system']);
			$this->selected_channel_type = I('channel_type');
			$this->channel_type = C('channel_type');
			$this->display();
		}

	}
}