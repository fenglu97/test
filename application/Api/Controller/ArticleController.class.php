<?php
/**
 * 咨询接口
 * @author liqing
 * @date 2018-04-08
 */

namespace Api\Controller;
use Common\Controller\AppframeController;

class ArticleController extends AppframeController
{
    /**
     * 咨询详情
     */
    public function info()
    {
        $id = I('id');
        $channel = I('channel');

        if(empty($id) || empty($channel))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'id'=>$id,
            'channel'=>$channel,
            'sign'=>I('sign'),
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        //根据该ID获取文章内容
        $Model = new \Think\Model(null,null,C('185DB')); // 实例化一个model对象 没有对应任何数据表



        $sql = 'select t1.id,t1.title,t1.author,t1.source,t1.release_time,t1.content,t1.game_id
        from `syo_article` t1 join `syo_term_relationships` t2
          on t1.id = t2.object_id where t2.tid = '.$id.' and t2.status = 1 and t1.is_verify = 1 ';
        $article_info = $Model->query($sql);


        if(!$article_info)
        {
            $this->ajaxReturn(null,'资讯不存在',1);
        }


        M('syo_article',null,C('185DB'))->where(array('id'=>$article_info[0]['id']))->setInc('view_counts',1);

        //获取相关阅读 同款游戏的资讯取3个


        $sql = "SELECT t1.title,t2.tid FROM `syo_article`
        t1 join `syo_term_relationships` t2 on t1.id = t2.object_id
        WHERE t1.game_id = {$article_info[0]['game_id']} AND t1.is_verify = 1 and t2.status = 1
        group by t1.id order by t2.listorder DESC LIMIT 0,3 ";
        $xiangguan_info  = $Model->query($sql);

        $data = array(
            'article_info'=>$article_info[0],
            'xiangguan_info'=>$xiangguan_info,
        );

        $this->ajaxReturn($data);
    }





    /**
     * 独家活动
     */
    public function exclusive_list()
    {
        $platform = I('platform');

        if(empty($platform))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'platform'=>$platform,
            'sign'=>I('sign')
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        $data = M('syo_app_slide',null,C('185DB'))
            ->field('url,slide_pic')->where(array('type'=>3,'display'=>1,'status'=>0,'slide_cat'=>4,'platform'=>$platform))
            ->order('listorder asc')
            ->select();

        foreach($data as $k=>$v) {
            $data[$k]['slide_pic'] = C('185SY_URL') . $v['slide_pic'];

            preg_match('/\d+/',strrchr($v['url'],'/'),$matches);
            $data[$k]['article_id'] =$matches[0];
            $data[$k]['title'] = M('syo_term_relationships',null,C('185DB'))
                ->alias('t1')
                ->join('syo_article t2 on t1.object_id= t2.id')
                ->where(Array('t1.tid'=>$data[$k]['article_id']))
                ->getfield('t2.title');

        }

        $this->ajaxReturn($data);
    }

    public function share()
    {
        $channel = I('channel');
        $article_id = I('article_id');

        if(empty($article_id) || empty($channel))
        {
            $this->ajaxReturn(null,'参数不能为空',0);
        }

        $arr = array(
            'channel'=>$channel,
            'article_id'=>$article_id,
            'sign'=>I('sign')
        );

        $res = checkSign($arr,C('API_KEY'));

        if(!$res)
        {
            $this->ajaxReturn(null,'签名错误',0);
        }

        if(M('syo_article',null,C('185DB'))->where(array('id'=>$article_id))->setInc('shares',1)!==false)
        {
            $this->ajaxReturn(null,'操作成功');
        }

        $this->ajaxReturn(null,'操作失败',0);
    }





}