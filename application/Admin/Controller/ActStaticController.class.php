<?php
/**
 * 盒子玩家行为统计
 * @author liqing
 * @date 2018-09-06
 */

namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class ActStaticController extends AdminbaseController
{
    public function index()
    {
        $action = I('action') ? I('action') : 0;
        //日期最大限制
        $resquest_data = I('request.');
        $max = date('Y-m-d H:i',time());
        $start_time = $resquest_data['start_time']?$resquest_data['start_time']:date('Y-m-d H:i',strtotime('-6 days'));
        $end_time = $resquest_data['end_time']?$resquest_data['end_time']:$max;
        $map = array();
        if($start_time) $map['t1.create_time'][] = array('egt',strtotime($start_time));
        if($end_time) $map['t1.create_time'][] = array('elt',strtotime($end_time));
        if($resquest_data['channel'] > 0) $map['t1.channel'] = $resquest_data['channel'];


        if ($action == 0) {
            $count = M('activity_static')
                ->alias('t1')
                ->where($map)
                ->count();

            $page = $this->page($count, 20);
            $list = M('activity_static')
                ->alias('t1')
                ->join('left join bt_channel t2 on t1.channel = t2.id')
                ->field('t1.*,t2.name')
                ->where($map)
                ->order('t1.create_time desc')
                ->limit($page->firstRow, $page->listRows)->select();

            $this->assign('page', $page->show('Admin'));
            $this->assign('list', $list);
            $this->assign('start_time',$start_time);
            $this->assign('end_time',$end_time);
            $this->assign('max',$max);
            $this->assign('selected_channel_type',$resquest_data['channel_type']);
            $this->assign('channel_type',C('channel_type'));
            $this->display();
        } else {

            $list = M('activity_static')
                ->alias('t1')
                ->join('left join bt_channel t2 on t1.channel = t2.id')
                ->field('t2.name,t1.machine_code,t1.step1,t1.step2,t1.step3,t1.step4,t1.step5,t1.step6,t1.step7,t1.step8,t1.step9,t1.step10,t1.step11,step12,step13,step14,
                step15,step16,step17,step18,step19,step20,t1.is_register,t1.create_time')
                ->where($map)
                ->order('t1.create_time desc')
                ->limit(0, 1000)
                ->select();

                //导出模式
                $xlsTitle = iconv('utf-8', 'gb2312', '盒子玩家行为统计');//文件名称
                $fileName =  '盒子玩家行为统计';//or $xlsTitle 文件名称可根据自己情况设定

                $expCellName = array('渠道', '设备号', '步骤1', '步骤2', '步骤3', '步骤4', '步骤5',
                    '步骤6', '步骤7', '步骤8', '步骤9', '步骤10', '步骤11', '步骤12', '步骤13', '步骤14', '步骤15', '步骤16', '步骤17', '步骤18', '步骤19', '步骤20','完成注册','上报时间');

                $cellNum = count($expCellName);

                $dataNum = count($list);

                vendor("PHPExcel.PHPExcel");

                $objPHPExcel = new \PHPExcel();
                $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

                $objActSheet = $objPHPExcel->getActiveSheet();
                // $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
                for ($i = 0; $i < $cellNum; $i++) {
                    $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '1', $expCellName[$i]);
                }
                // Miscellaneous glyphs, UTF-8
                $field = array('name','machine_code','step1','step2','step3','step4','step5','step6','step7','step8','step9','step10','step11','step12','step13','step14','step15','step16','step17','step18','step19','step20','is_register','create_time');
                for ($i = 0; $i < $dataNum; $i++) {
                    for ($j = 0; $j < $cellNum; $j++) {
                        if ($j == 22)
                        {
                            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i+2), ($list[$i][$field[$j]] == 0)?"否":"是");
                        } elseif ($j == 23)
                        {
                            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i+2), date('Y-m-d H:i:s',$list[$i][$field[$j]]));
                        }
                        else
                        {
                            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+2), $list[$i][$field[$j]]);
                        }

                    }
                }

                header('pragma:public');
                header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
                header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
                $objWriter->save('php://output');
                exit(1);


        }

    }
}