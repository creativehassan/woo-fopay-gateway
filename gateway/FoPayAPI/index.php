<?php
require 'vendor/autoload.php';
require './FoPayAPI.php';

$fopayAPI = new FoPayAPI();

$accountList = $fopayAPI->accountList();
var_dump($accountList);

$createdInvoice = $fopayAPI->createInvoice([
    "amount" => "0.01",
    "currency" => "USD",
    "redirectUrl" => "http://www.disney.com",
]);
var_dump($createdInvoice);
//
$invoice = $fopayAPI->getInvoice([
    "invoiceId" => "254887de-4da5-4f4d-92a0-9f7e899e9855"
]);
var_dump($invoice);
//
$invoice = $fopayAPI->cancelInvoice([
    "invoiceId" => "254887de-4da5-4f4d-92a0-9f7e899e9855"
]);
var_dump($invoice);