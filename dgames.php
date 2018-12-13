<?php
require_once __DIR__ . '/init.php';

use Workerman\Worker;
use App\Dgames;
use Hprose\Http\Server;
use Workerman\MySQL\Connection;
use GuzzleHttp\Client;
use Workerman\Lib\Timer;

$http_worker = new Worker();

$http_worker->count = 1;

$http_worker->onWorkerStart = function($connection)
{

    $time_interval = 5;
    Timer::add($time_interval, function()
    {
        $DGAS_CONSUME=1;
        $DGAS_RECHARGE=2;
        $DGAS_RECHARGE_SCHARGE=2;//recharge
        $DGAS_NORMAL=0;//normal

        global $db;
        $db = new Connection(getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'));
        $ethapiHost=getenv('ethapi');
        echo "starts:".memory_get_usage()."\r\n";
        $dgames2dgas= $db->select('*')->from('dgame2dgas')->where('status= 0')->query();
        foreach ($dgames2dgas as $dr){
            $txid = $dr['txid'];
            if(!$txid){
                continue;
            }
            $client  = new Client();
            $result = $client->request('GET', $ethapiHost.'/api?module=transaction&action=gettxreceiptstatus&txhash='.$txid.'&apikey=YourApiKeyToken');
            $result=$result->getBody();
            $resbody = json_decode($result,true);
            var_dump("store----------".$ethapiHost.'/api?module=transaction&action=gettxreceiptstatus&txhash='.$txid,$resbody);
            unset($result);
            if(is_array($resbody['result']) && $resbody['result']['status']){
                $up =  $db->update("dgame2dgas")->cols(['status'=>1])->where('txid= :txid')->bindValues(['txid'=> $txid])->query();
                var_dump("update is ",$up);
                if($up==0){
                    continue;
                }
                $addDgas=$dr['dgas']-$dr['Scharge'];//recharge dgas，deduct fee
                $address=$dr['address'];

                $para=['appid'=>$dr['appid'],
                    'num'=>$addDgas,
                    'address'=>$address];            // Dgas address
                $query=['appid'=>$para['appid'],
                    'address'=>$para['address']];
                $is=$db->select('dgas')->from('account')->where('appid= :appid AND address= :address')->bindValues($query)->row();
                $data=['dgas'=>$para['num']+$is['dgas']];
                $rel= $db->update("account")->cols($data)->where('appid= :appid AND address= :address')->bindValues($query)->query();
                if($rel)
                    echo 'user add dgas success!------';
                else
                    echo 'user add dgas fail!------';

                //insert gas_log
                $gas_log1=['appid'=>$dr['appid'],
                    'address'=>$address,
                    'exchange_id'=>$dr['id'],
                    'type'=>$DGAS_RECHARGE,
                    'flag'=>$DGAS_NORMAL,
                    'create_time'=>time()];
                $gas_log2= ['appid'=>$dr['appid'],
                    'address'=>$address,
                    'exchange_id'=>$dr['id'],
                    'type'=>$DGAS_CONSUME,
                    'flag'=>$DGAS_RECHARGE_SCHARGE,
                    'create_time'=>time()];
                $db->insert('gas_log')->cols($gas_log1)->query();
                $db->insert('gas_log')->cols($gas_log2)->query();
            }else{
                $try = $dr['call_num'];
                $try++;
                if ($try <=15){
                    $up =  $db->update("dgame2dgas")->cols(['call_num'=>$try])->where('txid= :txid')->bindValues(["txid"=>$txid])->query();
                }else{
                    $up =  $db->update("dgame2dgas")->cols(['status'=>2])->where('txid= :txid')->bindValues(["txid"=>$txid])->query();
                }
                var_dump("update is ",$up);
            }
           // break;
        }
        echo "process:".memory_get_usage()."\r\n";
        unset($dgames2dgas);
        echo "end:".memory_get_usage()."\r\n";
    });

};



Worker::runAll();
