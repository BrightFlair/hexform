<?php
use GT\Json\Schema\JSONDocument;

function go(JSONDocument $document):void {
	$document->set("Hello", "world!");
}
