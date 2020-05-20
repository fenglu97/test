<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;
use Think\Db;

class PlayerController extends AdminbaseController{

	protected $player_model,$player_info_model,$games_model,$channel_model,$player_closed_model,$player_remark_model,$player_machine_model;

	public function _initialize() {
		parent::_initialize();
		$this->player_model = D("Common/Player");
		$this->games_model = D("Common/Game");
		$this->channel_model = D("Common/Channel");
		$this->player_info_model = D("Common/Player_info");
		$this->player_closed_model = D("Common/Player_closed");
		$this->player_remark_model = D("Common/Player_remark");
		$this->player_machine_model = D("Common/Player_machine");
	}

	// 玩家列表
	public function index(){
		$where['status']=array('NEQ',0);
		/**搜索条件**/
		$player_login = I('request.user_login');
		$order = '';
		if($player_login){
			$where['username'] = array('like',"%$player_login%");
			$order = 'length(username) asc,';
		}
		$player_mobile = trim(I('request.user_mobile'));
		if($player_mobile){
			$where['mobile'] = array('like',"%$player_mobile%");
		}
		$player_appid = trim(I('request.appid'));
		if($player_appid){
			$where['appid'] = (int)$player_appid;
		}else{
			if(session('game_role') != 'all'){
				$where['appid'] = array('in',session('game_role'));
			}
		}
		$player_cid = trim(I('request.cid'));
		if($player_cid){
			$where['channel'] = (int)$player_cid;
		}else{
			if(session('channel_role') != 'all'){
				$where['channel'] = array('in',session('channel_role'));
			}
		}
		$player_status = trim(I('request.status'));
//		if($player_status){
//			$where['status']=(int)$player_status;
//		}

		$start_time=I('request.start_time',date('Y-m-d',strtotime('-1 month')));
		if(!empty($start_time)){
			$where['create_time']=array(
				array('EGT',strtotime($start_time.' 00:00:00'))
			);
		}

		$end_time=I('request.end_time',date('Y-m-d'));
		if(!empty($end_time)){
			if(empty($where['create_time'])){
				$where['create_time']=array();
			}
			array_push($where['create_time'], array('ELT',strtotime($end_time.' 23:59:59')));
		}

		$regip = I('request.regip');
		if(!empty($regip))
		{
			$where['regip'] = ip2long($regip);
		}

		$machine_code = I('request.machine_code');

		if(!empty($machine_code))
		{
			$where['machine_code'] = $machine_code;
		}


		$count=$this->player_model->where($where)->count();
		$page = $this->page($count, 20);
		$player = $this->player_model
			->field('id,username,mobile,appid,channel,count,source,regip,regtime,status,system,machine_code,referee_uid,promoter_uid,rule')
			->where($where)
			->order($order."create_time DESC")
			->limit($page->firstRow, $page->listRows)
			->select();

		$referee_uid_ids = '';
		$promoter_uids= '';

		foreach($player as $k => $v)
		{
			$referee_uid_ids.=$v['referee_uid'].',';
			$promoter_uids.=$v['promoter_uid'].',';
			// 判断账号是否被禁言
            $closedAccount = M('player_closed')->where(array('uid'=>$v['id']))->order('end_time')->find();
            if($closedAccount && ($closedAccount['end_time'] > time())) {
                $player[$k]['status'] = 2;
            }
            if($player_status == 1) {
                if($player[$k]['status'] == 2) {
                    unset($player[$k]);
                }
            }else if($player_status == 2){
                if($player[$k]['status'] == 1) {
                    unset($player[$k]);
                }
            }
		}

		$referee_uid_ids = trim($referee_uid_ids,',');
		$promoter_uids = trim($promoter_uids,',');

		$player_names = $this->player_model->where(array('id'=>array('in',$referee_uid_ids)))->getfield('id,username',true);

		$admin_user_logins = M('users')->where(array('id'=>array('in',$promoter_uids)))->getfield('id,user_login',true);

		$games_src=$this->games_model->field('id,game_name')->where(array())->select();
		$games=array();
		foreach($games_src as $g){
			$gameid=$g['id'];
			$games["$gameid"]=$g;
		}
		$channels_src=$this->channel_model->field('id,name')->where(array())->select();
		$channels=array();
		foreach($channels_src as $c){
			$channelid=$c['id'];
			$channels["$channelid"]=$c;
		}

		$this->assign('start_time',$start_time);
		$this->assign('end_time',$end_time);
		$this->assign('player_names',$player_names);
		$this->assign('admin_user_logins',$admin_user_logins);
		$this->assign("page", $page->show('Admin'));
		$this->assign("games",$games);
		$this->assign("channels",$channels);
		$this->assign("player",$player);

		$this->display();
	}

	//玩家信息
	public function userInfo(){
		$uid=  I("get.uid",0,'intval');
		/*
		if(empty($where['uid'])){
			$this->error('用户ID不能为空！');
		}
		*/
		$player=$this->player_model->field('username')->where(array('id'=>$uid))->find();
		$userInfo=$this->player_info_model->where(array('uid'=>$uid))->find();
		$userInfo['username']=$player['username'];
		$this->assign("userInfo",$userInfo);
		$this->display();
	}

	//玩家信息修改
	public function update(){
		$uid=  I("get.uid",0,'intval');
		/*
		if(empty($where['uid'])){
			$this->error('用户ID不能为空！');
		}
		*/
		$player=$this->player_model->field('mobile')->where(array('id'=>$uid))->find();
		$this->assign($player);
		$this->display();
	}

	// 密码修改提交
	public function password_post(){
		if (IS_POST) {
			if(empty($_POST['password'])){
				$this->error("新密码不能为空！");
			}

			$uid=  I("get.uid",0,'intval');
			/*
			if(empty($where['uid'])){
				$this->error('用户ID不能为空！');
			}
			*/
			$player=$this->player_model->where(array("id"=>$uid))->find();
			$password=I('post.password');
			$mobile=I('post.mobile');
			if(!empty($mobile))
			{
				$mobile_count=$this->player_model->where(array("mobile"=>$mobile,'id'=>array('neq',$uid)))->count();
				if(!empty($mobile_count)){
					$this->error("手机号已经存在！");
				}
				$data['mobile']=$mobile;
			}


			if($password==I('post.repassword')){

				$data['password']=md5(md5($password).$player['salt']);
				$data['id']=$uid;
				$r=$this->player_model->save($data);
				if ($r!==false) {
					//updateCache(C('UPDATE_CACHE_URL'),'clearCache','player','3e0f65282d1b601e1f07cd9b2384f79a');
					$this->success("修改成功！");
				} else {
					$this->error("修改失败！");
				}
			}else{
				$this->error("密码输入不一致！");
			}
		}
	}



	//玩家登陆日志
	public function logEntry(){

		/**搜索条件**/
		$where['uid']=I("get.uid",0,'intval');
		/*
		if(empty($where['uid'])){
			$this->error('用户ID不能为空！');
		}
		*/
		$player_appid = trim(I('request.appid'));
		if($player_appid){
			$where['appid'] = (int)$player_appid;
		}

		$player_cid = trim(I('request.cid'));
		if($player_cid){
			$where['channel'] = (int)$player_cid;
		}

		$player_system = trim(I('request.system'));
		if($player_system){
			$where['system'] = (int)$player_system;
		}

		$start_time = I('start_time')?I('start_time'):date('Ym',time());

		$player_login_model = M('player_login_logs'.$start_time);

		$count=$player_login_model->where($where)->count();
		$page = $this->page($count, 20);
		$player = $player_login_model
			->field('id,username,appid,channel,system,ip,create_time,machine_code,app_uid')
			->where($where)
			->order("create_time DESC")
			->limit($page->firstRow, $page->listRows)
			->select();

		$games_src=$this->games_model->field('id,game_name')->where(array())->select();
		$games=array();
		foreach($games_src as $g){
			$gameid=$g['id'];
			$games["$gameid"]=$g;
		}
		$channels_src=$this->channel_model->field('id,name')->where(array())->select();
		$channels=array();
		foreach($channels_src as $c){
			$channelid=$c['id'];
			$channels["$channelid"]=$c;
		}
		$this->assign("page", $page->show('Admin'));
		$this->assign("games",$games);
		$this->assign("channels",$channels);
		$this->assign("player",$player);
		$this->assign("uid",$where['uid']);
		$this->assign('start_time',$start_time);
		$this->display();
	}
	//查封帐号列表
	public function closingRecord(){

	    $uid = I('get.uid',0,'intval');

	    $where = array();
	    if($uid) {
	        $where['uid'] = $uid;
        }
        $userData = $this->player_closed_model
            ->alias('a')
            ->field('a.*,b.username,b.id as userId,b.status')
            ->join('left join bt_player as b on a.uid = b.id')
            ->where($where)
            ->order('a.end_time DESC')
            ->select();
        $this->assign('data',$userData);
        $this->assign('uid',$uid);
        $this->display();
	}
	//查封设备列表
	public function closingMachine(){
		$machine = trim(I('request.machine'));
		if($machine){
			$where['machine_code'] = $machine;
		}
		$user = trim(I('request.username'));
		if($user){
			$user_data=M('users')->field('id')->where(array('user_login'=>$user))->find();
			$where['operator'] = (int)$user_data['id'];
		}
		$roleid = trim(I('request.roleid'));
		if($roleid){
			$where['roleid'] = (int)$roleid;
		}
		$deleted = trim(I('request.deleted'));
		if(is_numeric($deleted)){
			$where['status'] = (int)$deleted;
		}


		$start_time=strtotime(trim(I('request.start_time')));
		if(!empty($start_time)){
			$where['end_time']=array(
				array('EGT',$start_time)
			);
		}

		$end_time=strtotime(trim(I('request.end_time')));
		if(!empty($end_time)){
			if(empty($where['end_time'])){
				$where['end_time']=array();
			}
			array_push($where['end_time'], array('ELT',$end_time));
		}

		$roles=M('role')->where(array('status' => 1))->order("id DESC")->select();
		$this->assign("roles",$roles);

		$count=$this->player_machine_model->where($where)->count();
		$page = $this->page($count, 20);
		$closingMachine = $this->player_machine_model
			->where($where)
			->order("create_time DESC")
			->limit($page->firstRow, $page->listRows)
			->select();

		$this->assign("closingMachine",$closingMachine);
		$this->display();
	}

	public function closeMachine() {
	    $id = I('id',0,'intval');
        $admin = session('ADMIN_ID');

	    if(IS_POST) {

	        $param = I('post.');
	        if(!$param['machine_code'])
	            $this->error('设备号不能为空');

	        if(strtotime($param['end_time']) < time())
	            $this->error('结束时间必须大于当前时间');

            $param['end_time'] = strtotime($param['end_time']);

	        if($param['id']) { // 有ID的情况为修改
	            $res = $this->player_machine_model
                    ->where(array('id'=>$param['id']))
                    ->setField($param);
	            if($res) {
	                $this->success('修改成功',U('closingMachine'));
                }
	            $this->error('修改失败');
            }

	        $param['create_time'] = time();
	        $param['roleid'] = $admin;
	        $param['operator'] = $admin;

	        $res = $this->player_machine_model->add($param);
	        if($res) {
                $this->success('添加成功',U('closingMachine'));
            }
            $this->error('添加失败');
        }else{
	        $where = array();
	        $data  = array();
	        if($id) {
	            $where['id'] = $id;
                $data = $this->player_machine_model
                    ->where($where)
                    ->find();
            }
            $this->assign('data',$data);
            $this->display();
        }

    }

	//玩家查封帐号
	public function closingAccount(){

		$uid=I("get.uid",0,'intval');
		$id=I("get.id",0,'intval');
		$text='添加';
		$closing=array();
		if($uid){    // 传UID 是账号信息页面，是添加封号
			$where['uid']=$uid;
			$player=$this->player_model->field('username,machine_code')->where(array('id'=>$where['uid']))->find();
		}else if($id){  // 传ID 是封号记录页面 是修改封号
				$text='修改';
				$where['id']=$id;
				$closing=$this->player_closed_model->where($where)->find();
				$player = $this->player_model
                    ->where(array('id'=>$closing['uid']))
                    ->find();
				$uid = $closing['uid'];
		}
		$this->assign("closing",$closing);
		$this->assign("text",$text);
		$this->assign("player",$player);
		$this->assign('uid',$uid);
		$this->display();
	}

	//修改帐号查封ajax
	public function closingAjax(){
		$uid=I("get.uid",0,'intval');
		$id=I("get.id",0,'intval');
		$type=I("get.type",0,'intval');
		$closing=array();
		if($uid){		//用户名封号
			$where['uid']=$uid;
			$player=$this->player_model->field('username,machine_code')->where(array('id'=>$where['uid']))->find();
			$tem_data=$this->player_machine_model->where(array('machine_code'=>$player['machine_code'],'status'=>0))->find();
			//if(!empty($tem_data['end_time'])&&$tem_data['end_time']>time()){
			if($type==2){ //设备封号
				$where['id']=$tem_data['id'];
				//if(!empty($tem_data['end_time'])&&$tem_data['end_time']>time()){
				if(!empty($tem_data['end_time'])){
					$closing=$this->player_machine_model->where($where)->find();
				}
			}
			if($type==1){
				//帐号封号
				if($id){
					$where['id']=$id;
					$closing=$this->player_closed_model->where($where)->find();
				}
			}
			if(!empty($closing)){
				$closing['status'] = 1;
				$closing['end_time'] = date('Y-m-d H:i',$closing['end_time']);
			}else{
				$closing['status'] = 1;
				$closing['end_time'] =date('Y-m-d H:i');
			}

			$this->ajaxReturn($closing);
		}else{
			$closing['status'] = 0;
			$closing['end_time'] =date('Y-m-d H:i');
			$this->ajaxReturn($closing);
		}

	}

	//封号添加与修改
	public function closingAdd(){
		if (IS_POST) {
			$where['id']=I("id",0,'intval');
			$where['uid']=I("uid",0,'intval');
			$type=I("post.type",0,'intval');
            $param = I('post.');

            if(!$param['user_login']) {
                $this->error('用户名不能为空');
            }else {
                $player = $this->player_model->where(array('username' => $param['user_login']))->find();
                if($player) {
                    $data['uid'] = $player['id'];
                    $data['username'] = $player['username'];
                }else {
                    $this->error('该账号不存在');
                }
            }

			$data['remark']=I('post.remark');
			$data['end_time']=strtotime(I('post.end_time'));
			$data['operator']=(int)$_SESSION['ADMIN_ID'];

			$role_temp=M('role_user')->where(array('user_id'=>$data['operator']))->find();
			if($data['operator']==1){
				$data['roleid']=1;
			}else{
				if(empty($role_temp['role_id'])){
					$this->error('非法权限！');
				}else{
					$data['roleid']=(int)$role_temp['role_id'];
				}
			}

			if(empty($where['id'])||$where['id']==0){	//添加
				$data['create_time']=time();
				if($type==1){
					unset($data['roleid']);
					//用户名查封
					if ($this->player_closed_model->create($data)!==false) {
						if ($this->player_closed_model->add($data)!==false) {
							if($data['end_time']>time()){
								$this->success('添加成功!',U('closingRecord',array('uid'=>$data['uid'])));
							}
						} else {
							$this->error("添加失败！");
						}
					} else {
						$this->error($this->player_closed_model->getError());
					}
				}
				if($type==2){
					$data['machine_code']=I('post.machine');
					//机器码查封
                    if(empty($data['machine_code'])) {
                        $this->error('设备号不能为空');
                    }
					$where_count=array('machine_code'=>$data['machine_code'],'status'=>0);
					$where_count['end_time']=array('gt',time());
					$count=$this->player_machine_model->where($where_count)->count();
					if(empty($count)){
						if($data['end_time']<time()){
							$data['status']=1;
						}
						if ($this->player_machine_model->create($data)!==false) {
							if ($id=$this->player_machine_model->add($data)!==false) {
								if(!empty($where['uid'])){
									$this->success("添加成功！",U("Player/closingAccount",array('id'=>$id,'uid'=>$where['uid'])));
								}else{
									$this->success("添加成功！",U("Player/closingAccount",array('id'=>$id)));
								}

							} else {
								$this->error("添加失败！");
							}
						} else {
							$this->error($this->player_machine_model->getError());
						}
					}else{
						$this->error("此设备已查封！");
					}
				}
			}else{
				//修改用户名查封
				$data['modifiy_time']=time();
				if($type==1){
					$data['uid']=$where['uid'];
					unset($data['roleid']);
					if ($this->player_closed_model->where($where)->create($data)!==false) {

						if ($this->player_closed_model->where($where)->save($data)!==false)
						{

							$this->success("保存成功！", U("Player/closingAccount",array('id'=>$where['id'],'uid'=>$where['uid'])));

						} else {
							$this->error("保存失败！");
						}
					} else {
						$this->error($this->player_machine_model->getError());
					}
				}
				//修改机器码查封
				if($type==2){
					$data['machine_code']=I('post.machine');
					$count=$this->player_machine_model->where(array('machine_code'=>$data['machine_code'],'status'=>0))->find();
					if($data['end_time']<time()){
						$data['status']=1;
					}else{
						$data['status']=0;
					}
					if(empty($count)){
						$data['create_time']=time();
						unset($data['modifiy_time']);
						if ($this->player_machine_model->create($data)!==false) {
							if ($id=M('player_machine')->add($data)) {
								if(!empty($where['uid'])){
									$this->success("添加成功！",U("Player/closingAccount",array('id'=>$id,'uid'=>$where['uid'])));
								}else{
									$this->success("添加成功！",U("Player/closingAccount",array('id'=>$id)));
								}

							} else {
								$this->error("添加失败！");
							}
						} else {
							$this->error($this->player_machine_model->getError());
						}
					}else{
						if ($this->player_machine_model->where(array('machine_code'=>$data['machine_code'],'status'=>0))->create($data)!==false) {
							if ($this->player_machine_model->where(array('machine_code'=>$data['machine_code'],'status'=>0))->save($data)!==false) {
								if(!empty($where['uid'])){
									$this->success("保存成功！", U("Player/closingAccount",array('id'=>$count['id'],'uid'=>$where['uid'])));
								}else{
									$this->success("保存成功！", U("Player/closingAccount",array('id'=>$count['id'])));
								}

							} else {
								$this->error("保存失败！");
							}
						} else {
							$this->error($this->player_machine_model->getError());
						}
					}
				}
			}
		}
	}

	//玩家充值记录
	public function playerPayRecord(){
		/**搜索条件**/
		$where['uid']=I("get.uid",0,'intval');
		/*
		if(empty($where['uid'])){
			$this->error('用户ID不能为空！');
		}
		*/
		$player_appid = trim(I('request.appid'));
		if($player_appid){
			$where['appid'] = (int)$player_appid;
		}

		$player_cid = trim(I('request.cid'));
		if($player_cid){
			$where['cid'] = (int)$player_cid;
		}
		// $player_system = trim(I('request.system'));
		// if($player_system){
		// 	$where['system'] = (int)$player_system;
		// }

		$start_time=strtotime(trim(I('request.start_time')));
		if(!empty($start_time)){
			$where['pay_to_time']=array(
				array('EGT',$start_time)
			);
		}

		$end_time=strtotime(trim(I('request.end_time')));
		if(!empty($end_time)){
			if(empty($where['pay_to_time'])){
				$where['pay_to_time']=array();
			}
			array_push($where['pay_to_time'], array('ELT',$end_time));
		}
		$where['status'] = 1;
		$count=M('inpour')->where($where)->count();
		//echo M('inpour')->getLastSql();
		//exit;
		$page = $this->page($count, 20);
		$player = M('inpour')
			->field('id,orderid,payType,appid,cid,money,deviceType,ip,pay_to_time,app_uid')
			->where($where)
			->order("pay_to_time DESC")
			->limit($page->firstRow, $page->listRows)
			->select();

		$games_src=$this->games_model->field('id,game_name')->where()->select();
		$games=array();
		foreach($games_src as $g){
			$gameid=$g['id'];
			$games["$gameid"]=$g;
		}
		$channels_src=$this->channel_model->field('id,name')->where()->select();
		$channels=array();
		foreach($channels_src as $c){
			$channelid=$c['id'];
			$channels["$channelid"]=$c;
		}
		$this->assign("page", $page->show('Admin'));
		$this->assign("games",$games);
		$this->assign("channels",$channels);
		$this->assign("player",$player);
		$this->assign("uid",$where['uid']);
		$this->display();
	}

	//玩家记录备注
	public function remark(){
		$where['uid']=I("get.uid",0,'intval');
		/*
		if(empty($where['uid'])){
			$this->error('用户ID不能为空！');
		}else{
			
		}
		*/
		$user = trim(I('request.username'));
		if($user){
			$user_data=M('users')->field('id')->where(array('user_login'=>$user))->find();
			$where['operator'] = (int)$user_data['id'];
		}
		$roleid = trim(I('request.roleid'));
		if($roleid){
			$where['roleid'] = (int)$roleid;
		}
		$follow = trim(I('request.follow'));
		if(is_numeric($follow)){
			$where['follow'] = (int)$follow;
		}
		$deleted = trim(I('request.deleted'));
		if(is_numeric($deleted)){
			$where['status'] = (int)$deleted;
		}


		$start_time=strtotime(trim(I('request.start_time')));
		if(!empty($start_time)){
			$where['add_time']=array(
				array('EGT',$start_time)
			);
		}

		$end_time=strtotime(trim(I('request.end_time')));
		if(!empty($end_time)){
			if(empty($where['add_time'])){
				$where['add_time']=array();
			}
			array_push($where['add_time'], array('ELT',$end_time));
		}
		$roles=M('role')->where(array('status' => 1))->order("id DESC")->select();
		$this->assign("roles",$roles);
		$username=$this->player_model->field('username')->where(array('id'=>$where['uid']))->find();
		$remark_data=$this->player_remark_model->where($where)->select();
		$this->assign("username",$username['username']);
		$this->assign("remark_data",$remark_data);
		$this->display();
	}

	//玩家记录备注编辑
	public function playerRecordEdit(){
		$where['uid']=I("get.uid",0,'intval');
		/*
		if(empty($where['uid'])){
			$this->error('用户ID不能为空！');
		}else{
			
		}
		*/
		$username=$this->player_model->field('username')->where(array('id'=>$where['uid']))->find();
		$where['id']=I("get.id",0,'intval');

		if(empty($where['id'])||$where['id']==0){
			$text='添加';
			$closing=array();
		}else{
			$text='修改';
			$remark=M('player_remark')->where($where)->find();
			$id=$where['id'];
		}

		$this->assign("id",$id);
		$this->assign("remark",$remark);
		$this->assign("text",$text);
		$this->assign("username",$username);
		$this->display();
	}

	// 玩家记录备注提交
	public function playerRecordPost(){
		if (IS_POST) {$data=I('post');
			$where['id']=I("get.id",0,'intval');
			$where['uid']=I("get.uid",0,'intval');
			/*
			if(empty($where['uid'])){
				$this->error('用户ID不能为空！');
			}else{
				
			}
			*/
			$data=$_POST;
			$data['uid']=$where['uid'];
			$data['add_time']=strtotime($data['add_time']);
			$data['operator']=(int)$_SESSION['ADMIN_ID'];
			$role_temp=M('role_user')->where(array('user_id'=>$data['operator']))->find();
			if($data['operator']==1){
				$data['roleid']=1;
			}else{
				if(empty($role_temp['role_id'])){
					$this->error('非法权限！');
				}else{
					$data['roleid']=(int)$role_temp['role_id'];
				}
			}
			if(empty($where['id'])||$where['id']==0){	//添加
				$data['create_time']=time();
				if ($this->player_remark_model->create($data)!==false) {
					if ($this->player_remark_model->add($data)!==false) {
						$this->success("添加成功！",U("Player/remark",array('id'=>$where['id'],'uid'=>$where['uid'])));
					} else {
						$this->error("添加失败！");
					}
				} else {
					$this->error($this->player_remark_model->getError());
				}
			}else{		//修改
				$data['modifiy_time']=time();
				if ($this->player_remark_model->where($where)->create($data)!==false) {

					if ($this->player_remark_model->where($where)->save($data)!==false) {
						$this->success("保存成功！", U("Player/remark",array('id'=>$where['id'],'uid'=>$where['uid'])));
					} else {
						$this->error("保存失败！");
					}
				}else{
					$this->error($this->player_remark_model->getError());
				}
			}
		}
	}

	public function unbind_mobile()
	{
		$uid = I('uid');
		$res = M('player')->where(array('id'=>$uid))->save(array('mobile'=>''));

		if($res)
		{
			$this->success('解绑成功');
		}
		else
		{
			$this->error('解绑失败');
		}
	}

	public function referee_uid_list()
	{
		$uid = I('uid');
		$where['referee_uid'] = $uid;
		$count=$this->player_model->where($where)->count();
		$page = $this->page($count, 20);
		$player = $this->player_model
			->field('id,username,mobile,appid,channel,count,source,regip,regtime,status,system,machine_code')
			->where($where)
			->order("create_time DESC")
			->limit($page->firstRow, $page->listRows)
			->select();

		$gids = '';
		$cids = '';
		foreach($player as $v)
		{
			$gids.=$v['appid'].',';
			$cids.=$v['channel'].',';
		}
		$gids = trim($gids,',');
		$cids = trim($cids,',');

		$games=$this->games_model->where(array('id'=>array('in',$gids)))->getfield('id,game_name',true);

		$channels=$this->channel_model->where(array('id'=>array('in',$cids)))->getfield('id,name',true);

		$this->assign("page", $page->show('Admin'));
		$this->assign("games",$games);
		$this->assign("channels",$channels);
		$this->assign("player",$player);

		$this->display();
	}

	/**
	 * 玩家BI数据
	 */
	public function fromBI(){
		$game_role = session('game_role');
		$channel_role = session('channel_role');
		$start = I('start',date('Y-m-d'));
		$end = I('end',date('Y-m-d'));
		$gid = I('gid');
		$channel = I('channel');
		$uname = I('uname');
		$qq = I('qq');
		$vip = I('vip');
		$vipactive = I('vipactive');
		$status = I('status');
		$regip = I('regip');

		if($gid) $where['gid'] = $gid;
		if($channel) $where['channel'] = $channel;
		if($uname) $where['username'] = array('like',$uname.'%');
		if($qq) $where['qq'] = $qq;
		if($vip != -1) $where['vip'] = $vip;
		if($vipactive != -1) $where['vipactive'] = $vipactive;
		if($status) $where['status'] = $status;
		if($regip) $where['regip'] = $regip;

		$where['created'] = array('between',array(strtotime($start),strtotime($end.' 23:59:59')));
		if($game_role != 'all'){
			$where['gid'] = array('in',$game_role);
		}
		if($channel_role != 'all'){
			$where['channel'] = array('in',$channel_role);
		}
		$count = M('syo_member',null,C('biDB'))->where($where)->count();
		$page = $this->page($count,20);
		$data = M('syo_member',null,C('biDB'))
			->where($where)
			->order('created desc')
			->limit($page->firstRow,$page->listRows)
			->select();



		if(!empty($data))
		{
			$gids = '';
			$cids = '';
			foreach($data as $v)
			{
				$gids.=$v['gid'].',';
				$cids.=$v['channel'].',';
			}

			$gids = trim($gids,',');
			$cids = trim($cids,',');

			$game_names = M('game')->
			where(array('id'=>array('in',$gids)))->
			getfield('id,game_name',true);

			$channel_names = M('channel')->
			where(array('id'=>array('in',$cids)))->
			getfield('id,name',true);

		}

		$this->game_names = $game_names;
		$this->channel_names = $channel_names;
		$this->gid = $gid;
		$this->channel = $channel;
		$this->uname = $uname;
		$this->qq = $qq;
		$this->vip = $vip;
		$this->vipactive = $vipactive;
		$this->status = $status;
		$this->start = $start;
		$this->end = $end;
		$this->data = $data;
		$this->regip = $regip;
		$this->page = $page->show('Admin');
		$this->display();
	}

	/**
	 * BI玩家日志
	 */
	public function biPlayerLog(){
		$uname = I('uname');
		$start = I('start');
		$end = I('end');
		$where['l.time'] = array('between',array(strtotime($start),strtotime($end.' 23:59:59')));
		$where['l.username'] = $uname;
		$data = M('login_log l',null,C('biDB'))
			->field('l.*,g.game_name,c.channel_name')
			->join('left join game g on g.game_id=l.gameid')
			->join('left join channel c on c.channel_id=l.channel')
			->where($where)
			->order('l.time desc')
			->select();
		$this->data = $data;
		$this->display();
	}

	public function editBIplayer(){
		$id = I('id');
		$qq = I('qq');
		$pwd = I('pwd');
		if($pwd){
			$info = M('syo_member',null,C('biDB'))->where(array('id'=>$id))->find();
			$pwd = md5($pwd);
			if(strlen($pwd)==32){
				$pwd = md5($pwd.$info['salt']);
			}else{
				$pwd = md5(md5($pwd).$info['salt']);
			}
		}
		if(M('syo_member',null,C('biDB'))->where(array('id'=>$id))->setField(array('qq'=>$qq,'password'=>$pwd)) !== false){

			//修改ucenter密码
			$User = M('members','uc_','mysql://zfh:3tGhQcueTqSFy@10.66.202.198/ucenter#utf8');
			$uc_info =$User->where(array('username'=>$info['username']))->find();
			if(!empty($uc_info))
			{
				$password = md5(md5(I('pwd')).$uc_info['salt']);
				$User->where(array('uid'=>$uc_info['uid']))->save(array('password'=>$password));

			}

			$this->success();
		}else{
			$this->error('操作失败');
		}
	}

	public function ban_biuser()
	{
		$id = I('id');
		$op = I('op');
		if(M('syo_member',null,C('biDB'))->where(array('id'=>$id))->save(array('status'=>$op))!==false)
		{
			$this->success('操作成功');
		}
		else
		{
			$this->error('操作失败');
		}
	}

	public function add()
	{
		if(IS_POST) {

			$username = trim(I('username'));
			$password = trim(I('password'));
			$channel = I('channel');
			$appid = I('appid');

			if (strlen($username) > 16 || strlen($username) < 6) {
				$this->error('用户名长度为6-16位');
			}

			if (strlen($password) > 16 || strlen($password) < 6) {
				$this->error('密码长度为6-16位');
			}

			//用户名需过滤的字符的正则
			$stripChar = '?<*.>\'"';
			if (preg_match('/[' . $stripChar . ']/is', $username) == 1) {
				$this->error( '用户名中包含' . $stripChar . '等非法字符！');
			}

			//用戶名不能以大寫XX開頭
			if (preg_match('/^XX/', $username) == 1) {
				$this->error('用戶名不能以XX开头');
			}

			//查看SDK本地是否存在该用户名
			$check_username = M('player')->where(Array('username'=>$username))->count();
			if($check_username > 0)
			{
				$this->error('用户名已存在');
			}

			$salt = getRandomString(6);
			$time = time();

			$data = array(
				'username' => $username,
				'password' => sp_password_by_player($password, $salt),
				'salt' => $salt,
				'regip' => ip2long(get_client_ip(0, true)),
				'regtime' => $time,
				'appid' => $appid,
				'channel' => $channel,
				'system' => 1,
				'maker' => '',
				'mobile_model' => '',
				'machine_code' => '',
				'system_version' => '',
				'create_time' => $time,
			);
			$res = $this->player_model->add($data);
			if ($res) {
				$game_name = M('game')->where(array('id'=>$appid))->getField('game_name');
				$app_player = array(
					'uid'=>$res,
					'channel'=>$channel,
					'appid'=>$appid,
					'nick_name'=>$game_name.'_小号1',
					'machine_code'=>$data['machine_code'],
					'system'=>$data['system'],
					'ip'=>$data['regip'],
					'create_time'=>$time,
				);
				M('app_player')->add($app_player);
				$this->success('注册成功',U('index'));
			} else {
				$this->error('注册失败');
			}

		}
		else
		{
			$this->display();
		}
	}


	/**
	 * 小号列表
	 */
	public function app_uid_list()
	{
		$uid = I('request.uid');
		$appid = I('appid');
		$map['uid'] = $uid;

		$game_role = session('game_role');


		if($game_role !='all')
		{
			$map['appid'] = array('in',$game_role);
		}

		if(!empty($appid))
		{
			$map['appid'] = $appid;
		}

		$count = M('app_player')->where($map)->count();

		$page = $this->page($count, 20);

		$list = M('app_player')
			->where($map)
			->order("create_time DESC")
			->limit($page->firstRow, $page->listRows)
			->select();


		$appids = '';

		foreach($list as $v)
		{
			$appids.=$v['appid'].',';
		}
		$appids = trim($appids,',');

		if(!empty($appids))
		{
			$gamenames = M('game')->where(array('id'=>array('in',$appids)))->getfield('id,game_name',true);
			$this->assign('gamenames',$gamenames);
		}


		$this->assign('uid',$uid);
		$this->assign('list',$list);
		$this->assign('game_list',get_game_list($appid));
		$this->assign("page", $page->show('Admin'));

		$this->display();

	}

    public function checkPlayer(){
        if(IS_POST){
            $val = I('val');
            $type = M('player p')
                ->join('__CHANNEL__ c on c.id=p.channel')
                ->where(array('p.username|p.mobile'=>$val))
                ->getField('type');
            if($type){
                $this->success($type);
            }else{
                $type = M('player p','bt_',C('SDK_DB'))
                    ->join('__CHANNEL__ c on c.id=p.channel')
                    ->where(array('p.username|p.mobile'=>$val))
                    ->getField('c.type');
                if($type){
                    $this->success($type);
                }else{
                    $this->error('未查询到该玩家');
                }

            }
        }else{
            $this->display();
        }
    }

	/**
	 * 允许玩家进入的游戏
	 */
	public function allow_game()
	{
		if(IS_POST) {
			$data = I('post.');
			$uid = I('get.uid');
			if(empty($data)) {
				$update = [
					'allow_game' => ''
				];
			}else {
				$update = [
					'allow_game' => implode(',',$data['game_ids'])
				];
			}
			$res = M('player')->where(['id'=>$uid])->save($update);
			if($res) {
				$this->success('更新成功');
			}else {
				$this->error('更新失败');
			}
		}else{
			$uid = I('get.uid');
			import('PinYin');
			$py = new \PinYin();
			
			$player = M('player')
				->field('allow_game')
				->where(['id' => $uid])
				->find();
	
			$games = M('game')
				->field('id,game_name')
				->where(array('status'=>1))
				->select();
	
			foreach($games as $k=>$v){
				$games[$k]['pinyin'] = ucfirst(substr($py->getFirstPY($v['game_name']),0,1));
			}
			$games = multi_array_sort($games,'pinyin');
			$playerGame = explode(',',$player['allow_game']);
			$this->assign('games',$games);
			$this->assign('playerGame',$playerGame);
			$this->display();
		}
		
	}

}