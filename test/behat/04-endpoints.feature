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
    And I select "6 months" from "Save submissions"
    And I fill in "Maximum submissions per month" with "250"
    And I press "Save changes"
    Then the "Title" field should contain "Sales enquiries"
    And the "Maximum submissions per month" field should contain "250"
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
