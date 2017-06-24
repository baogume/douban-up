# 豆瓣自动顶贴

## Installation

Installation is possible using [Composer](https://getcomposer.org/).

If you don't already use Composer, you can download the `composer.phar` binary:

    curl -sS https://getcomposer.org/installer | php

Then install the library:

    php composer.phar require baogume/douban-up

## Run
    require 'douban_up.php';
    $douban = new douban_up();
    $douban->username      = '豆瓣用户名';
    $douban->password      = '豆瓣密码';
    $douban->code_username = '打码账号用户名';
    $douban->code_password = '打码账号密码';
    $douban->code_id       = '打码账号软件ID';
    $douban->code_key      = '打码账号软件Key';
    $douban->comment       = '顶帖内容';
    // 顶帖url和内容 如果为空则用$douban->comment
    $douban->douban_up_url = [
        'https://www.douban.com/group/topic/xxxx/' => 'up1',
        'https://www.douban.com/group/topic/xxxx/' => 'up2',
        'https://www.douban.com/group/topic/xxxx/' => 'up3'
    ];
    print_r($douban->douban_up());
