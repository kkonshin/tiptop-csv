<?php

define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);
define('PUBLIC_AJAX_MODE', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
require_once("vendor/autoload.php");

use League\Csv\Reader;
use League\Csv\Writer;
use JonnyW\PhantomJs\Client as PJSClient;
use JonnyW\PhantomJs\DependencyInjection\ServiceContainer as PJSServiceContainer;
use Symfony\Component\DomCrawler\Crawler; // для разборки респонса phantomjs

$jsLocation = __DIR__ . '/js';

try {
	// Получаем информацию о загруженном файле из main.file.input:csv_upload
	$filePath = '';
	$jsonRequest = file_get_contents('php://input');
	$jsonRequestBody = json_decode($jsonRequest, true);

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

	foreach ($columnNames as $key => $value) {
		if (!in_array($value, $headers)) {
			$headers[] = $value;
		}
	}

	$headersCount = count($headers);

	foreach ($res as $k => $v) {
		if (count($v) < $headersCount) {
			$diff = $headersCount - count($v);
			$empty = array_fill($headersCount - $diff, $diff, '');
			$res[$k] = array_merge($res[$k], $empty);
		}
	}

	// KORABLIK
	// Сайт защищен от парсинга, возможна реализация через puppeteer/phantomjs?
	/*
	if (!empty($res)) {
		foreach ($res as $resKey => $resValue) {
			if (!empty($resValue[3]) && (strpos($resValue[2], "http") !== false)) {
				try {
					$serviceContainer = PJSServiceContainer::getInstance();
					$procedureLoader = $serviceContainer->get('procedure_loader_factory')->createProcedureLoader($jsLocation);
					$client = PJSClient::getInstance();
					$client->getProcedureLoader()->addLoader($procedureLoader);
					$client->isLazy();
					$request = $client->getMessageFactory()->createRequest($resValue[2]);
					$request->setTimeout(3000);
					$response = $client->getMessageFactory()->createResponse();
					$client->send($request, $response);
					$phantomCrawler = new Crawler($response->getContent());

					$phantomBasePrice = $phantomCrawler->filter('#current_offer_price')->each(function ($node) {
						return $node->text();
					});

//					$phantomDiscountPrice = $phantomCrawler->filter('.about-bonus .add-discount-text-price')->each(function ($node) {
//						return $node->text();
//					});

//					$phantomFinalPrice = $phantomCrawler->filter('.j-promo-tooltip-content p:last-child')->each(function ($node) {
//						return $node->text();
//					});

					$res[$resKey][9] = trim($phantomBasePrice[0]);
//					$res[$resKey][10] = trim($phantomDiscountPrice[0]);

//					if (stripos($phantomFinalPrice[0], "{{") === false
//						&& stripos($phantomFinalPrice[0], "Скидка") === false
//						&& stripos($phantomFinalPrice[0], "Промокод") !== false
//					) {
//						$result = explode("%", $phantomFinalPrice[0]);
//						$res[$resKey][11] = trim($result[count($result) - 1]);
//					}

				} catch (Exception $e) {
					file_put_contents(__DIR__ . "/errors.log", print_r($e->getMessage(), true), FILE_APPEND);
				}
			}
		}
	}
	*/

	// WILDBERRIES
	if (!empty($res)) {
		foreach ($res as $resKey => $resValue) {
			if (!empty($resValue[3]) && (strpos($resValue[3], "http") !== false)) {
				try {
					$serviceContainer = PJSServiceContainer::getInstance();
					$procedureLoader = $serviceContainer->get('procedure_loader_factory')->createProcedureLoader($jsLocation);
					$client = PJSClient::getInstance();
					$client->getProcedureLoader()->addLoader($procedureLoader);
					$client->isLazy();
					$request = $client->getMessageFactory()->createRequest($resValue[3]);
					$request->setTimeout(3000);
					$response = $client->getMessageFactory()->createResponse();
					$client->send($request, $response);
					$phantomCrawler = new Crawler($response->getContent());

					$phantomBasePrice = $phantomCrawler->filter('#Price ins')->each(function ($node) {
						return $node->text();
					});

					$phantomDiscountPrice = $phantomCrawler->filter('.about-bonus .add-discount-text-price')->each(function ($node) {
						return $node->text();
					});

					$phantomFinalPrice = $phantomCrawler->filter('.j-promo-tooltip-content p:last-child')->each(function ($node) {
						return $node->text();
					});

					$res[$resKey][4] = trim($phantomBasePrice[0]);
					$res[$resKey][5] = trim($phantomDiscountPrice[0]);

					if (stripos($phantomFinalPrice[0], "{{") === false
						&& stripos($phantomFinalPrice[0], "Скидка") === false
						&& stripos($phantomFinalPrice[0], "Промокод") !== false
					) {
						$result = explode("%", $phantomFinalPrice[0]);
						$res[$resKey][6] = trim($result[count($result) - 1]);
					}

				} catch (Exception $e) {
					file_put_contents(__DIR__ . "/errors.log", print_r($e->getMessage(), true), FILE_APPEND);
				}
			}
		}
	}

	// TIPTOPKIDS
	if (!empty($res)) {
		foreach ($res as $resKey => $resValue) {
			if (!empty($resValue[1]) && (strpos($resValue[1], "http") !== false)) {
				try {
					$serviceContainer = PJSServiceContainer::getInstance();
					$procedureLoader = $serviceContainer->get('procedure_loader_factory')->createProcedureLoader($jsLocation);
					$client = PJSClient::getInstance();
					$client->getProcedureLoader()->addLoader($procedureLoader);
					$client->isLazy();
					$request = $client->getMessageFactory()->createRequest($resValue[1]);
					$request->setTimeout(3000);
					$response = $client->getMessageFactory()->createResponse();
					$client->send($request, $response);
					$phantomCrawler = new Crawler($response->getContent());

					$phantomBasePrice = $phantomCrawler->filter('.price__pv.js-price_pv-3')->each(function ($node) {
						return $node->text();
					});

					$phantomDiscountPrice = $phantomCrawler->filter('.price__pdv.js-price_pdv-3')->each(function ($node) {
						return $node->text();
					});

					$res[$resKey][7] = trim($phantomBasePrice[0]);
					$res[$resKey][8] = trim($phantomDiscountPrice[0]);

				} catch (Exception $e) {
					file_put_contents(__DIR__ . "/errors.log", print_r($e->getMessage() . "\n", true), FILE_APPEND);
				}
			}
		}
	}

	// Очищаем прайс от незаполненных полей шаблонизатора
	foreach ($res as $outerKey => $outerValue) {
		foreach ($outerValue as $innerKey => $innerValue) {
			if (stripos($innerValue, "{{") !== false) {
				$res[$outerKey][$innerKey] = '';
			}
		}
	}

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

} catch (Exception $e) {
	file_put_contents(__DIR__ . "/global_errors.log", print_r($e->getMessage(), true));
}
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
