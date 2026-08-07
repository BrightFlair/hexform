const sidebar = document.querySelector(".ds-sidebar");

if(sidebar) {
	const links = [...sidebar.querySelectorAll('nav a[href^="#"]')];

	function showCurrentLocation() {
		const currentHash = window.location.hash || "#digital-style";

		for(const link of links) {
			if(link.getAttribute("href") === currentHash) {
				link.setAttribute("aria-current", "location");
			}
			else {
				link.removeAttribute("aria-current");
			}
		}
	}

	showCurrentLocation();
	window.addEventListener("hashchange", showCurrentLocation);
}
