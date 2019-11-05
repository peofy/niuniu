<?php

namespace app\admin\controller;


use app\common\model\Member as MemberModel;
use app\common\model\User as UserModel;
use app\common\model\Withdrawals;
use app\common\model\Users;
use app\common\model\AgentInfo;
use think\Request;
use think\Db;
use think\Loader;

class Bonuspools extends Common
{


    public function index()
    {
        $list = Db::name('bonus_setting')->select();
        $this->assign('list', $list);
        $this->assign('meta_title', '奖金池设置');
        return $this->fetch();
    }

    public function edit()
    {

        $id   = input('id', 0);

        $info = Db::table('bonus_setting')->where('id', $id)->find();

        if (request()->isPost()) {

            $recommend      = input('recommend/f', 0);
//            $pingji_level       = input('pingji_level/f', 0);
//            $layer_number       = input('layer_number/f', 0);
            $upgrade       = input('upgrade', '');
            $levelname  = input('levelname', '');
            $desc  = input('desc', '');

            if (empty($levelname)) {
                $this->error('等级名称不能为空！');
            }

            if (empty($levelname)) {
                $this->error('等级名称不能为空！');
            }
            $data['proportion']  = $recommend;
//            $data['pingji_level']   = $pingji_level / 100;
//            $data['direct_num']   = $layer_number;
            $data['direct_num']   = $upgrade;
            $data['bonusname']  = $levelname;
            $data['desc']  = $desc;
            $res = Db::table('bonus_pool_setting')->where(['id' => $id])->update($data);
//    print_r($res);die;
            if ($res !== false) {
                $this->success('操作成功', url('bonuspools/level'));
            } else {
                $this->error('失败！');
            }
        };


        return $this->fetch('', [
            'meta_title'    =>  '编辑奖金池设置',
            'info'          =>  $info,
        ]);
    }





    public function add()
    {

        $id   = input('id', 0);

        $info = Db::table('bonus_setting')->where('id', $id)->find();

        if (request()->isPost()) {

            $recommend      = input('recommend/f', 0);
//            $pingji_level       = input('pingji_level/f', 0);
//            $layer_number       = input('layer_number/f', 0);
            $upgrade       = input('upgrade', '');
            $levelname  = input('levelname', '');
            $desc  = input('desc', '');

            if (empty($levelname)) {
                $this->error('等级名称不能为空！');
            }

            if (empty($levelname)) {
                $this->error('等级名称不能为空！');
            }
            $data['proportion']  = $recommend;
//            $data['pingji_level']   = $pingji_level / 100;
//            $data['direct_num']   = $layer_number;
            $data['direct_num']   = $upgrade;
            $data['bonusname']  = $levelname;
            $data['desc']  = $desc;
            $res = Db::table('bonus_pool_setting')->where(['id' => $id])->insert($data);
            if ($res !== false) {
                $this->success('操作成功', url('bonuspools/level'));
            } else {
                $this->error('失败！');
            }
        };


        return $this->fetch('', [
            'meta_title'    =>  '增加奖金池设置',
            'info'          =>  $info,
        ]);
    }








    public function level()
    {
        $list = Db::name('bonus_pool_setting')->select();
        $this->assign('list', $list);
        $this->assign('meta_title', '分奖金设置');
        return $this->fetch();
    }


    public function level_edit()
    {

        $id   = input('id', 0);

        $info = Db::table('bonus_pool_setting')->where('id', $id)->find();

        if (request()->isPost()) {

            $recommend      = input('recommend/f', 0);
//            $pingji_level       = input('pingji_level/f', 0);
//            $layer_number       = input('layer_number/f', 0);
            $upgrade       = input('upgrade', '');
            $levelname  = input('levelname', '');
            $desc  = input('desc', '');
            $is_day = input('is_day', '');

            if (empty($levelname)) {
                $this->error('等级名称不能为空！');
            }

            $data['proportion']  = $recommend;
//            $data['pingji_level']   = $pingji_level / 100;
//            $data['direct_num']   = $layer_number;
            $data['direct_num']   = $upgrade;
            $data['bonusname']  = $levelname;
            $data['desc']  = $desc;
            $data['is_day']  = $is_day;

//            print_r($data);die;
            $res = Db::table('bonus_pool_setting')->where(['id' => $id])->update($data);
//    print_r($res);die;
            if ($res !== false) {
                $this->success('操作成功', url('bonuspools/level'));
            } else {
                $this->error('失败！');
            }
        };

        $info['is_day'] += 1;

        return $this->fetch('', [
            'meta_title'    =>  '编辑分奖金设置',
            'info'          =>  $info,
        ]);
    }



    public function level_add()
    {

        $id   = input('id', 0);

        $info = Db::table('bonus_pool_setting')->where('id', $id)->find();

        if (request()->isPost()) {

            $recommend      = input('recommend/f', 0);
//            $pingji_level       = input('pingji_level/f', 0);
//            $layer_number       = input('layer_number/f', 0);
            $upgrade       = input('upgrade', '');
            $levelname  = input('levelname', '');
            $desc  = input('desc', '');
            $is_day = input('is_day', '');

            if (empty($levelname)) {
                $this->error('等级名称不能为空！');
            }

            if (empty($levelname)) {
                $this->error('等级名称不能为空！');
            }
            $data['proportion']  = $recommend;
//            $data['pingji_level']   = $pingji_level / 100;
//            $data['direct_num']   = $layer_number;
            $data['direct_num']   = $upgrade;
            $data['bonusname']  = $levelname;
            $data['desc']  = $desc;
            $data['is_day']  = $is_day;
            $res = Db::table('bonus_pool_setting')->where(['id' => $id])->insert($data);
            if ($res !== false) {
                $this->success('操作成功', url('bonuspools/level'));
            } else {
                $this->error('失败！');
            }
        };


        return $this->fetch('', [
            'meta_title'    =>  '增加分奖金设置',
            'info'          =>  $info,
        ]);
    }


    //查看排行榜

    public function ranking_list()
    {
        $list=DB::name('order')
            ->alias('o')
            ->join('member m ','m.id=o.user_id')
            ->where('o.order_status > 1 AND o.order_status != 7')
            ->group('o.user_id')
            ->field('o.user_id,m.realname,sum(o.total_amount) as total')
            ->order('total desc')
            ->select();

        foreach ($list as $key => $value)
        {
            $list[$key]['rank'] =$key+1;
            if (($key-1) < 0){
                $list[$key]['different_last'] = 0;
            }else{
                $list[$key]['different_last'] = $list[$key-1]['total']-$list[$key]['total'];
            }

        }

        $this->assign('list', $list);
        $this->assign('meta_title', '查看排行榜');
        return $this->fetch();
    }


    //查看今日奖金池
    public function today_bonus()
    {
        $bonus = DB::name('bonus_pool')
            ->where('is_day',0)
            ->field('money,create_time')
            ->select();

        $sum = 0;
        foreach ($bonus as $key=>$value)
        {
            if (date('Y-m-d') == date('Y-m-d',$bonus[$key]['create_time'])){

                $sum = $sum + $value['money'];
            }
        }
        $lottery_time = date("Y-m-d",strtotime("+1 day"));

        $t = time();
        $start = mktime(0,0,0,date("m",$t),date("d",$t),date("Y",$t));
        $end = mktime(23,59,59,date("m",$t),date("d",$t),date("Y",$t));

        $list=DB::name('member')
            ->where('first_leader','>','0')
            ->where('createtime','>=',$start)
            ->where('createtime','<=',$end)
            ->group('first_leader')
            ->field('first_leader,realname,mobile ,count(first_leader) as num')
            ->order('num desc')
            ->limit(10)
            ->select();
        foreach ($list as $key=>$value){
            $list[$key]['No'] = $key+1;

            $list[$key]['mobile'] = DB::name('member')
                ->where('id',$value['first_leader'])
                ->field('mobile')
                ->find()['mobile'];
            $list[$key]['realname'] = DB::name('member')
                ->where('id',$value['first_leader'])
                ->field('realname')
                ->find()['realname'];
        }

        $total = $sum;
        $lottery_time = $lottery_time.' 00:00';


        $this->assign('total', $total);
        $this->assign('lottery_time', $lottery_time);
        $this->assign('list', $list);
        $this->assign('meta_title', '查看今日奖金池');
        return $this->fetch();
    }

    //查看当月奖金池
    public function mon_bonus()
    {
        $bonus = DB::name('bonus_pool')
            ->where('is_day',1)
            ->field('money,create_time')
            ->select();

        $sum = 0;
        foreach ($bonus as $key=>$value)
        {
            if (date('Y-m') == date('Y-m',$bonus[$key]['create_time'])){

                $sum = $sum + $value['money'];
            }
        }


        $list=DB::name('member')
            ->where('first_leader','>','0')
            ->group('first_leader')
            ->field('first_leader,realname ,mobile,count(first_leader) as num')
            ->order('num desc')
            ->limit(10)
            ->select();
        foreach ($list as $key=>$value){
            $list[$key]['No'] = $key+1;

            $list[$key]['mobile'] = DB::name('member')
                ->where('id',$value['first_leader'])
                ->field('mobile')
                ->find()['mobile'];
            $list[$key]['realname'] = DB::name('member')
                ->where('id',$value['first_leader'])
                ->field('realname')
                ->find()['realname'];
        }
//        foreach ($list as $key=>$value){
//
//            if (date('Y-m',$list[$key]['createtime']) == date('Y-m')){
//                $data[$key] = $value;
//            }
//        }

        $total = $sum;

        $data['total'] =  $total;
//        print_r($list);die;
        $this->assign('list', $list);
        $this->assign('total', $total);
        $this->assign('meta_title', '查看当月奖金池');
        return $this->fetch();
    }



    /*
   * 删除
   */
    public function del(){
        $id   = input('id', 0);
        if(!$id){
            jason([],'参数错误',0);
        }
        $info = Db::table('bonus_pool_setting')->find($id);
        if(!$info){
            jason([],'参数错误',0);
        }

        if( Db::table('bonus_pool_setting')->where('id',$id)->delete() ){
            jason([],'删除成功！');
        }else{
            jason([],'删除失败！',0);
        }

    }
}
