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
		console.log(json);
	});
})();
