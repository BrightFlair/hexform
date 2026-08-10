@javascript
Feature: Manage form endpoints
	In order to connect forms on my websites
	As an account holder
	I want to create, configure, find, and remove endpoints

	Scenario: Create a first endpoint
		Given I am signed in
		When I open "Endpoints" from app navigation
		And I follow "New endpoint"
		And I fill in "Title" with "Newsletter"
		And I fill in "Client host" with "https://newsletter.example.com"
		And I press "Create endpoint"
		Then I should see "Newsletter"
		And I should see "Connect your form"
		And the endpoint list should contain "Newsletter"

	Scenario: Configure how submissions are displayed and retained
		Given I have an endpoint named "Contact form"
		And I am signed in
		When I configure the endpoint "Contact form"
		And I fill in "Title" with "Sales enquiries"
		And I fill in "Main field" with "message"
		And I fill in "Submitter identity field" with "email"
		And I fill in "Ignored keys" with "do,csrf-token,__component,tracking-id"
		And I select "6 months" from "Save submissions"
		And I fill in "Maximum submissions per month" with "250"
		And I press "Save changes"
		Then the "Title" field should contain "Sales enquiries"
		And the "Maximum submissions per month" field should contain "250"
		And the "Ignored keys" field should contain "do,csrf-token,__component,tracking-id"
		And the endpoint list should contain "Sales enquiries"

	Scenario: Open an endpoint-specific inbox
		Given I have an endpoint named "Contact form"
		And I am signed in
		When I open the inbox for endpoint "Contact form"
		Then I should see "Submissions"
		And the inbox should be filtered to endpoint "Contact form"

	Scenario: Delete an endpoint
		Given I have an endpoint named "Old campaign"
		And I am signed in
		When I delete the endpoint "Old campaign"
		Then the endpoint list should not contain "Old campaign"

	Scenario: Confirm an email forwarding address
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" has a pending email forwarder "team@example.com" with code "12345"
		And I am signed in
		When I configure the endpoint "Contact form"
		And I fill in "Confirmation code" with "12345"
		And I press "Confirm"
		Then I should see "team@example.com"
		And I should see "Confirmed"
		And the audit log should contain a successful "confirm" for "team@example.com"

	Scenario: Reject an incorrect email confirmation code with feedback
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" has a pending email forwarder "team@example.com" with code "12345"
		And I am signed in
		When I configure the endpoint "Contact form"
		And I fill in "Confirmation code" with "99999"
		And I press "Confirm"
		Then I should see "The confirmation code is incorrect. Please try again."
		And the audit log should contain a failed "confirm" for "team@example.com"

	Scenario: A fresh confirmation code cannot be resent yet
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" has a pending email forwarder "team@example.com" with code "12345"
		And I am signed in
		When I configure the endpoint "Contact form"
		Then I should not see "Resend code"

	Scenario: An expired confirmation code can be resent
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" has a resendable email forwarder "team@example.com" with code "12345"
		And I am signed in
		When I configure the endpoint "Contact form"
		And I press "Resend code"
		Then the email forwarder "team@example.com" should have a new confirmation code
		And the audit log should contain a successful "resend-confirmation" for "team@example.com"

	Scenario: Delete an email forwarding address
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" has a pending email forwarder "remove@example.com" with code "12345"
		And I am signed in
		When I configure the endpoint "Contact form"
		And I delete the email forwarder "remove@example.com"
		Then I should not see "remove@example.com"
		And the audit log should contain a successful "delete" for "remove@example.com"
