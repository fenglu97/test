var system = JudgeSystem();
var Api =getUserInfo();
function getUserInfo() {
    var util = {
        jointUrl: function (data){
            var params = '';
            for(var key in data){
                params += key + '=' + data[key] + '&';
            }
            params = params.replace(/&{1}$/,'');
            return params;
        },
        getSign: function(options, key){
            key = key || config.appkey;
            return md5(util.jointUrl(options) + key).toLowerCase();
        },
        iosCookie: {
            set: function (name, value) {
                if(system === 2){
                    window.webkit.messageHandlers.wz_callOC.postMessage({
                        method: 'iOSSetData',
                        param: {
                            key: name,
                            value: value
                        }
                    });
                }
            },
        },
        cookie: {
            set: function (name, value, expires, domain, path, secure) {
                var cookieText = "";
                cookieText += encodeURIComponent(name) + "=" + encodeURIComponent(value);
                if (expires instanceof Date) {
                    cookieText += "; expires=" + expires.toGMTString();
                }
                if (path) {
                    cookieText += "; path=" + path;
                }
                if (domain) {
                    cookieText += "; domain=" + domain;
                }
                if (secure) {
                    cookieText += "; secure";
                }
                document.cookie = cookieText;
                // $(".test").html(cookieText);
            },
            // name=value; expires=expiration_time; path=domain_path; domain=domain_name; secure
            // 获取cookie
            get: function (name) {
                var cookieName = encodeURIComponent(name) + "=",
                    cookieStart = document.cookie.indexOf(cookieName),
                    cookieValue = "";
                if (cookieStart > -1) {
                    var cookieEnd = document.cookie.indexOf (";", cookieStart);
                    if (cookieEnd == -1) {
                        cookieEnd = document.cookie.length;
                    }
                    cookieValue = decodeURIComponent(document.cookie.substring(cookieStart + cookieName.length, cookieEnd));
                }
                return cookieValue;
            },
            // 删除cookie
            unset: function (name, domain, path, secure) {
                this.set(name, "", Date(0), domain, path, secure);
            }
        }
    };
    var config;
    if (system===1){
        var urlParams  = window.location.search;
        var allUrlParams = null;
        if (urlParams){
            util.cookie.set('payInfoByUrl',JSON.stringify(getParamsUrl(urlParams)));
            allUrlParams = JSON.parse(util.cookie.get('payInfoByUrl'));
        }
        else {
            if (util.cookie.get('payInfoByUrl')) {
                allUrlParams = JSON.parse(util.cookie.get('payInfoByUrl'));
            }
        }
        if(util.cookie.get('userInfoByUrl')){
            var userInfo = JSON.parse(util.cookie.get('userInfoByUrl'));
            var discountF;
            if (userInfo.discount){
                discountF = userInfo.discount;
            }
            else {
                discountF = 0;
            }
            config = {
                host: 'http://sdk.23yxm.com',
                key: 'e10adc3949ba59abbe56e057f20f883e',
                system: system,
                uid: allUrlParams.uid,
                cuid: allUrlParams.cuid,
                // 设备ID
                appid: allUrlParams.appId,
                // 系统版本
                appkey: allUrlParams.appKey,
                // 支付详情
                payParams: JSON.parse(decodeURI(allUrlParams.payParams)),
                // 折扣
                discount: discountF,
                /*频道*/
                channel: userInfo.channel,
                // 厂商
                maker: userInfo.maker,
                // 手机类型
                mobile_model: userInfo.mobileModel,
                // 手机设备号
                machine_code: userInfo.machineCode,
                // 设备ID
                machine_id: userInfo.androidId,
                // 系统版本
                system_version: userInfo.versionName,
                // 广告图片跳转链接
                getAdLinkUrl: userInfo.adLinkUrl,
                // 广告图片地址
                getAdImageUrl: userInfo.adImageUrl,
                // 是否显示广告图片
                isDisplayAd: false,
                // 是否开启注册
                isRegisterEnabled: userInfo.registerEnabled,
                // 是否开启实名认证
                isNameAuthEnabled: userInfo.nameAuthEnabled,
                // 是否显示平台币和平台币支付
                isPlatformMoneyEnabled: userInfo.platformMoneyEnabled,
                // 是否开启绑定手机
                isBindMobileEnabled: userInfo.bindMobileEnabled,
                //获取账号
                getAccount: getAccount(),
                // 登录成功回调
                onLoginSucceed: function (account, token, uid) {
                    if(system === 1){
                        window.Android.onLoginSucceed(account, token, uid);
                    } else if(system === 2){
                        window.webkit.messageHandlers.wz_callOC.postMessage({
                            method: 'onLoginSucceed',
                            param: {
                                account: account,
                                token: token,
                                uid: uid
                            }
                        });
                    }
                },
                // 登录失败回调
                onLoginError: function (error) {
                    if(system === 1){
                        window.Android.onLoginFailed(error);
                    } else if(system === 2){
                        window.webkit.messageHandlers.wz_callOC.postMessage({
                            method: 'onLoginFailed'
                        });
                    }
                },
            };
        }
        else {
            config = {
                host: 'http://sdk.23yxm.com',
                key: 'e10adc3949ba59abbe56e057f20f883e',
                system: system,
                appid: getAppId(),
                appkey: getAppKey(),
                uid: getUid(),
                cuid: getCUid(),
                payParams: JSON.parse(getPayParams()),
                //渠道ID
                channel: getChannel(),
                // 厂商
                maker: getMaker(),
                // 手机类型
                mobile_model: getMobileModel(),
                // 手机设备号
                machine_code: getMachineCode(),
                // 设备ID
                machine_id: getAndroidId(),
                // 系统版本
                system_version: getVersionName(),
                // 广告图片跳转链接
                getAdLinkUrl: getAdLinkUrl(),
                // 广告图片地址
                getAdImageUrl: getAdImageUrl(),
                // 是否显示广告图片
                isDisplayAd: isDisplayAd(),
                // 是否开启注册
                isRegisterEnabled: isRegisterEnabled(),
                // 是否开启实名认证
                isNameAuthEnabled: isNameAuthEnabled(),
                // 是否显示平台币和平台币支付
                isPlatformMoneyEnabled: isPlatformMoneyEnabled(),
                // 是否开启绑定手机
                isBindMobileEnabled: isBindMobileEnabled(),
                // 折扣
                discount: discount(),
                //获取账号
                getAccount: getAccount(),
                // 登录成功回调
                onLoginSucceed: function (account, token, uid) {
                    if(system === 1){
                        window.Android.onLoginSucceed(account, token, uid);
                    } else if(system === 2){
                        window.webkit.messageHandlers.wz_callOC.postMessage({
                            method: 'onLoginSucceed',
                            param: {
                                account: account,
                                token: token,
                                uid: uid
                            }
                        });
                    }
                },
                // 登录失败回调
                onLoginError: function (error) {
                    if(system === 1){
                        window.Android.onLoginFailed(error);
                    } else if(system === 2){
                        window.webkit.messageHandlers.wz_callOC.postMessage({
                            method: 'onLoginFailed'
                        });
                    }
                },
            };
        }
    }
    else if (system===2){
        config = {
            host: 'http://sdk.23yxm.com',
            key: 'e10adc3949ba59abbe56e057f20f883e',
            system: system,
            appid: getAppId(),
            appkey: getAppKey(),
            //渠道ID
            channel: getChannel(),
            // 厂商
            maker: getMaker(),
            // 手机类型
            mobile_model: getMobileModel(),
            // 手机设备号
            machine_code: getMachineCode(),
            // 设备ID
            machine_id: getAndroidId(),
            // 系统版本
            system_version: getVersionName(),
            // 广告图片跳转链接
            getAdLinkUrl: getAdLinkUrl(),
            // 广告图片地址
            getAdImageUrl: getAdImageUrl(),
            // 是否显示广告图片
            isDisplayAd: isDisplayAd(),
            // 是否开启注册
            isRegisterEnabled: isRegisterEnabled(),
            // 是否开启实名认证
            isNameAuthEnabled: isNameAuthEnabled(),
            // 是否显示平台币和平台币支付
            isPlatformMoneyEnabled: isPlatformMoneyEnabled(),
            // 是否开启绑定手机
            isBindMobileEnabled: isBindMobileEnabled(),
            // 折扣
            discount: discount(),
            //获取账号
            getAccount: getAccount(),
            // 登录成功回调
            onLoginSucceed: function (account, token, uid) {
                if(system === 1){
                    window.Android.onLoginSucceed(account, token, uid);
                } else if(system === 2){
                    window.webkit.messageHandlers.wz_callOC.postMessage({
                        method: 'onLoginSucceed',
                        param: {
                            account: account,
                            token: token,
                            uid: uid
                        }
                    });
                }
            },
            // 登录失败回调
            onLoginError: function (error) {
                if(system === 1){
                    window.Android.onLoginFailed(error);
                } else if(system === 2){
                    window.webkit.messageHandlers.wz_callOC.postMessage({
                        method: 'onLoginFailed'
                    });
                }
            },
        };
    }
    return {
        util: util,
        config: config,
    }
}

function JudgeSystem(){
    var u = navigator.userAgent;
    var isAndroid = u.indexOf('Android') > -1 || u.indexOf('Adr') > -1; //android终端
    var isiOS = !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/); //ios终端
    return isAndroid?1:2;
}
function getAppId() {
    if(system === 1){
        return window.Android.getAppId();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getAppKey() {
    if(system === 1){
        return window.Android.getAppKey();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getChannel() {
    if(system === 1){
        return window.Android.getChannel();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getMaker() {
    if(system === 1){
        return window.Android.getMaker();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getMobileModel() {
    if(system === 1){
        return window.Android.getMobileModel();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getMachineCode() {
    if(system === 1){
        return window.Android.getMachineCode();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getAndroidId() {
    if(system === 1){
        return window.Android.getAndroidId();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getVersionName() {
    if(system === 1){
        return window.Android.getVersionName();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getAdLinkUrl() {
    if(system === 1){
        return window.Android.getAdLinkUrl();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getAdImageUrl() {
    if(system === 1){
        return window.Android.getAppId();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function isDisplayAd() {
    if(system === 1){
        return window.Android.isDisplayAd();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function isRegisterEnabled() {
    if(system === 1){
        return window.Android.isRegisterEnabled();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function isNameAuthEnabled() {
    if(system === 1){
        return window.Android.isNameAuthEnabled();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function isPlatformMoneyEnabled() {
    if(system === 1){
        return window.Android.isPlatformMoneyEnabled();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function isBindMobileEnabled() {
    if(system === 1){
        return window.Android.isBindMobileEnabled();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function discount() {
    if(system === 1){
        return window.Android.getDiscount();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getAccount() {
    if(system === 1){
        return window.Android.getAccount();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getUid() {
    if(system === 1){
        return window.Android.getUid();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getCUid() {
    if(system === 1){
        return window.Android.getCUid();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getPayParams() {
    if(system === 1){
        return window.Android.getPayParams();
    } else if(system === 2){
        return '';
        // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
    }
}
function getParamsUrl(url) {

    var urlObject = {};
    if (/\?/.test(url)) {
        var urlString = url.substring(url.indexOf("?")+1);
        var urlArray = urlString.split("&");
        for (var i=0, len=urlArray.length; i<len; i++) {
            var urlItem = urlArray[i];
            if (i == urlArray.length - 1) {
                var index = urlItem.indexOf("=");
                var length = urlItem.length;
                var key = urlItem.substr(0,index);
                var value = urlItem.substr(index+1,length-(index+1));
                urlObject[key] = value;

            }
            else {
                var item = urlItem.split("=");
                urlObject[item[0]] = item[1];
            }
        }

        return urlObject;
    }
};
