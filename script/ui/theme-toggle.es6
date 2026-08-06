const STORAGE_KEY = "theme";
const MODES = ["system", "light", "dark"];
const LABELS = {
	system: "System theme",
	light: "Light theme",
	dark: "Dark theme",
};
const THEME_COLORS = {
	light: "#fffdf7",
	dark: "#191713",
};

function getStoredTheme() {
	const stored = localStorage.getItem(STORAGE_KEY);
	return MODES.includes(stored) ? stored : "system";
}

function getEffectiveTheme(mode = getStoredTheme()) {
	if(mode === "light" || mode === "dark") {
		return mode;
	}

	return window.matchMedia("(prefers-color-scheme: dark)").matches
		? "dark"
		: "light";
}

function updateToggleButton(mode) {
	const button = document.querySelector("[data-theme-toggle]");
	if(!button) {
		return;
	}

	button.dataset.themeMode = mode;
	button.setAttribute("aria-label", LABELS[mode]);
	button.setAttribute("title", LABELS[mode]);

	const label = button.querySelector(".theme-toggle-label");
	if(label) {
		label.textContent = LABELS[mode];
	}
}

function updateMetaThemeColor(mode) {
	const meta = document.querySelector('meta[name="theme-color"]');
	if(meta) {
		meta.setAttribute("content", THEME_COLORS[getEffectiveTheme(mode)]);
	}
}

function applyTheme(mode) {
	if(mode === "light" || mode === "dark") {
		document.documentElement.dataset.theme = mode;
	}
	else {
		delete document.documentElement.dataset.theme;
	}

	updateToggleButton(mode);
	updateMetaThemeColor(mode);
}

function getModeSequence() {
	return getEffectiveTheme("system") === "light"
		? ["system", "dark", "light"]
		: ["system", "light", "dark"];
}

function cycleTheme() {
	const current = getStoredTheme();
	const sequence = getModeSequence();
	const next = sequence[(sequence.indexOf(current) + 1) % sequence.length];

	if(next === "system") {
		localStorage.removeItem(STORAGE_KEY);
	}
	else {
		localStorage.setItem(STORAGE_KEY, next);
	}

	applyTheme(next);
}

function initThemeToggle() {
	const button = document.querySelector("[data-theme-toggle]");
	if(!button) {
		return;
	}

	applyTheme(getStoredTheme());
	button.addEventListener("click", cycleTheme);
	window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
		if(getStoredTheme() === "system") {
			updateMetaThemeColor("system");
		}
	});
}

initThemeToggle();
