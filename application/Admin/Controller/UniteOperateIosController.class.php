<?php
/**
 * 苹果联运渠道
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/5/15
 * Time: 10:21
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class UniteOperateIosController extends AdminbaseController{

    /**
     * 联运渠道列表
     */
    public function index(){
        $cid = I('cid');
        $where = '';
        if($cid) $where['cid'] = $cid;
        $count = M('unite_channel_ios')->where($where)->count();
        $page = $this->page($count,20);
        $this->page = $page->show('Admin');
        $this->data = M('unite_channel_ios')->where($where)->limit($page->firstRow,$page->listRows)->order('id desc')->select();
        $this->channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->getfield('masterID,masterName',true);
        $this->cid = $cid;
        $this->display();
    }

    /**
     * 新增渠道
     */
    public function addChannel(){
        if(IS_POST){
            $data = I('');
            $arr = array();
            if(empty($data['cid'])){
                $this->error('请选择渠道');
            }
            if(M('unite_channel_ios')->where(array('cid'=>$data['cid']))->find()){
                $this->error('该渠道已经创建过数据');
            }
            if($data['tag'] == ''){
                $this->error('拼音简写不能为空');
            }
            if(M('unite_channel_ios')->where(array('tag'=>$data['tag']))->find()){
                $this->error('拼音简写不能重复');
            }
            if($data['packname'] == '' || strrpos($data['packname'],'#tag#') === false){
                $this->error('包名格式请严格按照示例填写');
            }
            foreach($data['name'] as $k=>$v){
                if($v == '' || $data['mapped'][$k] == ''){
                    $this->error('参数名或映射值不能为空');
                }
            }
            $add = array(
                'cid' => $data['cid'],
                'tag' => $data['tag'],
                'packname' => $data['packname'],
                'again_sub' => $data['again_sub'] ? 1 : 0,
                'create_time' => time()
            );
            foreach($data['name'] as $k=>$v){
                if($data['type'][$k] == 4 && $data['default'][$k] == ''){
                    $this->error($v.'：请填写文件上传路径');
                }
                $arr[] = array(
                    'name' => $v,
                    'mapped' => $data['mapped'][$k],
                    'default' => $data['default'][$k],
                    'type' => $data['type'][$k],
                    'value' => $data['value'][$k],
                    'change' => $data['change'][$k],
                    'required' => $data['required'][$k]
                );
            }
            $add['parameter'] = json_encode($arr);
            if(M('unite_channel_ios')->add($add)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->getfield('masterID,masterName',true);
            $this->display();
        }
    }

    /**
     * 修改渠道
     */
    public function editChannel(){
        if(IS_POST){
            $data = I('');
            $arr = array();
            if(empty($data['cid'])){
                $this->error('请选择渠道');
            }
            if(M('unite_channel_ios')->where(array('cid'=>$data['cid'],'id'=>array('neq',$data['id'])))->find()){
                $this->error('该渠道已经创建过数据');
            }
            if($data['tag'] == ''){
                $this->error('拼音简写不能为空');
            }
            if(M('unite_channel_ios')->where(array('tag'=>$data['tag'],'id'=>array('neq',$data['id'])))->find()){
                $this->error('拼音简写不能重复');
            }
            if($data['packname'] == '' || strrpos($data['packname'],'#tag#') === false){
                $this->error('包名格式请严格按照示例填写');
            }
            foreach($data['name'] as $k=>$v){
                if($v == '' || $data['mapped'][$k] == ''){
                    $this->error('参数名或映射值不能为空');
                }
            }
            $add = array(
                'id' => $data['id'],
                'cid' => $data['cid'],
                'tag' => $data['tag'],
                'packname' => $data['packname'],
                'again_sub' => $data['again_sub'] ? 1 : 0,
                'create_time' => time()
            );
            foreach($data['name'] as $k=>$v){
                if($data['type'][$k] == 4 && $data['default'][$k] == ''){
                    $this->error($v.'：请填写文件上传路径');
                }
                $arr[] = array(
                    'name' => $v,
                    'mapped' => $data['mapped'][$k],
                    'default' => $data['default'][$k],
                    'type' => $data['type'][$k],
                    'value' => $data['value'][$k],
                    'change' => $data['change'][$k],
                    'required' => $data['required'][$k]
                );
            }
            $add['parameter'] = json_encode($arr);
            if(M('unite_channel_ios')->save($add)){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $data = M('unite_channel_ios')->where(array('id'=>$id))->find();
            $data['parameter'] = json_decode($data['parameter'],true);
            $this->data = $data;
            $this->channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->getfield('masterID,masterName',true);
            $this->display();
        }
    }

    /**
     * 删除渠道
     */
    public function delChannel(){
        $id = I('id');
        if(is_array($id)){
            $id = implode(',',$id);
        }
        if(M('unite_channel_ios')->where(array('id'=>array('in',$id)))->delete()){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 分包列表
     */
    public function subIndex(){
        $masterid = I('masterid');
        $appid = I('appid','');
        $where = '';
        if($masterid > 0){
            $where['masterid'] = $masterid;
        }
        if($appid > 0){
            $where['appid'] = $appid;
        }
        $count = M('subpackage_unite_ios')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('subpackage_unite_ios')->where($where)->limit($page->firstRow,$page->listRows)->order('id desc')->select();
        foreach($data as $k=>&$v){
            $v['downurl'] = str_replace('sy217.com','sy218.com',$v['downurl']);
        }
        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->game = M('game')->getField('id,game_name',true);
        $this->gameList = get_game_list($appid,2,'all','all','all',1);
        $this->channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->getfield('masterID,masterName',true);
        $this->masterid = $masterid;
        $this->appid = $appid;
        $this->display();
    }

    /**
     * 新增分包
     */
    public function subAdd(){
        if(IS_POST){
            $data = I('');
            if(empty($data['appid'])) $this->error('请选择游戏');
            if(empty($data['masterid'])) $this->error('请选择渠道商');
            if(empty($data['packname'])) $this->error('包名不能为空');
            if(M('subpackage_unite_ios')->where(array('appid'=>$data['appid'],'masterid'=>$data['masterid']))->find()){
                $this->error('已有该游戏和渠道商的数据，请匆重复创建');
            }
            $add = array(
                'appid' => $data['appid'],
                'masterid' => $data['masterid'],
                'mastertag' => $data['mastertag'],
                'again_sub' => $data['again_sub'],
                'packname' => $data['packname'],
                'orientation' => $data['orientation'] ? 1 : 2,
                'create_time' => time()
            );
            //本地保存参数数组
            $arr = array();
            //请求接口数组
            $params = array();
            foreach($data['type'] as $k=>$v){
                //上传文件源路径
                $source = '';
                $path = '';
                if($v == 4){
                    //处理路径 /xxx/xxx.xx或/xxx/xxx/xxx.xx,要把路径和文件名分开
                    $pathinfo = explode('/',trim($data['default'][$k],'/'));
                    if(count($pathinfo) > 1){
                        for($i = 0 ;$i < count($pathinfo) - 1;$i++){
                            $path .= $pathinfo[$i].'/';
                        }
                    }else{
                        $path = '';
                    }
                    $name = explode('.',end($pathinfo));
//                    dump($_FILES);
//                    dump($path);
                    $info = $this->uploadfile($name[0],$data['appid'].'/'.$data['masterid'].'/'.$path,$name[0]);
                    if(!is_array($info)){
                        $this->error($data['name'][$k].':'.$info);
                    }else{
                        if($info['key'] == $data['default'][$k]){
                            $source = $info['fullpath'];
                        }
                    }
                    if($source == ''){
                        $this->error($data['name'][$k].'字段上传文件出错');
                    }
                }else{
                    if($data['required'][$k] == 1 && $data['default'][$k] == ''){
                        $this->error($data['name'][$k].'值不能为空');
                    }
                }
                $arr[$k] = array(
                    'name' => $data['name'][$k],
                    'key' => $data['key'][$k],
                    'type' => $v,
                    'change' => $data['change'][$k],
                    'required' => $data['required'][$k],
                    'default' => $data['default'][$k],
                    'value' => $data['value'][$k],
                    'source' => $source
                );
                //$params赋值
                if($v == 4){
                    $params[$data['key'][$k]] = array(
                        'type' => 'file',
                        'source' => $source,
                        'target' => $save
                    );
                }else{
                    switch ($v){
                        case 1 : $type = 'client';break;
                        case 2 : $type = 'server';break;
                        case 3 : $type = 'cs';break;
                        default : $type = 'client';
                    }
                    $params[$data['key'][$k]] = array(
                        'type' => $type,
                        'value' => $data['default'][$k]
                    );
                }
            }

            $json = json_encode($params);
            $curldata = array(
                'appid' => $data['appid'],
                'masterID' => $data['masterid'],
                'params' => base64_encode($json),
            );
            $curldata['sign'] = md5(urldecode(http_build_query($curldata)).C('RH_KEY'));
            $res = json_decode(curl_post(C('RH_CHANNEL'),$curldata),true);
            if($res['state'] == 1){
                $add['rh_appid'] = $res['rh_appID'];
                $add['rh_channel'] = $res['rh_channelID'];
                $add['rh_appkey'] = $res['rh_appKey'];
                $add['rh_appsecret'] = $res['rh_appSecret'];
                $add['rh_payurl'] = $res['rh_payUrl'];
                $add['params'] = json_encode($arr);
                if(M('subpackage_unite_ios')->add($add)){
                    $this->success('操作成功');
                }else{
                    $this->error('操作失败');
                }
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->cdata = M('unite_channel_ios')->select();
            $this->channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->getfield('masterID,masterName',true);
            $this->gameList = get_game_list('',2,'all','all','all',2);
            $this->display();
        }
    }


    /**
     * 修改分包
     */
    public function subEdit(){
        if(IS_POST){
            $data = I('');
            //如果上传文件过大会导致接收不到数据会报此错
            if(empty($data['packname'])) $this->error('包名不能为空');

            $edit = array(
                'id' => $data['id'],
                'packname' => $data['packname'],
                'orientation' => $data['orientation'] ? 1 : 2,
                'create_time' => time()
            );
            //本地保存参数数组
            $arr = array();
            //请求接口数组
            $params = array();
            foreach($data['type'] as $k=>$v){
                //上传文件源路径
                $source = '';
                $path = '';
                if($v == 4){
                    //处理路径 /xxx/xxx.xx或/xxx/xxx/xxx.xx,要把路径和文件名分开
                    $pathinfo = explode('/',trim($data['default'][$k],'/'));
                    if(count($pathinfo) > 1){
                        for($i = 0 ;$i < count($pathinfo) - 1;$i++){
                            $path .= $pathinfo[$i].'/';
                        }
                    }else{
                        $path = '';
                    }
                    $name = explode('.',end($pathinfo));
                    if($_FILES[$name[0]]){
                        $info = $this->uploadfile($name[0],$data['appid'].'/'.$data['masterid'].'/'.$path,$name[0]);
                        if(!is_array($info)){
                            $this->error($data['name'][$k].':'.$info);
                        }else{
                            if($info['key'] == $data['default'][$k]){
                                $source = $info['fullpath'];
                            }
                        }
                        if($source == ''){
                            $this->error($data['name'][$k].'字段上传文件出错');
                        }
                    }else{
                        $source = $data['source'][$k];
                    }

                }else{
                    if($data['required'][$k] == 1 && $data['default'][$k] == ''){
                        $this->error($data['name'][$k].'值不能为空');
                    }
                }
                $arr[$k] = array(
                    'name' => $data['name'][$k],
                    'key' => $data['key'][$k],
                    'type' => $v,
                    'change' => $data['change'][$k],
                    'required' => $data['required'][$k],
                    'default' => $data['default'][$k],
                    'value' => $data['value'][$k],
                    'source' => $source
                );
                //$params赋值
                if($v == 4){
                    $params[$data['key'][$k]] = array(
                        'type' => 'file',
                        'source' => $source,
                        'target' => $save
                    );
                }else{
                    switch ($v){
                        case 1 : $type = 'client';break;
                        case 2 : $type = 'server';break;
                        case 3 : $type = 'cs';break;
                        default : $type = 'client';
                    }
                    $params[$data['key'][$k]] = array(
                        'type' => $type,
                        'value' => $data['default'][$k]
                    );
                }
            }

            $json = json_encode($params);
            $curldata = array(
                'appid' => $data['appid'],
                'masterID' => $data['masterid'],
                'params' => base64_encode($json),
            );
            $curldata['sign'] = md5(urldecode(http_build_query($curldata)).C('RH_KEY'));
            $res = json_decode(curl_post(C('RH_CHANNEL'),$curldata),true);
            if($res['state'] == 1){
                $edit['rh_appid'] = $res['rh_appID'];
                $edit['rh_channel'] = $res['rh_channelID'];
                $edit['rh_appkey'] = $res['rh_appKey'];
                $edit['rh_appsecret'] = $res['rh_appSecret'];
                $edit['rh_payurl'] = $res['rh_payUrl'];
                $edit['params'] = json_encode($arr);
                if(M('subpackage_unite_ios')->save($edit)){
                    $this->success('操作成功');
                }else{
                    $this->error('操作失败');
                }
            }else{
                $this->error('操作失败');
            }
        }else{
            $id = I('id');
            $data = M('subpackage_unite_ios')->where(array('id'=>$id))->find();
            $data['params'] = json_decode($data['params'],true);
            $this->data = $data;
            $this->channel = M('uchannelmaster',null,C('RH_DB_CONFIG'))->getfield('masterID,masterName',true);
            $this->gameList = get_game_list($data['appid'],2,'all','all','all',1);
            $this->notice = M('game')->where(array('id'=>$data['appid']))->getField('rhnotice');
            $this->display();
        }
    }

    /**
     * 删除分包
     */
    public function subDel(){
        $id = I('id');
        if(is_array($id)){
            $id = implode(',',$id);
        }
        if(M('subpackage_unite_ios')->where(array('id'=>array('in',$id)))->delete()){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 请求分包
     */
    public function doPackage(){
        $id = I('id');
        $pakcOrretry = I('type',1);
        $data = M('subpackage_unite_ios')->where(array('id'=>$id))->find();
        $arr = json_decode($data['params'],true);
        $params = '';

        foreach($arr as $k=>$v){
            if($v['type'] == 4){
                $params[$v['key']] = array(
                    'type' => 'file',
                    'source' => $v['source'],
                    'target' => $v['default']
                );
            }else{
                switch ($v['type']){
                    case 1 : $type = 'client';break;
                    case 2 : $type = 'server';break;
                    case 3 : $type = 'cs';break;
                    default : $type = 'client';
                }
                $params[$v['key']] = array(
                    'type' => $type,
                    'value' => $v['default']
                );
            }
        }

        $sub = array(
            'appid' => $data['rh_appid'],
            'appkey' => $data['rh_appkey'],
            'channel' => $data['rh_channel'],
            'masterID' => $data['masterid'],
            'masterTag' => $data['mastertag'],
            'orientation' => $data['orientation'] == 1 ? 'landscape' : 'portrait',
            'packageName' => $data['packname'],
            'tag' => M('game')->where(array('id'=>$data['appid']))->getField('tag'),
            'params' => base64_encode(json_encode($params))
        );
        $sub['sign'] = md5(urldecode(http_build_query($sub)).C('FUNCTION_KEY'));
        $url = $pakcOrretry == 1 ? C('RH_IOS_URL') : C('RH_IOS_RETRY_ONE');
        $res = curl_post($url,$sub);
        $res = json_decode($res,true);
        if(is_array($res)){
            if($res['state'] == 1){
                M('subpackage_unite_ios')->where(array('id'=>$id))->setField(array('status'=>2,'sub_time'=>time()));
                $this->success('正在出包中,请稍后刷新进度');
            }elseif($res['state'] == 6){
                M('subpackage_unite_ios')->where(array('id'=>$id))->setField(array('status'=>1,'downurl'=>$res['url'],'sub_time'=>time()));
                $this->success('分包完成');
            }else{
                $this->error(getPackError($res['state']));
            }
        }else{
            $this->error('请求分包失败');
        }
    }

    /**
     * 分包进度
     */
    public function checkProgress(){
        $id = I('id');
        $data = M('subpackage_unite_ios')->where(array('id'=>$id))->find();
        $sub['appid'] = $data['rh_appid'];
        $sub['channel'] = $data['rh_channel'];
        $sub['sign'] = md5(http_build_query($sub).C('FUNCTION_KEY'));
        $res = curl_post(C('RH_IOS_CHECK_ONE'),$sub);

        $res = json_decode($res,true);
        if(is_array($res)){
            if($res['state'] == 6){
                M('subpackage_unite_ios')->where(array('id'=>$id))->setField(array('status'=>1,'downurl'=>$res['url']));
                $this->success('分包完成');
            }else{
                if($res['state'] == 7){
                    M('subpackage_unite_ios')->where(array('id'=>$id))->setField(array('status'=>3));
                }
                $this->error(getPackError($res['state']));
            }
        }else{
            $this->error('请求接口失败');
        }
    }


    /**
     * 根据选择游戏得到渠道商包名
     */
    public function getPackName(){
        $appid = I('appid');
        $cid = I('cid');
        if(!$appid || !$cid){
            $this->error('缺少参数');
        }else{
            $info = M('game')->field('tag,rhnotice')->where(array('id'=>$appid))->find();
            if($info['tag'] == ''){
                $this->error('生成包名失败，请自行填写包名');
            }else{
                $pack = M('unite_channel_ios')->field('packname,tag,again_sub')->where(array('cid'=>$cid))->find();
                $tag = str_replace('_sy','',$info['tag']);
                $str = str_replace('#tag#',$tag,$pack['packname']);
                $this->success(array('str'=>$str,'tag'=>$pack['tag'],'notice'=>$info['rhnotice'],'again_sub'=>$pack['again_sub']));
            }
        }
    }

    /**
     * 获得渠道商参数
     */
    public function getParams(){
        $cid = I('cid');
        $data = M('unite_channel_ios')->where(array('cid'=>$cid))->getField('parameter');
        if($data){
            $data = json_decode($data,true);
            $html = '<div class="control-group append"><label class="control-label">参数</label>';
            $html .= '<div class="controls" style="margin-top: 5px;color:#da534f">
                        <p>注：红色参数名为必填项</p>
                    </div>';
            foreach($data as $v){
                $style = '';
                if($v['required'] == 1){
                    $style = 'color:#da534f';
                }
                $html .= '<div class="controls" style="margin-bottom:10px;">';
                $html .= '<p title="'.$v['name'].'" style="text-align:left;height: 30px;line-height: 30px;float: left;margin: 0px 10px 0px 0px;width:400px;overflow: hidden;'.$style.'">'.$v['name'].'：</p>';

                //参数类型为文件
                if($v['type'] == 4){
                    $name = explode('.',end(explode('/',$v['default'])));
                    $html .= '<input type="file" style="margin-left: -410px;margin-top: 30px;" name="'.$name[0].'"  />';
                    $html .= '<input type="hidden" name="default[]" value="'.$v['default'].'" />';
                }else{
                    //值为字符类型
                    if($v['value'] == 1){
                        $str = $v['change'] == 1 ? '' : 'readonly';
                        $html .= '<input type="text" style="width:500px;margin-left: -410px;margin-top: 30px;"  name="default[]" '.$str.' value="'.$v['default'].'"/>';
                    }else{
                        //值为布尔类型
                        $str = '';
                        if($v['change'] == 2){
                            $str = 'disabled';
                        }
                        $html .= '<input type="hidden" class="default" name="default[]" value="'.$v['default'].'"/>';
                        $checked = $v['default'] == 'true' ? 'checked' : '';
                        $html .= '<div style="margin-left: -410px;margin-top: 30px;" class="appendswitch" data-on="success"  data-off="danger">
                                    <input type="checkbox" '.$str.' '.$checked.' onchange="getCheckVal($(this))"/>
                                </div>';
                    }
                }

                $html .= '<input type="hidden" name="name[]" value="'.$v['name'].'"/>';
                $html .= '<input type="hidden" name="key[]" value="'.$v['mapped'].'"/>';
                $html .= '<input type="hidden" name="type[]" value="'.$v['type'].'"/>';
                $html .= '<input type="hidden" name="change[]" value="'.$v['change'].'"/>';
                $html .= '<input type="hidden" name="required[]" value="'.$v['required'].'"/>';
                $html .= '<input type="hidden" name="value[]" value="'.$v['value'].'"/>';
                $html .= '</div>';
            }
            $html .= '</div>';

            $this->success($html);
        }else{
            $this->error('非法参数');
        }
    }

    /*
     * 上传文件
     */
    public function uploadfile($file,$root,$name){
        $upload = new \Think\Upload();
        $upload->driverConfig = array(
            'host' => '58.216.10.9',
            'port' => '2121',
            'timeout'  => '900',
            'username' => 'ftplianyun',
            'password' => 'Ftp@32326663!@#efb|fsd7VM#gv&'
        );
        $upload->maxSize = 10494615;//10M
        $upload->autoSub  = false;
        $upload->rootPath = 'upload/';
        $upload->savePath = $root;
        $upload->replace = true;
        $upload->saveName = $name;
        $info = $upload->uploadOne($_FILES[$file]);
        if($info){
            return $info;
        }else{
            return $upload->getError();
        }
    }

    public function ajaxCheckUrl(){
        $url = I('url');
        checkUrl($url) ? $this->success(): $this->error('数据正在上传中，请稍后下载');
    }
}

