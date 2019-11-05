<?php
/**
 * 团队API
 */
namespace app\api\controller;
use app\common\controller\ApiBase;
use app\common\model\Sales;
use think\Db;
use think\Config;

class Team extends ApiBase
{
    /**
     * 我的团队总览
     */
    public function general(){
        //echo $this->create_token(27760);die;
        $user_id = $this->get_user_id();
        $field = "id,realname,first_leader";
        //用户数据
        $res = Db::name("member")->where("id",$user_id)->field($field)->find();
        //上级名称
        if(!empty($res['first_leader'])){
            $res['first_leader_name'] = Db::name("member")->where("id",$res['first_leader'])
                                                                ->value("realname");
        }else{
            $res['first_leader_name'] = "";
        }
        //业绩
        $res['performance'] =DB::name("agent_performance")->where("user_id",$user_id)->value("agent_per");
        //奖励总金额
        $res['reward'] = DB::name("menber_balance_log")->where("user_id",$user_id)->where("balance_type",1)->sum("balance");
        $res['team_number'] = count(get_all_lower($user_id));
        $this->ajaxReturn(['status' => 200 , 'msg'=>'成功！','data'=>$res]);
    }

    /**
     *用户奖励明细
     */
    public function detail_record(){
        $user_id = $this->get_user_id();
        $page = input("page");
        $pageSize = input("pageSize");
        if(empty($page)){
            $page = 1;
        }
        if(empty($pageSize)){
            $pageSize = 20;
        }
        $pageArray = array();
        $pageArray['page'] = $page;
        $pageArray['list_rows'] = $pageSize;

        $res = DB::name("menber_balance_log")
              ->where("user_id",$user_id)
              ->where("source_type",13)
              ->field("id,source_type,balance,note,create_time")
              ->order("id desc")
              ->paginate($pageArray)
              ->toArray();
        foreach ($res['data'] as $key=>$value){
            $res['data'][$key]['create_time'] = date('Ymd',$value['create_time']);
        }
        $this->ajaxReturn(['status' => 200 , 'msg'=>'成功！','data'=>$res]);

    }

    /**
     * 团队列表
     */
    public function get_team(){
        $user_id = $this->get_user_id();
        $page = input("page");
        $pageSize = input("pageSize");
        if(empty($page)){
            $page = 1;
        }
        if(empty($pageSize)){
            $pageSize = 20;
        }
        $team_user = get_all_lower($user_id);
        $pageArray = array();
        $pageArray['page'] = $page;
        $pageArray['list_rows'] = $pageSize;
        $res = DB::name("member")->where("id","in",$team_user)->order("id desc")
                                                              ->field("id,realname")
                                                              ->paginate($pageArray)
                                                              ->toArray();
        $this->ajaxReturn(['status' => 200 , 'msg'=>'成功！','data'=>$res]);

    }

    /**
     * 团队列表查看订单
     */
    public function team_order(){
        $team_member_id = input("team_member_id");
        $page = input("page");
        $pageSize = input("pageSize");
        if(empty($team_member_id)){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'error','data'=>"请选择团队成员"]);
        }
        if(empty($page)){
            $page = 1;
        }
        if(empty($pageSize)){
            $pageSize = 20;
        }
        $pageArray = array();
        $pageArray['page'] = $page;
        $pageArray['list_rows'] = $pageSize;
        $res = DB::name("order")->where("user_id",$team_member_id)
            ->field("order_id,order_sn,consignee,add_time")
            ->where("pay_status",1)
            ->paginate($pageArray)
            ->toArray();
        foreach ($res['data'] as $key=>$value){
            $res['data'][$key]['add_time'] = date('Y-m-d H:i');
        }
        $this->ajaxReturn(['status' => 200 , 'msg'=>'成功！','data'=>$res]);

    }


    /**
     * 供应商申请
     */

    public function supplier()
    {
        $user_id = $this->get_user_id();
        $name = input("name");
        $moblie = input("moblie");
        if(empty($name)){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'error','data'=>"请输入姓名"]);
        }
        if(empty($moblie)){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'error','data'=>"请输入手机号"]);
        }
        $data['name']=$name;
        $data['moblie']=$moblie;
        $data['createtime']=time();
        $data['user_id']=$user_id;
        $data['status']=1;

        $supplier = Db::name('supplier')->field('name')->where('user_id',$user_id)->find();
        if($supplier){
            $res=Db::name("supplier")->where('user_id',$user_id)->update($data);
        }else{
            $res=Db::name("supplier")->insert($data);
        }
        if($res){
            return $this->successResult("提交成功等待审核");
        }else{
            return $this->failResult("提交失败");
        }
    }
}