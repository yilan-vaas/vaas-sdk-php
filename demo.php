<?php

class ylUtil
{
    public static function encrypt($key, $input)
    {
        $mode = 'AES-256-CBC';
        //IV取秘钥的前16字节
        $iv   = substr($key, 0, 16);
        $data = openssl_encrypt($input, $mode, $key, OPENSSL_RAW_DATA, $iv);
        $data = base64_encode($data);
        return $data;
    }

    public static function decrypt($key, $enData)
    {
        $mode   = 'AES-256-CBC';
        $iv     = substr($key, 0, 16);
        $deData = base64_decode($enData);
        $deData = openssl_decrypt($deData, $mode, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        return rtrim(self::PKCS5UnPadding($deData));
    }

    private static function PKCS5UnPadding($data)
    {
        $len     = strlen($data);
        $padding = ord($data[$len - 1]);
        if ($padding > 0 && $padding <= 16) {
            $data = substr($data, 0, $len - $padding);
        }
        return $data;
    }

    public static function curl($url, $method = 'GET', $postFields = null, $header = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == "https") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($postFields)) {
                    if (is_array($postFields)) {
                        $postBodyString = "";
                        $postMultipart  = false;
                        foreach ($postFields as $k => $v) {
                            if ("@" != substr($v, 0, 1)) { //判断是不是文件上传
                                $postBodyString .= "$k=" . urlencode($v) . "&";
                            } else { //文件上传用multipart/form-data，否则用www-form-urlencoded
                                $postMultipart = true;
                            }
                        }
                        unset($k, $v);
                        if ($postMultipart) {
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                        } else {
                            curl_setopt(
                                        $ch,
                                        CURLOPT_POSTFIELDS,
                                        substr($postBodyString, 0, -1)
                                        );
                        }
                    } else {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                    }
                }
                break;
            default:
                if (!empty($postFields) && is_array($postFields)) {
                    $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($postFields);
                }
                break;
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($header) && is_array($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch), 0);
        }
        curl_close($ch);
        return $response;
    }
}

$domain = 'http://api.yilanvaas.cn';
$uri = '/video/channels';
$access_key   = 'your access_key';
$access_token = 'your access_token';
$timestamp    = time() * 1000;
$params       = [
    'udid'       => 'your udid',
    'pkg_name'   => 'your pkg_name',
    'platform'   => 1,
    'video_type' => 1,
];
$header = [];

$en_params = ylUtil::encrypt($access_token, json_encode($params));
$hmac_key  = $access_token . $timestamp;

$sign     = base64_encode(hash_hmac("sha256", $en_params, $hmac_key, true));
$postBody = json_encode(['access_key' => $access_key, 'params' => $en_params, 'timestamp' => $timestamp, 'sign' => $sign]);

$res = ylUtil::curl($domain . $uri, 'POST', $postBody, $header);
echo('接口原始返回：' . $res . PHP_EOL . PHP_EOL);
$res = json_decode($res, true);
if ($res['code'] == 200) {
    $data = ylUtil::decrypt($access_token, $res['data']);
    echo('解密后数据：' . $data);
}
