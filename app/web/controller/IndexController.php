<?php


namespace app\web\controller;


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
            $query = $query->where("create_time",">=",$params['start_time']);
        }
        if (!empty($params['end_time'])){
            $query = $query->where("create_time","<=",$params['end_time']);
        }
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
           "query"=>['category_id'=>$params['category_id']??0],
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