@javascript
Feature: Choose a subscription after signing in
	In order to use HexForm on the right plan
	As a newly authenticated user
	I want to choose or activate a subscription

	Scenario: An account without a subscription must choose a plan
		Given I sign in without choosing a subscription
		Then I should be on "/app/account/"
		And I should see "You currently do not have an active subscription. Please choose a plan to continue."

	Scenario: Free signup activates the account without Stripe
		Given I sign up for the "free" subscription
		Then I should be on "/app/"
		And my subscription plan should be "free"

	Scenario Outline: Paid signup is ready on the account page
		Given I sign up for the "<plan>" subscription
		Then I should be on "/app/account/?signup=<plan>"
		And the "<plan>" subscription should be selected

		Examples:
			| plan       |
			| developer  |
			| enterprise |
