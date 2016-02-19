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


}