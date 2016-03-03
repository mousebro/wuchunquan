<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/3-003
 * Time: 9:25
 *
 * 产品模型
 */

namespace Model\Product;
use Library\Model;

class Land extends Model
{
    /**
     * 生成并获取终端ID
     * @author Guangpeng Chen
     * @date 2016-03-03 09:05:39
     * @return int
     */
    public  function getTerminalId()
    {
        return $this->table('pft_terminal_id')->add(['id'=>'null']);
    }
}