<?php
/**
 * 前端注册页面
 * 为了相应的url比较好看，方法名都直接用小写的单词，不加下划线和驼峰
 *
 * @author dwer
 * @date 2016-01-20 
 * 
 */

namespace Controller\Tpl;

use Library\Controller;

class register extends Controller {
    /**
     * 如果是需要加载头部和侧边栏，就必须要调用initPage方法
     * @author dwer
     * @date   2016-06-20
     *
     */
    public function __construct() {

    }
    
    /**
     * 注册主页
     * @author dwer
     * @date   2016-07-13
     *
     * @return 
     */
    public function index() {
        $this->display('register/index');
    }

    /**
     * 分销商或是供应商选择页
     * @author dwer
     * @date   2016-07-13
     *
     * @return 
     */
    public function dtype() {
        $this->display('register/dtype');
    }
}