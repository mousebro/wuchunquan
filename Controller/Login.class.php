<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/8-008
 * Time: 10:01
 */

namespace Controller;


class Login
{

    function random($len) {
        $srcstr="ABCDEFGHIJKLMNPQRSTUVWXYZ123456789";
        mt_srand();
        $strs="";
        for($i=0;$i<$len;$i++) {
            $strs.=$srcstr[mt_rand(0,33)];
        }
        return ($strs);
    }
    //生成验证码方法
    public function getCode()
    {
        $str= $this->random(4);    //随机生成的字符串
        $width=60;    //验证码图片的宽度
        $height=25;    //验证码图片的高度
        //Date in the past
        header("Expires:Mon,26 Jul 1997 05:00:00 GMT");
        //always modified
        header("Last-Modified:".gmdate("D,d M Y H:i:s")."GMT");
        //HTTP/1.1
        header("Cache-Control:no-store,no-cache,must-revalidate");
        header("Cache-Control:post-check=0,pre-check=0",false);
        //HTTP/1.0
        header("Pragma:no-cache");
        header("Content-Type:image/png");
        $im=imagecreate($width,$height);
        $back=imagecolorallocate($im,0xFF,0xFF,0xFF);    //背景色
        //$pix=imagecolorallocate($im,187,190,247);        //模糊点颜色
        $font=imagecolorallocate($im,41,163,238);        //字体色
        //绘制1000个模糊作用的点
        //mt_srand();
        //for($i=0;$i<1000;$i++) {
        //    imagesetpixel($im,mt_rand(0,$width),mt_rand(0,$height),$pix);
        //}
        imagestring($im,5,7,5,$str,$font);//绘制随机生成的字符串
        //imagerectangle($im,0,0,$width-1,$height-1,$font);//在验证码图像周围绘制1px的边框
        imagepng($im);//建立一张PNG格式图形
        imagedestroy($im);//将图片handle解构，释于内存空间
        $_SESSION["auth_code"]=$str;
    }
}