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

class card extends Controller {
    
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
        $this->assign('dog', 1000);

        $this->display('card/index');
    }

    /**
     * 某个功能模块的二级页面就对于的方法
     * http://www.12301.cc/new/card_user.html
     * 
     * @author dwer
     * @date   2016-06-18
     *
     * @return
     */
    public function user() {

    }

}