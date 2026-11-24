@mod @mod_supervideo @javascript
Feature: Create and edit Vimeo Super Video activities without local files
  In order to use externally hosted videos
  As a teacher
  I need to save a Vimeo activity without uploading a local file

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: Create and edit a Vimeo activity after switching away from upload
    When I add a "Super Video" to section "1" using the activity chooser
    And I set the field "id_name" to "Vimeo regression video"
    And I set the field "id_origem" to "Upload a Video"
    And I set the field "id_origem" to "Video from Vimeo"
    And I set the field "id_videourl_vimeo" to "https://vimeo.com/123456789"
    And I press "Save and return to course"
    Then I should see "Vimeo regression video" in the "region-main" "region"
    When I open "Vimeo regression video" actions menu
    And I click on "Edit settings" "link" in the "Vimeo regression video" activity
    Then the field "id_videourl_vimeo" matches value "https://vimeo.com/123456789"
    When I set the field "id_videourl_vimeo" to "https://vimeo.com/987654321/abcdef"
    And I press "Save and return to course"
    And I open "Vimeo regression video" actions menu
    And I click on "Edit settings" "link" in the "Vimeo regression video" activity
    Then the field "id_videourl_vimeo" matches value "https://vimeo.com/987654321/abcdef"
