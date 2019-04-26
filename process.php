<?php

define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);
define('PUBLIC_AJAX_MODE', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
require_once("vendor/autoload.php");

use League\Csv\Reader;
use League\Csv\Writer;
use Goutte\Client;
use JonnyW\PhantomJs\Client as PJSClient;
use JonnyW\PhantomJs\DependencyInjection\ServiceContainer as PJSServiceContainer;
use Symfony\Component\DomCrawler\Crawler; // для разборки респонса phantomjs

$jsLocation = __DIR__ . '/js';

try {
	// Получаем информацию о загруженном файле из main.file.input:csv_upload
	$filePath = '';
	$jsonRequest = file_get_contents('php://input');
	$jsonRequestBody = json_decode($jsonRequest, true);

//	file_put_contents(__DIR__ . "/jsonRequestBody.log", print_r($jsonRequestBody, true));
	// Парсим CSV
	if (is_array($jsonRequestBody) && !empty($jsonRequestBody)) {
		$fileRes = CFile::GetByID($jsonRequestBody['element_id']);
		$fileArray = $fileRes->fetch();
		$filePath = CFile::GetPath($jsonRequestBody['element_id']);
	}
	if ((string)$filePath !== '') {
		$inputCsv = Reader::createFromPath($_SERVER["DOCUMENT_ROOT"] . (string)$filePath, "r");
	} else {
		throw new Exception('Неправильный путь к исходному файлу');
	}
	$inputCsv->setDelimiter(';');
	$headers = $inputCsv->fetchOne();
	$res = $inputCsv->addFilter(function ($row, $index) {
		return $index > 0;
	})->fetchAll();


	$columnNames = [
		'Цена Wildberries',
		'Цена Wildberries со скидкой',
		'Цена Wildberries со скидкой и промокодом',
		'Цена Tiptopkids',
		'Цена Tiptopkids со скидкой'
	];

	foreach ($columnNames as $key => $value){
		if (!in_array($value, $headers)){
			$headers[] = $value;
		}
	}

	$headersCount = count($headers);

	foreach ($res as $k => $v){
		if (count($v) < $headersCount){
			$diff = $headersCount - count($v);
			$empty = array_fill($headersCount - $diff, $diff, '');
			$res[$k] = array_merge($res[$k], $empty);
		}
	}

// KORABLIK
	// Сайт защищен от парсинга, возможна реализация через puppeteer/phantomjs?
	/*
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
	*/

// WILDBERRIES

	$wbClient = new Client();


	file_put_contents(__DIR__ . "/res.log", print_r($res, true));

	if (!empty($res)) {

		foreach ($res as $resKey => $resValue) {

			if (!empty($resValue[3]) && strpos($resValue[3], "http")) {

				$wbCrawler = $wbClient->request('GET', $resValue[3]);

				file_put_contents(__DIR__ . "/wbCrawler.log", print_r($wbCrawler, true), FILE_APPEND);


				if ($wbCrawler->getUri() === $resValue[3]) {

					$wbPrice = $wbCrawler->filter('#Price ins')->each(function ($node) {
						return $node->text();
					});

					$res[$resKey][4] = trim($wbPrice[0]);

					// Базовая скидка

					$wbDiscountPrice = $wbCrawler->filter('.about-bonus .add-discount-text-price')->each(function ($node) {
						return $node->text();
					});

					$res[$resKey][5] = trim($wbDiscountPrice[0]);

					// Скидка + промокод (через phantomjs)

					try {

						$serviceContainer = PJSServiceContainer::getInstance();

						$procedureLoader = $serviceContainer->get('procedure_loader_factory')->createProcedureLoader($jsLocation);

						$client = PJSClient::getInstance();

						$client->getProcedureLoader()->addLoader($procedureLoader);

						// TODO включить кеш в продакшене

//						$client->getProcedureCompiler()->clearCache();

//						$client->getProcedureCompiler()->disableCache();

						$client->isLazy();

						$request = $client->getMessageFactory()->createRequest($resValue[3]);

						$request->setTimeout(3000);

						$response = $client->getMessageFactory()->createResponse();

						$client->send($request, $response);

						$phantomCrawler = new Crawler($response->getContent());

						$phantomCrawlerResult = $phantomCrawler->filter('.j-promo-tooltip-content p:last-child')->each(function ($node) {
							return $node->text();
						});

						if (stripos($phantomCrawlerResult[0], "{{") === false
							&& stripos($phantomCrawlerResult[0], "Скидка") === false
							&& stripos($phantomCrawlerResult[0], "Промокод") !== false
						) {
							$result = explode("%", $phantomCrawlerResult[0]);
							$res[$resKey][6] = trim($result[count($result) - 1]);
						}

					} catch (Exception $e) {
						file_put_contents(__DIR__ . "/errors.log", print_r($e->getMessage(), true));
					}
				}
			}
		}
	}

// TIPTOPKIDS

	$ttcClient = new Client();

	if (!empty($res)) {

		foreach ($res as $resKey => $resValue) {

			if (!empty($resValue[1])) {

				$ttcCrawler = $ttcClient->request('GET', $resValue[1]);

				if ($ttcCrawler->getUri() === $resValue[1]) {

					$ttcPrice = $ttcCrawler->filter('.price__pv.js-price_pv-3')->each(function ($node) {
						return $node->text();
					});

					$res[$resKey][7] = trim($ttcPrice[0]);

					// Базовая скидка

					$ttcDiscountPrice = $ttcCrawler->filter('.price__pdv.js-price_pdv-3')->each(function ($node) {
						return $node->text();
					});

					if (!empty($res[$resKey][7])){
						$res[$resKey][8] = trim($ttcDiscountPrice[0]);
					} else {
						$res[$resKey][7] = trim($ttcDiscountPrice[0]);
					}

				}
			}
		}
	}

	// Очистим прайс от незаполненных полей шаблонизатора

	foreach ($res as $outerKey => $outerValue) {
		foreach ($outerValue as $innerKey => $innerValue) {
			if (stripos($innerValue, "{{") !== false) {
				$res[$outerKey][$innerKey] = '';
			}
		}
	}

//	file_put_contents(__DIR__ . "/resAfter.log", print_r($res, true));

// Записываем csv

	$outputCsv = Writer::createFromFileObject(new SplTempFileObject());
	$outputCsv->setDelimiter(";");
	$outputCsv->setEnclosure('"');

	$outputCsv->insertOne($headers);

	$outputCsv->insertAll($res);

	$fileName = "new_price.csv";
	$directoryPath = substr(__DIR__, 16);

	ob_start();
	$outputCsvContents = $outputCsv->output($fileName);
	$outputCsvContents = ob_get_contents();
	ob_end_clean();

	if (!is_dir(__DIR__ . "/output")) {
		mkdir(__DIR__ . "/output", 0770, true);
	}

	if (file_put_contents(__DIR__ . "/output/" . $fileName, $outputCsvContents)) {
		echo json_encode($directoryPath . "/output/" . $fileName);
	}

	// TODO заново читаем файл?

} catch (Exception $e) {
	file_put_contents(__DIR__ . "/global_errors.log", print_r($e->getMessage(), true));
}
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php"); ?>
