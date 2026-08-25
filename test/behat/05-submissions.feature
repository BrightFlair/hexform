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

	Scenario: Redirect to the client host without a confirmation URL
		Given I have an endpoint named "Contact form"
		When someone submits "Please reply" to "Contact form" as "visitor@example.com"
		Then the submission response should redirect to "https://example.com"

	Scenario: Read all submitted fields
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" has received a submission from "reader@example.com" saying "A detailed enquiry"
		And I am signed in
		When I open "Submissions" from app navigation
		And I read the message from "reader@example.com"
		Then I should see "Submitted data"
		And I should see "reader@example.com"
		And I should see "A detailed enquiry"

	Scenario: Show a webhook response on the submission
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" forwards submissions to the test webhook
		When someone submits "Forward this" to "Contact form" as "hook@example.com"
		And I am signed in
		And I open "Submissions" from app navigation
		And I read the message from "hook@example.com"
		Then I should see "Forwarding activity"
		And I should see "Webhook"
		And I should see "Succeeded"
		And I should see "HTTP 202"

	Scenario: Show an SMTP response on the submission
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" has a confirmed email forwarder "team@example.com"
		When someone submits "Email this" to "Contact form" as "sender@example.com"
		And I am signed in
		And I open "Submissions" from app navigation
		And I read the message from "sender@example.com"
		Then I should see "Forwarding activity"
		And I should see "Email"
		And I should see "team@example.com"
		And I should see "SMTP"

	Scenario: Ignore WebEngine fields in submitted data
		Given I have an endpoint named "Contact form"
		And the endpoint "Contact form" ignores submission keys "do,csrf-token,__component,tracking-id"
		When someone submits WebEngine fields to "Contact form"
		Then the latest submission to "Contact form" should not contain key "do"
		And the latest submission to "Contact form" should not contain key "csrf-token"
		And the latest submission to "Contact form" should not contain key "__component"
		And the latest submission to "Contact form" should not contain key "tracking-id"
		And the latest submission to "Contact form" should contain key "message"

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

	Scenario: An unknown endpoint returns not found
		When someone submits to an unknown endpoint
		Then the submission response status should be 404
