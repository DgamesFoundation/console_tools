<?php
namespace App;


class Dgames
{

    public  function calc($m,$n,$x,$precisions=8){
        $errors=array(
            'Divisor cannot be zero',
            'Negative numbers have no square root'
        );
        $m =  sprintf("%.".$precisions."f",$m);
        $n =  sprintf("%.".$precisions."f",$n);
        bcscale($precisions);
        switch($x){
            case 'add':
                $t=bcadd($m,$n);
                break;
            case 'sub':
                $t=bcsub($m,$n);
                break;
            case 'mul':
                $t=bcmul($m,$n);
                break;
            case 'div':
                if($n!=0){
                    $t=bcdiv($m,$n);
                }else{
                    return $errors[0];
                }
                break;
            case 'pow':
                $t=bcpow($m,$n);
                break;
            case 'mod':
                if($n!=0){
                    $t=bcmod($m,$n);
                }else{
                    return $errors[0];
                }
                break;
            case 'sqrt':
                if($m>=0){
                    $t=bcsqrt($m);
                }else{
                    return $errors[1];
                }
                break;
        }
//        $t=preg_replace("/\..*0+$/",'',$t);
        $t=rtrim(rtrim($t, '0'), '.');
        return $t;

    }




}
