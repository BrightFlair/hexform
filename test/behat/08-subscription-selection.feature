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

	Scenario: A cancelled checkout explains that no payment was taken
		Given I sign up for the "developer" subscription
		When I go to "/app/account/?checkout=cancelled&signup=developer"
		Then I should see "Payment was cancelled. Your subscription has not changed."
		And my subscription plan should not be set

	Scenario: An invalid checkout return does not activate a subscription
		Given I sign up for the "developer" subscription
		When I go to "/app/account/?checkout=success&session_id=invalid"
		Then I should see "We could not verify your payment. Please contact support if you were charged."
		And my subscription plan should not be set

	Scenario: An unsupported plan is rejected
		Given I sign in without choosing a subscription
		When I submit an unsupported subscription plan
		Then I should see "Please choose a valid subscription plan."
		And my subscription plan should not be set

	Scenario: An active subscription shows its payment schedule
		Given I have an active "developer" billing subscription
		And I am signed in
		When I open "Account" from app navigation
		Then I should see "GBP 12.00"
		And I should see "1 August 2026"
		And I should see "1 September 2026"

	Scenario: A subscription cancelled at the period end retains paid access
		Given my "developer" billing subscription is cancelled at the end of the period
		And I am signed in
		When I open "Account" from app navigation
		Then I should see "Your subscription is cancelled and will change to Free on"
		And I should see "1 September 2026"
		And I should see "You will not be charged again."
		And I should not see "Next payment"
		And my subscription plan should be "developer"

	Scenario: A failed paid tier change preserves the existing subscription
		Given I have an active "developer" billing subscription
		And I am signed in
		When I choose the "enterprise" subscription plan
		Then I should see "Your subscription could not be changed. Your existing plan remains active."
		And my subscription plan should be "developer"
		And I should have one billing subscription

	Scenario: A failed downgrade does not hide an active Stripe subscription
		Given I have an active "developer" billing subscription
		And I am signed in
		When I choose the "free" subscription plan
		Then I should see "Your paid subscription could not be cancelled. Your plan has not changed."
		And my subscription plan should be "developer"
		And I should have one billing subscription
