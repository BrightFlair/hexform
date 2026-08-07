@javascript
Feature: Keep junk away from genuine submissions
  In order to focus on real messages
  As an account holder
  I want honeypot submissions separated and reviewable

  Scenario: A honeypot submission is placed in junk
    Given I have an endpoint named "Contact form"
    And I am signed in
    When a bot submits "Buy everything now" to "Contact form" as "bot@example.com"
    And I open "Junk" from app navigation
    Then the message list should contain "bot@example.com"
    And the message list should contain "Buy everything now"

  Scenario: Restore a genuine message from junk
    Given I have an endpoint named "Contact form"
    And the endpoint "Contact form" has caught a junk submission from "customer@example.com" saying "This is genuine"
    And I am signed in
    When I open "Junk" from app navigation
    And I mark the message from "customer@example.com" as not junk
    Then the message list should not contain "customer@example.com"
    When I open "Submissions" from app navigation
    Then the message list should contain "customer@example.com"

  Scenario: Permanently delete junk
    Given I have an endpoint named "Contact form"
    And the endpoint "Contact form" has caught a junk submission from "spam@example.com" saying "Unwanted"
    And I am signed in
    When I open "Junk" from app navigation
    And I delete the message from "spam@example.com"
    Then the message list should not contain "spam@example.com"
