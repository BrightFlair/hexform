const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

if(!reduceMotion.matches) {
	document.querySelectorAll(".hex-fade").forEach(hexField => {
		function createHex() {
			const rootSize = parseFloat(getComputedStyle(document.documentElement).fontSize);
			const rowHeight = rootSize * 1.296875;
			const columnWidth = rootSize * 4.5;
			const row = Math.floor(Math.random() * Math.max(1, hexField.clientHeight / rowHeight));
			const column = Math.floor(Math.random() * Math.max(1, hexField.clientWidth / columnWidth));
			const hexagon = document.createElement("i");
			const derivative = Math.floor(Math.random() * 4) + 1;

			hexagon.className = `hex-twinkle hex-twinkle--d${derivative}`;
			hexagon.style.left = `${column * columnWidth + (row % 2 ? 0 : columnWidth / 2)}px`;
			hexagon.style.top = `${row * rowHeight}px`;
			hexagon.addEventListener("animationend", removeHex);
			setTimeout(() => {removeHex.call(hexagon);}, 10000);
			hexField.append(hexagon);

			window.setTimeout(createHex, 100 + Math.random() * 100);
		}

		function removeHex() {
			let hexagon = this;
			if(hexagon.parentElement) {
				hexagon.remove();
			}
		}

		createHex();
		createHex();
		createHex();
		createHex();
		createHex();
		createHex();
	});
}
