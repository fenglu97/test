<?php
/**
 * 游戏列表
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class PromoterController extends AdminbaseController{

    public function game(){

        $channel_role = session('channel_role');


        if($channel_role == 'all') {
            $default_channel = C('MAIN_CHANNEL');
        } else {
            $channel_role = explode(',',$channel_role);
            $default_channel = $channel_role[0];
        }

        $cid = I('cid',$default_channel);
        $game_type = I('game_type',0);
        $gid = I('gid');
        $access_type = I('access_type',0);

        $admin_id = session('ADMIN_ID');
        $userright = M('userrights')->where(array('userid' => $admin_id))->getField('game_role');


        $cid_type = M('channel')->where(array('id'=>$cid))->getField('type');

        if($gid){
            $map['g.id'] = $gid;
            $where['id'] = $gid;
        }else{
            if($userright != 'all'){
                $map['g.id'] = array('in',$userright);
                $where['id'] = array('in',$userright);
            }else{
                $map['g.id'] = array('neq','1000');
                $where['id'] = array('neq','1000');
            }

        }
        if($game_type > 0){
            $map['g.game_type'] = $game_type;
            $where['game_type'] = $game_type;
        }

        if($access_type) {
            $map['g.game_platform'] = $access_type;
            $where['game_platform'] = $access_type;
        }

        $map['g.online'] = 1;
        $map['g.is_audit'] = 1;
        $map['g.status'] = 1;
        $where['online'] = 1;
        $where['is_audit'] = 1;
        $where['status'] = 1;

        $count = M('game')->where($where)->count();

        $page = $this->page($count,20);

        $data = M('game g')->field('g.*,p.id pid')->join('left join __PACKAGE__ p on p.appid=g.id')->where($map)->limit($page->firstRow,$page->listRows)->order('g.id desc')->select();

        $types = M('game_type','syo_',C('185DB'))->field('id,name')->select();
        $platform = C('PLATFORM');

        foreach($data as $k=>$v){
            $channel = explode(',',$v['channel']);
            if(in_array($cid_type,$channel)){
                $data[$k]['role'] = 1;
            }else{
                $data[$k]['role'] = 0;
            }
            $data[$k]['game_type'] = $platform[$v['game_type']];
            $info = get_185_gameinfo($v['tag']);

            $data[$k]['types'] = getTypes($info['tid'],$types);
            $data[$k]['logo'] = C('CDN_URL').$info['logo'];
            $data[$k]['system'] = $info['system'];
            if($info['system'] == 'a'){
                $data[$k]['client'] = 'Android';
            }elseif($info['system'] == 'i'){
                $data[$k]['client'] = 'Ios';
            }else{
                $data[$k]['client'] = 'Android/Ios';
            }
            $tag = urlencode(base64_encode($data[$k]['tag']));
            $data[$k]['tg_url'] = C('box_m_url')."/wap/{$cid}/{$tag}";
            if($info['tg_pic']){
                $data[$k]['new_tg_url'] = C('box_m_url')."/tg/{$cid}/{$tag}";
            }

            if($v['android_url']){
                $p = urlencode(base64_encode($v['android_url']));
                $data[$k]['android_url'] = C('BOX_DOWNLOAD_URL')."/download/parse?p={$p}&ext={$cid}-0&type=1&v={$v['android_version_num']}";
            }
            if($v['ios_url']){
                $p = urlencode(base64_encode($v['ios_url']));
                $data[$k]['ios_url'] = C('BOX_DOWNLOAD_URL')."/download/parse?p={$p}&ext={$cid}-0&type=3&v={$v['ios_version_num']}";
            }
            $data[$k]['libao'] = $v['pid'] ? 1 : 0;
        }

    	$this->page = $page->show('Admin');
        $this->game_type = $game_type;
        $this->platform = C('PLATFORM');
        $this->data = $data;
        $this->cid = $cid;
        $this->gid = $gid;
        $this->display();


    }

    public function material(){
        $tag = I('tag');

        $info = M('game','syo_',C('185DB'))->where(array('tag'=>$tag))->field('id,gamename,abstract,size,logo,pic1,pic2,pic3,pic4,pic5')->find();

        $info['abstract'] = html_entity_decode($info['abstract']);

        $game_link_types = M('game_link_type','syo_',C('185DB'))->where(array('gid'=>$info['id']))->getfield('tid',true);

        $game_types = M('game_type','syo_',C('185DB'))->where(array('id'=>array('in',implode(',',$game_link_types))))->getfield('name',true);

        $this->material_url = M('game')->where(array('tag'=>$tag))->getfield('material_url');
        $this->game_types = implode(',',$game_types);
        $this->url_185 = C('CDN_URL');
        $this->info =$info;
        $this->display();
    }

    /**
     * 开服表
     */
    public function open_server(){
        $game_id = I('game_id');
        $server_id = I('server_id');
        $type_day = I('type_day');
        if(empty($type_day)){
            $type_day = 0;
        }
        if($type_day == 0 ){  //今日开服
            $beginToday = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $endToday = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;

        }
        else{    //明日开服
            $beginToday = mktime(0,0,0,date('m'),date('d')+1,date('Y'));
            $endToday = mktime(0,0,0,date('m'),date('d')+2,date('Y'))-1;
        }
        $map['s.start_time'] =  array(array('egt',$beginToday),array('elt',$endToday)) ;
        if($game_id){
            $map['s.game_id'] = $game_id;
        }
        if($server_id){
            $map['s.server_id'] = $server_id;
        }
        $field = 's.id,s.line,s.server_name,s.server_id,s.start_time,s.game_id,g.gamename,g.platform';
        $map['s.status'] = 0;
        $map['s.is_display'] = 1;

        $count = M('server s','syo_',C('185DB'))
            ->join('left join syo_game g on g.id=s.game_id')
            ->where($map)
            ->count();
        $page = $this->page($count, 20);

        //  C('185DB')
        //C('185_DB')
        $data = M('server s','syo_',C('185DB'))
            ->join('left join syo_game g on g.id=s.game_id')
            ->field($field)
            ->where($map)
            ->order('s.start_time asc')
            ->limit($page->firstRow.','.$page->listRows)
            ->select();

        $this->lists = $data;
        $this->page = $page->show('Admin');
        $this->platform = C('PLATFORM');
        $this->game_id = $game_id;
        $this->server_id = $server_id;
        $this->type_day = $type_day;
        $this->display();
    }

    /**
     * 某游戏今日之后的所有开服信息
     */
    public function server_list_by_game(){
        $game_id = I('game_id');
        $field = 's.id,s.line,s.server_name,s.server_id,s.start_time,s.game_id,g.gamename,g.platform';
        $map['s.status'] = 0;
        $map['s.is_display'] = 1;
        $map['s.game_id'] = $game_id;
        $beginToday = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $map['s.start_time'] =  array(array('egt',$beginToday)) ;

        $count = M('server s','syo_',C('185DB'))
            ->join('left join syo_game g on g.id=s.game_id')
            ->where($map)
            ->count();
        $page = $this->page($count, 20);

        $data = M('server s','syo_',C('185DB'))
            ->join('left join syo_game g on g.id=s.game_id')
            ->field($field)
            ->where($map)
            ->limit($page->firstRow.','.$page->listRows)
            ->select();
        $this->lists = $data;
        $this->page = $page->show('Admin');
        $this->display();
    }

    /**
     * 暂时无用，用来测试一些数据
     */
    public function open_server_web(){
        //http://www.newbisdk.com/index.php?g=&m=Promoter&a=open_server_web
        $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
        $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
        //echo $beginToday.'----'.$endToday;
        //$pwd = '000000';
        //$salt = 'vzUZyn';
        //$str = sp_password_by_player($pwd,$salt);
        $m = md5('uid=4153651709931298992c123ba79f9394032e91e');
        echo $m;

    }




}
