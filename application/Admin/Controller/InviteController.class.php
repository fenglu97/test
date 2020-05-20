<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/12/8
 * Time: 10:02
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class InviteController extends AdminbaseController{

    public function index(){
        $request = I('');
        $model = M('player');
        $start = $request['start'] ? $request['start'] : date('Y-m-d',strtotime('-7 day'));
        $end = $request['end'] ? $request['end'] : date('Y-m-d',strtotime('-1 day'));


        if($request['cid'] != 0)
            $where['p1.channel'] = $request['cid'];
        $where['p1.create_time'] = array('between',array(strtotime($start),strtotime($end.' 23:59:59')));
        $where['p1.referee_uid'] = array('neq',0);
        $data = M('player p1')
                ->field('p.channel,c.name,p.username,count(*) count,p1.referee_uid,count(if(p1.system=1,1,null)) android,count(if(p1.system=2,1,null)) ios')
                ->join('join __PLAYER__ p on p1.referee_uid=p.id')
                ->join('left join __CHANNEL__ c on c.id=p.channel')
                ->where($where)
                ->group('p1.referee_uid')
                ->order('count desc')
                ->limit(100)
                ->select();

        foreach ($data as $v){
            $and_count += $v['android'];
            $ios_count += $v['ios'];
            $all_count += $v['count'];
        }

        $this->and_count = $and_count;
        $this->ios_count = $ios_count;
        $this->all_count = $all_count;
        $this->data = $data;
        $this->cid = $request['cid'];
        $this->start = $start;
        $this->end = $end;
        $this->max = date('Y-m-d',strtotime('-1 day'));
        $this->selected_channel_type = I('channel_type');
		$this->channel_type = C('channel_type');
        $this->display();
    }
}