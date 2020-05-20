<?php
namespace Common\Controller;

use Think\Controller;

class AppframeController extends Controller
{

    function _initialize()
    {
        $this->assign("waitSecond", 3);
        $site_options = get_site_options();
        $this->assign($site_options);
        $ucenter_syn = $this->ucenter_enabled;

//        if ($ucenter_syn) {
//
//            $domain = explode('.', $_SERVER['HTTP_HOST']);
//
//            if (file_exists('data/uc_conf/' . $domain['0'] . '/config.inc.php')) {
//
//                include "data/uc_conf/" . $domain['0'] . "/config.inc.php";
//
//
//            } else {
//
//                exit("please add your uc config in the data/uc_conf/" . $domain['0'] . "/config.inc.php file!");
//
//            }
//
//        }
        $time = time();
        $this->assign("js_debug", APP_DEBUG ? "?v=$time" : "");
    }

    public function display($templateFile = '', $charset = '', $contentType = '', $content = '', $prefix = '') {
        parent::display($this->parseTemplate($templateFile), $charset, $contentType,$content,$prefix);
    }

    /**
     * 自动定位模板文件
     * @access protected
     * @param string $template 模板文件规则
     * @return string
     */
    public function parseTemplate($template='') {
        $tmpl_path=C("SP_ADMIN_TMPL_PATH");
        define("SP_TMPL_PATH", $tmpl_path);
        if($this->theme) { // 指定模板主题
            $theme = $this->theme;
        }else{
            // 获取当前主题名称
            $theme      =    C('SP_ADMIN_DEFAULT_THEME');
        }

        if(is_file($template)) {
            // 获取当前主题的模版路径
            define('THEME_PATH',   $tmpl_path.$theme."/");
            return $template;
        }
        $depr       =   C('TMPL_FILE_DEPR');
        $template   =   str_replace(':', $depr, $template);

        // 获取当前模块
        $module   =  MODULE_NAME."/";
        if(strpos($template,'@')){ // 跨模块调用模版文件
            list($module,$template)  =   explode('@',$template);
        }

        $module =$module."/";

        // 获取当前主题的模版路径
        define('THEME_PATH',   $tmpl_path.$theme."/");

        // 分析模板文件规则
        if('' == $template) {
            // 如果模板文件名为空 按照默认规则定位
            $template = CONTROLLER_NAME . $depr . ACTION_NAME;
        }elseif(false === strpos($template, '/')){
            $template = CONTROLLER_NAME . $depr . $template;
        }

        $cdn_settings=sp_get_option('cdn_settings');
        if(!empty($cdn_settings['cdn_static_root'])){
            $cdn_static_root=rtrim($cdn_settings['cdn_static_root'],'/');
            C("TMPL_PARSE_STRING.__TMPL__",$cdn_static_root."/".THEME_PATH);
            C("TMPL_PARSE_STRING.__PUBLIC__",$cdn_static_root."/public");
            C("TMPL_PARSE_STRING.__WEB_ROOT__",$cdn_static_root);
        }else{
            C("TMPL_PARSE_STRING.__TMPL__",__ROOT__."/".THEME_PATH);
        }


        C('SP_VIEW_PATH',$tmpl_path);
        C('DEFAULT_THEME',$theme);
        define("SP_CURRENT_THEME", $theme);

        $file = sp_add_template_file_suffix(THEME_PATH.$module.$template);
        $file= str_replace("//",'/',$file);
        if(!file_exists_case($file)) E(L('_TEMPLATE_NOT_EXIST_').':'.$file);
        return $file;
    }

    /**
     * @param mixed $result
     * @param string $msg
     * @param int $status
     * @param string $type
     * @param int $json_option
     */
    protected function ajaxReturn($result, $msg = '', $status = 1, $type = '', $json_option = 0)
    {


        $data = array(
            'data' => $result,
            'msg' => $msg,
            'status' => $status,
        );

        if(strlen($_GET['callback'])>0)
        {
            $type = 'JSONP';
        }


        //    $data['referer']=$data['url'] ? $data['url'] : "";


        //     $data['state']=$data['status'] ? "success" : "fail";


        if (empty($type)) $type = C('DEFAULT_AJAX_RETURN');

        header("Access-Control-Allow-Origin:*");

        switch (strtoupper($type)) {


            case 'JSON' :


                // 返回JSON数据格式到客户端 包含状态信息


                header('Content-Type:application/json; charset=utf-8');


                exit(json_encode($data, $json_option));


            case 'XML'  :


                // 返回xml格式数据


                header('Content-Type:text/xml; charset=utf-8');


                exit(xml_encode($data));


            case 'JSONP':


                // 返回JSON数据格式到客户端 包含状态信息


                header('Content-Type:application/json; charset=utf-8');


                $handler = isset($_GET[C('VAR_JSONP_HANDLER')]) ? $_GET[C('VAR_JSONP_HANDLER')] : C('DEFAULT_JSONP_HANDLER');


                exit($handler . '(' . json_encode($data, $json_option) . ');');


            case 'EVAL' :


                // 返回可执行的js脚本


                header('Content-Type:text/html; charset=utf-8');


                exit($data);


            case 'AJAX_UPLOAD':


                // 返回JSON数据格式到客户端 包含状态信息


                header('Content-Type:text/html; charset=utf-8');


                exit(json_encode($data, $json_option));


            default :


                // 用于扩展其他返回格式数据


                Hook::listen('ajax_return', $data);


        }


    }

    /**
     *
     * @param number $totalSize 总数
     * @param number $pageSize 总页数
     * @param number $currentPage 当前页
     * @param number $listRows 每页显示条数
     * @param string $pageParam 分页参数
     * @param string $pageLink 分页链接
     * @param string $static 是否为静态链接
     */
    protected function page($totalSize = 1, $pageSize = 0, $currentPage = 1, $listRows = 6, $pageParam = '', $pageLink = '', $static = FALSE)
    {
        if ($pageSize == 0) {
            $pageSize = C("PAGE_LISTROWS");
        }
        if (empty($pageParam)) {
            $pageParam = C("VAR_PAGE");
        }

        $page = new \Page($totalSize, $pageSize, $currentPage, $listRows, $pageParam, $pageLink, $static);

        $page->setLinkWraper("li");
        if (sp_is_mobile()) {
            $page->SetPager('default', '{prev}&nbsp;{list}&nbsp;{next}', array("listlong" => "4", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => ""));
        } else {
            $page->SetPager('default', '{first}{prev}&nbsp;{liststart}{list}{listend}&nbsp;{next}{last}', array("listlong" => "4", "first" => "首页", "last" => "尾页", "prev" => "上一页", "next" => "下一页", "list" => "*", "disabledclass" => ""));
        }

        return $page;
    }

    //空操作
    public function _empty()
    {
        $this->error('该页面不存在！');
    }

    /**
     * 检查操作频率
     * @param int $duration 距离最后一次操作的时长
     */
    protected function check_last_action($duration)
    {

        $action = MODULE_NAME . "-" . CONTROLLER_NAME . "-" . ACTION_NAME;
        $time = time();

        $session_last_action = session('last_action');
        if (!empty($session_last_action['action']) && $action == $session_last_action['action']) {
            $mduration = $time - $session_last_action['time'];
            if ($duration > $mduration) {
                $this->error("您的操作太过频繁，请稍后再试~~~");
            } else {
                session('last_action.time', $time);
            }
        } else {
            session('last_action.action', $action);
            session('last_action.time', $time);
        }
    }

    /**
     * 模板主题设置
     * @access protected
     * @param string $theme 模版主题
     * @return Action
     */
    public function theme($theme)
    {
        $this->theme = $theme;
        return $this;
    }

}