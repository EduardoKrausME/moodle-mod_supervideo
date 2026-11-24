@mod @mod_supervideo @javascript
Feature: Configure optional video marker jumps
  In order to allow students to skip optional waiting periods
  As an editing teacher
  I need to see the destination and save the marker configuration

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: Configure a jump between question and answer markers
    When I add a "Super Video" to section "1" using the activity chooser
    And I set the field "id_name" to "Marker regression video"
    And I set the field "id_origem" to "Link to a Video"
    And I set the field "id_videourl_link" to "https://example.com/video.mp4"
    And I expand all fieldsets
    And I set the field with xpath "(//input[@data-marker-time])[1]" to "00:30"
    And I set the field with xpath "(//input[@data-marker-label])[1]" to "Question"
    And I set the field "Allow jump to next marker" to "1"
    Then I should see "No following marker is available."
    When I click on "Add marker" "button"
    And I set the field with xpath "(//input[@data-marker-time])[2]" to "01:30"
    And I set the field with xpath "(//input[@data-marker-label])[2]" to "Answer"
    Then I should see "Destination: 01:30 - Answer"
    When I press "Save and return to course"
    Then I should see "Marker regression video" in the "region-main" "region"
    When I open "Marker regression video" actions menu
    And I click on "Edit settings" "link" in the "Marker regression video" activity
    And I expand all fieldsets
    Then I should see "Destination: 01:30 - Answer"
