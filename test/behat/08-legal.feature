@javascript
Feature: Understand the legal relationship
	In order to make an informed decision about HexForm
	As a visitor or account holder
	I want the terms and privacy policy to remain easy to find

	Scenario: Read the terms from the homepage
		Given I am on the homepage
		When I follow "Terms"
		Then I should see "Terms of service"
		And I should see "Using HexForm"

	Scenario: Read the privacy policy from the homepage
		Given I am on the homepage
		When I follow "Privacy"
		Then I should see "Privacy notice"
		And I should see "Cookies and local storage"
		And I should see "We never sell personal information"

	Scenario: Legal pages remain available inside the application
		Given I am signed in
		When I follow "Privacy"
		Then I should see "Privacy notice"
		When I follow "Terms"
		Then I should see "Terms of service"
