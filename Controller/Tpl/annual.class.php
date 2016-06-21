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
    
    /**
     * 某个功能模块的首页就进入这个方法
     * http://www.12301.cc/new/card.html
     * 
     * @author dwer
     * @date   2016-06-18
     *
     * @return
     */
    public function index() {
        //需要传给模板的参数在这里定义
        //$this->assign('data', $data);

        $this->display('card/index');
    }

    /**
     * 某个功能模块的首页就进入这个方法
     * http://www.12301.cc/new/card.html
     * 
     * @author dwer
     * @date   2016-06-18
     *
     * @return
     */
    public function order() {
        //需要传给模板的参数在这里定义
        //$this->assign('data', $data);

        $this->display('card/order');
    }

    /**
     * publish_prod_info页面
     * 某个功能模块的二级页面就对于的方法
     * http://www.12301.cc/new/annual_prod.html
     * 
     * @author dwer
     * @date   2016-06-18
     *
     * @return
     */
    public function prod() {

        $this->display('annual/prod');
    }

    /**
     * publish_package_info
     * 某个功能模块的二级页面就对于的方法
     * http://www.12301.cc/new/annual_prod.html
     * 
     * @author dwer
     * @date   2016-06-18
     *
     * @return
     */
    public function package() {
        
        $this->display('annual/package');
    }

    /**
     * entry_card
     * 某个功能模块的二级页面就对于的方法
     * http://www.12301.cc/new/annual_prod.html
     * 
     * @author dwer
     * @date   2016-06-18
     *
     * @return
     */
    public function entry() {
        
        $this->display('annual/entry');
    }
}