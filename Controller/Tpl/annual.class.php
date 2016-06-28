<?php
/**
 * 年卡入口页面
 * 为了相应的url比较好看，方法名都直接用小写的单词，不加下划线和驼峰
 *
 * @author dwer
 * @date 2016-01-20 
 * 
 */

namespace Controller\Tpl;

use Library\Controller;

class annual extends Controller {
    /**
     * 如果是需要加载头部和侧边栏，就必须要调用initPage方法
     * @author dwer
     * @date   2016-06-20
     *
     */
    public function __construct() {
        $this->initPage();
    }
    
    public function index() {
        $this->display('annual/index');
    }

    public function package() {
        $this->display('annual/package');
    }

    public function prod() {
        $this->display('annual/prod');
    }

    public function entry() {
        $this->display('annual/entry');
    }

    public function order() {
        $this->display('annual/order');
    }

    public function storage() {
        $this->display('annual/storage');
    }
}