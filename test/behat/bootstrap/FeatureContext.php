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
	private ?int $submissionResponseStatus = null;
	/** @var array<string, array{id: string, code: string}> */
	private array $endpointList = [];

	/** @BeforeScenario */
	public function prepareScenario():void {
		$this->getSession()->reset();
		$this->endpointSequence = 0;
		$this->submissionSequence = 0;
		$this->endpointList = [];
		$this->submissionResponseStatus = null;
		$this->deleteTestUser();
	}

	/** @AfterScenario */
	public function cleanScenario():void {
		$this->deleteTestUser();
	}

	/** @Given I am signed in */
	public function iAmSignedIn():void {
		$this->authenticateTestUser("free");
		$this->assertSession()->addressEquals("/app/");
	}

	/** @Given I am not signed in */
	public function iAmNotSignedIn():void {
		$this->getSession()->reset();
	}

	/** @Given I sign in without choosing a subscription */
	public function iSignInWithoutChoosingASubscription():void {
		$this->authenticateTestUser();
	}

	/** @Given I sign up for the :plan subscription */
	public function iSignUpForTheSubscription(string $plan):void {
		$this->authenticateTestUser($plan);
	}

	/** @Then my subscription plan should be :plan */
	public function mySubscriptionPlanShouldBe(string $plan):void {
		$statement = $this->database()->prepare(
			"select subscriptionPlan from User where id = ?",
		);
		$statement->execute([self::TEST_USER_ID]);
		if($statement->fetchColumn() !== $plan) {
			throw $this->expectation("The subscription plan is not '$plan'.");
		}
	}

	/** @Then my subscription plan should not be set */
	public function mySubscriptionPlanShouldNotBeSet():void {
		$statement = $this->database()->prepare(
			"select subscriptionPlan from User where id = ?",
		);
		$statement->execute([self::TEST_USER_ID]);
		if($statement->fetchColumn() !== null) {
			throw $this->expectation("Expected the subscription plan not to be set.");
		}
	}

	/** @Given I have an active :plan billing subscription */
	public function iHaveAnActiveBillingSubscription(string $plan):void {
		$this->ensureTestUser();
		$this->database()->prepare(
			"update User set subscriptionPlan = :plan where id = :id",
		)->execute(["plan" => $plan, "id" => self::TEST_USER_ID]);
		$this->database()->prepare(<<<'SQL'
			insert into BillingSubscription (
				userId, stripeCustomerId, stripeSubscriptionId, plan, status,
				latestPaymentAmount, latestPaymentAt, nextPaymentAmount,
				nextPaymentAt, currency, checkedAt
			) values (
				:userId, 'cus_behat', 'sub_behat', :plan, 'active',
				1200, '2026-08-01 12:00:00', 1200,
				'2026-09-01 12:00:00', 'gbp', current_timestamp
			)
		SQL)->execute(["userId" => self::TEST_USER_ID, "plan" => $plan]);
	}

	/** @Given I have a changeable active :plan billing subscription */
	public function iHaveAChangeableActiveBillingSubscription(string $plan):void {
		$this->iHaveAnActiveBillingSubscription($plan);
		$this->database()->prepare(<<<'SQL'
			update BillingSubscription
			set stripeCustomerId = 'cus_behat_success',
				stripeSubscriptionId = 'sub_success'
			where userId = :userId
		SQL)->execute(["userId" => self::TEST_USER_ID]);
	}

	/** @Given my changeable :plan subscription is cancelled at the end of the period */
	public function myChangeableSubscriptionIsCancelledAtPeriodEnd(string $plan):void {
		$this->iHaveAChangeableActiveBillingSubscription($plan);
		$this->database()->prepare(<<<'SQL'
			update BillingSubscription
			set cancelAtPeriodEnd = true
			where userId = :userId
		SQL)->execute(["userId" => self::TEST_USER_ID]);
	}

	/** @Given my :plan billing subscription is cancelled at the end of the period */
	public function myBillingSubscriptionIsCancelledAtPeriodEnd(string $plan):void {
		$this->iHaveAnActiveBillingSubscription($plan);
		$this->database()->prepare(<<<'SQL'
			update BillingSubscription
			set cancelAtPeriodEnd = true
			where userId = :userId
		SQL)->execute(["userId" => self::TEST_USER_ID]);
	}

	/** @Then the :plan subscription should be selected */
	public function theSubscriptionShouldBeSelected(string $plan):void {
		$this->assertSession()->elementExists(
			"css",
			"input[name='subscriptionPlan'][value='$plan']:checked",
		);
	}

	/** @When I submit an unsupported subscription plan */
	public function iSubmitAnUnsupportedSubscriptionPlan():void {
		$this->getSession()->executeScript(<<<'JS'
			const option = document.querySelector("input[name='subscriptionPlan'][value='developer']");
			option.value = "unsupported";
			option.checked = true;
		JS);
		$this->pressButton("Continue with selected plan");
	}

	/** @When I choose the :plan subscription plan */
	public function iChooseTheSubscriptionPlan(string $plan):void {
		$this->visitPath("/app/account/");
		$option = $this->requireElement(
			"input[name='subscriptionPlan'][value='$plan']",
			"$plan subscription option",
		);
		$this->getSession()->executeScript(
			"const option = document.querySelector("
			. json_encode("input[name='subscriptionPlan'][value='$plan']")
			. "); option.checked = true;",
		);
		$this->pressButton("Continue with selected plan");
	}

	/** @Then I should have one billing subscription */
	public function iShouldHaveOneBillingSubscription():void {
		$statement = $this->database()->prepare(
			"select count(*) from BillingSubscription where userId = ?",
		);
		$statement->execute([self::TEST_USER_ID]);
		if((int)$statement->fetchColumn() !== 1) {
			throw $this->expectation("Expected exactly one billing subscription.");
		}
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

	/** @Given the endpoint :title ignores submission keys :keys */
	public function endpointIgnoresSubmissionKeys(string $title, string $keys):void {
		$endpoint = $this->endpoint($title);
		$this->database()->prepare(
			"update Endpoint set ignoredKeys = :keys where id = :id",
		)->execute(["keys" => $keys, "id" => $endpoint["id"]]);
	}

	/** @Given the endpoint :title has a pending email forwarder :email with code :code */
	public function endpointHasPendingEmailForwarder(
		string $title,
		string $email,
		string $code,
	):void {
		$endpoint = $this->endpoint($title);
		$this->database()->prepare(<<<'SQL'
			insert into EmailForwarder (
				id, endpointId, email, confirmationCode, confirmationCreatedAt
			) values (:id, :endpointId, :email, :code, current_timestamp)
		SQL)->execute([
			"id" => "behat-forwarder-" . count($this->endpointList),
			"endpointId" => $endpoint["id"],
			"email" => $email,
			"code" => $code,
		]);
	}

	/** @Given the endpoint :title has a resendable email forwarder :email with code :code */
	public function endpointHasResendableEmailForwarder(
		string $title,
		string $email,
		string $code,
	):void {
		$this->endpointHasPendingEmailForwarder($title, $email, $code);
		$this->database()->prepare(<<<'SQL'
			update EmailForwarder
			set confirmationCreatedAt=:createdAt
			where email=:email
		SQL)->execute([
			"createdAt" => (new DateTimeImmutable("-3 minutes"))->format("Y-m-d H:i:s"),
			"email" => $email,
		]);
	}

	/** @Then the email forwarder :email should have a new confirmation code */
	public function emailForwarderShouldHaveNewConfirmationCode(string $email):void {
		$statement = $this->database()->prepare(
			"select confirmationCode from EmailForwarder where email=:email",
		);
		$statement->execute(["email" => $email]);
		if($statement->fetchColumn() === "12345") {
			throw $this->expectation("The confirmation code was not regenerated.");
		}
	}

	/** @When I delete the email forwarder :email */
	public function deleteEmailForwarder(string $email):void {
		$row = $this->findContaining("[data-email-forwarders] li", $email);
		$this->acceptNextConfirmation();
		$row->pressButton("Delete");
	}

	/** @Then the forwarding address :email should be confirmed */
	public function forwardingAddressShouldBeConfirmed(string $email):void {
		$row = $this->findContaining("[data-email-forwarders] li", $email);
		if(!str_contains($row->getText(), "Confirmed")) {
			throw $this->expectation("The forwarding address '$email' is not confirmed.");
		}
	}

	/** @Then the audit log should contain a successful :action for :email */
	public function auditShouldContainSuccessfulAction(string $action, string $email):void {
		$this->assertAuditEntry($action, "succeeded", $email);
	}

	/** @Then the audit log should contain a failed :action for :email */
	public function auditShouldContainFailedAction(string $action, string $email):void {
		$this->assertAuditEntry($action, "failed", $email);
	}

	/** @Then the audit log should not contain :action for :email */
	public function auditShouldNotContainAction(string $action, string $email):void {
		$statement = $this->database()->prepare(<<<'SQL'
			select count(*)
			from AuditLog
			where action=:action and json_unquote(json_extract(context, '$.email'))=:email
			SQL);
		$statement->execute(["action" => $action, "email" => $email]);
		if((int)$statement->fetchColumn() !== 0) {
			throw $this->expectation("Audit log unexpectedly contains '$action' for '$email'.");
		}
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

	/** @When someone submits WebEngine fields to :title */
	public function someoneSubmitsWebEngineFieldsTo(string $title):void {
		$this->postForm($title, [
			"message" => "Keep me",
			"do" => "submit",
			"csrf-token" => "token",
			"__component" => "contact-form",
			"tracking-id" => "internal-tracking-value",
		]);
	}

	/** @Then the latest submission to :title should not contain key :key */
	public function latestSubmissionShouldNotContainKey(string $title, string $key):void {
		$data = $this->latestSubmissionData($title);
		if(array_key_exists($key, $data)) {
			throw $this->expectation("Latest submission unexpectedly contains '$key'.");
		}
	}

	/** @Then the latest submission to :title should contain key :key */
	public function latestSubmissionShouldContainKey(string $title, string $key):void {
		$data = $this->latestSubmissionData($title);
		if(!array_key_exists($key, $data)) {
			throw $this->expectation("Latest submission does not contain '$key'.");
		}
	}

	/** @When someone submits to an unknown endpoint */
	public function someoneSubmitsToUnknownEndpoint():void {
		$url = rtrim((string)getenv("BEHAT_BASE_URL"), "/") . "/f/unknown-endpoint/";
		$context = stream_context_create(["http" => [
			"method" => "POST",
			"header" => "Content-Type: application/x-www-form-urlencoded",
			"content" => "message=test",
			"ignore_errors" => true,
		]]);
		file_get_contents($url, false, $context);
		$this->submissionResponseStatus = (int)preg_replace(
			'/^HTTP\/\S+\s+(\d+).*$/',
			'$1',
			$http_response_header[0] ?? "0",
		);
	}

	/** @Then the submission response status should be :status */
	public function submissionResponseStatusShouldBe(int $status):void {
		if($this->submissionResponseStatus !== $status) {
			throw $this->expectation(
				"Submission response was {$this->submissionResponseStatus}, expected $status.",
			);
		}
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

	/** @return array<string, mixed> */
	private function latestSubmissionData(string $title):array {
		$endpoint = $this->endpoint($title);
		$statement = $this->database()->prepare(
			"select data from Submission where endpointId = ? order by createdAt desc limit 1",
		);
		$statement->execute([$endpoint["id"]]);
		$data = $statement->fetchColumn();
		if(!is_string($data)) {
			throw $this->expectation("No submission found for '$title'.");
		}

		/** @var array<string, mixed> */
		return json_decode($data, true, flags: JSON_THROW_ON_ERROR);
	}

	/** @return array{id: string, code: string} */
	private function endpoint(string $title):array {
		if(!isset($this->endpointList[$title])) {
			throw $this->expectation("No test endpoint named '$title' exists.");
		}
		return $this->endpointList[$title];
	}

	private function authenticateTestUser(?string $signupPlan = null):void {
		$signInPath = "/?debug-auth=" . self::TEST_USER_ID;
		if($signupPlan !== null) {
			$signInPath .= "&signup=" . urlencode($signupPlan);
		}

		$this->visitPath($signInPath);
		if(parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH) === "/login/") {
			$this->getSession()->reset();
			$this->visitPath($signInPath);
		}
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

	private function assertAuditEntry(string $action, string $outcome, string $email):void {
		$statement = $this->database()->prepare(<<<'SQL'
			select count(*) from AuditLog
			where actorUserId=:userId and action=:action and outcome=:outcome
			and json_unquote(json_extract(context, '$.email'))=:email
		SQL);
		$statement->execute([
			"userId" => self::TEST_USER_ID,
			"action" => $action,
			"outcome" => $outcome,
			"email" => $email,
		]);
		if((int)$statement->fetchColumn() < 1) {
			throw $this->expectation("No $outcome '$action' audit entry exists for $email.");
		}
	}
}
