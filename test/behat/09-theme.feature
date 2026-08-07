@javascript
Feature: Remember a preferred colour theme
  In order to read HexForm comfortably
  As a visitor
  I want my colour preference retained while I browse

  Scenario: Theme starts by following the system
    Given I am on the homepage
    Then the theme preference should be "system"

  Scenario: A chosen theme follows the visitor to another page
    Given I am on the homepage
    When I change the colour theme
    Then a non-system theme preference should be stored
    And my theme preference should remain after opening "/legal/privacy/"
