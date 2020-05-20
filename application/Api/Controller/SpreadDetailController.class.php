<?php
/**
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/7/16
 * Time: 15:31
 */
namespace Api\Controller;
use Common\Controller\AppframeController;

class SpreadDetailController extends AppframeController {

    public function spreadData(){
        $time = date('Ymd',strtotime('-1 day'));
        $table = 'bt_report_data'.$time;
        $start = strtotime($time);
        $end = strtotime($time.' 23:59:59');


        M('spread_detail')->where(array('_string'=>"to_days(from_unixtime(regTime)) = to_days({$time})"))->delete();
      
        M('blacklist')->where(array('_string'=>"to_days(from_unixtime(createTime)) = to_days({$time})"))->delete();

        $model = new \Think\Model;
        $sql = "SELECT `channel`,`appid`,a.`deviceID`,`ip`,`userID`,`serverID`,`serverName`,`roleID`,`roleName`,a.roleLevel,`money`,`regTime` 
                FROM {$table} a join (select id,max(roleLevel) roleLevel,deviceID from
                (select * from {$table}  WHERE ( to_days(from_unixtime(regTime)) = to_days({$time}) ) 
                order by roleLevel desc) c group by c.deviceID) b on a.id=b.id GROUP BY a.ip";
        $data = $model->query($sql);

        if($data){
            $black = array();
            $spread = array();
            foreach($data as $k=>$v){
                $status = 0;
                $remark = '';

                $level = M('game')->where(array('id'=>$v['appid']))->getField('reach_level');
                if($v['roleLevel'] < $level){
                    $remark = '等级未达标';
                }

                $res = M('blacklist')->where(array('channel'=>$v['channel'],'deviceID'=>$v['deviceID']))->find();
                if($res){
                    $remark = '设备号重复';
                }else{
                    $black[] = array('channel'=>$v['channel'],'deviceID'=>$v['deviceID'],'createTime'=>strtotime($time));
                }


                //满足达标条件
                if(empty($res) && $v['roleLevel'] >= $level){
                    $status = 1;
                }
                $spread[] = array(
                    'channel'    => $v['channel'],
                    'appid'      => $v['appid'],
                    'uid'        => $v['userID'],
                    'deviceID'   => $v['deviceID'],
                    'ip'         => $v['ip'],
                    'serverID'   => $v['serverID'],
                    'serverName' => $v['serverName'],
                    'roleID'     => $v['roleID'],
                    'roleName'   => $v['roleName'],
                    'reachLevel' => $level,
                    'todayLevel' => $v['roleLevel'],
                    'money'      => $v['money'],
                    'regTime'    => $v['regTime'],
                    'status'     => $status,
                    'remark'     => $remark,
                    'createTime' => strtotime($time)
                );
            }
            M('spread_detail')->addAll($spread);
            spread_reward($spread,$time);
            if(count($black) > 1){
                M('blacklist')->addAll($black);
            }

        }
        M('do_spreadtime')->add(array('time'=>time()));
    }

    public function test(){
        $cid = '77,801,802,803,804,805,806,807,808,809,810,811,812,813,814,815,816,817,818,819,820,821,822,823,824,825,826,827,828,829,830,831,832,833,834,835,836,837,838,839,840,841,842,843,844,845,846,847,848,849,850,851,852,853,854,855,856,857,858,859,860,861,862,863,864,865,866,867,868,869,870,871,872,873,874,875,876,877,878,879,880,881,882,883,884,885,886,887,888,889,890,891,892,893,894,895,896,897,898,899,900,901,902,903,904,905,906,907,908,909,910,911,912,913,914,915,916,917,918,919,920,921,922,923,924,925,926,927,928,929,930,931,932,933,934,935,936,937,938,939,940,941,942,943,944,945,946,947,948,949,950,951,952,953,954,955,956,957,958,959,960,961,962,963,964,965,966,967,968,969,970,971,972,973,974,975,976,977,978,979,980,981,982,983,984,985,986,987,988,989,990,991,992,993,994,995,996,997,998,999,1000,1001,1002,1003,1004,1005,1006,1007,1013,1014,1015,1016,1017,1018,1019,1020,1021,1022,1023,1024,1025,1026,1027,1028,1029,1030,1031,1032,1033,1034,1035,1036,1037,1038,1039,1040,1041,1042,1043,1044,1045,1046,1047,1048,1049';
//        $cid = '77';
        dump(getRegsNum($cid,'2018-7-31',2));
    }

    public function diff(){
        $model = new \Think\Model;
        $sql1 = "select c.id cid from bt_users u join(select admin_id,id from bt_channel where type <>2) c on u.id=c.admin_id
where  u.last_login_time<='2018-04-26' ";
        $res1 = $model->query($sql1);

        foreach($res1 as $k=>$v){
            $login[] = $v['cid'];
        }

        $sql2 = "select i.cid from bt_inpour i join(select admin_id,id from bt_channel where type <>2) c on i.cid=c.id
where  create_time  between 1524672000 and 1532571029 group by c.id";
        $res2 = $model->query($sql2);
        foreach($res2 as $k=>$v){
            $pay[] = $v['cid'];
        }
        echo json_encode(array_values(array_diff($login,$pay)));
    }
}