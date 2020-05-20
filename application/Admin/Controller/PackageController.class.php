<?php
/**
 * 礼包控制器
 * @author qing.li
 * @date 2017-03-20
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;
use PDO;
class PackageController extends AdminbaseController{

	public function index()
	{
		//接受Post数据
		$parameter = array();
		$parameter['appid'] = I('appid');
		$parameter['pack_type'] = I('pack_type');

		//组装搜索条件
		$map = array('bt_package.status'=>1);
	   // $map['bt_game.source'] = 1;
		
		$game_role = session('game_role');

		if($game_role !='all')
		{
			$map['appid'] = array('in',$game_role);
		}
		
		foreach($parameter as $k=>$v)
		{
			if(!empty($v))
			{
				$map[$k] = $v;
			}
		}
		



		$counts = M('Package')
		->join('__GAME__ ON __PACKAGE__.appid = __GAME__.id')
		->where($map)->count();
		
		

		$page = $this->page($counts, 20);

		foreach($parameter as $key=>$val)
		{
			if(!empty($val))
			$page->parameter[$key] = urlencode($val);
		}

		
		$list = M('Package')
		->join('__GAME__ ON __PACKAGE__.appid = __GAME__.id')
		->field('bt_package.*,bt_game.game_name')
		->where($map)
		->order('create_time desc')
		->limit($page->firstRow . ',' . $page->listRows)
		->select();

		$this->assign('pack_type',$parameter['pack_type']);
		$this->assign('page',$page->show('Admin'));
		$this->assign('list',$list);
		$this->assign('games',get_game_list($parameter['appid'],1,'all'));
		$this->display();
	}

	public function add()
	{

		if(IS_POST)
		{

			$data = I('post.');
			//action行为为0时为编辑模式，1为添加模式

			if($data['export']>100000 || $data['export']<0)
			{
				$this->error("可导出数量必须小于等于100000并且为正数");
			}

            if($data['singe_export']>$data['export'] || $data['export']<0)
            {
                $this->error("单次导出数量不能超过总导出数量并且为正数");
            }

			$action = 0;
			if(empty($data['id']))
			{
				$action = 1;
			}
			$id = $data['id'];

			//如果是添加模式，先检查是否存在同款游戏同款礼包类型的礼包
			if($action == 1)
			{
				$is_exists = M('Package')->where(array('appid'=>(int)$data['appid'],'pack_type'=>(int)$data['pack_type'],'status'=>1))->find();

				if($is_exists != null)
				{
					$this->error("已导入过该礼包类型的游戏礼包，不能重复导入！");
				}
			}


			if($data['is_android'] == 1 && $data['is_ios'] ==1)
			{
				$ios_android = 3;
			}
			else
			{
				if($data['is_android'] == 1)
				{
					$ios_android = 1;
				}
				elseif($data['is_ios'] == 1)
				{
					$ios_android = 2;
				}
				else
				{
					$ios_android = 0;
				}
			}



			$data = array(
                'pack_name'=>$data['pack_name'],
                'pack_type'=>(int)$data['pack_type'],
                'appid'=>(int)$data['appid'],
                'start_time'=>strtotime($data['start_time']),
                'end_time'=>strtotime($data['end_time']),
                'uid'=>get_current_admin_id(),
                'pack_abstract'=>isset($data['pack_abstract'])?$data['pack_abstract']:'',
                'export'=>isset($data['export'])?(int)$data['export']:0,
                'singe_export' => $data['singe_export'] ? : 1000,
                'get_counts'=>isset($data['get_counts'])?(int)$data['get_counts']:0,
                'ios_android'=>$ios_android,
                'pack_url'=>isset($data['pack_url'])?$data['pack_url']:''
			);




			if($action == 1)
			{
				$data['create_time'] = time();
				$data['modify_time'] = time();
				$id = M('Package')->add($data);
			}
			else
			{
				$data['modify_time'] = time();
				M('Package')->where(array('id'=>$id))->save($data);
			}


			//上传礼包
			if (!empty($_FILES['pack_url']['name'])) {

				header("Content-Type:text/html;charset=utf-8");

				$upload = new \Think\Upload();// 实例化上传类

				$upload->maxSize = 3145728;// 设置附件上传大小

				$upload->driver = 'Local';//

				$upload->driverConfig = array();

				$upload->replace = true; //存在同名是否覆盖

				$upload->autoSub = false; //自动子目录保存文件

				$upload->exts = array('csv');// 设置附件上传类

				$upload->saveName = 'time'; //上传文件命名规则，[0]-函数名，[1]-参数，多个参数使用数组

				$upload->rootPath = './data/upload/' . date('Ymd') . "/"; // 设置附件上传目录

				if(!is_dir($upload->rootPath))
				{
					@mkdir($upload->rootPath,0777);
				}
				// 上传文件
				$info = $upload->uploadOne($_FILES['pack_url']);

				if (!$info)
				{// 上传错误提示错误信息

					$this->error($upload->getError());

				}
				//上传成功
				$file_name=SITE_PATH.$upload->rootPath.$info['savename'];

				$abs_file_name =$file_name;
				$abs_file_name = str_replace('\\','/',$abs_file_name);
				$abs_file_name = str_replace('./','',$abs_file_name);
				$file_name=substr($file_name,strpos($file_name,'./')+1);


				$sql = 'load data local infile "'.$abs_file_name.'" into
                table `bt_package_code` fields terminated by \',\' (card)set appid='.$data['appid'].',pid='.$id.',create_time='.time();
				//echo $sql;die;

				$host = C('DB_HOST');
				$db_user = C('DB_USER');
				$db_pass = C('DB_PWD');
				$db_name = C('DB_NAME');
				$db_port = C('DB_PORT');
				$dsn="mysql:host={$host};dbname={$db_name};port={$db_port}";

				try{
					$pdo=new PDO($dsn,$db_user,$db_pass,array(PDO::MYSQL_ATTR_LOCAL_INFILE=>1));
				}catch(PDOException $e)
				{
					$this->error('数据库连接失败'.$e->getMessage());
				}
				$pack_counts =  $pdo->exec($sql);
				//导入失败并且是添加操作,将行为回滚,删除已添加的packs_class的记录
				if( $pack_counts === false && $action == 1)
				{
					M('Package')->delete($id);

					$this->error('礼包导入失败');
				}
				$pdo = null;

				//由于CSV导入会有\r，所以进行一次替换

				$Model = new \Think\Model(); // 实例化一个model对象 没有对应任何数据表
				$sql = "update `bt_package_code` set card=trim(TRAILING '\r' FROM `card`)";
				$Model->execute($sql);

				$arr1=array('pack_url'=>$file_name);

				if($action == 1)
				{
					$arr1['pack_counts'] = $pack_counts;
				}
				else
				{

					$totals = M('Package')->where(array('id'=>$id))->field('pack_counts')->find();
					$arr1['pack_counts'] = $totals['pack_counts'] + $pack_counts;
				}

				M('Package')->where(array('id'=>$id))->save($arr1);

			}
			$this->cleanCache();
			$this->success("上传成功", U('Package/index'));
			exit;
		}
		$id= I("get.id");
		$info = '';
		if(!empty($id))
		{
			$info = M('Package')->where(array('id'=>$id))->find();
			$gamename = M('Game')->where(array('id'=>$info['appid']))->field('game_name')->find();

			$this->assign('gamename',$gamename);
		}
		else
		{
			$games = get_game_list('',1,'all');
			$this->assign('games',$games);

		}

		$this->assign('info',$info);
		$this->display();

	}
	public function del()
	{
        $data = [
            'status' => 0,
            'modify_time' => time()
        ];
		if(IS_POST) {
			$ids = I('post.ids');

			if(is_array($ids)) {
				$id_sql = implode(',',$ids);
				$re = M('Package')->where(array('id'=>array('in',$id_sql)))->save($data);
				//如果删除成功，删除相应的礼包码
				if($re!==false) {
					$this->cleanCache();
					$this->success("删除成功！");
				}
				$this->error("删除失败！");
			}
			exit;
		}
		$id = I('get.id');
		$re = M('Package')->where(array('id'=>$id))->save($data);
		if($re!==false) {
			$this->cleanCache();
			$this->success("删除成功！");
		}
		$this->error("删除失败！");

	}

	public function verify()
	{

		$action = I('get.action');

		$ids = I('post.ids');
		$ids_sql = implode(',',$ids);

		$re = M('Package')->where(array('id'=>array('in',$ids_sql)))->save(array('is_verify'=>$action));

		if($re !==false)
		{
			$this->cleanCache();
			$this->success("操作成功！");
		}
		$this->error("操作失败！");
	}

	public function recommend()
	{

		$action = I('get.action');

		$ids = I('post.ids');
		$ids_sql = implode(',',$ids);

		$re = M('Package')->where(array('id'=>array('in',$ids_sql)))->save(array('is_recommend'=>$action));

		if($re !==false)
		{
			$this->cleanCache();
			$this->success("操作成功！");
		}
		$this->error("操作失败！");
	}

	public function export_package()
	{
		$get_data = I('get.');

		$list = M('Package')
		->join('__GAME__ ON __PACKAGE__.appid = __GAME__.id')
		->field('bt_package.*,bt_game.game_name')
		->where(array('bt_package.status'=>1,'appid'=>$get_data['appid']))
		->order('create_time desc')
		->select();

		
		$this->assign('cid',$get_data['cid']);
		$this->assign('list',$list);
		$this->display();
	}


	public function do_export_package()
	{
		set_time_limit(0);
		$data = $_REQUEST;
		if(!empty($data))
		{

			$arr['export']=(int)$data['count'];
			$arr['cid']=(int)$data['cid'];
			$arr['pid']=(int)$data['pid'];
            $arr['singe_export']=(int)$data['singe_export'];
			$arr['create_time']=time();



			if($arr['export']<1){
				echo '该值不能为非负整数！';exit;
			}
			
			if($arr['export']>$arr['singe_export'])
			{

				echo '该值不能大于'.$arr['singe_export'];exit;
			}

			$exp=M('Package')->where(array('id'=>$arr['pid'],'status'=>1))->field('export,appid,pack_counts,pack_get_counts,pack_export_counts')->find();	//可以导出礼包数量
			$channel=M('channel')->where(array('id'=>$arr['cid'],'status'=>1))->field('name')->find();
			if(!$exp){
				echo '参数错误！';exit;
			}
			if(!$channel){
				echo '参数错误！';exit;
     		}



			if((int)$exp['pack_export_counts']<(int)$exp['export']){	//判断导出礼包码数量是否超出范围
				$exp_count=(int)$exp['export']-(int)$exp['pack_export_counts'];

				//剩余可导出
				$exp_count = ($exp_count>($exp['pack_counts']-($exp['pack_get_counts']+$exp['pack_export_counts'])))?($exp['pack_counts']-($exp['pack_get_counts']+$exp['pack_export_counts'])):$exp_count;

				if($arr['export']<=$exp_count){	//判断输入礼包码是否大于剩余可导出礼包码
					$list=M('Package_code')->where(array('pid'=>$arr['pid']))->field('id,card')->limit($arr['export'])->select();	//剩余礼包码数量

					if(!empty($list)){

						if(!empty($_POST)){
							echo 1;exit;
						}

						if($_GET['cid'])
						{
							$game=M('Game')->where(array('id'=>$exp['appid']))->field('tag,game_name')->find();
							$export_count =count($list);
							for($i=0;$i<$export_count;$i++){
								$str_ids.=$list[$i]['id'].',';
							}
							$str_ids=substr($str_ids,0,-1);

							$xlsName  = trim($game['tag'])."-Packs";
							$xlsCell  = array($game['game_name'].' 礼包码');
							$i=0;
							foreach($list as $k=>$v){
								$xlsData[$k]['card']=$v['card'];
							}

							//packs status字段废弃 如果导出，直接删除 ,并在packs_class表记录packs_used
							$re=M('Package_code')->delete($str_ids);

							if($re)
							{
								M('Package')->where(array('id'=>$arr['pid']))->save(array('pack_export_counts'=>$exp['pack_export_counts']+$export_count));
							}

							M('Package_channelget_logs')->add($arr);	//礼包日志
                            $this->cleanCache();
							$this->_exportPacks($xlsName,$xlsCell,$xlsData);


						}

					}else{
						echo '礼包码已被领取一空！';exit;
					}
				}else{
					echo '该游戏可导出数量仅为：'.$exp_count;
				}
			}else{
				//echo '礼包码已被领取一空！';
				echo '该游戏可导出数量仅为：'.$exp_count;
			}

		}else{
			echo '参数错误！';
		}
	}

	//导出excel
	private function _exportPacks($expTitle,$expCellName,$expTableData){
		vendor('PHPExcel.PHPExcel');
		$expTitle=$expTitle.'-'.date('Y-m-d-Hi',time());
		$excel = new \PHPExcel();
		$excel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		$excel->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
		$objActSheet = $excel->getActiveSheet();
		$cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
		for($i = 0;$i < count($expCellName);$i++) {
			$objActSheet->setCellValue("$cellName[$i]1","$expCellName[$i]");
		}
		//设置图片列宽度
		$objActSheet->getColumnDimension('E')->setWidth(30);
		foreach($expTableData as $k=>$v){
			$j = $k+2;
			$objActSheet->getRowDimension($j)->setRowHeight(20);
			$excel->setActiveSheetIndex(0)
			->setCellValue('A'.$j, $v['card']);
		}
		$write = new \PHPExcel_Writer_Excel5($excel);
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
		header("Content-Type:application/force-download");
		header("Content-Type:application/vnd.ms-execl");
		header("Content-Type:application/octet-stream");
		header("Content-Type:application/download");
		header('Content-Disposition: attachment;filename="'.$expTitle.'.xls"');
		header("Content-Transfer-Encoding:binary");
		$write->save('php://output');
		unset($expCellName);
		unset($expTableData);
		exit;
	}
	
	protected function cleanCache()
	{
		//updateCache(C('UPDATE_CACHE_URL'),'clearCache','package','e3479371cae36e78cd3a7d4a214126b5');
	}
	


}