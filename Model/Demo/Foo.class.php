<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 2/18-018
 * Time: 14:50
 */
namespace Model\Demo;
use Library\Exception;
use Library\Model;
class Foo extends Model
{
//    public function setConnection($connection)
//    {
//        $this->connection = $connection;
//    }
//    protected $tableName = 'pft_member';
    public static function say()
    {
        echo 'hello world';
    }
    protected $fields = [];
    //不自动检测数据表字段信息
    protected $autoCheckFields = false;

    protected function _initialize()
    {

    }

    /**
     * sql语句模式
     */
    public function members()
    {
        return $this->query("select * from pft_member where id=94 limit 1");
    }

    public function findMemberById($id)
    {
        return self::where("id=$id")->find();
    }

    /**
     * 绑定参数查询
     *
     * @param $id
     * @return mixed
     */
    public function findMemberByIdDemo2($id)
    {
        $where['id'] = ':id';
        return $this->where($where)->bind(':id',$id,\PDO::PARAM_INT)->select();
    }

    /**
     * 更新
     *
     * @param $id
     * @return bool
     */
    public function updateMember($id)
    {
        $this->dname = '陈光鹏测试';
        $this->ctel  = '100086';
        return $this->where("id=$id")->save();
    }

    /**
     * 创建数据
     *
     * @param $mainData
     * @param array $extData
     * @return int
     */
    public function register($mainData, $extData=array())
    {
        try {
            $this->startTrans();
            $result1 = $this->Table('pft_member')->data($mainData)->add();
            $mid     = $this->getLastInsID();
            $flag = 1;
            $extData = ['fid'=>$mid];
            $result2 = $this->Table('pft_member_extinfo')->data($extData)->add();
            if ($result1 && $result2) $this->commit();
            else $this->rollback();
        } catch (Exception $e) {
            echo $e->getMessage();
            return 0;
        }
        return $flag;
    }

    /**
     * 跨模型调用
     *
     * @param $ticket_id
     * @param Bar $bar
     * @return mixed
     */
    public function show_ticket($ticket_id, Bar $bar)
    {
        return $bar->show_ticket_info($ticket_id);
    }
}