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

        global $db;
        $db = new Connection(getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'));
        $ethapiHost=getenv('ethapi');
        echo "starts:".memory_get_usage()."\r\n";
        $dgame2subchain = $db->select('*')->from('dgame2subchain')->where('status=0')->query();
       // var_dump($dgame2subchain);
        echo "start1:".memory_get_usage()."\r\n";
        foreach ($dgame2subchain as $dr) {
            if(!$dr['txid']){
                unset($dr);
                continue;
            }
            $txid = $dr['txid'];
            $client  = new Client();
            $result = $client->request('GET', $ethapiHost.'/api?module=transaction&action=gettxreceiptstatus&txhash='.$txid.'&apikey=YourApiKeyToken');
            $result=$result->getBody();
            $resbody = json_decode($result,true);
            var_dump($ethapiHost . '/api?module=transaction&action=gettxreceiptstatus&txhash=' . $txid . '&apikey=YourApiKeyToken');
            //var_dump($dr);
             unset($result);
            //var_dump($resbody);
            if (is_array($resbody['result']) && $resbody['result']['status']) {

                $res =  $db->update("dgame2subchain")->cols(['status'=>1])->where('txid= :txid')->bindValues(['txid'=> $txid])->query();
                unset($txid);
                // var_dump($res);break;
                if ($res > 0) {
                    $subData = ['dgas' => $dr['dgame'],
                        'amount' => $dr['subchain'],
                        'faddress' => $dr['fromaddr'],
                        'taddress' => $dr['address'],
                        'out_trade_no' => $dr['order_sn'],
                        'create_time' => time(),
                        'update_time' => time(),
                        'type' => 0];
                    var_dump($subData);
                    $rel=$db->select('pay_callback_url,precisions')->from('application')->where('appid= :appid')->bindValues(['appid'=> $dr['appid']])->query();
                    var_dump($rel);
                    $mul_len = getenv('dgas_mul_num');

		    $dgames=new Dgames();

                    $subData['dgas'] = $dgames->calc($subData['dgas'], $mul_len, 'mul');
                    $subData['amount'] = $dgames->calc($subData['amount'], $rel[0]['precisions'], 'mul');
                    $client  = new Client();
                    $result1 = $client->request('POST',$rel[0]['pay_callback_url'], ['form_params'=>$subData]);
                    var_dump($subData);
                    unset($res);
                    unset($subData);
                    unset($result1);
                }
            }
            unset($dr);
            unset($resbody);
        }
        unset($dgame2subchain);
        echo "process:".memory_get_usage()."\r\n";
        echo "end:".memory_get_usage()."\r\n";
    });

};



Worker::runAll();
