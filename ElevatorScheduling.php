<?php
/**
* ELevator类继承了Thread类，是针对每个电梯的抽象类
* Elevator类根据当前该电梯的状态计算出对于特定任务的FS值
*/
class Elevator extends Thread{
    private $CurPos;
    private $TaskQueue;
    private $TaskTarget;
    private $TaskDirection;
    private $FirstServe;
    
    function __construct($CurPos, $TaskQueue, $TaskTarget){
        $this->CurPos = $CurPos;
        $this->TaskQueue = $TaskQueue;
        $this->TaskTarget = $TaskTarget;
        $this->TaskDirection = $TaskTarget > 0 ? 1 : -1;
    }

    public function run(){
        $N = 20;
        $d = abs($this->CurPos - abs($this->TaskTarget));
        if($this->TaskQueue[0] != 0){
            $ElevatorDirection = $this->GetDirection($this->CurPos, abs((int)$this->TaskQueue[0]));
            $ElevatorToTask = $this->GetDirection($this->CurPos, abs($this->TaskTarget));
            if($ElevatorDirection == $this->TaskDirection && $ElevatorDirection == $ElevatorToTask){
                $this->FirstServe = $N + 2 -$d;
            }elseif($ElevatorDirection == -$this->TaskDirection && $ElevatorDirection == $ElevatorToTask){
                $this->FirstServe = $N + 1 -$d;
            }else{
                $this->FirstServe = 1;
            }
        }else{
            $this->FirstServe = $N + 1 - $d;
        }
        //将该任务插到任务队列中去
        $this->InsertTaskQueue();
    }
    
    //任务插入分配方法
    private function InsertTaskQueue(){
        $BeginPos = 0; $EndPos = 0;
        //插入原则是：若待插入任务在某一任务区间内，则插入到相应区间，反之则插入到队列末尾
        for($i = 0; (int)$this->TaskQueue[$i] != 0; $i++){
            if($i == 0){
                $BeginPos = $this->CurPos;
                $EndPos = abs((int)$this->TaskQueue[$i]); 
            }else{
                $BeginPos = abs((int)$this->TaskQueue[$i - 1]);
                $EndPos = abs((int)$this->TaskQueue[$i]);
            }
            if($this->GetDirection($BeginPos, $EndPos) == $this->TaskDirection && $this->CheckRoutine($BeginPos, $EndPos, abs($this->TaskTarget))){
                if($i == 0){ 
                    array_splice($this->TaskQueue, 0, 0, array(0 => (string)$this->TaskTarget));
                }else{
                    array_splice($this->TaskQueue, $i - 1, 0, array(0 => (string)$this->TaskTarget));
                }
                return;
            }
        }
        //插入到队列末尾（忽略占位符的影响）
        array_splice($this->TaskQueue, $i, 0, array(0 => (string)$this->TaskTarget));
    }
    //返回调度完成后的任务队列
    public function GetRetVal(){
        return $this->TaskQueue;
    }
    //返回FS值
    public function GetFirstServe(){
        return $this->FirstServe;
    }
    //获取运行方向
    private function GetDirection($BeginPos, $EndPos){
        $TempDis = $EndPos - $BeginPos;
        if($TempDis > 0){
            return 1;
        }elseif ($TempDis < 0) {
            return -1;
        }else{
            return 0;
        }
    }
    //检查某一任务是否在给定的区间内
    private function CheckRoutine($BeginPos, $EndPos, $Pos){
        if($BeginPos <= $Pos && $EndPos >= $Pos || $BeginPos >= $Pos && $EndPos <= $Pos){
            return TRUE;
        }else{
            return FALSE;
        }
    }
}

/**
* ElevatorScheduling类是电梯调度类，封装了电梯的调度函数
*/
class ElevatorScheduling {
    private $ElevatorCurrentPos;
    private $OuterTaskQueue;
    private $InnerTaskQueue;

    //构造函数接收来自前端的数据
    function __construct() {
        $this->ElevatorCurrentPos = $_POST['ElevatorCurrentPos'];
        $this->OuterTaskQueue = $_POST['OuterTaskQueue'];
        $this->InnerTaskQueue = $_POST['InnerTaskQueue'];
    }
    
    //该函数针对每部电梯实例化一个Elevator类，并创建线程处理
    public function GetElevatorSchedulingRes(){
        for($i = 0; $i < count($this->OuterTaskQueue); $i++){
            $ElevatorArray = array();
            for($j = 0; $j < count($this->InnerTaskQueue); $j++){
                $ElevatorArray[$j] = new Elevator((int)$this->ElevatorCurrentPos[$j], $this->InnerTaskQueue[$j], (int)$this->OuterTaskQueue[$i]);
                //异步执行Elevator类中的run方法
                $ElevatorArray[$j]->start();
            }   
            for($j = 0; $j < count($ElevatorArray); $j++){
                //当前主线程等待该线程执行完毕
                $ElevatorArray[$j]->join();
            }
            //选择出最优的调度分配方案
            $this->SelectBestElevator($ElevatorArray);
        }
        //向前端返回调度结果
        echo json_encode(array("ElevatorTaskQueue" => $this->InnerTaskQueue));
    }

    //选择出一个FS值最高的调度方案
    private function SelectBestElevator($ElevatorArray){
        $index = -1;
        $FirstServe = -1;
        for($i = 0; $i < count($ElevatorArray); $i++){
            if($ElevatorArray[$i]->GetFirstServe() > $FirstServe){
                $FirstServe = $ElevatorArray[$i]->GetFirstServe();
                $index = $i;
            }
        }
        $this->InnerTaskQueue[$index] = $ElevatorArray[$index]->GetRetVal();
    }
}

//实例化ElevatroScheduling类，并调用GetElevatorSchedulingRes成员函数
$ElevatorSchedulingObj = new ElevatorScheduling();
$ElevatorSchedulingObj->GetElevatorSchedulingRes();
?>