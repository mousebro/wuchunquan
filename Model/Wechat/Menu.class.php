<?php

/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/2-002
 * Time: 17:14
 *
 * 保存微信菜单
 */
namespace Model\Wechat;
use Library\Model;
class Menu extends Model
{
    protected $tableName = 'pft_wechat_menus';

    /**
     * 设置微信菜单
     *
     * @param $appid string 微信appid
     * @param $contents string 微信菜单内容，json格式
     * @return bool
     */
    public function Set($appid, $contents)
    {

        $data = [
            'appid'  => $appid,
            'contents'     => $contents,
        ];
        return $this->data($data)->add('','', true);
    }

    /**
     * 获取微信菜单
     *
     * @param $appid
     * @return mixed
     */
    public function Get($appid)
    {
        $where = [
            'appid'=> ':appid',
        ];
        return $this->where($where)
            ->bind([':appid'=>$appid, ])
            ->field('contents')
            ->limit(1)
            ->find();
    }

}