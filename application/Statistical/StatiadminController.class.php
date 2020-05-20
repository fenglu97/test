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

		$box_install_info_obj = M('box_install_info');
	

		$xinzen_info = $box_install_info_obj
		->field("count(*) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where(array('create_time'=>array(array('egt',$start_time),array('elt',$end_time))))
		->group("FROM_UNIXTIME(create_time,'%Y%m%d')")
		->select();
		
		$xinzen_multi = M('statistics_set')->
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
	
		$parameter['end_time'] = $post_data['end_time']?$post_data['end_time']:date('Ymd',strtotime('-1 day'));
		$parameter['start_time'] = $post_data['start_time']?$post_data['start_time']:date('Ymd',strtotime('-7 days'));
		

		$map =array();
        $map['first_time'] = array('between',array(strtotime($parameter['start_time']),strtotime($parameter['end_time'])));

        $map_channel = array();
        if(!empty($parameter['channel_id'])){
            $map['channel'] = $parameter['channel_id'];
        }else{
            if(!empty(trim($_SESSION['channel']))){
                $map['channel'] = array('in',$_SESSION['channel']);
                $map_channel['channel'] = array('in',$_SESSION['channel']);
            }

        }
   
        
        $counts = M('retained_day_channel')->
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

        
        $data = M('retained_day_channel')->
        field('first_time,SUM(installs) as installs,SUM(huoyue) as huoyue,SUM(one_day) as one_day ,SUM(two_day) as two_day,
        SUM(three_day) as three_day,SUM(four_day) as four_day,SUM(five_day) as five_day ,SUM(six_day) as six_day,
        SUM(seven_day) as seven_day,SUM(fourteen_day) as fourteen_day,SUM(thirty_day) as thirty_day')->
        where($map)->
        order('first_time desc')->
        group('first_time')->
        limit($page->firstRow . ',' . $page->listRows)->
        select();
   
        
		$xinzen_multi = M('statistics_set')->
		where(array('time'=>array(array('egt',date('Y-m-d',strtotime($parameter['start_time']))),array('elt',date('Y-m-d',strtotime($parameter['end_time']))))))->
		getfield('UNIX_TIMESTAMP(time) as time,xinzen_multiple');

		
		
		$channels = M('box_boot_info')->where($map_channel)->field('distinct(channel) channel')->select();

		$this->assign('channels',$channels);
        $this->assign('data',$data);
		$this->assign('max',$max);
		$this->assign('pager',$page->show());
		$this->assign('parameter',$parameter);
		$this->assign('xinzen_multi',$xinzen_multi);
		$this->display();
	}
	
	public function set_xinzen_multi()
	{
		$setmulti = I('post.setmulti');
		$statistics_set_obj = M('statistics_set');
		
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
		if(!empty($post_data['channel_id']))
		{
			$page->parameter['channel_id'] = urlencode($post_data['channel_id']);
			$map['channel'] = $post_data['channel_id'];
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

		$box_install_info_obj = M('box_install_info');
		$box_boot_info_obj = M('box_boot_info');
		$box_download_obj = M('box_download');
	
		$map['create_time'] = array(array('egt',$start_time),array('elt',$end_time));

		
		$xinzen_info = $box_install_info_obj
		->field("count(*) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where($map)
		->group("FROM_UNIXTIME(create_time,'%Y%m%d')")
		->select();
		
	
		
		$huoyue_info = $box_boot_info_obj
		->field("count(distinct(machine_code)) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where($map)
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();

		$boot_info = $box_boot_info_obj
		->field("count(*) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where($map)
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();
		
		$download_info=$box_download_obj
		->field("count(distinct(ip)) as count, FROM_UNIXTIME(create_time,'%Y-%m-%d') time")
		->where($map)
		->group("FROM_UNIXTIME(create_time,'%Y-%m-%d')")
		->select();
		


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
		
		foreach($datearr as $v)
		{
			$xinzen = isset($xinzen_info[$v])?$xinzen_info[$v]:0;
			$huoyue = isset($huoyue_info[$v])?$huoyue_info[$v]:0;
			$boot = isset($boot_info[$v])?$boot_info[$v]:0;

			$data[] = array($v,$xinzen,$huoyue,$boot);
		}
		

			
		$channels = M('box_boot_info')->field('distinct(channel) channel')->select();

		$this->assign('data',$data);
		$this->assign('post_data',$post_data);
		$this->assign('max',date('Y-m-d',time()));
		$this->assign('pager',$pager);
		$this->assign('channels',$channels);
		$this->display();		
	}
}