# Survey UI Tweaks

This module provides end users with the ability to apply certain survey tweaks either globally to all surveys in the project or on a survey-by-survey basis.

## Tweaks Included:

1. **Remove Excess TD:** When you don't want to waste the left 1" of the survey, you can turn off 'auto-numbering' of survey questions and enable this tweak.

1. **Hide Submit Button:** In some cases you want a survey to be a 'dead-end'.  Perhaps to display read-only information or to stop an auto-continue chain.  With this tweak you can remove the Submit button from the page.

1. **Hide Queue Corner:** Sometimes you use the survey queue but do not want the button in the upper-right corner to appear on each survey.

1. **Hide Font Resize:** Sometimes you don't want users to see the +/- font resize options

1. **AutoScroll:** Autoscroll is a nifty little add-on that moves the next question to the top of the window after completing any radio/dropdown question.  It is great for mobile surveys where the user doesn't have to scroll with their thumb.  Also, it supports a client-side cookie to remember if a user wants to deactivate the autoscroll feature.

1. **Rename Submit Button:** In some cases, you want to rename the submit button on a survey.  Perhaps to, "Next Section" instead?  This allows you to do just that.

1. **Hide End Queue:** At the end of a survey where the survey queue is enabled, you can HIDE the summary table that shows where someone is on the queue.

1. **Hide Reset Button:** In some cases you want to hide the 'reset link' for radio questions.

1. **Rename "Next Page >>" and "<< Previous Page" Buttons":** You may want to change the language on these buttons when breaking up a survey by section.

1. **Hide required field text:** De-clutter your page on surveys with many required fields.

1. **Save and Return without email:** Remove the option to send a return link to the user's email. Users must save the url themselves.

1. **Survey Login on Save** You can use this feature to prevent users from having to perform a survey-login during
 the initial data entry.  To use, have a non-login survey where they enter their 'code'.  Set this survey as the
  EM option.  Then, when it is saved, it will automatically make the cookies as though the user just logged in.

1. **Survey Duration Fields:** Designate text fields for capture of the cumulative duration that a survey respondent spends on the survey page where the field is located. Fields can be designated either via the project module configuration dialog or by specifing the action tag @SURVEY-DURATION in Field Annotations.

1. **Change the amount of screen space a survey takes up:** You may want your survey to appear slightly wider

What's next?  Up to you.  Post an issue as a request on the github site or fork and make a pull request on your own.

Versions:
- 0.1.0: Initial Development Version
- 0.2.0: Added global function
- 1.0.0: Initial repo release
- 1.0.1: Changed class so as not to have array constants
- 1.1.5: Fixed matrix ranking bug
- 1.2.0: Fixed renaming of buttons bug (REDCap 12+), form level renaming of buttons now takes priority over global renaming

Notes:
As of version 1.2.0, instrument level configuration will take priority over global configurations
