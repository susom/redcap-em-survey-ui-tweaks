<?php
namespace Stanford\SurveyUITweaks;

include_once ("emLoggerTrait.php");

class SurveyUITweaks extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public $settings;

    public $global_remove_excess_td;

    function __construct()
    {
        parent::__construct();

        if ($this->getProjectId()) {

            // In project context
            $this->settings = $this->getSubSettings('survey_tweaks');

            $this->global_remove_excess_td = $this->getProjectSetting('global_remove_excess_td');

            $this->emDebug($this->global_remove_excess_td);
        }
    }

    function redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {


        // Remove excess td
        if ($this->global_remove_excess_td) {
            $this->emDebug("Here");
            $this->removeExcessTd();
        } else {
            foreach ($this->settings as $settings) {
                if ($settings['survey_name'] == $instrument && $settings['remove_excess_td']) {
                    $this->removeExcessTd();
                }
            }
        }





        $this->emDebug("SURVEY PAGE TOP - $instrument");
        foreach ($this->settings as $settings) {
            if ($settings['survey_name'] == $instrument) {
                $this->emDebug("Running tweaks on $instrument for " . __FUNCTION__);
                $this->runTweaks(__FUNCTION__, $settings);
            }
        }

    }


    function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        foreach ($this->settings as $settings) {
            if ($settings['survey_name'] == $instrument) {
                $this->emDebug("Running tweaks on $instrument for " . __FUNCTION__);
                $this->runTweaks(__FUNCTION__, $settings);
            }
        }
    }

    function runTweaks($function, $settings) {

        // Convert the keys of settings into variables
        $hide_queue_corner    = @$settings['hide_queue_corner'];
        $remove_excess_td     = @$settings['remove_excess_td'];
        $hide_submit_button   = @$settings['hide_submit_button'];
        $rename_submit_button = @$settings['rename_submit_button'];
        $hide_end_queue       = @$settings['hide_end_queue'];

        if ($function == 'redcap_survey_page_top')
        {
            //hide the survey_queue button on upper right corner
            if ($hide_queue_corner || $this->global_remove_excess_td) {
                ?>
                <style>
                    #return_corner, #survey_queue_corner {
                        display: none !important;
                    }
                </style>
                <?php
            }

//            //remove the excess TD on left if $question_auto_numbering on
//            if ($remove_excess_td) {
//                global $question_auto_numbering;
//                if ($question_auto_numbering == 0) {
//                    ?>
<!--                    <style>-->
<!--                        td.questionnum, td.questionnummatrix {-->
<!--                            display: none !important;-->
<!--                        }-->
<!--                    </style>-->
<!--                    --><?php
//                }
//            }

            if ($hide_submit_button) {
                ?>
                <script type="text/javascript">
                    $(document).ready(function () {
                        $("[name=submit-btn-saverecord]").hide();
                        // //change all the submit buttons to next page buttons
                        // $("button:contains('Submit')").text("Next Page >>");
                    });
                </script>
                <?php
            }

            if ($rename_submit_button) {
                // Hide the TR so you don't see the text get swapped out
                ?>
                <style>
                    tr.surveysubmit {
                        opacity: 0;
                    }
                </style>
                <script type="text/javascript">
                    $(document).ready(function () {
                        var newval = "<?php echo $rename_submit_button ?>";
                        //change all the submit buttons to next page buttons
                        $("button:contains('Submit')").text(newval);
                        $("tr.surveysubmit").css({"opacity":1});
                    });
                </script>
                <?php

            }

        }

        if ($function == 'redcap_survey_complete')
        {
            if ($hide_end_queue) {
                ?>
                <style>
                    #survey_queue {
                        display: none !important;
                    }
                </style>
                <?php
            }
        }
    }


    function removeExcessTd()
    {
        //remove the excess TD on left if $question_auto_numbering on
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

}