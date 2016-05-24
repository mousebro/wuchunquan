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

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }


    /**
     * @param mixed $openid
     */
    public function setOpenid($openid)
    {
        $this->openid = $openid;
    }

    /**
     * @param mixed $tplId
     */
    public function setTplId($tplId)
    {
        $this->tplId = $tplId;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @param mixed $color
     */
    public function setColor($color)
    {
        $this->color = $color;
    }

    public function setParams()
    {

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