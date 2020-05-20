<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/12/27
 * Time: 10:12
 */
namespace Admin\Controller;
use Think\Model;
use Common\Controller\AdminbaseController;

class AnalyseController extends AdminbaseController{

	/* BI分析 */
	/**
     * 留存分析
     */
	public function retained_BI(){
		$start = I('start',date("Y-m-d", strtotime("-1 month")));
		$end = I('end',date('Y-m-d'));
		$gid = I('gid',-1);
		$cid = I("cid",-1);

		if($gid > 0) $where['gameid'] = $gid;
		if($cid > 0) $where['channel'] = $cid;
		$where['stat_time'] = array('between',array(strtotime($start),strtotime($end.' 23:59:59')));

		$data = M('Stat_remain',null,C('biDB'))->where($where)->field('stat_time,sum(dru) as dru,sum(next_day) as next_day,sum(second_day) as second_day,sum(third_day) as third_day,sum(fourth_day) as fourth_day,sum(fifth_day) as fifth_day,sum(sixth_day) as sixth_day,sum(seventh_day) as seventh_day,sum(fifteenth_day) as fifteenth_day,sum(thirtieth_day) as thirtieth_day')->group('stat_time')->select();
		foreach ($data as $key => $value) {
			$day_time= date('Y-m-d',$value['stat_time']);
			$res[$day_time]['stat_time'] = $day_time;
			$res[$day_time]['dru'] = $value['dru'];
			$res[$day_time]['next_day_rate'] = round(($value['next_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['second_day_rate'] = round(($value['second_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['third_day_rate'] = round(($value['third_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['fourth_day_rate'] = round(($value['fourth_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['fifth_day_rate'] = round(($value['fifth_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['sixth_day_rate'] = round(($value['sixth_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['seventh_day_rate'] = round(($value['seventh_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['fifteenth_day_rate'] = round(($value['fifteenth_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['thirtieth_day_rate'] = round(($value['thirtieth_day']/$value['dru'])*100,2).'%';
		}

		$this->gid = $gid;
		$this->cid = $cid;
		$this->data = $res;
		$this->start = $start;
		$this->end = $end;
		$this->selected_channel_type = I('channel_type');
		$this->channel_type = C('channel_type');
		$this->display();
	}

	/**
     * 充值统计
     */
	public function payStatistics_BI(){
		$time = I('time',date('Y-m-d'));
		$end = strtotime($time.' 23:59:59');
		$cid = I('cid',-1);

		$where = array('status'=>1,'type'=>1,'pay_to_time'=>array('between',array(strtotime($time),$end)));
		$channel_role = session('channel_role');
		if($channel_role !='all')  $where['channel'] = array('in',$channel_role);
		if($cid >0) $where['channel'] = $cid;
		//统计总计
		$result_sum = M('syo_pay',null,C('biDB'))->where($where)->field('sum(rmb) as rmb,count(distinct(username)) as username,count(1) as count')->select();

		//按小时统计
		$list = M('syo_pay',null,C('biDB'))->where($where)->field('FROM_UNIXTIME(pay_to_time,"%H") AS hour,sum(rmb) as rmb,count(distinct(username)) as username,count(1) as count')->group('hour')->select();

		//充值人数累加
		$username_counts = 0;
		if(is_array($list)) {
			foreach($list as $v) {
				$username_counts+=(int)$v['username'];
			}
		}

		$this->assign('list',$list);
		$this->assign('time',$time);
		$this->assign('cid',$cid);
		$this->assign('result_sum',$result_sum);
		$this->assign('username_counts',$username_counts);
		$this->selected_channel_type = I('channel_type');
		$this->channel_type = C('channel_type');
		$this->display();
	}

	/**
     * 充值排行
     */
	public function payRanking_BI(){
		$type = I('type',1);
		$cid = I('cid',-1);
		$gid = I('gid',-1);
		$start = I('start',date('Y-m-d'));
		$end = I('end',date('Y-m-d'));
		$p = I('p')?I('p'):1;

		if($cid > 0) $where['a.channel'] = $cid;
		if($gid > 0) $where['a.gameid'] = $gid;
		$where['a.pay_to_time'] = array('between',array(strtotime($start),strtotime($end.' 23:59:59')));
		$where['a.type'] = $type;

		$search = array('":["',']],"','",[','":"','","','{"','":',',"',',','"}','}');
		$replace   = array(' '," and ", ' ', "=",' and '," ",'=',' and ',' and ',' ',' ');
		$where_sql = str_replace($search, $replace, json_encode($where));


		$model = new Model('','',C('biDB'));

		$sql = 'SELECT count(*) FROM
(SELECT a.username,a.pay_to_time as first_time,MAX(pay_to_time) as end_time,sum(rmb) as rmb FROM syo_pay as a
 WHERE '.$where_sql.' GROUP BY a.username) as b';

		$count = $model->query($sql);

		$page = $this->page($count[0]['count(*)'],20);

		$sql = 'SELECT *,(SELECT channel FROM syo_member c WHERE c.username=b.username) AS channel FROM
(SELECT a.username,a.pay_to_time as first_time,MAX(pay_to_time) as end_time,sum(rmb) as rmb FROM syo_pay as a
 WHERE '.$where_sql.' GROUP BY a.username  ORDER BY sum(rmb) DESC LIMIT '.$page->firstRow.','.$page->listRows.') as b';


		$data = $model->query($sql);


		$this->data = $data;
		$this->type = $type;
		$this->cid = $cid;
		$this->gid = $gid;
		$this->start = $start;
		$this->end = $end;
		$this->selected_channel_type = I('channel_type');
		$this->channel_type = C('channel_type');
		$this->page = $page->show('Admin');
		$this->starting_value = ($p-1)*20;
		$this->display();
	}

	/**
     * 充值区间
     */
	public function paySection_BI(){
		$gid = I('gid');
		$first = I('first');
		$last = I('last');
		$start = I('start',date('Y-m-d'));
		$end = I('end',date('Y-m-d'));


		//时间内总充值人数
		$total = M('syo_pay',null,C('biDB'))->where(array('status'=>1,'type'=>1,'created'=>array('between',array(strtotime($start),strtotime($end.' 23:59:59')))))->count('distinct(username)');
		if($gid) $where['gameid'] = $gid;
		$where['created'] = array('between',array(strtotime($start),strtotime($end.' 23:59:59')));
		$where['type'] = 1;
		$where['status'] = 1;
		$model = new Model('','',C('biDB'));
		if($first || $last){
			$min = $first ? $first : 0;

			$max = $last ? $last : '以上';
			$level_info = array($min.'-'.$max);
		}else{
			$level_info = array(
			1 => '1-10',
			2 => '11-100',
			3 => '101-500',
			4 => '501-2000',
			5 => '2001-5000',
			6 => '5001-10000',
			7 => '10001-20000',
			8 => '20001-以上'
			);
		}
		$search = array('":["',']],"','",[','":"','","','{"','":',',"',',','"}','}');
		$replace   = array(' '," and ", ' ', "=",' and '," ",'=',' and ',' and ',' ',' ');
		$where = str_replace($search, $replace, json_encode($where));

		foreach($level_info as $k=>$v){
			list($min,$max) = explode('-',$v);
			if($max == '以上'){
				$map = ' b.rmb >= '.$min;
			}else{
				$map = ' b.rmb between '.$min.' and '.$max;
			}
			//档位内充值人数
			$data = $model->query("select count(*) as count,sum(rmb) as sum from (SELECT a.username,sum(rmb) as rmb FROM syo_pay as a WHERE ".$where." and a.type=1 and a.status=1 GROUP BY a.username) as b where ".$map);
			//占总充值人数百分比
			$parent = round($data[0]['count']/$total,4)*100;

			$res[$k]['level'] = $v;
			$res[$k]['count'] = $data[0]['count'];
			$res[$k]['parent'] = $parent;
		}

		$this->gid = $gid;
		$this->data = $res;
		$this->first = $first;
		$this->last = $last;
		$this->start = $start;
		$this->end = $end;
		$this->display();
	}

	/* 新游分析 */
	public function retained(){
		$start = I('start',date("Y-m-d", strtotime("-1 month")));
		$end = I('end',date('Y-m-d'));
		$gid = I('gid',-1);
		$cid = I("cid",-1);

		if($gid > 0) $where['gameid'] = $gid;
		if($cid > 0) $where['channel'] = $cid;
		$where['stat_time'] = array('between',array(strtotime($start),strtotime($end.' 23:59:59')));

		$data = M('stat_remain')->where($where)->field('stat_time,sum(dru) as dru,sum(next_day) as next_day,sum(second_day) as second_day,sum(third_day) as third_day,sum(fourth_day) as fourth_day,sum(fifth_day) as fifth_day,sum(sixth_day) as sixth_day,sum(seventh_day) as seventh_day,sum(fifteenth_day) as fifteenth_day,sum(thirtieth_day) as thirtieth_day')->group('stat_time')->select();
		foreach ($data as $key => $value) {
			$day_time= date('Y-m-d',$value['stat_time']);
			$res[$day_time]['stat_time'] = $day_time;
			$res[$day_time]['dru'] = $value['dru'];
			$res[$day_time]['next_day_rate'] = round(($value['next_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['second_day_rate'] = round(($value['second_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['third_day_rate'] = round(($value['third_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['fourth_day_rate'] = round(($value['fourth_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['fifth_day_rate'] = round(($value['fifth_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['sixth_day_rate'] = round(($value['sixth_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['seventh_day_rate'] = round(($value['seventh_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['fifteenth_day_rate'] = round(($value['fifteenth_day']/$value['dru'])*100,2).'%';
			$res[$day_time]['thirtieth_day_rate'] = round(($value['thirtieth_day']/$value['dru'])*100,2).'%';
		}

		$this->gid = $gid;
		$this->cid = $cid;
		$this->data = $res;
		$this->start = $start;
		$this->end = $end;
		$this->selected_channel_type = I('channel_type');
		$this->channel_type = C('channel_type');
		$this->display();
	}

	/**
     * 充值统计
     */
	public function payStatistics(){
		$time = I('time',date('Y-m-d'));
		$end = strtotime($time.' 23:59:59');
		$cid = I('cid',-1);

		$where = array('status'=>1,'create_time'=>array('between',array(strtotime($time),$end)));
		$channel_role = session('channel_role');
		if($channel_role !='all')  $where['cid'] = array('in',$channel_role);
		if($cid >0) $where['cid'] = $cid;
		//统计总计
		$result_sum = M('inpour')->where($where)->field('sum(money) as rmb,count(distinct(username)) as username,count(1) as count')->select();

		//按小时统计
		$list = M('inpour')->where($where)->field('FROM_UNIXTIME(create_time,"%H") AS hour,sum(money) as rmb,count(distinct(username)) as username,count(1) as count')->group('hour')->select();

		//充值人数累加
		$username_counts = 0;
		if(is_array($list)) {
			foreach($list as $v) {
				$username_counts+=(int)$v['username'];
			}
		}

		$this->assign('list',$list);
		$this->assign('time',$time);
		$this->assign('cid',$cid);
		$this->assign('result_sum',$result_sum);
		$this->assign('username_counts',$username_counts);
		$this->selected_channel_type = I('channel_type');
		$this->channel_type = C('channel_type');
		$this->display();
	}

	/**
     * 充值排行
     */
	public function payRanking(){
		$cid = I('cid',-1);
		$gid = I('gid',-1);
		$start = I('start',date('Y-m-d'));
		$end = I('end',date('Y-m-d'));
		$p = I('p')?I('p'):1;

		$where = '';
		if($cid > 0) $where .= ' a.cid='.$cid.' and';
		if($gid > 0) $where .= ' a.appid='.$gid.' and';
		$where .= ' a.status=1 and';
		$where .= ' a.create_time between '.strtotime($start).' and '.strtotime($end.' 23:59:59');

		$model = new Model();

		$sql = 'SELECT count(*) FROM
(SELECT a.username,a.create_time as first_time,MAX(create_time) as end_time,sum(money) as rmb FROM bt_inpour as a
WHERE '.$where.' GROUP BY a.username) as b';

		$count = $model->query($sql);

		$page = $this->page($count[0]['count(*)'], 20);

		$sql = 'SELECT *,(SELECT channel FROM bt_player c WHERE c.username=b.username) AS channel FROM
(SELECT a.username,a.create_time as first_time,MAX(create_time) as end_time,sum(money) as rmb FROM bt_inpour as a
WHERE '.$where.' GROUP BY a.username  ORDER BY sum(money) DESC LIMIT '.$page->firstRow.','.$page->listRows.') as b';

		$data = $model->query($sql);

		$this->data = $data;
		$this->cid = $cid;
		$this->gid = $gid;
		$this->start = $start;
		$this->end = $end;
		$this->selected_channel_type = I('channel_type');
		$this->channel_type = C('channel_type');
		$this->page = $page->show('Admin');
		$this->starting_value = ($p-1)*20;
		$this->display();
	}

	/**
     * 充值区间
     */
	public function paySection(){
		$gid = I('gid');
		$first = I('first');
		$last = I('last');
		$start = I('start',date('Y-m-d'));
		$end = I('end',date('Y-m-d'));


		//时间内总充值人数
		$total = M('inpour')->where(array('status'=>1,'payType'=>array('neq',10),'create_time'=>array('between',array(strtotime($start),strtotime($end.' 23:59:59')))))->count('distinct(username)');
		$where = '';
		$model = new Model();
		if($first || $last){
			$min = $first ? $first : 0;
			$max = $last ? $last : '以上';
			$level_info = array($min.'-'.$max);
		}else{
			$level_info = array(
			1 => '1-10',
			2 => '11-100',
			3 => '101-500',
			4 => '501-2000',
			5 => '2001-5000',
			6 => '5001-10000',
			7 => '10001-20000',
			8 => '20001-以上'
			);
		}

		if($gid) $where .= ' appid='.$gid.' and';
		$where .= ' payType<>10 and';
		$where .= ' status=1 and';
		$where .= ' create_time between '.strtotime($start).' and '.strtotime($end.' 23:59:59');

		foreach($level_info as $k=>$v){
			list($min,$max) = explode('-',$v);
			if($max == '以上'){
				$map = ' b.rmb >= '.$min;
			}else{
				$map = ' b.rmb between '.$min.' and '.$max;
			}
			//档位内充值人数
			$data = $model->query("select count(*) as count,sum(rmb) as sum from (SELECT a.username,sum(money) as rmb FROM bt_inpour as a WHERE ".$where." and a.payType<>10 and a.status=1 GROUP BY a.username) as b where ".$map);
			//占总充值人数百分比
			$parent = round($data[0]['count']/$total,4)*100;

			$res[$k]['level'] = $v;
			$res[$k]['count'] = $data[0]['count'];
			$res[$k]['parent'] = $parent;
		}

		$this->gid = $gid;
		$this->data = $res;
		$this->first = $first;
		$this->last = $last;
		$this->start = $start;
		$this->end = $end;
		$this->display();
	}

	public function ltv()
	{
		$start = I('start',date("Y-m-d", strtotime("-1 month")));
		$end = I('end',date('Y-m-d'));
		$appid = I('appid');
		$channel = I('channel');

		if($appid > 0) $where['appid'] = $appid;
		$channel_role = session('channel_role');
		if($channel_role !='all')  $where['channel'] = array('in',$channel_role);
		if($channel > 0) $where['channel'] = $channel;
		$where['stat_time'] = array('between',array(strtotime($start),strtotime($end.' 23:59:59')));

		$data = M('stat_ltv')->where($where)->field('stat_time,sum(register_counts) as register_counts,sum(register_money) as register_money,sum(acculate_register_money) as acculate_register_money
		,sum(next_day) as next_day,sum(third_day) as third_day,sum(fifth_day) as fifth_day,sum(seventh_day) as seventh_day,sum(fifteenth_day) as fifteenth_day,
		sum(thirtieth_day) as thirtieth_day,sum(sixtieth_day) as sixtieth_day,sum(ninetieth_day) as ninetieth_day')->group('stat_time')->order('stat_time desc')->select();

		foreach ($data as $key => $value) {
			$day_time= date('Y-m-d',$value['stat_time']);
			$res[$day_time]['stat_time'] = $day_time;
			$res[$day_time]['register_counts'] = $value['register_counts'];
			$res[$day_time]['acculate_register_money'] = sprintf("%.2f",$value['acculate_register_money']);
			$res[$day_time]['today_ltv'] = round(($value['register_money']/$value['register_counts']),2);
			$res[$day_time]['next_day_ltv'] = round(($value['next_day']/$value['register_counts']),2);
			$res[$day_time]['third_day_ltv'] = round(($value['third_day']/$value['register_counts']),2);
			$res[$day_time]['fifth_day_ltv'] = round(($value['fifth_day']/$value['register_counts']),2);
			$res[$day_time]['seventh_day_ltv'] = round(($value['seventh_day']/$value['register_counts']),2);
			$res[$day_time]['fifteenth_day_ltv'] = round(($value['fifteenth_day']/$value['register_counts']),2);
			$res[$day_time]['thirtieth_day_ltv'] = round(($value['thirtieth_day']/$value['register_counts']),2);
			$res[$day_time]['sixtieth_day_ltv'] = round(($value['sixtieth_day']/$value['register_counts']),2);
			$res[$day_time]['ninetieth_day_ltv'] = round(($value['ninetieth_day']/$value['register_counts']),2);
		}

		$this->appid = $appid;
		$this->channel = $channel;
		$this->data = $res;
		$this->start = $start;
		$this->end = $end;
		$this->selected_channel_type = I('channel_type');
		$this->channel_type = C('channel_type');
		$this->display();

	}
}