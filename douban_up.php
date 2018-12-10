<?php
/**
* 豆瓣自动顶帖
* @author Smile Chen <819916913@qq.com>
*/
require_once 'curl.php';
class douban_up
{
    // Curl对象
    private $curl;

    // 豆瓣用户名
    public $username = '';
    // 豆瓣密码
    public $password = '';
    // 打码账号用户名
    public $code_username = '';
    // 打码账号密码
    public $code_password = '';
    // 打码账号软件ID
    public $code_id = '';
    // 打码账号软件Key
    public $code_key = '';
    // 打码图片类型 http://www.ysdm.net/home/PriceType
    public $code_type = 2000;

    private $token = '';
    private $ck = '';

    private $douban_login_url = 'https://accounts.douban.com/login';

    // 顶帖URL 可以是数组
    public $douban_up_url;
    public $comment = 'up';

    function __construct()
    {
        $this->curl = new Curl('cookie.txt');
        $this->curl->options[CURLOPT_TIMEOUT] = 30;
        $this->curl->options[CURLOPT_SSL_VERIFYPEER] = false;
    }

    /**
     * 豆瓣登陆
     * @param  string $username 豆瓣用户名
     * @param  string $password 豆瓣密码
     * @param  string $url      如果是重新登陆则返回需要访问的url内容
     * @return bool|object
     */
    public function login($username = '', $password = '', $url = '')
    {
        if (empty($username) && !empty($password) && empty($this->username) && empty($this->password)) {
            return false;
        }
        $username = empty($username) ? $this->username : $username;
        $password = empty($password) ? $this->password : $password;

        $this->curl->options[CURLOPT_SSL_VERIFYPEER] = false;
        $response = $this->curl->get($this->douban_login_url);

        preg_match('#value="(.*?):en"/>#', $response->body, $match);
        preg_match('#id="captcha_image" src="(.*?)"#i', $response->body, $captcha_url);
        $token = $code = '';
        $post_str = 'source=None&redir=https%3A%2F%2Fwww.douban.com&form_email='.$username.'&form_password='.$password.'&login=%E7%99%BB%E5%BD%95';
        if (!empty($match)  && !empty($captcha_url)) {
            echo "登陆有验证码\r\n";
            $captcha_url = $captcha_url[1];
            $token = $match[1];
            try {
                $code = $this->captcha($captcha_url);
            } catch (Exception $e) {
                echo $e->getMessage() . "\r\n";
                return false;
            }
            $post_str = 'source=None&redir=https%3A%2F%2Fwww.douban.com&form_email='.$username.'&form_password='.$password.'&login=%E7%99%BB%E5%BD%95&captcha-id='.$token.'%3Aen&captcha-solution='.$code;
        }

        $this->curl->options[CURLOPT_FOLLOWLOCATION] = true;
        $response = $this->curl->post($this->douban_login_url, $post_str);
        if (strpos($response->body, '个人主页') !== false && strpos($response->body, '退出') !== false ) {
            echo "登陆成功\r\n";
            if ($url) {
                return $this->curl->get($url);
            }
            return true;
        }

        echo "登陆失败\r\n";
        return false;
    }

    /**
     * 云速打码平台验证码识别
     * @param  string $captcha_url 打码图片url
     * @return string 验证码识别结果
     */
    private function captcha($captcha_url)
    {
        if (empty($this->code_username) || empty($this->code_password) || empty($this->code_id) || empty($this->code_key)) {
            throw new Exception("打码账号未设置");
        }
        $url = "http://api.ysdm.net/create.json";
        $key = "image\"; filename=\"image.jpg\"\r\nContent-Type: application/octet-stream\r\nAccept: \"";
        $this->curl->headers["Accept"] = "image/webp,image/*,*/*;q=0.8";
        $response = $this->curl->get($captcha_url);

        $captcha_post_data = [
            'username' => $this->code_username,
            'password' => $this->code_password,
            'typeid'   => $this->code_type,
            'timeout'  => 60,
            'softid'   => $this->code_id,
            'softkey'  => $this->code_key,
            $key       => $response->body
        ];
        // 验证码识别重试三次
        for ($i=0; $i < 2; $i++) {
            echo "开始识别验证码\r\n";
            $response = $this->curl->upload($url, $captcha_post_data);

            $captcha_data = json_decode($response->body, true);
            if (isset($captcha_data['Result'])) {
                echo "验证码识别结果: {$captcha_data['Result']}\r\n";
                return $captcha_data['Result'];
            } elseif (isset($captcha_data['Error'])) {
                if ($i == 2) {
                    throw new Exception($captcha_data['Error']);
                }
                continue;
            }
        }

        return false;
    }

    /**
     * 顶帖方法
     */
    public function douban_up()
    {
        $start_time = (int)microtime(true);
        if (empty($this->douban_up_url)) {
            echo "顶帖URL为空\r\n";
            return false;
        }

        $urls = $this->douban_up_url;

        if (is_string($urls)) {
            $temp[$urls] = $this->comment;
            $urls = $temp;
        }

        foreach ($urls as $url => $comment) {
            $comment = empty($comment) ? $this->comment : $comment;
            if (empty($comment)) {
                echo "url:{$url} 顶帖内容为空\r\n";
                continue;
            }

            echo "开始顶帖:{$url} 顶帖内容:{$comment}\r\n";
            preg_match('#(/\d+)#', $url,$mat);
            if (empty($mat)) {
                echo "{$url} --> 错误的url\r\n";
                continue;
            }

            // 访问第一页的最后一个评论查看评论框在多少页
            $response = $this->curl->get($url . '/?start=0#last');
            // 验证是否登陆
            if (strpos($response->body, '登录')) {
                echo "cookie过期，开始重新登陆\r\n";
                $response = $this->login('', '', $url . '/?start=0#last');
                if (!$response) {
                    echo "url:{$url}顶帖失败\r\n";
                    continue;
                }
            }

            // 获取所有的评论翻页num
            preg_match_all('#/topic/.*?/?start=(\d+)#i', $response->body, $page_match);
            // 评论post的必要参数
            preg_match('#<input type="hidden" name="ck" value="(.*?)"/>#i', $response->body, $ck);
            $code = '';
            $ck = !empty($ck) ? $ck[1] : '';
            if (!empty($page_match)) {
                $comment_page_num = max($page_match[1]);
                // 没有验证码的评论post
                $post_data = 'ck='.$ck.'&rv_comment='.$comment.'&start='.$comment_page_num.'&submit_btn=%E5%8A%A0%E4%B8%8A%E5%8E%BB';
                echo "帖子已经顶帖到" . ($comment_page_num / 100 + 1) . "页\r\n";

                // 请求有评论框的页面
                $response = $this->curl->get($url . "/?start={$comment_page_num}#last");
                preg_match('#id="captcha_image" src="(.*?)"#i', $response->body, $captcha_url);
                preg_match('#value="(.*?):en"/>#', $response->body, $page_match);
                if (!empty($captcha_url)) {
                    echo "顶帖有验证码\r\n";
                    $token = $page_match[1];
                    $captcha_url = $captcha_url[1];
                    try {
                        $code = $this->captcha($captcha_url);
                    } catch (Exception $e) {
                        echo $e->getMessage() . "\r\n";
                        echo "url:{$url}顶帖失败\r\n";
                        continue;
                    }
                }
                if ($code) {
                    // 有验证码的评论post
                    $post_data = 'ck='.$ck.'&rv_comment='.$comment.'&captcha-solution='.$code.'&captcha-id='.$token.'%3Aen&start='.$comment_page_num.'&submit_btn=%E5%8A%A0%E4%B8%8A%E5%8E%BB';
                }
            }

            // 关闭自动跳转以判断是否顶帖成功
            $this->curl->follow_redirects = false;
            $response = $this->curl->post($url . 'add_comment', $post_data);
            if (strpos($response->body, 'post=ok#last') !== false) {
                echo "顶帖成功\r\n\r\n";
            } else {
                echo "顶帖失败\r\n\r\n";
            }
        }

        $end_time = (int) microtime(true) - $start_time;
        echo "耗时:{$end_time}s\r\n";
    }
    
    public function deleteComment($url = "") {
        if (empty($this->douban_up_url) && empty($url)) {
            echo "帖子为空";
            return false;
        }
        $urls = empty($this->douban_up_url) ? $url : array_keys($this->douban_up_url);
        if (!is_array($urls)) {
            $urls = [$urls];
        }
        foreach ($urls as $url) {
            $topicID = "";
            preg_match('#topic/(\d+)/#is', $url, $topic_match);
            $topicID = $topic_match[1];
            if (empty($topicID)) {
                echo "{$url}错误";
                continue;
            }
            $response = $this->curl->get($url . '/?start=0#last');
            // 验证是否登陆
            if (strpos($response->body, '登录')) {
                echo "cookie过期，开始重新登陆\r\n";
                $response = $this->login('', '', $url . '/?start=0#last');
                if (!$response) {
                    echo "url:{$url}登陆失败\r\n";
                    continue;
                }
            }

            // 获取所有的评论翻页num
            preg_match_all('#data-cid="(\d+)"#i', $response->body, $cids_match);
            // 评论post的必要参数
            preg_match('#<input type="hidden" name="ck" value="(.*?)"/>#i', $response->body, $ck);
            $cids = $cids_match[1];
            $ck = !empty($ck) ? $ck[1] : '';
            if (empty($cids) || empty($ck)) {
                echo "{$url}出现错误";
                continue;
            }
            foreach ($cids as $cid) {
                // https://www.douban.com/j/group/topic/128473095/remove_comment
                $u = "https://www.douban.com/j/group/topic/{$topicID}/remove_comment";
                $resp = $this->curl->post($u, ["cid" => $cid, "ck" => $ck]);
                $json = json_decode($resp->body, true);
                echo $json['r'] == 1 ? "删除成功\r\n" : "删除失败\r\n";
		sleep(mt_rand(11, 23));
            }
        }
    }
}
