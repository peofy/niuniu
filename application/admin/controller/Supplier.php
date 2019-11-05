<?php
namespace app\admin\controller;

use think\Db;
use app\common\model\Order as OrderModel;
use app\common\model\Member as MemberModel;
use app\common\model\MemberWithdrawal;
use think\Request;
/**
 * 首页
 */
class Supplier extends Common
{

    /***
     * 提现列表
     */

    public function supplier_application(){
        $where = array();
        $status  = input('status');
        $kw      = input('kw');
        if($status != 0){
            $where['w.status'] =  $status;
        }

        if(!empty($kw)){
            is_numeric($kw)?$where['m.mobile'] = ['like', "%{$kw}%"]:$where['m.realname'] = ['like', "%{$kw}%"];
        }

        
        $list  = Db::name('supplier')->alias('w')
            ->field('w.id,m.id as mid ,w.name,w.moblie,m.groupid , m.level , m.avatar ,  w.content  ,  m.realname , m.mobile ,w.createtime ,w.checktime ,w.status')
            ->join("member m",'m.id = w.user_id','LEFT')
            ->where($where)
            ->order('m.createtime DESC')
            ->paginate(10, false, ['query' => $where]);

        $this->assign('list', $list);
        $this->assign('meta_title', '申请供应商');
        return $this->fetch('supplier/supplier_application',[
            'status'       => $status,
            'kw'           => $kw,
            'list'         => $list,
            'meta_title'   => '申请供应商',
        ]);
    }

    /***
     * 提现审批操作
     */

    public function withdrawal(){

        $id     = input('id');
        $status = input('status1');
        $content = input('content','');
        $info = MemberWithdrawal::where(['id' => $id])->find();
        if($status == 2){
            //调用支付宝转账接口
            $this->error('支付宝配置未对接');
        }elseif($status == -1){
            //审核拒绝

            // 启动事务
            Db::startTrans();
            $update = [
                'status' => $status,
                'checktime' => time(),
                'content'   => $content,
            ];

            $res = MemberWithdrawal::where(['id' => $id])->update($update);

            if($res == false){
                Db::rollback();
                $this->error('拒绝失败');
            }
            //余额返回
            $res1 = MemberModel::where(['id' => $info['user_id']])->setInc('remainder_money',$info['money']);
            if($res1 == false){
                Db::rollback();
                $this->error('拒绝失败');
            }
           
            Db::commit();
            $this->success('拒绝成功', url('supplier/supplier_application'));

        }

    }


    //供应商审核
    public function supplier_check(){
        $status=input('status');
        $id=input('id');
        if(request()->isPost()){
           $res = Db::name('supplier')->where(['id'=>$id])->update(['status'=>$status,'checktime'=>time()]);
            if($res!==false){
                echo json_encode(['status'=>200,'message'=>'操作成功']);die;
            }else{
                echo json_encode(['status'=>301,'message'=>'操作失败']);die;
            }
        }
    }

    //不通过
    public function supplier_no(){
        $status=input('status1');
        $id=input('id');
        $content=input('content');
        if(request()->isPost()){
           $res = Db::name('supplier')->where(['id'=>$id])->update(['status'=>$status,'content'=>$content,'checktime'=>time()]);
            if($res!==false){
                // echo json_encode(['status'=>200,'message'=>'操作成功']);die;
                $this->success("操作成功");
            }else{
                // echo json_encode(['status'=>301,'message'=>'操作失败']);die;
                $this->eroor("操作失败");
            }
        }
    }



}