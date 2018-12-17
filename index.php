<?php

define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);
define('PUBLIC_AJAX_MODE', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

require_once("vendor/autoload.php");

use League\Csv\Reader;
use League\Csv\Writer;
use Goutte\Client;

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

	$fileName = "new_price_" . date("Y_m_d_h_i_s") . ".csv";

	$outputCsv->output($fileName);

	die();

} catch (Exception $e) {
	echo $e->getMessage();
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
