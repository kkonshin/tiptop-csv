#!/usr/bin/php
<?php

$_SERVER["DOCUMENT_ROOT"] = $_SERVER["HOME"] . "/www";

if (php_sapi_name() !== 'cli'){
	die('Скрипт предназначен для запуска из командной строки');
}

require ($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
require_once ("vendor/autoload.php");

use League\Csv\Reader;
use League\Csv\Writer;
use Goutte\Client;

try {

    // Парсим CSV

	$inputCsv = Reader::createFromPath(__DIR__ . "/price.csv", "r");

	$inputCsv->setDelimiter(';');

	$headers = $inputCsv->fetchOne();

	$res = $inputCsv->addFilter(function ($row, $index){
	    return $index > 0;
    })->fetchAll();

    // KORABLIK

    // TODO Защита от DDOS на кораблике, пробуем обойти через подмену user-agent

	$korablikClient = new Client();

	// TODO в параметры request передать пользовательский заголовок или CURLOPT

	$korablikCrawler = $korablikClient->request('GET', $res[0][2]);

	$korablikPrice = $korablikCrawler->filter('#current_offer_price')->each(function($node){
	    return $node->attr('data-price') . PHP_EOL;
    });

	// WILDBERRIES

	$wbClient = new Client();

	if(!empty($res)){

		foreach ($res as $resKey => $resValue){

			if (!empty($resValue[3])){

				$wbCrawler = $wbClient->request('GET', $resValue[3]);

				if ($wbCrawler->getUri() === $resValue[3]){
					$wbPrice = $wbCrawler->filter('#Price ins')->each(function($node){
						return $node->text();
					});

					// TODO записать цену в массив / в CSV

					echo trim($wbPrice[0]) . PHP_EOL;

                }
			}
		}
	}

} catch (Exception $e){
    echo $e->getMessage();
}

require ($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");