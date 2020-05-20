<?php
/**
 * 游戏管理.
 * User: fantasmic
 * Date: 2017/6/16
 * Time: 18:04
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;
class GameController extends AdminbaseController{

    /**
     * 列表
     */
    public function index(){
        $keywords = I('keywords');
        $type = I('type',-1);
        $appid = I('appid');
        $tag = I('tag');
        $group = I('group');
        $access_type = I('access_type');
        $game_platform = I('game_platform');
        $game_sdk = I('game_sdk',1);

        if($type == 1){
            $where['is_audit'] = 1;
        }elseif($type == 0){
            $where['is_audit'] = 0;
        }
        if($keywords) $where['game_name'] = array('like','%'.$keywords.'%');
        if($appid) $where['id'] = $appid;
        if($tag) $where['tag'] =$tag;
        if($group) $where['group'] = $group;
        if($access_type) $where['access_type'] = $access_type;
        if($game_platform) $where['game_platform'] = $game_platform;
        if($game_sdk) $where['game_sdk'] = $game_sdk;

        $where['status'] = 1;
        $data = M('game')->where($where)->order('id desc')->select();
        $count = count($data);
        $page = $this->page($count, 15);
        $data = array_slice($data,$page->firstRow, $page->listRows);

        $this->game_sdk = $game_sdk;
        $this->game_platform = $game_platform;
        $this->access_type = $access_type;
        $this->type = $type;
        $this->keywords = $keywords;
        $this->appid = $appid;
        $this->tag = $tag;
        $this->group = $group;
        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->game_group = C('GAME_GROUP');
        $this->game_platfrom_list = M('game_platform')->select();
        $this->game_sdk_list = M('game_sdk')->select();
        $this->display();
    }

    /**
     * 新增游戏
     */
    public function add(){
        $name = I('name');
        if(empty($name)) $this->error('请输入游戏名！');
        if(M('game')->where(array('game_name'=>$name,'status'=>1))->find()){
            $this->error('游戏名已存在！');
        }
        $data = array(
            'game_name' => $name,
            'server_key' => makeKey(1),
            'client_key' => makeKey(2),
            'create_time' => time(),
            'add_time' => time(),
            'modifiy_time' => time()
        );
        if($id = M('game')->add($data)){
            $game_role = session('game_role');
            $uid = session('ADMIN_ID');
            if($uid != 1 && $game_role != 'all'){
                $role = M("userrights")->where(array('userid'=>$uid))->getField('game_role');
                $newrole = $role.','.$id;
                M('userrights')->where(array('userid'=>$uid))->setField('game_role',$newrole);
                session('game_role',$newrole);
            }
//            $this->cleanCache();
            $this->success($id);
        }else{
            $this->error('创建失败！');
        }
    }

    /**
     * 完善游戏
     */
    public function edit(){
    
        if(IS_POST){
            
            $model = D('Common/Game');
            if($_POST['access_type'] == 1) $_POST['game_platform'] = 1;
	        $_POST['start_time'] = strtotime($_POST['start_time']);
            if($_POST['android_url'] && $_POST['android_up']){
                $_POST['android_version_num'] = $_POST['old_android_ver'] + 1;
            }
            if($_POST['ios_url'] && $_POST['ios_up']){
                $_POST['ios_version_num'] = $_POST['old_ios_ver'] + 1;
            }
	    if($_POST['ios_super_url'] && $_POST['super_up']){
                $_POST['super_version_num'] = $_POST['old_super_ver'] + 1;
            }
            if($model->create()){
                if($model->save()){
                    $this->success('操作成功');
                }else{
                    $this->error('操作失败');
                }
            }else{
                $this->error($model->getError());
            }
        }else{
            $id = I('id');
            $data = M('game g')
                ->field('g.*,gm.name pri_name,op.name op_name')
                ->join('left join '.C('DB_PREFIX').'gm_pri gm on gm.id=g.gm_pri_id')
                ->join('left join '.C('DB_PREFIX').'rebate_option op on op.id=g.rebate_option')
                ->where(array('g.id'=>$id,'g.status'=>1))->find();
            if(!$data){
                $this->error('错误行为');
            }else{
                $game_platform = M('game_platform')->where(array('id'=>array('neq',1)))->select();
                $this->game_platform = $game_platform;
                $this->game_type = C('PLATFORM');
                $this->game_sdk = M('game_sdk')->select();

                $this->data = $data;
                $this->game_group = C('GAME_GROUP');
                $this->channel = C('CHANNEL_TYPE');
                $this->display();
            }
        }
    }

    /**
     * 删除游戏
     */
    public function del(){
        $id = I('id');
        $source = I('source');
        if(!$id) $this->error('请选择数据');
        if(is_array($id)){
            $id = implode(",",$id);
        }
        $where['id'] = array('in',$id);
        if($source == 1){
            $res = M('game')->where($where)->setField(array('status'=>0,'uid'=>session('ADMIN_ID'),'modifiy_time'=>time()));
        }else{
            $res = M('game')->delete($id);
        }
        if($res !== false){
//            $this->cleanCache();
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

    /**
     * ajax修改状态
     */
    public function changeStatus(){
        $id = I('id',0);
        $status = I('status',0);
        $type = I('type');
        $model = D('Common/Game');
        if($type == 1){
            $set = array('is_audit'=>$status);
        }else{
            $set = array('trade'=>$status);
        }
        if($model->where(array('id'=>$id))->setField($set) !== false){
            $model->putData();

            $this->success();
        }else{
            $this->error('修改失败');
        }
    }

    /*
     * 创建融合
     */
    public function addRh(){
        $appid = I('appid');
        $name = I('name');
        $ckey = I('ckey');
        $skey = I('skey');
        $rh = doRh($appid,$name,$ckey,$skey,1);
        $info = json_decode($rh,1);
        if($info['state'] == 1){
            $data = <<<EOF
<p>RH_AppID：$info[rh_appID]<button class="btn btn-primary rhallcopy copy" onclick="return false;" data-type="1">复制全部</button></p>
<p style="overflow: hidden;margin-bottom: 10px;">RH_ChannelID ：
    <span id="rh_channelID">$info[rh_channelID]</span>
</p>
<p style="overflow: hidden;margin-bottom: 10px;">RH_AppKey：
    <span id="rh_appKey">$info[rh_appKey]</span>
    <button class="btn btn-primary rh_appKey copy" onclick="return false;" data-type="1">复制</button>
</p>
<p style="overflow: hidden;margin-bottom: 0;">RH_AppSecret：
    <span id="rh_appSecret">$info[rh_appSecret]</span>
    <button class="btn btn-primary rh_appSecret copy" onclick="return false;" data-type="2">复制</button>
</p>
EOF;
            $this->ajaxReturn( array(
                'info' => '',
                'status'=> 1,
                'data' => $data
            ));
        }else{
            $this->error('请求失败');
        }
    }

    public function upload(){
        set_time_limit(0);
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
        $upload->rootPath = "www.sy217.com/zt/";
        $upload->savePath = "{$tag}/";
        $info = $upload->uploadOne($_FILES['upload']);

        if(is_array($info)){
            $info['root'] = C('FTP_URL');
            $info['fullpath'] = str_replace('www.sy217.com','',$info['fullpath']);
            $this->ajaxReturn($info);
        }
    }

    public function url_del(){
        $id = I('id');
        $name = I('name');
        if(M('game')->where(array('id'=>$id))->setField($name,'') !== false){
            $this->success();
        }else{
            $this->error('操作失败');
        }
    }

    public function getToken(){
        $res = curl_post(C('UPLOAD_TOKEN'),array('username'=>C('UP_USER'),'password'=>C('UP_PASS'),'sign'=>C('UP_SIGN')));
	
        $this->success(json_decode($res,1));
    }

    public function getSpuerToken(){
        $res = curl_post(C('SUPER_UP_TOKEN'),array('username'=>C('SUPER_USER'),'password'=>C('SUPER_PASS'),'sign'=>C('SUPER_SIGNS')));
      
	$this->success(json_decode($res,1));
    }

    public function getGameInfo(){

        $p = urlencode(base64_encode(I('p')));
        $res = curl_get(C('BOX_DOWNLOAD_URL').'/download/info?p='.$p);
        $res = json_decode($res,1);
        if($res['state'] == 1){
            $this->success($res);
        }else{
            $this->error('获取游戏信息失败，请自行填写');
        }
    }


//    protected function cleanCache(){
//        updateCache(C('UPDATE_CACHE_URL'),'clearCache','game','bd4db117349f0d98225fcd6188a8549e');
//    }
}