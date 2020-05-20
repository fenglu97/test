<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class ChannelController extends AdminbaseController{

	public function _initialize() {
		parent::_initialize();
		$this->channel_mode = M("Channel");

	}

	/**
	 * 渠道列表
	 */
	public function index()
	{
		//接受Post数据
		$parameter = array();
		$parameter['id'] = I('id');
		$parameter['name'] = I('name');
		$parameter['type'] = I('type')?I('type'):0;
		$parameter['template_id'] = I('template_id');



		//组装搜索条件

		if($parameter['type'] == 0)
		{
			$map = array('status'=>1,'parent'=>0);
		}
		else
		{
			$map['parent'] = array('neq',0);
		}

		$channel_role = session('channel_role');
		if($channel_role != 'all'){
			$map['id'] = array('in',$channel_role);
		}

		if(!empty($parameter['name']))
		{
			$map['name'] = array('like',"%{$parameter['name']}%");
		}
		if(!empty($parameter['id']))
		{
			$map['id'] = $parameter['id'];
		}
		if($parameter['template_id'])
		{
			$map['template_id'] = $parameter['template_id'];
		}

		$channel_template_lists = M('channel_template')->field('id,name')->select();

		$this->assign('channel_template_lists',$channel_template_lists);


		$counts =$this->channel_mode
			->where($map)
			->count();

		$page = $this->page($counts, 10);


		foreach($parameter as $key=>$val)
		{
			if(!empty($val))
				$page->parameter[$key] = urlencode($val);
		}


		$this->assign('page',$page->show('Admin'));

		$this->assign('parameter',$parameter);

		if($parameter['type'] == 0)
		{
			$result = $this->channel_mode->
			field('*,parent as parentid')->
			where($map)->
			order(array("id" =>"asc"))->
			limit($page->firstRow . ',' . $page->listRows)->
			select();

			if(!empty($result))
			{
				$ids = '';
				foreach($result as $v)
				{
					$ids.=$v['id'].',';
				}
				$ids = trim($ids,',');

				$child_result = $this->channel_mode
				->field('*,parent as parentid')
				->where(array('status'=>1,'parent'=>array('in',$ids)))
				->select();

				$result = array_merge($result,is_array($child_result)?$child_result:array());

			}


			$tree = new \Tree();


			$newmenus=array();
			foreach ($result as $m){
				$newmenus[$m['id']]=$m;

			}

			foreach ($result as $n=> $r) {

				//$result[$n]['level'] = $this->_get_level($r['id'], $newmenus);
				$result[$n]['parentid_node'] = ($r['parent']) ? ' class="child-of-node-' . $r['parent'] . '"' : '';

				$result[$n]['style'] = empty($r['parent']) ? '' : 'display:none;';

				if($r['parent'] ==0)
				{
					$result[$n]['str_manage'] = '<a href="' . U("Channel/add", array("parentid" => $r['id'])) . '">添加子渠道</a> | <a href="' . U("Channel/edit", array("id" => $r['id'])) . '">'.L('EDIT').'</a> | <a class="js-ajax-delete" href="' . U("Channel/del", array("id" => $r['id'])). '">'.L('DELETE').'</a> ';
				}
				else
				{
					$result[$n]['str_manage'] = '<font color="#cccccc">添加子渠道</font>| <a href="' . U("Channel/edit", array("id" => $r['id'])) . '">'.L('EDIT').'</a> | <a class="js-ajax-delete" href="' . U("Channel/del", array("id" => $r['id'])). '">'.L('DELETE').'</a> ';
				}
				$result[$n]['create_time'] = date('Y-m-d H:i:s',$r['create_time']);
			}

			$tree->init($result);

			$str = "<tr id='node-\$id' \$parentid_node style='\$style'>
					<td style='padding-left:20px;'>\$spacer\$id</td>
        			<td>\$name</td>
					<td>\$gain_sharing</td>
				    <td>\$create_time</td>
					<td>\$str_manage</td>
				</tr>";

			$categorys = $tree->get_tree(0, $str);


			$this->assign("categorys", $categorys);
			$this->display();
		}
		else
		{
			$list = $this->channel_mode->
			where($map)->
			order(array("id" =>"asc"))->
			limit($page->firstRow . ',' . $page->listRows)->
			select();

			$this->assign('list',$list);
			$this->display('child_index');
		}


	}

	/**
	 * 渠道列表-最高5级子渠道，暂时弃用
	 */
	public function index_new()
	{
		//接受Post数据
		$parameter = array();
		$parameter['id'] = I('id');
		$parameter['name'] = I('name');
		$parameter['type'] = I('type')?I('type'):0;
		$parameter['template_id'] = I('template_id');



		//组装搜索条件

		if($parameter['type'] == 0)
		{
			$map = array('status'=>1,'parent'=>0);
		}
		else
		{
			$map['parent'] = array('neq',0);
		}



		if(!empty($parameter['name']))
		{
			$map['name'] = array('like',"%{$parameter['name']}%");
		}
		if(!empty($parameter['id']))
		{
			$map['id'] = $parameter['id'];
		}
		if($parameter['template_id'])
		{
			$map['template_id'] = $parameter['template_id'];
		}

		$channel_template_lists = M('channel_template')->field('id,name')->select();

		$this->assign('channel_template_lists',$channel_template_lists);


		$counts =$this->channel_mode
			->where($map)
			->count();

		$page = $this->page($counts, 10);


		foreach($parameter as $key=>$val)
		{
			if(!empty($val))
				$page->parameter[$key] = urlencode($val);
		}


		$this->assign('page',$page->show('Admin'));

		$this->assign('parameter',$parameter);

		if($parameter['type'] == 0)
		{
			$result = $this->channel_mode->
			field('*,parent as parentid')->
			where($map)->
			order(array("id" =>"asc"))->
			limit($page->firstRow . ',' . $page->listRows)->
			select();

			if(!empty($result))
			{
				$ids = '';
				foreach($result as $v)
				{
					$ids.=$v['id'].',';
				}
				$ids = trim($ids,',');

				$child_result = $this->channel_mode
					->field('*,parent as parentid')
					->where(array('status'=>1,'top_parent'=>array('in',$ids)))
					->select();

				$result = array_merge($result,is_array($child_result)?$child_result:array());

			}


			$tree = new \Tree();


			$newmenus=array();
			foreach ($result as $m){
				$newmenus[$m['id']]=$m;

			}
			foreach ($result as $n=> $r) {

				//$result[$n]['level'] = $this->_get_level($r['id'], $newmenus);
				$result[$n]['parentid_node'] = ($r['parent']) ? ' class="child-of-node-' . $r['parent'] . '"' : '';

				$result[$n]['style'] = empty($r['parent']) ? '' : 'display:none;';

				if($result[$n]['level'] < 5)
				{
					$result[$n]['str_manage'] = '<a href="' . U("Channel/add", array("parentid" => $r['id'])) . '">添加子渠道</a> | <a href="' . U("Channel/edit", array("id" => $r['id'])) . '">'.L('EDIT').'</a> | <a class="js-ajax-delete" href="' . U("Channel/del", array("id" => $r['id'])). '">'.L('DELETE').'</a> ';
				}
				else
				{
					$result[$n]['str_manage'] = '<a href="' . U("Channel/edit", array("id" => $r['id'])) . '">'.L('EDIT').'</a> | <a class="js-ajax-delete" href="' . U("Channel/del", array("id" => $r['id'])). '">'.L('DELETE').'</a> ';
				}
				$result[$n]['create_time'] = date('Y-m-d H:i:s',$r['create_time']);
			}

			$tree->init($result);
			$str = "<tr id='node-\$id' \$parentid_node style='\$style'>
					<td style='padding-left:20px;'>\$spacer\$id</td>
        			<td>\$spacer\$name</td>
					<td>\$gain_sharing</td>
				    <td>\$create_time</td>
					<td>\$str_manage</td>
				</tr>";

			$categorys = $tree->get_tree(0, $str);


			$this->assign("categorys", $categorys);
			$this->display();
		}
		else
		{
			$list = $this->channel_mode->
			where($map)->
			order(array("id" =>"asc"))->
			limit($page->firstRow . ',' . $page->listRows)->
			select();

			$this->assign('list',$list);
			$this->display('child_index');
		}


	}



	public function add()
	{
		if(IS_POST)
		{
			$_POST['create_time'] = time();
			$_POST['shouyou_qq'] = json_encode(array('number'=>$_POST['shouyou_qq_number'],'link'=>$_POST['shouyou_qq_link']));
			$_POST['fanli_qq'] = json_encode(array('number'=>$_POST['fanli_qq_number'],'link'=>$_POST['fanli_qq_link']));
			$_POST['shouyou_group'] = json_encode(array('number'=>$_POST['shouyou_group_number'],'link'=>$_POST['shouyou_group_link'],'weblink'=>$_POST['shouyou_group_weblink']));
			$_POST['box_group'] = json_encode(array('number'=>$_POST['box_group_number'],'link'=>$_POST['box_group_link'],'weblink'=>$_POST['box_group_weblink']));

			if($_POST['parent'] > 0)
			{
				$parent_channel = $this->channel_mode->where(array('id'=>$_POST['parent']))->find();
				$_POST['top_parent'] = ($parent_channel['top_parent'] == 0)?$parent_channel['id']:$parent_channel['top_parent'];
				$_POST['level'] = $parent_channel['level'] + 1;
			}


			if($this->channel_mode->create())
			{
				$channel_id = $this->channel_mode->add($_POST);
				if($channel_id)
				{
					//如果是自投放渠道 将在185主站数据库添加
					if($_POST['type'] == 3)
					{
						$setting_185 = M('setting','syo_',C('185DB'));
						$setting_data = array(
							'set_key'=>'channel',
							'set_value'=>$channel_id
						);
						if($setting_185->where($setting_data)->count() == 0)
						{
							$setting_185->add($setting_data);
						}
					}

					$user_model =D("Common/Users");
					// 判断该账号是否为已有账号
					$user = M('Users')->field('id,user_login')->where(array('user_login'=>$_POST['user_login']))->find();
                    if($user && $user['id']) {  // 旧账号就分配权限
                        $this->channel_mode->where(array('id'=>$channel_id))->save(array('admin_id'=>$user['id']));
                        if($_POST['type'] == 2){
                            if($_POST['internal'] == 1) {
                                $role_id = 15;
                            }else{
                                $role_id = 29;
                            }
                        } elseif($_POST['type'] == 3) $role_id = 18;
                        else $role_id = 4;

                        $roleUser = M('RoleUser')->where(array('user_id'=>$user['id']))->find();
                        if(!$roleUser) {
                            M("RoleUser")->add(array("role_id"=>$role_id,"user_id"=>$user['id']));
                        }

                        //创建数据权限,渠道用户游戏权限为all,一级渠道账号加上所有子级账号，二级加其本身
                        $data = array();
                        $data['game_role'] = 'all';
                        $data['channel_role'] = $channel_id;

                        $data['userid'] = $user['id'];


                        $userRights = M('userrights')->field('id,userid,channel_role')->where(array('userid'=>$user['id']))->find();
                        if($userRights) {
                            $data['channel_role'] = $userRights['channel_role'] . ',' . $channel_id;
                            M('userrights')->where(array('id'=>$userRights['id']))->setField($data);
                        }else {
                            M('userrights')->add($data);
                        }

                        $this->_bind_parent_role($_POST['parent'],$channel_id);

                        $this->success('添加成功',U('Channel/index'));

                    }else {  // 新账号就添加账户
                        if ($user_model->create()!==false)
                        {
                            $result=$user_model->add();
                            if ($result!==false)
                            {
                                $this->channel_mode->where(array('id'=>$channel_id))->save(array('admin_id'=>$result));
                                if($_POST['type'] == 2){
                                    if($_POST['internal'] == 1) {
                                        $role_id = 15;
                                    }else{
                                        $role_id = 29;
                                    }
                                } elseif($_POST['type'] == 3) $role_id = 18;
                                else $role_id = 4;

                                M("RoleUser")->add(array("role_id"=>$role_id,"user_id"=>$result));

                                //创建数据权限,渠道用户游戏权限为all,一级渠道账号加上所有子级账号，二级加其本身
                                $data = array();
                                $data['game_role'] = 'all';
                                $data['channel_role'] = $channel_id;

                                $data['userid'] = $result;
                                $userrights_model = M('userrights');
                                $userrights_model->add($data);

                                $this->_bind_parent_role($_POST['parent'],$channel_id);

                                $this->success('添加成功',U('Channel/index'));
                            }
                            else
                            {
                                //如果添加失败，事物回滚，删除channel_id
                                $this->channel_mode->delete($channel_id);
                                $this->error("添加失败！");
                            }
                        }
                        else
                        {
                            //如果添加失败，事物回滚，删除channel_id
                            $this->channel_mode->delete($channel_id);
                            $this->error($user_model->getError());
                        }
                    }

					exit;
				}
				else
				{
					$this->error('添加失败');
					exit;
				}
			}
		}

		$channel_info = $this->channel_mode->field('id,name')->where(array('id'=>I('parentid')))->find();
		$this->assign('channel_info',$channel_info);
		$channel_list = $this->channel_mode->field('id,name')->where(array('status'=>1,'parent'=>0))->select();
		$this->assign('channel_list',$channel_list);
		$this->channel_type = C('channel_type');
		$this->channel_template = M('channel_template')->field('id,name')->select();

		$box_static_enbale = $this->check_access(get_current_admin_id(),'Admin/Channel/box_static');
		$this->box_static_enbale = $box_static_enbale;
		$this->display();
	}

	public function edit()
	{
		$user_model =D("Common/Users");
		if(IS_POST)
		{
			$post_data = I('post.');
			$post_data['shouyou_qq'] = json_encode(array('number'=>$post_data['shouyou_qq_number'],'link'=>$post_data['shouyou_qq_link']));
			$post_data['fanli_qq'] = json_encode(array('number'=>$post_data['fanli_qq_number'],'link'=>$post_data['fanli_qq_link']));
			$post_data['shouyou_group'] = json_encode(array('number'=>$post_data['shouyou_group_number'],'link'=>$post_data['shouyou_group_link'],'weblink'=>$post_data['shouyou_group_weblink']));
			$post_data['box_group'] = json_encode(array('number'=>$post_data['box_group_number'],'link'=>$post_data['box_group_link'],'weblink'=>$post_data['box_group_weblink']));

            $post_data['cash_time'] = strtotime($post_data['cash_time']);
			$result = $this->channel_mode->where(array('id'=>$_POST['id']))->save($post_data);
			if($result!==false)
			{
				//如果是自投放渠道 将在185主站数据库添加
				if($_POST['type'] == 3)
				{
					$setting_185 = M('setting','syo_',C('185DB'));
					$setting_data = array(
						'set_key'=>'channel',
						'set_value'=>$_POST['id']
					);
					if($setting_185->where($setting_data)->count() == 0)
					{
						$setting_185->add($setting_data);
					}
				}

				$user_model =D("Common/Users");

				unset($_POST['id']);
				if(!empty($post_data['user_id']))
				{
					//如果是空，为不修改密码
					$_POST['id'] = $post_data['user_id'];
					if(empty($_POST['user_pass']))
					{
						unset($_POST['user_pass']);
					}
				}

				if ($user_model->create()!==false)
				{
					if($_POST['type'] == 2){if($_POST['internal'] == 1){$role_id = 15;}else{$role_id = 29;}}
					elseif($_POST['type'] == 3) $role_id = 18;
					else $role_id = 4;
					if(!empty($post_data['user_id']))
					{
						$user_model->where(array('id'=>$post_data['user_id']))->save();
						M("RoleUser")->where(array('user_id'=>$post_data['user_id']))->save(array("role_id"=>$role_id));
					}
					else
					{
						$result=$user_model->add();
						$this->channel_mode->where(array('id'=>$post_data['id']))->save(array('admin_id'=>$result));

						M("RoleUser")->add(array("role_id"=>$role_id,"user_id"=>$result));
						//创建数据权限,渠道用户游戏权限为all
						$data = array();
						$data['game_role'] = 'all';
						$data['channel_role'] = $post_data['id'];

						$data['userid'] = $result;
						$userrights_model = M('userrights');
						$userrights_model->add($data);

					}

					$this->success("修改成功！", U("Channel/index"));
				}
				else
				{
					$this->error($user_model->getError());
				}

				exit;
			}
			else
			{
				$this->error('修改失败');
				exit;
			}
		}
		$id = I('get.id');
		$info = $this->channel_mode->where(array('status'=>1,'id'=>$id))->find();

		$info['shouyou_qq'] =json_decode($info['shouyou_qq'],true);
		$info['fanli_qq'] = json_decode($info['fanli_qq'],true);
		$info['shouyou_group'] =json_decode($info['shouyou_group'],true);
		$info['box_group'] =json_decode($info['box_group'],true);

		$info['ad_pic_url'] = $info['ad_pic']?sp_get_image_preview_url($info['ad_pic']):'';
		$this->channel_template = M('channel_template')->field('id,name')->select();


//		$user_map = array('b.role_id'=>$role_id);
		if($info['parent']!=0)
		{
			$channel_info = $this->channel_mode->field('id,name')->where(array('id'=>$info['parent']))->find();
//			$user_map['a.channel_role'] = $id;
		}
		else
		{
			$channel_info = array('id'=>0,'name'=>'一级渠道');
//			$user_map['_string']='FIND_IN_SET("'.$id.'", a.channel_role)';
		}


		$user_info = $user_model->field('id,user_login,user_pass')->where(array('id'=>$info['admin_id']))->find();

		$channel_list = $this->channel_mode->field('id,name')->where(array('status'=>1,'parent'=>0))->select();
		$this->assign('channel_list',$channel_list);
		$this->assign('info',$info);
		$this->assign('channel_info',$channel_info);
		$this->assign('user_info',$user_info);
		$this->channel_type = C('channel_type');
		$box_static_enbale = $this->check_access(get_current_admin_id(),'Admin/Channel/box_static');
        $this->box_static_enbale = $box_static_enbale;
        
		$this->display();
	}

	public function del()
	{
		$channel_role = session('channel_role');

		$id = I('get.id');
		if($channel_role !='all' && !in_array($id,explode(',',$channel_role)))
		{
			$this->error('没有权限');
		}

		//查看该渠道是否有子渠道，如果有子渠道不能删除
		$count = $this->channel_mode->where(array('parent'=>$id,'status'=>1))->count();
		if($count > 0)
		{
			$this->error('该渠道下有子渠道,不能删除');

		}
		else
		{
			$res = $this->channel_mode->where(array('id'=>$id))->save(array('status'=>0));
			if($res)
			{

				$this->success('删除成功');
			}
			else
			{
				$this->success('删除失败');
			}
		}


	}


	public function index_by_channel()
	{
		//接受Post数据
		$parameter = array();
		$parameter['id'] = I('id');
		$parameter['name'] = I('name');

		if(!empty($parameter['name']))
		{
			$map['name'] = array('like',"%{$parameter['name']}%");
		}

		$channel_role = session('channel_role');


		if($channel_role !='all')
		{
			$map['id'] = array('in',$channel_role);
		}

		if(!empty($parameter['id']))
		{
			$map['id'] = $parameter['id'];
		}



//		$counts =$this->channel_mode
//			->where($map)
//			->count();
//
//		$page = $this->page($counts, 10);

//		foreach($parameter as $key=>$val)
//		{
//			if(!empty($val))
//				$page->parameter[$key] = urlencode($val);
//		}


		//$this->assign('page',$page->show('Admin'));

		$this->assign('parameter',$parameter);

		$result = $this->channel_mode->
		field('*,parent as parentid')->
		where($map)->
		order(array("id" =>"asc"))->
		//limit($page->firstRow . ',' . $page->listRows)->
		select();


		$level = $this->channel_mode->
		where($map)->
		getfield('min(level)');


		if($level > 1)
		{
			$parent = $this->channel_mode->
			where(array_merge($map,array('level'=>$level)))->
			getfield('parent');
		}
		else
		{
			$parent = 0;
		}


		$tree = new \Tree();

		$newmenus=array();
		foreach ($result as $m){
			$newmenus[$m['id']]=$m;

		}
		foreach ($result as $n=> $r) {

			//$result[$n]['level'] = $this->_get_level($r['id'], $newmenus);
			$result[$n]['parentid_node'] = (($r['parent'] == $parent) ? '':' class="child-of-node-' . $r['parent'] . '"' );

			$result[$n]['style'] = (($r['parent'] == $parent) ? '' : 'display:none;');

			if($r['parent'] ==0)
			{
				$result[$n]['str_manage'] = '<a href="' . U("Channel/add_by_channel", array("parentid" => $r['id'])) . '">添加子渠道</a> | <a href="' . U("Channel/edit", array("id" => $r['id'])) . '">'.L('EDIT').'</a> | <a class="js-ajax-delete" href="' . U("Channel/del", array("id" => $r['id'])). '">'.L('DELETE').'</a> ';
			}
			else
			{
				$result[$n]['str_manage'] = '<font color="#cccccc">添加子渠道</font>| <a href="' . U("Channel/edit", array("id" => $r['id'])) . '">'.L('EDIT').'</a> | <a class="js-ajax-delete" href="' . U("Channel/del", array("id" => $r['id'])). '">'.L('DELETE').'</a> ';
			}
			$result[$n]['create_time'] = date('Y-m-d H:i:s',$r['create_time']);
		}

		$tree->init($result);
		$str = "<tr id='node-\$id' \$parentid_node style='\$style'>
					<td style='padding-left:20px;'>\$spacer\$id</td>
        			<td>\$spacer\$name</td>
					<td>\$gain_sharing</td>
				    <td>\$create_time</td>
					<td>\$str_manage</td>
				</tr>";


		$categorys = $tree->get_tree($parent, $str);



		$this->assign("categorys", $categorys);
		$this->display();

	}

	public function add_by_channel()
	{
		if(IS_POST)
		{
			$channel_role = session('channel_role');

			if($channel_role !='all' && !in_array($_POST['parent'],explode(',',$channel_role)))
			{
				$this->error('没有权限');
			}

			$_POST['create_time'] = time();
			$_POST['shouyou_qq'] = json_encode(array('number'=>$_POST['shouyou_qq_number'],'link'=>$_POST['shouyou_qq_link']));
			$_POST['fanli_qq'] = json_encode(array('number'=>$_POST['fanli_qq_number'],'link'=>$_POST['fanli_qq_link']));
			$_POST['shouyou_group'] = json_encode(array('number'=>$_POST['shouyou_group_number'],'link'=>$_POST['shouyou_group_link']));
			$_POST['box_group'] = json_encode(array('number'=>$_POST['box_group_number'],'link'=>$_POST['box_group_link']));

			if($_POST['parent'] > 0)
			{
				$parent_channel = $this->channel_mode->where(array('id'=>$_POST['parent']))->find();
				$_POST['top_parent'] = ($parent_channel['top_parent'] == 0)?$parent_channel['id']:$parent_channel['top_parent'];
				$_POST['level'] = $parent_channel['level'] + 1;
			}

			if($this->channel_mode->create())
			{
				$channel_id = $this->channel_mode->add($_POST);
				if($channel_id)
				{
					//如果是自投放渠道 将在185主站数据库添加
					if($_POST['type'] == 3)
					{
						$setting_185 = M('setting','syo_',C('185DB'));
						$setting_data = array(
							'set_key'=>'channel',
							'set_value'=>$channel_id
						);
						if($setting_185->where($setting_data)->count() == 0)
						{
							$setting_185->add($setting_data);
						}
					}

					$user_model =D("Common/Users");
					if ($user_model->create()!==false)
					{
						$result=$user_model->add();
						if ($result!==false)
						{
							$this->channel_mode->where(array('id'=>$channel_id))->save(array('admin_id'=>$result));
							if($_POST['type'] == 2){if($_POST['internal'] == 1){$role_id = 15;}else{$role_id = 29;}}
							elseif($_POST['type'] == 3) $role_id = 18;
							else $role_id = 4;

							M("RoleUser")->add(array("role_id"=>$role_id,"user_id"=>$result));


							//创建数据权限,渠道用户游戏权限为all
							$data = array();
							$data['game_role'] = 'all';
							$data['channel_role'] = $channel_id;

							$data['userid'] = $result;
							$userrights_model = M('userrights');
							$userrights_model->add($data);


							//添加成功后需要将这个子渠道的数据权限给用户加上并在session里面添加

							$channel_role = $channel_role.','.$channel_id;
							session('channel_role',$channel_role);
							$res = $userrights_model
								->where(array('userid'=>session('ADMIN_ID')))
								->save(array('channel_role'=>$channel_role));


							$this->_bind_parent_role($_POST['parent'],$channel_id);


							$this->success("添加成功！", U("Channel/index_by_channel"));
						}
						else
						{
							$this->channel_mode->delete($channel_id);
							$this->error("添加失败！");
						}
					}
					else
					{
						$this->channel_mode->delete($channel_id);
						$this->error($user_model->getError());
					}

					exit;
				}
				else
				{
					$this->error('添加失败');
					exit;
				}
			}
		}


		$channel_info = $this->channel_mode->field('id,name')->where(array('id'=>I('parentid')))->find();
		$this->assign('channel_info',$channel_info);
		$this->assign('parentid', I('parentid'));
		$this->channel_type = C('channel_type');
		$box_static_enbale = $this->check_access(get_current_admin_id(),'Admin/Channel/box_static');
		$this->box_static_enbale = $box_static_enbale;
		$this->display();
	}

	public function edit_by_channel()
	{
		$channel_role = session('channel_role');
		$userrights_model = M('userrights');
		$user_model =D("Common/Users");



		if(IS_POST)
		{

			if($channel_role !='all' && !in_array($_POST['id'],explode(',',$channel_role)))
			{
				$this->error('没有权限');
			}

			$post_data = I('post.');
			$post_data['shouyou_qq'] = json_encode(array('number'=>$post_data['shouyou_qq_number'],'link'=>$post_data['shouyou_qq_link']));
			$post_data['fanli_qq'] = json_encode(array('number'=>$post_data['fanli_qq_number'],'link'=>$post_data['fanli_qq_link']));
			$post_data['shouyou_group'] = json_encode(array('number'=>$post_data['shouyou_group_number'],'link'=>$post_data['shouyou_group_link']));
			$post_data['box_group'] = json_encode(array('number'=>$post_data['box_group_number'],'link'=>$post_data['box_group_link']));

			$original_channel_name = $this->channel_mode->where(array('id'=>$post_data['id']))->getfield('name');

			$result = $this->channel_mode->where(array('id'=>$post_data['id']))->save($post_data);

			if($result!==false)
			{
				//如果是自投放渠道 将在185主站数据库添加
				if($_POST['type'] == 3)
				{
					$setting_185 = M('setting','syo_',C('185DB'));
					$setting_data = array(
						'set_key'=>'channel',
						'set_value'=>$post_data['id']
					);
					if($setting_185->where($setting_data)->count() == 0)
					{
						$setting_185->add($setting_data);
					}
				}

				$user_model =D("Common/Users");

				unset($_POST['id']);
				if(!empty($post_data['user_id']))
				{
					//如果是空，为不修改密码
					$_POST['id'] = $post_data['user_id'];
					if(empty($_POST['user_pass']))
					{
						unset($_POST['user_pass']);
					}
				}
				if ($user_model->create()!==false)
				{
					if($_POST['type'] == 2){if($_POST['internal'] == 1){$role_id = 15;}else{$role_id = 29;}}
					elseif($_POST['type'] == 3) $role_id = 18;
					else $role_id = 4;
					if(!empty($post_data['user_id']))
					{
						$user_model->where(array('id'=>$post_data['user_id']))->save();
						M("RoleUser")->where(array('user_id'=>$post_data['user_id']))->save(array("role_id"=>$role_id));
					}
					else
					{
						$result=$user_model->add();

						$this->channel_mode->where(array('id'=>$post_data['id']))->save(array('admin_id'=>$result));
						M("RoleUser")->add(array("role_id"=>$role_id,"user_id"=>$result));
						//创建数据权限,渠道用户游戏权限为all
						$data = array();
						$data['game_role'] = 'all';
						$data['channel_role'] = $post_data['id'];

						$data['userid'] = $result;
						$userrights_model = M('userrights');
						$userrights_model->add($data);

					}

					$this->success("修改成功！", U("Channel/index_by_channel"));
				}
				else
				{
					$this->error($user_model->getError());
				}

				exit;
			}
			else
			{
				$this->error('修改失败');
				exit;
			}
		}
		$id = I('get.id');
		$info = $this->channel_mode->where(array('status'=>1,'id'=>$id))->find();
		$info['shouyou_qq'] =json_decode($info['shouyou_qq'],true);
		$info['fanli_qq'] = json_decode($info['fanli_qq'],true);
		$info['shouyou_group'] =json_decode($info['shouyou_group'],true);
		$info['box_group'] =json_decode($info['box_group'],true);

		//是否可以修改分成比例（一级渠道可以修改子渠道的分成比例，二级渠道用户和一级渠道用户不能修改自身的分成比例）
		$is_modify_fencheng = 0;

		//$user_map = array('b.role_id'=>$role_id);
		if($info['parent']!=0)
		{
			//如果不是一级渠道，判断该渠道的父渠道是否在该用户的数据权限内

			if(in_array($info['parent'],explode(',',$channel_role)))
			{
				$is_modify_fencheng = 1;
			}
			$channel_info = $this->channel_mode->field('id,name')->where(array('id'=>$info['parent']))->find();
			//	$user_map['a.channel_role'] = $id;
		}
		else
		{
			$channel_info = array('id'=>0,'name'=>'一级渠道');
			//	$user_map['_string']='FIND_IN_SET("'.$id.'", a.channel_role)';
		}

		$info['ad_pic_url'] = $info['ad_pic']?sp_get_image_preview_url($info['ad_pic']):'';

		//查询该渠道的管理员
//		$userid = $userrights_model->
//		alias('a')->
//		join('__ROLE_USER__ as b on a.userid = b.user_id')->
//		field('a.userid')->
//		where($user_map)
//		->find();

		$user_info = $user_model->field('id,user_login,user_pass')->where(array('id'=>$info['admin_id']))->find();

		$this->assign('channel_info',$channel_info);
		$this->assign('info',$info);
		$this->assign('is_modify_fencheng',$is_modify_fencheng);
		$this->assign('user_info',$user_info);
		$this->channel_type = C('channel_type');
		$box_static_enbale = $this->check_access(get_current_admin_id(),'Admin/Channel/box_static');
		$this->box_static_enbale = $box_static_enbale;
		$this->display();
	}

	public function get_template_info()
	{
		$id = I('id');
		$info = M('channel_template')->where(array('id'=>$id))->find();
		$info['ad_pic_url'] = $info['ad_pic']?sp_get_image_preview_url($info['ad_pic']):'';
		$info['shouyou_qq'] =json_decode($info['shouyou_qq'],true);
		$info['fanli_qq'] = json_decode($info['fanli_qq'],true);
		$info['shouyou_group'] =json_decode($info['shouyou_group'],true);
		$info['box_group'] =json_decode($info['box_group'],true);
		exit(json_encode($info));

	}


	private function check_access($uid,$name)
	{
		if($uid == 1)
		{
			return true;
		}

		return sp_auth_check($uid,$name);

	}

	public function channel_target()
	{

		$admin_id = SESSION('ADMIN_ID');

		$channel_legion = M('users')->where(array('id'=>$admin_id))->getfield('channel_legion');

		$map = array();
		if($channel_legion != 'all')
		{
			$map['id'] = $channel_legion?array('in',$channel_legion):'';
		}

		$channel_legions = M('channel_legion')->where($map)->select();

		$cids = '';
		$legion_names = array();

		foreach($channel_legions as $v)
		{
			$cids.= $v['channels'].',';
			$channel_arr = explode(',',$v['channels']);
			foreach($channel_arr as $channel)
			{
				$legion_names[$channel] = $v['name'];
			}
		}

		$cids = trim($cids,',');
		$order = 'id';
		if($cids) $order = 'field(id,'.$cids.')';
		$count = M('channel')
			->where(array('id'=>array('in',$cids)))
			->count();

		$page = $this->page($count, 50);

		$list = M('channel')
			->where(array('id'=>array('in',$cids)))
			->field('id,name,lastweek_register,lastweek_money,thisweek_register,thisweek_money')
			->order($order)
			->limit($page->firstRow . ',' . $page->listRows)
			->select();

		//计算每个团队当月在职人数
		$info = M('tg_info')->where(array('parent_channel'=>array('in',$cids),'time'=>date('Y-m',time())))->getfield('parent_channel,target',true);

		$lastinfo = M('tg_info')->where(array('parent_channel'=>array('in',$cids),'time'=>date('Y-m',strtotime('-1 month'))))->getfield('parent_channel,target,money',true);

		//$this_month_start = strtotime(date('Y-m').'-01 00:00:00');


		foreach($list as $k=>$v)
		{
			$list[$k]['lastmonth_target'] = isset($lastinfo[$v['id']]['target'])?$lastinfo[$v['id']]['target']:0;
			$list[$k]['lastmonth_money'] = isset($lastinfo[$v['id']]['money'])?$lastinfo[$v['id']]['money']:0.00;
			$list[$k]['legion_name'] = $legion_names[$v['id']];
			$list[$k]['current_target'] = isset($info[$v['id']])?$info[$v['id']]:0;
//			$list[$k]['lastmonth_employees'] = M('tg_employees')
//				->where(array('parent_channel'=>$v['id'],'departure_time'=>array(array('egt',$this_month_start),array('eq',0),'or'),'hire_date'=>array(array('gt',0),array('lt',$this_month_start))))
//				->count();


//			$list[$k]['employees'] = M('tg_employees')
//				->where(array('parent_channel'=>$v['id'],'departure_time'=>0,'hire_date'=>array(array('gt',0),array('lt',time()))))
//				->count();
		}


		$this->assign('list',$list);
		$this->assign('page',$page->show('Admin'));
		$this->display();
	}

	public function edit_channel_target()
	{
		$channel = I('channel');
		$target = I('target');
		$type = I('type')?I('type'):1;

		if($type == 1)
		{
			//创建或者修改当月目标记录
			$map['time'] = date('Y-m');
			$map['parent_channel'] = $channel;
			$channel_legion =  M('channel_legion')->where(array('_string'=>"FIND_IN_SET($channel,channels)"))->getfield('id');
			$map['channel_legion'] = $channel_legion;

			$tg_info_model = M('tg_info');

			if($tg_info_model->where($map)->count())
			{
				$res = $tg_info_model->where($map)->save(array('target'=>$target));
			}
			else
			{
				$map['create_time'] = time();
				$map['target'] = $target;
				$res = $tg_info_model->add($map);
			}
		}
		else
		{
			if($type == 2) $save['thisweek_register'] = $target;
			if($type == 3) $save['thisweek_money'] = $target;
			$res = M('channel')->where(array('id'=>$channel))->save($save);
		}


		if($res!==false)
		{
			$this->success('修改成功');
		}
		else
		{
			$this->error('修改失败');
		}

	}

	//给父渠道账号添加权限（递归）
	private function _bind_parent_role($parent,$channel_id)
	{//添加成功后需要将这个子渠道的数据权限给父渠道用户加上
		if($parent != 0 )
		{
			$channel_info = $this->channel_mode->where(array('id'=>$parent))->find();
			$userid = $channel_info['admin_id'];
			$userrights_info = M('userrights')->field('channel_role')->where(array('userid'=>$userid))->find();
			$channel_role = $userrights_info['channel_role'].','.$channel_id;

			M('userrights')
				->where(array('userid'=>$userid))
				->save(array('channel_role'=>$channel_role));

			$userids = M('userrights')->where(array('user_role'=>$userid))->getfield('userid',true);

			if($userids)
			{
				$userids = implode(',', $userids);
				M('userrights')->where(array('userid' => array('in', $userids)))->save(array('channel_role' => $channel_role));
			}

			$this->_bind_parent_role($channel_info['parent'],$channel_id);
		}
	}



	/**
	 * 渠道管理（利润），新增后台功能
	 */
	public function channel_profit(){
		$search = I('name');
		$type_id = I('type_id');
		if($search){
			$map['p.name'] = array('like',"%{$search}%");
		}
		if($type_id){
			$map['p.type_id'] = $type_id;
		}
		$map['p.status'] = 1;
		$counts = M('channel_profit p')
				->join('left join __CHANNEL_TYPE__ t on t.id = p.type_id')
				->where($map)
				->count();
		$page = $this->page($counts, 10);
		$list = M('channel_profit p')
				->join('left join __CHANNEL_TYPE__ t on t.id = p.type_id')
				->field('p.*,t.name type_name')
				->where($map)
				->limit($page->firstRow . ',' . $page->listRows)
				->select();

		$this->page = $page->show('Admin');
		$this->data = $list;
		$this->search = $search;
		$this->type_id = $type_id;
		$this->display();



	}

	public function channel_profit_add(){
		if(IS_POST){
			if(M('channel_profit')->add($_POST)){
				$this->success('添加成功',U('Channel/channel_profit'));
				exit;
			}
			else{
				$this->error("添加失败！");
				exit;
			}
		}
		$this->display();
	}

	public function channel_profit_edit(){
		if(IS_POST){

			$result = M('channel_profit')->where(array('id'=>$_POST['id']))->save($_POST);
			if($result !== false){
				$this->success('修改成功',U('Channel/channel_profit'));
				exit;
			}
			else{
				$this->error("修改失败！");
				exit;
			}

		}
		$id = I('id');
		$info = M('channel_profit')->where(array('id'=>$id))->find();
		$this->info = $info;
		$this->display();
	}

	public function channel_profit_del(){
		$id = I('id');
		$res = M('channel_profit')->where(array('id'=>$id))->save(array('status'=>0));
		if($res)
		{
			$this->success('删除成功');
		}
		else
		{
			$this->success('删除失败');
		}
	}

	public function channel_type(){
		$search = I('name');
		if($search){
			$map['name'] = array('like',"%{$search}%");
		}
		$map['status'] = 1;
		$list = M('channel_type')->where($map)->select();
		$this->data = $list;
		$this->search = $search;
		$this->display();
	}

	public function channel_type_add(){
		if(IS_POST){
			$data['name'] = $_POST['name'];
			if(M('channel_type')->add($data)){
				$this->success('添加成功',U('Channel/channel_type'));
				exit;
			}
			else{
				$this->error("添加失败！");
				exit;
			}

		}
		$this->display();
	}

	public function channel_type_edit(){
		if(IS_POST){
			$data['name'] = $_POST['name'];
			$result = M('channel_type')->where(array('id'=>$_POST['id']))->save($data);
			if($result !== false){
				$this->success('修改成功',U('Channel/channel_type'));
				exit;
			}
			else{
				$this->error("修改失败！");
				exit;
			}

		}
		$id = I('id');
		$info = M('channel_type')->where(array('id'=>$id))->find();
		$this->info = $info;
		$this->display();
	}

	public function channel_type_del(){
		$id = I('id');
		$res = M('channel_type')->where(array('id'=>$id))->save(array('status'=>0));
		if($res)
		{
			$this->success('删除成功');
		}
		else
		{
			$this->success('删除失败');
		}
	}
}