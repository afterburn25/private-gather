<?php
namespace App\Contracts;use App\Models\Order;interface PaymentGateway{public function name():string;public function begin(Order $order):array;public function refund(Order $order,int $amountCents):array;}
