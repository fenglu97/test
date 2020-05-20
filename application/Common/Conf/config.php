<?php
if(file_exists("data/conf/db.php")){
    $db=include "data/conf/db.php";
}else{
    $db=array();
}
if(file_exists("data/conf/config.php")){
    $runtime_config=include "data/conf/config.php";
}else{
    $runtime_config=array();
}

if (file_exists("data/conf/route.php")) {
    $routes = include 'data/conf/route.php';
} else {
    $routes = array();
}

$configs= array(
    "LOAD_EXT_CONFIG" => 'web_site_options',
    "LOAD_EXT_FILE"=>"extend",
    'UPLOADPATH' => 'data/upload/',
    //'SHOW_ERROR_MSG'        =>  true,    // 显示错误信息
    'SHOW_PAGE_TRACE'		=> false,
    'TMPL_STRIP_SPACE'		=> true,// 是否去除模板文件里面的html空格与换行
    'THIRD_UDER_ACCESS'		=> false, //第三方用户是否有全部权限，没有则需绑定本地账号
    /* 标签库 */
    'TAGLIB_BUILD_IN' => THINKCMF_CORE_TAGLIBS,
    'MODULE_ALLOW_LIST'  => array('Admin','Portal','Asset','Api','User','Wx','Comment','Qiushi','Tpl','Topic','Install','Bug','Better','Cas'),
    'TMPL_DETECT_THEME'     => false,       // 自动侦测模板主题
    'TMPL_TEMPLATE_SUFFIX'  => '.html',     // 默认模板文件后缀
    'DEFAULT_MODULE'        =>  'Admin',  // 默认模块
    'DEFAULT_CONTROLLER'    =>  'public', // 默认控制器名称
    'DEFAULT_ACTION'        =>  'login', // 默认操作名称
    'DEFAULT_M_LAYER'       =>  'Model', // 默认的模型层名称
    'DEFAULT_C_LAYER'       =>  'Controller', // 默认的控制器层名称

    'DEFAULT_FILTER'        =>  'htmlspecialchars', // 默认参数过滤方法 用于I函数...htmlspecialchars

    'LANG_SWITCH_ON'        =>  true,   // 开启语言包功能
    'DEFAULT_LANG'          =>  'zh-cn', // 默认语言
    'LANG_LIST'				=>  'zh-cn,en-us,zh-tw',
    'LANG_AUTO_DETECT'		=>  true,
    'ADMIN_LANG_SWITCH_ON'        =>  false,   // 后台开启语言包功能

    'VAR_MODULE'            =>  'g',     // 默认模块获取变量
    'VAR_CONTROLLER'        =>  'm',    // 默认控制器获取变量
    'VAR_ACTION'            =>  'a',    // 默认操作获取变量

    'APP_USE_NAMESPACE'     =>   true, // 关闭应用的命名空间定义
    'APP_AUTOLOAD_LAYER'    =>  'Controller,Model', // 模块自动加载的类库后缀

    'SP_TMPL_PATH'     		=> 'themes/',       // 前台模板文件根目录
    'SP_DEFAULT_THEME'		=> 'simplebootx',       // 前台模板文件
    'SP_TMPL_ACTION_ERROR' 	=> 'error', // 默认错误跳转对应的模板文件,注：相对于前台模板路径
    'SP_TMPL_ACTION_SUCCESS' 	=> 'success', // 默认成功跳转对应的模板文件,注：相对于前台模板路径
    'SP_ADMIN_STYLE'		=> 'flat',
    'SP_ADMIN_TMPL_PATH'    => 'admin/themes/',       // 各个项目后台模板文件根目录
    'SP_ADMIN_DEFAULT_THEME'=> 'simplebootx',       // 各个项目后台模板文件
    'SP_ADMIN_TMPL_ACTION_ERROR' 	=> 'Admin/error.html', // 默认错误跳转对应的模板文件,注：相对于后台模板路径
    'SP_ADMIN_TMPL_ACTION_SUCCESS' 	=> 'Admin/success.html', // 默认成功跳转对应的模板文件,注：相对于后台模板路径
    'TMPL_EXCEPTION_FILE'   => SITE_PATH.'public/exception.html',

    'AUTOLOAD_NAMESPACE' => array('plugins' => './plugins/'), //扩展模块列表

    'ERROR_PAGE'            =>'',//不要设置，否则会让404变302

    'VAR_SESSION_ID'        => 'session_id',

    "UCENTER_ENABLED"		=>0, //UCenter 开启1, 关闭0
    "COMMENT_NEED_CHECK"	=>0, //评论是否需审核 审核1，不审核0
    "COMMENT_TIME_INTERVAL"	=>60, //评论时间间隔 单位s

    /* URL设置 */
    'URL_CASE_INSENSITIVE'  => false,   // 默认false 表示URL区分大小写 true则表示不区分大小写
    'URL_MODEL'             => 0,       // URL访问模式,可选参数0、1、2、3,代表以下四种模式：
    // 0 (普通模式); 1 (PATHINFO 模式); 2 (REWRITE  模式); 3 (兼容模式)  默认为PATHINFO 模式，提供最好的用户体验和SEO支持
    'URL_PATHINFO_DEPR'     => '/',	// PATHINFO模式下，各参数之间的分割符号
    'URL_HTML_SUFFIX'       => '',  // URL伪静态后缀设置

    'VAR_PAGE'				=>"p",

    'URL_ROUTER_ON'			=> true,
    'URL_ROUTE_RULES'       => $routes,

    /*性能优化*/
    'OUTPUT_ENCODE'			=>true,// 页面压缩输出

    'HTML_CACHE_ON'         =>    false, // 开启静态缓存
    'HTML_CACHE_TIME'       =>    60,   // 全局静态缓存有效期（秒）
    'HTML_FILE_SUFFIX'      =>    '.html', // 设置静态缓存文件后缀
    'DEFAULT_AJAX_RETURN'   =>'JSON',
    'TMPL_PARSE_STRING'=>array(
        '/Public/upload'=>'/data/upload',
        '__UPLOAD__' => __ROOT__.'/data/upload/',
        '__STATICS__' => __ROOT__.'/statics/',
        '__WEB_ROOT__'=>__ROOT__
    ),



    /*FTP上传设置*/
    'FILE_UPLOAD_TYPE'    =>    'Local',
    'UPLOAD_TYPE_CONFIG'  =>    array(
        'host'     => 'ftps3.verycloud.cn', //服务器
        'port'     => '21', //端口
        'timeout'  => '900', //超时时间
        'username' => 'sy217/sy217', //用户名
        'password' => 'sy217@VeryCDN', //密码
    ),
    'FTP_URL'=>'',
    'PLATFORM_COIN_RATIO' => 10,//平台币充值比例

    'REDIS_PASS' => 'sesuoe123$sdf',


    //游戏平台
    'PLATFORM' => array(1=>'BT游戏'),
    //充值表中支付方式
    'PAY_TYPE_INPOUR' =>array(1=>'支付宝扫码',2=>'支付宝手机',3=>'微信扫码',4=>'微信手机',10=>'平台币',11=>'支付宝新手机'),
    /*母包上传配置*/
    'UPLOAD_TOKEN' => 'http://download.singmaan.com/upload/token',//获取上传token
    'UPLOAD_URL' => 'http://download.singmaan.com:8088/upload/multi',//上传地址
    'UP_USER' => 'zhongtuisdk',
    'UP_PASS' => md5('321@jushu!@#321'),
    'UP_SIGN' => md5("username=zhongtuisdk&password=".md5('321@jushu!@#321')."&key=dcdf133425c5c9510646b131c2952577"),  // 这个key值不变
    'ANDROID_PATH' => '/apk/',
    'IOS_PATH' => '/ipa/',
    'UP_PLATFORM' => 'singmaan',//上传平台
    //本站支付密钥
    '185KEY' => '~f!a@n#t$a%s^m&i*c',
    //接口密钥
    'API_KEY' => 'e10adc3949ba59abbe56e057f20f883e',
    'MAIN_CHANNEL'=>1,
    'DATA_CACHE_TIME'=>1,
    'API_URL'=>'http://sdk.singmaan.com',
    'BOX_URL'=>'http://box.singmaan.com',

    'BOX_APP_ID'=>1000, //185盒子APPID
    '185SY_URL'=>'http://box.singmaan.com',
    'BOX_DOWNLOAD_URL'=>'http://download.singmaan.com',


    'SIGN_CONFIG'=>array(
        'DAY_BONUS'=>array(
            'normal'=>5,
            'vip_extra'=>66,
        ),
        'ACCUM_BONUS'=>array(
            array('num'=>3, 'bonus'=>20),
            array('num'=>7, 'bonus'=>50),
            array('num'=>15, 'bonus'=>100),
            array('num'=>'all', 'bonus'=>200),
        )
    ),

    'TG_ROLD_ID'=>10,

    'QUESTION_TYPE'=>
        array(
            array('id'=>1,'name'=>'不能支付'),
            array('id'=>2,'name'=>'角色数据丢失'),
            array('id'=>3,'name'=>'游戏闪退'),
            array('id'=>4,'name'=>'游戏登录问题'),
            array('id'=>5,'name'=>'游戏回档'),
            array('id'=>6,'name'=>'支付不到账'),
            array('id'=>7,'name'=>'其它问题'),
        ),

    'CHANNEL_QUESTION_TYPE'=>
        array(
            array('id'=>1,'name'=>'充值返利'),
            array('id'=>2,'name'=>'推广分享'),
            array('id'=>3,'name'=>'登陆闪退','order'=>1),
            array('id'=>4,'name'=>'角色数据丢失','order'=>1),
            array('id'=>5,'name'=>'充值不到账','order'=>1),
            array('id'=>7,'name'=>'游戏bug'),
            array('id'=>8,'name'=>'游戏问题及活动咨询'),
            array('id'=>6,'name'=>'其它问题'),
        ),


    'DAY_QUESTION_LIMIT'=>3,
    'TG_LINK'=>'http://p.185sy.com/tg_register.html', //推广链接
    'TG_REGISTER_BONUS'=>50,

    'DRIVER_LEVEL'=>array(
        array('level'=>1,'fans_counts'=>'0-100'),
        array('level'=>2,'fans_counts'=>'101-200'),
        array('level'=>3,'fans_counts'=>'201-500'),
        array('level'=>4,'fans_counts'=>'501-1000'),
        array('level'=>5,'fans_counts'=>'1001-'),
    ),  //老司机指数

    'DYNAMICS_LIKE_BONUS'=>2, //开车当日首次点赞奖励
    'DYNAMICS_COMMENT_BONUS'=>array(
        array('num'=>1,'bonus'=>2),
        array('num'=>10,'bonus'=>4),
    ),//开车当日首次评论奖励 评论十次奖励

    'DRIVE_BONUS'=>array(
        array('level'=>'S','bonus'=>30),
        array('level'=>'A','bonus'=>20),
        array('level'=>'B','bonus'=>10),
        array('level'=>'C','bonus'=>5),
    ),//发车奖励
    'IP_REGISTER_LIMIT'=>30, //当天IP注册上限

    //游戏评论奖励
    'GAME_COMMENT_BONUS'=>'3-10',


    'CHANNEL_TYPE'=>array(
        1=>'常用',
        2=>'自推广',
        3=>'自投放',
        4=>'一般',
        5=>'废弃',
    ), //渠道类型


    'ZTG_TOP_YG'=>array(
        'start'=>3000,
        'range'=>array(
            array('money'=>'3001-7999','commision'=>'10%'),
            array('money'=>'8000-14999','commision'=>'12%'),
            array('money'=>'15000-19999','commision'=>'15%'),
            array('money'=>'20000-24999','commision'=>'18%'),
            array('money'=>'25000-29999','commision'=>'21%'),
            array('money'=>'30000-34999','commision'=>'25%'),
            array('money'=>'35000-49999','commision'=>'30%'),
            array('money'=>'50000-69999','commision'=>'40%'),
            array('money'=>'70000-','commision'=>'50%')),
    ),//推广前十提成

    'ZTG_YG'=>array(
        'start'=>3000,
        'range'=>array(
            array('money'=>'3001-8999','commision'=>'4%'),
            array('money'=>'9000-14999','commision'=>'6%'),
            array('money'=>'15000-19999','commision'=>'8%'),
            array('money'=>'20000-24999','commision'=>'10%'),
            array('money'=>'25000-29999','commision'=>'12%'),
            array('money'=>'30000-39999','commision'=>'15%'),
            array('money'=>'40000-49999','commision'=>'18%'),
            array('money'=>'50000-59999','commision'=>'24%'),
            array('money'=>'60000-79999','commision'=>'30%'),
            array('money'=>'80000-99999','commision'=>'36%'),
            array('money'=>'100000-119999','commision'=>'42%'),
            array('money'=>'120000-','commision'=>'50%'),
        ),

    ),//推广提成

        //军衔配置
    'V_PLAN' => array(
        'stage' => array(
            //num-提成百分比，level-等级，name-等级名，color-颜色标识，mark-流水阶段，bonus-流水奖金，hols-带薪休假
            array('num'=>'/','level'=>1,'name'=>'士兵','color'=>'#494949','mark'=>0,'bonus'=>0,'hols'=>'/'),
            array('num'=>'4%','level'=>2,'name'=>'士官','color'=>'#413330','mark'=>3001,'bonus'=>50,'hols'=>'/'),
            array('num'=>'6%','level'=>3,'name'=>'副排长','color'=>'#574a3d','mark'=>10001,'bonus'=>100,'hols'=>'/'),
            array('num'=>'8%','level'=>4,'name'=>'排长','color'=>'#77684c','mark'=>20001,'bonus'=>200,'hols'=>'/'),
            array('num'=>'10%','level'=>5,'name'=>'副连长','color'=>'#95964e','mark'=>30001,'bonus'=>300,'hols'=>'/'),
            array('num'=>'12%','level'=>6,'name'=>'连长','color'=>'#91a338','mark'=>40001,'bonus'=>400,'hols'=>'1'),
            array('num'=>'15%','level'=>7,'name'=>'副营长','color'=>'#2377a9','mark'=>50001,'bonus'=>500,'hols'=>'1'),
            array('num'=>'18%','level'=>8,'name'=>'营长','color'=>'#3e63a4','mark'=>60001,'bonus'=>600,'hols'=>'1'),
            array('num'=>'21%','level'=>9,'name'=>'副团长','color'=>'#433352','mark'=>70001,'bonus'=>700,'hols'=>'2'),
            array('num'=>'24%','level'=>10,'name'=>'团长','color'=>'#7b1e8b','mark'=>80001,'bonus'=>800,'hols'=>'2'),
            array('num'=>'25%','level'=>11,'name'=>'副旅长','color'=>'#9f3a5c','mark'=>90001,'bonus'=>900,'hols'=>'2'),
            array('num'=>'30%','level'=>12,'name'=>'旅长','color'=>'#c81c43','mark'=>100001,'bonus'=>1000,'hols'=>'3'),
            array('num'=>'36%','level'=>13,'name'=>'副师长','color'=>'#d90499','mark'=>120001,'bonus'=>1200,'hols'=>'3'),
            array('num'=>'40%','level'=>14,'name'=>'师长','color'=>'#ef007a','mark'=>140001,'bonus'=>1400,'hols'=>'3'),
            array('num'=>'42%','level'=>15,'name'=>'军长','color'=>'#e40131','mark'=>160001,'bonus'=>1600,'hols'=>'4'),
            array('num'=>'50%','level'=>16,'name'=>'司令','color'=>'#fd0000','mark'=>200001,'bonus'=>2000,'hols'=>'5'),
        ),
        'top10' => array(
            array('money'=>'/','level'=>1),
            array('money'=>'/','level'=>2),
            array('money'=>'/','level'=>3),
            array('money'=>'/','level'=>4),
            array('money'=>'3000','level'=>5),
            array('money'=>'8000','level'=>6),
            array('money'=>'15000','level'=>7),
            array('money'=>'20000','level'=>8),
            array('money'=>'25000','level'=>9),
            array('money'=>'/','level'=>10),
            array('money'=>'30000','level'=>11),
            array('money'=>'35000','level'=>12),
            array('money'=>'/','level'=>13),
            array('money'=>'50000','level'=>14),
            array('money'=>'/','level'=>15),
            array('money'=>'70000','level'=>16),
        ),
        'last10' => array(
            array('money'=>'/','level'=>1),
            array('money'=>'3000','level'=>2),
            array('money'=>'9000','level'=>3),
            array('money'=>'15000','level'=>4),
            array('money'=>'20000','level'=>5),
            array('money'=>'25000','level'=>6),
            array('money'=>'30000','level'=>7),
            array('money'=>'40000','level'=>8),
            array('money'=>'/','level'=>9),
            array('money'=>'50000','level'=>10),
            array('money'=>'/','level'=>11),
            array('money'=>'60000','level'=>12),
            array('money'=>'80000','level'=>13),
            array('money'=>'/','level'=>14),
            array('money'=>'100000','level'=>15),
            array('money'=>'120000','level'=>16),
        ),
    ),


    //账号交易商品
    'ACCOUNT_PRODUCTS'=>array(
        'price_limit'=>10.00,
    ),

    //评论白名单
    'COMMENT_WHITE_LIST'=>array(
        '良心平台,很不错的游戏','良心平台，很不错的游戏'
    ),

    'GAME_GROUP'=>array(
        array('value'=>1,'name'=>'1组'),
        array('value'=>2,'name'=>'2组'),
        array('value'=>3,'name'=>'3组'),
        array('value'=>4,'name'=>'下线组'),
    ),

    //外部统计
    'STATIC_CONF'=>array(
        1=>'热云统计',
        2=>'今日头条',
        3=>'维度统计',
    ),

    'REDIS_HOST'=>'127.0.0.1', //sdk后台redis

    //账号交易临时登录的key
    'TOKEN_KEY'=>'&^#(@!Ods',
    //商品状态
    'PRODUCTS_STATUS'=> array(
        1=>'审核中',
        2=>'出售中',
        3=>'交易中',
        4=>'已出售',
        5=>'已下架',
    ),

    //达标奖金
    'TG_QUALIFIED'=>array(
         'qualified_counts'=>7,
         'bonus'=>5,
         'bonus_top_counts'=>10,
    ),
    //新人有效注册数统计,1阶段1-7天任务为0，8-30天任务为5，之后为10
    'REG_COUNT'=>array(0,5,10),

    //有效注册绩效扣除
    'TG_ACHIEVEMENTS'=>10,

    //悬赏金额
    'REWARD' => array(0,50,100),

    //当日悬赏最高金额
    'DAY_CONSULT_REWARD_TOP'=>500,
    //游戏问答答案审核奖励
    'DAY_CONSULT_BONUS'=>array(
        'num'=>10,
        'bonus'=>50,
    ),
    'CDN_URL'=>'http://box.singmaan.com',
    'MESSAGE_ATTACH_TOP'=>1000,
    'FREESUB_KEY'=>'4e118bb0',
    'DEVICE_REGISTER_LIMIT'=>20,//当日注册设备上限
    'VIDEOADS_URL'=>'http://www.vidoadsplus.xyz/install',
    'FREESUB_DOWNLOAD'=> 'http://box.singmaan.com/box/',
        /* 狂人排名配置 S */
    'CRAZY_MAN' => array(
        //签到狂
        'signIn' => array(
            1 => 3,
            2 => 7,
            3 => 15,
            4 => 30,
            5 => 60,
            6 => 120,
            7 => 200,
            8 => 280,
            9 => 360
        ),
        //助人狂
        'help' => array(
            1 => 10,
            2 => 30,
            3 => 60,
            4 => 100,
            5 => 150,
            6 => 210,
            7 => 280,
            8 => 360,
            9 => 500
        ),
        //点评狂
        'comment' => array(
            1 => 1,
            2 => 5,
            3 => 10,
            4 => 30,
            5 => 60,
            6 => 100,
            7 => 150,
            8 => 220,
            9 => 300
        ),
        //开车狂
        'drive' => array(
            1 => 10,
            2 => 30,
            3 => 60,
            4 => 100,
            5 => 150,
            6 => 210,
            7 => 280,
            8 => 360,
            9 => 450
        ),
    ),
    'APP_UID_TOP'=>10,//小号数量
    /* 狂人排名配置 E */
    'DEFAULT_NAME'=>'木有昵称',//默认昵称
    //支付宝配置
    'alipay' => array(
        'APPID' => '2021001101695842',
        'RSA_PRIVATE_KEY' => 'MIIEowIBAAKCAQEAsr7MvNcoQTON/lOWBf99oRMr3uKa/6mTgu0IS7eBUNK3YIQSvq/WJYXY2Swds5CimALO1Zie0nWgnPuOpXAWXPGP9oKkjKHEuzXhZn8WO6XQQmILWA2aUccOF01PrSHKm9lGDksqXktIHPESE9WIcF37+GTE1sGYsXXtY9LPpcX2s44FiOp+5LjlDlJ3dpyVbIt83eLpKfEepOmBaWo0YAcE3cBNzORedSVpys4zoWQ92wP7BGXXJuXcBzgHg4NGCHf4usXUlrJu12VZ4gOFwN9vPO2tJsYMylSqABG8FZS42aPZ83bUxB32aDFbwUIGlvi2YigAH4X3WPNyQCyeiwIDAQABAoIBACygKilzZh9xKaA3ahsxQEI4sRlCZgQyaBul5g5RUD3HffTgxHVLHSHdGtiNhRTRpWZiPRVitzRAHctTmrd/FnY58QFQeJiLwrwipZWbYQefTDFmnQJs0vxbwA7dRGkrKJRkWHM1HOzRyr0/Co5dH2U+cawALIyZZO/ZS11CwojWSvPHRJIw4Vcgs5hY1UBKwEmAqbgUUetrinNtTEt52RxfZhHn0Q5vS1q4tJhWDkFz45YiN8z6Pt0XnYMy1gv3/F9tbBy2L9ZkvKwKOX3CSvqiTAlVBAAUfFFX4mh28Lp79DHCG2H/gH6j4+kcRBxAvQIlqtTgTDB8IQ3USRXwTcECgYEA3T2LzT9S+Synb0L+8n1GPDeIIKS6fTk3ue9QtuP8HM41scgD2gsa0Y2IwbFkSmjx35RN6HHT+mRfBYlhPgAE7ggXPfpoMjWVAOeUx0g/6x7U59mGgNWFedm/o1xwuVIsWl3OzOYP3jdxXa9WgdHefJB/Nb23xUjuWVMtsjZcS3ECgYEAztQRrDOaKH/+BDgXMdhC+DqpgHKkBm7e6h+WOsDp984D+rola/4c4mTvf085zlEqSVdsmZQyPIYYJgXsK+IWuwTDDmSU4y0RPXwNMVBjPo6uxSofRkXKsNOKO+yMrwDqyJl2B9MvaXudMTlGSfvUa8iJgjrPZ0q+RSdj8sJaM7sCgYB5EQ/qvidSkXwCZ6AJ5EHF/AFzE5G1qtTy26HIo1O4E6PQaIqrC/6eA5x9mewux+TsG0TS2U9NqxVQe1AKVIpeE3FciiXwq9+hAhkpQEEyEcoiIaf34mBLQLwc0h4hW3VH8iOTfqXaTW6s/KETI1xjv9LM/seMnw9HNDxCVHsHEQKBgQDKzSyrAkolbiMf9fPgXyKDjdnIZiCpYitXvDMtVx3TawGB5uZsttWRs9EWrxOKVxG0qnGFQcfkisPA/Muv19fb93ZfdYZ8HyOpeyrgcOdvXq7GC2BxIlv1+OaxjWgA7VBJ9BfE4nG554ihzHw8bR7DtPYIaSwXJzXcTxx029dgowKBgGuQR10R2zTUTE/V+w2Ppqf70FxS2YxKvM/Ooy17ZKjbybF/JtstgU+T/bA9JoyKVSmWcROZ5zwZoTsygubliqIwJ2f6gDOBYBuMahgruoO3s/ThQJ0fZBRx08mfqWDqVtqqmdfEKPRauRvy3GRSuTapAWb0+8AvBN6tbXSDHPUt',
        'RSA_PUBLIC_KEY' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAnEdbk8ZhnWRAKH5KFIDkklnbrBd0CDARKAJlHHU/cRduV4UXZq4Sw7g6SgrczJSHny3iH2zQpHEcALDurqOmg9UiFbzWGmqWcjS0eOWAWztXIWP8xF+ate1Xg67SeMT1sXZwa4WOqN+AyatPg8SgpK4T4+SnPuxyS4RZ+XuaPnxWG3IlzRn84GRoxCs0eOIm/jAA0e7eXwhFSfAp5qM1duYrIz84m6LcR5PZcB/nOipw4Gj7AMmh8Zgl/r3dm0cjDFTxQnoE9rlY7PJqiWCN4uMyyppzhGaPOcibmELhFfJqNdaHF4Qem3iX0qvMuhiRqFk0NA8mvUuigp99OrIUwQIDAQAB'
    ),
    // 微信配置
    'wechat' => array(
        'APPID' => 'wx74d8422124a9547a',
        'KEY' => '75d99d3175225b6a907caf6734e21c4d',
        'MCH_ID' => 1571964901,
        'APP_SECRET' => '0be04659b1e705168174f87cd88c5744'
    ),
);

return  array_merge($configs,$db,$runtime_config);

