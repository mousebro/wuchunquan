<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/5-005
 * Time: 16:33
 */

namespace Model\Product;


use Library\Model;

class Round extends Model
{
    public function __construct()
    {
        parent::__construct('remote_1');
    }

    public function GetRoundZoneInfo($venus_id)
    {
        $data = $this->table('pft_roundzone')
            ->field('id,zone_name')
            ->where(['venue_id'=>$venus_id])
            ->select();
        if ($data!=false) {
            return ['code'=>200,'data'=>$data, 'msg'=>'OK'];
        }
        return ['code'=>0, 'data'=>'','msg'=>'查询失败,错误描述:' . $this->getDbError()];
    }
}