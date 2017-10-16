<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 10/2/17
 * Time: 2:47 PM
 */

namespace Stanford\SurveyUITweaks;

class SurveyUITweaks extends \ExternalModules\AbstractExternalModule
{

    function hook_survey_complete() {
        $hide_complete = $this->getProjectSetting('hide_queue_end');

        if ($hide_complete) {

            ?>
            <style>
                #survey_queue {
                    display: none !important;
                }
            </style>
            <?php

        }
    }

    function hook_survey_page_top()
    {
        $hide_corner = $this->getProjectSetting('hide_queue_corner');
        $remove_excess_td = $this->getProjectSetting('remove_excess_td');
        $hide_submit_button = $this->getProjectSetting('hide_submit_button');
        $rename_submit_button = $this->getProjectSetting('rename_submit_button');

        //hide the survey_queue button on upper right corner
        if ($hide_corner) {
            ?>
            <style>
                #return_corner, #survey_queue_corner {
                    display: none !important;
                }
            </style>

            <?php
        }

        //remove the excess TD on left if $question_auto_numbering on
        if ($remove_excess_td) {

            global $question_auto_numbering;
            if ($question_auto_numbering == 0) {
                ?>
                <style>
                    td.questionnum, td.questionnummatrix {
                        display: none !important;
                    }
                </style>
                <?php
            }
        }

        if ($hide_submit_button) {
            ?>
            <script type="text/javascript">
                $(document).ready(function() {

                    $("[name=submit-btn-saverecord]").hide();

                    //change all the submit buttons to next page buttons
                    $("button:contains('Submit')").text("Next Page >>");
                });
            </script>
            <?php

        }

        if ($rename_submit_button) {
            ?>
            <script type="text/javascript">
                $(document).ready(function() {
                    var newval = "<?php echo $rename_submit_button ?>";


                    //change all the submit buttons to next page buttons
                    $("button:contains('Submit')").text(newval);
                });
            </script>
            <?php

        }
    }
}