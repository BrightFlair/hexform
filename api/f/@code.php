<?php
use GT\Json\Schema\JSONDocument;
use GT\Routing\Path\DynamicPath;
use HexForm\Endpoint\EndpointRepository;

function go(
	DynamicPath $dynamicPath,
	EndpointRepository $endpointRepository,
	JSONDocument $document,
):void {
	$code = $dynamicPath->get();
	$endpoint = $endpointRepository->getByCode($code);

	$document->set("code", $endpoint->code);
	$document->set("title", $endpoint->title);
	$document->set("junkDetection", $endpoint->junkDetection);
	$document->set("junkFieldName", $endpoint->junkFieldName);
	$document->set("mainField", $endpoint->mainField);
	$document->set("submitterIdentityField", $endpoint->submitterIdentityField);
}
