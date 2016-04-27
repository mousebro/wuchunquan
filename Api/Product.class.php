<?php
/**
 * Created by PhpStorm.
 * User: cgp
 * Date: 16/4/23
 * Time: 12:29
 */

namespace Api;


use Library\Controller;

class Product extends Controller
{
    public function Create()
    {
        $params = [];
        $params['title']    = I('post.product_name', '', 'strip_tags,addslashes');
        $params['address']  = I('post.address', '', 'strip_tags,addslashes');
        $params['ptype']    = I('post.product_type');

        $params['area']     = I('post.city');

        $params['jqts']     = I('post.notice', '', 'strip_tags,addslashes');
        $params['bhjq']     = I('post.details','', 'htmlspecialchars,addslashes');
        $params['jtzn']     = I('post.traffic','', 'strip_tags,addslashes');
        $params['imgpath']  = I('post.img_path','', 'strip_tags,addslashes');
        $params['opentime'] = I('post.opentime', '', 'strip_tags,addslashes');
        $params['tel']      = I('post.tel', '', 'strip_tags,addslashes');

        $params['salerid']  = '';
    }
}