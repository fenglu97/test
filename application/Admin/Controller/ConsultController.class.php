<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/9/18
 * Time: 15:18
 */

namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class ConsultController extends AdminbaseController{

    /**
     * 列表
     */
    public function index(){
        $name = I('name');
        $status = I('status');
        $start = I('start');
        $end = I('end');
        $filter = I('filter');

        $where = '';
        if($name) $where['p.username'] = array('like',"%{$name}%");
        if($status > 0) $where['c.status'] = $status;
        if($start){
            $map['start'] = I('start');
            $where['c.create_time'][] = array('egt',strtotime(I('start')));
        }
        if($end){
            $map['end'] = I('end');
            $where['c.create_time'][] = array('elt',strtotime(I('end').' 23:59:59'));
        }

        $join ='';
        $field = 'p.username,c.*';
        $group = '';
        if($filter == 1)
        {
            $where['ci.audit'] = 2;
            $join = 'left join __CONSULT_INFO__ ci on ci.consult_id = c.id';
            $field = 'p.username,c.*,count(*) anwser';
            $group = 'ci.consult_id';
        }


        $count = M('consult c')->join('left join __PLAYER__ p on p.id=c.uid')->join($join)->where($where)->order('c.create_time desc')->getfield('count(distinct(c.id))');

        $page = $this->page($count,20);
        $data = M('consult c')
                ->field($field)
                ->join('left join __PLAYER__ p on p.id=c.uid')
                ->join($join)
                ->where($where)
                ->group($group)
                ->limit($page->firstRow,$page->listRows)
                ->order('create_time desc')->select();


        if($data){
            foreach($data as $k=>$v){
                $info = get_185_gameinfo($v['appid'],2);
                $data[$k]['gamename'] = $info['gamename'];
                $data[$k]['count'] = M('consult_info')->where(array('consult_id'=>$v['id']))->count();
                if($filter !=1)
                {
                    $data[$k]['anwser'] = M('consult_info')->where(array('consult_id'=>$v['id'],'audit'=>2))->count();
                }
            }
        }


        $this->filter = $filter;
        $this->p = I('p')?I('p'):1;
        $this->status = $status;
        $this->name = $name;
        $this->start = $start;
        $this->end = $end;
        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->display();
    }

    /**
     * 新增提问
     */
    public function add(){
        if(IS_POST){
            $data = array(
                'uid' => I('uid'),
                'appid' => I('appid'),
                'money' => I('money'),
                'content' => I('content'),
                'status' => I('status') == 'on' ? 2 : 1,
                'do_user' => I('status') == 'on' ? 'admin' : '',
                'create_time' => time()
            );
            if(!trim($data['content'],' ')){
                $this->error('内容不能为空');
            }
            if(!M('player')->where(array('id'=>$data['uid']))->find()){
                $this->error('没有该用户ID');
            }

            if(M('consult')->add($data)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->reward = C('REWARD');
            $this->display();
        }
    }

    /**
     * 审核
     */
    public function review(){
        $id = I('id');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        if(M('consult')->where(array('id'=>array('in',$id)))->setField(array('status'=>2,'do_user'=>session('name'),'audit_time'=>time())) !== false){
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 删除
     */
    public function del(){
        $id = I('id');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        $where['id'] = array('in',$id);
        $map['consult_id'] = array('in',$id);
        if(M('consult')->where($where)->delete()){
            M('consult_info')->where($map)->delete();
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }

}