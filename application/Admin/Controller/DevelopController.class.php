<?php
/**
 * 研发游戏管理
 * User: fantasmic
 * Date: 2017/6/15
 * Time: 10:34
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class DevelopController extends AdminbaseController{

    /**
     * 游戏列表
     */
    public function index(){
        $type = I('type',-1);
        if($type != -1) $where['is_audit'] = $type;
        $name = I('name');
        $appid = I('appid');

        $gids = session('game_role');
        if($gids != 'all'){
            $where['id'] = array('in',$gids);
        }else{
            if($name) $where['game_name'] = array('like','%'.$name.'%');
            if($appid > 0) $where['id'] = $appid;
        }
        $where['status'] = 1;

        $data = M('game')->where($where)->order('create_time desc')->select();
        $count = count($data);
        $page = $this->page($count, 16);
        $data = array_slice($data,$page->firstRow, $page->listRows);

        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->name = $name;
        $this->appid = $appid;
        $this->type = $type;
        $this->display();

    }

    /**
     * 新增游戏
     */
    public function add(){
        $name = I('name');
        $uid = session('ADMIN_ID');
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
            'modifiy_time' => time(),
            'uid' => $uid
        );
        if($id = M('game')->add($data)){
            $game_role = session('game_role');

            if($uid != 1 && $game_role != 'all'){
                $role = M("userrights")->where(array('userid'=>$uid))->getField('game_role');
                $newrole = $role.','.$id;
                M('userrights')->where(array('userid'=>$uid))->setField('game_role',$newrole);
                session('game_role',$newrole);
            }
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
            $model = M('game');
            $rule = array(
                array('name', 'require', '请输入游戏名！', 0, 'regex', 2),
//                array('android_payurl', 'require', '请输入安卓支付地址！', 0, 'regex', 2),
//                array('ios_payurl', 'require', '请输入苹果支付地址！', 0, 'regex', 2),
//                array('serverurl', 'require', '请输入区服地址！', 0, 'regex', 2),
//                array('bpayurl', 'require', '请输入返利地址！', 0, 'regex', 2),
            );
            $auto = array (
                array ('modifiy_time', 'time', 2, 'function'),
                array ('uid',function(){return session('ADMIN_ID');},2,'function')
            );
            if($model->validate($rule)->create()){
                $model->auto($auto)->create();
                if($model->save()){
//                    $this->cleanCache();
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
                    ->field('g.*,gm.name pri_name')
                    ->join('left join '.C('DB_PREFIX').'gm_pri gm on gm.id=g.gm_pri_id')
                    ->where(array('g.id'=>$id,'g.status'=>1))->find();
            if(!$data){
                $this->error('错误行为');
            }else{
//                $rh = doRh($data['id'],$data['game_name'],$data['client_key'],$data['server_key'],2);
//                $rh = json_decode($rh,1);
//                if($rh['state'] == 1) $this->rh = $rh;
                $this->data = $data;
                $this->display();
            }
        }

    }

    /**
     * 删除游戏
     */
    public function del(){
        $id = I('id',0);
        if(!$id) $this->error('错误行为');
        if(M('game')->where(array('id'=>$id))->setField('status',0) !== false){
//            $this->cleanCache();
            $this->success();
        }else{
            $this->error('删除失败');
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

//    protected function cleanCache(){
//        updateCache(C('UPDATE_CACHE_URL'),'clearCache','game','bd4db117349f0d98225fcd6188a8549e');
//    }
}