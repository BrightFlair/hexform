<?php
use Gt\Dom\Element;
use Gt\Http\Uri;
use HexForm\User\User;

function go(Element $element, Uri $uri, ?User $user):void {
	if($uri->getPath() === "/digital-style/") {
		$element->remove();
		return;
	}

	if($uri->getPath() !== "/" || !$user) {
		$element->querySelector(".homepage-dashboard")?->remove();
	}

	$isApp = str_starts_with($uri->getPath(), "/app/");
	if(!$isApp) {
		$element->querySelector(".app-navigation")?->remove();
		$element->querySelector(".app-only")?->remove();
	}
	$currentPath = rtrim($uri->getPath(), "/") . "/";
	foreach($element->querySelectorAll("nav a") as $link) {
		$href = $link->getAttribute("href");
		$isAppSection = $href !== "/app/"
			&& str_starts_with($href, "/app/")
			&& str_starts_with($currentPath, $href);
		if($href === $currentPath || $isAppSection) {
			$link->setAttribute("aria-current", "location");
		}
	}
}
