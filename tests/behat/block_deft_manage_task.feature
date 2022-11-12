@block @block_deft @javascript
Feature: The deft response block allows managers and teachers to interact with students in polls and chats
  In order to use deft response to interact
  As a manager
  I need to configure tasks in the block

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | teacher1 | teacher   | 1        | teacher1@example.com |
      | manager1 | manager   | 1        | manager1@example.com |
    And the following "categories" exist:
      | name        | category | idnumber |
      | Category A  | 0        | CATA     |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | teacher1 | C1     | teacher |
    And the following "blocks" exist:
      | blockname  | contextlevel | reference | pagetypepattern | defaultregion |
      | deft       | System       | 1         | site-index      | side-post     |

  Scenario: Manager adds text task which is not visible
    Given I log in as "admin"
    And I am on site homepage
    And I change window size to "large"
    When I follow "Manage"
    And I click on "Add text" "button"
    And I set the following fields to these values:
    | Name         | Welcome     |
    | Page content | Hello World |
    And I press "Save changes"
    And I am on site homepage
    Then I should not see "Hello World"

  Scenario: Manager adds text task which is visible
    Given I log in as "admin"
    And I am on site homepage
    And I change window size to "large"
    When I follow "Manage"
    And I click on "Add text" "button"
    And I set the following fields to these values:
    | Name         | Welcome     |
    | Page content | Hello World |
    And I press "Save changes"
    And I set the following fields to these values:
    | Visible         | 1 |
    | Show title      | 1 |
    And I press "Save changes"
    And I am on site homepage
    Then I should see "Hello World"
