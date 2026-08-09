@javascript
Feature: Access the private application
	In order to keep my form data private
	As an account holder
	I want application pages to require authentication

	Scenario: A visitor cannot open the dashboard
		Given I am not signed in
		When I go to "/app/"
		Then I should not see "Dashboard"

	Scenario: A signed-in user reaches their dashboard
		Given I am signed in
		Then I should see "Dashboard"
		And "Dashboard" should be selected in app navigation

	Scenario: A user can sign out
		Given I am signed in
		When I open "Log out" from app navigation
		Then I should see "Your HTML form."
		When I go to "/app/"
		Then I should not see "Dashboard"
