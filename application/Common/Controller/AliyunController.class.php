<?php
namespace Common\Controller;

require './vendor/autoload.php';
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use Think\Controller;
use Think\Log;

class AliyunController extends Controller {

    const LOG_TPL = 'aliyun:';

    /**
     * 静态变量保存全局实例
     * @var null
     */
    private static $_instance = null;

    public function __construct()
    {
    }

    public static function getInstance() {
        if(is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function sendSms($phone = '',$code = '') {
        AlibabaCloud::accessKeyClient(C('access_key_id'),C('access_key_secret'))
            ->regionId('cn-hangzhou')
            ->asDefaultClient();
        try{
            $result = AlibabaCloud::rpc()
                                    ->product('Dysmsapi')
                                    ->version('2017-05-25')
                                    ->action('SendSms')
                                    ->method('POST')
                                    ->host('dysmsapi.aliyuncs.com')
                                    ->options([
                                        'query' => [
                                            'RegionId' => "cn-hangzhou",
                                            'PhoneNumbers' => $phone,
                                            'SignName' => C('sign_name'),
                                            'TemplateCode' => C('template_code'),
                                            'TemplateParam' => "{code:$code}"
                                        ],
                                    ])
                                    ->request();
            return $result->toArray();
        }catch (ClientException $exception) {
            Log::write(self::LOG_TPL.$exception->getErrorMessage());
            return false;
        }catch (ServerException $exception) {
            Log::write(self::LOG_TPL.$exception->getErrorMessage());
            return false;
        }
    }
}