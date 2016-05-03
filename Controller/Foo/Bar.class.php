<?php

/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/25-025
 * Time: 18:30
 */
namespace Controller\Foo;
use Library\Controller;
use Model\Demo\Foo;

class Bar extends Controller
{
    public function test()
    {
        echo 'abc';
    }

    public function getMemberById()
    {
        $model = new Foo();

        $memberId = I('get.id');
        //$memberId = I('post.id');
        $data = $model->findMemberById($memberId);
        print_r($data);
    }

    public function pipeline()
    {
        $redis = new \Redis();
        $redis->ping();
        $redis->multi();
    }

}