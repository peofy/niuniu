<?php
namespace app\index\controller;
use think\Controller;
use think\Verify;
use think\Db;
use think\Request;//tp5需要引入
use app\common\logic\UsersLogic;

class Reg extends Controller
{
    // public function _initialize()
    // {
       
       
    // }

    public function index()
    {
        if (\think\Request::instance()->isMobile()) {
            $this->redirect('mobile_index');
        }
        $list  = Db::table('official_website')->where("type",2)->order("sort desc,id desc")->select();
        $logos = Db::table('official_website')->where("type",1)->find();
        $logo = $logos['img'];
        $request = Request::instance();
        $domain=$request->domain();

        foreach($list as $key=>$val){
            if(!empty($val['img'])){
                $list[$key]['img'] = str_replace('\\', '/',$val['img'] );
            }
            if(!empty($val['content_img'])){
                // $list[$key]['content_img'] = $domain.$val['content_img'];
                $list[$key]['content_img'] = str_replace('\\', '/',$val['content_img'] );
            }

        }
        $this->assign('list', $list);
        $this->assign('logo', $logo);
        return $this->fetch();
    }


    public function register()
    {
        $uid=input('inviteCode');
        $realname=Db::name('member')->where(['id'=>$uid])->value('realname');
        return $this->fetch('',[
            'uid'=>$uid,
            'realname'=>$realname
        ]);
    }


    public function app()
    {
        $site=Db::name('site')->find();
        $server_host = 'http://'.$_SERVER['HTTP_HOST'];
        $showTip = 0;
        if(isset($site['ios_url'])&&strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            $down_url=isset($site['ios_url'])?$site['ios_url']:''; 
        }else if(isset($site['android_url'])&&strpos($_SERVER['HTTP_USER_AGENT'], 'Android')){
            $down_url = $site['android_url'];
            (strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && strpos($_SERVER['HTTP_USER_AGENT'], 'Android')) && $showTip = 1;
        }else{
            $down_url='';
        }
        $this->assign('showTip' , $showTip);
        $this->assign('down_url' , $down_url);
        return $this->fetch();
    }

 
    /**
     * APP下载地址, 如果APP不存在则显示WAP端地址
     * @return \think\mixed
     */
    public function app_down(){

        $server_host = 'http://'.$_SERVER['HTTP_HOST'];
        $showTip = false;
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            //苹果:直接指向AppStore下载
            // $down_url = tpCache('ios.app_path');
        }else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')){
            // 安卓:需要拼接下载地址
            $down_url = $server_host.'/'.tpCache('android.app_path');
            //如果是安卓手机微信打开, 则显示"其他浏览器打开"提示
            (strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && strpos($_SERVER['HTTP_USER_AGENT'], 'Android')) && $showTip = true;
        }

        // $wap_url = $server_host.'/Mobile';
        /*  echo "down_url : ".$down_url;
         echo "wap_url : ".wap_url;
         echo "<br/>showTip : ".$showTip; */
        $this->assign('showTip' , $showTip);
        $this->assign('down_url' , $down_url);
        // $this->assign('wap_url' , $wap_url);
        return $this->fetch();
    }

    










}
