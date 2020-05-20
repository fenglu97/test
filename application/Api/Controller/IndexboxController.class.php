<?php

// +----------------------------------------------------------------------

// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]

// +----------------------------------------------------------------------

// | Copyright (c) 2013-2014 http://www.thinkcmf.com All rights reserved.

// +----------------------------------------------------------------------

// | Author: Tuolaji <479923197@qq.com>

// +----------------------------------------------------------------------

/**

 * 参    数：

 * 作    者：lht

 * 功    能：OAth2.0协议下第三方登录数据报表

 * 修改日期：2013-12-13

 */

namespace Api\Controller;

use Common\Controller\AppframeController;

class IndexboxController extends AppframeController {



	//设置

	function index(){
	echo 'The server is running successfully';
	}

	public function map()
    {
        $api_url = C('API_URL');
        $box_url = C('BOX_URL');
        $h5_url = C('h5_domain_url');
		$map = array(
            'DOAMIN' =>C('CDN_URL'),//APP接口地址
		    'INDEX_SLIDE'=>$box_url,
            'H5_LOGIN_MOBILE' => $api_url.'/api/H5sdk/login_account.html',//H5登录
            'H5_MOBILE_PAY' => $api_url.'/api/H5sdk/pay.html',//H5支付
            'H5_USER_CENTER' => $h5_url.'/user',
					//'INDEX_SLIDE'=>'',
            'PACKS_LIST'=>$box_url.'/index.php?g=api&m=packs&a=get_list', //礼包列表
            'PACKS_LINGQU'=>$box_url.'/index.php?g=api&m=packs&a=get_pack', //礼包领取
            'GAME_PACK'=>$box_url.'/index.php?g=api&m=packs&a=get_list_by_game', //游戏相关礼包
            'USER_PACK'=>$box_url.'/index.php?g=api&m=packs&a=get_list_by_user', //用户领取礼包
            'PACKS_SLIDE'=>$box_url.'/index.php?g=api&m=packs&a=get_slide',//礼包列表幻灯片
            'GAME_INDEX' => $box_url.'/index.php?g=api&m=game&a=index',//游戏推荐
            'GAME_NEWINDEX' => $box_url.'/index.php?g=api&m=game&a=newIndex',//游戏推荐
            'GAME_TYPE' => $box_url.'/index.php?g=api&m=game&a=gameType',//新游、热门、排行
            'NEW_GAME_TYPE' => $box_url.'/index.php?g=api&m=game&a=newGameType',//新*新游、热门、排行
            'HQ_GAME' => $box_url.'/index.php?g=api&m=game&a=hqGame',//精品游戏
            'NEW_GAME_LIST' => $box_url.'/index.php?g=api&m=game&a=newgameList',//新游内测或预约
            'RESERVE_NEWGAME' => $box_url.'/index.php?g=api&m=game&a=newgame_reserve',//新游预约
			'RESERVE_SUCCESS' => $box_url.'/index.php?g=api&m=game&a=reserve_success',//预约通知回调
            'OPEN_SERVER' => $box_url.'/index.php?g=api&m=game&a=openServer',//开服
            'GAME_INFO' => $box_url.'/index.php?g=api&m=game&a=gameInfo',//游戏详情
            'GAME_COLLECT' => $box_url.'/index.php?g=api&m=game&a=collect',//游戏收藏/取消
            'GAME_OPEN_SERVER' => $box_url.'/index.php?g=api&m=game&a=gameOpenServer',//单一游戏开服
            'GAME_CLASS' => $box_url.'/index.php?g=api&m=game&a=gameClass',//游戏分类
            'GAME_CLASS_INFO' => $box_url.'/index.php?g=api&m=game&a=gameClassInfo',//单一游戏分类
            'GAME_GETALLNAME' => $box_url.'/index.php?g=api&m=game&a=getAllGameName',//获得所有游戏名
            'GAME_GETHOT' => $box_url.'/index.php?g=api&m=game&a=hotGameSearch',//获得热门搜索游戏
            'GAME_SEARCH_LIST' => $box_url.'/index.php?g=api&m=game&a=gameSearchList',//游戏搜索结果
            'GAME_UPDATA' => $box_url.'/index.php?g=api&m=game&a=gameUpdata',//游戏更新
            'GAME_CHANNEL_DOWNLOAD' => $box_url.'/index.php?g=api&m=game&a=channelDownload',//子渠道下载
            'GAME_MY_COLLECT' => $box_url.'/index.php?g=api&m=game&a=myCollect',//我的收藏
            'GAME_INSTALL' => $box_url.'/index.php?g=api&m=game&a=gameInstall',//游戏安装
            'GAME_UNINSTALL' => $box_url.'/index.php?g=api&m=game&a=gameUninstall',//游戏卸载
            'GAME_GRADE' => $box_url.'/index.php?g=api&m=game&a=gameGrade',//游戏评分
            'GAME_List' => $box_url.'/index.php?g=api-rankList-gameList',//新版游戏排行
            'GAME_CHECK_CLIENT' => $box_url.'/index.php?g=api&m=game&a=checkClient',//客户端更新检测
            'GAME_DOWNLOAD_RECORD' => $box_url.'/index.php?g=api&m=game&a=downloadRecord',//下载数+1
            'GAME_BOX_INSTALL_INFO' =>$box_url.'/index.php?g=api&m=game&a=boxInstallInfo',//盒子安装记录
            'GAME_BOX_START_INFO' => $box_url.'/index.php?g=api&m=game&a=boxStartInfo',//盒子启动记录
            'INDEX_ARTICLE'=>$box_url.'/index.php?g=api-article-get_list',//首页攻略 活动列表
            'GAME_GONGLUE' =>$box_url.'/index.php?g=api-article-get_list_by_game',//游戏详情页攻略
            'GAME_ERROR_LOG' => $box_url.'/index.php?g=api&m=game&a=uploadErrorLog',//上传错误日志
            'USER_LOGIN' =>$api_url.'/index.php?g=api&m=userbox&a=login', //用户登录
            'USER_REGISTER' =>$api_url.'/index.php?g=api&m=userbox&a=register', //用户注册
            'USER_SENDMSG'=>$api_url.'/index.php?g=api&m=userbox&a=send_message',//发送短信
            'USER_CHECKMSG'=>$api_url.'/index.php?g=api&m=userbox&a=check_smscode',//检验验证码
            'USER_FINDPWD'=>$api_url.'/index.php?g=api&m=userbox&a=forget_password',//重置密码
            'USER_MODIFYPWD'=>$api_url.'/index.php?g=api&m=userbox&a=modify_password',//修改密码
	        'USER_UPLOAD'=>$api_url.'/index.php?g=api&m=userbox&a=upload_portrait',//上传头像
            'USER_MODIFYNN'=>$api_url.'/index.php?g=api&m=userbox&a=modify_nickname',//修改昵称
            'CHANGEGAME_APPLY'=>$api_url.'/index.php?g=api&m=changegame&a=apply',//申请转游
            'CHANGEGAME_LOG'=>$api_url.'/index.php?g=api&m=changegame&a=log',//转游记录
            'CUSTOMER_SERVICE'=>$api_url.'/index.php?g=api&m=user&a=customer_service',//客服信息
            'COIN_LOG'=>$api_url.'/index.php?g=api&m=coin&a=log',//金币明细
            'COIN_INFO'=>$api_url.'/index.php?g=api&m=coin&a=coin_info',//用户金币详情
            'MY_COIN'=>$api_url.'/index.php?g=api&m=coin&a=my_coin',//用户金币中心
            'PLAT_EXCHANGE'=>$api_url.'/index.php?g=api&m=platformmoney&a=exchange',//平台币兑换
            'PLAT_LOG'=>$api_url.'/index.php?g=api&m=platformmoney&a=log',//平台币明细
            'SIGN_INIT'=>$api_url.'/index.php?g=api&m=sign&a=sign_init',//签到初始化
            'DO_SIGN'=>$api_url.'/index.php?g=api&m=sign&a=do_sign',//签到
            'FREND_RECOM'=>C('FREESUB_DOWNLOAD'), //好友推荐
            'COMMENT_COIN'=> $api_url.'/index.php?g=api&m=comment&a=giveCoin',//评论获取金币
            'VIP_OPTION' => $api_url.'/index.php?g=api&m=pay&a=getVipOption',//获取VIP充值配置
            'PAY_READY' => $api_url.'/index.php?g=api&m=pay&a=payReady',//支付前检测
            'PAY_START' => $api_url.'/index.php?g=api&m=pay&a=payStart',//发起支付
            'PAY_QUERY' => $api_url.'/index.php?g=api&m=pay&a=payQuery',//支付查询
	    'VIP_QUERY' => $api_url.'/index.php?g=api&m=pay&a=vipQuery',//vip支付查询
            'REBATE_NOTICE' => $api_url.'/index.php?g=api&m=selfRebate&a=rebateNotice',//返利滚动通知
            'REBATE_INFO' => $api_url.'/index.php?g=api&m=selfRebate&a=rebateInfo',//用户可返利列表
            'REBATE_RECORD' => $api_url.'/index.php?g=api&m=selfRebate&a=rebateRecord',//返利记录
            'REBATE_APPLY' => $api_url.'/index.php?g=api&m=selfRebate&a=rebateApply',//返利申请
            'REBATE_KNOW' => $api_url.'/index.php?g=api&m=selfRebate&a=rebateKnow',//返利须知
	        'FRIEND_RECOM_INFO' =>$api_url.'/index.php?g=api&m=userbox&a=friend_recom_info',//好友推荐
            'USER_CENTER'=>$api_url.'/index.php?g=api&m=userbox&a=user_center_box',//盒子用户中心

            'CHANGEGAME_NOTICE'=>$api_url.'/index.php?g=api&m=changegame&a=notice',//转游须知
            'MY_PRIZE' => $api_url.'/index.php?g=api&m=luckydraw&a=myPrize',//我的奖品
            'LUCKY_DRAW' => $api_url.'/index.php?g=api&m=luckydraw&a=show',//抽奖
            'USER_BIND_MOBILE'=>$api_url.'/index.php?g=api&m=user&a=bind_mobile',//绑定手机
            'USER_UNBIND_MOBILE'=>$api_url.'/index.php?g=api&m=user&a=unbind_mobile',//解绑手机
            'GAME_GET_START_IMGS' => $api_url.'/index.php?g=api&m=game&a=getStartImgs',//获得启动页、广告页
            'APP_NOTICE' => $api_url.'/index.php?g=api&m=userbox&a=notice',//公告
	        'GET_PATCH'=>$api_url.'/index.php?g=api&m=userbox&a=get_patch',//获取补丁
			'MESSAGE_NOTICE'=>$api_url.'/index.php?g=api&m=message&a=message_notice',//消息附件通知(盒子)
			'READ_MSG'=>$api_url.'/index.php?g=api&m=message&a=read_msg',//消息附件通知(盒子)
			'GET_MSG_PLATFORM'=>$api_url.'/index.php?g=api&m=platformmoney&a=get_msg_platform',//领取消息附件中的平台币
	        'MESSAGE_UNREAD'=>$api_url.'/index.php?g=api&m=message&a=unread_counts',//未读消息
	        'MESSAGE_LIST'=>$api_url.'/index.php?g=api&m=message&a=get_message_list',//消息列表
            'MESSAGE_INFO'=>$api_url.'/index.php?g=api&m=message&a=get_message_info',//消息详情
            'MESSAGE_DELETE'=>$api_url.'/index.php?g=api&m=message&a=delete_message',//删除消息
            'PLAT_REG_BONUS'=>$api_url.'/index.php?g=api&m=platformmoney&a=get_reigster_bonus',//领取手机注册奖励
            'RANKING_LIST' => $api_url.'/index.php?g=api&m=userbox&a=rankingList',//邀请好友排行
            'USER_RANKING' => $api_url.'/index.php?g=api&m=userbox&a=userRanking',//用户自己邀请排行
            'RANKNOTICE' => $api_url.'/index.php?g=api&m=userbox&a=rankNotice',//排行奖励须知
            'RECEIVE_REWARD' => $api_url.'/index.php?g=api&m=userbox&a=receiveReward',//领取排行奖励
            'USER_AGREEMENT' => $api_url.'/index.php?g=api&m=userbox&a=userAgreement',//用户协议
            /* 社区接口 */
            'PUBLISH_DYNAMICS'=>$api_url.'/index.php?g=api&m=dynamics&a=publishDynamics',//发布动态
            'GET_DYNAMICS'=>$api_url.'/index.php?g=api&m=dynamics&a=getDynamics',//获取动态
            'FOLLOW_OR_CANCEL'=>$api_url.'/index.php?g=api&m=dynamics&a=followOrCancel',//获取动态
	        'COMMENT'=>$api_url.'/index.php?g=api&m=comment&a=do_comment',//发表评论
            'COMMENT_LIST'=>$api_url.'/index.php?g=api&m=comment&a=comment_list',//评论列表
            'COMMENT_DEL'=>$api_url.'/index.php?g=api&m=comment&a=delete_comment',//删除评论
            'DYNAMICS_LIKE'=>$api_url.'/index.php?g=api&m=likeinfo&a=dynamics_like',//动态赞踩
            'COMMENT_LIKE'=>$api_url.'/index.php?g=api&m=likeinfo&a=comment_like',//评论赞踩
            'ARTICLE_LIKE'=>$api_url.'/index.php?g=api&m=likeinfo&a=article_like',//文章赞踩
            'FOLLOW_LIST'=>$api_url.'/index.php?g=api&m=userbox&a=follow_list',//粉丝关注列表
            'USER_DESC'=>$api_url.'/index.php?g=api&m=userbox&a=user_desc',//用户详情
            'USER_EDIT'=>$api_url.'/index.php?g=api&m=userbox&a=edit_desc',//编辑用户信息        
            'USER_NEW_UP'=>$api_url.'/index.php?g=api&m=userbox&a=new_up_counts',//我的新消息数量
            'USER_COMMENT_ZAN'=>$api_url.'/index.php?g=api&m=userbox&a=my_comment_zan',//我的赞踩评论
            'SHARE_DYNAMICS'=>$api_url.'/index.php?g=api&m=dynamics&a=shareDynamics',//转发动态
            'DYNAMICS_WAP_INFO'=>$api_url.'/api/dynamics/webdisplay.html',//动态详情页
            'BOX_INIT'=>$api_url.'/index.php?g=api&m=userbox&a=do_init',//盒子初始化
            'CANCEL_DYNAMICS_LIKE'=>$api_url.'/index.php?g=api&m=likeinfo&a=cancel_dynamics_like',//取消动态赞踩
            'CANCEL_COMMENT_LIKE'=>$api_url.'/index.php?g=api&m=likeinfo&a=cancel_comment_like',//取消评论赞踩
            'PACKAGE_INFO'=>$api_url.'/index.php?g=api&m=package&a=pack_info',//礼包详情
            'COMMENT_COUNTS'=>$api_url.'/index.php?g=api&m=comment&a=get_comment_counts',//评论数
            'DEL_DYNAMIC'=>$api_url.'/index.php?g=api&m=dynamics&a=delDynamic',//删除我的动态
			'COMMENT_REPLY_LIST'=>$api_url.'/index.php?g=api&m=comment&a=get_replay_comment',//评论回复
			'USER_APP_LOGIN'=>$api_url.'/index.php?g=api&m=comment&a=user_login_app',//评论前置接口
			'GDT_REPORT'=>$api_url.'/index.php?g=api&m=gdtstatic&a=report_data',//广点通上报
			'JRTT_REPORT'=>$api_url.'/index.php?g=api&m=jrttstatic&a=report_data',//今日头条上报
			'TASK_CENTER'=>$api_url.'/index.php?g=api&m=userbox&a=task_center',//任务中心
			'APP_PROMISE'=>$api_url.'/index.php?g=api&m=userbox&a=app_promise',//APP承诺
			'BOX_INIT_V2'=>$api_url.'/index.php?g=api&m=userbox&a=do_init_v2',//盒子初始化v2
			'BSP_SENDMSG'=>$api_url.'/index.php?g=api&m=businessplayer&a=send_message',//交易账号发送短信
			'BSP_REGISTER'=>$api_url.'/index.php?g=api&m=businessplayer&a=register',//交易账号注册
			'BSP_LOGIN'=>$api_url.'/index.php?g=api&m=businessplayer&a=login',//交易账号登陆
			'BSP_MODIFYPWD'=>$api_url.'/index.php?g=api&m=businessplayer&a=modify_password',//交易账号修改密码
			'BSP_FORGETPWD'=>$api_url.'/index.php?g=api&m=businessplayer&a=forget_password',//交易账号忘记密码
			'BSP_USERINFO'=>$api_url.'/index.php?g=api&m=businessplayer&a=user_info',//交易账号资料
			'BSP_EDITUSER'=>$api_url.'/index.php?g=api&m=businessplayer&a=edit_user',//编辑交易账号资料
			'BSP_COMPLETEINFO'=>$api_url.'/index.php?g=api&m=businessplayer&a=is_complete_info',//验证交易账号信息完整性
			'BIND_SDKUSER'=>$api_url.'/index.php?g=api&m=businessplayer&a=bind_sdkuser',//关联SDK账号
			'UNBIND_SDKUSER'=>$api_url.'/index.php?g=api&m=businessplayer&a=unbind_sdkuser',//撤销关联SDK账号
			'SDKUSER_LIST'=>$api_url.'/index.php?g=api&m=businessplayer&a=sdkuser_list',//关联账号列表
			'PRODUCT_LIST'=>$api_url.'/index.php?g=api&m=Products&a=get_product_list',//商品列表
			'GAME_BY_SDKUSER'=>$api_url.'/index.php?g=api&m=Products&a=game_by_sdkuser',//账号可交易游戏
			'SELL_PRODUCTS'=>$api_url.'/index.php?g=api&m=Products&a=sell_product',//提交商品信息
			'WITHDRAW_PRODUCTS'=>$api_url.'/index.php?g=api&m=Products&a=withdraw_product',//下架商品
			'DELETE_PRODUCTS'=>$api_url.'/index.php?g=api&m=Products&a=delete_product',//删除商品
			'PRODUCT_INFO'=>$api_url.'/index.php?g=api&m=Products&a=product_info',//商品详情
			'PRODUCT_CUSTOMER'=>$api_url.'/index.php?g=api&m=Products&a=customer',//客服信息
			'PRODUCT_BYUSER'=>$api_url.'/index.php?g=api&m=Products&a=get_product_by_user',//我的商品
			'PRODUCT_ONSALE'=>$api_url.'/index.php?g=api&m=Products&a=apply_onsale',//商品上架
			'TRADE_NOTES'=>$api_url.'/index.php?g=api&m=Products&a=trade_notes',//交易须知
			'TRADE_NOTES_H5'=>C('box_domain_url').'/tradenotes.html',//交易须知H5页面
            'START_PAYMENT'=>$api_url.'/index.php?g=api&m=AccountTrade&a=startPayment',//发起支付
            'CANCEL_PAYMENT'=>$api_url.'/index.php?g=api&m=AccountTrade&a=cancelPayment',//取消支付
            'BUYER_RECORD'=>$api_url.'/index.php?g=api&m=AccountTrade&a=buyerRecord',//买家交易记录
            'SELLER_RECORD'=>$api_url.'/index.php?g=api&m=AccountTrade&a=sellerRecord',//卖家交易记录
			'EXCLUSIVE_ACT'=>$api_url.'/index.php?g=api&m=article&a=exclusive_list',//独家活动
			'SDK_LOGIN_URL'=>$api_url.'/index.php?g=api&m=user&a=login',//SDK登录
            'GAME_PLAYED' => $api_url.'/index.php?g=api&m=game&a=played',//玩过的游戏
			'ARITCLE_SHARE'=>$api_url.'/index.php?g=api&m=article&a=share',//文章分享
			'ACT_STATIC'=>$api_url.'/index.php?g=api&m=ActStatic&a=do_report',//用户行为上报
            'MY_QUESTION'=>$api_url.'/index.php?g=api&m=Consult&a=myQuestions',//我的提问
            'PUT_QUESTION'=>$api_url.'/index.php?g=api&m=Consult&a=putQuestion',//游戏提问
            'CONSULT_LIST'=>$api_url.'/index.php?g=api&m=Consult&a=consultList',//游戏问答列表
	    	'CONSULTINFO_LIST'=>$api_url.'/index.php?g=api&m=ConsultInfo&a=info',//问题详情
			'CONSULTINFO_ANSWER'=>$api_url.'/index.php?g=api&m=ConsultInfo&a=do_answer',//问题回答
			'CONSULTINFO_REWARD'=>$api_url.'/index.php?g=api&m=ConsultInfo&a=do_reward',//答案悬赏
			'CONSULT_ANSWERGAME'=>$api_url.'/index.php?g=api&m=ConsultInfo&a=get_answer_game',//我来回答
			'FREE_SUB'=>C('p_domain_url').'/index.php?g=api&m=FreeSub&a=get_c',//免分包获取渠道
            'CRAZY_MAN_TOP' => $api_url.'/index.php?g=api&m=userbox&a=crazyManTop',//狂人排行
			'COMMENT_LIST_V2'=>$api_url.'/index.php?g=api&m=comment&a=comment_list_v2',//评论列表V2
			'COMMENT_V2'=>$api_url.'/index.php?g=api&m=comment&a=do_comment_v2',//发表评论V2
            'COMMENT_INFO'=>$api_url.'/index.php?g=api&m=comment&a=get_comment_info',//
            'IDCARD_VERIFY'=>$api_url.'/index.php?g=api&m=user&a=id_auth', // 实名认证提交数据接口
        );
        $this->ajaxReturn($map);
    }

	//设置
	function setting_post(){

		if($_POST){

			$qq_key=$_POST['qq_key'];

			$qq_sec=$_POST['qq_sec'];

			$sina_key=$_POST['sina_key'];

			$sina_sec=$_POST['sina_sec'];

			$google_key=$_POST['google_key'];

			$google_sec=$_POST['google_sec'];

			$facebook_key=$_POST['facebook_key'];

			$facebook_sec=$_POST['facebook_sec'];

			$twitter_key=$_POST['twitter_key'];

			$twitter_sec=$_POST['twitter_sec'];

			$host=sp_get_host();

			$call_back = $host.__ROOT__.'/index.php?g=api&m=oauth&a=callback&type=';

			$data = array(

					'THINK_SDK_QQ' => array(

							'APP_KEY'    => $qq_key,

							'APP_SECRET' => $qq_sec,

							'CALLBACK'   => $call_back . 'qq',

					),

					'THINK_SDK_SINA' => array(

							'APP_KEY'    => $sina_key,

							'APP_SECRET' => $sina_sec,

							'CALLBACK'   => $call_back . 'sina',

					),

					'THINK_SDK_GOOGLE' => array(

							'APP_KEY'    => $google_key,

							'APP_SECRET' => $google_sec,

							'CALLBACK'   => $call_back . 'google',

					),

					'THINK_SDK_FACEBOOK' => array(

							'APP_KEY'    => $facebook_key,

							'APP_SECRET' => $facebook_sec,

							'CALLBACK'   => $call_back . 'facebook',

					),

					'THINK_SDK_TWITTER' => array(

							'APP_KEY'    => $twitter_key,

							'APP_SECRET' => $twitter_sec,

							'CALLBACK'   => $call_back . 'twitter',

					),

			);

			

			$result=sp_set_dynamic_config($data);

			

			if($result){

				$this->success("更新成功！");

			}else{

				$this->error("更新失败！");

			}

		}

	}

}