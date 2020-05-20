<?php
/**
 * 动态接口
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/1/8
 * Time: 15:09
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class DynamicsController extends AppframeController{

    /**
     * 发表动态
     */
    public function publishDynamics(){
        $data['uid'] = I('uid');
        $data['content'] = I('content');

        $data['sign'] = I('sign');

        $res = checkSign($data,C('API_KEY'));
        if(!$res) {
            $this->ajaxReturn(null,'签名错误',0);
        }
        if(!M('player')->where(array('id'=>$data['uid']))->find()){
            $this->ajaxReturn(null,'非法用户',0);
        }

        if(empty($data['content'])){
            $this->ajaxReturn(null,'内容不能为空',0);
        }

        if(count($_FILES['imgs']['name']) > 4){
            $this->ajaxReturn(null,'动态图片最多发布4张',0);
        }
        $start = strtotime(date('Y-m-d'));
        $end = strtotime(date('Y-m-d'). ' 23:59:59');
        $count = M('dynamics')->where(array('uid'=>$data['uid'],'create_time'=>array('between',array($start,$end))))->count();
        if($count >= 20){
            $this->ajaxReturn(null,'动态每日最多发表20次',0);
        }
        //logs('dynamic',$_POST);
        
   
        if($_FILES['imgs']){
            $upload = new \Think\Upload(array(
                'rootPath' => './'.C("UPLOADPATH"),
                'subName' => array('date', 'Ymd'),
                'maxSize' => 10485760,
                'exts' => array('jpg', 'png', 'jpeg','gif'),
            ));
            $info = $upload->upload();
            if(!$info){
                $this->ajaxReturn(null,$upload->getError(),0);
            }else{
                foreach($info as $v){
                    $file_name = trim($v['fullpath'],'.');
                    $src[] = str_replace('/www.sy217.com','',$file_name);
                }
                $data['imgs'] = json_encode($src);
            }
        }
        unset($data['sign']);
	    $data['create_time'] = time();
        $data['publish_time'] = time();
        if($id = M('dynamics')->add($data)){

            //发布成功 将信息推送给所有客服进行处理
            $link = U('Admin/Dynamic/index');
            create_admin_message(1,$id,'all',$link);

            $this->ajaxReturn(null,'发布成功');
        }else{
            $this->ajaxReturn(null,'发布失败',0);
        }
    }


    /**
     * 获取动态
     */
    public function getDynamics(){
        $data['type'] = I('type',1);//1热门，2全部，3穿越,4我关注的人,5我的
        $data['page'] = I('page',1);
        $data['sign'] = I('sign');
        $limit = 10;
        $info = array();
	    $uid = I('uid');
        $res = checkSign($data,C('API_KEY'));
        if(!$res) {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $where['d.status'] = 0;
        $where['d.audit'] = 1;
        $where['d.publish_time'] = array('elt',time());
        $where['d.end_time'] = array('egt',time());
        switch ($data['type']){
            case 1:
                $where['d.publish_time'] = array('between',array(strtotime('-3 day'),time()));
                $order = 'd.likes desc,d.comment desc';
                break;
            case 2:
                $order = 'd.top desc,d.publish_time desc';
                break;
            case 3:
                $arr = M('dynamics')->where(array('audit'=>1,'status'=>0))->group('from_unixtime(publish_time,\'%Y-%m-%d\')')->getField('publish_time',true);
                $time = $arr[array_rand($arr,1)];
                $start_time = strtotime(date('Y-m-d',$time));
                $end_time = strtotime(date('Y-m-d',$time).' 23:59:59');
                $where['d.publish_time'] = array('between',array($start_time,$end_time));
                $order = 'd.publish_time desc';
                $limit = 100;
                break;
            case 4:
                if(!$uid) $this->ajaxReturn(null,'缺少参数',0);
                $buids = M('follow')->where(array('uid'=>$uid))->group('uid')->getField('group_concat(buid)');
                if(!$buids) $this->ajaxReturn(null,'您还没有关注',0);
                $where['d.uid'] = array('in',$buids);
                $order = 'd.publish_time desc';
                break;
            case 5:
                $buid = I('buid');
                if(!$uid || !$buid) $this->ajaxReturn(null,'缺少参数',0);

                if($buid == $uid){
                    unset($where['d.audit']);
                }
                $where['d.uid'] = $buid;
                $order = 'd.create_time desc';
                break;
        }
        $start = ($data['page'] - 1) * $limit;
        $count = M('dynamics d')
            ->field('d.*,p2.nick_name,p2.sex,p2.icon_url,p1.vip')
            ->join('left join __PLAYER__ p1 on p1.id=d.uid')
            ->join('left join __PLAYER_INFO__ p2 on p2.uid=d.uid')
            ->where($where)
            ->count();
        $res = M('dynamics d')
                ->field('d.*,p2.nick_name,p2.sex,p2.icon_url,p1.vip')
                ->join('left join __PLAYER__ p1 on p1.id=d.uid')
                ->join('left join __PLAYER_INFO__ p2 on p2.uid=d.uid')
                ->where($where)
                ->order($order)
                ->limit($start,$limit)
                ->select();
        if($res){
            if($data['type'] == 1){
                $res = array_sort_td($res,'likes',SORT_DESC);
            }
            foreach($res as $k=>$v){
                switch ($v['level']){
                    case 'S':$level = 1;break;
                    case 'A':$level = 2;break;
                    case 'B':$level = 3;break;
                    case 'C':$level = 4;break;
                    default:$level = 0;
                }
                $type = M('dynamics_like_info')->where(array('dynamics_id'=>$v['id'],'uid'=>$uid))->getField('type');
                if($type == '0'){
                    $operate = 0;
                }elseif($type == '1'){
                    $operate = 1;
                }else{
                    $operate = 2;
                }
                /*统计狂人排名*/
                //开车狂
                $drive = M('dynamics')->where(array('uid'=>$v['uid'],'audit'=>1,'status'=>0))->count();

                //点评狂
                $comment = M('comment')->where(array('uid'=>$v['uid'],'status'=>1,'comment_type'=>2,'order'=>1))->count();

                //助人狂
                $help = M('consult_info')->where(array('uid'=>$v['uid'],'audit'=>1))->count();

                //签到狂
                $signin = M('sign_log')->where(array('uid'=>$v['uid']))->count();

                $info[$k]['user'] = array(
                    'nick_name' => $v['nick_name'] ? $v['nick_name'] : M('player')->where(array('id'=>$v['uid']))->getField('username'),
                    'sex' => $v['sex'],
                    'icon_url' => get_avatar_url($v['icon_url']),
                    'vip' => $v['vip'],
                    'operate' => $operate,
                    'driveLevel' => crazy_level($drive,4),
                    'commentLevel' => crazy_level($comment,3),
                    'helpLevel' => crazy_level($help,2),
                    'signLevel' => crazy_level($signin,1)
                );
                if($data['type'] != 5){
                    $info[$k]['user']['follow'] = 0;
                    if($uid){
                        if(M('follow')->where(array('uid'=>$uid,'buid'=>$v['uid']))->find()){
                            $info[$k]['user']['follow'] = 1;
                        }
                    }
                }

                $imgs = json_decode($v['imgs'],1);
                $arr = '';
                if($imgs){
                    foreach($imgs as $v1){
                        $arr[] = get_avatar_url($v1);
                    }
                }
                $info[$k]['dynamics'] = array(
                    'id' => $v['id'],
                    'uid' => $v['uid'],
                    'content' => $v['content'],
                    'imgs' => $arr,
                    'likes' => $v['likes'],
                    'dislike' => $v['dislike'],
                    'share' => $v['share'],
                    'comment' => $v['comment'],
                    'comment_info' => M('comment c')
                                      ->field('p2.nick_name,p1.username,c.content,c.create_time')
                                      ->join('left join __PLAYER__ p1 on p1.id=c.uid')
                                      ->join('left join __PLAYER_INFO__ p2 on p2.uid=c.uid')
                                      ->where(array('c.dynamics_id'=>$v['id'],'c.comment_type'=>1,'c.to_uid'=>0,'c.status'=>1))
                                      ->order('c.create_time desc')
                                      ->limit(2)->select(),
                    'remark' => $v['remark'],
                    'create_time' => $v['create_time'],
                    'publish_time' => $v['publish_time'],
                    'dynamics_count' => $count ? $count : 0,
                    'level' => $level,
                    'status' => $v['audit']
                );
                foreach($info[$k]['dynamics']['comment_info'] as $k1=>$v1){
                    $username = $v1['nick_name'] ? $v1['nick_name'] : hideStar($v1['username']);
                    $info[$k]['dynamics']['comment_info'][$k1]['username'] = $username;
                    unset($info[$k]['dynamics']['comment_info'][$k1]['nick_name']);
                }
            }
            $this->ajaxReturn($info,'success');
        }else{
            $this->ajaxReturn(null,'没有数据了',0);
        }

    }


    /**
     * 关注/取关
     */
    public function followOrCancel(){
        $data['uid'] = I('uid');
        $data['buid'] = I('buid');
        $data['type'] = I('type',1);
        $data['sign'] = I('sign');

        $res = checkSign($data,C('API_KEY'));
        if(!$res) {
            $this->ajaxReturn(null,'签名错误',0);
        }
        if(!$data['uid'] || !$data['buid']){
            $this->ajaxReturn(null,'缺少参数',0);
        }
        if($data['uid'] == $data['buid']){
            $this->ajaxReturn(null,'不能对自己操作',0);
        }
	    $data['create_time'] = time();
        if($data['type'] == 1){
            if(M('follow')->where(array('uid'=>$data['uid'],'buid'=>$data['buid']))->find()){
                $this->ajaxReturn(null,'请不要重复关注',0);
            }else{
                unset($data['type'],$data['sign']);
                if(!M('follow')->add($data)){
                    $this->ajaxReturn(null,'关注失败',0);
                }
                M('player_info')->where(array('uid'=>$data['uid']))->setInc('follow_counts');
                M('player_info')->where(array('uid'=>$data['buid']))->setInc('fans_counts');
            }
        }else{
            if(M('follow')->where(array('uid'=>$data['uid'],'buid'=>$data['buid']))->delete() === false){
                $this->ajaxReturn(null,'取关失败',0);
            }
            M('player_info')->where(array('uid'=>$data['uid']))->setDec('follow_counts');
            M('player_info')->where(array('uid'=>$data['buid']))->setDec('fans_counts');
        }
        $this->ajaxReturn(null,'success');
    }

    /**
     * 分享
     */
    public function shareDynamics(){
        $data['id'] = I('id');
        $data['sign'] = I('sign');

        $res = checkSign($data,C('API_KEY'));
        if(!$res) {
            $this->ajaxReturn(null,'签名错误',0);
        }
        if(M('dynamics')->where(array('id'=>$data['id']))->setInc('share')){
            $url = C('API_URL').'/api/dynamics/webdisplay?id='.$data['id'];
            $this->ajaxReturn($url,'转发成功');
        }else{
            $this->ajaxReturn(null,'转发失败',0);
        }
    }

    /**
     * 动态web页
     */
    public function webDisplay(){
        $this->id = I('id');
        $this->display();
    }

    /**
     * 删除我的动态
     */
    public function delDynamic(){
        $data = array(
            'uid' => I('uid'),
            'id' => I('id'),
            'sign' => I('sign')
        );
        $res = checkSign($data,C('API_KEY'));
        if(!$res) {
            $this->ajaxReturn(null,'签名错误',0);
        }
        if(!M('player')->where(array('id'=>$data['uid']))->find()){
            $this->ajaxReturn(null,'非法用户',0);
        }
        if(M('dynamics')->where(array('uid'=>$data['uid'],'id'=>$data['id']))->setField('status',1) !== false){
            $this->ajaxReturn(null,'操作成功');
        }else{
            $this->ajaxReturn(null,'操作失败',0);
        }

    }
}