<?php
/**
 * 自动分包
 * User: fantasmic
 * Date: 2017/7/5
 * Time: 15:01
 */
namespace Admin\Controller;
//use Common\Controller\AdminbaseController;
use Think\Controller;

class AutoSubController extends Controller{

    /**
     * 自动分包
     */
    public function index(){
        $system = I('system',1);
        $table = $system == 1 ? 'subpackage_android' : 'subpackage_ios';

        $data = M($table)->field('tag,appid,cid channel,version')->where(array('auto'=>1,'status'=>0))->order('modifiy_time')->select();

	    foreach($data as $k=>$v){
            $data[$k]['sdk'] = M('game')->where(array('id'=>$v['appid']))->getField('source');
            M($table)->where(array('tag'=>$v['tag'],'cid'=>$v['cid'],'version'=>$v['version']))->setField('modifiy_time',time());
        }
        $send = array(
            'data' => $data,
            'state' => 1
        );
        exit(json_encode($send));
    }

    /**
     * 分包完成后回调方法
     */
    public function subFinish(){
        $appid = I('appid');
        $tag = I('tag');
        $cid = I('channel');
        $ver = I('version');
        $system = I('system');
        $url = I('url');
        $sdk = I('sdk');
        $sign = I('sign');
        $key = md5("appid={$appid}&tag={$tag}&channel={$cid}&version={$ver}&system={$system}&sdk={$sdk}&url={$url}".C('FUNCTION_KEY'));
        if($key == $sign){
            $where['appid'] = $appid;
            $where['cid'] = $cid;
            $where['version'] = $ver;
            $table = $system == 1 ? M('subpackage_android') : M('subpackage_ios');
            if(!$table->where($where)->find()){
                $where['tag'] = $tag;
                $where['status'] = 1;
                $where['fenbao_url'] = $url;
                $where['create_time'] = time();
                $where['wait'] = 0;
                $where['auto'] = M('channel')->where(array('id'=>$cid))->getField('is_auto_fenbo');
                $table->add($where);
            }else{
                $table->where($where)->setField(array('wait'=>0,'status'=>1,'fenbao_url'=>$url,'create_time'=>time()));
            }
        }
    }

    /**
     * 安卓融合分包完成回调
     */
    public function rhFinish(){
        diylogs('rhFinish',I(''));
        $appid = I('appid');
        $tag = I('tag');
        $cid = I('channel');
        $url = I('url');
        $sign = I('sign');
        $key = md5("appid={$appid}&tag={$tag}&channel={$cid}&url={$url}".C('FUNCTION_KEY'));
        if($key == $sign){
            M('subpackage_unite')->where(array('rh_appid'=>$appid,'rh_channel'=>$cid))->setField(array('status'=>1,'downurl'=>$url));
        }else{
            $this->error('sign error');
        }
    }

    /**
     * 苹果融合分包完成回调
     */
    public function rhIosFinish(){
    	diylogs('rhiosFinish',I(''));
        $appid = I('appid');
        $tag = I('tag');
        $cid = I('channel');
        $url = I('url');
        $sign = I('sign');
        $key = md5("appid={$appid}&tag={$tag}&channel={$cid}&url={$url}".C('FUNCTION_KEY'));
        if($key == $sign){
            M('subpackage_unite_ios')->where(array('rh_appid'=>$appid,'rh_channel'=>$cid))->setField(array('status'=>1,'downurl'=>$url));
        }else{
            $this->error('sign error');
        }
    }
}