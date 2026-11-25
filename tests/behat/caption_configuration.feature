@mod @mod_supervideo @javascript
Feature: Configure captions for linked videos
  In order to make videos accessible
  As an editing teacher
  I need to configure WebVTT tracks without a local video upload

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: Save and edit an external WebVTT track
    When I add a "Super Video" to section "1" using the activity chooser
    And I set the field "id_name" to "Caption regression video"
    And I set the field "id_origem" to "Link to a Video"
    And I set the field "id_videourl_link" to "https://example.com/video.mp4"
    And I expand all fieldsets
    And I set the field "id_captionsource_0" to "External HTTPS URL"
    And I set the field "id_captionlabel_0" to "Italiano"
    And I set the field "id_captionlanguage_0" to "it"
    And I set the field "id_captionurl_0" to "https://example.com/italian.vtt"
    And I set the field "id_captiondefault_0" to "Yes"
    And I set the field "id_transcriptenabled" to "Yes"
    And I press "Save and return to course"
    Then I should see "Caption regression video" in the "region-main" "region"
    When I open "Caption regression video" actions menu
    And I click on "Edit settings" "link" in the "Caption regression video" activity
    And I expand all fieldsets
    Then the field "id_captionlabel_0" matches value "Italiano"
    And the field "id_captionurl_0" matches value "https://example.com/italian.vtt"
