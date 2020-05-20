<?php
/**
 * 精选产品接口
 * @author qing.li
 * @date 2018-08-27
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class HotProductController extends AppframeController
{

    private $rebate_cycle = array(
        1=>'立刻发放',
        2=>'12小时内发放',
        3=>'24小时内发放',
        4=>'72小时内发放',
    );
    private $troubleshooting = array(
        1=>'1天内解决',
        2=>'2天内解决',
        3=>'3天内解决',
        4=>'1周内解决',
        5=>'1个月内解决',
    );
    private $yf_cooper_degree = array(
        1=>'非常配合',
        2=>'一般配合',
        3=>'不配合',
        4=>'基本不管',
    );

    public function get_list()
    {
        $page = I('page')?I('page'):1;
        $page_size = (I('page_size')>=1 && I('page_size')<=100)?I('page_size'):10;
        $search = I('search');
        $theme = I('theme');
        $type = I('type');

        $map = array('t1.status'=>1);
        if($search) $map['t2.game_name'] = array('like',"%{$search}%");
        if($theme) $map['t1.theme'] = array('like',"%{$theme}%");
        if($type) $map['t1.type'] = array('like',"%{$type}%");

        $count = M('hot_product')->alias('t1')->join('left join bt_game t2 on t1.appid = t2.id')->where($map)->count();
        $list = M('hot_product')
            ->field('t1.theme,t1.type,t1.imgs,t2.game_name,t1.appid,t1.rebate_cycle,t1.troubleshooting,t1.yf_cooper_degree')
            ->alias('t1')
            ->join('left join bt_game t2 on t1.appid = t2.id')
            ->where($map)
//            ->limit($page_size*($page-1), $page_size)
            ->order('t1.order asc,t1.create_time desc')
            ->select();


        //获取所有产品appid
        $appids = M('hot_product')->where(array('status'=>1))->getfield('appid',true);

        //获取appid的接入类型
        $access_types = M('game')->where(array('id'=>array('in',implode(',',$appids))))->getfield('id,access_type');

        //查询作日排名 上周排名

        //sdk数据
        $yestertoday_new = M('pay_by_day')->
        where(array('time'=>date('Y-m-d',strtotime('-1 day'))))->
        group('appid')->
        cache(true)->
        getfield('appid,sum(pay_amount)',true);

        $lastweek_new = M('pay_by_day')->
        where(array('time'=>array(array('egt',date('Y-m-d', strtotime('-1 sunday')-6*3600*24)),array('elt',date('Y-m-d', strtotime('-1 sunday'))))))->
        group('appid')->
        cache(true)->
        getfield('appid,sum(pay_amount)',true);

        //BI数据
        $pay_model = M('pay','syo_',C('DB_OLDSDK_CONFIG'));

        $yestertoday_old = $pay_model
            ->where((array('status'=>1,'vip'=>2,'created'=>array(array('egt',strtotime(date('Y-m-d',strtotime('-1 day')).' 00:00:00')),array('lt',strtotime(date('Y-m-d',time()).' 00:00:00'))))))
            ->group("gameid")
            ->cache(true)
            ->getfield("gameid,sum(rmb) as money",true);

        $lastweek_old = $pay_model
            ->where((array('status'=>1,'vip'=>2,'created'=>array(array('egt',strtotime(date('Y-m-d', strtotime('-1 sunday')-6*3600*24).' 00:00:00')),array('lt',strtotime(date('Y-m-d',strtotime('-1 sunday')).' 00:00:00')+3600*24)))))
            ->group("gameid")
            ->cache(true)
            ->getfield("gameid,sum(rmb) as money",true);

        //u8数据
        $u8_yseterday = M('tchannelsummary t',' ',C('U8DB'))
            ->join('left join uchannel u on u.appID=t.appID')
            ->where(array('t.masterID'=>array('neq',89),'u.masterID'=>89,'_string'=>"TO_DAYS(NOW()) - TO_DAYS(currTime) <= 1"))
            ->group('t.appID')
            ->order('money desc')
            ->getfield('u.cpAppID,sum(t.money) money');

        $u8_lastweek = M('tchannelsummary t',' ',C('U8DB'))
            ->join('left join uchannel u on u.appID=t.appID')
            ->where(array('t.masterID'=>array('neq',89),'u.masterID'=>89,'_string'=>"YEARWEEK(currTime,1) = YEARWEEK(now(),1)-1"))
            ->group('t.appID')
            ->order('money desc')
            ->getfield('u.cpAppID,sum(t.money) money');

        $appids = M('game')->where(array('status'=>1))->getfield('id',true);

        $yessterday_list = array();
        $lastweek_list = array();
        foreach($appids as $appid)
        {
            $yesterday_money = $yestertoday_new[$appid] + $yestertoday_old[$appid] + $u8_yseterday[$appid]/100;
            $lastweek_money = $lastweek_new[$appid] + $lastweek_old[$appid] + $u8_lastweek[$appid]/100;
            if ($yesterday_money > 0)
            {
                $yessterday_list[] = array('appid'=>$appid,'money'=>$yesterday_money);
            }

            if($lastweek_money > 0)
            {
                $lastweek_list[] = array('appid'=>$appid,'money'=>$lastweek_money);
            }

        }

        foreach($yessterday_list as $k=>$v)
        {
            $money_k[$k] = $v['money'];
        }
        array_multisort($money_k, SORT_DESC, $yessterday_list);

        $money_k = array();
        foreach($lastweek_list as $k=>$v)
        {
            $money_k[$k] = $v['money'];
        }
        array_multisort($money_k, SORT_DESC, $lastweek_list);


        $yesterday_rank = array();
        $lastweek_rank = array();

        foreach($yessterday_list as $k=>$v)
        {
            $yesterday_rank[$v['appid']] = $k+1;
        }

        foreach($lastweek_list as $k=>$v)
        {
            $lastweek_rank[$v['appid']] = $k+1;
        }


        $rank = array();
        foreach($list as $k=>$v)
        {
            $rank[$k] = $yesterday_rank[$v['appid']];
            $list[$k]['access_type'] = $access_types[$v['appid']];
            $list[$k]['yesterday_rank'] = $yesterday_rank[$v['appid']];
            $list[$k]['lastweek_rank'] = $lastweek_rank[$v['appid']];
            $list[$k]['rebate_cycle'] = isset($this->rebate_cycle[$list[$k]['rebate_cycle']])?$this->rebate_cycle[$list[$k]['rebate_cycle']]:'';
            $list[$k]['troubleshooting'] = isset($this->troubleshooting[$list[$k]['troubleshooting']])?$this->troubleshooting[$list[$k]['troubleshooting']]:'';
            $list[$k]['yf_cooper_degree'] = isset($this->yf_cooper_degree[$list[$k]['yf_cooper_degree']])?$this->yf_cooper_degree[$list[$k]['yf_cooper_degree']]:'';
            $imgs = json_decode($v['imgs'],true);

            $list[$k]['imgs'] = array();
            foreach($imgs as $imgs_v)
            {
                $list[$k]['imgs'][] = sp_get_image_preview_url($imgs_v);
            }
        }
        array_multisort($rank, SORT_ASC, $list);
        $other = array();
        foreach($list as $k=>$v){
            if($v['yesterday_rank'] == null){
                $other[$k] = $v;
                unset($list[$k]);
            }

        }
        $list = array_merge($list,$other);

        $list = array_slice($list,$page_size*($page-1),$page_size);
        $this->ajaxReturn(array('list'=>$list,'count'=>$count));

    }

}