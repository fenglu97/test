<?php
/**
 * 自推广排行榜
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class ZtgRankController extends AdminbaseController
{
    public function index()
    {
        $admin_id = SESSION('ADMIN_ID');
        $channel = M('channel')->where(array('admin_id'=>$admin_id))->find();
        $effective = M('users')->where(array('id'=>$admin_id))->getfield('effective');
        $this->assign('effective',$effective);
        $work_day = (strtotime(date('Y-m-d',time()).' 00:00:00')-strtotime(date('Y-m-d',$effective).' 00:00:00'))/(3600*24)+1;
        $this->assign('work_day',$work_day);
        $is_tg = 0;
        if($channel && $channel['type'] == 2)
        {
            $this->assign('channel_name',$channel['name']);
            $inpour_model = M('inpour');
            $pay_by_day_model = M('pay_by_day');
            $player_model = M('player');
            $tg_qualified_info_model = M('tg_qualified_info');

            $is_tg = 1;
            //流水信息
            $tg_data = get_tg_data($channel['id']);

            $this->assign('tg_data',$tg_data);

            //昨日充值 今日充值

            $today_timestamp = strtotime(date('Y-m-d',time()));
            $yesterday_timestamp = strtotime(date('Y-m-d',time()))-3600*24;

            $yesterday_inpour_new = $inpour_model->
            where(array('create_time'=>array(array('egt',$yesterday_timestamp),array('lt',$today_timestamp)),'status'=>1,'cid'=>$channel['id']))->
            cache(true)->
            getfield('sum(getmoney)');


            $today_inpour_new = $inpour_model->
            where(array('create_time'=>array(array('egt',$today_timestamp)),'status'=>1,'cid'=>$channel['id']))->
            cache(true)->
            getfield('sum(getmoney)');


            $this->assign('today_inpour',sprintf("%.2f",$today_inpour_new));
            $this->assign('yesterday_inpour',sprintf("%.2f",$yesterday_inpour_new));

            //昨日推广奖金 今日推广奖金
            $yesterday_tg_bonus = (int)$tg_qualified_info_model
                ->where(array('create_time'=>array(array('egt',$yesterday_timestamp)),'channel'=>$channel['id']))
                ->cache(true)
                ->getfield('tg_qualified_bonus');


            //获取今日的推广数据
            //getTodayData();
            $today_tg_counts = M('spread_detail')
                ->where(array('channel'=>$channel['id'],'createTime'=>array(array('egt',$today_timestamp)),'status'=>1))
                ->cache(true)
                ->count();


            $today_tg_bonus = tg_bonus($today_tg_counts);

            $this->assign('yesterday_tg_bonus',sprintf("%.2f",$yesterday_tg_bonus));
            $this->assign('today_tg_bonus',sprintf("%.2f",$today_tg_bonus));

            //本月 今日绩效有效注册数
            $today_valid_users = $player_model
                ->where(array('channel'=>$channel['id'],'first_login_time'=>array(array('egt',$today_timestamp))))
                ->cache(true)
                ->getfield('count(distinct(machine_code)) as valid_new_user');

            $month_start = date('Y-m-01',time());

            $month_valid_users = $pay_by_day_model
                ->where(array('time'=>array(array('egt',$month_start)),'cid'=>$channel['id']))
                ->cache(true)
                ->getfield('sum(valid_new_user)');


            $this->assign('today_valid_users',$today_valid_users);
            $this->assign('month_valid_users',$month_valid_users+$today_valid_users);

            //本月 今日绩效扣除
            $month_task = get_channel_task($channel['id'],date('Y-m',time()),2);
            $today_task = get_channel_task($channel['id'],date('Y-m-d',time()),1);

            $month_performance_deduct = sprintf("%.2f",get_performance_deduct($month_valid_users+$today_valid_users,$month_task[0]['task']));
            $today_performance_deduct = sprintf("%.2f",get_performance_deduct($today_valid_users,$today_task[0]['task']));

            $this->assign('month_performance_deduct',$month_performance_deduct);
            $this->assign('today_performance_deduct',$today_performance_deduct);


        }
        $this->assign('is_tg',$is_tg);
        $this->display();
    }
}
