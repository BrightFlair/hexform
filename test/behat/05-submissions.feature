@javascript
Feature: Receive and manage form submissions
	In order to act on messages sent through my forms
	As an account holder
	I want to browse, filter, read, and delete submissions

	Scenario: A form submission appears in the inbox
		Given I have an endpoint named "Contact form"
		And I am signed in
		When someone submits "I need a quote" to "Contact form" as "buyer@example.com"
		And I open "Submissions" from app navigation
		Then the message list should contain "buyer@example.com"
		And the message list should contain "I need a quote"

	Scenario: Read all submitted fields
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" has received a submission from "reader@example.com" saying "A detailed enquiry"
		And I am signed in
		When I open "Submissions" from app navigation
		And I read the message from "reader@example.com"
		Then I should see "Submitted data"
		And I should see "reader@example.com"
		And I should see "A detailed enquiry"

	Scenario: Filter messages to one endpoint
		Given I have an endpoint named "Contact form"
		And I have an endpoint named "Newsletter"
		And the endpoint "Contact form" has received a submission from "contact@example.com" saying "Contact message"
		And the endpoint "Newsletter" has received a submission from "news@example.com" saying "Newsletter message"
		And I am signed in
		When I open "Submissions" from app navigation
		And I filter the inbox by endpoint "Newsletter"
		Then the message list should contain "news@example.com"
		And the message list should not contain "contact@example.com"

	Scenario: Delete a submission
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" has received a submission from "delete@example.com" saying "Temporary message"
		And I am signed in
		When I open "Submissions" from app navigation
		And I delete the message from "delete@example.com"
		Then the message list should not contain "delete@example.com"
