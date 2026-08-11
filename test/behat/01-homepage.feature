@javascript
Feature: Understand HexForm before joining
	In order to decide whether HexForm suits my forms
	As a visitor
	I want to understand the product, integrations, and plans

	Scenario: The homepage explains the core service
		Given I am on the homepage
		Then I should see "Your HTML form."
		And I should see "Our endpoint."
		And I should see "Collect form data from any static website. No servers, no SDKs."
		And I should see "A static HTML form that POSTs to HexForm."

	Scenario: The homepage describes integrations and plans
		Given I am on the homepage
		Then I should see "Send data to your favourite tools"
		And I should see "Zapier"
		And I should see "Slack"
		And I should see "Start free. Scale when ready."
		And I should see "Developer"
		And I should see "Enterprise"

	Scenario: A visitor can begin creating an account
		Given I am on the homepage
		When I follow "Start collecting - free"
		Then I should see "Get started in 60 seconds."
		And I should see "Email address"
