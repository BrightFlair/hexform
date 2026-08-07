@javascript
Feature: Homepage
  Scenario: Homepage should show default content
    Given I am on the homepage
    Then I should see "Your HTML form."
    And I should see "Our endpoint."
    And I should see "Collect form data from any static website. No servers, no SDKs."
