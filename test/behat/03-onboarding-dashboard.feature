@javascript
Feature: See progress and usage at a glance
	In order to start collecting useful form data
	As a new account holder
	I want clear onboarding tasks and usage totals

	Scenario: A new account starts with an incomplete checklist
		Given I am signed in
		Then onboarding task "Create your first endpoint" should be incomplete
		And onboarding task "Receive your first submission" should be incomplete
		And onboarding task "Set up junk protection" should be incomplete
		And onboarding task "Set up a forwarder" should be incomplete
		And the dashboard metric "Total submissions" should be "0"
		And the dashboard metric "Active endpoints" should be "0"

	Scenario: Creating and using an endpoint advances onboarding
		Given I have an endpoint named "Contact form"
		And I am signed in
		When someone submits "Please call me" to "Contact form" as "person@example.com"
		And I reload the page
		Then onboarding task "Create your first endpoint" should be complete
		And onboarding task "Receive your first submission" should be complete
		And onboarding task "Set up junk protection" should be complete
		And the dashboard metric "Total submissions" should be "1"
		And the dashboard metric "Active endpoints" should be "1"

	Scenario: Configuring forwarding completes onboarding
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" forwards submissions to "https://hooks.example.com/form"
		And I am signed in
		Then onboarding task "Set up a forwarder" should be complete
