<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/9/13
 * Time: 15:43
 */

namespace Api\Controller;
use Common\Controller\AppframeController;

class ConsultController extends AppframeController{

    /**
     * 游戏问答列表
     */
    public function consultList(){
        $data = array(
            'appid' => I('appid'),
            'page' => I('page',1),
            'sign' => I('sign')
        );
        $uid = I('uid');
        $limit = 10;
        $start = ($data['page'] - 1) * $limit;

        if(!checkSign($data,C('API_KEY'))) {
            $this->ajaxReturn(null,'签名错误',0);
        }

        //显示个人提问在最上面
        if($data['page'] == 1 && $uid){
            $res['conf'] = C('REWARD');
            $my = M('consult')->where(array('uid'=>$uid,'appid'=>$data['appid']))->order('create_time desc')->select();
            if($my){
                $res['user'] = $my;
                foreach($my as $k=>$v){
                    $res['user'][$k]['answer'] = M('consult_info')->field('content')->where(array('audit'=>1,'consult_id'=>$v['id']))->order('create_time desc')->limit(2)->select();
                    $res['user'][$k]['answer_count'] = M('consult_info')->where(array('audit'=>1,'consult_id'=>$v['id']))->count();
                }

            }
        }
        //列表提问排除自己
        $where['appid'] = $data['appid'];
        $where['status'] = 2;
        if($uid) $where['uid'] = array('neq',$uid);
        $question = M('consult')->where($where)->order('money desc,create_time desc')->limit($start,$limit)->select();
        $res['list'] = array();
        if($question){
            $res['list'] = $question;
            foreach($question as $k=>$v){
                $res['list'][$k]['answer'] = M('consult_info')->field('content')->where(array('audit'=>1,'consult_id'=>$v['id']))->order('create_time desc')->limit(2)->select();
                $res['list'][$k]['answer_count'] = M('consult_info')->where(array('audit'=>1,'consult_id'=>$v['id']))->count();
            }


        }
        $this->ajaxReturn($res,'success');
    }

    /**
     * 提问
     */
    public function putQuestion(){
        $data = array(
            'uid' => I('uid'),
            'appid' => I('appid'),
            'content' => I('content'),
            'money' => I('money'),
            'sign' => I('sign')
        );

        if(!checkSign($data,C('API_KEY'))) {
            $this->ajaxReturn(null,'签名错误',0);
        }
        if(!trim($data['content'],' ')){
            $this->ajaxReturn(null,'内容不能为空',0);
        }
        if(!get_185_gameinfo($data['appid'],2)){
            $this->ajaxReturn(null,'非法参数',0);
        }
        $conf = C('REWARD');
        if($data['money'] > end($conf)){
            $this->ajaxReturn(null,'悬赏金额超过最大值',0);
        }

        $userCoin = M('player')->where(array('id'=>$data['uid']))->getField('coin');
        if($userCoin < $data['money']){
            $this->ajaxReturn(null,'金币不足，请重新设置悬赏金额',0);
        }


        $info = M('consult')->where(array(
                'uid'=>$data['uid'],
                'appid'=>$data['appid'],
                '_string'=>"to_days(FROM_UNIXTIME(create_time))=to_days(now())")
        )->count();

        if($info >= 2){
            $this->ajaxReturn(null,'每日单个游戏最多提问2个问题',0);
        }else{
            $data['create_time'] = time();
            unset($data['sign']);
            if($consult_id = M('consult')->add($data)){
                M('player')->where(array('id'=>$data['uid']))->setDec('coin',$data['money']);
                M('coin_log')->add(array('uid'=>$data['uid'],'type'=>11,'coin_change'=>-$data['money'],'coin_counts'=>$userCoin - $data['money'],'create_time'=>time()));

                $appid = get_sdk_appid(I('appid'));
                //提问成功后 发送信息队列
                $link = U('Admin/Consult/index');
                create_admin_message(5,$consult_id,'all',$link,$appid);

                $this->ajaxReturn(null,'success');
            }else{
                $this->ajaxReturn(null,'操作失败',0);
            }
        }
    }


    /**
     * 我的提问
     */
    public function myQuestions(){
        $data = array(
            'uid' => I('uid'),
            'page' => I('page',1),
            'sign' => I('sign')
        );

        if(!checkSign($data,C('API_KEY'))) {
            //$this->ajaxReturn(null,'签名错误',0);
        }

        $limit = 10;
        $start = ($data['page'] - 1) * $limit;

        //未读角标
        if($data['page'] == 1){
            $resRead = M('consult')->where(array('uid'=>$data['uid']))->select();
            $read = 0;
            if($resRead){
                foreach($resRead as $k=>$v){
                    $read = $read + M('consult_info')->where(array('consult_id'=>$v['id'],'is_read'=>0,'audit'=>1))->count();
                }
            }
            $success['read'] = $read;
        }

        //分页数据
        $info = array();
        $resPage = M('consult')->where(array('uid'=>$data['uid']))->limit($start,$limit)->order('create_time desc')->select();
        if($resPage){
            foreach($resPage as $k=>$v){
                $game = get_185_gameinfo($v['appid'],2);

                $info[$k] = array(
                    'id' => $v['id'],
                    'appid' => $v['appid'],
                    'logo' => C('CDN_URL').$game['logo'],
                    'name' => $game['gamename'],
                    'question' => $v['content'],
                    'money' => $v['money'],
                    'is_reward'=>($v['type'] == 2)?1:0,
                    'anwsers' => M('consult_info')->where(array('consult_id'=>$v['id'],'audit'=>1))->count(),
                    'time' => $v['create_time']
                );
            }
        }

        $success['list'] = $info;

        $this->ajaxReturn($success,'success');
    }
}