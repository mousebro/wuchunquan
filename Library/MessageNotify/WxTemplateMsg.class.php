<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/27-2016
 * Time: 16:41
 * 微信模板消息
 */
namespace Library\MessageNotify;
use \LaneWeChat\Core\TemplateMessage;
use \LaneWeChat\Core\OpenExt;

defined('PFT_INIT') or exit('Access Invalid!');

class WxTemplateMsg
{
    private $data;
    private $openid;
    private $tplId;
    private $url = '';
    private $color = '';

    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
        return $this;
    }
    public function Send()
    {
        $tplId = TemplateMessage::getTmpId($this->tplId, OpenExt::PFT_APP_ID);
        $res = TemplateMessage::openSendTemplateMessage(
            $this->data,
            $this->openid,
            $tplId,
            $this->url,
            $this->color,
            OpenExt::PFT_APP_ID
        );
        if ($res['errcode']==0 && $res['errmsg']=='ok') {
            return ['code'=>'200',];
        }
        return ['code'=>401, 'msg'=>$res['msg']];
    }
}