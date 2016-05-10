<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/22-022
 * Time: 13:56
 */

namespace Controller;


use Library\Controller;

class Member extends Controller
{
    public function resetPassword()
    {
        $limit_channel = ['ResetOperPassword',];
        //$model = $this->model('Member\Member');
        $model = new \Model\Member\Member();
        $member_id = $_SESSION['memberID'];
        if (I('post.member_id')>0 && in_array(I('post.channel'), $limit_channel)) {
            $member_id = I('post.member_id')+0;
        }
        if (!$member_id) parent::apiReturn(parent::CODE_AUTH_ERROR,[], '身份校验失败');
        $new_password1 = I('post.new_pwd');
        $new_password2 = I('post.confirm_pwd');
        if (isset($_POST['old'])) {
            $old_password  = md5(md5(I('post.old')));
            if (! $model->checkOldPassword($member_id, $old_password)) {
                parent::apiReturn(parent::CODE_INVALID_REQUEST,[], '旧密码不正确');
            }
        }

        $res = self::chkPassword($new_password1, $new_password2);
        if ($res !== true) {
            parent::apiReturn(parent::CODE_AUTH_ERROR,[], $res);
        }
        if ($model->resetPassword($member_id, $new_password1, false)) {
            parent::apiReturn(parent::CODE_SUCCESS,[], '修改成功');
        }
        parent::apiReturn(parent::CODE_INVALID_REQUEST,[], '修改失败');
    }

    public static function chkPassword($p1, $p2)
    {
        $p1 = strval($p1);
        $commonWeakPassword = array (
            '123456','a123456','a123456789','woaini1314','qq123456','abc123456',
            '123456a','123456789a','abc123','qq123456789','123456789.',
            'woaini','q123456','123456abc','123456.','0123456789',
            'asd123456','aa123456','q123456789','abcd123456','woaini520',
            'woaini123','w123456','aini1314','abc123456789','woaini521',
            'qwertyuiop','qwe123456','asd123','123456789abc','z123456',
            'aaa123456','abcd1234','www123456','123456789q','123abc',
            'qwe123','w123456789','123456qq','zxc123456','qazwsxedc',
            '123456..','zxc123','asdfghjkl','123456q','123456aa',
            '9876543210','qaz123456','qq5201314','as123456',
            'z123456789','a123123','a5201314','wang123456','abcd123',
            '123456789..','woaini1314520','123456asd','aa123456789',
            '741852963','a12345678',
        );

        $len = mb_strlen($p1,'utf-8');
        if($p1!=$p2) {
            return "两次密码输入不一致";
        }
        elseif($len<6 || $len>20) {
            return "密码长度必须大于6小于20" . $len;
        }
        elseif ( ctype_digit($p1) || ctype_alpha($p1)) {
            //纯数字&纯字母的提示
            return '您设置的密码过于简单，请输入6-20位数字、字母或常用符号，字母区分大小写';
        }
        elseif (in_array($p1, $commonWeakPassword)) {
            return '您输入的密码太常见，很容易被人猜出，请重新选择无规则的数字字母组合。';
        }
        elseif(preg_match('/\s/', $p1)){
            return "密码仅支持英文、数字和字符，不支持空格";
        }
        return true;
    }
}