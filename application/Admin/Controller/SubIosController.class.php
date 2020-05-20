<?php
/**
 * 苹果分包
 * User: fantasmic
 * Date: 2017/6/23
 * Time: 17:33
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class SubIosController extends AdminbaseController{

    public function _initialize() {
        parent::_initialize();
        $this->subpackage_ios = M("subpackage_ios");
    }

    /**
     * 列表
     */
    public function index(){
        $getcid = I('cid');
        $gid = I('gid',0);
        $access_type = I('type',0);

        if(session('channel_role') !== 'all'){
            $cids = explode(',',session('channel_role'));
            $cid = $cids[0];
        }else{
            $cid = C('MAIN_CHANNEL');
        }

        if($access_type > 0){
            $where['access_type'] = $access_type;
            $map['g.access_type'] = $access_type;
        }


        if($getcid){
            $cid = $getcid;
        }else{
            $getcid = $cid;
        }
        if($gid) {
            $where['id'] = $gid;
            $map['g.id'] = $gid;
        }
        $type = M('channel')->where(array('id'=>$getcid))->getField('type');
        if(!$type){
            $this->display();
            exit();
        }
        $where['_string'] = "(find_in_set({$type},channel)) and (is_ios_sub = 1 or ios_freesub_enabled = 1)";
        $where['status'] = 1;
        $where['is_audit'] = 1;
        $field = 'g.id,game_name,type,tag,ios_version_num version,ios_repair,abstract_url,vip_url,material_url,add_time,source,p.id pid,access_type,ios_freesub_enabled,is_ios_sub';

        //分页条件总数
        $count = M('game')->where($where)->count();
        //分包总数
        $packageWhere = $where;
        $packageWhere['is_ios_sub'] = 1;
        unset($packageWhere['id'],$packageWhere['access_type']);
        $packageCount = M('game')->where($packageWhere)->count();

        $page = $this->page($count, 20);
        $map['g.status'] = 1;
        $map['is_audit'] = 1;
        $map['_string'] =  "(find_in_set({$type},channel)) and (is_ios_sub = 1 or ios_freesub_enabled = 1)";
        $data = M('game g')
            ->field($field)
            ->join('left join __PACKAGE__ p on p.appid=g.id')
            ->where($map)
            ->limit($page->firstRow, $page->listRows)
            ->order('g.sort desc')
	        ->group('g.id')
            ->select();

        unset($map['g.id'],$map['g.access_type']);
        $map['is_ios_sub'] = 1;
        $alldata = M('game g')
            ->field($field)
            ->join('left join __PACKAGE__ p on p.appid=g.id')
            ->where($map)
            ->order('g.sort desc')
            ->group('g.id')
            ->select();

        //查询苹果盒子免分包是否开启
        $box_app_id = C('BOX_APP_ID');
        $freesub_enabled = M('game')->where(array('id'=>$box_app_id))->getfield('ios_freesub_enabled');
        if($freesub_enabled == 1)
        {
            $box_tg_url = "苹果游戏盒子免分包下载链接 ".C('FREESUB_DOWNLOAD')."/ap/{$box_app_id}/ch/".freesub_channel($cid);
        }
        else
        {
            $box_tg_url = "苹果游戏盒子免分包推广链接（必须先注册,用注册的账号进行登录）http://p.185sy.com/box_register.html?c={$cid}";
        }

        foreach ($data as $k=>&$v){
            $v['abstract_url'] = str_replace('sy217','sy218',$v['abstract_url']);
            $v['vip_url'] = str_replace('sy217','sy218',$v['vip_url']);
            $v['material_url'] = str_replace('sy217','sy218',$v['material_url']);
            $pack = $this->subpackage_ios->where(array('cid'=>$cid,'appid'=>$v['id']))->order('version desc')->find();
//            $libao = M('package')->where(array('appid'=>$v['id'],'status'=>1))->find();
            $data[$k]['libao'] = $v['pid'] ? 1 : 0;
            $v['tg_url'] = "http://m.185sy.com/index.php?g=wap&m=game&a=url&cid={$cid}&tag={$data[$k]['tag']}";


            $data[$k]['wait'] = $pack['wait'];
            switch ($pack['status']){
                case 1://分包完成的
                    if($v['version'] == $pack['version']){
                        $v['pack_version'] = $pack['version'];
                        $v['status'] = 1;
                        $v['download_url'] = str_replace('sy217.com','sy218.com',$pack['fenbao_url']);
                        $v['create_time'] = $pack['create_time'];
                        $v['modifiy_time'] = $pack['modifiy_time'];
                        $v['pid'] = $pack['id'];
                        $v['cid'] = $cid;                      
                    }else{
                        $v['pack_version'] = $v['version'];
                        $v['status'] = 0;
                        $v['download_url'] = '';
                        $v['create_time'] = '';
                        $v['modifiy_time'] = '';
                        $v['pid'] = 0;
                        $v['cid'] = $cid;
                    }

                    break;
                case -1://分包还未完成的
                    $v['pack_version'] = $pack['version'];
                    $v['status'] = -1;
                    $v['download_url'] = '';
                    $v['create_time'] = $pack['create_time'];
                    $v['modifiy_time'] = $pack['modifiy_time'];
                    $v['pid'] = $pack['id'];
                    $v['cid'] = $cid;
                    break;
                case 0://没有分包或未成功的
                    $v['pack_version'] = $pack['version'];
                    $v['status'] = 0;
                    $v['download_url'] = '';
                    $v['create_time'] = '';
                    $v['modifiy_time'] = '';
                    $v['pid'] = $pack['id'] ? $pack['id'] : 0;
                    $v['cid'] = $cid;
                    break;
                default://没有分包的
                    $v['pack_version'] = 0;
                    $v['status'] = 0;
                    $v['download_url'] = '';
                    $v['create_time'] = '';
                    $v['modifiy_time'] = '';
                    $v['pid'] = 0;
                    $v['cid'] = $cid;
                    break;
            }
            $v['fenfa_url'] = 'http://p.185sy.com/ios/'.$v['tag'].'_'.$cid.'_'.$v['version'];

        }
        $success = 0;
        foreach($alldata as $k=>$val){
            $pack = $this->subpackage_ios->where(array('cid'=>$cid,'appid'=>$val['id']))->order('version desc')->find();
            if($pack['status'] == 1) $success ++;
        }


        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->cid = $cid;
        $this->gid = $gid;
        $this->box_tg_url = $box_tg_url;
        $this->total = $packageCount;
        $this->success = $success;
        $this->type = $access_type;
        $this->display();
    }


    /**
     * 单个分包
     */
    public function subOnePackage(){
        $pid = I('pid');
        $gid = I('gid');
        $cid = I('cid');
        $ver = I('ver');
        $pack_ver = I('pack_ver');
        $tag = I('tag');
        $status = I('status');
        if($gid == '' || $cid == '' || $ver == '' || $tag == '' || $status == ''){
            $this->error('缺少关键参数');
        }
        if($status == 0) {
            /*请求接口分包*/
            if (!$pid) {
                $auto = M('channel')->where(array('id' => $cid))->getField('is_auto_fenbo');
                $data = array('tag' => $tag, 'appid' => $gid, 'cid' => $cid, 'status' => 0, 'version' => $ver, 'auto' => $auto ? $auto : 0,);
                $pid = $this->subpackage_ios->add($data);
            }
            $sdk = M('game')->where(array('id' => $gid))->getField('source');

            $packOne = packOne(2, $tag, $cid, $ver, $gid, $sdk);
            $packOne = json_decode($packOne, 1);
            if ($packOne['state'] == 1) {
                $this->subpackage_ios->where(array('id' => $pid))->setField(array('version' => $ver, 'wait' => $packOne['position'], 'status' => -1, 'create_time' => time(), 'modifiy_time' => time()));

                $this->success('正在出包中,请稍后刷新进度');
            }elseif($packOne['state'] == 6){
                $this->subpackage_ios->where(array('id' => $pid))->setField(array('version' => $ver, 'fenbao_url' => $packOne['url'], 'status' => 1, 'create_time' => time(), 'modifiy_time' => time()));
                $this->success('分包完成');
            }else{
                $this->error(getPackError($packOne['state']));
            }
        }else{
            $this->success('现在已经是最新版本！');
        }
    }

    /**
     * 修复
     */
    public function reTry(){
        $pid = I('pid');
        $gid = I('gid');
        $cid = I('cid');
        $ver = I('ver');
        $pack_ver = I('pack_ver');
        $tag = I('tag');
        $status = I('status');
        if($gid == '' || $cid == '' || $ver == '' || $tag == '' || $status == ''){
            $this->error('缺少关键参数');
        }
        $sdk = M('game')->where(array('id'=>$gid))->getField('source');
        $tryOne = reTryOne($tag,$cid,$ver,$gid,$sdk);
        $tryOne = json_decode($tryOne, 1);
        if($tryOne['state'] == 1){
            $this->subpackage_ios->where(array('id'=>$pid))->setField(array('wait'=>$tryOne['position'],'status'=>-1,'modifiy_time'=>time()));

            $this->success('正在出包中,请稍后刷新进度');
        }else{
            $this->error(getPackError($tryOne['state']));
        }
    }

    /**
     * 批量修复
     */
    public function subAllRetry(){
        $this->subAllPackage();
    }

    /**
     * 批量分包
     */
    public function subAllPackage(){
//        $gids = I('gids');
        $cid = I('cid');
        $packtype = I('type',1);//1分包2修复
        $access_type = I('access_type');

        if(empty($cid)) $this->error('错误请求');

        $type = M('channel')->where(array('id'=>$cid))->getField('type');
        $where = array(
            'is_ios_sub' => 1,
            'is_audit' => 1,
            'status' => 1,
            '_string' => "find_in_set({$type},channel)"
        );
        if($access_type > 0) $where['access_type'] = $access_type;
        $games = M('game')->where($where)->select();

        $auto = M('channel')->where(array('id'=>$cid))->getField('is_auto_fenbo');
        /*组装数据*/
        foreach ($games as $k=>$v){
            $pack = $this->subpackage_ios->where(array('appid'=>$v['id'],'cid'=>$cid))->order('id desc')->find();
            if($packtype == 1){
                if(!$pack || $pack['version'] < $v['ios_version_num'] ){
                    $info[] = array(
                        'tag' => $v['tag'],
                        'appid' => $v['id'],
                        'cid' => $cid,
                        'version' => $v['ios_version_num'],
                        'status' => 0,
                        'auto' => $auto ? $auto : 0,
                    );
                    $data[] = array(
                        'appid' => $v['id'],
                        'tag' => $v['tag'],
                        'channel' => $cid,
                        'version' => $v['ios_version_num'],
                        'sdk' => $v['source']
                    );
                }else{
                    if($pack['status'] == 0){
                        $data[] = array(
                            'appid' => $v['id'],
                            'tag' => $v['tag'],
                            'channel' => $cid,
                            'version' => $v['ios_version_num'],
                            'sdk' => $v['source']
                        );
                    }

                }
            }else{
                $data[] = array(
                    'appid' => $v['id'],
                    'tag' => $v['tag'],
                    'channel' => $cid,
                    'version' => $v['ios_version_num'],
                    'sdk' => $v['source']
                );
            }

        }

        if($info){
            $this->subpackage_ios->addAll($info);
        }

        /*请求分包接口*/
        if($data){
            if($packtype == 1){
                $res = packAll(2,$data);
            }else{
                $res = packAll(2,$data,2);
            }

            $res = json_decode($res,1);
            if($res['state'] == 1){
                foreach ($res['data'] as $k=>$v){
                    if($v['pState'] == 1 || $v['pState'] == 5){
                        $this->subpackage_ios->where(array('appid'=>$v['appid'],'cid'=>$v['channel'],'version'=>$v['version']))->setField(array('wait'=>$v['position'],'status'=>-1,'create_time'=>time(),'modifiy_time'=>time()));
                    }
                }
                $this->success('操作完成，请稍后查询进度');
            }else{
                $this->error(getPackError($res['state']));
            }
        }else{
            $this->success('所有版本均已是最新');
        }

    }

    /**
     * 查询分包进度
     */
    public function subProgress(){
        $pid = I('pid');
        $cid = I('cid');
        $tag = I('tag');
        $gid = I('gid');
        $ver = I('ver');
        if(empty($pid) || empty($cid) || empty($ver) || empty($tag) || empty($gid)){
            $this->error('缺少关键参数');
        }
        $sdk = M('game')->where(array('id'=>$gid))->getField('source');
        $checkOne = checkOne(2,$tag,$cid,$ver,$gid,$sdk);
        $checkOne = json_decode($checkOne, 1);
        if($checkOne['state'] == 6){
            $this->subpackage_ios->where(array('id'=>$pid))->setField(array('wait'=>0,'version'=>$ver,'status'=>1,'fenbao_url'=>$checkOne['url']));
//            $this->cleanCache();
            $this->success('分包完成');
        }elseif($checkOne['state'] == 5){
            $this->subpackage_ios->where(array('id'=>$pid))->setField(array('wait'=>$checkOne['position']));
            $this->success('刷新成功');
        }else{
            $this->error('正在出包中,请稍后刷新进度');
        }
    }

    /**
     * 批量查询分包进度
     */
    public function subAllProgress(){
        $gids = I('gids');
        $cid = I('cid');
        if(!is_array($gids) && empty($cid)) $this->error('错误请求');
        $gids = implode(",",$gids);
        $packs = $this->subpackage_ios->where(array('appid'=>array('in',$gids),'cid'=>$cid,'status'=>-1))->select();
        foreach ($packs as $k=>$v){
            $data[] = array(
                'appid' => $v['appid'],
                'tag' => $v['tag'],
                'channel' => $cid,
                'version' => $v['version'],
                'sdk' => M('game')->where(array('id'=>$v['appid']))->getField('source')
            );
        }

        if($data){
            $res = checkAll(2,$data);
            $res = json_decode($res,1);
            if($res['state'] == 1){
                foreach ($res['data'] as $k=>$v){
                    $where['appid'] = $v['appid'];
                    $where['cid'] = $v['channel'];
                    $where['version'] = $v['version'];
                    if($v['pState'] == 6){
                        $this->subpackage_ios->where($where)->setField(array('wait'=>0,'status'=>1,'fenbao_url'=>$v['url']));
                    }elseif ($v['pState'] == 5){
                        $this->subpackage_ios->where($where)->setField(array('wait'=>$v['position']));
                    }
                }
                $this->success('刷新成功');
            }else{
                $this->error(getPackError($res['state']));
            }
        }
//        $this->cleanCache();
        $this->success('刷新成功');
    }

    public function ajaxCheckUrl(){
        $url = I('url');
        checkUrl($url) ? $this->success(): $this->error('数据正在上传中，请稍后下载');
    }



//    protected function cleanCache(){
//        updateCache(C('UPDATE_CACHE_URL'),'clearCache','subpack','cd32e674993f5b43425e860774ff589c');
//    }
}