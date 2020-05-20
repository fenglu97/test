var Api = (function () {
    var system = (function() {
        var u = navigator.userAgent;
        var isAndroid = u.indexOf('Android') > -1 || u.indexOf('Adr') > -1; //android终端
        var isiOS = !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/); //ios终端
        return isAndroid?1:2;
    })();
    // if(system === 2){
    //     window.appIosCallback = function (data) {
    //         // config.iosInfo = JSON.parse(Base64.decode(data));
    //         var iosInfo = JSON.parse(Base64.decode(data));
    //         config.appid = iosInfo.getAppId;
    //         config.appkey = iosInfo.getAppKey;
    //         config.channel = iosInfo.getChannel;
    //         config.maker = iosInfo.getMaker;
    //         config.mobile_model = iosInfo.getMobileModel;
    //         config.machine_code = iosInfo.getMachineCode;
    //         config.system_version = iosInfo.getVersionName;
    //         config.getAdLinkUrl = iosInfo.getAdLinkUrl;
    //         config.getAdImageUrl = iosInfo.getAdImageUrl;
    //         config.isDisplayAd = iosInfo.isDisplayAd;
    //         config.isRegisterEnabled = iosInfo.isRegisterEnabled;
    //         config.isNameAuthEnabled = iosInfo.isNameAuthEnabled;
    //         config.isPlatformMoneyEnabled = iosInfo.isPlatformMoneyEnabled;
    //         config.isBindMobileEnabled = iosInfo.isBindMobileEnabled;
    //         config.getDiscount = iosInfo.getDiscount;
    //     };
    //     window.webkit.messageHandlers.wz_callOC.postMessage({method : 'getAppInfo'});
    // }

    var config = {
        host: 'http://sdk.23yxm.com.com',
        key: 'e10adc3949ba59abbe56e057f20f883e',
        system: system,
        appid: (function () {
            if(system === 1){
                return window.Android.getAppId();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        appkey: (function () {
            if(system === 1){
                return window.Android.getAppKey();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        //渠道ID
        channel: (function () {
            if(system === 1){
                return window.Android.getChannel();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 厂商
        maker: (function () {
            if(system === 1){
                return window.Android.getMaker();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 手机类型
        mobile_model: (function () {
            if(system === 1){
                return window.Android.getMobileModel();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 手机设备号
        machine_code: (function () {
            if(system === 1){
                return window.Android.getMachineCode();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 设备ID
        machine_id: (function () {
            if(system === 1){
                return window.Android.getAndroidId();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 系统版本
        system_version: (function () {
            if(system === 1){
                return window.Android.getVersionName();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 广告图片跳转链接
        getAdLinkUrl: (function () {
            if(system === 1){
                return window.Android.getAdLinkUrl();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 广告图片地址
        getAdImageUrl: (function () {
            if(system === 1){
                return window.Android.getAdImageUrl();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 是否显示广告图片
        isDisplayAd: (function () {
            if(system === 1){
                return window.Android.isDisplayAd();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 是否开启注册
        isRegisterEnabled: (function () {
            if(system === 1){
                return window.Android.isRegisterEnabled();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 是否开启实名认证
        isNameAuthEnabled: (function () {
            if(system === 1){
                return window.Android.isNameAuthEnabled();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 是否显示平台币和平台币支付
        isPlatformMoneyEnabled: (function () {
            if(system === 1){
                return window.Android.isPlatformMoneyEnabled();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 是否开启绑定手机
        isBindMobileEnabled: (function () {
            if(system === 1){
                return window.Android.isBindMobileEnabled();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
        // 折扣
        discount: (function () {
            if(system === 1){
                return window.Android.getDiscount();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),
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
        // 保存账号
        /*setAccount: (function () {
            if(system === 1){
                return window.Android.setAccount();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),*/
        //获取账号
        getAccount: (function () {
            if(system === 1){
                return window.Android.getAccount();
            } else if(system === 2){
                return '';
                // window.webkit.messageHandlers.wz_callOC.postMessage({method : 'wz_close_user_center'});
            }
        })(),

    };

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
            key = key || config.key;
            return md5(util.jointUrl(options) + key).toLowerCase();
        },
        localStorage: {
            get: function (name) {
                return JSON.parse(localStorage.getItem(name))
            },
            set: function (name, val) {
                localStorage.setItem(name, JSON.stringify(val))
            },
            add: function (name, addVal) {
                let oldVal = util.localStorage.get(name)
                let newVal = oldVal.concat(addVal)
                util.localStorage.set(name, newVal)
            }
        },
        sessionStorage: {
            get: function (name) {
                return JSON.parse(sessionStorage.getItem(name))
            },
            set: function (name, val) {
                sessionStorage.setItem(name, JSON.stringify(val))
            },
            add: function (name, addVal) {
                let oldVal = util.sessionStorage.get(name)
                let newVal = oldVal.concat(addVal)
                util.sessionStorage.set(name, newVal)
            }
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


    return {
        config: config,
        util: util
    }
})();
