<?php
namespace Stanford\SurveyUITweaks;

include_once ("emLoggerTrait.php");

class SurveyUITweaks extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public $settings;

    function __construct()
    {
        parent::__construct();

        if ($this->getProjectId()) {

            // In project context
            $this->settings = $this->getSubSettings('survey_tweaks');
        }
    }

    function redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        //Remove excess td
        $this->checkFeature('remove_excess_td', 'removeExcessTd', $instrument);

        //Auto scrolling
        $this->checkFeature('autoscroll', 'autoscroll', $instrument);

        //Hide survey queue button
        $this->checkFeature('hide_queue_corner', 'hideQueueCorner', $instrument);

        //Hide font resize button
        $this->checkFeature('hide_font_resize', 'hideFontResize', $instrument);


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
        $hide_reset_button    = @$settings['hide_reset_button'];

        if ($function == 'redcap_survey_page_top')
        {
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

            if ($hide_reset_button) {
                ?>
                <script type="text/javascript">
                    $(document).ready(function () {
                        $(".smalllink").remove();
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

    function hideQueueCorner()
    {
        ?>
        <style>
            #return_corner, #survey_queue_corner {
            display: none !important;
                    }
        </style>
        <?php
    }

    function hideFontResize()
    {
        ?>
        <style>
            #changeFont {
                display: none;
            }
        </style>
        <?php
    }

    function autoscroll()
    {
        ?>
        <style>
            #autoscroll         { background-color: #666; display:inline-block; color: #fff !important; }
            #autoscroll.enabled { background-color: #8C1515; }
        </style>
        <?php
        echo "<script>" . file_get_contents($this->getModulePath() . "/js/autoscroll.js") . "</script>";
    }

    function checkFeature($keyName, $funcName, $instrument, $args = array())
    {
        $global_setting = $this->getProjectSetting("global_" . $keyName);

        if ($global_setting) {
            $this->emDebug("enabling global $funcName");
            call_user_func_array(array($this, $funcName), $args);
        } else {
            foreach ($this->settings as $settings) {
                if ($settings['survey_name'] == $instrument && $settings[$keyName]) {
                    $this->emDebug("enabling  $funcName on $instrument");
                    call_user_func_array(array($this, $funcName), $args);
                }
            }
        }
    }

}