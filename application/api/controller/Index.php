<?php
/**
 * 用户API
 */
namespace app\api\controller;
use app\common\controller\ApiBase;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use think\Db;

class Index extends ApiBase
{

    public function changeTableVal()
    {
        $table = I('table'); // 表名
        $id_name = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field = I('field'); // 修改哪个字段
        $value = I('value'); // 修改字段值
        $res = M($table)->where([$id_name => $id_value])->update(array($field => $value));
        if ($res) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '无修改']);
        }
        // 根据条件保存修改的数据
    }

    public function changeTableCommend()
    {
        $table = I('table'); // 表名
        $id_name = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field = I('field'); // 修改哪个字段
        $value = I('value'); // 修改字段值

        $res = M($table)->where([$id_name => $id_value])->update(array($field => $value));
        if ($res) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '无修改']);
        }
    }
    /**
     * @api {GET} /index/index 首页
     * @apiGroup index
     * @apiVersion 1.0.0
     *
     * @apiParamExample {json} 请求数据:
     * {
     *
     * }
     * @apiSuccessExample {json} 返回数据：
     * //正确返回结果
     * {
     *       "status": 200,
     *       "msg": "获取成功",
     *       "data": {
     *           "banners": [
     *           {
     *               "picture": "http://api.retail.com\\uploads\\fixed_picture\\20190529\\bce5780d314bb3bfd3921ffefc77fcdd.jpeg",
     *               "title": "个人中心个人资料和设置",
     *               "url": "www.cctvhong.com"
     *           },
     *           {
     *               "picture": "http://api.retail.com\\uploads\\fixed_picture\\20190529\\94cbe33d1e15a5ebdd92cd0e3a4f4f19.jpeg",
     *               "title": "13.2 我的钱包-提现记录",
     *               "url": "www.ceshi.com"
     *           },
     *           {
     *               "picture": "http://api.retail.com\\uploads\\fixed_picture\\20190529\\414eac4f30c011288ae42e822cb637cc.jpeg",
     *               "title": "钱包转换",
     *               "url": "www.ceshi.com"
     *           }
     *           ],banner轮播图
     *           "announce": [
     *
     *           ],公告
     *           "hot_goods": [
     *           {
     *               "goods_id": 39,
     *               "goods_name": "本草",
     *               "img": "http://zfwl.zhifengwangluo.c3w.cc/upload/images/goods/20190514155782540787289.png",
     *               "price": "200.00",
     *               "original_price": "250.00"
     *           },
     *           {
     *               "goods_id": 18,
     *               "goods_name": "美的（Midea） 三门冰箱 风冷无霜家",
     *               "img": "http://zfwl.zhifengwangluo.c3w.cc/upload/images/goods/20190514155782540787289.png",
     *               "price": "2188.00",
     *               "original_price": "2588.00"
     *           }
     *           ],热门商品
     *           "recommend_goods": {
     *           "total": 2,
     *           "per_page": 4,
     *           "current_page": 1,
     *           "last_page": 1,
     *           "data": [
     *               {
     *               "goods_id": 39,
     *               "goods_name": "本草",
     *               "img": "http://zfwl.zhifengwangluo.c3w.cc/upload/images/goods/20190514155782540787289.png",
     *               "price": "200.00",
     *               "original_price": "250.00"
     *               },
     *               {
     *               "goods_id": 36,
     *               "goods_name": "美的（Midea） 三门冰箱 ",
     *               "img": "http://zfwl.zhifengwangluo.c3w.cc/upload/images/goods/20190514155782540787289.png",
     *               "price": "50.00",
     *               "original_price": "40.00"
     *               }
     *           ]
     *           }推荐商品
     *       }
     *       }
     * //错误返回结果
     * 无
     */
    public function index()
    {

//        一级分类导航
        $catenav1 = Db::table('category')->field('cat_id,cat_name')->where(['is_show' =>1 ,'level'=>1])->limit(4)->select();

        //轮播图
        $banners=Db::name('advertisement')->field('picture,title,url')->where(['type'=>1,'state'=>1,'location'=>1])->order('sort','desc')->limit(3)->select();
        if($banners){
            foreach($banners as $bk=>$bv){
                $banners[$bk]['picture']=SITE_URL.'/public'.$bv['picture'];
            }
        }
        //二级分类

        $cat_id =  Db::table('category')->field('cat_id')->where(['cat_name' =>'推荐'])->find()['cat_id'];
        $catenav2 = Db::table('category')->field('cat_id,cat_name,img')->where(['is_show' =>1 ,'level'=>2,'pid'=>$cat_id])->limit(8)->select();
        foreach ($catenav2 as $key=>$value){
            $catenav2[$key]['img'] = SITE_URL.'/public/upload/images/'.$catenav2[$key]['img'];
        }

//        //公告
//        $announce=Db::name('announce')->field('id,title,urllink as link,desc')->where(['status'=>1])->order('create_time','desc')->limit(3)->select();


        //自定义分类广告
        $selfnav = Db::table('catenav')->field('title,image,url')->where(['status' => ['<>', -1]])->limit(1)->select();
        for ($i = 0; $i < count($selfnav); $i++) {
            $selfnav[$i]['image'] = SITE_URL . '/public/' . $selfnav[$i]['image'];
        }


//        //购买获取推荐专区
//        $push_goods = Db::table('goods')->alias('g')
//                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
//                ->where('gi.main',1)
//                ->where('g.is_show',1)
//                ->where('g.is_del',0)
//                ->where('g.is_push',1)
//                ->where('FIND_IN_SET(3,g.goods_attr)')
//                ->order('g.goods_id DESC')
//                ->field('g.goods_id,goods_name,gi.picture img,price,original_price')
//                ->limit(4)
//                ->select();
//
//        if($push_goods){
//            foreach($push_goods as $key=>&$value){
//                $value['img'] = Config('c_pub.apiimg') .$value['img'];
//            }
//        }
        //优选商品
        $youxuan_goods = Db::table('youxuan')
            ->where('location',1)
            ->where('status',1)
            ->select();

        if($youxuan_goods){
//            $recommend_goods = $recommend_goods->all();
            foreach($youxuan_goods as $key=>&$value){
                $value['image'] = SITE_URL.'/public/'.$value['image'];
            }
        }
        $goods_gift = Db::table('goods')->alias('g')
                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                ->where('gi.main',1)
                ->where('g.is_show',1)
                ->where('g.is_del',0)
                ->order('g.goods_id DESC')
                ->field('g.goods_id,goods_name,gi.picture img,g.desc,price,original_price')
                ->paginate(3);

              

        if($goods_gift){
            $goods_gift = $goods_gift->all();
            foreach($goods_gift as $key=>&$value){
                $value['img'] = SITE_URL.'/public/upload/images/'.$value['img'];
                $value['rmb_price']=$value['price']*$this->aus_tormb;
            }
        }

        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>['catenav1'=>$catenav1,'banners'=>$banners,'catenav2'=>$catenav2,'selfnav'=>$selfnav,'buy_now'=>$goods_gift,'youxuan_goods'=>$youxuan_goods,]]);
    }

    //列表页
    public function list_page()
    {
        $cat_id = input('cat_id/s', '');
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
        $goods = Db::table('goods')->alias('g')
            ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
            ->where('gi.main',1)
            ->where('g.is_show',1)
            ->where('g.is_del',0)
            ->where('g.cat_id1',$cat_id)
            ->order('g.goods_id DESC')
            ->field('g.goods_id,goods_name,gi.picture img,price,original_price')
            ->paginate($pageArray)
            ->toArray();
        if($goods){
            foreach($goods['data'] as $key=>&$value){
                $value['img'] = Config('c_pub.apiimg') .$value['img'];
                $value['rmb_price']=$value['price']*$this->aus_tormb;
            }
        }
        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>['goods'=>$goods]]);
    }



    //首页兴农扶贫
    public function indexfarm()
    {

//        一级分类导航
        $catenav1 = Db::table('category')->field('cat_id,cat_name')->where(['is_show' =>1 ])->limit(4)->select();

        //轮播图
        $banners=Db::name('advertisement')->field('picture,title,url')->where(['type'=>0,'state'=>1,'location'=>2])->order('sort','desc')->limit(3)->select();
        if($banners){
            foreach($banners as $bk=>$bv){
                $banners[$bk]['picture']=SITE_URL. '/public/' .$bv['picture'];
            }
        }


        //公告
//        $announce=Db::name('announce')->field('id,title,urllink as link,desc')->where(['status'=>1])->order('create_time','desc')->limit(3)->select();
        //二级分类

        $cat_id =  Db::table('category')->field('cat_id')->where(['cat_name' =>'兴农扶贫'])->find()['cat_id'];
        $catenav2 = Db::table('category')->field('cat_id,cat_name,img')->where(['is_show' =>1 ,'level'=>2,'pid'=>$cat_id])->limit(10)->select();
        foreach ($catenav2 as $key=>$value){
            $catenav2[$key]['img'] = SITE_URL.'/public/upload/images/'.$catenav2[$key]['img'];
        }

        //自定义分类广告
        $selfnav = Db::table('catenav')->field('title,image,url')->where(['status' => ['<>', -1],'location'=>2])->limit(4)->select();
        for ($i = 0; $i < count($selfnav); $i++) {
            $selfnav[$i]['image'] = SITE_URL.'/public/'.$selfnav[$i]['image'];
        }

        //购买获取推荐专区
        $push_goods = Db::table('goods')->alias('g')
            ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
            ->where('gi.main',1)
            ->where('g.is_show',1)
            ->where('g.is_del',0)
            ->where('g.is_push',1)
            ->where('FIND_IN_SET(3,g.goods_attr)')
            ->order('g.goods_id DESC')
            ->field('g.goods_id,goods_name,gi.picture img,g.desc,price,original_price')
            ->limit(4)
            ->select();

        if($push_goods){
            foreach($push_goods as $key=>&$value){
                $value['img'] = Config('c_pub.apiimg') .$value['img'];
                $value['rmb_price']=$value['price']*$this->aus_tormb;
            }
        }
        //优选商品
        $youxuan_goods = Db::table('youxuan')
            ->where('location',2)
            ->where('status',1)
            ->select();

        if($youxuan_goods){
//            $recommend_goods = $recommend_goods->all();
            foreach($youxuan_goods as $key=>&$value){
                $value['image'] = SITE_URL.'/public/'.$value['image'];
            }
        }

        $goods_gift = Db::table('goods')->alias('g')
            ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
            ->where('gi.main',1)
            ->where('g.is_show',1)
            ->where('g.is_del',0)
            ->where('g.is_gift',1)
            ->order('g.goods_id DESC')
            ->field('g.goods_id,goods_name,gi.picture img,g.desc,price,original_price')
            ->paginate(4);

        if($goods_gift){
            $goods_gift = $goods_gift->all();
            foreach($goods_gift as $key=>&$value){
                $value['img'] = SITE_URL.'/public/upload/images/'.$value['img'];
                $value['rmb_price']=$value['price']*$this->aus_tormb;
            }
        }

        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>['catenav1'=>$catenav1,'banners'=>$banners,'catenav2'=>$catenav2,'selfnav'=>$selfnav,'youxuan_goods'=>$youxuan_goods,'like'=>$goods_gift]]);
    }





    //首页进口
    public function index_import()
    {

       //一级分类导航
        $catenav1 = Db::table('category')->field('cat_id,cat_name')->where(['is_show' =>1 ])->limit(4)->select();

        //轮播图
        $banners=Db::name('advertisement')->field('picture,title,url')->where(['type'=>1,'state'=>1,'location'=>4])->order('sort','desc')->limit(3)->select();
        if($banners){
            foreach($banners as $bk=>$bv){
                $banners[$bk]['picture']=SITE_URL.'/public'.$bv['picture'];
            }
        }

        //二级分类

        $cat_id =  Db::table('category')->field('cat_id')->where(['cat_name' =>'进口货物'])->find()['cat_id'];
        $catenav2 = Db::table('category')->field('cat_id,cat_name,img')->where(['is_show' =>1 ,'level'=>2,'pid'=>$cat_id])->limit(8)->select();
        foreach ($catenav2 as $key=>$value){
            $catenav2[$key]['img'] = SITE_URL.'/public/upload/images/'.$catenav2[$key]['img'];
        }
        //公告
//        $announce=Db::name('announce')->field('id,title,urllink as link,desc')->where(['status'=>1])->order('create_time','desc')->limit(3)->select();


        //重磅推荐自定义广告
        $selfnav = Db::table('catenav')->field('title,image,url')->where(['status' => ['<>', -1],'location'=>4])->limit(4)->select();
        for ($i = 0; $i < count($selfnav); $i++) {
            $selfnav[$i]['image'] = SITE_URL . '/public/' . $selfnav[$i]['image'];
        }


        //进口商品
        $import = Db::table('goods')->alias('g')
            ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
            ->where('gi.main',1)
            ->where('g.is_show',1)
            ->where('g.is_del',0)
            ->where('g.is_import',1)
            ->where('FIND_IN_SET(3,g.goods_attr)')
            ->order('g.goods_id DESC')
            ->field('g.goods_id,goods_name,gi.picture img,g.desc,price,original_price')
            ->limit(4)
            ->select();

        if($import){
//            $recommend_goods = $recommend_goods->all();
            foreach($import as $key=>&$value){
                $value['img'] = SITE_URL.'/public/upload/images/'.$value['img'];
                $value['rmb_price']=$value['price']*$this->aus_tormb;
            }
        }

//        $goods_gift = Db::table('goods')->alias('g')
//            ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
//            ->where('gi.main',1)
//            ->where('g.is_show',1)
//            ->where('g.is_del',0)
//            ->where('g.is_gift',1)
//            ->order('g.goods_id DESC')
//            ->field('g.goods_id,goods_name,gi.picture img,g.desc,price,original_price')
//            ->paginate(4);
//
//        if($goods_gift){
//            $goods_gift = $goods_gift->all();
//            foreach($goods_gift as $key=>&$value){
//                $value['img'] = SITE_URL.'/public/upload/images/'.$value['img'];
//            }
//        }

        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>['catenav1'=>$catenav1,'banners'=>$banners,'catenav2'=>$catenav2,'selfnav'=>$selfnav,'jinkou'=>$import]]);
    }

    //首页食品酒水
    public function index_food_drink()
    {
        //一级分类导航
        $catenav1 = Db::table('category')->field('cat_id,cat_name')->where(['is_show' =>1 ])->limit(4)->select();

        //轮播图
        $banners=Db::name('advertisement')->field('picture,title,url')->where(['type'=>1,'state'=>1,'location'=>3])->order('sort','desc')->limit(3)->select();
        if($banners){
            foreach($banners as $bk=>$bv){
                $banners[$bk]['picture']=SITE_URL.'/public'.$bv['picture'];
            }
        }

        //二级分类

        $cat_id =  Db::table('category')->field('cat_id')->where(['cat_name' =>'食品酒水'])->find()['cat_id'];
        $catenav2 = Db::table('category')->field('cat_id,cat_name,img')->where(['is_show' =>1 ,'level'=>2,'pid'=>$cat_id])->limit(8)->select();
        foreach ($catenav2 as $key=>$value){
            $catenav2[$key]['img'] = SITE_URL.'/public/upload/images/'.$catenav2[$key]['img'];
        }
        //公告
//        $announce=Db::name('announce')->field('id,title,urllink as link,desc')->where(['status'=>1])->order('create_time','desc')->limit(3)->select();


        //重磅推荐自定义广告
        $selfnav = Db::table('catenav')->field('title,image,url')->where(['status' => ['<>', -1],'location'=>3])->limit(4)->select();
        for ($i = 0; $i < count($selfnav); $i++) {
            $selfnav[$i]['image'] = SITE_URL . '/public/' . $selfnav[$i]['image'];
        }


        //食品酒水
        $food_drink = Db::table('goods')->alias('g')
            ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
            ->where('gi.main',1)
            ->where('g.is_show',1)
            ->where('g.is_del',0)
            ->where('g.cat_id1',9)
            ->where('FIND_IN_SET(3,g.goods_attr)')
            ->order('g.goods_id DESC')
            ->field('g.goods_id,goods_name,gi.picture img,g.desc,price,original_price')
            ->limit(4)
            ->select();

        if($food_drink){
//            $recommend_goods = $recommend_goods->all();
            foreach($food_drink as $key=>&$value){
                $value['img'] = SITE_URL.'/public/upload/images/'.$value['img'];
                $value['rmb_price']=$value['price']*$this->aus_tormb;
            }
        }

//        $goods_gift = Db::table('goods')->alias('g')
//            ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
//            ->where('gi.main',1)
//            ->where('g.is_show',1)
//            ->where('g.is_del',0)
//            ->where('g.is_gift',1)
//            ->order('g.goods_id DESC')
//            ->field('g.goods_id,goods_name,gi.picture img,g.desc,price,original_price')
//            ->paginate(4);
//
//        if($goods_gift){
//            $goods_gift = $goods_gift->all();
//            foreach($goods_gift as $key=>&$value){
//                $value['img'] = SITE_URL.'/public/upload/images/'.$value['img'];
//            }
//        }

        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>['catenav1'=>$catenav1,'banners'=>$banners,'catenav2'=>$catenav2,'selfnav'=>$selfnav,'food_drink'=>$food_drink]]);
    }
    //公用二级分类
    public function cat2()
    {
        $cat_id = input('cat_id');
        $catenav = Db::table('category')->field('cat_id,cat_name,img')->where(['is_show' =>1 ,'level'=>2,'pid'=>$cat_id])->limit(8)->select();
        foreach ($catenav as $key=>$value){
            $catenav[$key]['img'] = SITE_URL.'/public/upload/images/'.$catenav[$key]['img'];
        }
        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>['catenav'=>$catenav]]);
    }

    //兴农扶贫二级分类
    public function cat()
    {
        $cat_id = input('cat_id');
        $catenav = Db::table('category')->field('cat_id,cat_name,img')->where(['is_show' =>1 ,'level'=>2,'pid'=>$cat_id])->limit(10)->select();
        foreach ($catenav as $key=>$value){
            $catenav[$key]['img'] = SITE_URL.'/public/upload/images/'.$catenav[$key]['img'];
        }
        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功','data'=>['catenav'=>$catenav]]);
    }

    /**
     * 上传身份证
     */
    public function updata_idcard_pic()
    {
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -1, 'msg' => '用户不存在', 'data' => '']);
        }
        $pic_front = input('pic_front');
        $pic_back = input('pic_back');
        $idname = input('name');
        $idcard = input('idcard');
        if (empty($pic_front)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '身份证正面没上传', 'data' => '']);
        }
        if (empty($pic_back)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '身份证背面没上传', 'data' => '']);
        }
        if (empty($idname)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请填写身份证姓名','data'=>'']);
        }
        if (empty($idcard)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请填写身份证号码','data'=>'']);
        }

        if(!check_idcard($idcard,$idname,$user_id)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'身份证号码和真实姓名不一致','data'=>'']);
        }

        if(empty($pic_front) && empty($pic_back)){
            $file = request()->file('pic_front');
            $save_url = UPLOAD_PATH.'idcard/' . date('Y', time()) . '/' . date('m-d', time());
            if($file) {
                // 移动到框架应用根目录/public/uploads/ 目录下
                $image_upload_limit_size = config('image_upload_limit_size');
                $info = $file->rule('uniqid')->validate(['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    // 输出 jpg
                    $comment_img = '/' . $save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->ajaxReturn(['status' =>-1,'msg' =>$pic_front->getError()]);
                }
                $pic_front =SITE_URL.$comment_img;
            }
            $files = request()->file('pic_back');
            $save_url = UPLOAD_PATH.'idcard/' . date('Y', time()) . '/' . date('m-d', time());
            if($files) {
                // 移动到框架应用根目录/public/uploads/ 目录下
                $image_upload_limit_size = config('image_upload_limit_size');
                $info = $files->rule('uniqid')->validate(['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    // 输出 jpg
                    $comment_img1 = '/' . $save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->ajaxReturn(['status' =>-1,'msg' =>$pic_back->getError()]);
                }
                $pic_back =SITE_URL.$comment_img1;
            }
            Db::name('member')->where(['id' => $user_id])->update(['pic_front' =>$pic_front ,'pic_back' =>$pic_back ,'idcard'=>$idcard,'name'=>$idname]);

            $this->ajaxReturn(['status'=>1,'msg'=>'上传成功','data'=>['pic_front' =>$pic_front ,'pic_back' =>$pic_back ,'idcard'=>$idcard,'name'=>$idname]]);
        }

//        if (explode('%',$pic_front)){
//            $pic_front = urldecode($pic_front);
//        }
//        if (explode('%',$pic_back)){
//            $pic_back = urldecode($pic_back);
//        }
        $saveNamefront       = request()->time().rand(0,99999) . '.png';
        $saveNameback       = request()->time().rand(0,99999) . '.png';
        $base64_pic_front  = explode(',', $pic_front);
        $base64_pic_back  = explode(',', $pic_back);
        $img_pic_front           = base64_decode($base64_pic_front[1]);
        $img_pic_back           = base64_decode($base64_pic_back [1]);
        //生成文件夹
        $names = "public/idcard_pic";
        $name  = "public/idcard_pic/" .date('Ymd',time());
        if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){
            mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
        }
        //保存图片到本地
        $r   = file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveNamefront,$img_pic_front);
        $rr   = file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveNameback,$img_pic_back);
        // if(!$r || !$rr){
        //     $this->ajaxReturn(['status'=>-2,'msg'=>'上传出错','data' =>'']);
        // }
        $pic_front = SITE_URL.'/'.'public\upload\images/'.$name.$saveNamefront;
        $pic_back = SITE_URL.'/'.'public\upload\images/'.$name.$saveNameback;
        Db::name('member')->where(['id' => $user_id])->update(['pic_front' =>$pic_front ,'pic_back' =>$pic_back ,'idcard'=>$idcard,'name'=>$idname]);

        $this->ajaxReturn(['status'=>1,'msg'=>'上传成功','data'=>['pic_front' => SITE_URL.'/'.'public\upload\images/'.$name.$saveNamefront,'pic_back' => SITE_URL.'/'.'public\upload\images/'.$name.$saveNameback,'idcard'=>$idcard,'name'=>$idname]]);

    }

    // 身份认证显示
    public function show_idcard(){
        $user_id=$this->get_user_id();
        $userinfo=Db::name('member')->where(['id'=>$user_id])->find();
        $data['pic_front']=$userinfo['pic_front'];
        $data['pic_back']=$userinfo['pic_back'];
        $data['name']=$userinfo['name'];
        $data['idcard']=$userinfo['idcard'];
        return $this->successResult($data);
    }
}
