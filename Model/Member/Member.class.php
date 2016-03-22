<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 2/18-018
 * Time: 14:50
 */

namespace Model\Member;
use Library\Model;
class Member extends Model
{
    const __MEMBER_TABLE__ = 'pft_member';

    protected $connection = '';
    public static function say()
    {
        echo 'hello world';
    }

    /**
     * 根据账号获取用户信息
     * @param  mixed $identify 字段值
     * @param  mixed $field    字段名
     * @return mixed        [description]
     */
    public function getMemberInfo($identify, $field = 'id') {
        
        $where[$field] = $identify;

        $member = $this->table(self::__MEMBER_TABLE__)->where($where)->find();
        return $member ?: false;
    }

}