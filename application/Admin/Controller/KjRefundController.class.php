<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2019/1/8
 * Time: 14:13
 */
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class KjRefundController extends AdminbaseController{

    /**
     * 列表
     */
    public function index(){
        $this->display();
    }

    /**
     * 检查订单
     */
    public function checkOrder(){
        $order = trim(I('order'));
        $res = M('inpour i')
                ->field('i.*,c.name cname,g.game_name')
                ->join('left join __GAME__ g on g.id=i.appid')
                ->join('left join __CHANNEL__ c on c.id=i.cid')
                ->where(array('i.orderID'=>$order))->find();

        if(empty($res)) $this->error('没有该订单');
        if($res['status'] == 3) $this->error('该订单为无效订单');
        if($res['payWay'] != 2) $this->error('该功能只支持快接退款');


        $html = <<<HTML
        <div style="padding:15px">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <th style="width:23%">本地单号</th>
                        <th>{$res['orderID']}</th>
                    </tr>
                    <tr>
                        <th >快接单号</th>
                        <th>{$res['jz_other']}</th>
                    </tr>
                    <tr>
                        <th >玩家账号</th>
                        <th>{$res['username']}</th>
                    </tr>
                    <tr>
                        <th >游戏名</th>
                        <th>{$res['game_name']}</th>
                    </tr>
                    <tr>
                        <th >渠道名</th>
                        <th>{$res['cname']}</th>
                    </tr>
                    <tr>
                        <th >订单金额</th>
                        <th>{$res['money']}</th>
                    </tr>
                    <tr>
                        <th >实际收入</th>
                        <th>{$res['getmoney']}</th>
                    </tr>
                    <tr>
                        <th >角色名</th>
                        <th>{$res['roleNAME']}</th>
                    </tr>
                    <tr>
                        <th >区服名</th>
                        <th>{$res['serverNAME']}</th>
                    </tr>
                    <tr>
                        <th >退款原因</th>
                        <th><textarea class="desc"></textarea></th>
                    </tr>
                </tbody>
            </table>
        </div>    
HTML;


        $this->success($html);
    }

    /**
     * 退款
     */
    public function doRefund(){
        $order = I('order');
        $desc = I('desc');

        $res = D('Common/Inpour')->refund($order,$desc);
        if($res === false){
            $this->error('订单错误');
        }else{
            if($res['info'] == 1){
                $id = M('inpour')->where(array('orderID'=>$order))->getField('id');
                if(M('kj_refund')->add(array(
                    'inpour_id'=>$id,
                    'refund_no'=>$res['data']['refund_no'],
                    'desc'=>$desc,
                    'adminid'=>session('ADMIN_ID'),
                    'create_time'=>time()
                )) !== false){
                    $this->success('退款成功');
                }else{
                    $this->error('写入失败');
                }
            }else{
                $this->error($res['info']);
            }
        }
    }
}