<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/19-2016
 * Time: 11:00
 * 操作日志模型
 */

namespace Model\SystemLog;


use Library\Model;

class OptLog extends Model
{
    /**
     * 员工操作日志
     *
     * @param $fid
     * @param $sid
     * @param $daction
     * @return mixed
     */
    public function StuffOptLog($fid, $sid, $daction)
    {
        $data = [
            'fid'   =>$fid,
            'sid'   =>$sid,
            'daction'=>$daction,
            'rectime'=>date('Y-m-d H:i:s'),
        ];
        return $this->table('pft_d_operation_rec')->data($data)->add();
    }
}