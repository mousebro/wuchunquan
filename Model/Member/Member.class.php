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

}