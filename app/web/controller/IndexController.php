<?php


namespace app\web\controller;


use app\user\model\UserModel;
use app\web\model\Finance;
use cmf\controller\UserBaseController;
use think\db\Query;

class IndexController extends UserBaseController
{
    public function index(){
        $params = $this->request->param();
        $user = cmf_get_current_user();
        $query = Finance::where(['user_id'=>$user['id']]);
        if (!empty($params['category_id'])){
            $query = $query->where(['category_id'=>$params['category_id']]);
        }
        if (!empty($params['start_time'])){
            $params['start_time'] = date("Y-m-d 00:00:00",strtotime($params['start_time']));
        }else{ //默认本月开始时间
            if(date('d')<=15){//如果今天小于等于15号就查询上个月的  月份要-1
                if(date('m')==1){ //如果月份等于1
                    $year = (int)(date('Y'))-1;
                    $params['start_time'] = date("$year-12-16 00:00:00",time());
                }else{
                    $month = (int)(date('m'))-1;
                    $params['start_time'] = date("Y-$month-16 00:00:00",time());
                }
            }else{//如果今天大于15号 那就算下个月的 月份不用-1
                $params['start_time'] = date("Y-m-16 00:00:00",time());
            }
        }
        $query = $query->where("create_time",">=",$params['start_time']);
        if (!empty($params['end_time'])){
            $params['end_time'] = date("Y-m-d 23:59:59",strtotime($params['end_time']));
        }else{//默认本月结束时间
            if (date('d')<=15) { //结束时间就是这月的15号
                $params['end_time'] = date("Y-m-15 23:59:59");
                // code...
            }else{ //结束时间就是下个月的15号
            $month = (int)(date('m'));
            $year = (int)(date('Y'));
                if ($month ==12) { //如果十二月份那就是明年1月份结束
                    // code...
                    $year = $year+1;
                    $params['end_time'] = date("$year-1-15 23:59:59");
                }else{
                    $month  = $month+1;
                    $params['end_time'] = date("Y-$month-15 23:59:59");
                }
            }
        }
        $query = $query->where("create_time","<=",$params['end_time']);
        //支出统计
        $popMoney = Finance::where(function (Query $query) use ($params,$user){
            $query->where(['user_id'=>$user['id']]);
            if (!empty($params['category_id'])){
                $query->where(['category_id'=>$params['category_id']]);
            }
            if (!empty($params['start_time'])){
                $query->where("create_time",">=",$params['start_time']);
            }
            if (!empty($params['end_time'])){
                $query->where("create_time","<=",$params['end_time']);
            }
        })->field("sum(money) as total")->where('category_id',"not in",[7,8])->find();
        if (empty($popMoney)){
            $popMoney = 0;
        }else{
            $popMoney = $popMoney->toArray()['total'];
        }
        //收入统计
        $pushMoney = Finance::where(function (Query $query) use ($params,$user){
            $query->where(['user_id'=>$user['id']]);
            if (!empty($params['category_id'])){
                $query->where(['category_id'=>$params['category_id']]);
            }
            if (!empty($params['start_time'])){
                $query->where("create_time",">=",$params['start_time']);
            }
            if (!empty($params['end_time'])){
                $query->where("create_time","<=",$params['end_time']);
            }
        })->field("sum(money) as total")->where('category_id',"in",[7,8])->find();
        if (empty($pushMoney)){
            $pushMoney = 0;
        }else{
            $pushMoney = $pushMoney->toArray()['total'];
        }
        $finance = $query->order(['id'=>"desc"])->paginate([
           "query"=>[
               'category_id'=>$params['category_id']??0,
               "start_time"=>$params['start_time']??"",
               "end_time"=>$params['end_time']??""
           ],
            "list_rows"=>15
        ]);
        $categorys = config("app.categorys");
        $categorys = array_column($categorys,null,"id");
        $datas = [];
        foreach ($categorys as $key=>$value){
            $datas[$key]['name'] = $value['name'];
            $money  =  Finance::where(function (Query $query) use ($params,$user){
                $query->where(['user_id'=>$user['id']]);
                if (!empty($params['start_time'])){
                    $query->where("create_time",">=",$params['start_time']);
                }
                if (!empty($params['end_time'])){
                    $query->where("create_time","<=",$params['end_time']);
                }
            })->field("sum(money) as total")->where('category_id',"=",$value['id'])->find();
            if (empty($money)){
                $datas[$key]['money'] = 0;
            }else{
                $datas[$key]['money'] = $money->toArray()['total'];
            }
        }
        return $this->fetch("index",[
            "categorys"=>$categorys,
            "datas"=>$finance->items(),
            "page"=>$finance->render(),
            "category_id"=>$params['category_id']??0,
            "start_time"=>$params['start_time']??"",
            "end_time"=>$params['end_time']??"",
            "popMoney"=>$popMoney,
            "pushMoney"=>$pushMoney,
            "cates"=>$datas
        ]);
    }


    public function add(){
        if ($this->request->isPost()){
            $params = $this->request->param();
            if (empty($params['category_id']))$this->error("分类不能为空");
            if (empty($params['money']))$this->error("金额不能为空");
            if (!isset($params['remark']))$this->error("备注不能为空");
            $params['remark'] = trim($params['remark']);
            if (empty($params['remark']))$this->error("备注不能为空");
            if ((int)$params['money']<0)$this->error("金额不能小于0");
            $user = cmf_get_current_user();
            $params['create_time'] = date("Y-m-d H:i:s");
            $params['user_id'] = $user['id'];
            $cuser = cmf_get_current_user();
            if (in_array($params['category_id'],[7,8])){//这里面是+
                $params['after_balance'] = $cuser['balance']+$params['money'];
            }else{
                $params['after_balance'] = $cuser['balance']-$params['money'];
            }
            $usermodel = new UserModel();
            $usermodel->where(['id'=>$cuser['id']])->update(['balance'=>$params['after_balance']]);
            $model = new Finance($params);
            $model->save()?$this->success("新增成功"):$this->error("新增失败");
        }
        $categorys = config("app.categorys");
        $categorys = array_column($categorys,null,"id");
        return $this->fetch("add",[
           "categorys"=>$categorys
        ]);
    }

    public function edit(){
        if ($this->request->isPost()){
            $params = $this->request->param();
            if (empty($params['category_id']))$this->error("分类不能为空");
            if (empty($params['money']))$this->error("金额不能为空");
            if (!isset($params['remark']))$this->error("备注不能为空");
            $params['remark'] = trim($params['remark']);
            if (empty($params['remark']))$this->error("备注不能为空");
            if ((int)$params['money']<0)$this->error("金额不能小于0");

            Finance::where(['id'=>$params['id']])->update($params);
            $this->success("修改成功");
        }
        $id = $this->request->param("id",0);
        $finance = Finance::find($id);
        $categorys = config("app.categorys");
        $categorys = array_column($categorys,null,"id");
        return $this->fetch("edit",[
            "categorys" =>  $categorys,
            "finance"        =>  $finance
        ]);
    }
}