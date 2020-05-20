<?php
/**
 * 问答详情控制器
 * @author qing.li
 * @date 2018-07-10
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class ConsultInfoController extends AdminbaseController
{
    public function unaudit_list()
    {

        $start = I('start');
        $end = I('end');
        $map = array();
        $map['a.audit'] = 2;

        if($start) $map['a.create_time'][] = array('egt',strtotime($start. '00:00:00'));
        if($end) $map['a.create_time'][] = array('elt',strtotime($end.' 23:59:59'));


        $consult_info_model = M('consult_info');

        $count = $consult_info_model
            ->alias('a')
            ->join('left join `bt_player` b on a.uid = b.id')
            ->where($map)
            ->count();

        $page = $this->page($count, 20);


        $list = $consult_info_model
            ->field('a.*,b.username')
            ->alias('a')
            ->join('left join `bt_player` b on a.uid = b.id')
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('consult_id desc,top desc,is_reward desc,create_time desc')
            ->select();

        $consult_ids = '';
        foreach($list as $k=>$v)
        {
            $consult_ids.=$v['consult_id'].',';
        }

        $consult_ids = trim($consult_ids,',');

        $consult_data = M('consult c')
            ->join('left join __PLAYER__ p on p.id=c.uid')
            ->where(array('c.id'=>array('in',$consult_ids)))
            ->getfield('c.id,p.username,c.content,c.appid',true);

        foreach($consult_data as $k=>$v)
        {
            $info = get_185_gameinfo($v['appid'],2);
            $consult_data[$k]['gamename'] = $info['gamename'];
        }

        $this->assign('consult_data',$consult_data);
        $this->assign('list',$list);
        $this->assign('start',$start);
        $this->assign('end',$end);
        $this->assign('page',$page->show('Admin'));
        $this->display();
    }

    public function index()
    {
        $consult_id = I('get.consult_id');
        $name = I('name');
        $audit = I('audit');
        $start = I('start');
        $end = I('end');
        $url = html_entity_decode(I('url'));

        $map = array();
        $map['a.consult_id'] = $consult_id;

        if($name) $map['b.username'] = array('like',$name.'%');
        if($audit) $map['a.audit'] = $audit;
        if($start) $map['a.create_time'][] = array('egt',strtotime($start. '00:00:00'));
        if($end) $map['a.create_time'][] = array('elt',strtotime($end.' 23:59:59'));

        $consult_info_model = M('consult_info');

        $count = $consult_info_model
            ->alias('a')
            ->join('left join `bt_player` b on a.uid = b.id')
            ->where($map)
            ->count();



        $page = $this->page($count, 20);

        $list = $consult_info_model
            ->field('a.*,b.username')
            ->alias('a')
            ->join('left join `bt_player` b on a.uid = b.id')
            ->where($map)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('top desc,is_reward desc,create_time desc')
            ->select();

        $consult_content = M('consult')->where(array('id'=>$consult_id))->Getfield('content');

        $this->assign('url',$url);
        $this->assign('consult_content',$consult_content);
        $this->assign('list',$list);
        $this->assign('consult_id',$consult_id);
        $this->assign('name',$name);
        $this->assign('audit',$audit);
        $this->assign('start',$start);
        $this->assign('end',$end);
        $this->assign('page',$page->show('Admin'));
        $this->display();

    }

    public function top()
    {
        $id = I('id');
        if(!$id) $this->error('请选择数据');
        $top = I('top')>0?1:0;

        if(M('consult_info')->where(array('id'=>$id))->setField('top',$top) !== false)
        {
            $this->success('修改成功');
        }else
        {
            $this->error('修改失败');
        }
    }

    public function audit()
    {
        $id = I('id');
        if(!$id) $this->error('请选择数据');
        if(is_array($id)){
            $id = implode(",",$id);
        }

        $ids = explode(',',$id);

        //只能操作未审核的
        $ids = M('consult_info')->where(array('id'=>array('in',implode(',',$ids)),'audit'=>2))->getfield('id',true);
        $audit = (I('get.audit')!=1)?3:1;

        if(!empty($ids))
        {
            $consult_infos = M('consult_info')->where(array('id'=>array('in',implode(',',$ids))))->getfield('id,uid,consult_id,create_time',true);
        }

        foreach($ids as $id)
        {
            $consult_info = $consult_infos[$id];
            $flagA = true;
            $flagB = true;
            $flagC = true;
            $flagD = true;
            $flagE = true;

            $player_mobile = M('player')->where(array('id'=>$consult_info['uid']))->getfield('mobile');

            $Model = M(); // 实例化一个空对象
            $Model->startTrans(); // 开启事务

            $flagA = $Model->table('bt_consult_info')->where(array('id'=>$id))->setField('audit',$audit);
            //审核成功后查询该条回答是否是该用户当天前10条回答，如果是，奖励50个金币
            if(preg_match("/^1\d{10}$/", $player_mobile) && $audit == 1 && M('task')->where(array('uid'=>$consult_info['uid'],'type'=>9,'consult_id'=>$consult_info['consult_id']))->count() == 0)
            {
                $day_consult_bonus = C('DAY_CONSULT_BONUS');
                $create_time = strtotime(date('Y-m-d',$consult_info['create_time']).' 00:00:00');
                //如果该用户在该问题未获得问答奖励
                $current_task = M('task')
                    ->where(array('uid'=>$consult_info['uid'],'type'=>9,'create_time'=>array(array('egt',$create_time),array('lt',$create_time+3600*24))))
                    ->count();

                if($current_task < $day_consult_bonus['num'])
                {
                    //如果该用户回答问题奖励小于10个，可以获得奖励
                    $flagB = $Model->table('bt_player')->where(array('id'=>$consult_info['uid']))->setInc('coin',$day_consult_bonus['bonus']);
                    $flagC = $Model->table('bt_consult_info')->where(array('id'=>$id))->setField(array('is_task_bonus'=>1));
                    $current_coin = M('player')->where(array('id'=>$consult_info['uid']))->getfield('coin');
                    //添加日志
                    $log_data = array(
                        'uid'=>$consult_info['uid'],
                        'type'=>13,
                        'coin_change'=>$day_consult_bonus['bonus'],
                        'coin_counts'=>$current_coin,
                        'consult_time'=>$consult_info['create_time'],
                        'create_time'=>time()
                    );

                    $flagD = $Model->table('bt_coin_log')->add($log_data);

                    $flagE = $Model
                        ->table('bt_task')
                        ->add(array('uid'=>$consult_info['uid'],'type'=>9,'consult_id'=>$consult_info['consult_id'],'create_time'=>$consult_info['create_time']));
                }
            }

            if(($flagA!==false) && ($flagB!==false) && ($flagC!==false) && ($flagD!==false) && ($flagE!==false))
            {
                $Model->commit();
            }
            else
            {
                $Model->rollback();
                $this->error('操作失败');
                exit('');
            }
        }

        $this->success('操作成功');



    }

    public function del()
    {
        $id = I('id');
        if(!$id) $this->error('请选择数据');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        $where['id'] = array('in',$id);
        if(M('consult_info')->where($where)->delete())
        {
            $this->success('删除成功');
        }
        else
        {
            $this->error('删除失败');
        }
    }


}