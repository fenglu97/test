<?php
/**
 * 渠道军团接口
 * @author qing.li
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class ChannelLegionController extends AppframeController
{
    public function sync_data()
    {
        set_time_limit(0);
        $list = M('channel_legion')->select();
        $last_month_time = strtotime(date('Y-m-01 00:00:00',strtotime('-1 month')));
        $current_month_time = strtotime(date('Y-m-01 00:00:00',time()));
        foreach($list as $v)
        {
            $cids = M('channel')->where(array('id|parent'=>array('in',$v['channels'])))->getfield('id',true);

            $pay_info = M('inpour')
                ->where(array('cid'=>array('in',implode(',',$cids)),'status'=>1,'create_time'=>array(array('egt',$last_month_time),array('lt',$current_month_time))))
                ->getfield('sum(getmoney)');



            $data = array();
            $data['channel_legion_id'] = $v['id'];
            $data['channels'] = $v['channels'];
            $data['money'] = $pay_info;
            $data['target'] = M('tg_info')->where(array('channel_legion'=>$v['id'],'time'=>date('Y-m',strtotime('-1 month'))))->getfield('sum(target)');
            $data['create_time'] = date('Y-m',strtotime('-1 month'));

            M('channel_legion_log')->add($data);

        }
        exit('excuted successfully');
    }
}