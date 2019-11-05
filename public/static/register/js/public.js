    
var stateUrl = true;
/*返回 按钮*/
function returnFun(){
	if(!stateUrl){
		return false;
	}
	/**当前需要跳转的方式 */
	var return_url = $('.lb_headWrap .lb_headWrap_return').attr('data-num');
	/*返回上一页*/
	if(return_url === '1' || return_url == undefined ){
		window.history.back();
		console.log("返回上一页");
	}else {
		/*页面跳转*/
		window.location.href = return_url;
		console.log("页面跳转");
	}
	return false;
	
}

/*页面跳转*/
function pageJump(_url){
	/*页面跳转*/
    window.location.href = _url;
}
/**获取焦点=> 隐藏元素
 * 参数1: 获取焦点的jQuery元素;
 * 参数2: 隐藏的jQuery元素;
 * **/
function focus_fun(_ele,_hideBox){
	_ele.focus(function(){
		_hideBox.hide();
	})
}
/**失去焦点=> 隐藏元素
 * 参数1: 获取焦点的jQuery元素;
 * 参数2: 隐藏的jQuery元素;
 * **/
function blur_fun(_ele,_hideBox){
	_ele.blur(function(){
		_hideBox.show();
	})
}