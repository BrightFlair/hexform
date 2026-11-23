<?php
function go():void {
	header("Content-type: text/javascript");
	echo file_get_contents("script/hexform-spam.js");
	exit;
}
