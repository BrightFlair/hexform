document.querySelectorAll("global-header").forEach(header => {
	const details = header.querySelector("details");
	if(!details) {
		return;
	}

	function resizeGlobalHeader() {
		const breakpoint = getComputedStyle(document.documentElement)
			.getPropertyValue("--break")
			.trim();

		if(breakpoint === "desktop" || breakpoint === "wide") {
			details.open = true;
		}
		else {
			details.open = false;
		}
	}

	window.addEventListener("resize", resizeGlobalHeader);
	resizeGlobalHeader();
});
