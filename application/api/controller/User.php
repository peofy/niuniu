<?php
/**
 * Created by PhpStorm.
 * User: zgp
 * Date: 2019/7/2 0002
 * Time: 17:51
 */

namespace app\api\controller;
use app\common\logic\UsersLogic;
use app\common\controller\ApiBase;
use app\common\model\Member;
use app\common\util\jwt\JWT;
use think\Config;
use think\Db;
use think\Exception;
use think\Request;
use app\common\model\Member as MemberModel;

class User extends ApiBase
{


    //用户协议
    public function consult()
    {
        $consult = Db::table('site')->field('consult')->find();
        $this->ajaxReturn(['status'=>200,'msg'=>'获取成功','data'=>$consult]);
    }


    /**
     * @api {POST} /user/login 用户登录
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    phone              手机号码*（必填）
     * @apiParam {string}    user_password      用户密码（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "phone":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "user_password":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data": {
     * "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJEQyIsImlhdCI6MTU2MjEyNDc0NCwiZXhwIjoxNTYyMTYwNzQ0LCJ1c2VyX2lkIjoiODAifQ.y_TRtHQ347Hl3URRJ4ECVgPbyGbniwyGyHjSjJY7fXY",  token值，下次调用接口，需传回给后端
     * "mobile": "18520783339",     手机号码
     * "id": "80"       用户ID
     * }
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "手机号码格式有误！",
     * "data": false
     * }
     */
    public function login()
    {
        $result = [];
        try {
            // if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
            $phone = trim($this->param['phone']);
            $password = trim($this->param['user_password']);

            $result = $this->validate($this->param, 'User.login');
            if (true !== $result) {
                return $this->failResult($result, 301);
            }

            if (!preg_match("/^1[23456789]\d{9}$/", $phone)) {
                return $this->failResult('手机号码格式有误', 301);
            }

            $data = Db::table("member")->where('mobile', $phone)
                ->field('id,password,mobile,salt')
                ->find();

            if (!$data) {
                return $this->failResult('手机不存在或错误', 301);
            }

            $password = md5($data['salt'] . $password);

            if ($password != $data['password']) {
                return $this->failResult('登录密码错误', 301);
            }

            unset($data['password'], $data['salt']);
            //重写
            $data['token'] = $this->create_token($data['id']);
            $result = $this->successResult($data);

        } catch (Exception $e) {
            $result = $this->failResult($e->getMessage(), 301);
        }
        return $result;
    }

    /**
     * +---------------------------------
     * 地址组件原数据
     * +---------------------------------
    */
    public function get_address(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        //第一种方法
        //$province_list  =  Db::name('region')->field('*')->where(['area_type' => 1])->column('area_id,area_name');
        // $city_list      =  Db::name('region')->field('*')->where(['area_type' => 2])->column('area_id,area_name');
        // $county_list    =  Db::name('region')->field('*')->where(['area_type' => 3])->column('area_id,area_name');
        // $data = [
        //     'province_list' => $province_list,
        //     'city_list'     => $city_list,
        //     'county_list'   => $county_list,
        // ];
        //第二种方法
        $list  = Db::name('region')->field('*')->select();
        foreach($list as $v){
           if($v['area_type'] == 1){
              $address_list['province_list'][$v['code'] * 10000]=  $v['area_name'];
           }
           if($v['area_type'] == 2){
              $address_list['city_list'][$v['code'] *100]=  $v['area_name'];
           }
           if($v['area_type'] == 3){
              $address_list['county_list'][$v['code']]=  $v['area_name'];
           }
        }
        $this->ajaxReturn(['status'=>200,'msg'=>'获取地址成功','data'=>$address_list]);
    }

    /**
     * @api {POST} /user/sendVerifyCode 发送验证码
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    phone              手机号码*（必填）
     * @apiParam {string}    temp      发送模板类型：注册 sms_reg；忘记密码 sms_forget（必填）
     * @apiParam {string}    auth      校验规则（md5(phone+temp)）（必填）
     * @apiParam {string}    type      1登录密码 2支付密码（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "phone":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "user_password":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data": "发送成功！"
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "手机号码格式有误！",
     * "data": false
     * }
     */
    public function sendVerifyCode()
    {
        $result = [];
        try {
            if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
            $phone = input('post.phone/s', '');
//            $temp = input('post.temp/s', '');
//            $auth = input('post.auth/s', '');
//            $type = input('type/d', 1);
            $result = $this->sendPhoneCode($phone);
            if ($result['status'] == 1) {
                return $this->successResult($result['msg']);
            }

            return $this->failResult($result['msg'], 301);

        } catch (Exception $e) {
            $result = $this->failResult($e->getMessage(), 301);
        }
        return $result;
    }

    /**
     * @api {POST} /user/register 用户注册
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    phone              手机号码*（必填）
     * @apiParam {string}    verify_code        验证码（必填）
     * @apiParam {string}    user_password      用户密码（必填）
     * @apiParam {string}    confirm_password   用户确认密码（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "phone":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "verify_code":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "user_password":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "confirm_password":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data": {
     * "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJEQyIsImlhdCI6MTU2MjEyNDc0NCwiZXhwIjoxNTYyMTYwNzQ0LCJ1c2VyX2lkIjoiODAifQ.y_TRtHQ347Hl3URRJ4ECVgPbyGbniwyGyHjSjJY7fXY",   token值，下次调用接口，需传回给后端
     * "mobile": "18520783339",     手机号码
     * "id": "80"       用户ID
     * }
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "验证码错误！",
     * "data": false
     * }
     */
    public function dologin()
    {
        $result = [];
        try {
            if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
            $phone = input('phone/s', '');
            $verify_code = input('verify_code/s', '');
            //邀请人id
            $uid=input('uid',0);
//            $type = input('verify_code/s', '');
//            $password    = input('user_password/s', '');
//            $confirm_password = input('confirm_password/s', '');
//            $uid = input('uid', 0);
//            if ($password != $confirm_password) {
//                return $this->failResult('密码不一致');
//            }

//            $result = $this->validate($this->param, 'User.register_phone');
//            if (true !== $result) {
//                return $this->failResult($result, 301);
//            }
            if(!isMobile($phone)){
                return $this->failResult('手机格式不正确',301);
            }

            // if(empty($verify_code)){
            //     return $this->failResult("验证码不能为空",301);
            // }

            //验证码判断
           $res = $this->phoneAuth($phone, $verify_code);
           if ($res === -1) {
               return $this->failResult('验证码已过期！', 301);
           } else if (!$res) {
               return $this->failResult('验证码错误！', 301);
           }
            
            $member = Db::table('member')->where('mobile', $phone)->value('id');

            if (!empty($member)) {
                $lastlogin = Db::table('member')->where('mobile', $phone)->value('lastlogin');
                if (empty($lastlogin)){
                    $data['token']   = $this->create_token($member);
                    $data['mobile']  = $phone;
                    $data['id']      = $member;
                    $data['is_first']    = 1;
                    return $this->successResult($data);
                }
                $data['token']   = $this->create_token($member);
                $data['mobile']  = $phone;
                $data['id']      = $member;
//                $data['lastlogin']     = time();
                Db::table('member')->where('id',$member)->update(['lastlogin'=>time()]);
                return $this->successResult($data);
            }
            if (empty($member)){
               

//                            $salt     = create_salt();
//            $password = md5($salt . $password);
                $insert['mobile']     = $phone;
//            $insert['salt']       = $salt;
//            $insert['password']   = $password;
                $insert['createtime'] = time();
                $insert['realname']   = '默认昵称';
                $insert['avatar']     = SITE_URL.'/public/static/images/headimg/20190711156280864771502.png';
                $insert['first_leader']=$uid;


                $id = Db::table('member')->insertGetId($insert);
                if (!$id) {
                    return $this->failResult('注册失败，请重试！', 301);
                } 
                //注册成功获取推广奖励
                // if($uid){
                //     $this->invite_bonus($uid,$id);
                // }

                $data['token']   = $this->create_token($id);
                $data['mobile']  = $phone;
                $data['id']      = $id;
                $data['is_first']= 1;

                $inviteCode=input('invitation_code',0);
                if($inviteCode){
                    $res=$this->invitation_code($data['id']);
                    $resarr=json_decode($res,true);
                    if($resarr['status']!==200){
                        return $res;
                    }
                }

                return $this->successResult($data);
            }
//
//            print_r($lastlogin);die;
//            if ($uid) {
//                $info = Db::table('member')->where('id', $uid)->find();
//                if (!$info) {
//                    return $this->failResult('邀请人账号不存在！', 301);
//                }
//               //绑定上下级关系
//               $insert['first_leader']   = $uid;
//               $insert['second_leader']  = $info['first_leader']; //  第一级推荐人
//               $insert['third_leader']   = $info['second_leader']; // 第二级推荐人
//            }else{
//               $insert['first_leader']   = 0;
//            }
           

//            if($uid){
//                // if($info['if_buy_fifty']){
//                //     Db::table('member')->where('id',$uid)->setInc('release');
//                // }
//            }

        } catch (Exception $e) {
            $result = $this->failResult($e->getMessage(), 301);
        }
        return $result;
    }



    public function invite_bonus($recommend_id,$user_id)
    {
        $recommend_direct=Db::name('member')->where(['first_leader'=>$recommend_id])->count();
        //对应的人数  对应的奖励
        $this->check_invite_bonus($recommend_direct,$recommend_id,$user_id);
        //公共方法判断人数 和业绩 来升级
        $this->check_upgrade($recommend_id,$user_id);

        //野牛以上符合直推人数奖励用户   这个逻辑作废的
        // $this->upgrade_bonus($recommend_direct,$recommend_id);
    }


    // public function upgrade_bonus($recommend_direct,$recommend_id){
    //     $recommendInfo=Db::name('member')->where(['id'=>$recommend_id])->find();
    //     $member_level=Db::name('member_level')->column("*",'level');

    //     if($recommendInfo){
    //           if($recommend_direct==$member_level[$recommendInfo['level']]['team_num']){
    //             $upgrade_bonus=$member_level[$recommendInfo['level']]['upgrade_bonus']*1;

    //             if($upgrade_bonus){
    //                 Db::name('member')->where(['id'=>$recommendInfo['id']])->setInc('remainder_money',$upgrade_bonus);
    //                 $desc="{$member_level[$recommendInfo['level']]['levelname']}直推{$member_level[$recommendInfo['level']]['team_num']}人奖励{$upgrade_bonus}";
    //                 Db::name('menber_balance_log')->insert(['user_id'=>$recommend_id,'balance'=>$upgrade_bonus,'source_type'=>13,'log_type'=>1,'note'=>$desc,'create_time'=>time(),'balance_type'=>1,'order_id'=>0]);
    //             }

    //         }
    //     }
          
    // }



    public function check_invite_bonus($num,$recommend_id,$user_id)
      {

        $invite_list=Db::name('invite_bonus')->column("*",'level');//做个邀请表
        $userinfo=Db::name('member')->where(['id'=>$user_id])->find(); //被邀请人信息
        foreach($invite_list as $ik=>$iv){
            $next=$ik+1;
            $users_lower=get_all_lower($recommend_id);
            if(isset($invite_list[$next])){
                 if(($invite_list[$ik]['pop_num']<=$num) && ($num<$invite_list[$next]['pop_num'])){
                    if($invite_list[$ik]['level']==5||$invite_list[$ik]['level']==6){
                        if(count($users_lower)>=$invite_list[$ik]['team_num']){
                            $desc = "团队{$invite_list[$ik]['team_num']}人,推荐id-{$user_id}-{$userinfo['mobile']}奖励{$invite_list[$ik]['per_invite_bonus']}元";
                            $bonus=$invite_list[$ik]['per_invite_bonus'];
                            Db::name('menber_balance_log')->insert(['user_id'=>$recommend_id,'balance'=>$bonus,'source_type'=>13,'log_type'=>1,'note'=>$desc,'create_time'=>time(),'balance_type'=>1,'order_id'=>0]);
                            Db::name('member')->where(['id'=>$recommend_id])->setInc('remainder_money',$bonus);
                        }
                    }else{
                        $bonus = $iv['per_invite_bonus'];
                        $desc="推荐新人id-{$user_id}-{$userinfo['mobile']}奖励{$iv['per_invite_bonus']}元";
                        Db::name('menber_balance_log')->insert(['user_id'=>$recommend_id,'balance'=>$bonus,'source_type'=>13,'log_type'=>1,'note'=>$desc,'create_time'=>time(),'balance_type'=>1,'order_id'=>0]);
                        Db::name('member')->where(['id'=>$recommend_id])->setInc('remainder_money',$bonus);
                    }
                }
            }else{
                if($invite_list[$ik]['pop_num']<=$num){
                    if($invite_list[$ik]['level']==5||$invite_list[$ik]['level']==6){
                        if(count($users_lower)>=$invite_list[$ik]['team_num']){
                            $desc = "团队{$invite_list[$ik]['team_num']}人,推荐id-{$user_id}-{$userinfo['mobile']}奖励{$invite_list[$ik]['per_invite_bonus']}元";
                            $bonus=$invite_list[$ik]['per_invite_bonus'];
                            Db::name('menber_balance_log')->insert(['user_id'=>$recommend_id,'balance'=>$bonus,'source_type'=>13,'log_type'=>1,'note'=>$desc,'create_time'=>time(),'balance_type'=>1,'order_id'=>0]);
                            Db::name('member')->where(['id'=>$recommend_id])->setInc('remainder_money',$bonus);
                        }
                    }
                }
            }
        }
    }


    public function check_upgrade($recommend_id,$user_id)
    {
        //业绩
        //推荐人数
        $all_ups=first_leader_ids($user_id);
        foreach($all_ups as $ak=>$av){
            $this->upgrade_one($av);
        }
        //如果都符合 
    }

    public function upgrade_one($user_id){
        $member_model=Db::name('member');
        $one=$member_model->where(['id'=>$user_id])->find();
        $next_level=$one['level']+1;
        $member_level=Db::name('member_level')->column('*','level');
        // echo $next_level;die;
        if($next_level<7){
            $standard_team_num=$member_level[$next_level]['team_num'];  //下级的团队人数条件
            $standar_agent_amount=$member_level[$next_level]['agent_amount'];   //下级的业绩总数
            $users=get_all_lower($user_id); //当前团队人数
            $num=count($users);
            $agent_performance=Db::name('agent_performance')->where(['user_id'=>$user_id])->find();
            if($num>=$standard_team_num&&$agent_performance['agent_per']>=$standar_agent_amount){
                //升级黄牛要自购一件商品
                if($next_level==4){
                    $order_count=Db::name('order')->where(['user_id'=>$user_id,'pay_status'=>1])->count();
                    if($order_count>=2){
                        $level_update=$member_model->where(['id'=>$user_id])->update(['level'=>$next_level]);
                        if($level_update!==false){
                            $bonus=$member_level[$next_level]['agent_bonus']*1;
                            Db::name('member')->where(['id'=>$user_id])->setInc('remainder_money',$bonus);
                            $levelname=$member_level[$next_level]['levelname'];
                            $desc="晋升{$levelname}奖励{$bonus}";
                            if($bonus!=0){
                                 Db::name('menber_balance_log')->insert(['user_id'=>$user_id,'balance'=>$bonus,'source_type'=>12,'log_type'=>1,'note'=>$desc,'create_time'=>time(),'balance_type'=>1]);
                            }
                        }
                    }
                }else{
                    $level_update=$member_model->where(['id'=>$user_id])->update(['level'=>$next_level]);
                    if($level_update!==false){
                        $bonus=$member_level[$next_level]['agent_bonus']*1;
                        Db::name('member')->where(['id'=>$user_id])->setInc('remainder_money',$bonus);
                        $levelname=$member_level[$next_level]['levelname'];
                        $desc="晋升{$levelname}奖励{$bonus}";
                        if($bonus!=0){
                       Db::name('menber_balance_log')->insert(['user_id'=>$user_id,'balance'=>$bonus,'source_type'=>12,'log_type'=>1,'note'=>$desc,'create_time'=>time(),'balance_type'=>1]);
                        }
                    }
                }
            }

        }
      
    }





    //首次登陆邀请码
    public function invitation_code($user=0)
    {
        if($user>0){
            $user_id=$user;
        }else{
             $user_id = $this->get_user_id();
        }
        // $user_id=27927;
        $invitation_code = input('invitation_code',0);
        if ($invitation_code){
                $info = Db::table('member')->where('id', $invitation_code)->find();
                if (empty($info)){
                    return $this->failResult('邀请人账号不存在！', 301);
                }

                $or_count=Db::name('order')->where(['user_id'=>$invitation_code,'pay_status'=>1])->count();
                if($or_count<1){
                    return $this->failResult('该推荐人没有购买订单,暂不可以推荐',301);
                }

                $all_lowers=get_all_lower($user_id);
                if(in_array($invitation_code,$all_lowers)){
                    return $this->failResult('下级不能作为推荐人',301);
                }

                if($user_id==$invitation_code){
                    return $this->failResult('自己不能作为推荐人',301);
                }

                //绑定上下级关系
                $update['first_leader']   = $invitation_code;
                $update['second_leader']  = $info['first_leader']; //  第一级推荐人
                $update['third_leader']   = $info['second_leader']; // 第二级推荐人
                $update['lastlogin']   = time();
                $update['level']=1;
            }else{
                $update['first_leader']   = 0;
                $update['lastlogin']   = time();
                $update['level']=1;
            }
            $res = Db::table('member')->where('id',$user_id)->update($update);

              //注册成功获取推广奖励
            if($user_id){
                $this->invite_bonus($invitation_code,$user_id);
                // 推荐一个新人就有9元进入奖励池
                $this->invite_pool($invitation_code,$user_id);
            }
            if ($res){
                return $this->failResult('success',200);
            }
    }

    public function invite_pool($invitation_code,$user_id)
    {
        if($invitation_code){
            $money = Db::table('config')->where('name','day_bonus')->field('value')->find();
            $pool['goods_id'] =  0;
            $pool['uid']      = $user_id;
            $pool['money']    = $money['value'];
            $pool['is_day']   = 0;
            $pool['create_time'] = time();
            $res = Db::table('bonus_pool')->insert($pool);
            $nickname = Db::table('member')->where('id',$user_id)->field('realname')->find();
            $log['uid'] = $user_id;
            $log['title'] = $nickname['realname'].'推荐新会员增加日奖金池'.$money['value'].'元';
            $log['money'] = $money['value'];
            $log['create_time'] = time();
            $log['is_day'] = 0;
            Db::table('cashing_prize_log')->insert($log);
        }
    }


    /**
     * @api {POST} /user/resetPassword 修改密码
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    phone              手机号码*（必填）
     * @apiParam {string}    type               1 登录密码；2 支付密码*（必填）
     * @apiParam {string}    verify_code        验证码（必填）
     * @apiParam {string}    user_password      用户密码（必填）
     * @apiParam {string}    confirm_password   用户确认密码（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "phone":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "type":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "verify_code":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "user_password":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "confirm_password":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data": "修改成功"
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "验证码错误！",
     * "data": false
     * }
     */
    public function resetPassword()
    {
        $result = [];
        try {
            if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
            $phone = input('phone/s', '');
            $type = input('type', 1);
            $verify_code      = input('verify_code/s', '');
            $password         = input('user_password/s', '');
            $confirm_password = input('confirm_password/s', '');

            if ($password != $confirm_password) {
                return $this->failResult('密码不一致错误', 301);
            }

            if (!preg_match("/^1[23456789]\d{9}$/", $phone)) {
                return $this->failResult('手机号码格式有误', 301);
            }

            $result = $this->validate($this->param, 'User.find_login_password');
            if (true !== $result) {
                return $this->failResult($result, 301);
            }

            $member = Db::name('member')->where(['mobile' => $phone])->field('id,password,pwd,mobile,salt')->find();
            if (empty($member)) {
                return $this->failResult('手机号码不存在', 301);
            }

            //验证码判断
            $res = $this->phoneAuth($phone, $verify_code);
            if ($res === -1) {
                return $this->failResult('验证码已过期！', 301);
            } else if (!$res) {
                return $this->failResult('验证码错误！', 301);
            }

            if ($type == 1) {
                $stri = 'password';
            } else {
                $stri = 'pwd';
                if(strlen($password) != 6){
                    return $this->failResult('支付密码长度错误！', 301);
                }
            }
            $password = md5($member['salt'] . $password);
            if ($password == $member[$stri]) {
                return $this->failResult('新密码和旧密码不能相同', 301);
            } else {
                $data = array($stri => $password);
                $update = Db::name('member')->where('id', $member['id'])->data($data)->update();
                if ($update) {
                    return $this->successResult('修改成功');
                } else {
                    return $this->failResult('修改失败');
                }
            }

        } catch (Exception $e) {
            $result = $this->failResult($e->getMessage(), 301);
        }
        return $result;
    }

       /**
     * @api {POST} /user/edit_name 修改用户名
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParam {string}    realname              姓名*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "realname":"xxxxxxxxxxxxxxxxxxxxxx",
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data":"成功"
     * 
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "操作失败",
     * "data": false
     * }
     */
    public function edit_name()
    {
        $user_id = $this->get_user_id();
        $data['realname']=input("realname");
        $result = $this->validate($this->param, 'User.edit_name');
        if (true !== $result) {
            return $this->failResult($result, 301);
        }

        $memberRes=Db::name("member")->where("id",$user_id)->update($data);
       if($memberRes!==false){
           return $this->successResult("用户名修改成功");
       }else{
           return $this->successResult("操作失败");
       }
    }




       /**
     * @api {POST} /user/team 我的团队
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data": {
     * "team_count": "12",  团队人数
     * "distribut_money": 12.20 佣金总收益
     * "estimate_money": "20.00",  预计收益
     * }
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "验证码错误！",
     * "data": false
     * }
     */
    public function team()
    {
        if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        //佣金总收益
        // $distribut_money    = Db::name('member')->where('id',$user_id)->value('distribut_money');
        $distribut_money    = Db::name('agent_performance')->where('user_id',$user_id)->value('agent_per');

        //奖励总金额
        $reward = DB::name("menber_balance_log")->where("user_id",$user_id)->where("balance_type",1)->sum("balance");
        //团队人数
        $team_count         = Db::query("SELECT count(*) as count FROM parents_cache where find_in_set('$user_id',`parents`)");
        //今日推荐
        //预计收益
       $estimate_money     = Db::name('distrbut_commission_log')->where(['to_user_id' => $user_id,'distrbut_state' => 0])->field('sum(money) as money')->find();
        //上级
        $first_leaderid   = Db::name('member')->where('id',$user_id)->value('first_leader');
        $first_leadername   = Db::name('member')->where('id',$first_leaderid)->value('realname');
        $data['id'] = $user_id;
        $data['realname'] = Db::name('member')->where('id',$user_id)->value('realname');
        $data['estimate_money']  = empty($estimate_money['money'])? 0.00 : $estimate_money['money'];//预计收入
        $data['distribut_money'] = $distribut_money;
        $data['reward'] = $reward;
        $data['first_leader'] = $first_leaderid;
        $data['first_leadername'] = $first_leadername;
        $data['team_count']      = $team_count[0]['count'] ? $team_count[0]['count'] : 0;
        return $this->successResult($data);
    }
      /**
     * @api {POST} /user/team_list 团队列表
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data":[
     * {
     *       "id":1, 用户ID
     *       "realname":"凉", 用户名称
     *        "mobile":"13413695347" 手机号
     * },
     * {
     *       "id":2,
     *       "realname":"啦啦啦",
     *       "mobile":"13413695348"
     * },
     * 
     * ]
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "验证码错误！",
     * "data": false
     * }
     */
    public function team_list()
    {
        if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
        $user_id = $this->get_user_id();
        // $user_id = 27958;
        $page = input("page");
        $pageSize = input("pageSize");
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }

        $type  = input('type/d',1); //1:一级直推 2:其他成员
        if(empty($page)){
            $page = 1;
        }
        if(empty($pageSize)){
            $pageSize = 20;
        }
        $pageArray = array();
        $pageArray['page'] = $page;
        $pageArray['list_rows'] = $pageSize;
        // if($type == 1){
        //     $all_lower = Db::name("member")->where(['first_leader' => $user_id])->column('id');
        //     $all_lower = implode(',',$all_lower);
       
        // }else{

        //     $first_arr = Db::name("member")->where(['first_leader' => $user_id])->column('id');
     
        //     $all_lower = get_all_lower($user_id);

        //     $all_lower = array_diff($all_lower ,$first_arr);

        //     $all_lower = implode(',',$all_lower);
        
        // }

        $all_lower= Db::query("SELECT `user_id` FROM parents_cache where find_in_set('$user_id',`parents`)");
      
        $all_lower=array_column($all_lower,'user_id');

        // $list = array();
        // print_r($all_lower);die;

        if ($all_lower) {
//            $list = Db::query("select id,realname,mobile from `member` where `first_leader` > 0 and `id` in ($all_lower)")->paginate($pageArray)
//                ->toArray();
            // $list=Db::name("member")
            //     ->where('first_leader',"in",$all_lower)
            //     ->field('id,realname,mobile')
            //     ->order("id desc")
            //     ->paginate($pageArray)
            //     ->toArray();
            $list=Db::name('member')
            ->where(['id'=>['in',$all_lower]])
            ->field('id,realname,mobile')
            ->order("id desc")
            ->paginate($pageArray)
            ->toArray();
        }
        if (!$list){
                $list =["total"=> 4,
                        "per_page"=> 20,
                        "current_page"=> 1,
                        "last_page"=> 1,
                        "data"=>[]
                        ];
                    }
        $data['list'] = $list;
      
        return $this->successResult($list);
    }





      /**
     * @api {POST} /user/distribut_list 佣金明细
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParam {string}    page              页数*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "page":"1"  页数 默认1,
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data":[
     * {
     *       "order_sn":'RC20190116110509664542', 订单号
     *       "money":"8.00", 金额
     *       "desc":"经理1级别利润(家用1台)" 描述
     * },
     * {
     *       "order_sn":'RC20190116110509282892',
     *       "money":"8.00",
     *       "desc":"总监2级别利润(家用1台)"
     * },
     * 
     * ]
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "验证码错误！",
     * "data": false
     * }
     */
    public function distribut_list()
    {
        if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
        
        $user_id = $this->get_user_id();
        $page    = input('page',1);
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $where['distrbut_state'] = 1;
        $where['to_user_id']     = $user_id;
        $list = Db::name('distrbut_commission_log')
        ->where($where)
        ->field('order_sn,money,desc,create_time')
        ->order('create_time desc')
        ->paginate(20,false,['page'=>$page]);
     
        $data['list'] = $list;
        
        return $this->successResult($list);
    }


     /**
     * @api {POST} /user/estimate_list 预计收益明细
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParam {string}    page              页数*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "page":"1"  页数 默认1,
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data":[
     * {
     *       "user_id"  : 123132, 用户ID
     *       "realname":'张三', 用户名称
     *       "order_sn":'RC20190116110509664542', 订单号
     *       "money":"8.00", 金额
     * },
     * {
     *      "user_id"  : 123132, 用户ID
     *       "realname":'张三', 
     *       "order_sn":'RC20190116110509282892',
     *       "money":"8.00",
     * },
     * 
     * ]
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "验证码错误！",
     * "data": false
     * }
     */
    public function estimate_list()
    {
        if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
        
        $user_id = $this->get_user_id();
        $page    = input('page',1);
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        
        $where['to_user_id']     = $user_id;
        $where['distrbut_state'] = 0;
        $list = Db::name('distrbut_commission_log')
                ->where($where)
                ->field('order_sn,money,desc')
                ->paginate(2000,false,['page'=>$page]);
     
        $data['list'] = $list;
        
        return $this->successResult($data);
    }


         /**
     * @api {POST} /user/user_info 我的信息
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data": {
     * "id": "12",  用户id
     * "mobile": 12.20 手机号
     * "realname": "20.00", 用户名称
     * "remainder_money": "20.00",  余额
     * "distribut_money": "20.00",  佣金累计收益
     * "estimate_money": "20.00",   预计收益
     * "createtime": "",  注册时间
     * "avatar": "",  头像
     * "collection": "20",  收藏
     * "not_pay" : 0 ,待付款
     * "not_delivery" : 0 ,待发货
     * "not_receiving" : 0 ,待收货
     * "not_evaluate" : 0 ,待评价
     * "refund" : 0 ,退款
     * 
     * }
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "验证码错误！",
     * "data": false
     * }
     */
    public function user_info()
    {
        if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
        $user_id = $this->get_user_id();
        // $user_id = 27914;
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        //当月累计奖金
        $where['distrbut_state'] = 1;
        $where['to_user_id']     = $user_id;
        $list = DB::name('bonus_pool')
            ->field('money,create_time,is_day')
            ->select();
        $month = 0;
        $day = 0;
        foreach ($list as $key=>$value)
        {
            if (date('Y-m') == date('Y-m',$list[$key]['create_time']) && $list[$key]['is_day'] == 1 ){

                $month = $month + $value['money'];
            }

            if (date('Y-m-d',$value['create_time']) == date('Y-m-d') && $list[$key]['is_day'] == 0){
                $day += $value['money'];
            }
        }

        //总人数
        $team_count         = Db::query("SELECT count(*) as count FROM parents_cache where find_in_set('$user_id',`parents`)");

        //今日推荐
        $rec = Db::name("member")->field('createtime')->where(['first_leader' => $user_id])->select();
        $sum = 0;
        foreach ($rec as $key => $value){
            if (date('Y-m-d',$value['createtime']) == date('Y-m-d') ){
                $sum +=1;
            }
        }
        $info     = Db::name('member')->where(['id' => $user_id])->field('realname,mobile,id,remainder_money,distribut_money,createtime,avatar,level')->find();

        $level_name = Db::name('member_level')->where(['level' => $info['level']])->field('levelname')->find();
        //退款
        $refund   = Db::name('order_refund')->where(['user_id' => $user_id,'refund_status' => 2])->field('*')->count();
        //待付款
        $where = array('order_status' => 1 ,'pay_status'=>0 ,'shipping_status' =>0,'user_id'=>$user_id); //待付款
        $not_pay  = Db::name('order')->where($where)->field('*')->count();
        //待发货
        $where = array('order_status' => 1 ,'pay_status'=>1 ,'shipping_status' =>0,'user_id' => $user_id); //待发货
        $not_delivery   = Db::name('order')->where($where)->field('*')->count();
        //待收货
        $where = array('order_status' => 1 ,'pay_status'=>1 ,'shipping_status' =>1,'user_id' => $user_id); //待收货
        $not_receiving  = Db::name('order')->where($where)->field('*')->count();
        //待评价
        $where = array('order_status' => 4 ,'pay_status'=>1 ,'shipping_status' =>3,'user_id' => $user_id,'comment' =>0); //待评价
        $not_evaluate   = Db::name('order')->where($where)->field('*')->count();
        //收藏
        $collection     = Db::name('collection')->where(['user_id' => $user_id])->field('*')->count();
        //预计收益
        $estimate_money = Db::name('distrbut_commission_log')->where(['to_user_id' => $user_id,'distrbut_state' => 0])->field('sum(money) as money')->find();
        //佣金总收益
        $distribut_money    = Db::name('agent_performance')->where('user_id',$user_id)->value('agent_per');

        $info['estimate_money'] = empty($estimate_money['money'])?0:$estimate_money['money']; //预计收益
        $info['refund']         = $refund;
        $info['not_pay']        = $not_pay;
        $info['not_delivery']   = $not_delivery;
        $info['not_receiving']  = $not_receiving;
        $info['distribut_money']  = $distribut_money;
        $info['not_evaluate']   = $not_evaluate;
        $info['collection']     = $collection;
        $info['levelname']      = $level_name['levelname'];
        $info['today_rec']      = $sum;
        $info['team_count']     =$team_count[0]['count'];
        $info['month']          =$month;
        $info['day']            =$day;

        return $this->successResult($info);
    }

    /**
     * 获取默认头像
     * @param $user_id
     */
    public function defaultTou($user_id){
        $info     = Db::name('member')->where(['id' => $user_id])->field('realname,mobile,id,avatar')->find();
        //默认头像的位置
        return $info['avatar'];
    }

    /**
     * 个人资料页面
     */
    public function personal(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $info  = Db::name('member')->where(['id' => $user_id])->field('realname,mobile,id,avatar,level')->find();
        return $this->successResult($info);
    }

   /**
     * 上传头像
     * @throws Exception
     */
    public function updateTou(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $img = input('image');
//        print_r(explode('%',$img));die;
        write_log($img);
        if(!empty($img)){
                if (explode('%',$img)){
                    $img = urldecode($img);
                }

                $img = explode(',',$img)[1];
                $saveName = request()->time().rand(0,99999) . '.png';

                $img=base64_decode($img);
                //生成文件夹
                $names = "headpic" ;
                $name = "headpic/" .date('Ymd',time()) ;
                if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){
                    mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
                }
                //保存图片到本地
                file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$img);

        }

        $imgPath = SITE_URL .'/public/upload/images/'. $name.$saveName;
        $data['avatar']=$imgPath;
        $member=Db::name('member')->where('id',$user_id)->find();
        if($member){
            $res=Db::name("member")->where('id',$user_id)->update($data);
        }else{
            return $this->failResult("操作失败1");
        }
        if($res){
            $this->ajaxReturn(['status' => 200 , 'msg'=>'操作成功！','data'=>$data['avatar']]);
        }else{
            return $this->failResult("操作失败");
        }
    }

    /**
     * 退出成功
     */
    public function logout(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $data['token'] = '';
        return $this->successResult($data);
    }

    /**
     * 我的邀请链接
     */
    public function shareUrl(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $info  = Db::name('member')->where(['id' => $user_id])->field('realname,mobile,id,avatar')->find();
        $data['realname'] = $info['realname'];
        $data['avatar'] = $info['avatar'];
        $data['mobile'] = $info['mobile'];
        $data['id'] = $info['id'];
        $data['url'] = '?uid='.$info['id'];
        return $this->successResult($data);
    }

    /**
     * 账户余额
     */
    public function user_remainder(){

        $user_id = $this->get_user_id();

        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }

        $info = Db::name('member')->where(['id' => $user_id])->field('remainder_money,alipay,alipay_name')->find();
        $info['rate'] = 0.006;//tode:做成配置
             
        return $this->successResult($info);
    }

     /**
     * 提现列表
     */
    public function withdrawal_list(){
        $user_id = $this->get_user_id();
        $page = input("page");
        $pageSize = input("pageSize");
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
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
        $list  = Db::name('member_withdrawal')
            ->where(['user_id' => $user_id])
            ->field('createtime,money,taxfee,status')
            ->order('createtime desc')
            ->paginate($pageArray)
            ->toArray();

        foreach ($list['data'] as $key=>$value)
        {
            $list['data'][$key]['createtime'] = date('Y.m.d',$value['createtime']);
        }
        $data['list'] = $list;
        return $this->successResult($data);
    }

    //获取银行卡
    public function get_bank_number()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $bank = Db::name('member')->where(['id' => $user_id])->field('bank_card,bank_name')->find();

        $bank['bank_card'] = unserialize($bank['bank_card']);
        $bank['bank_name'] = unserialize($bank['bank_name']);
        for ($i=0; $i<count($bank['bank_card']); $i++){
            $data[$i]['bank_card'] = $bank['bank_card'][$i];
            $data[$i]['bank_name'] = $bank['bank_name'][$i];
        }
        if (empty( $bank['bank_card'])){
            $data = array();
        }
        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功！','data'=>$data]);
    }


    //获取银行卡图片
    public function bank_img()
    {
        $bank_img = include APP_PATH.'api/bank.php'; // 银行对应图片
        $i = 0;
        foreach ($bank_img as $key =>$value){

            $data[$i]['name'] = $key;
            $data[$i]['img'] = SITE_URL.'/public/uploads/images-out/'.$value;

            $i++;
        }
        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功！','data'=>$data]);
    }

    //绑定银行卡
    public function bound_bank()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $bankcard = input('bankcard');
        $bankname = input('bankname');
        $data['bankcard'] = $bankcard;
        $data['bankname'] = $bankname;

        $bank = Db::name('member')->where(['id' => $user_id])->field('bank_card,bank_name')->find();
        $bank['bank_card'] = unserialize($bank['bank_card']);
        $bank['bank_name'] = unserialize($bank['bank_name']);

        if (count($bank['bank_card']) > 18){
            $this->ajaxReturn(['status' => 300 , 'msg'=>'最多绑定18张银行卡！','data'=>'']);
        }

        if (!empty($bank['bank_card'])){
            if (in_array($bankcard,$bank['bank_card'])){
                $this->ajaxReturn(['status' => 300 , 'msg'=>'该卡已绑定！','data'=>$data]);
            }
        }
        if (empty($bank['bank_name']) || empty($bank['bank_card'])){
            $bank['bank_name'] = array();
            $bank['bank_card'] = array();
            array_push($bank['bank_name'],$bankname);
            array_push($bank['bank_card'],$bankcard);

            $bank['bank_name'] = serialize($bank['bank_name']);
            $bank['bank_card'] = serialize($bank['bank_card']);

            $res = Db::name('member')->where(['id' => $user_id])->update(['bank_name'=>$bank['bank_name'],'bank_card'=>$bank['bank_card']]);
            if ($res){
                $this->ajaxReturn(['status' => 200 , 'msg'=>'绑定成功！','data'=>$data]);
            }else{
                $this->ajaxReturn(['status' => 300 , 'msg'=>'添加失败！','data'=>'']);
            }
        }

        if (!empty($bank)){
            array_push($bank['bank_name'],$bankname);
            array_push($bank['bank_card'],$bankcard);
            $bank['bank_name'] = serialize($bank['bank_name']);
            $bank['bank_card'] = serialize($bank['bank_card']);

            $res = Db::name('member')->where(['id' => $user_id])->update(['bank_name'=>$bank['bank_name'],'bank_card'=>$bank['bank_card']]);
            if ($res){
                $this->ajaxReturn(['status' => 200 , 'msg'=>'绑定成功！','data'=>$data]);
            }else{
                $this->ajaxReturn(['status' => 300 , 'msg'=>'添加失败！','data'=>'']);
            }
        }



    }


    /***
     * 余额充值
     */
    public function balance_recharge()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $balance_remainder = Db::name('member')->where(['id' => $user_id])->field('remainder_money,pwd')->find();
        if (Request::instance()->isPost()){
            $num = input('num/f');
            $pwd = input('pwd/f');
            if(!empty($pwd) && ($balance_remainder['pwd'] != md5(C("AUTH_CODE").$pwd))){
                $this->ajaxReturn(['status'=>-1,'msg'=>'密码错误！','result'=>'']);
            }
            if($num <= 0){
                $this->error('输入的金额有误');
            }
            $dephp_11      =  $balance_remainder['remainder_money'] + $num;

            $dephp_12 = array('user_id' => $user_id, 'balance_type' => 1, 'old_balance' => $balance_remainder['remainder_money'], 'balance' => $dephp_11,'create_time' => time(), 'note' => '余额充值','log_type'=>1,'source_type'=>6);
            // 启动事务
            Db::startTrans();

            $res = Db::name('member')->where(['id' => $user_id])->update(['remainder_money'=>$dephp_11]);

            if(!$res){
                Db::rollback();
            }
//            print_r($dephp_12);die;
            $res1 = Db::name('menber_balance_log')->insert($dephp_12);
            if(!$res1){
                Db::rollback();
            }
        }
        // 提交事务
        Db::commit();
            $this->ajaxReturn(['status' => 200 , 'msg'=>'充值成功！','data'=>$res]);
        }
//        $profile['balance'] = $balance_info['balance'];
//        $this->assign('profile', $profile);
//        $this->assign('meta_title', '余额充值');
//        return $this->fetch();


    //充值明细
    public function recharge()
    {
        $user_id = $this->get_user_id();
        // $user_id =28018;
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
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

        $list  =Db::name('qr_recharge')->where(['user_id' => $user_id])->field('create_time,money,australian_dollar,handle_status')->order('create_time desc') ->paginate($pageArray)
            ->toArray();

        foreach ($list['data'] as $key=>&$v)
        {
            $list['data'][$key]['balance']= round(abs($v['money']),4);
            unset($v['money']);
            $v['australian_dollar']     = round(abs($v['australian_dollar']),4);
            $v['create_time'] = date("Ymd",$v['create_time']);
            $list['data'][$key]['status']= $v['handle_status'];
            unset($v['handle_status']);
        }
        $data['list'] = $list;
        return $this->successResult($list);





    }

      /**
     * 账单列表
     */
    public function remainder_list(){
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
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $log_type=input('log_type')?input('log_type'):0;
        $list           = Db::name('menber_balance_log')->where(['user_id' => $user_id,'balance_type' => ['neq',0],'log_type'=>$log_type])->field('note,balance,source_id,create_time,old_balance,log_type')->order('create_time desc')->paginate($pageArray)
            ->toArray();
        if(!empty( $list)){
            foreach($list['data'] as &$v){
                $v['balance']     = round(abs($v['old_balance'] -  $v['balance']),2);
                $v['create_time'] = date("Y-m-d H:i:s",$v['create_time']);
            }
        }
        $data['list']   = $list;
        return $this->successResult($list);
    }

      /**
     * 支付宝账号详情
     */
    public function zfb_info(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $data  = Db::name('member')->where(['id' => $user_id])->field('alipay_name,alipay')->find();
        return $this->successResult($data);
    }

    /***
     * 
     * 用户订单
     */
    public function user_order(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }

        $user_ids = input('user_ids/d',0);

        if(!$user_ids){
            return $this->failResult('参数错误', 301);
        }
       
        // //50元专区订单
        // $fifty_zone_order  = Db::name('fifty_zone_order')->where(['user_id' => $user_id,'pay_status' => 1,'order_status' => 4,'shipping_status' => 3 ])->field('order_sn,pay_time,order_amount,add_time')->order('add_time desc')->select();
         //30元专区订单
        // $fifty_zone_order  = Db::name('fifty_order')->where(['user_id' => $user_id,'pay_status' => 1,'order_status' => 4,'shipping_status' => 3 ])->field('order_sn,pay_time,order_amount,add_time')->order('add_time desc')->select();
        //商品订单
        //$list = Db::name('order')->where(['user_id' => $user_id,'pay_status' => 1,'order_status' => 4,'shipping_status' => 3 ])->field('order_sn,pay_time,order_amount,add_time')->order('add_time desc')->select();

        $list    = Db::name('menber_balance_log')->where(['user_id' => $user_ids,'balance_type' => ['in','1,2']])->field('note,balance,source_id as order_sn,create_time as pay_time,old_balance,log_type')->order('create_time desc')->select();
        if(!empty( $list)){
            foreach($list as &$v){
                $v['order_amount']   = round(abs($v['old_balance'] -  $v['balance']),2);
            }
        }

        return $this->successResult($list);
    }


    /**
     * 支付宝账号编辑
     */
    public function zfb_edit(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $alipay_name = input('alipay_name');
        $alipay      = input('alipay');

        if(empty($alipay_name) || empty($alipay) ){
            return $this->failResult('支付宝用户名或者账户不能为空', 301);
        }

        $data = [
            'alipay'      => $alipay,
            'alipay_name' => $alipay_name,
        ];
        $res  = Db::name('member')->where(['id' => $user_id])->update($data);

        if($res == false){
            return $this->failResult('编辑失败', 301);
        }

       $this->ajaxReturn(['status'=>200,'msg'=>'编辑成功','data'=>$data]);
    }



    /**
     * 用户提现申请
     */
    public function withdrawal(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }

        $member  = Db::name('member')->where(['id' => $user_id])->field('alipay_name,alipay,is_cash,remainder_money')->find();
 
        $money   = input('money/f');
        if(!preg_match("/^\d+(\.\d+)?$/",$money))$this->failResult('请输入正确的金额！', 301);

        if($money > $member['remainder_money']){
            $this->failResult('提现金额大于余额！', 301);
        }

        // if($member['is_cash'] != 1){

        //     $this->failResult('暂时不可以提现！', 301);

        // }

        if(empty($member['alipay_name']) || empty($member['alipay'])){

            $this->failResult('支付宝用户名或者账户不能为空！', 777);

        }
       
        $withdraw_type      = input('withdraw_type',4);
        $tax                = 0.006; //提现费率 todo:后台配置
        $taxfee             = $money * $tax;
        $data = [
            'user_id' => $user_id,
            'money'   => $money*$this->aus_tormb ,
            'rate'    => $tax,
            'taxfee'  => $taxfee,
            'createtime'     => time(),
            'type'           => $withdraw_type,
            'account_name'   =>  $member['alipay_name'],
            'account_number' =>  $member['alipay'],
            'status'         => 1,
        ];
        // 启动事务
        Db::startTrans();

        $res  = Db::name('member_withdrawal')->insert($data);

        if($res == false){
            Db::rollback();
            return $this->failResult('提现失败', 301);
        }
        //余额扣减
        $res1 = Member::where(['id' => $user_id])->setDec('remainder_money',$money);
        if($res1 == false){
            Db::rollback();
            return $this->failResult('提现失败', 301);
        }
        Db::commit();
        $this->ajaxReturn(['status'=>200,'msg'=>'提现申请成功,工作人员加急审核中！','data'=>[]]);
    }

     /***
     * 代理商等级条件
     */
    public function agent_res(){
        
        $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $member_level  = Db::name('member_level')->field('id,levelname')->select();
        return $this->successResult($member_level);
      
    }


    /***
     * 代理商（申请代理）
     */
    public function agent_handle(){
//        $user_id = 27726;
        $user_id=$this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $level_id    = input('level_id/d',1);   
        $image       = input('image','');
        $realname    = input('realname','');
        $mobile      = input('mobile','');
        
        $level = Db::name('member_level')->where(['id' => $level_id])->find();

        $level =  $level['level'];

        $all_lower = get_all_lower($user_id);

        $all_lower = implode(',',$all_lower);
     
        if($level == 1){
            if(count($all_lower) < 1000){
                return $this->failResult('团队未达到1000人！', 301);
            }
        }elseif($level == 2){

            if ($all_lower) {
                $count = Db::name('member')->where(['level' => 1,'first_leader'=> ['gt',0],'id' => ['in',$all_lower]])->count();
                // $info  = Db::query("select count(id) as count from `member` where `level` = 2 and `first_leader` > 0 and `id` in ($all_lower)");
                // $count = $info['count'];
            } else{
                $count = 0;
            }

            if($count < 5 ){
                return $this->failResult('您的团队里没有5个县级代理！', 301);
            }

        }else{

            if ($all_lower) {
                $count = Db::name('member')->where(['level' => 2,'first_leader'=> ['gt',0],'id' => ['in',$all_lower]])->count();
            } else{
                $count = 0;
            }

            if($count < 5 ){
                return $this->failResult('您的团队里没有5个市级代理！', 301);
            }

        }



        if (!preg_match("/^1[23456789]\d{9}$/", $mobile)) {
            return $this->failResult('手机号码格式有误!', 301);
        }

        if (empty($realname)) {
            return $this->failResult('用户名不能为空!', 301);
        }

        if (empty($image)){
            return $this->failResult('请上传打款凭证！', 301);
        }

        $saveName = request()->time().rand(0,99999) . '.png';
        $imga     = file_get_contents($image);
        //生成文件夹
        $names = "agent" ;
        $name  = "agent/" .date('Ymd',time()) ;
        if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){ 
            mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
        }
        file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$imga);
        $imgPath   =  Config('c_pub.apiimg') . $name.$saveName;

        $insert = [
            'level_id'    =>  $level_id,
            'image'       =>  $imgPath,
            'realname'    =>  $realname,
            'mobile'      =>  $mobile,
            'status'      =>  1,
            'create_time' =>  time(),
        ];
        $res   = Db::name('agent_handle')->insert($insert);
        if($res == false){
            $this->ajaxReturn(['status'=>301,'msg'=>'申请失败,请稍后重试！','data'=>[]]);
         
        }
            $this->ajaxReturn(['status'=>200,'msg'=>'申请成功,加急审核中！','data'=>[]]);
       
    }
    



        /**
     * @api {POST} /user/sharePoster 我的推广码
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data": {
     * "my_poster_src": "http:\/\/127.0.0.1:20019\/shareposter\/123-share.png",  图片路径
     * }
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "验证码错误！",
     * "data": false
     * }
     */
    public function sharePoster(){
        try {
            if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
            $user_id = $this->get_user_id();
            // $user_id=27986;
            if(!$user_id){
                return $this->failResult('用户不存在', 301);
            }
            $share_error = 0;
            $filename = $user_id.'-qrcode.png';
            $save_dir = ROOT_PATH.'public/shareposter/';
            $my_poster = $save_dir.$user_id.'-share.png';
            $my_poster_src = SITE_URL.'/public/shareposter/'.$user_id.'-share.png';
            if( !file_exists($my_poster) ){
                    $domain=request()->domain();
                    $qianurl = $domain.'/index/reg/register';
                    $imgUrl  = $qianurl.'?inviteCode='.$user_id;
                    vendor('phpqrcode.phpqrcode');
                    \QRcode::png($imgUrl, $save_dir.$filename, QR_ECLEVEL_M);
                    $image_path =  ROOT_PATH.'public/shareposter/load/qr_backgroup.png';
                    if(!file_exists($image_path)){
                        $share_error = 1;
                    }
                    # 分享海报
                    if(!file_exists($my_poster) && !$share_error){
                        # 海报配置
                        $conf = Db::name('config')->where(['name' => 'shareposter'])->find();
                        $config = json_decode($conf['value'],true);
                        $image_w = $config['w'] ? $config['w'] : 200;
                        $image_h = $config['h'] ? $config['h'] : 200;
                        $image_x = $config['x'] ? $config['x'] : 0;
                        $image_y = $config['y'] ? $config['y'] : 0;
                        # 根据设置的尺寸，生成缓存二维码
                        $qr_image = \think\Image::open($save_dir.$filename);
                        $qrcode_temp_path = $save_dir.$user_id.'-poster.png';
                        $qr_image->thumb($image_w,$image_h,\think\Image::THUMB_SOUTHEAST)->save($qrcode_temp_path);
                        
                        if($image_x > 0 || $image_y > 0){
                            $water = [$image_x, $image_y];
                        }else{
                            $water = 5;
                        }
                        # 图片合成
                        $image = \think\Image::open($image_path);
                        $image->water($qrcode_temp_path, $water)->save($my_poster);
//                        @unlink($qrcode_temp_path);
                        @unlink($save_dir.$filename);
                    }
            }
            $info  = Db::name('member')->where(['id' => $user_id])->field('realname,mobile,id,avatar')->find();
            $data['realname'] = $info['realname'];
            $data['avatar']   = $info['avatar'];
            $data['mobile']   = $info['mobile'];
            $data['invitation_code']       = $info['id'];
            $data['qrcode'] = SITE_URL.'/public/shareposter/'.$user_id.'-poster.png';
            return $this->successResult($data);
        } catch (Exception $e) {
                return $this->failResult($e->getMessage(), 301);
        }
         
    }



       /**
     * @api {POST} /user/ewm 动态二维码
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParam {string}    url                url*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     *      "url":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     * "status": 200,
     * "msg": "success",
     * "data": {
     * "url": "http:\/\/127.0.0.1:20019\/shareposter\/123-share.png",  图片路径
     * }
     * }
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "操作失败",
     * "data": false
     * }
     */
    public function ewm(){
      if (!Request::instance()->isPost()) return $this->getResult(301, 'error', '请求方式有误');
         $user_id = $this->get_user_id();
        if(!$user_id){
            return $this->failResult('用户不存在', 301);
        }
        $url     = Db::table('site')->value('web_url');
        $imgUrl  = $url . '/Register?uid=' . $user_id;
        $filename = $user_id.'-qrcodee.png';
        $save_dir = ROOT_PATH.'public/Ewm/';
        $my_poster = $save_dir.$user_id.'-qrcodee.png';
        $my_poster_srcurl = SITE_URL.'/Ewm/'.$user_id.'-qrcodee.png';
        if( !file_exists($my_poster) ){

            vendor('phpqrcode.phpqrcode');
            \QRcode::png($imgUrl, $save_dir.$filename, QR_ECLEVEL_M);
         
            # 根据设置的尺寸，生成缓存二维码
            $qr_image = \think\Image::open($save_dir.$filename);
            $image_w = 200;
            $image_h = 200;
            $my_poster_src = $save_dir.$user_id.'-qrcodee.png';
            $qr_image->thumb($image_w,$image_h,\think\Image::THUMB_SOUTHEAST)->save($my_poster_src);
        }
        $data['url'] = $my_poster_srcurl;
        return $this->successResult($data);
}


           /**
     * @api {POST} /user/bank_card 银行设置
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
       *{
         * "status":200,
         * "msg":"success",
         * "data":{"id":55,"title":"张11","name":"1321545646546","module":1,"group":0,"extra":"","remark":"广州工商银行","status":1,"value":"6202565465215495","sort":1,"update_time":1562727208,"create_time":1562727187}}
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "操作失败",
     * "data": false
     * }
     */
       public function bank_card()
       {
           $bankInfo=Db::name('config')->where(['id'=>56])->find();
           if($bankInfo){
                 return $this->successResult($bankInfo);
           }else{
                  return $this->failResult("操作失败");
           }
       }

              /**
     * @api {POST} /user/shop_list 我的发布
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParam {string}    token              token*（必填）
     * @apiParamExample {json} 请求数据:
     * {
     *      "token":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
       *{
         * {"status":200,
         * "msg":"成功！",
         * "data":[{"fz_id":45,"user_id":76,"goods_id":50,"stock":11,"frozen_stock":0,
         * "add_time":1562412462,"goods_name":"五十块钱","img":"http://newretail.com/upload/images/goods/20190703156215158672344.png"},
         * {"fz_id":44,"user_id":76,"goods_id":50,"stock":11,"frozen_stock":0,"add_time":1562408249,"goods_name":"五十块钱",
         * "img":"http://newretail.com/upload/images/goods/20190703156215158672344.png"}]}
     * //错误返回结果
     * {
     * "status": 301,
     * "msg": "操作失败",
     * "data": false
     * }
     */
    public function shop_list(){
        $user_id = $this->get_user_id();
        // $user_id=76;
        $goods_id = 50;
        $info = Db::table('config')->where('module',5)->column('value','name');
        $where['g.goods_id'] = $goods_id;
        // $where['fzs.stock'] = ['neq',0];
        $where['fzs.user_id'] = ['eq',$user_id];
        $list = Db::table('fifty_zone_shop')->alias('fzs')
                ->join('goods g','g.goods_id=fzs.goods_id','LEFT')
                ->join('fifty_zone_img fzi','fzi.id=fzs.img_id','LEFT')
                ->join('member m','m.id=fzs.user_id','LEFT')
                ->field('fzs.*,g.goods_name,fzi.picture img')
                ->where($where)
                ->order('fzs.add_time DESC')
                ->limit(11)
                ->select();
        foreach($list as $key=>&$value){
            $value['img'] = Config('c_pub.apiimg') . $value['img'];
            $value['ymdTime'] = date("Y-m-d",$value['add_time']);
            $value['hisTime'] = date("h:i:s",$value['add_time']);
            $res = Db::table('fifty_zone_order')->where('fz_id',$value['fz_id'])->where('user_confirm',0)->find();
            $ress = Db::table('fifty_zone_order')->where('fz_id',$value['fz_id'])->where('shop_confirm',1)->order('shop_time desc')->find();
            $time = 0;
            $time = $ress['shop_time'] + 3600;
            if(!$res && !$value['stock'] && $time < time()){
                unset($list[$key]);
            }
        }
        $this->ajaxReturn(['status' => 200 , 'msg'=>'成功！','data'=>$list]);
    }

    //我的发布详情
    public function my_shop_detail(){
        $where['fzo.shop_user_id'] = $this->get_user_id();
        $where['fzo.fz_id'] = input('fz_id');

        $list = Db::table('fifty_zone_order')->alias('fzo')
                ->join('member m ','m.id=fzo.user_id','LEFT')
                ->field('fzo.fz_order_id,fzo.user_id,fzo.user_confirm,fzo.shop_confirm,fzo.add_time,fzo.pay_time,m.mobile')
                ->where($where)->field('user_id')->select();

        $this->ajaxReturn(['status' => 200 , 'msg'=>'成功！','data'=>$list]);
    }

    /***
     * 充值列表
     */
    public function recharge_list(){

        $user_id        = $this->get_user_id();

        $recharge_list  = Db::name('qr_recharge')->where(['user_id' => $user_id])->select();
        foreach ($recharge_list as $key=>$value)
        {
            $recharge_list[$key]['create_time'] = date('Ymd',$value['create_time']);
        }
        $data['list']   = $recharge_list;
        return $this->successResult($data);
    }


    /***
     * 公告列表
     */
    public function announce_list(){

        $user_id        = $this->get_user_id();

        $announce_list  = Db::name('announce')->where(['status' => 1])->field('*')->select();
        
        $data['list']   = $announce_list;
        return $this->successResult($data);
    }


    /***
     * 公告详情
     */
    public function announce_edit(){

        $user_id        = $this->get_user_id();

        $announce_id        = input('announce_id',0);

        if(!$announce_id){
            return $this->failResult("参数错误");
        }

        $announce  = Db::name('announce')->where(['status' => 1,'id' => $announce_id])->field('*')->find();

        return $this->successResult($announce);
    }

    //排行榜
    public function ranking_list()
    {
        $user_id = $this->get_user_id();
        // $user_id = 27958;
        $data=DB::name('order')
            ->alias('o')
            ->join('member m ','m.id=o.user_id')
            ->where('o.order_status > 0 AND o.order_status != 7')
            ->group('o.user_id')
            ->field('o.user_id,m.avatar,m.realname,sum(o.total_amount) as total')
            ->order('total desc')
            ->select();

        foreach ($data as $key => $value)
        {
            if (($key-1) < 0){
                $data[$key]['different_last'] = 0;
            }else{
                $data[$key]['different_last'] = $data[$key-1]['total']-$data[$key]['total'];
            }

            if ($value['user_id'] == $user_id){
                $my_ranking = $key+1;
            }

            $userid[$key] = $value['user_id'];

            $data[$key]['No'] = $key+1;
        }
        if (!in_array($user_id,$userid)){
            $my_ranking = 0;
        }

        if ($my_ranking){
            $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>$data , 'my_ranking'=>$my_ranking ]);
        }else{
            $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>$data , 'my_ranking'=>'没有我的订单信息不能排名' ]);
        }

    }

    //今日奖金池
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

        $total = $sum . '.00';
        $lottery_time = $lottery_time.' 00:00';

        $data['total'] =  $total;
        $data['lottery_time'] =   $lottery_time;
        $data['list'] =   $list;

        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>$data ]);
    }
    //分日奖金
    public function get_bonus()
    {

        $bonus = DB::name('bonus_pool')
            ->where('is_day',0)
            ->field('money,create_time')
            ->select();

        $sum = 0;
        foreach ($bonus as $key=>$value)
        {
            if (date('Y-m-d',time()-24*60*60) == date('Y-m-d',$bonus[$key]['create_time'])){

                $sum = $sum + $value['money'];
            }
        }

        if (empty($sum)){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'昨日未产生奖金或已开奖不予分奖','data'=>'']);
        }
        $t = time()-24*60*60;

        $start = mktime(0,0,0,date("m",$t),date("d",$t),date("Y",$t));
        $end = mktime(23,59,59,date("m",$t),date("d",$t),date("Y",$t));

        //需要知道推荐多少人
        //根据配置分奖金
        $direct_num =  Db::table('bonus_pool_setting')->field('direct_num')->where(['is_day'=>0])->order('direct_num asc')->select();

        $set = [];
        foreach ($direct_num as $key=>$value){
            $set[$key] = $value['direct_num'];
        }

        //标记
        $s = 0;

        for ($i=0;$i<count($set);$i++){
            if (($i+1) == count($set)){
                $where = "num >= $set[$i]";
                }else{
                $j = $i+1;
                $where = "num >= $set[$i] and num <$set[$j]";
                }


                $data=DB::name('member')
                ->where('first_leader','>','0')
                ->where('createtime','>=',$start)
                ->where('createtime','<=',$end)
                ->group('first_leader')
                ->having($where)
                ->field('first_leader,realname,remainder_money,count(first_leader) as num')
                ->order('num desc')
                ->select();

                $howpeople[$i] = count($data);//本次符合设置条件有多少人分
                if ($howpeople[$i] === 0){
                    continue;
                }

                $ratio =  Db::table('bonus_pool_setting')->where('direct_num',$set[$i])->where(['is_day'=>0])->field('proportion')->find();//查到本次条件的百分比
                $bonus = $sum*$ratio['proportion']*0.01/$howpeople[$i];//计算奖金

                //符合本次条件的所有人分奖金
                foreach ($data as $key=>$value){


                    //写入日志
                    Db::startTrans();
                    $insert = [
                        'user_id' => $data[$key]['first_leader'],
                        'balance' => $data[$key]['remainder_money'] + $bonus,
                        'change_money' =>$bonus,
                        'source_type' => 11,
                        'log_type' => 1,
                        'note'     => '分得日奖金',
                        'create_time' => time(),
                        'old_balance' => $data[$key]['remainder_money']
                    ];
                    $rel = Db::name('menber_balance_log')->insert($insert);

                    //更新会员余额
                    $res = Db::table('member')->where('id',$data[$key]['first_leader'])->setInc('remainder_money',$bonus);

                    if($res && $rel){
                        $s +=1;
                        Db::commit();
                    }else{
                        Db::rollback();
                        $this->ajaxReturn(['status' => 301 , 'msg'=>'失败！','data'=>'']);
                    }
                }
        }
        //清空奖金池
        $tt = time()-24*60*60;
        $startt = mktime(0,0,0,date("m",$tt),date("d",$tt),date("Y",$tt));
        $endt = mktime(23,59,59,date("m",$tt),date("d",$tt),date("Y",$tt));
        DB::name('bonus_pool')
            ->where('is_day','0')
            ->where('create_time','>=',$startt)
            ->where('create_time','<=',$endt)
            ->update(['money'=>0]);

        if ($s){
            $this->ajaxReturn(['status' => 200 , 'msg'=>'分奖金成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => 300 , 'msg'=>'没有符合分奖金条件的人','data'=>'']);
        }

    }

    //月奖金池
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
        $data['list'] =   $list;
        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>$data ]);
    }
    //分月奖金
    public function get_monthbonus()
    {

        $begin_time = mktime(0, 0 , 0,date("m")-1,1,date("Y"));
        $end_time = mktime(23,59,59,date("m") ,0,date("Y"));
        $bonus = DB::name('bonus_pool')
            ->where('is_day',1)
            ->where('create_time','>=',$begin_time)
            ->where('create_time','<=',$end_time)
            ->field('money,create_time')
            ->select();
        $sum = 0;
        foreach ($bonus as $key=>$value)
        {
            if ($end_time > $bonus[$key]['create_time'] && $bonus[$key]['create_time']  > $begin_time){

                $sum = $sum + $value['money'];
            }
        }


        $sump = DB::name('bonus_pool_setting')
            ->where('is_day',1)
            ->field('count(is_day) as sump')
            ->find()['sump'];
        //有分奖金资格的人数(月度排行榜前十名)
        $data=DB::name('order')
            ->alias('o')
            ->join('member m ','m.id=o.user_id')
            ->where('o.order_status > 1 AND o.order_status != 7')
            ->where('confirm_time','>=',$begin_time)
            ->where('confirm_time','<=',$end_time)
            ->group('o.user_id')
            ->field('o.user_id,m.avatar,m.realname,m.remainder_money,sum(o.total_amount) as total')
            ->order('total desc')
            ->limit(10)
            ->select();
        //需要知道推荐多少人
        foreach ($data as $key => $value)
        {
            if (($key-1) < 0){
                $data[$key]['different_last'] = 0;
            }else{
                $data[$key]['different_last'] = $data[$key-1]['total']-$data[$key]['total'];
            }

            $userid[$key] = $value['user_id'];

            $data[$key]['No'] = $key+1;
        }
        //根据配置分奖金
        $direct_num =  Db::table('bonus_pool_setting')->field('direct_num,proportion')->where(['is_day'=>1])->select();
        if (!empty($data)){
            foreach ($direct_num as $k=>$v)
            {
                foreach ($data as $key => $value){

                    if ($data[$key]['No'] == $direct_num[$k]['direct_num'] ){

                        $bonus = $sum*$direct_num[$k]['proportion']*0.01;
                        Db::startTrans();
                        $insert = [
                            'user_id' => $data[$key]['user_id'],
                            'balance' => $data[$key]['remainder_money'] + $bonus,
                            'change_money' =>$bonus,
                            'source_type' => 11,
                            'log_type' => 1,
                            'note'     => '分得月奖金',
                            'create_time' => time(),
                            'old_balance' => $data[$key]['remainder_money']
                        ];
                        Db::name('menber_balance_log')->insert($insert);

                        $res = Db::table('member')->where('id',$data[$key]['user_id'])->update(['remainder_money'=>$data[$key]['remainder_money']+$bonus]);
                        if($res){
                            Db::commit();
                            //清空奖金池
                            DB::name('bonus_pool')
                                ->where('is_day','1')
                                ->where('create_time','>=',$begin_time)
                                ->where('create_time','<=',$end_time)
                                ->update(['money'=>0]);
                        }else{
                            Db::rollback();
                            $this->ajaxReturn(['status' => 301 , 'msg'=>'失败！','data'=>'']);
                        }
                    }
                }
            }
            $this->ajaxReturn(['status' => 301 , 'msg'=>'成功','data'=>'']);
        }

        $this->ajaxReturn(['status' => 301 , 'msg'=>'未达到分奖金条件','data'=>'']);
    }

    /**
     * 设置支付密码
     * @return mixed
     */
    public function set_pay_password(){
        $new_password = trim(input('password'));
        $confirm_password = trim(input('repaypwd'));
        $userLogic = new UsersLogic();
        $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
        $this->ajaxReturn($data);
    }


    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        $user = M('member')->where('id', $this->user_id)->find();

            $paypwd = trim(input('paypwd'));

            if (empty($user['pwd'])){
                $this->ajaxReturn(['status'=>-1,'msg'=>'请先设置支付密码！','result'=>'']);
            }
            //以前设置过就得验证原来密码
            if(!empty($user['pwd']) && ($user['pwd'] != md5(C("AUTH_CODE").$paypwd))){
                $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>'']);
            }
    }

    /**
     *  修改支付密码
     * @param $user_id  用户id
     * @param $new_password  新密码
     * @return array
     */
    public function edit_pay_password()
    {
        $user = M('member')->where('id', $this->user_id)->find();
        $user_id = $this->user_id;
        $old_password = trim(input('old_password'));
        if(!empty($old_password) && ($user['pwd'] != md5(C("AUTH_CODE").$old_password))){
            $this->ajaxReturn(['status'=>-1,'msg'=>'原密码错误！','result'=>'']);
        }
        $new_password = trim(input('new_password'));
        if (strlen($new_password) < 6) {
            return array('status' => -1, 'msg' => '密码不能低于6位字符', 'result' => '');
        }

        $row = Db::name('member')->where(['id'=>$user_id])->update(array('pwd' =>md5(C("AUTH_CODE").$new_password)));
        if (!$row) {
            return array('status' => -1, 'msg' => '密码修改失败', 'result' => '');
        }
        return array('status' => 1, 'msg' => '密码修改成功', 'result' => '');
    }


    /**
     * 重置支付密码
     * @return mixed
     */
    public function paypwd_reset()
    {
            $user = M('member')->where('id', $this->user_id)->find();

            $verify_code = input('verify_code/s', '');
            //验证码判断
            $res = $this->phoneAuth($user['mobile'], $verify_code);
            if ($res === -1) {
                return $this->failResult('验证码已过期！', 301);
            } else if (!$res) {
                return $this->failResult('验证码错误！', 301);
            }
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $this->ajaxReturn($data);
    }




    /**
     * ios身份证上传
     */
    public function update_head_pic(){

        $user_id = $this->get_user_id();

        $idname = input('name');
        $idcard = input('idcard');
        $pic_front = input('pic_front');
        $pic_back = input('pic_back');

        if (empty($idname)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请填写身份证姓名','data'=>'']);
        }
        if (empty($idcard)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请填写身份证号码','data'=>'']);
        }

        if(!check_idcard($idcard,$idname,$user_id)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'身份证号码和真实姓名不一致','data'=>'']);
        }

        if($user_id!=""){
            if (strstr($pic_front, 'http')){
                Db::name('member')->where(['id' => $user_id])->update(['idcard'=>$idcard,'name'=>$idname]);
            }else{
                // 获取表单上传文件 例如上传了001.jpg
                $file = request()->file('pic_front');//正面
                if (!empty($file)){
                    // 移动到框架应用根目录/uploads/ 目录下
                    $info = $file->validate(['size'=>2048000000,'ext'=>'jpg,png,gif']);
                    $info = $file->rule('md5')->move(ROOT_PATH . DS.'public/upload');//加密->保存路径
                    if($info){
                        // 成功上传后 获取上传信息
                        // 输出 jpg
                        // echo $info->getExtension();
                        // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
                        // echo $info->getSaveName();
                        // 输出 42a79759f284b767dfcb2a0197904287.jpg
                        $pic_front = SITE_URL.'/public/upload/'.$info->getSaveName(); //输出路径
                        // ROOT_PATH . DS.
                        // 存着 地址
                        Db::name('member')->where(['id' => $user_id])->update(['pic_front' =>$pic_front  ,'idcard'=>$idcard,'name'=>$idname]);
                    }else{
                        $this->ajaxReturn(['status' => -2 , 'msg'=>'上传失败','data'=>$file->getError()]);
                    }

                }else{
                    $this->ajaxReturn(['status'=>2,'msg'=>'正面没上传','data'=>'']);
                }
            }

            if (strstr($pic_back, 'http')){

                Db::name('member')->where(['id' => $user_id])->update(['idcard'=>$idcard,'name'=>$idname]);
            }else{
                $files = request()->file('pic_back');//反面

                if (!empty($files)){
                    if ($files){
                        // 移动到框架应用根目录/uploads/ 目录下
                        $infos = $files->validate(['size'=>2048000000,'ext'=>'jpg,png,gif']);
                        $infos = $files->rule('md5')->move(ROOT_PATH . DS.'public/upload');//加密->保存路径
                        if($infos) {
                            // 成功上传后 获取上传信息
                            // 输出 jpg
                            // echo $info->getExtension();
                            // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
                            // echo $info->getSaveName();
                            // 输出 42a79759f284b767dfcb2a0197904287.jpg
                            $pic_back = SITE_URL . '/public/upload/' . $infos->getSaveName(); //输出路径
                        }else{
                            $this->ajaxReturn(['status' => -2 , 'msg'=>'上传失败','data'=>$file->getError()]);
                        }
                    }
                    Db::name('member')->where(['id' => $user_id])->update(['pic_back' =>$pic_back  ,'idcard'=>$idcard,'name'=>$idname]);

                }else{
                    $this->ajaxReturn(['status'=>2,'msg'=>'背面没上传','data'=>'']);
                }

            }

            $this->ajaxReturn(['status'=>200,'msg'=>'上传成功','data'=>['pic_front' =>$pic_front ,'pic_back' =>$pic_back ,'idcard'=>$idcard,'name'=>$idname]]);
            }

    }

    //获取汇率比率
    public function set_exchange_rate()
    {
        $appkey = "837b6ce63a8bf43ab0d46f963f8a5e5a";
        //************1.人民币牌价************
        $url = "http://web.juhe.cn:8080/finance/exchange/rmbquot";
        $params = array(
              "key" => $appkey,//APP Key
              "type" => "0",//两种格式(0或者1,默认为0)
        );
        $paramstring = http_build_query($params);
        $content = request_curl($url,$paramstring);
        $result = json_decode($content,true);
        $data['aus_tormb']=round($result['result'][0]['data6']['bankConversionPri']*0.01,4);
        $data['rmb_toaus']=round(1/$data['aus_tormb'],4);
        $res=Db::name('exchange_rate')->where(['id'=>1])->update($data);
        if($res!==false){
            $this->ajaxReturn(['status'=>200,'msg'=>'获取成功','data'=>$data]);
        }else{
            $this->failResult("获取失败");
        }

    }

    //微信登陆

    public function wxlogin()
    {
        $wxdata =  $data = input('post.');
        //邀请人id
        $uid=input('uid',0);

        $member = Db::table('member')->where('openid',$wxdata['openid'])->value('id');

        if (!empty($member)) {
//            $avatar = Db::table('member')->where('openid', $wxdata['openid'])->value('avatar');
//            if ($avatar == SITE_URL.'/public/static/images/headimg/20190711156280864771502.png'){
//                $data['avatar'] = $wxdata['headimgurl'];
//            }
            $lastlogin = Db::table('member')->where('openid',$wxdata['openid'])->value('lastlogin');
            $phone = Db::table('member')->where('openid',$wxdata['openid'])->value('mobile');
            if (empty($phone)){
                $phone = (string)0;
            }

            if (empty($lastlogin)){
                $data['token']   = $this->create_token($member);
                $data['mobile']  = $phone;
                $data['id']      = $member;
                $data['is_first']    = 1;
                return $this->successResult($data);
            }
            $data['token']   = $this->create_token($member);
            $data['mobile']  = $phone;
            $data['id']      = $member;
            Db::table('member')->where('id',$member)->update(['lastlogin'=>time()]);
            return $this->successResult($data);
        }
        if (empty($member)){

            $insert['openid']     = $wxdata['openid'];
            $insert['createtime'] = time();
            $insert['realname']   = $wxdata['nickname'];
            $insert['avatar']     = $wxdata['headimgurl'];
            $insert['gender']     = !empty($wxdata['sex'])?$wxdata['sex']:'';
            $insert['province']     = !empty($wxdata['province'])?$wxdata['province']:'';
            $insert['city']     = !empty($wxdata['city'])?$wxdata['city']:'';
            $insert['first_leader']=$uid;


            $id = Db::table('member')->insertGetId($insert);
            if (!$id) {
                return $this->failResult('注册失败，请重试！', 301);
            }
            //注册成功获取推广奖励
            // if($uid){
            //     $this->invite_bonus($uid,$id);
            // }
            $phone = Db::table('member')->where('id', $id)->value('mobile');
            if (empty($phone)){
                $phone = (string)0;
            }
            $data['token']   = $this->create_token($id);
            $data['mobile']  = $phone;
            $data['id']      = $id;
            $data['is_first']= 1;

            $inviteCode=input('invitation_code',0);
            if($inviteCode){
                $res=$this->invitation_code($data['id']);
                $resarr=json_decode($res,true);
                if($resarr['status']!==200){
                    return $res;
                }
            }

            return $this->successResult($data);
        }

    }


    //微信登陆后绑手机
    public function wxbound_phone()
    {
        $phone = input('phone/s', '');
        $verify_code = input('verify_code/s', '');
//        $this->user_id=27875;
        $user_id = $this->get_user_id();

        //验证手机号
        if(!isMobile($phone)){
            $this->failResult("手机号码格式错误",300);
        }
        //验证码判断
        $res = $this->phoneAuth($phone, $verify_code);
        if ($res === -1) {
            return $this->failResult('验证码已过期！', 301);
        } else if (!$res) {
            return $this->failResult('验证码错误！', 301);
        }
        $member = Db::name('member')->where(['mobile'=>$phone])->find();

        if (!empty($member['mobile']) && !empty($member['openid'])){
            $user_info=Db::name('member')->where(['id'=>$user_id])->find();
            if($user_info['mobile'] == $phone){
                $this->ajaxReturn(['status'=>300,'msg'=>'新手机号跟老手机号一致']);
            }
            $all_info=Db::name('member')->where(['mobile'=>$phone])->select();
            if($all_info){
                $this->failResult("此手机号码绑定的账号已达到上限，请更换手机号码");
            }
        }

        if(!empty($member['mobile'])){
            $wxmember = Db::table('member')->where('id', $user_id)->find();
            Db::startTrans();
            $updata['openid']     = $wxmember['openid'];
            $updata['realname']   = $member['realname'] == '默认昵称'? $wxmember['realname']:$member['realname'];
            $updata['avatar']     = substr($member['avatar'],-27) == '20190711156280864771502.png'? $wxmember['avatar']:$member['avatar'];

            $res = Db::table('member')->where(['mobile'=>$phone])->update($updata);
            if ($res){
                $r = Db::table('member')->where('id', $user_id)->delete();
                if ($r){
                    Db::commit();
                    $lastlogin = Db::table('member')->where(['mobile'=>$phone])->value('lastlogin');
                    if (empty($lastlogin)){
                        $last['is_first']= 1;
                    }
                    $this->ajaxReturn(['status'=>200,'msg'=>'绑定成功1','data'=>$last]);
                }
            }else{
                Db::rollback();
                $this->failResult("绑定失败",300);
            }

        }
        if (empty($member['mobile'])){
            $user_info=Db::name('member')->where(['id'=>$user_id])->find();
            if ($user_info){
                $res = Db::table('member')->where('id',$user_id)->update(['mobile'=>$phone]);
                if ($res){
                    $lastlogin = Db::table('member')->where('id',$user_id)->value('lastlogin');
                    if (empty($lastlogin)){
                        $lastlogin['is_first']= 1;
                    }
                    $this->ajaxReturn(['status'=>200,'msg'=>'绑定成功2','data'=>$lastlogin]);
                }else{
                    $this->failResult("绑定失败",300);
                }
            }else{
                $this->failResult("无此用户",300);
            }

        }
    }


    /**
     * @api {POST} /user/apply_recharge 申请充值
     * @apiGroup user
     * @apiVersion 1.0.0
     */
    public function apply_recharge(){

        if( Request::instance()->isPost() ) {

            $user_id = $this->get_user_id();

            $data = input('post.');

//            $qr_recharge = Db::table('qr_recharge')->where('user_id',$user_id)->find();
//
//            print_r($qr_recharge);die;
//            if($qr_recharge['handle_status'] == 1){
//                    $this->ajaxReturn(['status' => 301 , 'msg'=>'您已申请充值，待审核！','data'=>'']);
//            }


            if(!empty($data['img'])){
//                $img = json_decode($data['img'],true);

                    $data['img'] = explode(',',$data['img'])[1];
                    $saveName = request()->time().rand(0,99999) . '.png';

                    $imga=base64_decode($data['img']);
                    //生成文件夹
                    $names = "recharge" ;
                    $name = "recharge/" .date('Ymd',time()) ;
                    if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){
                        mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
                    }
                    //保存图片到本地
                    file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$imga);

                    // unset($img[$k]);
                    $data['img'] = $name.$saveName;
                }
            //获取汇率
            $toaus = Db::table('exchange_rate')->find()['rmb_toaus'];

            $data['australian_dollar']   = $toaus*$data['money'];
            $data['create_time']   = time();
            $data['user_id']   = $user_id;
            $data['img']   =$data['img'] ? $data['img'] : '';
            $data['handle_status'] = 1;
            Db::startTrans();
            $res = Db::table('qr_recharge')->strict(false)->insert($data);

            if($res){
                Db::commit();
                $this->ajaxReturn(['status' => 200 , 'msg'=>'申请成功待审核！','data'=>'']);
            }else{
                Db::rollback();
                $this->ajaxReturn(['status' => 301 , 'msg'=>'申请失败！','data'=>'']);
            }
        }

    }


    /**
     * @api {POST} /user/apply_recharge 获取收款码
     * @apiGroup user
     * @apiVersion 1.0.0
     */

    public function get_racharge_type()
    {
        $qr_racharge = Db::table('site')->field('wechat_qr_code,alipay_qr_code')->find();

        //获取汇率
        $toaus = Db::table('exchange_rate')->find();
       $qr_racharge['rmb_toaus'] = $toaus['rmb_toaus'];
       $qr_racharge['aus_tormb'] = $toaus['aus_tormb'];
        $qr_racharge['wechat_qr_code'] = SITE_URL.'/public/upload/images/'.$qr_racharge['wechat_qr_code'];
        $qr_racharge['alipay_qr_code'] = SITE_URL.'/public/upload/images/'.$qr_racharge['alipay_qr_code'];
        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功！','data'=> $qr_racharge]);
    }

}
