<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/6/5
 * Time: 14:17
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class AccountTradeController extends AdminbaseController{

    /**
     * 商品列表
     */
    public function goodsIndex(){
        $title = I('title');
        $appid = I('appid');
        $account = I('account');
        $status = I('status');
        $where = '';

        if($title) $where['pro.title'] = array('like','%'.$title.'%');
        if($appid) $where['pro.appid'] = $appid;
        if($account) $where['pro.account'] = array('like','%'.$account.'%');
        if($status) $where['pro.status'] = $status;

        $count = M('products pro')
            ->join('left join __GAME__ g on g.id=pro.appid')
            ->join('left join __PLAYER__ p on p.id=pro.uid')
            ->where($where)
            ->count();

        $page = $this->page($count,20);

        $data = M('products pro')
                ->field('pro.*,g.game_name,p.username')
                ->join('left join __GAME__ g on g.id=pro.appid')
                ->join('left join __PLAYER__ p on p.id=pro.uid')
                ->where($where)
                ->order('pro.order desc,pro.create_time desc')
                ->limit($page->firstRow,$page->listRows)->select();

        foreach($data as &$v){
            $total_pay = M('inpour')->where(array('app_uid'=>$v['account'],'appid'=>$v['appid'],'status'=>array('in','1,2')))->sum('money');
            $v['total_pay'] = $total_pay ? : 0;
        }
        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->title = $title;
        $this->appid = $appid;
        $this->account = $account;
        $this->status = $status;
        $this->display();
    }

    /**
     * 商品页
     */
    public function review(){
        $id = I('id');
        $type = I('type');
        $data = M('products pro')
                ->field('pro.*,g.game_name')
                ->join('left join __GAME__ g on g.id=pro.appid')
                ->where(array('pro.id'=>$id))
                ->find();
        if($data['system'] == 1){
            $data['system'] = '安卓';
        }elseif($data['system'] == 2){
            $data['system'] = '苹果';
        }else{
            $data['system'] = '全系统';
        }
        $data['imgs'] = json_decode($data['imgs'],true);
        foreach ($data['imgs'] as $v){
            $imgs .= "<img style='width:71px;margin-right:5px' src='".C('FTP_URL')."$v'>";
        }
        $data['trade_imgs'] = json_decode($data['trade_imgs'],true);
        if(count($data['trade_imgs']) > 0){
            foreach($data[trade_imgs] as $v){
                $trade_imgs .= "<img style='width:71px;margin-right:5px' src='".C('FTP_URL')."$v'>";
            }
        }
        if($type == 1){
            $reason = '<textarea class="reason" style="width:530px" placeholder="审核不通过时填写"></textarea>';
        }else{
            $reason = '<textarea class="reason" readonly style="width:530px" placeholder="审核不通过时填写">'.$data['off_reason'].'</textarea>';
        }
        //交易次数
        $count = M('products')->where(array('account'=>$data['account'],'status'=>4))->count();
        //历史充值
        $total_pay = M('inpour')->where(array('app_uid'=>$data['account'],'appid'=>$data['appid'],'status'=>array('in','1,2')))->sum('money');
        $total_pay = $total_pay ? : 0;
        $html = <<<HTML
        <div style="padding:15px">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <th style="width:15%">标题</th>
                        <th>{$data['title']}</th>
                    </tr>
                    <tr>
                        <th>游戏</th>
                        <th>{$data['game_name']}</th>
                    </tr>
                    <tr>
                        <th>小号ID</th>
                        <th>{$data['account']}</th>
                    </tr>
                    <tr>
                        <th>交易次数</th>
                        <th>{$count}次</th>
                    </tr>
                    <tr>
                        <th>历史充值</th>
                        <th>{$total_pay}</th>
                    </tr>
                    <tr>
                        <th>出售价格</th>
                        <th>{$data['price']}</th>
                    </tr>
                    <tr>
                        <th>系统</th>
                        <th>{$data['system']}</th>
                    </tr>
                    <tr>
                        <th>区服</th>
                        <th>{$data['server_name']}</th>
                    </tr>
                    <tr>
                        <th>描述</th>
                        <th><div style="overflow-y: auto;height: 150px;">{$data['desc']}</div></th>
                    </tr>
                    <tr>
                        <th>游戏截图</th>
                        <th id="layer-photos">
                            {$imgs}
                        </th>
                    </tr>
                    <tr>
                        <th>交易截图</th>
                        <th id="trade-photos">
                            {$trade_imgs}
                        </th>
                    </tr>
                    <tr>
                        <th>审核意见</th>
                        <th>{$reason}</th>
                    </tr>
                </tbody>
            </table>
        </div>
HTML;
        $this->success($html);
    }

    /**
     * 提交审核
     */
    public function doReview(){
        $id = I('id');
        $status = I('status');
        $reason = I('reason');
        if($status == 2){
            $appid = M('products')->where(array('id'=>$id))->getField('appid');
            if(!M('game')->where(array('id'=>$appid))->getField('trade')){
                $this->error('该游戏已关闭');
            }
        }

        if(M('products')->where(array('id'=>$id))->setField(array('status'=>$status,'off_reason'=>$reason)) !== false){
            //审核不通过解禁账号
            if($status == 5){
                $account = M('products')->where(array('id'=>$id))->getField('account');
                M('player_closed')->where(array('uid'=>$account,'type'=>1))->delete();
            }
            //发通知短信
            $info = M('products')->where(array('id'=>$id))->find();
            $game_name = M('game')->where(array('id'=>$info['appid']))->getField('game_name');
            $mobile = M('player')->where(array('id'=>$info['uid']))->getField('mobile');
            if($status == 2){
                $msg = "恭喜您！您在".C('BOX_NAME')."盒子内提交的{$game_name}游戏账号出售请求已审核通过。如有疑问，请联系QQ：". C('SMS_QQ')."【".C('BOX_NAME')."】";
                M('products')->where(array('id'=>$id))->setField('publish_time',time());
            }else{
                $msg = "很遗憾！您在".C('BOX_NAME')."盒子内提交的{$game_name}游戏账号出售请求审核失败，失败原因：{$reason}。如有疑问，请联系QQ：". C('SMS_QQ')."【".C('BOX_NAME')."】";
            }
            if(sendSms($mobile,$msg)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 删除商品
     */
    public function delGoods(){
        $id = I('id');
        if(is_array($id)) $id = implode(",",$id);
        $status = M('products')->where(array('id'=>array('in',$id)))->select();
        foreach($status as $v){
            if($v['status'] != 5){
                $this->error('商品状态异常，请刷新后操作');
            }
        }
        if(M('products')->where(array('id'=>array('in',$id)))->delete()){
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 下架
     */
    public function change(){
        $id = I('id');
        $status = M('products')->where(array('id'=>$id))->getField('status');
        if($status != 2){
            $this->error('该商品状态异常，请刷新后操作');
        }
        if(M('products')->where(array('id'=>$id))->setField('status',5) !== false){
            //解禁账号
            $info = M('products')->where(array('id'=>$id))->getField('account');
            M('player_closed')->where(array('uid'=>$info,'type'=>1))->delete();
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 临时密码
     */
    public function pwdToken(){
        $id = I('id');
        $info = M('products')->where(array('id'=>$id))->find();
        if($info['token_time'] == 0 || $info['token_time'] < time()){
            list($pwd,$token) = $this->createPwd();
            M('products')->where(array('id'=>$id))->setField(array('token'=>$token,'token_time'=>time()+900));
        }else{
            $str = base64_decode($info['token']);
            $pwd = substr($str,0,strpos($str,C('TOKEN_KEY')));
        }
        $this->success($pwd);
    }

    /**
     * 订单管理
     */
    public function orderList(){
        $where = '';
        $orderID = I('orderID');
        $other_order = I('other_order');
        $appid = I('appid');
        $type = I('type');
        $status = I('status');
        $account = I('account');
        $start = I('start');
        $end = I("end");


        if($orderID) $where['a.orderID'] = $orderID;
        if($other_order) $where['a.other_order'] = $other_order;
        if($appid) $where['g.id'] = $appid;
        if($type) $where['a.type'] = $type;
        if($account) $where['p.account'] = $account;
        if($status == 6){
            $where['a.status'] = 1;
            $where['a.is_trade'] = 1;
        }elseif($status == 1){
            $where['a.status'] = 1;
            $where['a.is_trade'] = 0;
            $where['a.is_transfer'] = 0;
        }elseif($status){
            $where['a.status'] = $status;
        }
        $start = $start ? strtotime($start) : strtotime(date('Y-m-d'));
        $end = $end ? strtotime($end.' 23:59:59') : strtotime(date('Y-m-d'.' 23:59:59'));
        $where['a.create_time'] = array('between',array($start,$end));

        //统计金额
        $res = M('account_trade')->field('sum(money) money,sum(price) price,sum(coupon) coupon')->where(array('status'=>4,'create_time'=>array('between',array($start,$end))))->find();

        $count = M('account_trade a')
                ->join('left join __PLAYER__ b1 on a.buy_id=b1.id')
                ->join('left join __PLAYER__ b2 on a.sell_id=b2.id')
                ->join('left join __GAME__ g on g.id=a.appid')
                ->join('left join __PRODUCTS__ p on a.proid=p.id')
                ->where($where)
                ->count();

        $page = $this->page($count,20);

        $data = M('account_trade a')
                ->field('a.*,b1.username buy,b2.username sell,g.game_name,p.account')
                ->join('left join __PLAYER__ b1 on a.buy_id=b1.id')
                ->join('left join __PLAYER__ b2 on a.sell_id=b2.id')
                ->join('left join __GAME__ g on g.id=a.appid')
                ->join('left join __PRODUCTS__ p on a.proid=p.id')
                ->where($where)
                ->order('id desc')
                ->limit($page->firstRow,$page->listRows)->select();
        $this->account = $account;
        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->orderID = $orderID;
        $this->other_order = $other_order;
        $this->appid = $appid;
        $this->type = $type;
        $this->status = $status;
        $this->res = $res;
        $this->start = date('Y-m-d',$start);
        $this->end = date('Y-m-d',$end);
        $this->display();
    }

    /**
     * 交易成功 转账卖家，发送通知信息、修改密码、手机号解绑、换绑账号关联、账号解禁
     */
    public function tradeSuccess(){

        $id = I('id');
        $res1 = $this->resetInfo($id,1);
        if(!$res1){
            $this->error('重置账号失败');
        }

        $res2 = $this->transfer($id,1);
        if(!$res2['status']){
            $this->error($res2['info']);
        }

        $this->success();
    }

    /**
     * 打款卖家
     */
    public function transfer($id,$type){
        vendor('alipay.AopSdk');
        $info = M('account_trade a')
                ->field('a.orderID,a.version,a.price,a.proid,b.alipay_account,b.real_name,b.username,b.mobile,g.game_name,p.account,from_unixtime(p.publish_time,"%Y年%m月%d日") time')
                ->join('left join __PLAYER__ b on b.id=a.sell_id')
                ->join('left join __PRODUCTS__ p on p.id=a.proid')
                ->join('left join __GAME__ g on g.id = a.appid')
                ->where(array('a.id'=>$id))
                ->find();

        if(!$info) $this->error('信息错误');

        //如果已经打款直接返回
        $status = M('account_trade')->where(array('id'=>$id))->getField('is_transfer');
        if($status == 1){
            M('account_trade')->where(array('id'=>$id))->setField('status',4);
            M('products')->where(array('id'=>$info['proid']))->setField(array('status'=>4,'lock_time'=>0));
            if($type == 2){
                $this->success();
            }else{
                return array('status' => true);
            }
        }

        $aop = new \AopClient();
        $aop->appId = C('alipay.APPID');
        $aop->rsaPrivateKey = C('alipay.RSA_PRIVATE_KEY');
        $aop->alipayrsaPublicKey = C('alipay.RSA_PUBLIC_KEY');
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $request = new \AlipayFundTransToaccountTransferRequest();
        $bizContent = array(
            'out_biz_no' => orderID('account_trade'),
            'payee_type' => 'ALIPAY_LOGONID',
            'payee_account' => $info['alipay_account'],
            'amount' => $info['price'],
            'payer_show_name' => C('BOX_NAME').'账号交易',
            'remark' => '账号交易打款'
        );
        //如果有真实姓名，支付宝验证姓名
        if(!empty($info['real_name'])){
            $bizContent['payee_real_name'] = $info['real_name'];
        }
        //取锁对比
        $version = M('account_trade')->where(array('id'=>$id))->getField('version');
        if(1 != $version){
            M('account_trade')->where(array('id'=>$id))->setField(array('status'=>4,'version' => array('exp','version+1')));
            M('products')->where(array('id'=>$info['proid']))->setField(array('status'=>4,'lock_time'=>0));
            if($type == 2){
                $this->success();
            }else{
                return array('status' => true);
            }
        }
        $request->setBizContent(json_encode($bizContent));
        try{
            $result = $aop->execute ($request);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;

            if(!empty($resultCode) && $resultCode == 10000){
                $status = M('account_trade')->where(array('id'=>$id))->getField('is_trade');
                $set = array(
                    'is_transfer' => 1,
                    'transfer' => $result->$responseNode->order_id,
                    'alipay_account' => $info['alipay_account'],
                    'version' => array('exp','version+1')
                );
                if($status == 1) $set['status'] = 4;

                M('account_trade')->where(array('id'=>$id))->setField($set);
                M('products')->where(array('id'=>$info['proid']))->setField(array('status'=>4,'lock_time'=>0));
                $mobile = $info['mobile'];
                $content = "您于{$info['time']}提交【{$info['game_name']}】的游戏账号，已交易成功。扣去服务费后的交易费用将会自动转入您的支付宝，如有疑问，请联系QQ：".C('SMS_QQ');

                if(!sendSMS($mobile,$content)){
                    $this->error('发送短信失败');
                }

                diylogs('transfer',$info);
                if($type == 2){
                    $this->success();
                }else{
                    return array('status' => true);
                }
            } else {
                $set['is_transfer'] = 2;
                $set['alipay_account'] = $info['alipay_account'];
                M('account_trade')->where(array('id'=>$id))->setField($set);
                if($type == 2){
                    $this->error($result->$responseNode->sub_msg);
                }else{
                    return array('status' => false,'info'=>$result->$responseNode->sub_msg);
                }
            }
        }catch (\Exception $e){
            M('account_trade')->where(array('id'=>$id))->setField(array('is_transfer'=>2));
            if($type == 2){
                $this->error($e->getMessage());
            }else{
                return array('status' => false,'info'=>$e->getMessage());
            }
        }
    }

    /**
     * 重置账号 发送通知信息、修改密码、手机号解绑、换绑账号关联、账号解禁
     */
    public function resetInfo($id,$type){
        $info = M('account_trade a')
            ->field('a.buy_id,a.sell_id,p.mobile,p.id sdkid,p.username,p.salt,pro.appid,pro.account,pro.uid,g.game_name')
            ->join('left join __PLAYER__ p on p.id=a.buy_id')
            ->join('left join __PRODUCTS__ pro on pro.id=a.proid')
            ->join('left join __GAME__ g on g.id = a.appid')
            ->where(array('a.id'=>$id))
            ->find();

        $buy_info = M('player')->where(array('id'=>$info['buy_id']))->find();
        $data = array(
            'uid' => $buy_info['id'],
            'channel' => $buy_info['channel'],
            'system' => $buy_info['system'],
            'machine_code' => $buy_info['machine_code'],
            'ip' => $buy_info['regip'],
        );
        //将小号信息更改为买家相关信息
        $res1 = M('app_player')->where(array('id'=>$info['account']))->save($data);
        $res2 = M('player_closed')->where(array('uid'=>$info['account'],'type'=>1))->delete();

        if($res1 !== false && $res2 !== false ){
            //删除下架商品
            M('products')->where(array('appid'=>$info['appid'],'account'=>$info['account'],'uid'=>$info['uid'],'status'=>5))->delete();
            $mobile = info['mobile'];
            $content = "恭喜您！您在".C('BOX_NAME')."盒子内购买【{$info['game_name']}】游戏账号成功，该游戏账号已转入你的账号下，如有疑问，请联系QQ：".C('SMS_QQ')."【".C('BOX_NAME')."】";

            if(!sendSms($mobile,$content)){
                $this->error('短信发送失败');
            };

            $set['is_trade'] = 1;
            M('account_trade')->where(array('id'=>$id))->setField($set);
            if($type == 2){
                $this->success();
            }else{
                return true;
            }
        }else{

            if($type == 2){
                $this->error('重置账号失败');
            }else{
                return false;
            }
        }
    }

    /**
     * 退款
     */
    public function refund(){
        $id = I('id');
        $reason = I('reason');
        $info = M('account_trade')->where(array('id'=>$id))->find();
        if($info['status'] != 1 && $info['is_transfer'] != 0 && $info['is_trade'] != 0){
            $this->error('状态错误，不能进行退款');
        }
        if($info['type'] == 1){
            $this->alipayRefund($info,$reason);
        }else{
            $this->wechatRefund($info,$reason);
        }
    }

    /**
     * 支付宝退款
     */
    protected function alipayRefund($info,$reason){
        vendor('alipay.AopSdk');

        $aop = new \AopClient();
        $aop->appId = C('alipay.APPID');
        $aop->rsaPrivateKey = C('alipay.RSA_PRIVATE_KEY');
        $aop->alipayrsaPublicKey = C('alipay.RSA_PUBLIC_KEY');
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $request = new \AlipayTradeRefundRequest ();
        $bizContent = array(
            'out_trade_no' => $info['orderID'],
            'trade_no' => $info['other_order'],
            'refund_amount' => $info['money'],
            'refund_currency' => 'CNY',
            'refund_reason' => '账号交易退款,原因：'.$reason,
            'operator_id' => session('ADMIN_ID'),
            'store_id' => 185
        );
        $request->setBizContent(json_encode($bizContent));
        try{
            $result = $aop->execute ($request);

            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode) && $resultCode == 10000){
                M('account_trade')->where(array('id'=>$info['id']))->setField(array('status'=>3,'reason'=>$reason));
                M('products')->where(array('id'=>$info['proid']))->setField('status',5);
                $this->success();
            } else {
                $this->error($result->$responseNode->sub_msg);
            }
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
    }

    /**
     * 微信退款
     */
    protected function wechatRefund($info,$reason){
        vendor('wechatPay');
        $wechat = new \wechatPay();
        $wechat->appid = C('wechat.APPID');
        $wechat->key = C('wechat.KEY');
        $wechat->mch_id = C('wechat.MCH_ID');
        $res = $wechat->refund($info,$reason);

        if($res['return_code'] == 'SUCCESS' && $res['result_code'] == 'SUCCESS'){
            M('account_trade')->where(array('id'=>$info['id']))->setField(array('status'=>3,'reason'=>$reason));
            M('products')->where(array('id'=>$info['proid']))->setField('status',5);
            $this->success('操作成功');
        }else{
            $this->error($res['err_code_des']);
        }
    }


    /**
     * 订单详情
     */
    public function info(){
        $id = I('id');
        $data = M('account_trade a')
                ->field('a.*,b1.username buy,b2.username sell,g.game_name')
                ->join('left join __PLAYER__ b1 on a.buy_id=b1.id')
                ->join('left join __PLAYER__ b2 on a.sell_id=b2.id')
                ->join('left join __GAME__ g on g.id=a.appid')
                ->where(array('a.id'=>$id))
                ->find();
        $data['type'] = $data['type'] == 1 ? '支付宝' : '微信';
        $data['is_transfer'] = $data['is_transfer'] == 1 ? '是' : '否';
        $data['is_trade'] = $data['is_trade'] == 1 ? '是' : '否';
        $data['fee'] = $data['third_fee'] + $data['local_fee'];

        $html = <<<HTML
        <div style="padding:15px">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <th style="width:22%">订单号</th>
                        <th style="width:50%">{$data['orderID']}</th>
                    </tr>
                    <tr>
                        <th>第三方订单号</th>
                        <th>{$data['other_order']}</th>
                    </tr>
                    <tr>
                        <th>第三方转账号</th>
                        <th>{$data['transfer']}</th>
                    </tr>
                    <tr>
                        <th>买家账号</th>
                        <th>{$data['buy']}</th>
                    </tr>
                    <tr>
                        <th>卖家账号</th>
                        <th>{$data['sell']}</th>
                    </tr>
                    <tr>
                        <th>卖家支付宝账号</th>
                        <th>{$data['alipay_account']}</th>
                    </tr>
                    <tr>
                        <th>游戏</th>
                        <th>{$data['game_name']}</th>
                    </tr>
                    <tr>
                        <th>支付方式</th>
                        <th>{$data['type']}</th>
                    </tr>
                    <tr>
                        <th>订单金额</th>
                        <th>
                            {$data['money']}
                        </th>
                    </tr>
                    <tr>
                        <th>手续费率</th>
                        <th>{$data['fee']}%</th>
                    </tr>
                    <tr>
                        <th>卖家实收</th>
                        <th>{$data['price']}</th>
                    </tr>
                    <tr>
                        <th>是否转账卖家</th>
                        <th>{$data['is_transfer']}</th>
                    </tr>
                    <tr>
                        <th>是否重置账号</th>
                        <th>{$data['is_trade']}</th>
                    </tr>
                    <tr>
                        <th>退款原因</th>
                        <th>{$data['reason']}</th>
                    </tr>
                </tbody>
            </table>
        </div>
HTML;
        $this->success($html);
    }

    /**
     * 生成密码
     */
    protected function createPwd(){
        $arr = array('1','a','2','b','3','c','4','d','5','e','6','f','7','g','8','h','9','j','k','m','n','z','x','v','t','y','u','q','w','r','p');
        shuffle($arr);
        $pwd = implode('',array_slice($arr,0,6));
        $token = base64_encode($pwd.C('TOKEN_KEY'));
        return array($pwd,$token);
    }

    //重置账号问题，苹果游戏关联到安卓下
}