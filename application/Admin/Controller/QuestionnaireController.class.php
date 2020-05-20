<?php
/**
 * 问卷调查
 * Created by PhpStorm.
 * User: fantasmic
 * Date: 2018/7/2
 * Time: 10:20
 */
namespace Admin\Controller;
use Common\Controller\AdminbaseController;

class QuestionnaireController extends AdminbaseController{

    /**
     * 问卷列表（管理员）
     */
    public function adminIndex(){
        $where = '';
        $title = trim(I('title'));
        if($title) $where['title'] = array('like','%'.$title.'%');
        $count = M('questionnaire')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('questionnaire')->where($where)->limit($page->firstRow,$page->listRows)->order('id desc')->select();
        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->title = $title;
        $this->display();
    }

    /**
     * 问卷列表（用户）
     */
    public function userIndex(){
        $uid = session('ADMIN_ID');
        $where['open'] = 1;
        $title = trim(I('title'));
        if($title) $where['title'] = array('like','%'.$title.'%');

        $count = M('questionnaire')->where($where)->count();
        $page = $this->page($count,20);
        $data = M('questionnaire')->where($where)->limit($page->firstRow,$page->listRows)->order('id desc')->select();
        $user = M('survey_user')->where(array('uid'=>$uid))->getField('qid,create_time',true);

        foreach($data as $k=>$v){
            if(isset($user[$v['id']])){
                $data[$k]['answer'] = 1;
                $data[$k]['answer_time'] = $user[$v['id']];
            }else{
                $data[$k]['answer'] = 0;
            }
        }
        $this->page = $page->show('Admin');
        $this->data = $data;
        $this->title = $title;
        $this->display();
    }

    /**
     * 新增问卷
     */
    public function add(){
        if(IS_POST){
            $data = json_decode($_POST['data'],1);
            if(empty($data['questionItems'])){
                $this->error('请设置题型');
            }else{
                $start = $data['start'];
                $end = $data['end'];
                if($start == ''){
                    $start = time();
                }else{
                    $start = strtotime($start);
                }
                if($end == ''){
                    $end = strtotime('+15 day',$start);
                }else{
                    $end = strtotime($end);
                }
                if($start && $end && ($start > $end || $end < $start || $start < time()) ){
                    $this->error('请选择正确时间');
                }
                $qid = M('questionnaire')->add(array('title'=>strip_tags($data['questionTitle']),'create_time'=>time(),'start'=>$start,'end'=>$end));
                foreach($data['questionItems'] as $k=>$v){
                    $pid = M('questionnaire_problem')->add(array('qid'=>$qid,'name'=>strip_tags($v['QItemsTitle']),'type'=>$v['type']));
                    if($v['type'] != 3){
                        foreach($v['qListItems'] as $option){
                            M('questionnaire_option')->add(array('qid'=>$qid,'pid'=>$pid,'name'=>strip_tags($option['value'])));
                        }
                    }
                }
            }
            $this->success('操作成功');
        }else{
            $this->display();
        }
    }

    /**
     * 用户回答
     */
    public function answer(){
        if(IS_POST){
            $data = json_decode($_POST['data'],1);
            if(M('survey_user')->where(array('uid'=>$data['uid'],'qid'=>$data['qid']))->find()){
                $this->error('请勿重复提交');
            }
            $id = M('survey_user')->add(array('uid'=>$data['uid'],'qid'=>$data['qid'],'create_time'=>time()));
            foreach($data['listItem'] as $k=>$v){
                $add[] = array(
                    'sid' => $id,
                    'uid' => $data['uid'],
                    'pid' => $v['pid'],
                    'type' => $v['type'],
                    'value' => $v['value']
                );
            }
            M('survey_answer')->addAll($add);
            M('questionnaire')->where(array('id'=>$data['qid']))->setInc('count',1);
            $this->success('操作成功');
        }else{
            $qid = I('qid');
            $type = I('type');
            //检查时间
            $info = M('questionnaire')->where(array('id'=>$qid))->find();
            if($type == 2){
                $now = time();
                if($now < $info['start'] || $now > $info['end']){
                    $this->error('请关注问卷开始和结束时间');
                }
            }


            $data['title'] = $info['title'];
            $data['questionItems'] = M('questionnaire_problem')->where(array('qid'=>$qid))->select();
            foreach($data['questionItems'] as $k=>$v){
                $data['questionItems'][$k]['option'] = M('questionnaire_option')->where(array('pid'=>$v['id']))->select();
            }
            if($type == 1){
                $user = M('survey_user su')
                        ->field('sa.*')
                        ->join('left join __SURVEY_ANSWER__ sa on su.id=sa.sid')
                        ->where(array('su.qid'=>$qid,'su.uid'=>session('ADMIN_ID')))
                        ->select();
                foreach($user as &$v){
                    if($v['type'] == 2){
                        $v['value'] = explode(',',$v['value']);
                    }
                }
                $this->user = $user;
            }

            $this->data = $data;
            $this->qid = $qid;
            $this->type = $type;
            $this->display();
        }
    }

    /**
     * 问卷分析
     */
    public function analysis(){
        $id = I('id');
        $data['title'] = M('questionnaire')->where(array('id'=>$id))->getField('title');
        $data['question'] = M('questionnaire_problem')->where(array('qid'=>$id))->select();
        //问卷回答人数
        $total_answer = M('survey_user')->where(array('qid'=>$id))->count();

        foreach($data['question'] as $k=>&$v){
            if($v['type'] != 3){
                $v['option'] = M('questionnaire_option')->where(array('pid'=>$v['id']))->select();
                foreach($v['option'] as &$v1){
                    $v1['count'] = M('survey_answer')->where(array('pid'=>$v['id'],'_string'=>"FIND_IN_SET({$v1['id']},value)"))->count();
                    $v1['scale'] = $v1['count'] ? round($v1['count'] / $total_answer * 100,1) : 0;
                }
            }else{
                $v['answer'] = M('survey_answer')->field('value')->where(array('pid'=>$v['id']))->select();
            }
        }

        $this->data = $data;
        $this->display();
    }

    /**
     * 样本列表
     */
    public function sampleList(){
        $id = I('id');
        $data = M('survey_user s')
                ->field('s.uid,u.user_login,c.name,c.id,s.create_time')
                ->join('left join __USERS__ u on s.uid=u.id')
                ->join('left join __CHANNEL__ c on c.admin_id=u.id')
                ->where(array('s.qid'=>$id))
                ->select();
        $this->id = $id;
        $this->data = $data;
        $this->display();
    }

    /**
     * 样本答卷
     */
    public function sampleInfo(){
        $qid = I('qid');
        $uid = I('uid');

        $data['title'] = M('questionnaire')->where(array('id'=>$qid))->getField('title');
        $data['questionItems'] = M('questionnaire_problem')->where(array('qid'=>$qid))->select();
        foreach($data['questionItems'] as $k=>$v){
            $data['questionItems'][$k]['option'] = M('questionnaire_option')->where(array('pid'=>$v['id']))->select();
        }

        $user = M('survey_user su')
            ->field('sa.*')
            ->join('left join __SURVEY_ANSWER__ sa on su.id=sa.sid')
            ->where(array('su.qid'=>$qid,'su.uid'=>$uid))
            ->select();
        foreach($user as &$v){
            if($v['type'] == 2){
                $v['value'] = explode(',',$v['value']);
            }
        }
        $this->user = $user;
        $this->data = $data;
        $this->display();
    }

    /**
     * 开启、关闭问卷
     */
    public function changeStatus(){
        $id = I('id',0);
        $status = I('status',0);

        if(M('questionnaire')->where(array('id'=>$id))->setField('open',$status) !== false){
            $this->success();
        }else{
            $this->error('修改失败');
        }
    }

    /**
     * 删除
     */
    public function del(){
        $id = I('id');
        $model = M();
        $model->startTrans();
        $res1 = M('questionnaire')->where(array('id'=>$id))->delete();
        $res2 = M('questionnaire_problem')->where(array('qid'=>$id))->delete();
        $res3 = M('questionnaire_option')->where(array('qid'=>$id))->delete();

        $sid = M('survey_user')->where(array('qid'=>$id))->getField('id');
        $res4 = M('survey_answer')->where(array('sid'=>$sid))->delete();
        $res5 = M('survey_user')->where(array('qid'=>$id))->delete();
        if($res1 !== false && $res2 !== false && $res3 !== false && $res4 !== false && $res5 !== false){
            $model->commit();
            $this->success('操作成功');
        }else{
            $model->rollback();
            $this->error('操作失败');
        }

    }

    /**
     * 检查用户问卷回答量
     */
    public function checkData(){
        $uid = session('ADMIN_ID');
        if(M('role_user')->where(array('user_id'=>$uid,'role_id'=>15))->find()){
            $count1 = M('questionnaire')->where(array('open'=>1,'start'=>array('lt',time()),'end'=>array('gt',time())))->getField('id',true);
            $count2 = M('survey_user')->where(array('uid'=>$uid,'qid'=>array('in',implode(',',$count1))))->getField('qid',true);

            if(count($count1) == count($count2)){
                $this->success();
            }else{
                $this->error();
            }
        }else{
            $this->success();
        }
    }
}