<?php
/**
 * 苹果分包证书
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/7/31
 * Time: 14:59
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class SubIosCertifiController extends AdminbaseController{

    /**
     * 列表
     */
    public function index(){
        $where = '';
        $appid = I('appid');
        $channel = I('channel');
        $status = I('status',-1);
        if($appid) $where['appid'] = $appid;
        if($channel) $where['channel'] = $channel;
        if($status >= 0) $where['status'] = $status;
//        dump($where);
        $count = M('subpackage_ios_certifi')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('subpackage_ios_certifi')->where($where)->limit($page->firstRow,$page->listRows)->order('id desc')->select();

        $this->game = M('game')->where(array('status'=>1))->getField('id,game_name');
        $this->channel = M('channel')->getField('id,name');
        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->appid = $appid;
        $this->cid = $channel;
        $this->status = $status;
        $this->display();
    }

    /**
     * 添加证书
     */
    public function add(){
        if(IS_POST){
            $data['appid'] = I('appid');
            if($data['appid'] == 0){
                $data['tag'] = 0;
            }else{
                $data['tag'] = M('game')->where(array('id'=>$data['appid']))->getField('tag');
            }
            $data['channel'] = I('channel');
            $data['p12'] = I('p12_url');
            $data['provision'] = I('provision_url');
            $data['p12pass'] = base64_encode(I('p12_pwd'));
            $data['create_time'] = time();

            if(M('subpackage_ios_certifi')->where(array('appid'=>$data['appid'],'channel'=>$data['channel']))->find()){
                $this->error('选择的游戏和渠道已经存在');
            }
            if($id = M('subpackage_ios_certifi')->add($data)){
                $data['opStatus'] = 1;
                unset($data['appid'],$data['create_time']);
                $url = urldecode(http_build_query($data).C('FUNCTION_KEY'));
                $data['sign'] = md5($url);

                $res = curl_post(C('CertInstallOp'),$data);
                if($res !== false){
                    $res = json_decode($res,true);
                    if($res['state'] == 1){
                        M('subpackage_ios_certifi')->where(array('id'=>$id))->setField('status',1);
                        $this->success('操作成功');
                    }else{
                        $this->error("操作失败,错误码：{$res['state']},请联系管理员");
                    }

                }else{
                    $this->error('请求远程服务器失败');
                }
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->display();
        }
    }

    /**
     * 删除
     */
    public function del(){
        $id = I('id');
        $info = M('subpackage_ios_certifi')->where(array('id'=>$id))->find();
        $data = array(
            'tag' => $info['tag'],
            'channel' => $info['channel'],
            'p12' => 1,
            'provision' => 1,
            'p12pass' => 1,
            'opStatus' => 0
        );
        $url = urldecode(http_build_query($data).C('FUNCTION_KEY'));
        $data['sign'] = md5($url);

        $res = curl_post(C('CertInstallOp'),$data);

        if($res !== false){
            $res = json_decode($res,true);

            if($res['state'] == 1){
                M('subpackage_ios_certifi')->where(array('id'=>$id))->delete();
                $this->success('操作成功');
            }else{
                $this->error("操作失败,错误码：{$res['state']},请联系管理员");
            }
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 重新安装
     */
    public function reInstall(){
        $id = I('id');
        $data = M('subpackage_ios_certifi')->field('tag,channel,p12,provision,p12pass')->where(array('id'=>$id))->find();
        $data['opStatus'] = 1;
        $url = urldecode(http_build_query($data).C('FUNCTION_KEY'));
        $data['sign'] = md5($url);

        $res = curl_post(C('CertInstallOp'),$data);
        if($res !== false){
            $res = json_decode($res,true);
            if($res['state'] == 1){
                M('subpackage_ios_certifi')->where(array('id'=>$id))->setField('status',1);
                $this->success('操作成功');
            }else{
                $this->error("操作失败,错误码：{$res['state']},请联系管理员");
            }

        }else{
            $this->error('请求远程服务器失败');
        }
    }

    /**
     * 安装详情
     */
    public function view(){
        $id = I('id');
        $info = M('subpackage_ios_certifi')->field('tag,channel')->where(array('id'=>$id))->find();
        $url = urldecode(http_build_query($info).C('FUNCTION_KEY'));
        $info['sign'] = md5($url);
        $res = curl_post(C('CertInstallQuery'),$info);

        if($res !== false){
            $res = json_decode($res,true);
            $data = $res['certList'];

            $html = '';
            foreach ($data as $k=>&$v){
                //数据渲染
                if(strlen($v['tag']) == 1){
                    $v['tag'] = '全部游戏';
                }
                if($v['channel'] === 0){
                    $v['channel'] = '全部渠道';
                }

                $create_time = date('Y-m-d H:i:s',substr($v['create_time'],0,10));
                if($v['modify_time'] > 0) $modify_time = date('Y-m-d H:i:s',substr($v['modify_time'],0,10));
                $opStatus = $v['opStatus'] == 1 ? '安装' : '删除';
                switch ($v['status']){
                    case 0:$status = '安装失败';break;
                    case 1:$status = '安装成功';break;
                    case 2:$status = '证书问题导致的失败';break;
                    case 3:$status = '等待安装';break;
                    case 4:$status = '删除成功';break;
                }

                $html .= <<<html
            <div style="padding:15px">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <th style="width:24%">游戏简写</th>
                        <th>{$v['tag']}</th>
                    </tr>
                    <tr>
                        <th>渠道</th>
                        <th>{$v['channel']}</th>
                    </tr>
                    <tr>
                        <th>证书文件</th>
                        <th>{$v['p12']}</th>
                    </tr>
                    <tr>
                        <th>provision文件</th>
                        <th>{$v['provision']}</th>
                    </tr>
                    <tr>
                        <th>证书名称</th>
                        <th>{$v['certName']}</th>
                    </tr>
                    <tr>
                        <th>证书HASH值</th>
                        <th>{$v['certHash']}</th>
                    </tr>
                    <tr>
                        <th>证书安装的服务器ID</th>
                        <th>{$v['deploy_id']}</th>
                    </tr>
                    <tr>
                        <th>描述文件安装后路径</th>
                        <th>{$v['provisionName']}</th>
                    </tr>
                    <tr>
                        <th>操作类型</th>
                        <th>{$opStatus}</th>
                    </tr>
                    <tr>
                        <th>安装状态</th>
                        <th>{$status}</th>
                    </tr>
                    <tr>
                        <th>创建时间</th>
                        <th>{$create_time}</th>
                    </tr>
                    <tr>
                        <th>修改时间</th>
                        <th>{$modify_time}</th>
                    </tr>
                </tbody>
            </table>
        </div>
html;
            }
            $this->success($html);
        }else{
            $this->error('请求远程服务器失败');
        }
    }

    /**
     * 上传
     */
    public function upload(){

        $name = explode('.',$_FILES['upload']['name']);
        $name = $name[0];
        $upload = new \Think\Upload();
        $upload->driverConfig = C('UPLOAD_IOS_CERTIFI');
        $upload->autoSub  = false;
        $upload->exts = array('p12','mobileprovision');
        $upload->saveName = $name.'_'.time();
        $upload->rootPath = "/";
//        $upload->savePath = "{$name}/";
        $info = $upload->uploadOne($_FILES['upload']);

        if(is_array($info)){
            $info['status'] = 1;
            $info['fullpath'] = str_replace('/','',$info['fullpath']);
            $this->ajaxReturn($info);
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>$upload->getError()));
        }
    }
}