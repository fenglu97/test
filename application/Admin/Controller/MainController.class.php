<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class MainController extends AdminbaseController {
	
    public function index(){
    	$game_role = session('game_role');
        $channel_role = session('channel_role');
        $where['status'] = 1;
        if($game_role != 'all') $where['appid'] = array('in',$game_role.',0');
        if($channel_role != 'all') $where['cid'] = array('in',$channel_role.',0');

		$cps = array();
		if(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')) == 'cps') {
			// 获取昨天的时间段
			$yesStart = mktime(0,0,0,date('m'),date('d')-1,date('Y'));
            $yesEnd = mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
			// 获取今日的时间段
			$toStart = mktime(0,0,0,date('m'),date('d'),date('Y'));
			$toEnd = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
			
			$condition = array();
			$condition['channel'] = $channel_role;
			$map['cid'] = $channel_role;
			// 注册时间
			$yesterday['regtime'] = $yesPay['create_time'] = array(array('egt',$yesStart),array('elt',$yesEnd));
			$today['regtime'] = $toPay['create_time']  = array(array('egt',$toStart),array('elt',$toEnd));

			// 昨日注册数
			$resYesterday = M('player')
				->field('id')
				->where($condition)
				->where($yesterday)
				->count();
			// 今日注册数
			$resToday = M('player')
				->field('id')
				->where($condition)
				->where($today)
				->count();

			// 昨日充值数
			$payYesterday = M('inpour')
				->field('SUM(money) as money')
				->where('status in (1,2)')
				->where($map)
				->where($yesPay)
				->find();
			// 今日充值数
			$payToday = M('inpour')
				->field('SUM(money) as money')
				->where('status in (1,2)')
				->where($map)
				->where($toPay)
				->find();
			$cps = array(
				'cps' => 1,
				'resYesterday' => $resYesterday,
				'payYesterday' => $payYesterday['money'] ? $payYesterday['money'] : 0.00 ,
				'resToday' => $resToday,
				'payToday' => $payToday['money'] ? $payToday['money'] : 0.00
			);
		}

        $notice = M('notice')->where($where)->order('add_time desc')->select();
        $users = M('users')->where(array('user_type'=>1))->getField('id,user_login');
        $model = M('syo_server',null,C('185DB'));

        $sql = 'select s.*,g.gamename from syo_server s left join syo_game g on g.id=s.game_id WHERE 
                s.status=0 and s.is_display=1 and is_stop=0 and to_days(FROM_UNIXTIME(start_time))=to_days(NOW()) order by s.start_time';
        $server = $model->query($sql);
        $top = array();
        $new = array();
        foreach($notice as $k=>$v){
            if($v['top'] == 1 && count($top) < 5){
                $v['user'] = $users[$v['uid']];
                $top[] = $v;
            }
            if($v['top'] == 0 && count($new) < 5){
                $v['user'] = $users[$v['uid']];
                $new[] = $v;
            }
        }
$activity = M('activity')->where(array('status'=>1,'end_time'=>array('gt',time())))->order('sort,id desc')->limit(5)->select();
	

        $this->activity = $activity;
        $this->server = $server;
    	$this->top = $top;
		$this->new = $new;
		$this->cps = $cps;
    	$this->display();
    }
    
    	public function new_teaching()
	{
		$this->display();
	}

	public function getKaifu(){
		$model = M('syo_server',null,C('185DB'));
		$sql = 'SELECT s.*,g.gamename FROM syo_server s
                left join syo_game g on g.id=s.game_id
                WHERE
                g.status=0 and g.isdisplay=1 and
                s.is_display = 1 and s.status = 0 and s.is_stop = 0 and
                (to_days(NOW()) - to_days(FROM_UNIXTIME(s.start_time)) = 1 or
                s.start_time > unix_timestamp(NOW())  )
                order by s.start_time';
		$server = $model->query($sql);
		foreach ($server as $k=>$v){
			$time = date('H:i',$v['start_time']);
			if($v['line'] == 1) $s_name = '双线';
			if($v['line'] == 2) $s_name = '内测';
			if($v['line'] == 3) $s_name = '删档';
			if($v['line'] == 4) $s_name = '公测';
			//昨天
			if(date('Y-m-d',$v['start_time']) < date('Y-m-d',time())){
				$yesterday .= '<tr style="background-color: #989595;color:white">';
				$yesterday .= "<td>".date('Y-m-d H:i',$v['start_time'])."</td>";
				$yesterday .= "<td>{$v['gamename']}</td>";
				$yesterday .= "<td>{$v['server_name']}</td>";
				$yesterday .= "<td>{$s_name} {$v['server_id']}服</td></tr>";
			}
			//明天
			if(date('Y-m-d',$v['start_time']) > date('Y-m-d',time())){
				$tomorrow .= '<tr>';
				$tomorrow .= "<td>".date('Y-m-d H:i',$v['start_time'])."</td>";
				$tomorrow .= "<td>{$v['gamename']}</td>";
				$tomorrow .= "<td>{$v['server_name']}</td>";
				$tomorrow .= "<td>{$s_name} {$v['server_id']}服</td></tr>";
			}
		}
		$this->success(array('tomorrow'=>$tomorrow,'yesterday'=>$yesterday));
	}

	public function activity(){
	    $data = M('activity')->where(array('status'=>1,'end_time'=>array('gt',time())))->order('sort,id desc')->limit(5,999)->select();
	    if($data){
	        $all = '';
	        foreach($data as $k=>$v){
                if($v['level'] == 3){
                    $all .= "<tr style='background-color: #f17671;color:white'>";
                    $all .= "<td>".$v['title']."</td>";
                    $all .= "<td>".$v['desc']."</td>";
                    $all .= "<td>".$v['content']."</td>";
                    $all .= "<td>重要活动</td>";
                    $all .= "<td>".date('Y-m-d',$v['add_time']).' 至 '.date('Y-m-d',$v['end_time'])."</td></tr>";
                }
	            if($v['level'] == 2){
                    $all .= "<tr style='background-color: #3392f1;color:white'>";
                    $all .= "<td>".$v['title']."</td>";
                    $all .= "<td>".$v['desc']."</td>";
                    $all .= "<td>".$v['content']."</td>";
                    $all .= "<td>中度活动</td>";
                    $all .= "<td>".date('Y-m-d',$v['add_time']).' 至 '.date('Y-m-d',$v['end_time'])."</td></tr>";
                }
                if($v['level'] == 1){
                    $all .= "<tr>";
                    $all .= "<td>".$v['title']."</td>";
                    $all .= "<td>".$v['desc']."</td>";
                    $all .= "<td>".$v['content']."</td>";
                    $all .= "<td>小额活动</td>";
                    $all .= "<td>".date('Y-m-d',$v['add_time']).' 至 '.date('Y-m-d',$v['end_time'])."</td></tr>";
                }
            }
            $this->success($all);
        }else{
	        $this->error('没有数据了');
        }
    }

	public function getNotice(){
		$count = I('count');
		$top = I('top');
        $game_role = session('game_role');
        $channel_role = session('channel_role');

        if($game_role != 'all') $where['appid'] = array('in',$game_role.',0');
        if($channel_role != 'all') $where['cid'] = array('in',$channel_role.',0');
        $where['top'] = $top;
        $where['status'] = 1;
        $notice = M('notice')->where($where)->limit($count,5)->order('add_time desc')->select();
//            echo M()->_sql();die;
        $users = M('users')->where(array('user_type'=>1))->getField('id,user_login');
        foreach($notice as $k=>$v){
            $notice[$k]['user'] = $users[$v['uid']];
            $notice[$k]['add_time'] = date('Y-m-d H:i',$v['add_time']);
        }

		$this->success($notice);
	}

	private function getlastMonthDays()
	{
		$date = date('Y-m-d');
		$timestamp=strtotime($date);
		$firstday=date('Y-m-01',strtotime(date('Y',$timestamp).'-'.(date('m',$timestamp)-1).'-01'));
		$lastday=date('Y-m-d',strtotime("$firstday +1 month -1 day"));
		return array($firstday,$lastday);
	}

	private function array_sort($arr,$keys,$type='desc'){
		$keysvalue = $new_array = array();
		foreach ($arr as $k=>$v){
			$keysvalue[$k] = $v[$keys];
		}
		if($type == 'asc'){
			asort($keysvalue);
		}else{
			arsort($keysvalue);
		}
		reset($keysvalue);
		foreach ($keysvalue as $k=>$v){
			$new_array[$k] = $arr[$k];
		}
		return $new_array;
	}
	
	private function hidtel($phone)
	{
		$IsWhat = preg_match('/(1[358]{1}[0-9])[0-9]{4}([0-9]{4})/i',$phone); //手机号码
		if($IsWhat == 1){
			return  preg_replace('/(1[358]{1}[0-9])[0-9]{4}([0-9]{4})/i','$1****$2',$phone);
		}else{
			return $phone;
		}
	}

}