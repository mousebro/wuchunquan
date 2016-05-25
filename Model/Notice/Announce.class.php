<?php
/**
 * User: Fang
 * Time: 11:24 2016/5/25
 */

namespace Model\Notice;


use Library\Model;

class Announce extends Model
{
    public function __construct()
    {
        parent::__construct('remote_1');
    }

    /**
     * get_recent_notice
     *
     * 获取最近一周的重要通知
     */
    public function get_rcnt_nts()
    {
        $rcnt   = strtotime("-1 week");
        $where  = [
            'create_time' => ['gt', $rcnt],
            'status'      => 0, //0-已发布 1-草稿 4-删除
            'lvl'         => 1, //0-普通公告 1-重要公告
        ];
        $field  = [
            'id as an_id',
            'title',
            'details',
            'create_time',
        ];
        $result = $this->table("pft_announce")->where($where)->field($field)->find();

        return $result;
    }

    /**
     * 是否已读公告
     *
     * @param int $mid   用户id
     * @param int $an_id 公告id
     *
     * @return mixed
     */
    public function is_read($mid, $an_id)
    {
        $where = ['mid' => $mid, 'an_id' => $an_id];

        return $this->table('pft_announce_ext')->where($where)->getField('id');
    }

    /**
     * 添加已读记录
     *
     * @param int $mid   用户id
     * @param int $an_id 公告id
     *
     * @return mixed
     */
    public function add_read($mid, $an_id)
    {
        $data = ['mid' => $mid, 'an_id' => $an_id];

        return $this->table('pft_announce_ext')->add($data);
    }

}