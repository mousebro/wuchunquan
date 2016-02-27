<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 2/22-022
 * Time: 15:01
 */

namespace Model\Demo;

use Library\Model;

class Bar extends Model
{
    protected $autoCheckFields = false;

    public function test()
    {
        return 'test';
    }

    /**
     * 关联查询
     *
     * @param $ticket_id
     * @return mixed
     */
    public function show_ticket_info($ticket_id)
    {
        $where['uu_jq_ticket.id'] = ':id';
        return $this->where($where)
            ->join('left join uu_products ON uu_products.id=uu_jq_ticket.pid')
            ->bind(':id',$ticket_id,\PDO::PARAM_INT)
            ->field('uu_jq_ticket.id,uu_jq_ticket.title,uu_products.p_name')
            ->select();
//        return $this->where($where)->bind(':id',$ticket_id,\PDO::PARAM_INT)->select();
    }

    /**
     * 调用存储过程
     *
     * @return mixed
     */
    public function call_procudure($id)
    {
        return $this->query("call test_p($id)");//正常
    }
}