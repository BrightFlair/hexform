(function() {
	let form = document.currentScript.closest("form");
	if(!form) {
		console.error("Hexform script loaded outside of form");
		return;
	}

	if(!form.action.indexOf("//hexform.io/f/")) {
		console.error("Hexform script's form does not post to the correct URL");
		return;
	}

	fetch(form.action, {
		headers: {
			"Accept": "application/json"
		}
	}).then(response => {
		if(!response.ok) {
			console.error("Hexform can not make a remote request");
			return;
		}

		return response.json();
	}).then(json => {
		injectForm(form, json);
	});

	function injectForm(form, json) {
		let field = form.ownerDocument.createElement("input");
		field.name = json.junkFieldName;

		let firstField = form.querySelector("input");
		let firstFieldLabel = firstField.closest("label");
		let injectAfter = firstFieldLabel ?? firstField;

		injectAfter.after(field);
		field.required = true;
		field.tabIndex = -1;
		field.style.position = "absolute";
		field.style.translate = "-100vw";

		let timeout = null;

		form.addEventListener("input", () => {
			if(timeout) {
				return;
			}

			timeout = setTimeout(() => {
				field.required = false;
			}, 5000);
		});
	}
})();
