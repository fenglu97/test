<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2017/11/27
 * Time: 15:28
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class GameController extends AppframeController{


    /**
     *
     */
    public function getStartImgs(){
        $data['system'] = I('system',1);
        $data['channel'] = I('channel');
        $data['sign'] = I('sign');
        $key = C('API_KEY');
        if(!checkSign($data,$key)) $this->ajaxReturn('','sign error',0);
        if(!$data['system'] || !$data['channel']) $this->ajaxReturn('','Missing param(system or channel)',0);

        $res = M('appstart')->where(array('channel'=>$data['channel']))->find();

        $path = '';
        if($res){
            $header = 'http://'.str_replace('sy217','sy218',C('UPLOADPATH'));
            if($data['system'] == 1){
                $path = $res['android_img'] ? $header.$res['android_img'] : '';
            }else{
                $path = $res['ios_img'] ? $header.$res['ios_img'] : '';
            }
        }
        $this->ajaxReturn($path,'success');
    }

    public function upload(){
        set_time_limit(600);
        $start = time();
        $tag = I('tag');
        $upload = new \Think\Upload();
        /**/
//        $upload->driver = 'Qiniu';//
//        $upload->driverConfig = array(
//            'secretKey'      => 'eGRqlsxwtykBUOljEm-ez5DvXYBq6Ik7KX1lRV7E', //七牛服务器
//            'accessKey'      => 'E5Ju0WB5WkiRgl-hXim3a-H9AM5moPFvKGm9016S', //七牛用户
//            'domain'         => 'ourcpojhy.bkt.clouddn.com', //七牛密码
//            'bucket'         => 'btsdk', //空间名称
//        );
        /**/
        $upload->autoSub  = false;
        $upload->saveName = $tag.'_'.time();
        $upload->rootPath = "www.sy217.com/assets/sc/";
        $upload->savePath = "{$tag}/";
        $info = $upload->uploadOne($_FILES['upload']);

        if(is_array($info)){
            $info['root'] = C('FTP_URL');
            $info['fullpath'] = str_replace('www.sy217.com','',$info['fullpath']);
            $this->ajaxReturn($info);
        }
    }

    /**
     * 上报玩家玩过的游戏
     */
    public function played(){
        $data = array(
            'uid' => I('uid'),
            'appid' => I('appid'),
            'type' => I('type'),
            'sign' => I('sign')
        );
        $res = checkSign($data,C('API_KEY'));
        if(!$res) {
            $this->ajaxReturn(null,'签名错误',0);
        }
        $data['createTime'] = time();
        if(M('played')->add($data)){
            $this->ajaxReturn(null,'操作成功');
        }else{
            $this->ajaxReturn(null,'操作失败',0);
        }
    }

    /**
     * 收入排行
     */
    public function payTop(){
        $day = 1;
        //前一周  YEARWEEK(currTime,1) = YEARWEEK(now(),1)-1

        //SDK数据
        $sdk_lastweek = M('pay_by_day')
            ->where("time = DATE_SUB(CURDATE() ,INTERVAL {$day} day)")
            ->group('appid')->order('appid')->having('money>0')
            ->getField('appid,sum(pay_amount) money',true);

        //全部游戏
        $haveGame = $sdk_lastweek;
        $gameID = '';
        foreach($haveGame as $k=>$v){
            $gameID[] = $k;
        }

        $data = array();
        foreach($gameID as $k=>$v){
            $appid = get_185_gameinfo($v);
            $money = $sdk_lastweek[$v];
            if($money > 0){
                $data[] = array(
                    '185_appid' => $appid['id'],
                    'sdk_appid' => $v,
                    'money' => $money,
                    'time' => date('Y-m-d',strtotime("-{$day} day"))
                );
            }
        }
        $have = M('income_top')->where(array('time'=>date('Y-m-d',strtotime("-{$day} day"))))->find();
        if($data && !$have){
            M('income_top')->addAll($data);
        }
    }
}