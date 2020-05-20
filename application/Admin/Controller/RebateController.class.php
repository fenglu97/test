<?php
/**
 * 返利控制器
 * User: fantasmic
 * Date: 2017/7/12
 * Time: 11:02
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class RebateController extends AdminbaseController{

    /**
     * 返利列表
     */
    public function index(){
     
        $username = I('username');
        $founder = I('founder');
        $appid = I('appid',-1);
        $status = I('status',-1);
        $review = I('review',-1);
        $start = I('start');
        $end = I('end');
        $where = '';
        if($username) $where['username'] = $username;
        if($founder){
            $uid = M('users')->where(array('user_type'=>1,'user_login'=>$founder))->getField('id');
            $where['founder'] = $uid ? $uid : 0;
        }
        if($appid != -1){
            $where['appid'] = $appid;
        }else{
            if(session('game_role') != 'all'){
                $where['appid'] = array('in',session('game_role'));
            }
        }
        if($status != -1) $where['status'] = $status;
        if($review != -1) $where['review'] = $review;
        if($start) $where['create_time'][] = array('gt',strtotime($start));
        if($end) $where['create_time'][] = array('lt',strtotime($end.' 23:59:59'));


        $users = M('users')->where(array('user_type'=>1))->getField('id,user_login',true);
        $games = M('game')->getField('id,game_name',true);

        if(empty($_GET['action'])){
            $count = M('rebate')->where($where)->count();
            $page = $this->page($count, 20);
            $data = M('rebate')->where($where)->limit($page->firstRow, $page->listRows)->order('id desc')->select();

            $this->page = $page->show('Admin');
            $this->games = $games;
            $this->users = $users;
            $this->data = $data;
            $this->username = $username;
            $this->founder = $founder;
            $this->appid = $appid;
            $this->status = $status;
            $this->review = $review;
            $this->start = $start;
            $this->end = $end;
            $this->display();
        }else{
            if(empty($start) || empty($end)){
                $this->error('请选择导出时间，最长1个月');
            }elseif(strtotime($end) - strtotime($start) > 2678400){
                $this->error('请选择导出时间，最长1个月');
            }
//            dump($where);die;
            $data = M('rebate')->where($where)->order('id desc')->select();
            //导出模式
            foreach($data as $k=>&$v){
                $v['appid'] = $games[$v['appid']];
                switch ($v['type']){
                    case 1:$v['type'] = '推广奖励'; break;
                    case 2:$v['type'] = '充值赠送'; break;
                    case 3:$v['type'] = '游戏补偿'; break;
                    default :$v['type'] = '其他原因';
                }
                switch ($v['status']){
                    case 1:$v['status'] = '成功'; break;
                    case 2:$v['status'] = '未请求'; break;
                    case 0:$v['status'] = '失败'; break;
                    default :$v['status'] = '其他原因';
                }
                $v['create_time'] = date('Y-m-d H:i',$v['create_time']);
                $v['is_review'] = $v['is_review'] == 1 ? '是' : '否';
                switch ($v['review']){
                    case 1:$v['review'] = '审核通过'; break;
                    case 2:$v['review'] = '未审核'; break;
                    default :$v['review'] = '审核失败';
                }
                $v['review_time'] = $v['review_time'] ? date('Y-m-d H:i',$v['review_time']) : '';
                $v['founder'] = $users[$v['founder']];

            }
            $xlsTitle = iconv('utf-8', 'gb2312', '返利统计');//文件名称
            $fileName = date('YmdHis').'返利统计';//or $xlsTitle 文件名称可根据自己情况设定

            $expCellName = array('订单号','账号','游戏','区服','金额','返利类型','订单状态','创建时间','是否需要审核','审核状态','审核时间','创建人');

            $cellNum = count($expCellName);
            $dataNum = count($data);

            vendor("PHPExcel.PHPExcel");

            $objPHPExcel = new \PHPExcel();
            $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

            $objActSheet = $objPHPExcel->getActiveSheet();
            // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
            for($i=0;$i<$cellNum;$i++){
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'1', $expCellName[$i]);
            }
            // Miscellaneous glyphs, UTF-8
            $filed = array('orderID','username','appid','serverID','amount','type','status','create_time','is_review','review','review_time','founder');
            for($i=0;$i<$dataNum;$i++){
                for($j=0;$j<$cellNum;$j++){
                    $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $data[$i][$filed[$j]]);
                }
            }

            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
            exit();
        }

    }

    /**
     * 新增返利
     */
    public function add(){
        if(IS_POST){
            $model = D('Common/Rebate');
            if($info = $model->create()){
                if($model->add()){
//                    $res = $model->where(array('id'=>$id))->getField('is_review');
                    if($info['is_review'] == 0) $model->requestApi($info);
                    $this->success('操作成功');
                }else{
                    $this->error('操作失败');
                }
            }else{
                $this->error($model->getError());
            }
        }else{
            $op = M('options')->where(array('option_name'=>'site_options'))->getField('option_value');
            $rebate = json_decode($op,true);
            $this->rebateSingle = $rebate['rebateSingle'] ? $rebate['rebateSingle'] : 1000;
            $this->rebateTotal = $rebate['rebateTotal'] ? $rebate['rebateTotal'] : 10000;
            $this->display();
        }
    }

    /**
     * 返利审核列表
     */
    public function checkList(){
        $username = I('username');
        $founder = I('founder');
        $appid = I('appid',-1);
        $start = I('start');
        $end = I('end');
        if($username) $where['username'] = $username;
        if($founder){
            $uid = M('users')->where(array('user_type'=>1,'user_login'=>$founder))->getField('id');
            $where['founder'] = $uid ? $uid : 0;
        }
        if($appid != -1) $where['appid'] = $appid;
        if($start) $where['create_time'] = array('gt',strtotime($start));
        if($end) $where['create_time'] = array('lt',strtotime($end.' 23:59:59'));

        $where['is_review'] = 1;
        $where['review'] = 2;

        $data = M('rebate')->where($where)->order('id desc')->select();
        $count = count($data);
        $page = $this->page($count, 20);
        $data = array_slice($data,$page->firstRow, $page->listRows);

        $this->data = $data;
        $this->page = $page->show('Admin');
        $this->games = M('game')->getField('id,game_name',true);
        $this->users = M('users')->where(array('user_type'=>1))->getField('id,user_login',true);
        $this->username = $username;
        $this->founder = $founder;
        $this->appid = $appid;
        $this->start = $start;
        $this->end = $end;
        $this->display();
    }


    /**
     * 审核单个
     */
    public function reviewSingle(){
        $id = I('id');
        $state = I('state');
        $text = I('text');
        if(M('rebate')->where(array('id'=>$id))->setField(array('review'=>$state,'feedback'=>$text,'review_time'=>time())) !== false){
            $info = M('rebate')->where(array('id'=>$id))->find();
            if($state) D('Common/Rebate')->requestApi($info);
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 批量通过
     */
    public function allPass(){
        $model = D('Common/Rebate');
        $id = I('id');
        $ids = implode(",",$id);
        if(M('rebate')->where(array('id'=>array('in',$ids)))->setField(array('review'=>1,'review_time'=>time())) !== false){
            foreach($id as $v){
	    	$info = M('rebate')->where(array('id'=>$v))->find();
                $model->requestApi($info);
            }
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 批量失败
     */
    public function allFail(){
        $id = I('id');
        $ids = implode(",",$id);
        if(M('rebate')->where(array('id'=>array('in',$ids)))->setField(array('review'=>0,'review_time'=>time())) !== false){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 获取区服数据
     */
    public function getServer(){
        $appid = I('appid');
        $info = M('game')->field('serverurl,notice')->where(array('id'=>$appid))->find();

        $con = curl_init($info['serverurl'].'?appid='.$appid);
        curl_setopt($con, CURLOPT_HEADER, false);
        curl_setopt($con, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($con, CURLOPT_TIMEOUT, 4);
	    curl_setopt($con, CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($con, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($con, CURLOPT_SSL_VERIFYHOST, 0);
        $data = curl_exec($con);

        curl_close($con);

        $option = '';
        $data = json_decode(trim($data,chr(239).chr(187).chr(191)),true);
        if(is_array($data)){
            //json_decode
            foreach ($data['data'] as $k=>$v){
                $option .= "<option value='{$v['serverID']}'>{$v['serverName']}</option>";
            }
            $this->ajaxReturn( array(
                'info' => '',
                'status'=> 1,
                'data' => $option,
                'notice'=>$info['notice']
            ));
        }else{
            $this->error('区服请求失败');
        }
    }

    /**
     * 检测是否满足返利条件
     */
    public function checkRebate(){
        $appid = I('appid');
        $info = M('game')->field('bpayurl,serverurl')->where(array('id'=>$appid))->find();
        if($info['bpayurl'] == '' || $info['serverurl'] == ''){
            $this->error();
        }else{
            $this->success();
        }
    }
    
    /**
     * 获取元宝赠送
     */
    public function yuanbao_bonus()
    {
    	$appid = I('appid');
    	$money = I('money');
    	$reward = I('reward');
    
    	$appinfo = M('game')->where(array('id'=>$appid))->field('topup_scale,give_scale')->find();
    	
    	//满50 额外赠送
    	if($reward =='true')
    	{
    		if($money >= 50)
    		{
    			$bonus = $money*(1+$appinfo['give_scale'])*$appinfo['topup_scale'];
    		}
    		else 
    		{
    			$bonus = $money * $appinfo['topup_scale'];
    		}
    		
    	}
    	else 
    	{
    	    $bonus = $money * $appinfo['topup_scale'];
    	}
    	
    	exit(json_encode(array('bonus'=>$bonus)));
    	
    }
}