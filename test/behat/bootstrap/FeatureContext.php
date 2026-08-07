<?php
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;

class FeatureContext extends MinkContext {
	private const TEST_USER_ID = "behat-user";
	private const TEST_USER_EMAIL = "behat@hexform.io";
	private const TEST_ENDPOINT_ID = "behat-endpoint";
	private const TEST_ENDPOINT_CODE = "behatcode";
	private const TEST_SUBMISSION_ID = "behat-submission";

	private ?PDO $database = null;
	private int $endpointSequence = 0;
	private int $submissionSequence = 0;
	/** @var array<string, array{id: string, code: string}> */
	private array $endpointList = [];

	/** @BeforeScenario */
	public function prepareScenario():void {
		$this->getSession()->reset();
		$this->endpointSequence = 0;
		$this->submissionSequence = 0;
		$this->endpointList = [];
		$this->deleteTestUser();
	}

	/** @AfterScenario */
	public function cleanScenario():void {
		$this->deleteTestUser();
	}

	/** @Given I am signed in */
	public function iAmSignedIn():void {
		$this->visitPath("/?debug-auth=" . self::TEST_USER_ID);
		$this->assertSession()->addressEquals("/app/");
	}

	/** @Given I am not signed in */
	public function iAmNotSignedIn():void {
		$this->getSession()->reset();
	}

	/** @Given I have an endpoint named :title */
	public function iHaveAnEndpointNamed(string $title):void {
		$this->ensureTestUser();
		$this->endpointSequence++;
		$id = self::TEST_ENDPOINT_ID . "-$this->endpointSequence";
		$code = self::TEST_ENDPOINT_CODE . $this->endpointSequence;
		$this->database()->prepare(<<<'SQL'
			insert into Endpoint (
				id, userId, code, title, clientHost, confirmationUrl,
				junkDetection, junkFieldName, mainField,
				submitterIdentityField, retentionMonths,
				maximumSubmissionsPerMonth, forwarderUrl
			) values (
				:id, :userId, :code, :title, :clientHost, null,
				true, 'company', 'message', 'email', 1, 50, null
			)
		SQL)->execute([
			"id" => $id,
			"userId" => self::TEST_USER_ID,
			"code" => $code,
			"title" => $title,
			"clientHost" => "https://example.com",
		]);
		$this->endpointList[$title] = ["id" => $id, "code" => $code];
	}

	/** @Given the endpoint :title forwards submissions to :url */
	public function theEndpointForwardsSubmissionsTo(string $title, string $url):void {
		$endpoint = $this->endpoint($title);
		$this->database()->prepare(
			"update Endpoint set forwarderUrl = :url where id = :id",
		)->execute(["url" => $url, "id" => $endpoint["id"]]);
	}

	/** @Given the endpoint :title has received a submission from :submitter saying :message */
	public function theEndpointHasReceivedSubmission(
		string $title,
		string $submitter,
		string $message,
	):void {
		$this->insertSubmission($title, $submitter, $message, false);
	}

	/** @Given the endpoint :title has caught a junk submission from :submitter saying :message */
	public function theEndpointHasCaughtJunk(
		string $title,
		string $submitter,
		string $message,
	):void {
		$this->insertSubmission($title, $submitter, $message, true);
	}

	/** @When someone submits :message to :title as :submitter */
	public function someoneSubmitsToAs(
		string $message,
		string $title,
		string $submitter,
	):void {
		$this->postForm($title, ["email" => $submitter, "message" => $message]);
	}

	/** @When a bot submits :message to :title as :submitter */
	public function aBotSubmitsToAs(
		string $message,
		string $title,
		string $submitter,
	):void {
		$this->postForm($title, [
			"email" => $submitter,
			"message" => $message,
			"company" => "Definitely a bot",
		]);
	}

	/** @When I open :label from app navigation */
	public function iOpenFromAppNavigation(string $label):void {
		$navigation = $this->requireElement("global-header .app-navigation", "app navigation");
		$link = $navigation->findLink($label);
		if(!$link) {
			throw $this->expectation("App navigation has no '$label' link.");
		}
		$link->click();
	}

	/** @Then :label should be selected in app navigation */
	public function shouldBeSelectedInAppNavigation(string $label):void {
		$navigation = $this->requireElement("global-header .app-navigation", "app navigation");
		$link = $navigation->findLink($label);
		if(!$link || $link->getAttribute("aria-current") !== "location") {
			throw $this->expectation("'$label' is not selected in app navigation.");
		}
	}

	/** @Then onboarding task :task should be complete */
	public function onboardingTaskShouldBeComplete(string $task):void {
		$row = $this->findContaining(".onboarding li", $task);
		if(!$row->hasClass("complete")) {
			throw $this->expectation("Onboarding task '$task' is not complete.");
		}
	}

	/** @Then onboarding task :task should be incomplete */
	public function onboardingTaskShouldBeIncomplete(string $task):void {
		$row = $this->findContaining(".onboarding li", $task);
		if($row->hasClass("complete")) {
			throw $this->expectation("Onboarding task '$task' is complete.");
		}
	}

	/** @Then the dashboard metric :label should be :value */
	public function theDashboardMetricShouldBe(string $label, string $value):void {
		$card = $this->findContaining(".metric-card", $label);
		$actual = $card->find("css", "strong")?->getText();
		if($actual !== $value) {
			throw $this->expectation("Dashboard metric '$label' is '$actual', expected '$value'.");
		}
	}

	/** @When I configure the endpoint :title */
	public function iConfigureTheEndpoint(string $title):void {
		$this->visitPath("/app/endpoints/");
		$row = $this->findContaining("tbody tr", $title);
		$row->findLink("Configure")?->click();
	}

	/** @When I open the inbox for endpoint :title */
	public function iOpenTheInboxForEndpoint(string $title):void {
		$this->visitPath("/app/endpoints/");
		$row = $this->findContaining("tbody tr", $title);
		$row->findLink("Inbox")?->click();
	}

	/** @When I delete the endpoint :title */
	public function iDeleteTheEndpoint(string $title):void {
		$this->iConfigureTheEndpoint($title);
		$this->acceptNextConfirmation();
		$this->getSession()->getPage()->pressButton("Delete endpoint");
	}

	/** @Then the endpoint list should contain :title */
	public function theEndpointListShouldContain(string $title):void {
		$this->visitPath("/app/endpoints/");
		$this->findContaining("tbody tr", $title);
	}

	/** @Then the endpoint list should not contain :title */
	public function theEndpointListShouldNotContain(string $title):void {
		$this->visitPath("/app/endpoints/");
		foreach($this->getSession()->getPage()->findAll("css", "tbody tr") as $row) {
			if(str_contains($row->getText(), $title)) {
				throw $this->expectation("Endpoint list still contains '$title'.");
			}
		}
	}

	/** @Then the inbox should be filtered to endpoint :title */
	public function theInboxShouldBeFilteredToEndpoint(string $title):void {
		$endpoint = $this->endpoint($title);
		$this->assertSession()->addressEquals(
			"/app/submissions/?endpoint=" . urlencode($endpoint["id"]),
		);
	}

	/** @When I filter the inbox by endpoint :title */
	public function iFilterTheInboxByEndpoint(string $title):void {
		$this->getSession()->getPage()->selectFieldOption("Endpoint", $title);
		$this->getSession()->getPage()->pressButton("Filter");
	}

	/** @When I read the message from :submitter */
	public function iReadTheMessageFrom(string $submitter):void {
		$row = $this->findContaining("tbody tr", $submitter);
		$row->findLink("Read")?->click();
	}

	/** @When I delete the message from :submitter */
	public function iDeleteTheMessageFrom(string $submitter):void {
		$row = $this->findContaining("tbody tr", $submitter);
		$this->acceptNextConfirmation();
		$row->pressButton("Delete");
	}

	/** @When I mark the message from :submitter as not junk */
	public function iMarkTheMessageAsNotJunk(string $submitter):void {
		$this->findContaining("tbody tr", $submitter)->pressButton("Not junk");
	}

	/** @Then the message list should contain :text */
	public function theMessageListShouldContain(string $text):void {
		$this->findContaining("tbody tr", $text);
	}

	/** @Then the message list should not contain :text */
	public function theMessageListShouldNotContain(string $text):void {
		foreach($this->getSession()->getPage()->findAll("css", "tbody tr") as $row) {
			if(str_contains($row->getText(), $text)) {
				throw $this->expectation("Message list still contains '$text'.");
			}
		}
	}

	/** @Then the theme preference should be :mode */
	public function theThemePreferenceShouldBe(string $mode):void {
		$actual = $this->getSession()->evaluateScript(
			"localStorage.getItem('theme') || 'system'",
		);
		if($actual !== $mode) {
			throw $this->expectation("Theme preference is '$actual', expected '$mode'.");
		}
	}

	/** @When I change the colour theme */
	public function iChangeTheColourTheme():void {
		$this->requireElement("[data-theme-toggle]", "theme toggle")->click();
	}

	/** @Then a non-system theme preference should be stored */
	public function aNonSystemThemePreferenceShouldBeStored():void {
		$actual = $this->getSession()->evaluateScript("localStorage.getItem('theme')");
		if(!in_array($actual, ["light", "dark"], true)) {
			throw $this->expectation("No explicit colour theme preference was stored.");
		}
	}

	/** @Then my theme preference should remain after opening :path */
	public function myThemePreferenceShouldRemainAfterOpening(string $path):void {
		$before = $this->getSession()->evaluateScript("localStorage.getItem('theme')");
		$this->visitPath($path);
		$after = $this->getSession()->evaluateScript("localStorage.getItem('theme')");
		if(!$before || $after !== $before) {
			throw $this->expectation("The theme preference was not retained.");
		}
	}

	private function database():PDO {
		return $this->database ??= new PDO(
			"mysql:host=" . (getenv("BEHAT_DB_HOST") ?: "127.0.0.1")
				. ";port=" . (getenv("BEHAT_DB_PORT") ?: "3306")
				. ";dbname=" . (getenv("BEHAT_DB_NAME") ?: "hexform"),
			getenv("BEHAT_DB_USER") ?: "hexform_user",
			getenv("BEHAT_DB_PASSWORD") ?: "hexform_pass",
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
		);
	}

	private function ensureTestUser():void {
		$this->database()->prepare(<<<'SQL'
			insert into User (id, email, subscriptionPlan)
			values (:id, :email, 'free')
			on duplicate key update email = values(email)
		SQL)->execute(["id" => self::TEST_USER_ID, "email" => self::TEST_USER_EMAIL]);
	}

	private function deleteTestUser():void {
		$this->database()->prepare("delete from User where id = ?")
			->execute([self::TEST_USER_ID]);
	}

	private function insertSubmission(
		string $title,
		string $submitter,
		string $message,
		bool $isJunk,
	):void {
		$endpoint = $this->endpoint($title);
		$this->submissionSequence++;
		$this->database()->prepare(<<<'SQL'
			insert into Submission (id, endpointId, data, isJunk)
			values (:id, :endpointId, :data, :isJunk)
		SQL)->execute([
			"id" => self::TEST_SUBMISSION_ID . "-$this->submissionSequence",
			"endpointId" => $endpoint["id"],
			"data" => json_encode(["email" => $submitter, "message" => $message]),
			"isJunk" => (int)$isJunk,
		]);
	}

	/** @param array<string, string> $data */
	private function postForm(string $title, array $data):void {
		$endpoint = $this->endpoint($title);
		$url = rtrim((string)getenv("BEHAT_BASE_URL"), "/") . "/f/{$endpoint["code"]}/";
		$context = stream_context_create(["http" => [
			"method" => "POST",
			"header" => "Content-Type: application/x-www-form-urlencoded",
			"content" => http_build_query($data),
			"ignore_errors" => true,
		]]);
		file_get_contents($url, false, $context);
	}

	/** @return array{id: string, code: string} */
	private function endpoint(string $title):array {
		if(!isset($this->endpointList[$title])) {
			throw $this->expectation("No test endpoint named '$title' exists.");
		}
		return $this->endpointList[$title];
	}

	private function findContaining(string $selector, string $text):NodeElement {
		foreach($this->getSession()->getPage()->findAll("css", $selector) as $element) {
			if(str_contains($element->getText(), $text)) {
				return $element;
			}
		}
		throw $this->expectation("Could not find '$text' in the current list.");
	}

	private function requireElement(string $selector, string $description):NodeElement {
		$element = $this->getSession()->getPage()->find("css", $selector);
		if(!$element) {
			throw $this->expectation("Could not find $description.");
		}
		return $element;
	}

	private function acceptNextConfirmation():void {
		$this->getSession()->executeScript("window.confirm = () => true");
	}

	private function expectation(string $message):ExpectationException {
		return new ExpectationException($message, $this->getSession()->getDriver());
	}
}
