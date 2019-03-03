<?php

// ユーザーの一覧

require_once(__DIR__ . '/../config/config.php');

// var_dump($_SESSION['me']);

$app = new MyApp\Controller\Index();

$app->run();

class Account{
    //出勤手当
    public static $attend_allowance = 200;
    //開店手当
    public static $open_allowance = 400;
    //閉店手当
    public static $close_allowance = 200;
    //拘束手当/時
    public static $work_hourly = 100;
    //残業手当/時
    public static $otr_hourly = 100;

    //家賃/日
    public static $rent = 4500;
    //家賃/時間帯
    public $rent_t = 0;
    //投資分/日
    public static $initial_cost = 1800;
    //投資分/時間帯
    public $initial_cost_t = 0;

    //売上
    public $earning = 0;
    //出勤時刻
    public $attend_time = 0;
    //退勤時刻
    public $leaving_time = 0;
    //雑務
    public $tasks = [];

    //拘束時間
    public $work_time = 0;
    //残業手当
    public $min_reward = 0;
    //残業時間
    public $overtime = 0;
    //残業手当
    public $overtime_reward = 0;

    //売上毎時
    public $earning_hourly = 0;
    //対応手当倍率
    public $earnings_ratio = 0.1;
    //店番報酬(保証外)
    public $reward_raw = 0;
    //店番報酬(保証内)
    public $reward = 0;
    //雑務報酬
    public $task_reward = 0;
    //当日報酬
    public $day_reward = 0;


    public function __construct($earning,$attend_time,$leaving_time,$tasks){
        $this->set_parameter($earning,$attend_time,$leaving_time,$tasks);
        $this->calc_parameter();
        $this->calc_reward0();
        $this->calc_reward1();
        $this->calc_cost();
    }

    //売上,出勤時刻,退勤時刻をセット
    public function set_parameter($earning,$attend_time,$leaving_time,$tasks){
        $this->earning = $earning;
        $this->attend_time = $attend_time;
        $this->leaving_time = $leaving_time;
        $this->tasks = $tasks;
    }

    //拘束時間,売上毎時,対応手当倍率を算出
    public function calc_parameter(){
        //拘束時間
        $this->work_time = (strtotime($this->leaving_time) - strtotime($this->attend_time))/3600;
        //売上毎時
        if($this->work_time == 0){
            $this->earning_hourly = 0;
        }else{
            $this->earning_hourly = $this->earning/$this->work_time;
        }
        //対応手当倍率, y=0.025(売上毎時/500)+0.05, 0.1<=y<=0.25
        $this->earnings_ratio = ($this->earning_hourly/500)*0.025 + 0.05;
//        $this->earnings_ratio = (int)($this->earning_hourly/500)*0.025 + 0.075;
//        $this->earnings_ratio = ($this->earning_hourly/500)*0.025 + 0.075;
        if($this->earnings_ratio<0.125){
            $this->earnings_ratio = 0.125;
        }
        if($this->earnings_ratio>0.2){
            $this->earnings_ratio = 0.2;
        }
    }

    //報酬計算
    public function calc_reward0(){
        $this->reward = 0;
        //出勤手当
        $this->reward += self::$attend_allowance;
        //開店手当
        if($this->attend_time == '10:00'){
            $this->reward += self::$open_allowance;
        }
        //閉店手当
        if($this->leaving_time == '22:00'){
            $this->reward += self::$close_allowance;
        }
        //拘束手当
        $this->reward += $this->work_time * self::$work_hourly;

        //対応手当
        $this->reward += $this->earning * $this->earnings_ratio;
        $this->reward_raw += $this->reward;

        //最低報酬
        $this->min_reward = $this->work_time*200;
         if($this->reward < $this->min_reward){
            $this->reward = $this->min_reward;
        }

//        //残業時間
//        if($this->work_time>6){
//            $this->overtime += $this->work_time - 6;
//            $this->overtime_reward += $this->overtime * self::$otr_hourly;
//        }

        //10の位で二捨三入(2倍してから四捨五入して2で割る)
        $this->reward_raw = round($this->reward_raw*2,-2)/2;
        $this->reward = round($this->reward*2,-2)/2;

        $this->day_reward += $this->reward;
        $this->day_reward += $this->overtime_reward;

        return $this->reward;
    }

    //雑務報酬
    public function calc_reward1(){
        foreach ($this->tasks as $id => $item){
            if($item['value'] == 'on'){
                $this->task_reward += $item['reward'];
                $this->day_reward += $item['reward'];
            }
        }
    }


    //その他コストの算出
    public function calc_cost(){
        $this->rent_t = self::$rent * $this->work_time / 12;
        $this->initial_cost_t = self::$initial_cost * $this->work_time / 12;
    }
}



$earning = (isset($_POST['earning']) AND is_numeric($_POST['earning']))? $_POST['earning'] : 0;

$time_from = isset($_POST['time_from'])? $_POST['time_from'] : '10:00';
$time_to = isset($_POST['time_to'])? $_POST['time_to'] : '18:00';

$tasks = [
        0=>['name'=>'机拭き(主にリビング)', 'value'=>isset($_POST['tasks'][0])? $_POST['tasks'][0] : '',
            'codename'=>'cl_table0','reward'=>'100','comment'=>'毎朝or毎晩、清潔な布巾等で行う'],
        1=>['name'=>'机拭き(主にオフィス)', 'value'=>isset($_POST['tasks'][1])? $_POST['tasks'][1] : '',
            'codename'=>'cl_table1','reward'=>'100','comment'=>'毎朝or毎晩、清潔な布巾等で行う'],
        2=>['name'=>'掃除機(リビング)', 'value'=>isset($_POST['tasks'][2])? $_POST['tasks'][2] : '',
            'codename'=>'cl_floor0','reward'=>'150','comment'=>'(金夜or土朝)と(日夜or月朝)に1度ずつ'],
        3=>['name'=>'掃除機(オフィス)', 'value'=>isset($_POST['tasks'][3])? $_POST['tasks'][3] : '',
            'codename'=>'cl_floor1','reward'=>'150','comment'=>'(金夜or土朝)と(日夜or月朝)に1度ずつ']
];

//echo __LINE__ . "行目 :: このページのREQUESTデータ:<br>";
//if ($_REQUEST == null){ echo "なし<br>";}
//else { foreach ($_REQUEST as $idx => $val) {
//    if (is_array($val)) { echo "$idx = "; var_dump($val);}
//    else { echo "$idx = $val<br>";}}}

$account = new Account($earning,$time_from,$time_to,$tasks);


?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>Home</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <h1>給与計算ツール</h1>
  <div id="container">
    <form action="logout.php" method="post" id="logout">
      <?= h($app->me()->email); ?> <input type="submit" value="Log Out">
      <input type="hidden" name="token" value="<?= h($_SESSION['token']); ?>">
    </form>
    <h1>ログインしたユーザ <span class="fs12">(<?= count($app->getValues()->users); ?>)</span></h1>
    <ul>
      <?php foreach ($app->getValues()->users as $user) : ?>
      	<li id="log"><?= h($user->email); ?></li>
      <?php endforeach; ?>
    </ul>
   
 
  </div>

     <form method="post" action="payment.php">
    <div class="container">
      <p class="nine-label">売上を入力してください<input class="nice-box" type="number" name="earning" placeholder="売上を入力してください" value="<?=$earning?>" max="100000"></p>
    <p class="syukin">出勤時刻:<input class="syukin-box" type="time" name="time_from" value="<?=$time_from?>"></p>
    <p class="taikin">退勤時刻:<input class="taikin-box" type="time" name="time_to" value="<?=$time_to?>"></p>
    <?php foreach ($tasks as $id => $item):?>
        <p><input id="tasks<?=$id?>"
                  type="checkbox" name="tasks[<?=$id?>]" <?=($item['value']!='') ? 'checked' : ''?>>
        <label for="tasks<?=$id?>"><?=$item['name']?> <?=$item['comment']?></label></p>
    <?php endforeach;?>
    <input class="sansyutu "type="submit" value="給料を計算する">
</form>

<table class="AA" style="border: solid black 1pt; text-align: center; margin: 50px
 300px 100px 600px">
    <caption>営業成績</caption>
    <tr>
        <td>時間帯</td>
        <td><?=$time_from?> ~ <?=$time_to?></td>
    </tr>
    <tr>
        <td>売上高</td>
        <td><?=number_format($account->earning)?></td>
    </tr>

    <tr>
        <td>報酬</td>
        <td><?=number_format($account->day_reward)?></td>
    </tr>
    <tr>
        <td>粗利益</td>
        <td><?=number_format($account->earning - $account->day_reward)?></td>
    </tr>
    <tr>
        <td>純利益</td>
        <td><?=number_format($account->earning - $account->day_reward
                - $account->rent_t - $account->initial_cost_t)?></td>
    </tr>
</table>
<table style="border: solid black 1pt; text-align: center; margin: 10px 100px 100px 600px;" class="margin_v1 ">
    <caption>報酬内訳</caption>
    <tr>
        <td>出勤手当</td>
        <td><?=number_format(Account::$attend_allowance)?></td>
    </tr>
    <tr>
        <td>開店手当</td>
        <td><?=($account->attend_time == '10:00')? number_format(Account::$open_allowance) : 0?></td>
    </tr>
    <tr>
        <td>閉店手当</td>
        <td><?=($account->leaving_time == '22:00')? number_format(Account::$close_allowance) : 0?></td>
    </tr>
    <tr>
        <td>拘束手当</td>
        <td><?=number_format(Account::$work_hourly*$account->work_time)?></td>
    </tr>
    <tr>
        <td>(売上毎時)</td>
        <td><?=($account->work_time != 0)?number_format($account->earning/$account->work_time):''?></td>
    </tr>
    <tr>
        <td>対応手当</td>
        <td><?=number_format($account->earning * $account->earnings_ratio)?></td>
    </tr>
    <tr>
        <td>店番報酬</td>
        <td><?=number_format($account->reward_raw)?></td>
    </tr>
    <?php if($account->min_reward>$account->reward_raw):?>
    <tr>
        <td>最低保証</td>
        <td><?=number_format($account->min_reward)?></td>
    </tr>
    <?php endif;?>
    <?php if($account->overtime_reward>0):?>
        <tr>
            <td>残業報酬</td>
            <td><?=number_format($account->overtime_reward)?></td>
        </tr>
    <?php endif;?>
    <tr>
        <td>雑務報酬</td>
        <td><?=number_format($account->task_reward)?></td>
    </tr>
    <tr>
        <td>合計報酬</td>
        <td><?=number_format($account->day_reward)?></td>
    </tr>
</table>
</div>
<script src="main.js"></script>



</body>
</html>
