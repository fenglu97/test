<?php
/**
 *自推广渠道军团
 * @author qing.li
 * @date 2018-08-10
 */

namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class ChannelLegionController extends AdminbaseController
{
    public function index()
    {
        $channel = I('channel');
        $time = I('time')?I('time'):date('Y-m');
        $map = array();

        if($channel) $map['_string'] = "FIND_IN_SET($channel,channels)";

        $count = M('channel_legion')->where($map)->count();
        $page = $this->page($count, 20);
        $list = M('channel_legion')->where($map)->limit($page->firstRow, $page->listRows)->select();

        $channel_names = M('channel')->where(array('type'=>2,'status'=>1))->getfield('id,name',true);

        if($time  == date('Y-m'))
        {
            $is_edit = 1;
            $current_month_time = strtotime(date('Y-m-01').' 00:00:00');
            foreach($list as $k=>$v)
            {
                //获取当月目标
                $list[$k]['current_target'] = (int)M('tg_info')->where(array('channel_legion'=>$v['id'],'time'=>date('Y-m')))->getfield('sum(target)');

                $channels = explode(',',$v['channels']);
                $cids = M('channel')->where(array('id|parent'=>array('in',$v['channels'])))->getfield('id',true);

                $pay_info = M('inpour')
                    ->where(array('cid'=>array('in',implode(',',$cids)),'status'=>1,'create_time'=>array('egt',$current_month_time)))
                    ->getfield('sum(getmoney)');



                $list[$k]['current_money'] = $pay_info;

                $list[$k]['complate_rate'] = sprintf("%.2f",$list[$k]['current_money']/$list[$k]['current_target']*100);

                foreach($channels as $channel_v)
                {
                    $list[$k]['channel_names'].=$channel_names[$channel_v].',';
                }
                $list[$k]['channel_names'] = trim($list[$k]['channel_names'],',');

            }

            //合计目标
            $total_target = (int)M('tg_info')->where(array('time'=>date('Y-m')))->getfield('sum(target)');
            //合计完成
            $channels = M('channel_legion')->field('channels')->select();
            $in = '';
            if (is_array($channels))
            {
                foreach($channels as $v)
                {
                    $in.=trim($v['channels']).',';
                }
            }
            $in = trim($in,',');
            $cids = M('channel')->where(array('id|parent'=>array('in',$in)))->getfield('id',true);

            $pay_info = M('inpour')
                ->where(array('cid'=>array('in',implode(',',$cids)),'status'=>1,'create_time'=>array('egt',$current_month_time)))
                ->getfield('sum(getmoney)');

            $total_pay = $pay_info;
        }
        else
        {
            $log = M('channel_legion_log')->where(array('create_time'=>$time))->getfield('channel_legion_id,target,money,channels',true);
            foreach($list as $k=>$v)
            {
                $channels = explode(',',$log[$v['id']]['channels']);
                foreach($channels as $channel_v)
                {
                    $list[$k]['channel_names'].=$channel_names[$channel_v].',';
                }
                $list[$k]['channel_names'] = trim($list[$k]['channel_names'],',');
                $list[$k]['current_money'] = $log[$v['id']]['money'];
                $list[$k]['current_target'] = $log[$v['id']]['target'];
                $list[$k]['complate_rate'] = sprintf("%.2f",$list[$k]['current_money']/$list[$k]['current_target']*100);
            }

            $last_total =  M('channel_legion_log')->where(array('create_time'=>$time))->field('sum(target) target,sum(money) money')->find();
            $total_target = $last_total['target'];
            $total_pay = $last_total['money'];
        }



        $this->assign('is_edit',$is_edit);
        $this->assign('total_target',$total_target);
        $this->assign('total_pay',$total_pay);
        $this->assign('total_comlate_rate',sprintf("%.2f",$total_pay/$total_target*100));
        $this->assign('page',$page->show('Admin'));
        $this->assign('list',$list);
        $this->assign('channel_list',get_channel_list($channel,2,0));
        $this->assign('time',$time);
        $this->display();
    }

    public function add()
    {
        //查询不能选中的
        $channels = M('channel_legion')->field('channels')->select();
        $not_in = '';
        if (is_array($channels))
        {
            foreach($channels as $v)
            {
                $not_in.=trim($v['channels']).',';
            }
        }

        $not_in = trim($not_in,',');
        if(IS_POST) {
            $data = I('post.');
            if(array_intersect($data['channel'],explode(',',$not_in)))
            {
                $this->error('渠道不能跟其他军团重复，请重新添加');
            }

            $data['create_time'] = time();
            $data['channels'] = $data['channel']?implode(',',$data['channel']):'';



           if(M('channel_legion')->add($data)!==false)
           {
               $this->success('添加成功');
           }
           else
           {
               $this->error('添加失败');
           }

        }

        $this->assign('not_in',$not_in);
        $this->display();
    }

    public function edit()
    {
        $id = I('id');
        //查询不能选中的
        $channels = M('channel_legion')->where(array('id'=>array('neq',$id)))->field('channels')->select();
        $not_in = '';
        if (is_array($channels))
        {
            foreach($channels as $v)
            {
                $not_in.=trim($v['channels']).',';
            }
        }
        if(IS_POST)
        {
            $data = I('post.');
            if(array_intersect($data['channel'],explode(',',$not_in)))
            {
                $this->error('渠道不能跟其他军团重复，请重新添加');
            }

            $data['channels'] = $data['channel']?implode(',',$data['channel']):'';

            if(M('channel_legion')->where(array('id'=>$id))->save($data)!==false)
            {
                $this->success('修改成功');
            }
            else
            {
                $this->error('修改失败');
            }

        }

        $info = M('channel_legion')->where(array('id'=>$id))->find();

        $this->assign('not_in',$not_in);
        $this->assign('info',$info);
        $this->display();

    }

    public function del()
    {
        $id = I('id');
        if(M('channel_legion')->where(array('id'=>$id))->delete()!==false)
        {
            $this->success('删除成功');
        }
        else
        {
            $this->error('删除失败');
        }
    }

}