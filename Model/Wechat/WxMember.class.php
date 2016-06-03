<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/18-018
 * Time: 11:25
 */

namespace Model\Wechat;


use Library\Model;

class WxMember extends Model
{
    const __TABLE_WX_MEMBER__ = 'uu_wx_member_pft';

    /**
     * 设置uu_wx_member_pft表的接收通知帐号
     * @param $id
     * @param $code
     * @return bool
     */
    public function setNotify($id, $code)
    {
        return $this->table(self::__TABLE_WX_MEMBER__)
            ->where(['id'=>$id])->save(['verifycode'=>$code]);
    }
}