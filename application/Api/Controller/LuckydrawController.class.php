<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/11/9
 * Time: 14:36
 */
namespace Api\Controller;

use Common\Controller\AppframeController;

class LuckydrawController extends AppframeController{

    public function show(){
        $uid = I('uid');
        $data = M('prize')->getField('place,id,url',true);
        $site = get_site_options();
        $site['deplete_coin'] = $site['deplete_coin'] ? $site['deplete_coin'] : 100;
        $info = M('luckydraw_list l')
                ->field('p.username,r.name')
                ->join('left join __PLAYER__ p on p.id=l.uid')
                ->join('left join __PRIZE__ r on r.id=l.prizeid')
                ->order('l.id desc')
                ->limit(10)
                ->select();
        $this->info = $info;
        $this->needcoin = $site['deplete_coin'];
        $this->uid = $uid;
        $this->data = $data;
	    $this->main = C('LUCKY_DRAW_IMG');
        $this->display();
    }

    public function testLD(){
        $data = M('prize')->getField("place,id,concat('".C('LUCKY_DRAW_IMG')."',url) url",true);
        $site = get_site_options();
        $site['deplete_coin'] = $site['deplete_coin'] ? $site['deplete_coin'] : 100;
        $info = M('luckydraw_list l')
            ->field('p.username,r.name')
            ->join('left join __PLAYER__ p on p.id=l.uid')
            ->join('left join __PRIZE__ r on r.id=l.prizeid')
            ->order('l.id desc')
            ->limit(10)
            ->select();
        $res['luckydraw_info'] = $info;
        $res['needcoin'] = $site['deplete_coin'];
        $res['luckydraw_data'] = $data;
        $this->ajaxReturn($res,'success');
//        $this->info = $info;
//        $this->needcoin = $site['deplete_coin'];
//        $this->uid = $uid;
//        $this->data = $data;
//        $this->display();
    }

    /**
     * 我的奖品
     */
    public function myPrize(){
        $data['uid'] = I('uid');
        $data['sign'] = I('sign');
        $key = C('API_KEY');
        if(!checkSign($data,$key)) $this->ajaxReturn('','sign error',0);
        if(!$data['uid']) $this->ajaxReturn('','uid is null',0);
        $data = M('luckydraw_list l')
                ->field('l.create_time,p.name')
                ->join('left join __PRIZE__ p on p.id=l.prizeid')
                ->where(array('l.uid'=>$data['uid']))
                ->order('create_time desc')
                ->select();
        $this->ajaxReturn($data,'success');
    }

    /**
     * 抽奖程序
     */
    public function luckydraw(){
        $uid = I("uid");
        $site = get_site_options();
        $site['deplete_coin'] = $site['deplete_coin'] ? $site['deplete_coin'] : 100;
        $site['place'] = isset($site['place']) ? $site['place'] : 0;

        //任务记录
        M('task')->add(array('uid'=>$uid,'type'=>5,'create_time'=>time()));
        //本周开始时间
        $start = strtotime(date('Y-m-d',strtotime('this week')).'00:00:00');
        //今日结束时间
        $end = strtotime(date('Y-m-d',time()).' 23:59:59');

        if(!$uid){
            $this->ajaxReturn('','缺少关键参数',0);
        }

        //所有奖品
        $prize = M('prize')->order('place')->getField('place,id,name,version,number,output,worth,type,value');
        if(count($prize) < 12){
            $this->ajaxReturn('','系统维护中',0);
        }
        //用户持有金币
        $usercoin = M('player')->where(array('id'=>$uid))->getField('coin');
        if($usercoin < $site['deplete_coin']){
            $this->ajaxReturn('','抽奖金币不足',0);
        }


        //用户在时间内充值总额
        $userpay = M('inpour')->where(array('uid'=>$uid,'create_time'=>array('between',array($start,$end)),'payType'=>array('neq',10),'status'=>1))->getField('sum(money) money');
        $userpay = $userpay ? $userpay : 0;

        //获取抽奖配置,如果指定中奖位置直接返回结果
        if($site['place'] > 0){
            //网页位置是从0开始
            $place = $site['place'] - 1;
            $type = $prize[$site['place']]['type'] != 4 ? 2 : 1;
            $prize[$site['place']]['type'] == 3 ? $append = '，绑定手机后会有客服人员主动联系您' : '';
            $msg = $prize[$site['place']]['worth'] == 0 ? '很遗憾，谢谢参与' : '恭喜您抽中'.$prize[$site['place']]['name'].$append;

            $this->setUserInfo($uid,$site['deplete_coin'],$prize[$site['place']],$type);
            $this->ajaxReturn(array('place'=>$place),$msg);
        }else{
            $luckysite = M('luckydraw_setting')->order('money desc')->select();
            $data = $this->luckyReckon($uid,$userpay,$luckysite,$prize,$site['deplete_coin']);

            $data['type'] == 3 ? $append = '，绑定手机后会有客服人员主动联系您' : '';

            $msg = $data['worth'] == 0 ? '很遗憾，谢谢参与' : '恭喜您抽中'.$data['name'].$append;
            $this->ajaxReturn(array('place'=>$data['place'] - 1),$msg);
        }
    }

    /**
     * 计算奖品
     * @param $uid-用户id
     * @param $userpay-用户规定时间充值总数
     * @param $luckysite-抽奖配置
     * @param $prize-奖品
     * @param $needcoin-消耗金币
     * @return mixed
     */
    protected function luckyReckon($uid,$userpay,$luckysite,$prize,$needcoin){
        //取得符合充值条件的配置
        foreach($luckysite as $k=>$v){
            if($userpay >= $v['money']){
                $v['setting'] = json_decode($v['setting'],true);
                $newsite[] = $v;
            }
        }
        foreach($newsite as $k=>$v){
            //本阶段中奖位置
            $place = $this->get_rand($v['setting']);
            if($prize[$place]['worth'] == 0){

                $this->setUserInfo($uid,$needcoin,$prize[$place]);
                return $prize[$place];
            }
            //当前抽中的奖品每日产出数
            $count = M('luckydraw_list')->where(array('prizeid'=>$prize[$place]['id'],'_string'=>'DATEDIFF(FROM_UNIXTIME(create_time),NOW())=0'))->count();
            //奖品的库存没有或是无限的
            if($prize[$place]['number'] <= 0){
                //库存无限的
                if($prize[$place]['number'] < 0){
                    //当天当前奖品中奖数没有超过每日产出或每日产出是无限的
                    if($count < $prize[$place]['output'] || $prize[$place]['output'] < 0){
                        $this->setUserInfo($uid,$needcoin,$prize[$place],2);
                        return $prize[$place];
                    //中奖数大于每日产出并且没有到最底抽奖阶段
                    }elseif($count >= $prize[$place]['output'] && count($newsite) != ($k+1)){
                        continue;
                    }else{
                        foreach($prize as $k1=>$v1){
                            if($v1['worth'] == 0){
                                $this->setUserInfo($uid,$needcoin,$prize[$place]);
                                return $v1;
                            }
                        }
                    }
                }else{
                    if(count($newsite) != ($k+1)){
                        continue;
                    }else{
                        //没有库存并且是最低等配置则直接返回权重为0的奖品
                        foreach($prize as $k1=>$v1){
                            if($v1['worth'] == 0){
                                $this->setUserInfo($uid,$needcoin,$prize[$place]);
                                return $v1;
                            }
                        }
                    }
                }
            }else{
                //达到今日产出并且不是最低等的配置则继续下一等级配置抽奖
                if($count >= $prize[$place]['output'] && count($newsite) != ($k+1)){
                    continue;
                //抽中的奖品未达到产出上限则或每日产出是无限的
                }elseif($count < $prize[$place]['output'] || $prize[$place]['output'] < 0){
                    $where['id'] = $prize[$place]['id'];
                    $where['version'] = $prize[$place]['version'];
                    $set['version'] = array('exp','version+1');
                    $set['number'] = array('exp','number-1');
                    //并发写入失败则重新抽奖
                    if(M('prize')->where($where)->setField($set)){
                        $this->setUserInfo($uid,$needcoin,$prize[$place],2);
                        return $prize[$place];
                    }else{
                        $this->luckyReckon($uid,$userpay,$luckysite,$prize,$needcoin);
                    }
                }else{

                    //达到产出上限并且是最低等配置则直接返回权重为0的奖品
                    foreach($prize as $k1=>$v1){
                        if($v1['worth'] == 0){
                            $this->setUserInfo($uid,$needcoin,$prize[$place]);
                            return $v1;
                        }
                    }
                }
            }
        }
    }

    /**
     * 更新用户抽奖信息
     * @param $uid
     * @param $needcoin
     * @param $prizeid
     */
    protected function setUserInfo($uid,$needcoin,$prize,$type = 1){
        if($type != 1){
            $add = array(
                'uid' => $uid,
                'prizeid' => $prize['id'],
                'type' => $prize['type'],
                'status' => $prize['type'] == 3 ? 2 : 1,
                'create_time' => time()
            );
            M('luckydraw_list')->add($add);
        }
        $coin = M('player')->where(array('id'=>$uid))->getField('coin');

        switch ($prize['type']){
            case 1:
                $set['platform_money'] = array('exp','platform_money+'.$prize['value']);
                $set['coin'] = array('exp','coin-'.$needcoin);
                $platform = M('player')->where(array('id'=>$uid))->getField('platform_money');
                M('platform_detail_logs')->add(array('uid'=>$uid,'type'=>3,'platform_change'=>$prize['value'],'platform_counts'=>$platform+$prize['value'],'create_time'=>time()));
                $coinlog[] = array(
                    'uid' => $uid,
                    'type' => 5,
                    'coin_change' => -$needcoin,
                    'coin_counts' => $coin-$needcoin,
                    'create_time' => time()
                );
                break;
            case 2:
                $set['coin'] = array('exp','coin+'.($prize['value']-$needcoin));
                $coinlog[] = array('uid'=>$uid,'type'=>5,'coin_change'=>-$needcoin,'coin_counts'=>$coin-$needcoin,'create_time'=>time());
                $coinlog[] = array('uid'=>$uid,'type'=>5,'coin_change'=>$prize['value'],'coin_counts'=>$coin+($prize['value']-$needcoin),'create_time'=>time());
                break;
            default:
                $set['coin'] = array('exp','coin-'.$needcoin);
                $coinlog[] = array('uid'=>$uid,'type'=>5,'coin_change'=>-$needcoin,'coin_counts'=>$coin-$needcoin,'create_time'=>time());
                break;
        }
        M('player')->where(array('id'=>$uid))->setField($set);
        M('coin_log')->addAll($coinlog);
    }

    /**
     * 抽奖概率算法
     * @param $proArr
     * @return int|string
     */
    protected function get_rand($proArr){
        $result = '';
        foreach ($proArr as $key => $val) {
            $arr[$key] = $val;
        }
        // 概率数组的总概率
        $proSum = array_sum($arr);
        // 概率数组循环
        foreach ($arr as $k => $v) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $v) {
                $result = $k;
                break;
            } else {
                $proSum -= $v;
            }
        }
        return $result;
    }
}