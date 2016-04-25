<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 2/18-018
 * Time: 14:50
 */

namespace Model\Member;
use Library\Cache\Cache;
use Library\Model;
class Member extends Model
{
    const __MEMBER_TABLE__ = 'pft_member';
    const __MEMBER_RELATIONSHOP_TABLE__ = 'pft_member_relationship';

    protected $connection = '';
    public static function say()
    {
        echo 'hello world';
    }

    private function getLimitReferer()
    {
        return  array(
            '12301.cc',
            '16u.cc',
            '12301.local',
            '12301.test',
            '12301dev.com',
            '9117you.cn',
            '9117you.cn',
            );
    }

    public function login($account, $password, $chk_code='')
    {
        $where = [
            'account|mobile'  =>':account',
            'status'          =>':status',
            //':password'=>':password',
        ];
        //$map['name|title'] = 'thinkphp';
        $res = $this->table('pft_member')
            ->getField('id,account,member_auth,dname,satus,id,password,derror,errortime,dtype')
            ->where($where)
            ->bind([':account'=>$account, ':status'=>[0, 3]])
            ->find();
        if (!$res)  return false;
        if ($res['password']!=$password) {
            $this->table('pft_member')
                ->where("id={$res['id']}")
                ->save(
                    [
                        'derror'    =>$res['derror']+1,
                        'errortime' =>date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])
                    ]);
            return ['code'=>201,'msg'=>'账号或密码错误'];
        }
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

    public function getMemberCache()
    {
        /** @var $cache \Library\Cache\CacheRedis;*/
        $cache = Cache::getInstance('redis');
        $members = $cache->get('global:members');
        //var_dump($members);
        //$members = $cache->hdel('global:members', '');
        if ($members) return $members;
        $items = $this->table(self::__MEMBER_TABLE__)->where("status=0 AND dtype IN(0,1)")->getField('id,account,dname', true);
        $data = [];
        foreach ($items as $item) {
            $data[$item['id']] = [
                'account'=>$item['account'],
                'dname'=>$item['dname']
            ];
        }
        //print_r($data);
        //exit;
        $cache->set('global:members', $data, '', 86400);
        return $data;
    }

    /**
     * 检测旧密码是否正确
     *
     * @param $memberid
     * @param $old_password
     * @return bool
     */
    public function checkOldPassword($memberid, $old_password)
    {
        $old = $this->table(self::__MEMBER_TABLE__)->where(['id'=>$memberid])->getField('password');
        return $old_password == $old;
    }

    /**
     * 检查是否建立过对应的关系
     *
     * @param $parent_id
     * @param $son_id
     * @param $ship_type
     */
    public function checkRelationShip($parent_id, $son_id, $ship_type)
    {
        $where = [
            'parent_id'=>':parent_id',
            'son_id'   => ':son_id',
            'ship_type' => ':ship_type',
        ];
        $bind = [
            ':parent_id'=> $parent_id,
            ':son_id'   => $son_id,
            ':ship_type'=> $ship_type,
        ];
        return $this->table(self::__MEMBER_RELATIONSHOP_TABLE__)
            ->where($where)
            ->bind($bind)
            ->getField('id');
    }

    /**
     * 重置用户密码
     * @param  [type] $memberid     [description]
     * @param  [type] $new_password [description]
     * @return [type]               [description]
     */
    public function resetPassword($memberid, $new_password, $hasMd5=false) {
        $new_password = $hasMd5 ? md5($new_password) : md5(md5($new_password));
        $data = array(
            'id'        => $memberid,
            'password'  => $new_password
        );
        $affect_rows = $this->table(self::__MEMBER_TABLE__)->save($data);
        return $affect_rows ? true : false;
    }

}