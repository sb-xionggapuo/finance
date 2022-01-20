<?php


namespace app\web\controller;


use app\user\model\UserModel;
use cmf\controller\HomeBaseController;

class LoginController extends HomeBaseController
{
    public function index(){
        if (cmf_get_current_user()){
            if ($this->request->isAjax()){
                $this->error("用户已经登录",url("index/index"));
            }else{
                $this->redirect(url("index/index"));
            }
        }
        return $this->fetch();
    }


    public function login(){
        $params = $this->request->param();
        if (empty($params['username']))$this->error("用户名不能为空");
        if (empty($params['password']))$this->error("密码不能为空");
        $user = UserModel::where(['user_login'=>$params['username']])->find();
        if (empty($user))$this->error("用户名不存在");
        $user = $user->toArray();
        if (!cmf_compare_password($params['password'],$user['user_pass']))$this->error("密码错误");
        //更新前台用户信息
        cmf_update_current_user($user);
        $this->success("登录成功",url("index/index"));
    }
}