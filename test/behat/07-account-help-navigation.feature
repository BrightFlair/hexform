@javascript
Feature: Find account information and help
  In order to manage my use of HexForm
  As an account holder
  I want consistent navigation, account details, and support guidance

  Scenario Outline: App navigation identifies the current section
    Given I am signed in
    When I open "<section>" from app navigation
    Then I should see "<heading>"
    And "<section>" should be selected in app navigation

    Examples:
      | section     | heading     |
      | Dashboard   | Dashboard   |
      | Endpoints   | Endpoints   |
      | Submissions | Submissions |
      | Junk        | Junk        |
      | Help        | Help        |
      | Account     | Account     |

  Scenario: View account and plan information
    Given I am signed in
    When I open "Account" from app navigation
    Then I should see "fakelogin@authwave.com"
    And I should see "free"
    And I should see "Manage billing with Stripe"

  Scenario: View the help placeholder
    Given I am signed in
    When I open "Help" from app navigation
    Then I should see "Help with HexForm"
    And I should see "Documentation is on its way."
