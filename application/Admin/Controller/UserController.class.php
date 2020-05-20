<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class UserController extends AdminbaseController{

	protected $users_model,$role_model;

	public function _initialize() {
		parent::_initialize();
		$this->users_model = D("Common/Users");
		$this->role_model = D("Common/Role");
	}

	// 管理员列表
	public function index(){

		$where = array("user_type"=>1);
		/**搜索条件**/
		$user_login = I('request.user_login');
		$user_email = trim(I('request.user_email'));
		$channel = I('request.channel');
		$mobile = I('request.mobile');

		$role_id = I('role_id');
		$order='';
		if($user_login){
			$where['user_login'] = array('like',"%$user_login%");
			$order = 'length(user_login) asc,';
		}

		if($user_email){
			$where['user_email'] = array('like',"%$user_email%");
		}

		if($role_id)
		{
			$where['role_id'] = $role_id;
		}

		if($channel)
		{
			$admin_id = M('channel')->where(array('id'=>$channel))->getfield('admin_id');
			$where['bt_users.id'] = $admin_id;

		}

		if($mobile)
		{
			$where['mobile'] = $mobile;
		}


		$count = $this->users_model
			->join('__ROLE_USER__ ON __USERS__.id = __ROLE_USER__.user_id')
			->where($where)
			->count();





		$page = $this->page($count, 20);
		$users = $this->users_model
			->join('__ROLE_USER__ ON __USERS__.id = __ROLE_USER__.user_id')
			->field('bt_users.*,bt_role_user.role_id')
			->where($where)
			->order($order."create_time DESC ")
			->limit($page->firstRow, $page->listRows)
			->select();



		$roles_src=$this->role_model->order('listorder desc,create_time asc')->select();
		$roles=array();
		foreach ($roles_src as $r){
			$roleid=$r['id'];
			$roles["$roleid"]=$r;
		}

		$this->assign('mobile',$mobile);
		$this->assign('tg_role_id',C('TG_ROLD_ID'));
		$this->assign('role_id',$role_id);
		$this->assign("page", $page->show('Admin'));
		$this->assign("roles",$roles);
		$this->assign("users",$users);
		$this->assign('channel',$channel);

		$this->display();
	}

	// 管理员添加
	public function add(){
		$roles=$this->role_model->where(array('status' => 1))->order("id DESC")->select();
		$this->assign("roles",$roles);
		$this->assign('game_group',C('GAME_GROUP'));
		$this->assign('channellegions',M('channel_legion')->field('id,name')->select());
		$this->display();
	}

	// 管理员添加提交
	public function add_post(){
		if(IS_POST){

			if(!empty($_POST['role_id'])){
				$role_id=$_POST['role_id'];
				unset($_POST['role_id']);

				$channel_legion_count = M('channel_legion')->field('id,name')->count();

				if($channel_legion_count == count($_POST['channel_legion']))
				{
					$_POST['channel_legion'] = 'all';
				}
				else
				{
					$_POST['channel_legion'] = trim(implode(',',$_POST['channel_legion']),',');
				}


				if ($this->users_model->create()!==false) {
					$result=$this->users_model->add();
					if ($result!==false) {
						$role_user_model=M("RoleUser");

						if(sp_get_current_admin_id() != 1 && $role_id == 1){
							$this->error("为了网站的安全，非网站创建者不可创建超级管理员！");
						}
						$role_user_model->add(array("role_id"=>$role_id,"user_id"=>$result));
						$role_info = M('role')->where(array('id'=>$role_id))->find();

						//如果不展示用户数据 权限为all
						$data = array();

						if($role_info['display_userrights'] == 0 || $role_id == 1)
						{
							$data['game_role'] = 'all';
							$data['channel_role'] = 'all';
						}
						else
						{
							$data['game_role'] = '';
							$data['channel_role'] = '';
						}
						$data['userid'] = $result;
						M('userrights')->add($data);
						$this->success("添加成功！", U("user/index"));
					} else {
						$this->error("添加失败！");
					}
				} else {
					$this->error($this->users_model->getError());
				}
			}else{
				$this->error("请为此用户指定角色！");
			}

		}
	}

	// 管理员编辑
	public function edit(){
		$id = I('get.id',0,'intval');
		$roles=$this->role_model->where(array('status' => 1))->order("id DESC")->select();
		$this->assign("roles",$roles);
		$role_user_model=M("RoleUser");
		$role_ids=$role_user_model->where(array("user_id"=>$id))->getField("role_id",true);
		$this->assign("role_ids",$role_ids);

		$user=$this->users_model->where(array("id"=>$id))->find();

		$this->assign('channellegionarr',explode(',',$user['channel_legion']));
		$this->assign($user);

		$this->assign('channellegions',M('channel_legion')->field('id,name')->select());
		$this->assign('gamegroup',C('GAME_GROUP'));
		$this->display();
	}

	// 管理员编辑提交
	public function edit_post(){
		if (IS_POST) {
			if(!empty($_POST['role_id'])){
				if(empty($_POST['user_pass'])){
					unset($_POST['user_pass']);
				}
				$role_id=$_POST['role_id'];
				unset($_POST['role_id']);

				$channel_legion_count = M('channel_legion')->field('id,name')->count();

				if($channel_legion_count == count($_POST['channel_legion']))
				{
					$_POST['channel_legion'] = 'all';
				}
				else
				{
					$_POST['channel_legion'] = trim(implode(',',$_POST['channel_legion']),',');
				}

				if ($this->users_model->create()!==false) {
					$result=$this->users_model->save();
					if ($result!==false) {
						$uid = I('post.id',0,'intval');
						$role_user_model=M("RoleUser");
						$role_user_model->where(array("user_id"=>$uid))->delete();

						if(sp_get_current_admin_id() != 1 && $role_id == 1){
							$this->error("为了网站的安全，非网站创建者不可创建超级管理员！");
						}
						$role_user_model->add(array("role_id"=>$role_id,"user_id"=>$uid));
						$role_info = M('role')->where(array('id'=>$role_id))->find();

						//如果不展示用户数据 权限为all
						$data = array();

						if($role_info['display_userrights'] == 0 || $role_id == 1)
						{
							$data['game_role'] = 'all';
							$data['channel_role'] = 'all';
						}

						M('userrights')->where(array('userid'=>$uid))->save($data);
						$this->success("保存成功！");
					} else {
						$this->error("保存失败！");
					}
				} else {
					$this->error($this->users_model->getError());
				}
			}else{
				$this->error("请为此用户指定角色！");
			}

		}
	}

	// 管理员删除
	public function delete(){
		$id = I('get.id',0,'intval');
		if($id==1){
			$this->error("最高管理员不能删除！");
		}

		if ($this->users_model->delete($id)!==false) {
			M("RoleUser")->where(array("user_id"=>$id))->delete();
			$this->success("删除成功！");
		} else {
			$this->error("删除失败！");
		}
	}

	// 管理员个人信息修改
	public function userinfo(){
		$id=sp_get_current_admin_id();
		$user=$this->users_model->where(array("id"=>$id))->find();
		$this->assign($user);
		$this->display();
	}

	// 管理员个人信息修改提交
	public function userinfo_post(){
		if (IS_POST) {
			$_POST['id']=sp_get_current_admin_id();
			$create_result=$this->users_model
				->field("id,user_nicename,sex,birthday,user_url,signature")
				->create();
			if ($create_result!==false) {
				if ($this->users_model->save()!==false) {
					$this->success("保存成功！");
				} else {
					$this->error("保存失败！");
				}
			} else {
				$this->error($this->users_model->getError());
			}
		}
	}

	// 停用管理员
	public function ban(){
		$id = I('get.id',0,'intval');
		$channel = I('channel');
		if (!empty($id)) {
			$result = $this->users_model->where(array("id"=>$id,"user_type"=>1))->setField('user_status','0');
			if ($result!==false) {
				if(M('channel')->where(array('id'=>$channel))->count())
				{
					//所有用户转移至该账号
					$admin_channel = M('channel')->where(array('admin_id'=>$id))->getfield('id');
					if($channel)
					{
						M('player')->where(array('channel'=>$admin_channel))->save(array('channel'=>$channel));
						//	M('syo_member',null,C('DB_OLDSDK_CONFIG'))->where(array('channel'=>$admin_channel))->save(array('channel'=>$channel));
					}
				}
				$this->success("管理员停用成功！", U("user/index"));
			} else {
				$this->error('管理员停用失败！');
			}
		} else {
			$this->error('数据传入失败！');
		}
	}

	// 启用管理员
	public function cancelban(){
		$id = I('get.id',0,'intval');
		if (!empty($id)) {
			$result = $this->users_model->where(array("id"=>$id,"user_type"=>1))->setField('user_status','1');
			if ($result!==false) {
				$this->success("管理员启用成功！", U("user/index"));
			} else {
				$this->error('管理员启用失败！');
			}
		} else {
			$this->error('数据传入失败！');
		}
	}

	public function edit_userrights()
	{
		$id = I('get.id');
		import('PinYin');
		$py = new \PinYin();

		$games = M('game')->field('id,game_name')->where(array('status'=>1))
			->select();
		$channels = M('channel')->field('id,name')->where(array('status'=>1))
			->select();

		foreach($games as $k=>$v)
		{
			$games[$k]['pinyin'] = ucfirst(substr($py->getFirstPY($v['game_name']),0,1));
		}

		foreach($channels as $k=>$v)
		{
			$channels[$k]['pinyin'] = ucfirst(substr($py->getFirstPY($v['name']),0,1));
		}

		$games = multi_array_sort($games,'pinyin');
		$channels = multi_array_sort($channels,'pinyin');

		$userrights = M('userrights')->where(array('userid'=>$id))->find();

		$game_role_arr = explode(',',$userrights['game_role']);

		$channel_role_arr = explode(',',trim($userrights['channel_role']));


		//如果当前账号被其他账号绑定则不能绑定其他账号（不支持多重绑定）
		$this->assign('bind_count',M('userrights')->where(array('user_role'=>$id))->count());

		$userrights['user_role'] = M('users')->where(array('id'=>$userrights['user_role']))->getfield('user_login');

		$this->assign('game_role_arr',$game_role_arr);
		$this->assign('channel_role_arr',$channel_role_arr);
		$this->assign('userrights',$userrights);
		$this->assign('games',$games);
		$this->assign('channels',$channels);
		$this->assign('id',$id);
		$this->display();
	}

	public function do_edit_userrights()
	{
		$post_data = I('post.');

		$game_counts = M('game')->where(array('status'=>1))->count();
		$channel_counts = M('channel')->where(array('status'=>1))->count();
		$game_role = '';
		if(isset($post_data['game_ids']))
		{
			if($game_counts == count($post_data['game_ids']))
			{
				$game_role = 'all';
			}
			else
			{
				$game_role = implode(',',$post_data['game_ids']);
			}
		}

		$channel_role = '';
		if(isset($post_data['channel_ids']))
		{
			if($channel_counts == count($post_data['channel_ids']))
			{
				$channel_role = 'all';
			}
			else
			{
				$channel_role = implode(',',$post_data['channel_ids']);
			}
		}
		$post_data['user_role'] = M('users')->where(array('user_login'=>$post_data['user_role']))->getfield('id');

		if($post_data['user_role'] == $post_data['userid'])
		{
			$post_data['user_role'] = 0;
		}

		if($post_data['user_role'] == 1)
		{
			$post_data['user_role'] = 0;
		}
		else if($post_data['user_role'] > 1)
		{
			//查询该账号是否是超管账号
			$role_id = M('role_user')->where(array('user_id'=>$post_data['user_role']))->getfield('role_id');
			if($role_id == 1)
			{
				$post_data['user_role'] = 0;
			}
			else
			{
				//查询该用户是否绑定账号
				$user_role = M('userrights')->where(array('userid'=>$post_data['user_role']))->getfield('user_role');
				if($user_role) $post_data['user_role'] = $user_role;

			}

		}
		else
		{
			$post_data['user_role'] = 0;
		}

		$data = array();
		$data['user_role'] = $post_data['user_role'];
		if($post_data['user_role'] > 0)
		{
			$data['channel_role'] = M('userrights')->where(array('userid'=>$data['user_role']))->getfield('channel_role');
		}
		else
		{
			$data['channel_role'] = $channel_role;
		}
		$data['game_role'] = $game_role;

		$res = M('userrights')
			->where(array('userid'=>$post_data['userid']))
			->save($data);

		if($res>0)
		{
			$userids = M('userrights')->where(array('user_role'=>$post_data['userid']))->getfield('userid',true);
			if($userids)
			{
				$userids = implode(',',$userids);
				M('userrights')
					->where(array('userid'=>array('in',$userids)))
					->save(array('channel_role'=>$channel_role));
			}
			$this->success("修改成功", U("user/index"));
		}
		else
		{
			$this->error("修改失败", U("user/index"));
		}

	}

	public function promoter_uid_list()
	{
		$id = I('id');
		$where['promoter_uid'] = $id;
		$player_model = M('player');
		$count=$player_model->where($where)->count();
		$page = $this->page($count, 20);
		$player = $player_model
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

		$games=M('game')->where(array('id'=>array('in',$gids)))->getfield('id,game_name',true);

		$channels=M('channel')->where(array('id'=>array('in',$cids)))->getfield('id,name',true);

		$this->assign("page", $page->show('Admin'));
		$this->assign("games",$games);
		$this->assign("channels",$channels);
		$this->assign("player",$player);

		$this->display();
	}



}