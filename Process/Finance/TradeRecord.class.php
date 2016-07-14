<?php
/**
 * 交易记录数据统一处理层
 * 有些数据需要比较复杂的而且可重用的处理，可以统一放在这边处理
 * 
 * @author dwer
 * @date   2016-07-14
 *
 */
namespace Process\Finance;

class TradeRecord {

    /**
     * 如果查询的参数比较多，可以将入参的处理统一放在这边处理
     * @author dwer
     * @date   2016-07-14
     *
     * @return array
     */
    public function getQueryParams() {
        //按参数的处理，返回相应的处理数据

        if(!I('post.page')) {
            return ['code' => 400, 'msg' => '参数错误'];
        }

    }

    /**
     * 数据的转换，可以将复杂的数据转换统一放在这里
     * @author dwer
     * @date   2016-07-14
     *
     * @return []
     */
    public function transferData($data) {
        //数据的处理过程

        return $data;
    }


}