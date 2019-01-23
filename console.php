#!/usr/bin/php
<?php

$_SERVER["DOCUMENT_ROOT"] = $_SERVER["HOME"] . "/www";

if (php_sapi_name() !== 'cli') {
	die('Скрипт предназначен для запуска из командной строки');
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
require_once("vendor/autoload.php");

use League\Csv\Reader;
use League\Csv\Writer;
use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

try {

	// Парсим CSV

	$inputCsv = Reader::createFromPath(__DIR__ . "/price.csv", "r");

	$inputCsv->setDelimiter(';');

	$headers = $inputCsv->fetchOne();

	$res = $inputCsv->addFilter(function ($row, $index) {
		return $index > 0;
	})->fetchAll();

	// KORABLIK

    // Сайт защищен от парсинга, возможна реализация через puppeteer

    // FIXME 2й try/catch не нужен
    /*
	try {
		$korablikClient = new GuzzleClient(
			[
				"allow_redirects" => true,
				"curl" => [CURLOPT_USERAGENT => "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13"]
			]
		);

		$response = $korablikClient->request('GET', $res[0][2]);

		echo $response->getStatusCode() . PHP_EOL;

		print_r($response->getHeaders());
		print_r($response->getBody()->getContents());

	} catch (RequestException $exception) {
		echo Psr7\str($e->getRequest());
		if ($e->hasResponse()) {
			echo Psr7\str($e->getResponse());
		}
	}
    */

	$korablikClient = new Client(
		[
			"allow_redirects" => true,
			"curl" => [CURLOPT_USERAGENT => "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13"]
		]
	);

	$korablikCrawler = $korablikClient->request('GET', $res[0][2]);

	$korablikPrice = $korablikCrawler->filter('#current_offer_price')->each(function ($node) {
		return $node->attr('data-price') . PHP_EOL;
	});

	// WILDBERRIES

	$wbClient = new Client();

	if (!empty($res)) {

		foreach ($res as $resKey => $resValue) {

			if (!empty($resValue[3])) {

				$wbCrawler = $wbClient->request('GET', $resValue[3]);

				if ($wbCrawler->getUri() === $resValue[3]) {
					$wbPrice = $wbCrawler->filter('#Price ins')->each(function ($node) {
						return $node->text();
					});

					$res[$resKey][] = trim($wbPrice[0]) . PHP_EOL;

				}
			}
		}
	}

	$headers[] = 'Цена Wildberries';
	$headers[] = 'Цена Кораблик';
	$outputCsv = Writer::createFromFileObject(new SplTempFileObject());
	$outputCsv->insertOne($headers);
	$outputCsv->insertAll($res);

	ob_start();
	$outputCsv = $outputCsv->output("new_price.csv");
	$contents = ob_get_contents();
	ob_end_clean();

	file_put_contents(__DIR__ . "/new_price.csv", $contents);

} catch (Exception $e) {
	echo $e->getMessage();
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");