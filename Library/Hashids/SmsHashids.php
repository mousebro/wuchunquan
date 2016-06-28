<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 12/30-030
 * Time: 11:35
 */
namespace Library\Hashids;
class SmsHashids extends Hashids
{
    public function __construct()
    {
        parent::__construct('pft12301');
    }
}