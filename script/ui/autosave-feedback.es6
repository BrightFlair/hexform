const pendingSaves = new Map();
const feedbackTimeouts = new WeakMap();

document.addEventListener("submit", event => {
	const button = event.submitter;
	if(!(button instanceof HTMLButtonElement) || !button.hasAttribute("data-autosave")) {
		return;
	}

	pendingSaves.set(button.form, {
		name: button.name,
		value: button.value,
	});
}, true);

document.addEventListener("flux:after-render", event => {
	for(const [form, save] of pendingSaves) {
		const update = event.detail.updates.find(({existingElement}) =>
			existingElement === form || existingElement.contains(form)
		);
		if(!update) {
			continue;
		}

		pendingSaves.delete(form);
		const button = [...update.element?.querySelectorAll("button[data-autosave]") ?? []]
			.find(button => button.name === save.name && button.value === save.value);
		if(!button) {
			continue;
		}

		const originalText = button.textContent;
		button.textContent = button.dataset.autosave;
		clearTimeout(feedbackTimeouts.get(button));
		feedbackTimeouts.set(button, setTimeout(() => {
			button.textContent = originalText;
			feedbackTimeouts.delete(button);
		}, 2000));
	}
});
