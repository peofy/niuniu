<?php

/***
 * 支付api
 */
namespace app\api\controller;
use app\api\controller\TestNotify;
use app\common\controller\ApiBase;
use Payment\Common\PayException;
use Payment\Notify\PayNotifyInterface;
use Payment\Notify\AliNotify;
use Payment\Client\Charge;
use Payment\Client\Notify;
use Payment\Config as PayConfig;
use app\common\model\Member as MemberModel;
use app\common\model\Order;
use app\common\model\Sales;

use \think\Model;
use \think\Config;
use \think\Db;
use \think\Env;
use \think\Request;

class Pay extends ApiBase
{
    /**
     * 析构流函数
     */
    public function  __construct() {
        require_once ROOT_PATH.'vendor/riverslei/payment/autoload.php';
    }




    /**
     * @api {POST} /pay/set_payment 设置收款
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParamExample {json} 请求数据:
     * {
     *     "token":"",           token
     *      "type":''            当前操作的类型 1:码云  2:微信   3:支付宝
     *      "name":""              昵称
     *      "image":""           收款码图片
     *
     *
     *     操作对应的类型,填写对应的昵称和图片路径
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {"status":200,"msg":"success","data":"操作成功"}
     * //错误返回结果
     * {"status":301,"msg":"fail","data":"操作失败"}
     * 无
     */
    public function set_payment()
    {
        $userid=$this->get_user_id();
        // $userid=42;
        if(!Request()->isPost()){
            return $this->failResult("请求方式错误");
        }
        $type=input('type');
        if(!$type){
            return $this->failResult("没有选择收款方式");
        }
        $data=input();
        $image = input('image');
        if(!$image){
            return $this->failResult('缺少图片参数');
        }
        $saveName = request()->time().rand(0,99999) . '.png';
        $imga=file_get_contents($image);
        //生成文件夹
        $names = "pay_picture" ;
        $name = "pay_picture/" .date('Ymd',time()) ;
        if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){
            mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
        }
        file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$imga);
        $imgPath = Config('c_pub.apiimg') . $name.$saveName;
        if($type==1){
                $data['my_pic']=$imgPath;
                $data['pay_default']=3;
                $data['my_status']=1;
        }
        if($type==2){
                $data['wx_pic']=$imgPath;
                $data['pay_default']=2;
                $data['wx_status']=1;
        }
        if($type==3){
                $data['zfb_pic']=$imgPath;
                $data['pay_default']=1;
                $data['zfb_status']=1;
        }
        $data['user_id']=$userid;
        $data['create_time']=time();
        unset($data['image']);
        unset($data['type']);
        unset($data['token']);
        $one=Db::name('member_payment')->where('user_id',$userid)->find();
        if($one){
            $res=Db::name("member_payment")->where('user_id',$userid)->update($data);
        }else{
            $res=Db::name("member_payment")->insert($data);
        }
        if($res){
            return $this->successResult("操作成功");
        }else{
            return $this->failResult("操作失败");
        }

    }

    /**
     * @api {POST} /pay/get_user_payment  获取收款信息
     * @apiGroup user
     * @apiVersion 1.0.0
     *
     * @apiParamExample {json} 请求数据:
     * {
     *     "token":"",           token
     *
     *     操作对应的类型,填写对应的昵称和图片路径
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {"status":200,"msg":"success",
     * "data":{"pay_id":1,
     * "user_id":42,        //用户id
     * "my_name":"1111111",
     * "my_pic":"http://newretail.com/upload/images/pay_picture/20190709156266206410296.png",          //码云闪付二维码
     *  "my_status":1,      是否已经设置   0为未设置   1为已经设置
     * "wx_name":"cxxxxxxhenchenchen11","wx_pic":"\\uploads\\pay_picture\\6f916d3c4c3805e6ea3578718af99a86.png",  //微信收款二维码
     * "wx_status":1,
     * "zfb_account":"chenchenchen11","zfb_pic":"uploads\\pay_picture\\3ca9c2151ec5e02dda500daa293ace47.png",   //支付宝收款二维码
     * "zfb_status":1,
     * "pay_default":3,     //默认收款码：0为未设置,1:支付宝 ,2:微信,3:码云闪付
     * "create_time":1562662065}}
     * //错误返回结果
     * {"status":301,"msg":"fail","data":"操作失败"}
     * 无
     */
    public function get_user_payment()
    {
        $userid=$this->get_user_id();
        // $userid=42;
        if(!Request()->isPost()){
            return $this->failResult("请求方式错误");
        }
        $user_payment=Db::name("member_payment")->where("user_id",'=',$userid)->find();
        return $this->successResult($user_payment);
    }




    /***
     * 支付
     */
    public function payment(){
        $order_id     = input('order_id');
        $pay_type     = input('pay_type',0);//支付方式
        $pwd          = input('pwd','');
        $user_id      = $this->get_user_id();
        // $user_id      = 27951;

        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id,shipping_price')->find();//订单信息
        $member       = MemberModel::get($user_id);
        //验证是否本人的
        if(!$order_info){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'订单不存在','data'=>'']);
        }
        if($order_info['user_id'] != $user_id){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'非本人订单','data'=>'']);
        }

    	if($order_info['pay_status'] == 1){
			$this->ajaxReturn(['status' => 301 , 'msg'=>'此订单，已完成支付!','data'=>'']);
        }
        $amount       = $order_info['order_amount'];
        $client_ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

        if($pay_type == 3){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'已关闭','data'=> '']);
            $payData['order_no']        = $order_info['order_sn'];
            $payData['body']            = '';
            $payData['timeout_express'] = time() + 600;
            $payData['amount']          = $amount;
            $payData['subject']         = '支付宝支付';
            $payData['goods_type']      = 1;//虚拟还是实物
            $payData['return_param']    = '';
            $payData['store_id']        = '';
            $payData['quit_url']        = '';
            $pay_config = Config::get('pay_config');
            $web_url    = Db::table('site')->value('web_url');
            $pay_config['return_url'] = $web_url.'/order?type=2';
            $url        = Charge::run(PayConfig::ALI_CHANNEL_WAP, $pay_config, $payData);
            try {
                $this->ajaxReturn(['status' => 200 , 'msg'=>'支付路径','data'=> ['url' => $url]]);
            } catch (PayException $e) {
                $this->ajaxReturn(['status' => 301 , 'msg'=>$e->errorMessage(),'data'=>'']);
                exit;
            }
        }elseif($pay_type == 2){
            $payData = [
                'body'        => '测试',
                'subject'     => '测试',
                'order_no'    =>  $order_info['order_sn'],
                'timeout_express' => time() + 600,// 表示必须 600s 内付款
                'amount'       => $amount,// 金额
                'return_param' => '',
                'client_ip'    => $this->get_client_ip(),// 客户地址
                'scene_info' => [
                    'type'     => 'Wap',// IOS  Android  Wap  腾讯建议 IOS  ANDROID 采用app支付
                    'wap_url'  => 'http://www.puruitingxls.com/',//自己的 wap 地址
                    'wap_name' => '微信支付',
                ],
            ];
            $web_url    = Db::table('site')->value('web_url');

            $wxConfig = Config::get('wx_config');
            $wxConfig['redirect_url'] = $web_url.'/Order/OrderDetails?order_id='.$order_id;
            $url      = Charge::run(PayConfig::WX_CHANNEL_WAP, $wxConfig, $payData);
            try {
                $this->ajaxReturn(['status' => 200 , 'msg'=>'支付路径','data'=> ['url' => $url]]);
            } catch (PayException $e) {
                $this->ajaxReturn(['status' => 301 , 'msg'=>$e->errorMessage(),'data'=>'']);
                exit;
            }
        }elseif($pay_type == 1){
            if(empty($pwd)){
                $this->ajaxReturn(['status' => 301 , 'msg'=>'支付密码不能为空！','data'=>'']);
            }

            if(empty($member['pwd'])){
                $this->ajaxReturn(['status' => 888 , 'msg'=>'支付密码未设置','data'=>'']);
            }

            $pwd = md5($member['salt'] . $pwd);
            if ($pwd != $member['pwd']) {
                $this->ajaxReturn(['status' => 301 , 'msg'=>'支付密码错误！','data'=>'']);
            }
            // $balance_info  = get_balance($user_id,0);
            if($member['remainder_money'] < $order_info['order_amount']){
                $this->ajaxReturn(['status' => 308 , 'msg'=>'余额不足','data'=>'']);
            }
            // 启动事务
            Db::startTrans();

            //判断是否能进入50元专区

            $goods_ids = Db::table('order_goods')->where('order_id',$order_id)->column('goods_id');
            $is_gift   = Db::table('goods')->where('goods_id','in',$goods_ids)->where('is_gift',1)->count();

            if($is_gift > 0){
                $remainder_money['is_release']     = 1;
            }
            //扣除用户余额
            $remainder_money['remainder_money'] = Db::raw('remainder_money-'.$amount.'');
            $res =  Db::table('member')->where(['id' => $user_id])->update($remainder_money);
            if(!$res){
                Db::rollback();
                $this->ajaxReturn(['status' => 301 , 'msg'=>'余额不足','data'=>'']);
            }
            //余额记录
            $balance_log = [
                'user_id'      => $user_id,
                'balance'      => $member['remainder_money'] - $order_info['order_amount'],
                'source_type'  => 1,
                'log_type'     => 0,
                'source_id'    => $order_info['order_sn'],
                'note'         => '商品订单消费',
                'create_time'  => time(),
                'old_balance'  => $member['remainder_money']
            ];
            $res2 = Db::table('menber_balance_log')->insert($balance_log);
            if(!$res2){
                Db::rollback();
            }
            //修改订单状态
            $update = [
                'order_status' => 1,
                'pay_status'   => 1,
                'pay_type'     => $pay_type,
                'pay_time'     => time(),
            ];
            $reult = Order::where(['order_id' => $order_id])->update($update);


            $goods_res = Db::table('order_goods')->field('goods_id,goods_name,goods_num,spec_key_name,goods_price,sku_id')->where('order_id',$order_id)->select();

            $jifen = 0;
            $fenyong_money = 0;
            foreach($goods_res as $key=>$value){

                $goods = Db::table('goods')->where('goods_id',$value['goods_id'])->field('less_stock_type,gift_points,is_distribution')->find();
                if($goods['is_distribution']){
                    $price = Db::table('goods_sku')->where('sku_id',$value['sku_id'])->value('price');
                    $fenyong_money = sprintf("%.2f",$fenyong_money + ($value['goods_num'] * $price));
                }
                //付款减库存
                if($goods['less_stock_type']==2){
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('frozen_stock',$value['goods_num']);
                    // Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
                }
                $baifenbi = strpos($goods['gift_points'] ,'%');
                if($baifenbi){
                    $goods['gift_points'] = substr($goods['gift_points'],0,strlen($goods['gift_points'])-1); 
                    $goods['gift_points'] = $goods['gift_points'] / 100;
                    $jg    = sprintf("%.2f",$value['goods_price'] * $value['goods_num']);
                    $jifen = sprintf("%.2f",$jifen + ($jg * $goods['gift_points']));
                }else{
                    $goods['gift_points'] = $goods['gift_points'] ? $goods['gift_points'] : 0;
                    $jifen = sprintf("%.2f",$jifen + ($value['goods_num'] * $goods['gift_points']));
                }
            }

            $fenyong_money = sprintf("%.2f",$fenyong_money + $order_info['shipping_price']);

            if($fenyong_money > 0){
                $Sales = new Sales($user_id,$order_id,0);
                $res = $Sales->reward_leve($user_id,$order_id,$order_info['order_sn'],$fenyong_money,0);
                if($res === false){
                    Db::rollback();
                    $this->ajaxReturn(['status' => 301 , 'msg'=>'余额支付失败1','data'=>'']);
                }
            }
            // $res = Db::table('member')->update(['id'=>$user_id,'gouwujifen'=>$jifen]);

            if($reult){
                $orderInfo=Db::name('order')->where(['order_id'=>$order_id])->find();
                $goods_order = Db::name('order_goods')->where(['order_id'=>$order_id])->find();
                if($orderInfo['pay_status']==1){
                    $this->pay_uppers($order_id);
                    // $this->add_performance($order_id);
                    $this->add_agent_performance($order_id);
                    //更新所有上级用户等级  根据业绩或者是推荐的人
                    $this->upgrade_uppers($user_id);
                    //符合无限级的分佣
                    $this->unlimit_bonus($user_id,$order_id);
                }

                // 提交事务
                Db::commit();
                Db::table('goods_sku')->where('sku_id',$goods_order['sku_id'])->setDec('sales',1);
                Db::table('goods')->where('goods_id',$goods_order['goods_id'])->setDec('number_sales',1);
                $this->ajaxReturn(['status' => 200 , 'msg'=>'余额支付成功!','data'=>['order_id' =>$order_info['order_id'],'order_amount' =>$order_info['order_amount'],'goods_name' => getPayBody($order_info['order_id']),'order_sn' => $order_info['order_sn'] ]]);
            }else{
                Db::rollback();
                $this->ajaxReturn(['status' => 301 , 'msg'=>'余额支付失败2','data'=>'']);
            }
        }
    }



    public function unlimit_bonus($user_id,$order_id){

        $order=Db::name('order')->where(['order_id'=>$order_id])->find();
        $uppers=first_leader_ids($user_id);
        if(!$uppers){
            $uppers=[];
        }
        foreach($uppers as $uk=>$uv){
            $users=get_all_lower($uv); 
            $team_num=count($users);
            $direct_num=Db::name('member')->where(['first_leader'=>$uv])->count();
            $four_lev=Db::name('invite_bonus')->where(['id'=>4])->find();
            $five_lev=Db::name('invite_bonus')->where(['id'=>5])->find();
            //是否符合第四层的要求
            if($team_num>=$four_lev['team_num']&&$team_num<$five_lev['team_num']){
                if($direct_num>=$four_lev['pop_num']&&$direct_num<$five_lev['pop_num']){
                    $bonus=$four_lev['product_rate']*$order['total_amount'];
                    $desc="团队会员id{$user_id}购物无限级分销奖励{$bonus}";
                    Db::name('member')->where(['id'=>$uv])->setInc('remainder_money',$bonus);
                    Db::name('menber_balance_log')->insert(['user_id'=>$uv,'balance'=>$bonus,
                    'source_type'=>15,'log_type'=>1,'note'=>$desc,'create_time'=>time(),'balance_type'=>1]);
                }
            }
            //是否符合第五层的要求
            if($team_num>=$five_lev['team_num']){
                if($direct_num>=$five_lev['pop_num']){
                    $bonus=$five_lev['product_rate']*$order['total_amount'];
                    $desc="团队会员id{$user_id}购物无限级分销奖励{$bonus}";
                    Db::name('member')->where(['id'=>$uv])->setInc('remainder_money',$bonus);
                    Db::name('menber_balance_log')->insert(['user_id'=>$uv,'balance'=>$bonus,
                    'source_type'=>15,'log_type'=>1,'note'=>$desc,'create_time'=>time(),'balance_type'=>1]);
                }
            }
        }
    }


    public function upgrade_uppers($user_id){
        $uppers=first_leader_ids($user_id);
        if(!$uppers){
            $uppers=[];
        }
        $user_id=[0=>$user_id];
        $users=array_merge($user_id,$uppers);
        foreach($users as $uk=>$uv){
                $this->upgrade_one($uv);
        }
    }




    public function upgrade_one($user_id){
        $member_model=Db::name('member');
        $one=$member_model->where(['id'=>$user_id])->find();
        $next_level=$one['level']+1;
        if($next_level<7){
        $member_level=Db::name('member_level')->column('*','level');
        $standard_team_num=$member_level[$next_level]['team_num'];  //下级的团队人数条件
        $standar_agent_amount=$member_level[$next_level]['agent_amount'];   //下级的业绩总数
        $users=get_all_lower($user_id); //当前团队人数
        $num=count($users);
        $agent_performance=Db::name('agent_performance')->where(['user_id'=>$user_id])->find();
        //   $this->ajaxReturn(['status' => 301 , 'msg'=>"num:{$num}-standar_team_num{$standard_team_num}-agent_performanceagnet_per{$agent_performance['agent_per']}-standar_agent_amount{$standar_agent_amount}",'data'=>'']);
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


    // public function add_performance($order_id){
    //     $orderInfo=Db::name('order')->where(['order_id'=>$order_id])->find();
    //     $user_id=$orderInfo['user_id'];
    //     $performanceModel=Db::name('agent_performance');
    //     $agent_performance=$performanceModel->where(['user_id'=>$user_id])->find();
    //     $time=time();
    //     if($agent_performance){
    //         $performanceModel->where(['user_id'=>$user_id])->setInc('agent_per',$orderInfo['total_amount']);
    //     }else{
    //         $performanceModel->insert(['user_id'=>$user_id,'agent_per'=>$orderInfo['total_amount'],'create_time'=>$time]);
    //     }
    //     $all_uppers=first_leader_ids($user_id);
    //     foreach($all_uppers as $v){
    //        $uppers = $performanceModel->where(['user_id'=>$v])->find();
    //         if($uppers){
    //             $performanceModel->where(['user_id'=>$v])->setInc('agent_per',$orderInfo['total_amount']);
    //         }else{
    //               $performanceModel->insert(['user_id'=>$v,'agent_per'=>$orderInfo['total_amount'],'create_time'=>$time]);
    //         }
    //     }
    // }


    public function add_agent_performance($order_id)
    {

        $time = time();
        $order=Db::name('order')->where(['order_id'=>$order_id])->find();
        Db::name('agent_performance_log')->insert(['user_id'=>$order['user_id'],'money'=>$order['total_amount'],'create_time'=>$time,'note'=>'下单消费','order_id'=>$order['order_id'],'status'=>0]);
        $user_id=[0=>$order['user_id']];
        $uppers = first_leader_ids($order['user_id']);
        // var_dump($uppers);die;
        if(!$uppers){
            $uppers=[];
        }

        $all_uppers=array_merge($uppers,$user_id);
        foreach ($all_uppers as $v) {
            $user_agent = Db::name('agent_performance')->where('user_id', '=', $v)->find();
            $ind_per = $user_agent['ind_per'] + $order['total_amount'];
            if ($order['user_id'] == $v) {
                if ($user_agent) {
                    Db::name('agent_performance')->where('performance_id', '=', $user_agent['performance_id'])->update(['user_id' => $v, 'ind_per' => $ind_per, 'update_time' => $time]);
                } else {
                    Db::name('agent_performance')->insert(['user_id' => $v, 'ind_per' => $ind_per, 'agent_per' => 0, 'create_time' => $time]);
                }
            } else {
                $agent_per = $user_agent['agent_per'] + $order['total_amount'];
                if ($user_agent) {
                    Db::name('agent_performance')->where('performance_id', '=', $user_agent['performance_id'])->update(['user_id' => $v, 'agent_per' => $agent_per, 'update_time' => $time]);
                } else {
                    Db::name('agent_performance')->insert(['user_id' => $v, 'agent_per' => $agent_per, 'create_time' => $time]);
                }
            }
        }
    }




    public function pay_uppers($order_id){
        $orderInfo=Db::name('order')->where(['order_id'=>$order_id])->find();
        $member_level=Db::name('member_level')->column("*",'level');
        $memberInfo=Db::name('member')->where(['id'=>$orderInfo['user_id']])->find();
        //奖励上级
        if($memberInfo['first_leader']){
            $first_leader=Db::name('member')->where(['id'=>$memberInfo['first_leader']])->find();
            if($first_leader){
            $bonus=$member_level[$first_leader['level']]['direct_rate']*$orderInfo['total_amount'];
            if($bonus){
                    Db::name('member')->where(['id'=>$memberInfo['first_leader']])->setInc('remainder_money',$bonus);
                    $desc="下级{$memberInfo['mobile']}-id{$memberInfo['id']}购买商品一级分佣{$bonus}元";
                    Db::name('menber_balance_log')->insert(['user_id'=>$first_leader['id'],'balance'=>$bonus,'source_type'=>37,'log_type'=>1,'note'=>$desc,'create_time'=>time(),'balance_type'=>1,'order_id'=>$order_id]);
            }
            }
            // 奖励上上级
            $second_leader=Db::name('member')->where(['id'=>$first_leader['first_leader']])->find();
            if($second_leader){
                $sec_bonus=$member_level[$second_leader['level']]['indirect_rate']*$orderInfo['total_amount'];
                if($sec_bonus){
                    Db::name('member')->where(['id'=>$second_leader['id']])->setInc('remainder_money',$sec_bonus);
                    $sec_desc="下级{$memberInfo['mobile']}-id{$memberInfo['id']}购买商品二级分佣{$sec_bonus}元";
                    Db::name('menber_balance_log')->insert(['user_id'=>$second_leader['id'],'balance'=>$sec_bonus,'source_type'=>38,'log_type'=>1,'note'=>$sec_desc,'create_time'=>time(),'balance_type'=>1,'order_id'=>$order_id]);
                }
            }
        }
    }


    public function release_wx_pay(){
        $user_id = $this->get_user_id();
        $pay_type     = input('pay_type',1);//支付方式
        $pwd          = input('pwd');

        $ss = Db::table('config')->where('module',5)->where('name','release_money')->value('value');//金额

        $user = MemberModel::where('id',$user_id)->field('is_vip,release,residue_release,release_ci,release_time,remainder_money,salt,pwd,first_leader')->find();
        $time = strtotime(date("Y-m-d"),time());
        if(!$user['residue_release'] && $user['release'] == $user['release_ci'] &&  $time == $user['release_time'] ){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'今天发布次数已用完！','data'=>'']);
        }
        if( !$user['residue_release'] && $time > $user['release_time'] ){
            MemberModel::where('id',$user_id)->update(['release_time'=>$time,'release_ci'=>0]);
        }


        if($user['residue_release']){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'今天发布次数还未用！','data'=>'']);
        }
        $orderNo = time() . 'release' . rand(1000, 9999);
        $web_url    = Db::table('site')->value('web_url');
        if($pay_type==1){
            $pwd = md5($user['salt'] . $pwd);

            if($user['remainder_money'] < $ss){
                $this->ajaxReturn(['status' => 308 , 'msg'=>'余额不足！','data'=>'']);
            }

            if($pwd != $user['pwd']){
                $this->ajaxReturn(['status' => 301 , 'msg'=>'支付密码错误！','data'=>'']);
            }

            $insert = [
                'order_sn' => $orderNo,
                'user_id'  => $user_id,
                'amount'   => $ss,
                'source'   => $pay_type,
                'order_status' => 1,
                'create_time'  => time(),
                'pay_time'     => time(),
            ];
            $res = Db::name('fifty_order')->insert($insert);
            if($res == false){
                $this->ajaxReturn(['status' => 301 , 'msg'=>'下单失败!','data'=>'']);
            }

            if(!$user['is_vip']){
                Db::table('member')->where('id',$user_id)->setInc('is_vip');

                if($user['first_leader']){
                    Db::table('member')->where('id',$user['first_leader'])->setInc('release');
                }
            }

            // 启动事务
            Db::startTrans();

            $res = Db::table('member')->where('id',$user_id)->setDec('remainder_money',$ss);
            // $res1 = Db::table('member')->where('id',$user_id)->setInc('residue_release');
            if($res){
                //记录
                $log['user_id']     = $user_id;
                $log['balance']     = $user['remainder_money'] - $ss;//现有余额
                $log['old_balance'] = $user['remainder_money'];      //原有余额
                $log['source_type'] = 2;
                $log['log_type']    = 0;
                $log['source_id']   = $orderNo;
                $log['note']        = '平台服务费用-余额';
                $log['create_time'] = time();

                Db::table('menber_balance_log')->insert($log);

                $Sales = new Sales($user_id,$orderNo,0);

                $rest  = $Sales->rewardtame($user_id,$ss,0,$orderNo);
                if($rest === false){
                    Db::rollback();
                    $this->ajaxReturn(['status' => 301 , 'msg'=>'分佣失败2!','data'=>'']);
                }

                $rest = $Sales->reward_fifty($user_id,$orderNo,$orderNo,$ss);
                if($rest === false){
                    Db::rollback();
                    $this->ajaxReturn(['status' => 301 , 'msg'=>'分佣失败1!','data'=>'']);
                }
                Db::table('member')->where('id',$user_id)->setInc('release_ci');
                Db::table('fifty_zone_order')->where('user_id',$user_id)->where('is_show',0)->update(['is_show'=>1]);
                Db::commit();
                $this->ajaxReturn(['status' => 310 , 'msg'=>'付款成功!','data'=>'']);
            }
            Db::rollback();
            $this->ajaxReturn(['status' => 301 , 'msg'=>'付款失败!','data'=>'']);
        }elseif($pay_type==2){
            Db::startTrans();
            $insert = [
                'order_sn' => $orderNo,
                'user_id'  => $user_id,
                'amount'   => $ss,
                'source'   => $pay_type,
                'order_status' => 0,
                'create_time'  => time(),
            ];
            $res = Db::name('fifty_order')->insert($insert);
            if($res == false){
                Db::rollback();
                $this->ajaxReturn(['status' => 301 , 'msg'=>'下单失败!','data'=>'']);
            }
            $payData = [
                'body'        => '测试',
                'subject'     => '微信支付',
                'order_no'    =>  $orderNo,
                'timeout_express' => time() + 600,// 表示必须 600s 内付款
                'amount'       => $ss,// 金额
                'return_param' => '',
                'client_ip'    =>  $this->get_client_ip(),// 客户地址
                'scene_info'   => [
                    'type'     => 'Wap',// IOS  Android  Wap  腾讯建议 IOS  ANDROID 采用app支付
                    'wap_url'  => $web_url.'/Payment',//自己的 wap 地址
                    'wap_name' => '微信支付',
                ],
            ];
            $wxConfig = Config::get('wx_config');
            // $wxConfig['redirect_url'] = $web_url.'/Payment';
            $url      = Charge::run(PayConfig::WX_CHANNEL_WAP, $wxConfig, $payData);
            try {
                Db::commit();
                $this->ajaxReturn(['status' => 311 , 'msg'=>'支付路径','data'=> ['url' => $url]]);
            } catch (PayException $e) {
                Db::rollback();
                $this->ajaxReturn(['status' => 301 , 'msg'=>$e->errorMessage(),'data'=>'']);
                exit;
            };

        }elseif($pay_type == 3){//支付宝支付

        $this->ajaxReturn(['status' => 301 , 'msg'=>'已关闭','data'=> '']);
        Db::startTrans();
            $insert = [
                'order_sn' => $orderNo,
                'user_id'  => $user_id,
                'amount'   => $ss,
                'source'   => $pay_type,
                'order_status' => 0,
                'create_time'  => time(),
            ];
            $res = Db::name('fifty_order')->insert($insert);
            if( $res == false){
                Db::rollback();
                $this->ajaxReturn(['status' => 301 , 'msg'=>'下单失败!','data'=>'']);
            }
            $payData['order_no']        = $orderNo;
            $payData['body']            = '';
            $payData['timeout_express'] = time() + 600;
            $payData['amount']          = $ss;
            $payData['subject']         = '支付宝支付';
            $payData['goods_type']      = 1;//虚拟还是实物
            $payData['return_param']    = '';
            $payData['store_id']        = '';
            $payData['quit_url']        = '';
            $pay_config = Config::get('pay_config');
            $pay_config['return_url'] = $web_url.'/Payment';
            $url        = Charge::run(PayConfig::ALI_CHANNEL_WAP, $pay_config, $payData);
            try {
                Db::commit();
                $this->ajaxReturn(['status' => 200 , 'msg'=>'支付路径','data'=> ['url' => $url]]);
            } catch (PayException $e) {
                Db::rollback();
                $this->ajaxReturn(['status' => 301 , 'msg'=>$e->errorMessage(),'data'=>'']);
                exit;
            }

        }
    }


    public function get_client_ip() {
        if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return preg_match ( '/[\d\.]{7,15}/', $ip, $matches ) ? $matches [0] : '';
    }



    public function recharge_pay(){
        $user_id = $this->get_user_id();
        $pay_type     = input('pay_type',3);//支付方式
        $money        = input('money/f',0);
        if(!preg_match("/^\d+(\.\d+)?$/",$money))$this->failResult('请输入正确的金额！', 301);


        $ss = Db::table('config')->where('module',5)->where('name','release_money')->value('value');//最大充值金额

        if($money > 1000){
            $this->failResult('超过最大金额！', 301);
        }
        $orderNo = time() . 'recharge' . rand(1000, 9999);

        if($pay_type==2){
            $ip = $this->get_client_ip();
            $payData = [
                'body'        => '微信充值',
                'subject'     => '微信充值',
                'order_no'    =>  $orderNo,
                'timeout_express' => time() + 600,// 表示必须 600s 内付款
                'amount'       => $money,// 金额
                'return_param' => '',
                'client_ip'    => $ip,// 客户地址
                'scene_info' => [
                    'type'     => 'Wap',// IOS  Android  Wap  腾讯建议 IOS  ANDROID 采用app支付
                    'wap_url'  => '',//自己的 wap 地址
                    'wap_name' => '微信支付',
                ],
            ];
            // var_dump($this->getIp());
            // die;
            $wxConfig = Config::get('wx_config');
            $url      = Charge::run(PayConfig::WX_CHANNEL_WAP, $wxConfig, $payData);
            try {
                $this->ajaxReturn(['status' => 200 , 'msg'=>'支付路径','data'=> ['url' => $url]]);
            } catch (PayException $e) {
                $this->ajaxReturn(['status' => 301 , 'msg'=>$e->errorMessage(),'data'=>'']);
                exit;
            };

        }elseif($pay_type == 3){//支付宝支付
        $this->ajaxReturn(['status' => 301 , 'msg'=>'已关闭','data'=> '']);
        Db::startTrans();
            $insert = [
                'order_sn' => $orderNo,
                'user_id'  => $user_id,
                'amount'   => $money,
                'source'   => $pay_type,
                'order_status' => 0,
                'create_time'  => time(),
            ];
            $res = Db::name('recharge_order')->insert($insert);
            if( $res == false){
                Db::rollback();
                $this->ajaxReturn(['status' => 301 , 'msg'=>'充值失败!','data'=>'']);
            }
            $payData['order_no']        = $orderNo;
            $payData['body']            = '余额充值';
            $payData['timeout_express'] = time() + 600;
            $payData['amount']          = $money;
            $payData['subject']         = '支付宝支付';
            $payData['goods_type']      = 1;//虚拟还是实物
            $payData['return_param']    = '';
            $payData['store_id']        = '';
            $payData['quit_url']        = '';
            $pay_config = Config::get('pay_config');
            $web_url    = Db::table('site')->value('web_url');
            $pay_config['return_url'] = $web_url.'/Sell';
            $url        = Charge::run(PayConfig::ALI_CHANNEL_WAP, $pay_config, $payData);
            try {
                Db::commit();
                $this->ajaxReturn(['status' => 200 , 'msg'=>'支付路径','data'=> ['url' => $url]]);
            } catch (PayException $e) {
                Db::rollback();
                $this->ajaxReturn(['status' => 301 , 'msg'=>$e->errorMessage(),'data'=>'']);
                exit;
            }

        }
    }


    /***
     * 微信支付回调
     */
    public function weixin_notify(){
        $callback = new TestNotify();
        $config   = Config::get('wx_config');
        $ret      = Notify::run('wx_charge', $config, $callback);
        echo  $ret;
    }

    /***
     * 支付宝回调
     */
    public function alipay_notify(){
        $callback = new TestNotify();
        $config   = Config::get('pay_config');
        $ret      = Notify::run('ali_charge', $config, $callback);
        echo  $ret;
    }

    /**
    * 得到新订单号
    * @return  string
    */
    public function build_order_no()
    {
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        return date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * 模拟post进行url请求
     * @param string $url
     * @param string $param
     */
    public function request_post($url,$param="") {
        $postUrl  = $url;
        $curlPost = $param;
        $ch       = curl_init();
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 只信任CA颁布的证书
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 检查证书中是否设置域名，并且是否与提供的主机名匹配                               //初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);                 //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);                    //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);            //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);                      //post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);        // 增加 HTTP Header（头）里的字段
        // curl_setopt($ch, CURLOPT_SSLCERT,config('tixian_wx_config.client_cert')); //这个是证书的位置
        // curl_setopt($ch, CURLOPT_SSLKEY, config('tixian_wx_config.client_key')); //这个也是证书的位置
        //curl_setopt($ch, CURLOPT_CAINFO, config('zfb_config.rootca')); //
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /**
     * 第三方支付宝支付
     *
     * @return void
     */
    public function alipay(){

        $user_id      = $this->get_user_id();
        $order_id     = input('order_id');
        $update       = ['order_chongfuzhifu'=>$this->build_order_no()];
        Db::name('order')->where(['order_id' => $order_id])->update($update);
        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id,shipping_price,goods_price,order_chongfuzhifu')->find();//订单信息
        $order_goods  = Db::name('order_goods')->where(['order_id'=>$order_id])->field('order_id,goods_num,sku_id,spec_key_name')->find();
        $member       = MemberModel::get($user_id);
        //验证是否本人的111
        if(!$order_info){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'订单不存在','data'=>'']);
        }

        if($order_info['pay_status'] == 1){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'此订单，已完成支付!','data'=>'']);
        }
        $amount    = $order_info['order_amount'];
        $secretKey = "92ca23251eaa4c319f35fa4a978cd1e1";
        $timestamp = strval(time());
        $nonceStr  = strval($this->build_order_no());
        $money     = 1.3*100;
		$paypost['amount']         = strval($money);//交易金额
		$paypost['outTradeNo']     = strval($order_info['order_chongfuzhifu']);//外部订单号
		$paypost['currency']       = 'AUD'; //标价币种
		$paypost['payGateway']     = 'alipay'; //支付网关
		$paypost['notifyUrl']      = 'http://cattle.zhifengwangluo.com/api/pay/alipaynotify';//后台回调URL
		$paypost['referUrl']       = 'http://cattle.zhifengwangluo.com';//APP下载地址
		$paypost['goodsInfo']      = strval($order_goods['sku_id'].'^'.$order_goods['goods_num']);//商品信息，包含商品的SKU名和相应的数量，格式为 SKU_名^数量
		$paypost['totalQuantity']  = strval($order_goods['goods_num']);// 一个订单中的商品总量
		$paypost['body']           = json_encode($order_info);//交易详细说明，可以是商品json数据

        $paypost['mchCode']        = "326609695";//商户编码，MSPAY开通商户时所填写的商户编码
        $paypost['timestamp']      = $timestamp;//时间戳，见参数规定中的时间戳
        $paypost['nonceStr']       = $nonceStr;//随机字符串，见参数规定中的随机字符串

        $signpost['mchCode']       = "326609695";//商户编码，MSPAY开通商户时所填写的商户编码
        $signpost['timestamp']     = $timestamp;//时间戳，见参数规定中的时间戳
        $signpost['nonceStr']      = $nonceStr;//随机字符串，见参数规定中的随机字符串
        ksort($paypost);
        $str ='';
        foreach($signpost as $k=>$v){
            // $str.= $k.'='.$v.'&';
            $str.= $v.'&';
        }

        $str.=$secretKey;
        $paypost['sign'] = strtoupper(md5($str));
        $url     = 'https://merchant.mspay.com.au/api-open/v1/createAPPOrder';
        $res     =  $this->request_post($url,$paypost);
        $res     = json_decode($res,true);
        $this->ajaxReturn(['status' => 200 , 'msg'=>'支付宝扫码地址','data'=> $res]);
    }
    /**
     * 第三方支付宝回调
     * @return [type] [description]
     */
    public function alipaynotify(){
        $paypost    = file_get_contents("php://input");
        $paypost    = json_decode($paypost, true);
        write_log(var_export($paypost, true),'alipaynotify');
		$secretKey  = $paypost['merSecertKey']; //秘钥
        $notify_sin = $paypost['sign'];
        $signpost['mchCode']      =  $paypost['mchCode'];
        $signpost['nonceStr']     =  $paypost['nonceStr'];
        ksort($signpost);
        $str ='';
        foreach($signpost as $k=>$v){
            $str.= $v.'&';
        }
        $str.=$secretKey;
        $sign = strtoupper(md5($str));
        if($sign != $notify_sin){
            write_log($sign,'alipaynotify1111');
            return;
        }

        $orderInfo=Db::name('order')->where(['order_chongfuzhifu'=>$paypost['outTradeNo']])->find();
        $order_goods=Db::name('order_goods')->where(['order_id'=>$orderInfo['order_id']])->find();
        if($orderInfo['pay_status']==0){
            $this->pay_uppers($orderInfo['order_id']);
            // $this->add_performance($order_id);
            $this->add_agent_performance($orderInfo['order_id']);
            //更新所有上级用户等级  根据业绩或者是推荐的人
            $this->upgrade_uppers($orderInfo['user_id']);
            //符合无限级的分佣
            $this->unlimit_bonus($orderInfo['user_id'],$orderInfo['order_id']);
        }
        $pay_status = [
            'pay_status'   => 1,
            'pay_time'     => time(),
        ];
        Db::name('order')->where(['order_chongfuzhifu' => $paypost['outTradeNo']])->update($pay_status);
        Db::table('goods_sku')->where('sku_id',$order_goods['sku_id'])->setInc('sales',1);
        Db::table('goods')->where('goods_id',$order_goods['goods_id'])->setInc('number_sales',1);
    }

    /**
     * 第三方微信支付
     * @return [type] [description]
     */
    public function wxpay(){
        $user_id      = $this->get_user_id();
        $order_id     = input('order_id');
        $update       = ['order_chongfuzhifu'=>$this->build_order_no()];
        Db::name('order')->where(['order_id' => $order_id])->update($update);
        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id,shipping_price,goods_price,order_chongfuzhifu')->find();//订单信息
        $member       = MemberModel::get($user_id);
        //验证是否本人的111
        if(!$order_info){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'订单不存在','data'=>'']);
        }

        if($order_info['pay_status'] == 1){
            $this->ajaxReturn(['status' => 301 , 'msg'=>'此订单，已完成支付!','data'=>'']);
        }
        $amount    = $order_info['order_amount'];

        $secretKey = "92ca23251eaa4c319f35fa4a978cd1e1";
        $timestamp = strval(time());
        $nonceStr  = strval($this->build_order_no());
        $money = 0.05*100;
		$paypost['amount']      = strval($money);//交易金额
		$paypost['outTradeNo']  = strval($order_info['order_chongfuzhifu']);//外部订单号
		$paypost['currency']    = 'CNY'; //标价币种
		$paypost['payGateway']  = 'wxpay'; //支付网关
        $paypost['notifyUrl']   = 'http://cattle.zhifengwangluo.com/api/pay/sinopay_notify';//后台回调URL


        $paypost['subAppid']    = 'wx19ac5b21ebe2b173';//特约商户在微信开放平台申请的应用APPID

        $paypost['mchCode']     = "326609695";//商户编码，MSPAY开通商户时所填写的商户编码
        $paypost['timestamp']   = $timestamp;//时间戳，见参数规定中的时间戳
        $paypost['nonceStr']    = $nonceStr;//随机字符串，见参数规定中的随机字符串

        $signpost['mchCode']    = "326609695";//商户编码，MSPAY开通商户时所填写的商户编码
        $signpost['timestamp']  = $timestamp;//时间戳，见参数规定中的时间戳
        $signpost['nonceStr']   = $nonceStr;//随机字符串，见参数规定中的随机字符串
        ksort($paypost);
        $str ='';
        foreach($signpost as $k=>$v){
            // $str.= $k.'='.$v.'&';
            $str.= $v.'&';
        }

        $str.=$secretKey;
        $paypost['sign'] = strtoupper(md5($str));
        $url     = 'https://merchant.mspay.com.au/api-open/v1/createAPPOrder';
        $res     =  $this->request_post($url,$paypost);
        $res     = json_decode($res,true);
        $this->ajaxReturn(['status' => 200 , 'msg'=>'微信扫码地址','data'=>$res]);

    }

    /**
     * 第三方微信支付回调
     * @return [type] [description]
     */
    public function sinopay_notify(){
		$paypost    = file_get_contents("php://input");
        $paypost    = json_decode($paypost, true);
        write_log(var_export($paypost, true),'sinopay_notify');
		$secretKey  = $paypost['merSecertKey']; //秘钥
        $notify_sin = $paypost['sign'];
        $signpost['mchCode']      =  $paypost['mchCode'];
        $signpost['nonceStr']     =  $paypost['nonceStr'];
        ksort($signpost);
        $str ='';
        foreach($signpost as $k=>$v){
            $str.= $v.'&';
        }
        $str.=$secretKey;
        $sign = strtoupper(md5($str));
        if($sign != $notify_sin){
            write_log($sign,'sinopay_notify1111');
            return;
        }

        $orderInfo=Db::name('order')->where(['order_chongfuzhifu'=>$paypost['outTradeNo']])->find();
        $order_goods=Db::name('order_goods')->where(['order_id'=>$orderInfo['order_id']])->find();
        if($orderInfo['pay_status']==0){
            $this->pay_uppers($orderInfo['order_id']);
            // $this->add_performance($order_id);
            $this->add_agent_performance($orderInfo['order_id']);
            //更新所有上级用户等级  根据业绩或者是推荐的人
            $this->upgrade_uppers($orderInfo['user_id']);
            //符合无限级的分佣
            $this->unlimit_bonus($orderInfo['user_id'],$orderInfo['order_id']);
        }
        $pay_status = [
            'pay_status'   => 1,
            'pay_time'     => time(),
        ];
        Db::name('order')->where(['order_chongfuzhifu' => $paypost['outTradeNo']])->update($pay_status);
        Db::table('goods_sku')->where('sku_id',$order_goods['sku_id'])->setInc('sales',1);
        Db::table('goods')->where('goods_id',$order_goods['goods_id'])->setInc('number_sales',1);
    }
}
