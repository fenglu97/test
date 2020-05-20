<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2019/8/14
 * Time: 14:38
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class SupersignController extends AdminbaseController{

    public function index(){
        $appleAccount = I('appleAccount');
        $sort = I('sort');

        $where = '';
        if($appleAccount) $where['appleAccount'] = array('like','%'.$appleAccount.'%');
        if($sort > 0){
            switch ($sort){
                case 1:$order = 'expire';break;
                case 2:$order = 'iphone desc';break;
                case 3:$order = 'sort desc';break;
                default :$order = 'id';break;
            }
        }

        $data = M('dl_account',null,C('SUPER_SIGN'))->where($where)->order($order)->select();

        $this->data = $data;
        $this->sort = $sort;

        $this->display();
    }

    public function add(){
        if(IS_POST){
            $data = I('');
            $data['expire'] = strtotime($data['expire']);
            if(M('dl_account',null,C('SUPER_SIGN'))->add($data) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->display();
        }
    }

    public function edit(){
        $id = I('id');
        if(IS_POST){
            $data = I('');
            $data['expire'] = strtotime($data['expire']);
            if(M('dl_account',null,C('SUPER_SIGN'))->save($data) !== false){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->data = M('dl_account',null,C('SUPER_SIGN'))->where(array('id'=>$id))->find();
            $this->id = $id;
            $this->display('add');
        }
    }

    public function del(){
        $id = I('id');
        if(!$id) $this->error('请选择数据');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        $where['id'] = array('in',$id);
        if(M('dl_account',null,C('SUPER_SIGN'))->where($where)->delete()){

            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

    /**
     * 清理设备
     */
    public function clean(){
        $id = I('id');
        if(M('dl_device',null,C('SUPER_SIGN'))->where(array('aid'=>$id))->setField(array('isAdd'=>0,'appleAccount'=>'','aid'=>0)) !== false){
            $this->success();
        }else{
            $this->error();
        }
    }

    /**
     * 支付列表
     */
    public function pay_log(){
        $where = '';
        $status = I('status',1);
        $udid = I('udid');
        $start = I('start');
        $end = I('end');

        if($status) $where['s.status'] = $status;
        if($udid) $where['s.udid'] = $udid;
        if($start) $where['create_time'][] = array('gt',strtotime($start));
        if($end) $where['create_time'][] = array('lt',strtotime($end.' 23:59:59'));

        $count = M('super_pay s')
                ->join('left join __CHANNEL__ c on c.id=s.cid')
                ->where($where)
                ->count();

        $page = $this->page($count,20);

        $data = M('super_pay s')
            ->field('s.*,c.name,g.game_name gname')
            ->join('left join __GAME__ g on g.id=s.appid')
            ->join('left join __CHANNEL__ c on c.id=s.cid')
            ->where($where)
            ->limit($page->firstRow,$page->listRows)
            ->order('id desc')
            ->select();

        if($status != 2){
            $total = M('super_pay')->field('sum(money) money,sum(getmoney) getmoney')->where(array('status'=>1))->find();
        }


        $this->parameter = array(
            'status' => $status,
            'udid' => $udid,
            'start' => $start,
            'end' => $end
        );
        $this->total = $total;
        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->display();
    }

    /**
     * 安装日志
     */
    public function install(){
        $this->display();
    }

    public function payUdid(){
        $id = I('id');
        $order = M('super_pay')->where(array('id'=>$id))->find();

        $key = '7fc835c5764e2ebe637ae9691f330dcb';
        $url = C('i_domain_url').'/download/payudid';

        $arr = array(
            'orderID' => $order['orderID'],
            'udid' => $order['udid'],
            'amount' => $order['money'],
            'pf' => 'mowan',
            'time' => $order['create_time'],
        );
	
        $arr['sign'] = md5(http_build_query($arr).'&key='.$key);
        M('super_pay')->where(array('orderID'=>$order['orderID']))->setField('remark',$url.'?'.http_build_query($arr));
        $res = curl_post($url,$arr);
        if($res == 'SUCCESS'){
            $set['state'] = 1;
        }
        $set['call_back'] = $res;
        M('super_pay')->where(array('orderID'=>$order['orderID']))->setField($set);
        if($res == 'SUCCESS'){
            $this->success();
        }else{
            $this->error();
        }
    }

    public function info(){
        $id = I('id');
        $info = M('super_pay')->field('remark,call_back')->where(array('id'=>$id))->find();
        $html = <<<HTML
        <div style="padding:15px">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <th style="width:22%">请求地址</th>
                        <th style="width:50%"><div style="width: 500px;word-wrap: break-word;">{$info['remark']}</div></th>
                    </tr>
                    <tr>
                        <th>返回值</th>
                        <th>{$info['call_back']}</th>
                    </tr>
                   
                </tbody>
            </table>
        </div>
HTML;
        $this->success($html);
    }
}